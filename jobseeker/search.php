<?php
session_start();
include("../db.php");

// ✅ Utility: convert text into array of words
function to_words($text) {
    $clean = strtolower($text ?? '');
    $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
    return array_unique($words);
}

// ✅ Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id    = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

$keyword  = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');

/* -----------------------------------------------------------
   Fetch parsed resume data
------------------------------------------------------------ */
$resume_stmt = $conn->prepare("SELECT matched_keywords FROM parsed_resume_data WHERE js_id = ?");
$resume_stmt->bind_param("i", $js_id);
$resume_stmt->execute();
$resume_res = $resume_stmt->get_result();
$resume_row = $resume_res->fetch_assoc();
$resume_stmt->close();

$resume_keywords = [];
if ($resume_row && !empty($resume_row['matched_keywords'])) {
    $resume_keywords = to_words($resume_row['matched_keywords']);
}

/* -----------------------------------------------------------
   Fetch all open jobs
------------------------------------------------------------ */
$all_jobs = [];
$jobs_res = $conn->query("
    SELECT j.*, e.company_name
    FROM jobs j
    INNER JOIN employer_profiles e ON j.emp_id = e.emp_id
    WHERE j.status = 'open'
");

if ($jobs_res) {
    while ($job = $jobs_res->fetch_assoc()) {
        $all_jobs[] = $job;
    }
}

/* -----------------------------------------------------------
   Calculate and insert/update match scores
------------------------------------------------------------ */
foreach ($all_jobs as $job) {
    $job_id = (int)$job['id'];
    $score = 0;

    if (!empty($resume_keywords)) {
        $job_words = array_merge(
            to_words($job['title']),
            to_words($job['description']),
            to_words($job['keywords'])
        );

        $job_words = array_unique($job_words);

        $matches = array_intersect($resume_keywords, $job_words);
        $match_count = count($matches);

        // Weighted scoring
        $title_matches = count(array_intersect($resume_keywords, to_words($job['title'])));
        $keyword_matches = count(array_intersect($resume_keywords, to_words($job['keywords'])));
        $desc_matches = count(array_intersect($resume_keywords, to_words($job['description'])));

        $score = ($title_matches * 40) + ($keyword_matches * 30) + ($desc_matches * 20);

        // Normalize score (cap at 100)
        $score = min(100, $score);
    }

    // Insert or update into job_matches
    $check_stmt = $conn->prepare("SELECT id FROM job_matches WHERE js_id = ? AND job_id = ?");
    $check_stmt->bind_param("ii", $js_id, $job_id);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();
    $exists = $check_res->fetch_assoc();
    $check_stmt->close();

    if ($exists) {
        $upd_stmt = $conn->prepare("UPDATE job_matches SET score = ? WHERE js_id = ? AND job_id = ?");
        $upd_stmt->bind_param("iii", $score, $js_id, $job_id);
        $upd_stmt->execute();
        $upd_stmt->close();
    } else {
        $ins_stmt = $conn->prepare("INSERT INTO job_matches (js_id, job_id, score) VALUES (?, ?, ?)");
        $ins_stmt->bind_param("iii", $js_id, $job_id, $score);
        $ins_stmt->execute();
        $ins_stmt->close();
    }
}

/* -----------------------------------------------------------
   Reload jobs with scores
------------------------------------------------------------ */
$all_jobs = [];
$jobs_res = $conn->query("
    SELECT j.*, e.company_name, m.score AS __score
    FROM jobs j
    INNER JOIN employer_profiles e ON j.emp_id = e.emp_id
    LEFT JOIN job_matches m ON j.id = m.job_id AND m.js_id = $js_id
    WHERE j.status = 'open'
");

if ($jobs_res) {
    while ($job = $jobs_res->fetch_assoc()) {
        $job['__score'] = (int)($job['__score'] ?? 0);
        $all_jobs[] = $job;
    }
}

/* -----------------------------------------------------------
   Applied & Saved jobs
------------------------------------------------------------ */
$applied_jobs = [];
$res = $conn->query("SELECT job_id FROM applications WHERE user_id = $js_id");
while ($row = $res->fetch_assoc()) {
    $applied_jobs[] = (int)$row['job_id'];
}

$saved_jobs = [];
$res = $conn->query("SELECT job_id FROM saved_jobs WHERE user_id = $js_id");
while ($row = $res->fetch_assoc()) {
    $saved_jobs[] = (int)$row['job_id'];
}

/* -----------------------------------------------------------
   Apply search filters
------------------------------------------------------------ */
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

/* -----------------------------------------------------------
   Sort jobs (score DESC, posted_on DESC)
------------------------------------------------------------ */
usort($all_jobs, function ($a, $b) {
    return $b['__score'] <=> $a['__score']
        ?: strcmp($b['posted_on'], $a['posted_on']);
});

/* -----------------------------------------------------------
   Split into suggested + search results
------------------------------------------------------------ */
$suggested = array_slice($all_jobs, 0, 3);
$suggested_ids = array_column($suggested, 'id');

$jobs = [];
foreach ($all_jobs as $row) {
    if (!in_array((int)$row['id'], $suggested_ids, true)) {
        $jobs[] = $row;
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
        /* ======================== BODY & BACKGROUND ======================== */
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0a0f, #0f0f1e);
            color: #e0e0ff;
            padding: 40px 20px 20px;
        }
        /* Sidebar */
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
        /* Toggle button */
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
        /* ======================== MAIN CONTENT ======================== */
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
        /* ======================== SEARCH BAR ======================== */
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

        /* ======================== JOB CARDS ======================== */
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

        /* ======================== MATCH BAR ======================== */
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
        .label-low  { color:#b33939; }
        .label-mid  { color:#c77d00; }
        .label-high { color:#2e8b57; }

        /* ======================== TOP-RIGHT BUTTONS ======================== */
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

        /* ======================== RESPONSIVE ======================== */
        @media(max-width:900px) {
            .main { margin-left: 0; }
            .sidebar { left:-280px; }
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

    <?php if (empty($keyword) && empty($location) && $resume_has_data && !empty($suggested)): ?>
        <h3 class="section-title">✨ Suggested for you</h3>
        <div class="job-listing">
            <?php foreach ($suggested as $s):
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

                    <div class="match-wrap">
                        <div class="match-bar"><div class="match-fill" style="width: <?= $score ?>%;"></div></div>
                        <div class="match-label <?= $label_class ?>"><?= $score ?>% • <?= $label ?></div>
                    </div>

                    <div class="job-actions">
                        <form action="view_job.php" method="get" style="display:inline-block">
                            <input type="hidden" name="job_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit">🔎 View</button>
                        </form>

                        <form class="save-form" action="save_jobs.php" method="post" style="display:inline-block">
                            <input type="hidden" name="job_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" <?= in_array((int)$s['id'], $saved_jobs, true) ? 'disabled class="applied"' : '' ?>>
                                <?= in_array((int)$s['id'], $saved_jobs, true) ? '💾 Saved' : '💾 Save' ?>
                            </button>
                        </form>

                        <?php if ($already_applied): ?>
                            <button class="applied" disabled>✅ Applied</button>
                        <?php else: ?>
                            <form action="apply_job.php" method="get" style="display:inline-block">
                                <input type="hidden" name="job_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="apply">📤 Apply</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($keyword) && empty($location) && !$resume_has_data): ?>
        <h3 class="section-title">✨ Suggested for you</h3>
        <p style="text-align:center;">No parsed resume data found. Upload or parse your resume to get personalized recommendations.</p>
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
                <p class="desc"><?= htmlspecialchars(mb_strimwidth(strip_tags($row['description']), 0, 150, '…')) ?></p>

                <div class="match-wrap">
                    <div class="match-bar"><div class="match-fill" style="width: <?= $score ?>%;"></div></div>
                    <div class="match-label <?= $label_class ?>"><?= $score ?>% • <?= $label ?></div>
                </div>

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

                    <?php if (in_array((int)$row['id'], $applied_jobs, true)): ?>
                        <button class="applied" disabled>✅ Applied</button>
                    <?php else: ?>
                        <form action="apply_job.php" method="get">
                            <input type="hidden" name="job_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="apply">📤 Apply</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; else: ?>
            <p style="text-align:center;">😕 No jobs found. Try different keywords or locations.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
