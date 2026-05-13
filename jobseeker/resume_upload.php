<?php
session_start();

// Adjust path to your db.php
require_once __DIR__ . '/../db.php';

// Composer autoloader for smalot/pdfparser
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
$parserAvailable = false;
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    if (class_exists('\Smalot\PdfParser\Parser')) {
        $parserAvailable = true;
    }
}

// --- Initialize user id from session ---
$js_id = null;
if (!empty($_SESSION['js_id'])) {
    $js_id = intval($_SESSION['js_id']);
} elseif (!empty($_SESSION['user_id'])) {
    $js_id = intval($_SESSION['user_id']);
}
if (empty($js_id)) {
    die("You must be logged in to upload a resume.");
}

// Verify user exists
$chk = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$chk->bind_param("i", $js_id);
$chk->execute();
$chk_res = $chk->get_result();
if (!$chk_res || $chk_res->num_rows === 0) {
    $chk->close();
    die("Error: user not found in database.");
}
$chk->close();

// Ensure jobseeker_profiles row exists
$stmt = $conn->prepare("SELECT resume_path FROM jobseeker_profiles WHERE js_id = ? LIMIT 1");
$stmt->bind_param("i", $js_id);
$stmt->execute();
$r = $stmt->get_result();
if ($r && ($row = $r->fetch_assoc())) {
    $resume_path = $row['resume_path'] ?? '';
    $stmt->close();
} else {
    $stmt->close();
    $ins = $conn->prepare("INSERT INTO jobseeker_profiles (js_id) VALUES (?)");
    $ins->bind_param("i", $js_id);
    @$ins->execute();
    $ins->close();
    $resume_path = '';
}

// Define upload directory (physical path)
$upload_dir = __DIR__ . '/../uploads/resumes/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
        die("Failed to create upload directory.");
    }
}

// Base URL of your site - adjust if needed
$base_url = '/job_portal';

// Initialize messages and preview
$success_msg = '';
$error_msg = '';
$parse_message = '';
$parsed_preview = ['skills' => [], 'education' => [], 'experiences' => [], 'titles' => []];

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Upload error code: " . intval($_FILES['resume']['error']);
    } else {
        $tmp = $_FILES['resume']['tmp_name'];
        $orig = basename($_FILES['resume']['name']);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $error_msg = "Only PDF files are allowed.";
        } else {
            $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $orig);
            $new_filename = $js_id . '_' . time() . '_' . $safe;
            $new_path = $upload_dir . $new_filename;
            if (!move_uploaded_file($tmp, $new_path)) {
                $error_msg = "Failed to move uploaded file to uploads folder.";
            } else {
                // If old resume exists, delete it
                if (!empty($resume_path) && file_exists(__DIR__ . '/../' . $resume_path)) {
                    @unlink(__DIR__ . '/../' . $resume_path);
                }
                // Store relative path (relative to job_portal root)
                $relative_path = 'uploads/resumes/' . $new_filename;

                $u = $conn->prepare("UPDATE jobseeker_profiles SET resume_path = ? WHERE js_id = ?");
                $u->bind_param("si", $relative_path, $js_id);
                $u->execute();
                $u->close();

                $resume_path = $relative_path;
                $success_msg = "Resume uploaded successfully.";
            }
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!empty($resume_path) && file_exists(__DIR__ . '/../' . $resume_path)) {
        @unlink(__DIR__ . '/../' . $resume_path);
    }
    $d = $conn->prepare("UPDATE jobseeker_profiles SET resume_path = NULL WHERE js_id = ?");
    $d->bind_param("i", $js_id);
    $d->execute();
    $d->close();
    $resume_path = '';
    $success_msg = "Resume deleted.";
}

