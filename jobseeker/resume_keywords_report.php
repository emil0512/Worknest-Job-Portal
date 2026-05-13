<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);

if ($job_id === 0) {
    echo "Invalid Job ID.";
    exit();
}

// 1. Fetch job info
$job_stmt = $conn->prepare("SELECT j.*, e.company_name FROM jobs j INNER JOIN employer_profiles e ON j.emp_id = e.emp_id WHERE j.id = ?");
$job_stmt->bind_param("i", $job_id);
$job_stmt->execute();
$job = $job_stmt->get_result()->fetch_assoc();

if (!$job) {
    echo "Job not found.";
    exit();
}

// 2. Fetch parsed resume keywords (corrected column name)
$resume_stmt = $conn->prepare("SELECT resume_keyword FROM parsed_resume_data WHERE js_id = ?");
$resume_stmt->bind_param("i", $js_id);
$resume_stmt->execute();
$resume_result = $resume_stmt->get_result();

$resume_keywords = [];
while ($row = $resume_result->fetch_assoc()) {
    $resume_keywords[] = strtolower(trim($row['resume_keyword']));
}

// 3. Extract and normalize job keywords
$job_keywords = array_map('strtolower', array_map('trim', explode(',', $job['keywords'] ?? '')));

// 4. Calculate match score
$matched = array_intersect($resume_keywords, $job_keywords);

// Use the union of both arrays as the base
$total_unique_keywords = count(array_unique(array_merge($resume_keywords, $job_keywords)));

// Calculate realistic match score
$match_score = $total_unique_keywords > 0 ? intval((count($matched) / $total_unique_keywords) * 100) : 0;


// 5. Visual match score color
$score_class = $match_score >= 75 ? 'score-high' : ($match_score >= 50 ? 'score-medium' : 'score-low');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Job | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    background: radial-gradient(circle at top left, #0f0c29, #302b63, #24243e);
    color: #e0e0ff;
    padding: 0;
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
.container {
    max-width: 900px;
    margin: 50px auto;
    background: rgba(26,26,64,0.95);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 0 20px rgba(159,122,255,0.5);
}
h2 {
    font-weight: 600;
    color: #b29bff;
    text-shadow: 0 0 10px #7a5cf4;
    margin-bottom: 10px;
}
.company {
    font-weight: bold;
    color: #9f7aff;
    text-shadow: 0 0 6px #7a5cf4;
    margin-bottom: 15px;
}
p { line-height: 1.6; margin-bottom: 15px; color: #e0e0ff; }

.match-score-bar {
    margin-top: 10px;
    height: 22px;
    width: 100%;
    background-color: rgba(255,255,255,0.1);
    border-radius: 12px;
    overflow: hidden;
}
.match-score-fill {
    height: 100%;
    color: white;
    text-align: center;
    line-height: 22px;
    font-weight: bold;
    font-size: 14px;
}
.score-low { background-color: #ff4d4d; box-shadow: 0 0 8px #ff4d4d; }
.score-medium { background-color: #ffa500; box-shadow: 0 0 8px #ffa500; }
.score-high { background-color: #28a745; box-shadow: 0 0 8px #28a745; }

.btn-apply {
    display: inline-block;
    margin-top: 20px;
    background: linear-gradient(90deg,#6a11cb,#2575fc);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
}
.btn-apply:hover {
    background: linear-gradient(90deg,#2575fc,#6a11cb);
    box-shadow: 0 0 12px rgba(159,122,255,0.6);
}
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
<div class="container">
    <h2><?= htmlspecialchars($job['title']) ?></h2>
    <p class="company">🏢 <?= htmlspecialchars($job['company_name']) ?> — 📍 <?= htmlspecialchars($job['location']) ?></p>
    <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>

    <h3>🔍 Match Score:</h3>
    <div class="match-score-bar">
        <div class="match-score-fill <?= $score_class ?>" style="width: <?= $match_score ?>%">
            <?= $match_score ?>%
        </div>
    </div>
    <p style="margin-top: 8px;">
        <?php if ($match_score >= 75): ?>
            ✅ Great match! You’re a strong candidate.
        <?php elseif ($match_score >= 50): ?>
            ⚠️ Decent match. Consider improving your resume.
        <?php else: ?>
            ❌ Low match. Your resume shares few keywords with this job.
        <?php endif; ?>
    </p>

    <a href="apply_job.php?job_id=<?= $job_id ?>" class="btn-apply">📤 Apply for this Job</a>
</div>
</body>
</html>
