<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Confirmed | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
 <style>
/* ========================
   BODY & BACKGROUND
======================== */
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    background: linear-gradient(135deg, #0d0d0d, #0f0f1e, #1a1a2e, #000);
    color: #e0e0ff;
    padding: 40px;
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

/* ========================
   CONFIRMATION BOX
======================== */
.box {
    max-width: 550px;
    margin: 120px auto 0 auto;
    background: rgba(20,20,40,0.95);
    padding: 50px;
    border-radius: 16px;
    box-shadow: 0 0 50px rgba(108,99,255,0.5), 0 0 80px rgba(157,78,221,0.4);
    text-align: center;
    color: #e0e0ff;
    border-left: 8px solid #6c63ff;
    animation: fadeIn 1s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.box h2 {
    color: #00ff99;
    margin-bottom: 20px;
    font-weight: 500;
    text-shadow: 0 0 0px #00ff99, 0 0 0px #00ffcc;
}
.box p {
    font-size: 16px;
    margin-top: 10px;
    color: #cfcfff;
}

/* ========================
   BUTTON LINK
======================== */
.box a {
    display: inline-block;
    margin-top: 25px;
    text-decoration: none;
    background: linear-gradient(135deg, #6c63ff, #9d4edd);
    color: white;
    padding: 14px 24px;
    border-radius: 10px;
    font-weight: 700;
    transition: all 0.3s ease;
    box-shadow: 0 0 20px #6c63ff55, 0 0 35px #9d4edd55;
}
.box a:hover {
    background: linear-gradient(135deg, #5a52d4, #7b3fbf);
    transform: scale(1.08);
    box-shadow: 0 0 40px #6c63ffaa, 0 0 60px #bb86fccc;
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
<div class="box">
    <h2>🎉 Booking Confirmed!</h2>
    <p>Your counseling session has been successfully scheduled.</p>
    <a href="dashboard.php">Return to Dashboard</a>
</div>
<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