// Handle Parse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parse'])) {
    if (empty($resume_path) || !file_exists(__DIR__ . '/../' . $resume_path)) {
        $error_msg = "No resume file found to parse. Upload first.";
    } elseif (!$parserAvailable) {
        $error_msg = "PDF parser not available. Run `composer require smalot/pdfparser` and ensure vendor/autoload.php is present.";
    } else {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile(__DIR__ . '/../' . $resume_path);
            $text_original = $pdf->getText();
        } catch (Exception $e) {
            $text_original = '';
            $error_msg = "PDF parse error: " . $e->getMessage();
        }

        if (trim($text_original) === '') {
            $error_msg = $error_msg ?: "Parsed file contains no text (maybe scanned image).";
        } else {
            $text_lower = mb_strtolower($text_original, 'UTF-8');

            // Clear previous parsed data
            $del = $conn->prepare("DELETE FROM jobseeker_skills WHERE js_id = ?");
            $del->bind_param("i", $js_id);
            $del->execute();
            $del->close();

            $del = $conn->prepare("DELETE FROM jobseeker_education WHERE js_id = ?");
            $del->bind_param("i", $js_id);
            $del->execute();
            $del->close();

            $del = $conn->prepare("DELETE FROM jobseeker_experiences WHERE js_id = ?");
            $del->bind_param("i", $js_id);
            $del->execute();
            $del->close();

            $del = $conn->prepare("DELETE FROM parsed_resume_data WHERE js_id = ?");
            $del->bind_param("i", $js_id);
            $del->execute();
            $del->close();

            // Load keywords from DB
            $keywords_by_cat = [
                'skill' => [],
                'technology' => [],
                'job_title' => [],
                'education_field' => []
            ];
            $q = $conn->query("SELECT keyword, category FROM keywords");
            if ($q) {
                while ($row = $q->fetch_assoc()) {
                    $cat = $row['category'];
                    $kw = trim($row['keyword']);
                    if ($kw === '') continue;
                    if (isset($keywords_by_cat[$cat])) {
                        $keywords_by_cat[$cat][] = $kw;
                    }
                }
                $q->free();
            }

            $ins_skill = $conn->prepare("INSERT INTO jobseeker_skills (js_id, skill) VALUES (?, ?)");
            $ins_edu = $conn->prepare("INSERT INTO jobseeker_education (js_id, degree, institution, graduation_year) VALUES (?, ?, ?, ?)");
            $ins_exp = $conn->prepare("INSERT INTO jobseeker_experiences (js_id, position_title, company_name, duration, description) VALUES (?, ?, ?, ?, ?)");
            $ins_parsed = $conn->prepare("INSERT INTO parsed_resume_data (js_id, matched_keywords, score, parsed_text) VALUES (?, ?, ?, ?)");

            $matched_items = [];
            $score = 0;

            // Skills + technology
            $skill_candidates = array_merge($keywords_by_cat['skill'], $keywords_by_cat['technology']);
            $found_skills = [];
            foreach ($skill_candidates as $kw) {
                $pattern = '/\b' . preg_quote(mb_strtolower($kw, 'UTF-8'), '/') . '\b/u';
                if (preg_match($pattern, $text_lower)) {
                    $found_skills[] = $kw;
                }
            }
            $found_skills = array_values(array_unique($found_skills, SORT_REGULAR));
            foreach ($found_skills as $s) {
                $ins_skill->bind_param("is", $js_id, $s);
                $ins_skill->execute();
                $parsed_preview['skills'][] = $s;
                $matched_items[] = $s;
                $score += 1;
            }

            // Education
            $found_education = [];
            foreach ($keywords_by_cat['education_field'] as $kw) {
                $p = '/\b' . preg_quote(mb_strtolower($kw, 'UTF-8'), '/') . '\b/iu';
                if (preg_match($p, $text_lower, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = (int)$m[0][1];
                    $context_start = max(0, $pos - 120);
                    $context_len = 350;
                    $context = mb_substr($text_original, $context_start, $context_len, 'UTF-8');

                    $institution = null;
                    if (preg_match('/([A-Z][A-Za-z0-9\&\.\- ]{2,80}\b(?:University|Institute|College|School|Academy|Polytechnic|IIT|MIT|Caltech))/u', $context, $im)) {
                        $institution = trim($im[1]);
                    } else {
                        if (preg_match('/\b([A-Z][a-zA-Z]{2,}(?:\s+[A-Z][a-zA-Z]{2,}){0,3})\b/u', $context, $im2)) {
                            $institution = trim($im2[1]);
                        }
                    }

                    $grad_year = null;
                    if (preg_match('/Class\s+of\s+(\d{4})/i', $context, $gy)) {
                        $grad_year = $gy[1];
                    } elseif (preg_match('/\b(19|20)\d{2}\b/', $context, $gy2)) {
                        $grad_year = $gy2[0];
                    }

                    $found_education[] = [
                        'degree' => $kw,
                        'institution' => $institution,
                        'graduation_year' => $grad_year
                    ];
                }
            }
            $found_education = array_map("unserialize", array_unique(array_map("serialize", $found_education)));
            foreach ($found_education as $edu) {
                $ins_edu->bind_param("isss", $js_id, $edu['degree'], $edu['institution'], $edu['graduation_year']);
                $ins_edu->execute();
                $parsed_preview['education'][] = $edu['degree'] . ($edu['institution'] ? " @ {$edu['institution']}" : '') . ($edu['graduation_year'] ? " ({$edu['graduation_year']})" : '');
                $matched_items[] = $edu['degree'];
                if ($edu['institution']) $matched_items[] = $edu['institution'];
                $score += 2;
            }

            // Experiences (job titles)
            $found_exps = [];
            foreach ($keywords_by_cat['job_title'] as $kw) {
                $kw_l = mb_strtolower($kw, 'UTF-8');
                $offset = 0;
                while (($pos = mb_stripos($text_lower, $kw_l, $offset, 'UTF-8')) !== false) {
                    $context_start = max(0, $pos - 80);
                    $context_len = 350;
                    $context = mb_substr($text_original, $context_start, $context_len, 'UTF-8');

                    $company = null;
                    if (preg_match('/\b(?:at|@)\s+([A-Z][A-Za-z0-9&\.\- ]{1,80})/u', $context, $cm)) {
                        $company = trim(rtrim($cm[1], " ,.;"));
                    } else {
                        if (preg_match('/\b([A-Z][A-Za-z0-9\.\-]+(?:\s+[A-Z][A-Za-z0-9\.\-]+){0,3})\b/u', $context, $cm2)) {
                            $candidate = trim($cm2[1]);
                            if (mb_stripos($candidate, $kw, 0, 'UTF-8') === false) {
                                $company = $candidate;
                            }
                        }
                    }

                    $duration = null;
                    if (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\s*\d{4}\s*(?:-|to|–|—)\s*(?:Present|\d{4})/i', $context, $dm)) {
                        $duration = trim($dm[0]);
                    } elseif (preg_match('/\b(19|20)\d{2}\s*(?:-|to|–|—)\s*(Present|(19|20)\d{2})\b/i', $context, $dm2)) {
                        $duration = trim($dm2[0]);
                    } elseif (preg_match('/\b\d{1,2}\+?\s*(?:years|yrs?)\b/i', $context, $dm3)) {
                        $duration = trim($dm3[0]);
                    }

                    $description = trim($context);

                    $found_exps[] = [
                        'position_title' => $kw,
                        'company_name' => $company,
                        'duration' => $duration,
                        'description' => $description
                    ];

                    $offset = $pos + mb_strlen($kw, 'UTF-8');
                }
            }
            $found_exps = array_map("unserialize", array_unique(array_map("serialize", $found_exps)));
            foreach ($found_exps as $exp) {
                $ins_exp->bind_param("issss", $js_id, $exp['position_title'], $exp['company_name'], $exp['duration'], $exp['description']);
                $ins_exp->execute();
                $parsed_preview['experiences'][] = $exp['position_title'] . ($exp['company_name'] ? " @ {$exp['company_name']}" : '') . ($exp['duration'] ? " [{$exp['duration']}]" : '');
                $parsed_preview['titles'][] = $exp['position_title'];
                $matched_items[] = $exp['position_title'];
                if ($exp['company_name']) $matched_items[] = $exp['company_name'];
                $score += 3;
            }

            // Save parsed_resume_data
            $matched_items = array_values(array_unique(array_filter($matched_items)));
            $matched_str = implode(', ', $matched_items);
            $ins_parsed->bind_param("isis", $js_id, $matched_str, $score, $text_original);
            $ins_parsed->execute();

            // Close statements
            $ins_skill->close();
            $ins_edu->close();
            $ins_exp->close();
            $ins_parsed->close();

            $parse_message = "Parsed successfully: " . count($parsed_preview['skills']) . " skills, " . count($parsed_preview['education']) . " education entries, " . count($parsed_preview['experiences']) . " experiences.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Resume Upload</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: radial-gradient(circle at top left, #0f0c29, #302b63, #24243e);
    color: #e0e0ff;
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
.sidebar::-webkit-scrollbar-thumb { background-color: #9d4edd; border-radius: 8px; }
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

/* Main content */
.main {
    margin-left: 260px;
    padding: 40px 20px;
    display: flex;
    justify-content: center;
}
.container {
    background: rgba(26,26,64,0.95);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 0 20px rgba(159,122,255,0.5);
    width: 100%;
    max-width: 700px;
}
h1 {
    text-align: center;
    color: #b29bff;
    text-shadow: 0 0 10px #7a5cf4;
    margin-bottom: 25px;
}
button, input[type="file"] {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
}
input[type="file"] {
    width: 100%;
    padding: 8px;
    border-radius: 6px;
    border: none;
    margin-bottom: 12px;
    background: rgba(255,255,255,0.1);
    color: #fff;
}
button {
    background: linear-gradient(90deg,#6a11cb,#2575fc);
    border: none;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
    margin-bottom: 12px;
}
button:hover {
    background: linear-gradient(90deg,#2575fc,#6a11cb);
    box-shadow: 0 0 12px rgba(159,122,255,0.6);
}
.success { color: #28a745; font-weight: bold; margin-bottom: 10px; }
.error { color: #ff4d4d; font-weight: bold; margin-bottom: 10px; }
.parsed-preview h3 { color: #9f7aff; margin-top: 20px; }
.parsed-preview ul { list-style-type: disc; padding-left: 20px; }
a { color: #7ac7ff; text-decoration: none; }
a:hover { text-decoration: underline; }
hr { border: 1px solid rgba(159,122,255,0.3); margin: 20px 0; }
#bgVideo {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;   /* Fill screen without stretching */
  z-index: -3;         /* Behind sidebar, topbar, main content */
  filter: brightness(0.35) contrast(1.2); /* Darken for neon readability */
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
    <a href="applied_jobs.php"> 📄Applied & Saved<br><center>Jobs</center></a>
    <a href="book_session.php">📅 Career Guidance<center>Session</center></a>
    <a href="messages.php">💬 Messages</a>
    <a href="leave_review.php">📝 Leave Review</a>
    <a href="settings.php">⚙️ Settings</a>
    <a href="../logout.php">🚪 Logout</a>
</div>
<div class="main">
  <div class="container">
    <h1>Resume Upload</h1>

    <?php if ($success_msg): ?>
      <p class="success"><?=htmlspecialchars($success_msg)?></p>
    <?php endif; ?>
    <?php if ($error_msg): ?>
      <p class="error"><?=htmlspecialchars($error_msg)?></p>
    <?php endif; ?>
    <?php if ($parse_message): ?>
      <p><strong><?=htmlspecialchars($parse_message)?></strong></p>
    <?php endif; ?>

    <!-- Upload form always shown for reupload -->
    <form method="post" enctype="multipart/form-data" novalidate>
      <input type="file" name="resume" accept="application/pdf" required>
      <button type="submit">Upload Resume</button>
    </form>

    <?php if ($resume_path): ?>
      <p style="margin-top: 15px;">
        Current resume:
        <a href="<?=htmlspecialchars($base_url . '/' . $resume_path)?>" target="_blank" rel="noopener noreferrer">View</a>
      </p>

      <form method="post" onsubmit="return confirm('Are you sure you want to delete your resume?');" style="margin-bottom: 10px;">
        <button type="submit" name="delete">Delete Resume</button>
      </form>

      <form method="post" style="margin-bottom: 15px;">
        <button type="submit" name="parse">Parse Resume</button>
      </form>
    <?php endif; ?>

    <?php if ($parsed_preview['skills'] || $parsed_preview['education'] || $parsed_preview['experiences']): ?>
      <hr>
      <h2>Parsed Preview</h2>

      <?php if ($parsed_preview['skills']): ?>
        <h3>Skills</h3>
        <ul>
          <?php foreach ($parsed_preview['skills'] as $s): ?>
            <li><?=htmlspecialchars($s)?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($parsed_preview['education']): ?>
        <h3>Education</h3>
        <ul>
          <?php foreach ($parsed_preview['education'] as $edu): ?>
            <li><?=htmlspecialchars($edu)?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($parsed_preview['experiences']): ?>
        <h3>Experiences</h3>
        <ul>
          <?php foreach ($parsed_preview['experiences'] as $exp): ?>
            <li><?=htmlspecialchars($exp)?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  sidebar.classList.toggle('open');
}
</script>

</body>
</html>
