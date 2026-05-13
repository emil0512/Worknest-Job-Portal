<?php
session_start();
include("../db.php");

function to_words($text) {
    $clean = strtolower($text ?? '');
    $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
    return array_unique($words);
}
function calculate_match_score($job_text, $resume_text, $job_title = '', $resume_education = '', $resume_experience = '', $resume_certs = '') {
    if (empty($resume_text) || empty($job_text)) return 0;

    $job_words = array_map('strtolower', to_words($job_text));
    $resume_words = array_map('strtolower', to_words($resume_text));
    if (empty($job_words) || empty($resume_words)) return 0;

    // 1️⃣ Base score: matched words / total job words * 100
    $matches = array_intersect($job_words, $resume_words);
    $overlap = count(array_unique($matches));
    $total_job_words = count(array_unique($job_words));
    $base_score = $total_job_words > 0 ? ($overlap / $total_job_words) * 100 : 0;

    // 2️⃣ Title match boost (0–15)
    $title_words = array_map('strtolower', to_words($job_title));
    $title_matches = count(array_intersect($title_words, $resume_words));
    $title_boost = min($title_matches * 5, 15);

    // 3️⃣ Resume density boost (0–10)
    $resume_word_count = count($resume_words);
    if ($resume_word_count < 300) $density_boost = 2;
    elseif ($resume_word_count < 400) $density_boost = 4;
    elseif ($resume_word_count < 500) $density_boost = 6;
    elseif ($resume_word_count < 650) $density_boost = 8;
    else $density_boost = 10;

    // 4️⃣ Rare/long word boost (0–10)
    $rare_matches = 0;
    foreach ($matches as $w) {
        if (strlen($w) >= 7) $rare_matches++;
    }
    $rare_boost = min($rare_matches * 2, 10);

    // 5️⃣ Education boost (0–10)
    $edu_matches = count(array_intersect(to_words($resume_education), to_words($job_text)));
    $education_boost = min($edu_matches * 2, 10);

    // 6️⃣ Experience boost (0–15)
    $exp_years = (int)$resume_experience;
    $experience_boost = min($exp_years * 3, 15); // Example: 1 year = 3 points, max 15

    // 7️⃣ Certification boost (0–10)
    $cert_matches = count(array_intersect(to_words($resume_certs), to_words($job_text)));
    $cert_boost = min($cert_matches * 2, 10);

    // Raw total score
    $raw_score = $base_score + $title_boost + $density_boost + $rare_boost + $education_boost + $experience_boost + $cert_boost;

    // Raw max possible = 170
    $normalized_score = min(100, round(($raw_score / 170) * 100));

    return $normalized_score;
}

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$keyword = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');

// Fetch user's parsed resume data
$resume_stmt = $conn->prepare("SELECT matched_keywords FROM parsed_resume_data WHERE js_id = ?");
$resume_stmt->bind_param("i", $js_id);
$resume_stmt->execute();
$resume_data = $resume_stmt->get_result()->fetch_assoc();
$resume_keywords = $resume_data['matched_keywords'] ?? '';
$resume_stmt->close();

// Decide mode (default = ask user via popup)
$mode = $_GET['mode'] ?? ''; // 'personalized' or 'general'

