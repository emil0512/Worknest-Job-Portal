<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];

if (!isset($_GET['booking_id'])) {
    die("Invalid session.");
}

// Remove any database connection or booking logic
$booking = [
    'counselor_name' => 'N/A',
    'session_date' => 'N/A',
    'session_time' => 'N/A'
];

$payment_disabled_msg = "Booking confirmation is currently disabled.";

?>

<!DOCTYPE html>
<html>
<head>
    <title>Confirm Booking | WorkNest</title>
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
    max-width: 600px;
}
h2 {
    text-align: center;
    color: #b29bff;
    text-shadow: 0 0 10px #7a5cf4;
    margin-bottom: 25px;
}
.summary p {
    margin: 10px 0;
    font-size: 16px;
}
a.back-btn {
    display: inline-block;
    margin-bottom: 20px;
    color: #7ac7ff;
    font-weight: bold;
    text-decoration: none;
}
a.back-btn:hover { text-decoration: underline; }
button {
    background: linear-gradient(90deg,#6a11cb,#2575fc);
    border: none;
    color: white;
    padding: 14px;
    width: 100%;
    font-size: 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
}
button:hover {
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
<div class="container">
  <a class="back-btn" href="dashboard.php">← Back to Dashboard</a>
    <h2>💸 Confirm Your Booking</h2>
    <p style="text-align:center; font-weight:bold; color:#ff6b6b; margin-top:20px;">
        <?= $payment_disabled_msg ?>
    </p>
    <form method="POST">
        <button type="submit">💳 Pay & Confirm</button>
    </form>
</div>
<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
