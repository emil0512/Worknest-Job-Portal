<?php
session_start();
include("../db.php");

// Check if user is logged in and role is jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];

// Fetch assigned counselors for this jobseeker
$stmt = $conn->prepare("
    SELECT ca.counselor_id, cp.name, cp.specialization, cp.email, cp.phone
    FROM counselor_assignments ca
    JOIN counselor_profiles cp ON ca.counselor_id = cp.counselor_id
    WHERE ca.jobseeker_id = ?
    ORDER BY ca.assigned_at DESC
");
$stmt->bind_param("i", $js_id);
$stmt->execute();
$assigned_counselors = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Assigned Counselors | WorkNest</title>
 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: radial-gradient(circle at top left, #0f0c29, #302b63, #24243e);
        color: #f5f5f5;
        padding: 30px;
    }

    .container {
        max-width: 900px;
        margin: auto;
        background: rgba(26, 26, 64, 0.9);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(159, 122, 255, 0.5);
    }

    h1 {
        text-align: center;
        margin-bottom: 30px;
        color: #9f7aff;
        text-shadow: 0 0 0px #7a5cf4, 0 0 0px #6f42c1;
    }

    .counselor-card {
        background: rgba(42, 42, 106, 0.85);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border-left: 4px solid #7a5cf4;
        position: relative;
        box-shadow: 0 0 15px rgba(159, 122, 255, 0.3);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .counselor-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0 25px rgba(159, 122, 255, 0.6);
    }

    .counselor-card h2 {
        margin-top: 0;
        color: #b29bff;
        text-shadow: 0 0 0px #7a5cf4;
    }

    .counselor-card p {
        margin: 5px 0;
        color: #dcdcff;
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


   .message-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        background: linear-gradient(90deg, #6a11cb, #2575fc);
        color: white;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        font-weight: bold;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 0 12px rgba(106, 17, 203, 0.6);
        transition: all 0.3s ease;
    }

    .message-btn:hover {
        background: linear-gradient(90deg, #2575fc, #6a11cb);
        box-shadow: 0 0 16px rgba(159, 122, 255, 0.8);
    }

    a.back-btn {
        display: inline-block;
        margin-bottom: 25px;
        color: #9f7aff;
        font-weight: bold;
        text-decoration: none;
        transition: color 0.3s ease, text-shadow 0.3s ease;
    }

    a.back-btn:hover {
        color: #fff;
        text-shadow: 0 0 10px #7a5cf4;
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
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    <h1>Your Assigned Counselors</h1>

    <?php if ($assigned_counselors->num_rows > 0): ?>
        <?php while ($counselor = $assigned_counselors->fetch_assoc()): ?>
            <div class="counselor-card">
                <h2><?= htmlspecialchars($counselor['name']) ?></h2>
                <p><strong>Specialization:</strong> <?= htmlspecialchars($counselor['specialization']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($counselor['email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($counselor['phone']) ?></p>
                <a 
                  href="messages.php?counselor_id=<?= urlencode($counselor['counselor_id']) ?>" 
                  class="message-btn"
                >
                  Send Message
                </a>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center;">You have not been assigned to any counselor yet.</p>
    <?php endif; ?>
</div>
<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