// Fetch all open jobs
$all_jobs = [];
$jobs_res = $conn->query("
    SELECT j.*, e.company_name
    FROM jobs j
    INNER JOIN employer_profiles e ON j.emp_id = e.emp_id
    WHERE j.status = 'open' ");

if ($jobs_res) {
    while ($job = $jobs_res->fetch_assoc()) {
if ($mode === 'personalized') {
    // Personalized: calculate score
    $job_text = $job['title'] . ' ' . $job['description'] . ' ' . ($job['keywords'] ?? '');
    $score = calculate_match_score($job_text, $resume_keywords, $job['title']);

    // Ensure score is never 0 → baseline
    if ($score <= 0) {
        $score = 0;
    }

    // Insert or update match score
    $upsert_stmt = $conn->prepare("
        INSERT INTO job_matches (js_id, job_id, score, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE 
            score = VALUES(score),
            updated_at = CURRENT_TIMESTAMP
    ");
    $upsert_stmt->bind_param("iii", $js_id, $job['id'], $score);
    $upsert_stmt->execute();
    $upsert_stmt->close();

    // Attach score for display
    $job['__score'] = $score;
} else {
    $job['__score'] = null;
}
        $all_jobs[] = $job;
    }
}


// Log search
if ($keyword !== '' || $location !== '') {
    if ($log_stmt = $conn->prepare("INSERT INTO searches (js_id, keyword, location) VALUES (?, ?, ?)")) {
        $log_stmt->bind_param("iss", $js_id, $keyword, $location);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// Applied & Saved jobs
$applied_jobs = [];
$applied_stmt = $conn->prepare("SELECT job_id FROM applications WHERE user_id = ?");
$applied_stmt->bind_param("i", $js_id);
$applied_stmt->execute();
$applied_res = $applied_stmt->get_result();
while ($row = $applied_res->fetch_assoc()) {
    $applied_jobs[] = (int)$row['job_id'];
}
$applied_stmt->close();

$saved_jobs = [];
$saved_stmt = $conn->prepare("SELECT job_id FROM saved_jobs WHERE user_id = ?");
$saved_stmt->bind_param("i", $js_id);
$saved_stmt->execute();
$saved_res = $saved_stmt->get_result();
while ($row = $saved_res->fetch_assoc()) {
    $saved_jobs[] = (int)$row['job_id'];
}
$saved_stmt->close();

// Apply search filters
if ($keyword !== '') {
    $kw = strtolower($keyword);
    $all_jobs = array_filter($all_jobs, function ($job) use ($kw) {
        return str_contains(strtolower($job['title']), $kw)
            || str_contains(strtolower($job['description']), $kw)
            || str_contains(strtolower($job['keywords']), $kw)
            || str_contains(strtolower($job['location']), $kw);
    });
}
if ($location !== '') {
    $loc = strtolower($location);
    $all_jobs = array_filter($all_jobs, function ($job) use ($loc) {
        return str_contains(strtolower($job['location']), $loc);
    });
}

if ($mode === 'personalized') {
    // Sort by match score first
    usort($all_jobs, function ($a, $b) {
        return $b['__score'] <=> $a['__score']
            ?: strcmp($b['posted_on'], $a['posted_on']);
    });
} else {
    // General mode → sort only by date
    usort($all_jobs, function ($a, $b) {
        return strcmp($b['posted_on'], $a['posted_on']);
    });
}


// Split into suggested + search results
$suggested = [];
$jobs = [];

if (!empty($all_jobs)) {
    if ($mode === 'personalized') {
        // Personalized → top 3 suggested, rest in search results
        $suggested = array_slice($all_jobs, 0, 3);
        $suggested_ids = array_map(fn($j) => (int)$j['id'], $suggested);
        $jobs = array_values(array_filter($all_jobs, fn($j) => !in_array((int)$j['id'], $suggested_ids, true)));
    } else {
        // General → all jobs go to results, suggestions stay empty
        $jobs = $all_jobs;
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Jobs | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0a0f, #0f0f1e);
            color: #e0e0ff;
            padding: 40px 20px 20px;
        }
        .sidebar {
            width: 330px;
            background: rgba(20,20,40,0.95);
            height: 100vh;
            position: fixed;
            top: 0;
            left: -260px;
            padding: 60px 20px 100px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: hidden;
            border-right: 2px solid rgba(138,43,226,0.5);
        }
        .sidebar.open { left: 0; overflow-y: auto; }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-thumb {
            background-color: #9d4edd;
            border-radius: 8px;
        }
        .sidebar::-webkit-scrollbar-track { background: rgba(30,30,50,0.8); }
        .sidebar h3 {
            margin-top: -30px;
            margin-bottom: 30px;
            color: #bb86fc;
            font-size: 28px;
            text-align: center;
        }
        .sidebar a {
            display: block;
            padding: 16px;
            margin-bottom: 10px;
            text-decoration: none;
            color: #cfcfff;
            font-weight: 600;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        .sidebar a:hover { background: rgba(138,43,226,0.3); }
        .toggle-btn {
            position: fixed;
            top: 20px;
            left: 10px;
            font-size: 18px;
            background: rgba(138,43,226,0.2);
            color: #bb86fc;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            z-index: 1001;
            transition: background 0.2s ease;
        }
        .toggle-btn:hover { background: rgba(138,43,226,0.4); }
        .main {
            margin-left: 300px;
            padding: 20px;
            max-width: 1000px;
            margin-right: auto;
        }
        .main h2 {
            text-align: center;
            color: #bb86fc;
            font-size: 30px;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 0 0 6px #6c63ff22;
        }
        .search-bar {
            max-width: 720px;
            margin: 0 auto 30px;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
        }
        .search-bar input {
            padding: 10px 15px;
            border-radius: 6px;
            border: none;
            outline: none;
            font-size: 14px;
        }
        .search-bar button {
            background: #6c63ff;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .search-bar button:hover { background: #7d73ff; }
        #bgVideo {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -3;
            filter: brightness(0.35) contrast(1.2);
        }
        .chip {
            display: inline-block;
            padding: 2px 8px;
            font-size: 12px;
            background: #bb86fc33;
            color: #fff;
            border-radius: 12px;
            margin-left: 8px;
        }
        .job-listing { display: grid; gap: 16px; margin-bottom: 30px; }
        .job-card {
            background: rgba(25,25,45,0.95);
            border-left: 5px solid #6c63ff;
            padding: 18px 20px;
            border-radius: 10px;
            color: #e0e0ff;
            box-shadow: 0 0 8px #6c63ff22;
            transition: transform 0.2s ease, box-shadow 0.3s ease;
        }
        .job-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 20px #6c63ff33;
        }
        .job-card h3 { margin: 0 0 6px; color: #bb86fc; font-weight: 600; }
        .job-card .company { font-weight: 500; color: #cfcfff; margin-bottom: 8px; }
        .job-card .desc { color: #ccc; font-size: 14px; line-height: 1.4; }
        .job-card .job-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .job-card .job-actions button,
        .job-card .job-actions a {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .job-card .job-actions button.applied {
            background: #555;
            color: #999;
            cursor: not-allowed;
        }
        .job-card .job-actions button:not(.applied),
        .job-card .job-actions a.apply {
            background: #6c63ff;
            color: #fff;
        }
        .job-card .job-actions button:hover:not(.applied) { background: #7d73ff; }
        .match-wrap { margin-top: 10px; }
        .match-bar {
            position: relative;
            height: 10px;
            background: #2b2b44;
            border-radius: 999px;
        }
        .match-fill {
            height: 100%;
            background: linear-gradient(90deg,#50c878,#2e8b57);
            width: 0%;
            transition: width .3s ease;
        }
        .match-label {
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
            color:#ccc;
        }
        .label-low { color:#b33939; }
        .label-mid { color:#c77d00; }
        .label-high { color:#2e8b57; }
        .top-right-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1001;
        }
        .top-right-buttons .top-btn {
            background: #6c63ff;
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .top-right-buttons .top-btn:hover { background: #7d73ff; }
        @media(max-width:900px) {
            .main { margin-left: 0; }
            .sidebar { left:-280px; }
        }
.back-btn {
    display: inline-block;
    margin-bottom: 20px;
    padding: 10px 18px;
    background: linear-gradient(90deg,#6a11cb,#2575fc);
    color: #fff;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    box-shadow: 0 0 12px rgba(106,17,203,0.6);
    transition: 0.3s;
}
.back-btn:hover {
    background: linear-gradient(90deg,#2575fc,#6a11cb);
    box-shadow: 0 0 16px rgba(159,122,255,0.8);
}

    </style>
</head>
<body>
<video autoplay muted loop id="bgVideo">
    <source src="183279-870457579_small.mp4" type="video/mp4">
    Your browser does not support HTML5 video.
</video>
<div class="toggle-btn" onclick="toggleSidebar()">☰</div>
<div class="sidebar" id="sidebar">
    <h3>WorkNest</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="profile.php">👤 My Profile</a>
    <a href="search_jobs.php">🔍 Search Jobs</a>
   <a href="applied_jobs.php">📄 Applied Jobs</a>
    <a href="book_session.php">📅 Career Guidance<center>Session</center></a>
    <a href="messages.php">💬 Messages</a>
    <a href="leave_review.php">📝 Leave Review</a>
    <a href="settings.php">⚙️ Settings</a>
    <a href="../logout.php">🚪 Logout</a>
</div>
<div class="main">
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    <h1 style="text-align:center; margin-bottom:16px;">Find Your Perfect Job</h1>

    <form class="search-bar" method="GET" action="">
        <input type="text" name="q" placeholder="Keyword, title, company..." value="<?= htmlspecialchars($keyword) ?>" />
        <input type="text" name="location" placeholder="Location" value="<?= htmlspecialchars($location) ?>" />
        <button type="submit">Search</button>
    </form>
    <div class="top-right-buttons">
        <a href="applied_jobs.php" class="top-btn">📄 My Applied Jobs</a>
        <a href="saved_jobs.php" class="top-btn">💾 Saved Jobs</a>
    </div>
<?php if ($mode === 'personalized' && $resume_keywords === ''): ?>
<div id="resumeNotMatchedPopup" style="
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.7); display:flex; align-items:center;
    justify-content:center; z-index:2000;">
  <div style="background:#1e1e2f; padding:30px; border-radius:10px; text-align:center; color:#fff; width:360px;">
    <h2 style="color:#ff6b6b; margin-bottom:16px;">⚠️ Resume Not Matched</h2>
    <p style="color:#ddd; margin-bottom:15px; line-height:1.5;">
      Your uploaded resume could not be matched with jobs.
    </p>
    <p style="color:#ddd; margin-bottom:20px; line-height:1.5;">
      You can still <strong>search jobs in General mode</strong> or improve your resume with a counselor.
    </p>
    <div style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
      <a href="search_jobs.php?mode=general" style="background:#6c63ff; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:600;">🌐 General Mode</a>
      <a href="book_session.php" style="background:#50c878; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:600;">🎓 Career Counselor</a>
    </div>
    <div style="margin-top:20px;">
      
    </div>
  </div>
</div>
<?php elseif ($mode === 'personalized' && empty($keyword) && empty($location)): ?>

    <!-- Suggested jobs for personalized mode -->
    <h3 class="section-title">✨ Suggested for you</h3>
    <div class="job-listing">
        <?php if (!empty($suggested)): ?>
            <?php foreach ($suggested as $s): ?>
                <?php
                    $score = (int)$s['__score'];
                    $label = $score >= 75 ? 'Great match' : ($score >= 50 ? 'Good match' : 'Some match');
                    $label_class = $score >= 75 ? 'label-high' : ($score >= 50 ? 'label-mid' : 'label-low');
                    $already_applied = in_array((int)$s['id'], $applied_jobs, true);
                ?>
                <div class="job-card">
                    <h3><?= htmlspecialchars($s['title']) ?>
                        <span class="chip"><?= htmlspecialchars($s['job_type'] ?? 'Job') ?></span>
                    </h3>
                    <p class="company">🏢 <?= htmlspecialchars($s['company_name']) ?> — 📍 <?= htmlspecialchars($s['location']) ?></p>
                    <p class="desc"><?= htmlspecialchars(mb_strimwidth(strip_tags($s['description']), 0, 150, '…')) ?></p>

                    <?php if ($score !== null): ?>
                        <div class="match-wrap">
                            <div class="match-bar"><div class="match-fill" style="width: <?= $score ?>%;"></div></div>
                            <div class="match-label <?= $label_class ?>"><?= $score ?>% • <?= $label ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="job-actions">
                        <form action="view_job.php" method="get" style="display:inline-block">
                            <input type="hidden" name="job_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit">🔎 View</button>
                        </form>
                        <form class="save-form" action="save_jobs.php" method="post">
                            <input type="hidden" name="job_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" <?= in_array((int)$s['id'], $saved_jobs, true) ? 'disabled class="applied"' : '' ?>>
                                <?= in_array((int)$s['id'], $saved_jobs, true) ? '💾 Saved' : '💾 Save' ?>
                            </button>
                        </form>
                        <?php if ($mode === 'personalized'): ?>
    <?php if ($already_applied): ?>
        <button class="applied" disabled>✅ Applied</button>
    <?php else: ?>
        <form action="apply.php" method="get" style="display:inline-block">
            <input type="hidden" name="job_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="apply">📤 Apply</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center;">No recommendations yet. Add more skills/experience to your profile for better matches.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>


    <h3 class="section-title">🔍 Search results <?= ($keyword||$location) ? 'for your query' : '' ?></h3>
    <div class="job-listing">
        <?php if (!empty($jobs)): foreach ($jobs as $row):
            $score = (int)$row['__score'];
            $label = $score >= 75 ? 'Great match' : ($score >= 50 ? 'Good match' : 'Some match');
            $label_class = $score >= 75 ? 'label-high' : ($score >= 50 ? 'label-mid' : 'label-low');
        ?>
            <div class="job-card">
                <h3><?= htmlspecialchars($row['title']) ?>
                    <span class="chip"><?= htmlspecialchars($row['job_type'] ?? 'Job') ?></span>
                </h3>
                <p class="company">🏢 <?= htmlspecialchars($row['company_name']) ?> — 📍 <?= htmlspecialchars($row['location']) ?></p>
                <p class="desc"><?= htmlspecialchars(mb_strimwidth(strip_tags($row['description']), 0, 150, '…')) ?></p><?php if ($mode === 'personalized' && $score !== null): ?>
        <div class="match-wrap">
            <div class="match-bar"><div class="match-fill" style="width: <?= $score ?>%;"></div></div>
            <div class="match-label <?= $label_class ?>"><?= $score ?>% • <?= $label ?></div>
        </div>
    <?php endif; ?>
                <div class="job-actions">
                    <form action="view_job.php" method="get">
                        <input type="hidden" name="job_id" value="<?= (int)$row['id'] ?>">
                        <button type="submit">🔎 View</button>
                    </form>
                    <form class="save-form" action="save_jobs.php" method="post">
                        <input type="hidden" name="job_id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" <?= in_array((int)$row['id'], $saved_jobs, true) ? 'disabled class="applied"' : '' ?>>
                            <?= in_array((int)$row['id'], $saved_jobs, true) ? '💾 Saved' : '💾 Save' ?>
                        </button>
                    </form>
                   <?php if ($mode === 'personalized'): ?>
    <?php if ($mode === 'personalized'): ?>
    <?php if (in_array((int)$row['id'], $applied_jobs, true)): ?>
        <button class="applied" disabled>✅ Applied</button>
    <?php else: ?>
        <form action="apply.php" method="get">
            <input type="hidden" name="job_id" value="<?= (int)$row['id'] ?>">
            <button type="submit" class="apply">📤 Apply</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php endif; ?>

                </div>
            </div>
        <?php endforeach; else: ?>
            <p style="text-align:center;">😕 No jobs found. Try different keywords or locations.</p>
        <?php endif; ?>
    </div>
</div>
<?php
// Check if parsed resume data exists
$resume_check_stmt = $conn->prepare("SELECT 1 FROM parsed_resume_data WHERE js_id = ? LIMIT 1");
$resume_check_stmt->bind_param("i", $js_id);
$resume_check_stmt->execute();
$resume_check = $resume_check_stmt->get_result()->fetch_assoc();
$resume_check_stmt->close();

$has_resume_data = (bool)$resume_check;
?>
<?php if ($has_resume_data): ?>
<div id="modePopup" style="
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.7); display:flex; align-items:center;
    justify-content:center; z-index:2000;">
  <div style="background:#1e1e2f; padding:30px; border-radius:10px; text-align:center; color:#fff; width:300px;">
    <h2>Choose Search Mode</h2>
    <p>Do you want personalized job matches based on your resume?</p>
    <div style="margin-top:20px;">
     <a href="?mode=personalized" 
   style="display:inline-block; background:#6c63ff; color:#fff; padding:10px 15px; margin:5px; border-radius:6px; text-decoration:none;">
   🎯 Personalized
</a>
      <a href="?mode=general" style="display:inline-block; background:#444; color:#fff; padding:10px 15px; margin:5px; border-radius:6px; text-decoration:none;">🌐 General</a>
    </div>
  </div>
</div>
<?php else: ?>
<div id="resumePopup" style="
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.7); display:flex; align-items:center;
    justify-content:center; z-index:2000;">
  <div style="background:#1e1e2f; padding:30px; border-radius:10px; text-align:center; color:#fff; width:320px;">
    <h2>Upload Resume</h2>
    <p>You don’t have a parsed resume yet. Upload your resume to get personalized job matches.</p>
    <div style="margin-top:20px;">
      <a href="profile.php" style="display:inline-block; background:#6c63ff; color:#fff; padding:10px 15px; margin:5px; border-radius:6px; text-decoration:none;">📂 Upload Resume</a>
      <a href="?mode=general" style="display:inline-block; background:#444; color:#fff; padding:10px 15px; margin:5px; border-radius:6px; text-decoration:none;">🌐 Continue in General</a>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("open");
}
window.addEventListener("load", function(){
    const urlParams = new URLSearchParams(window.location.search);
    const hasSearch = urlParams.has('q') || urlParams.has('location'); // search bar used

    <?php if ($has_resume_data): ?>
        // Only show mode popup if no mode is selected AND no search performed
        if (!urlParams.has('mode') && !hasSearch) {
            document.getElementById("modePopup").style.display = "flex";
        } else {
            document.getElementById("modePopup").style.display = "none";
        }
    <?php else: ?>
        // Only show resume popup if no mode is selected AND no search performed
        if (!urlParams.has('mode') && !hasSearch) {
            document.getElementById("resumePopup").style.display = "flex";
        } else {
            document.getElementById("resumePopup").style.display = "none";
        }
    <?php endif; ?>
});

</script>
</body>
</html>