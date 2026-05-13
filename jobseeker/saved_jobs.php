<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$sql = "SELECT DISTINCT j.id AS job_id, j.title, j.location, ep.company_name, sj.saved_on
        FROM saved_jobs sj
        JOIN jobs j ON sj.job_id = j.id
        JOIN employer_profiles ep ON j.emp_id = ep.emp_id
        WHERE sj.user_id = ?
        ORDER BY sj.saved_on DESC";


$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $js_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <title>Saved Jobs | WorkNest</title>
    <style>
body {
    margin: 0;
    padding: 40px;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #0d0d0d, #0f0f1e, #1a1a2e, #000);
    color: #e0e0ff;
    overflow-x: hidden;
    animation: bgAnimate 15s linear infinite;
}
@keyframes bgAnimate {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
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

/* ---------------------------- Main Content ---------------------------- */
.main {
    margin-left: 280px;
    padding: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
h2 {
    font-size: 28px;
    font-weight: 700;
    color: #bb86fc;
    text-shadow: 0 0 15px #6c63ff,0 0 30px #9d4edd;
    margin-bottom: 25px;
}

/* ---------------------------- Job Cards ---------------------------- */
.job-listing { width: 70%; max-width: 800px; }
.job-card {
    background: rgba(20,20,40,0.95);
    padding: 40px;
    margin: 0 auto 25px auto;  /* centers horizontally */
    max-width: 600px;          /* reduce width */
    border-left: 8px solid #6c63ff;
    border-radius: 12px;
    box-shadow: 0 0 35px rgba(108,99,255,0.6);
    transition: transform 0.3s, box-shadow 0.3s;
    text-align: center;        /* centers content inside */
}

.job-card:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: 0 0 70px #6c63ff,0 0 100px #9d4edd;
}
.job-card h3 {
    margin: 0 0 10px;
    color: #bb86fc;
    font-weight: 700;
    text-shadow: 0 0 10px #6c63ff;
}
.company {
    font-weight: 600;
    color: #cfcfff;
    margin-bottom: 10px;
}
.btns {
    margin-top: 12px;
}
.btns a, .btns form button {
    padding: 8px 16px;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: #fff;
    font-size: 14px;
    margin-right: 8px;
    transition: 0.3s;
}
.view-btn {
    background: linear-gradient(90deg,#00ffff,#0077ff);
    box-shadow: 0 0 5px #00ffff,0 0 10px #0077ff;
}
.view-btn:hover {
    background: linear-gradient(90deg,#0077ff,#00ffff);
    box-shadow: 0 0 5px #00ffff,0 0 10px #0077ff;
}
.remove-btn {
    background: linear-gradient(90deg,#ff00ff,#8a2be2);
    box-shadow: 0 0 5px #ff00ff,0 0 10px #8a2be2;
}
.remove-btn:hover {
    background: linear-gradient(90deg,#8a2be2,#ff00ff);
    box-shadow: 0 0 5px #ff00ff,0 0 10px #8a2be2;
}

/* ---------------------------- Empty Message ---------------------------- */
.empty-message {
    text-align: center;
    font-size: 18px;
    color: #cfcfff;
    margin-top: 50px;
}

/* ---------------------------- Responsive ---------------------------- */
@media(max-width:900px){
    .main { margin-left: 0; }
    .sidebar { left:-260px; }
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
<a href="dashboard.php" style="text-decoration:none; color:#007c91; font-weight:bold; padding:80px;">← Back to Dashboard</a>

<div class="container">
    <h2><center>⭐ Saved Jobs</center></h2><br>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="job-card">
                <h2><?= htmlspecialchars($row['title']) ?></h2>
                <p>🏢 <?= htmlspecialchars($row['company_name']) ?> — 📍 <?= htmlspecialchars($row['location']) ?></p>
                <p>📅 Saved On: <?= date('F j, Y', strtotime($row['saved_on'])) ?></p>
                <div class="btns">
                    <a class="view-btn" href="view_job.php?job_id=<?= $row['job_id'] ?>">View</a>
<form method="POST" action="save_jobs.php" style="display:inline;">
    <input type="hidden" name="job_id" value="<?= $row['job_id'] ?>">
    <button class="remove-btn" type="submit">Remove</button>
</form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center;">😕 No saved jobs yet. Start browsing and save some!</p>
    <?php endif; ?>
</div>
<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
