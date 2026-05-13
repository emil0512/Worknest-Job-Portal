<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];

// Fetch parsed resume data
$parsed = $conn->prepare("SELECT * FROM parsed_resume_data WHERE js_id = ?");
$parsed->bind_param("i", $js_id);
$parsed->execute();
$parsed_result = $parsed->get_result()->fetch_assoc();

$recommended = [];

if ($parsed_result) {
    $keywords = [];

    // Combine all parsed keyword fields
    foreach (['skills', 'job_titles', 'education_fields', 'certifications', 'experience_levels'] as $field) {
        if (!empty($parsed_result[$field])) {
            $keywords = array_merge($keywords, array_map('trim', explode(',', strtolower($parsed_result[$field]))));
        }
    }

    $keywords = array_unique(array_filter($keywords));

    if (!empty($keywords)) {
        // Build LIKE conditions
        $like_conditions = [];
        $params = [];
        foreach ($keywords as $kw) {
            $like_conditions[] = "(j.title LIKE ? OR j.description LIKE ? OR j.requirements LIKE ?)";
            $kw_param = '%' . $kw . '%';
            $params[] = $kw_param;
            $params[] = $kw_param;
            $params[] = $kw_param;
        }

        $where_clause = implode(" OR ", $like_conditions);
        $sql = "SELECT j.*, ep.company_name 
                FROM jobs j
                JOIN employer_profiles ep ON j.emp_id = ep.emp_id
                WHERE $where_clause";

        $stmt = $conn->prepare($sql);
        $types = str_repeat("s", count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $results = $stmt->get_result();

        // Calculate match score
        while ($job = $results->fetch_assoc()) {
            $content = strtolower($job['title'] . " " . $job['description'] . " " . $job['requirements']);
            $match_count = 0;
            foreach ($keywords as $kw) {
                if (stripos($content, $kw) !== false) {
                    $match_count++;
                }
            }
            $score = ($match_count > 0) ? round(($match_count / count($keywords)) * 100) : 0;
            $job['match_score'] = $score;
            $recommended[] = $job;
        }

        // Sort descending by match score
        usort($recommended, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recommended Jobs | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
   <style>
body {
    font-family: 'Poppins', sans-serif;
    margin:0; padding:0;
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


.container {
    max-width: 900px;
    margin: 50px auto;
    background: rgba(26,26,64,0.95);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 0 20px rgba(159,122,255,0.5);
}
h2 {
    text-align: center;
    font-weight: 600;
    color: #b29bff;
    text-shadow: 0 0 10px #7a5cf4;
    margin-bottom: 30px;
}
.job-card {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.2);
    border-radius: 8px;
    margin-bottom: 15px;
    transition: transform 0.3s, box-shadow 0.3s;
}
.job-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0 20px rgba(159,122,255,0.5);
}
.job-card h3 { margin:0; color: #9f7aff; text-shadow: 0 0 6px #7a5cf4; }
.job-card p { margin:4px 0; color: #e0e0ff; }
.match-score { font-weight:bold; color:#4CAF50; text-shadow:0 0 4px #4CAF50; }
.btn {
    padding: 6px 12px;
    text-decoration: none;
    background: linear-gradient(90deg,#6a11cb,#2575fc);
    color: white;
    border-radius: 6px;
    font-weight: bold;
    display: inline-block;
    margin-top: 10px;
    transition: 0.3s;
}
.btn:hover { background: linear-gradient(90deg,#2575fc,#6a11cb); box-shadow: 0 0 12px rgba(159,122,255,0.6); }

.back-btn {
    display: inline-block;
    margin-bottom: 20px;
    color: #b29bff;
    font-weight: bold;
    text-decoration: none;
}
.back-btn:hover { text-decoration: underline; }

.empty {
    text-align: center;
    font-size: 18px;
    color: #c0c0ff;
    margin-top: 30px;
    text-shadow: 0 0 4px #9f7aff;
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
    <a class="back-btn" href="dashboard.php">← Back to Dashboard</a>
    <h2>🎯 Recommended Jobs For You</h2>

    <?php if (!empty($recommended)): ?>
        <?php foreach ($recommended as $job): ?>
            <div class="job-card">
                <h3><?= htmlspecialchars($job['title']) ?></h3>
                <p>🏢 <?= htmlspecialchars($job['company_name']) ?> — 📍 <?= htmlspecialchars($job['location']) ?></p>
                <p>🎯 Match Score: <span class="match-score"><?= $job['match_score'] ?>%</span></p>
                <a class="btn" href="view_job.php?job_id=<?= $job['id'] ?>">View Job</a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="empty">😕 No recommended jobs found. Please upload or improve your resume with more relevant keywords.</p>
    <?php endif; ?>
</div>
</body>
</html>
