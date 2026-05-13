<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$job_id = intval($_GET['job_id'] ?? 0);

if ($job_id === 0) {
    echo "Invalid Job ID.";
    exit();
}
// Fetch job and employer info
$job_stmt = $conn->prepare("
    SELECT j.title AS job_title, j.description AS job_description, j.location AS job_location, j.job_type, j.status,
           e.company_name, e.logo, e.website, e.location AS company_location, e.description AS company_description
    FROM jobs j
    INNER JOIN employer_profiles e ON j.emp_id = e.emp_id
    WHERE j.id = ?
");

$job_stmt->bind_param("i", $job_id);
$job_stmt->execute();
$job = $job_stmt->get_result()->fetch_assoc();
$job_stmt->close();

if (!$job) {
    echo "Job not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($job['job_title']) ?> | WorkNest</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body {
    font-family:'Poppins',sans-serif;
    background:#0c0c25;
    margin:0;
    padding:40px;
    color:#e0e0ff;
}
.container {
    max-width:900px;
    margin:auto;
    background:linear-gradient(135deg,#1e1e40,#2a2a60);
    padding:35px;
    border-radius:12px;
    box-shadow:0 0 15px rgba(50,50,100,0.5);
}
h2 {
    color:#88c0ff;
    font-size:28px;
    margin-bottom:10px;
}
.company-info {
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:20px;
}
.company-info img {
    max-width:100px;
    border-radius:8px;
}
.company-info div strong {
    font-size:18px;
}
.company-info div a {
    color:#81a1c1;
    text-decoration:none;
}
.company-info div a:hover {
    text-decoration:underline;
}
.info-card {
    background:rgba(30,30,50,0.7);
    padding:15px;
    border-radius:8px;
    display:flex;
    flex-direction:column;
    gap:8px;
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
.sidebar h3 { margin-top: -30px; margin-bottom: 30px; color: #bb86fc; font-size: 28px; text-align: center; }
.sidebar a { display: block; padding: 16px; margin-bottom: 10px; text-decoration: none; color: #cfcfff; font-weight: 600; border-radius: 6px; transition: background 0.2s ease; }
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

.toggle-btn:hover { background: rgba(138,43,226,0.4); }
.back-btn {
    display: inline-block;
    background: linear-gradient(90deg, #6a11cb, #2575fc);
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 10px 18px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(138,43,226,0.6);
    transition: all 0.3s ease;
    margin-bottom: 20px;
}
.back-btn:hover {
    background: linear-gradient(90deg, #2575fc, #6a11cb);
    box-shadow: 0 0 15px rgba(159,122,255,0.9);
    transform: translateY(-2px);
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
    <a href="book_session.php">📅 Career Guidance Session</a>
    <a href="messages.php">💬 Messages</a>
    <a href="leave_review.php">📝 Leave Review</a>
    <a href="settings.php">⚙️ Settings</a>
    <a href="../logout.php">🚪 Logout</a>
</div>

<div class="container">

    <a href="search_jobs.php" class="back-btn">⬅ Back to Search Jobs</a>
    <h2><?= htmlspecialchars($job['job_title']) ?></h2>


    <div class="company-info">
        <?php if (!empty($job['logo'])): ?>
            <img src="../<?= htmlspecialchars($job['logo']) ?>" alt="Company Logo">
        <?php endif; ?>
        <div>
            <strong><?= htmlspecialchars($job['company_name']) ?></strong><br>
            <a href="<?= htmlspecialchars($job['website']) ?>" target="_blank"><?= htmlspecialchars($job['website']) ?></a><br>
            📍 <?= htmlspecialchars($job['company_location']) ?>
        </div>
    </div>

    <div class="info-card">
        <div><strong>Job Location:</strong> <?= htmlspecialchars($job['job_location']) ?></div>
        <div><strong>Job Type:</strong> <?= htmlspecialchars($job['job_type']) ?></div>
        <div><strong>Status:</strong> <?= htmlspecialchars($job['status']) ?></div>
    </div>

<h3>About the Company</h3>
<p><?= nl2br(htmlspecialchars($job['company_description'] ?? 'Description not available')) ?></p>


<script>
function toggleSidebar(){ document.getElementById("sidebar").classList.toggle("open"); }
</script>
</body>
</html>
