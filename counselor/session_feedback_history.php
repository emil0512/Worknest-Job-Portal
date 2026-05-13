<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: ../login.php");
    exit();
}

$counselor_id = $_SESSION['user_id'];

// Fetch distinct reviews specific to this counselor
$stmt = $conn->prepare("
    SELECT r.id AS rating_id, r.rating, r.review, r.created_at, 
           jp.fullname AS jobseeker_name,
           a.date AS appointment_date, a.time AS appointment_time
    FROM ratings r
    JOIN jobseeker_profiles jp ON r.rater_id = jp.js_id
    LEFT JOIN appointments a ON r.appointment_id = a.id
    WHERE r.target_type = 'counselor' AND r.target_id = ?
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$feedbacks = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Session Feedback | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a001f, #1a0033);
            color: #eee;
            padding: 40px;
            margin: 0;
        }
        h1 {
            text-align: center;
            color: #bb86fc;
            margin-bottom: 30px;
            
        }
        .feedback-container { max-width: 900px; margin: auto; }
        .feedback-card {
            background: rgba(20, 20, 45, 0.95);
            border-left: 6px solid #6200ea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(98, 0, 234, 0.7),
                        0 0 25px rgba(98, 0, 234, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .feedback-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0 20px rgba(187, 134, 252, 0.9),
                        0 0 35px rgba(0, 191, 255, 0.6);
        }
        .feedback-card h3 {
            margin: 0;
            color: #00e5ff;
            font-size: 1.3em;
           
        }
        .feedback-card p {
            margin: 8px 0;
            color: #ddd;
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
  <a href="view_seekers.php">🧑‍💼 View Job Seekers</a>
  <a href="schedule_sessions.php">📅 Schedule Sessions</a>
  <a href="track_paid_sessions.php">💰 Track Paid Sessions</a>
  <a href="session_feedback_history.php">📜 Feedback History</a>
  <a href="messages.php">📩 Counselor Chat</a>
  <a href="../logout.php">🚪 Logout</a>
</div>


<h1>⭐ Feedback from Jobseekers</h1>
<div class="feedback-container">
    <?php if ($feedbacks->num_rows > 0): ?>
        <?php while ($row = $feedbacks->fetch_assoc()): ?>
            <div class="feedback-card">
                <h3><?= htmlspecialchars($row['jobseeker_name']) ?></h3>
                <?php if (!empty($row['appointment_date']) && !empty($row['appointment_time'])): ?>
                    <p>📅 Appointment: <?= date('M j, Y', strtotime($row['appointment_date'])) ?> at <?= htmlspecialchars($row['appointment_time']) ?></p>
                <?php endif; ?>
                <p>📅 Review Submitted: <?= date('F j, Y H:i', strtotime($row['created_at'])) ?></p>
                <?php if (!empty($row['rating'])): ?>
                    <p>⭐ Rating: <?= intval($row['rating']) ?>/5</p>
                <?php endif; ?>
                <p>💬 Review: <?= nl2br(htmlspecialchars($row['review'])) ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center;">🫤 No feedback received yet.</p>
    <?php endif; ?>
</div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
