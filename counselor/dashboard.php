<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$counselor_id = $_SESSION['user_id'];

// 📌 Fetch profile
$stmt = $conn->prepare("SELECT * FROM counselor_profiles WHERE counselor_id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// 📌 Fetch feedback from ratings
$feedbacks = [];
$stmt = $conn->prepare("
    SELECT r.rating, r.review, r.created_at, j.fullname AS jobseeker_name
    FROM ratings r
    JOIN jobseeker_profiles j ON r.rater_id = j.js_id
    WHERE r.target_type = 'counselor' AND r.target_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$feedbacks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Keep ONLY ONE definition
function isProfileComplete($profile) {
    if (!$profile) return false;
    return !empty($profile['name']) && !empty($profile['experience']) && !empty($profile['specialization']) && !empty($profile['qualifications']);
}
$profile_complete = isProfileComplete($profile);

// ... later: just use the variable (no redeclare)
$profile_complete = isProfileComplete($profile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Counselor Dashboard | WorkNest</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background: #0a0a1a;
      color: #d0d0f5;
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

    /* Topbar */
    .topbar {
      margin-left: 260px;
      background: linear-gradient(90deg, #141432, #1c1c40);
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #3a3a6a;
    }
    .topbar h2 {
      margin: 0;
      color: #9d4edd;
      text-shadow: 0 0 4px rgba(157, 78, 221, 0.4);
    }
    .logout-btn {
      background: #4a00e0;
      color: white;
      padding: 8px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
      transition: background 0.3s;
    }
    .logout-btn:hover { background: #52057b; }

    /* Main Content */
    .main {
      margin-left: 260px;
      padding: 30px;
    }

    .profile-reminder {
      background: rgba(30, 30, 60, 0.9);
      border-left: 6px solid #bb86fc;
      padding: 20px;
      margin-bottom: 30px;
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(138,43,226,0.3);
    }
    .profile-reminder h3 { margin: 0 0 10px; color: #bb86fc; }
    .profile-reminder p { margin: 5px 0; color: #d0d0f5; }

    /* Stats Section */
    .stats {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    .stat-card {
      flex: 1;
      min-width: 200px;
      background: #1a1a2e;
      border: 1px solid rgba(138,43,226,0.3);
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 0 12px rgba(157,78,221,0.2);
    }
    .stat-card h4 {
      margin: 0 0 10px;
      color: #9d4edd;
      font-size: 18px;
    }
    .stat-card p {
      font-size: 28px;
      font-weight: bold;
      color: #ffffff;
      margin: 0;
      text-shadow: 0 0 6px rgba(157, 78, 221, 0.6);
    }

    .corner-cartoon {
      position: fixed;
      bottom: 0;
      right: 0;
      width: 220px;
      opacity: 0.5;
      z-index: 0;
    }

    /* Background Video */
    #bgVideo {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: -3;
      filter: brightness(0.25) contrast(1.2);
    }
  </style>
</head>
<body>
<video autoplay muted loop id="bgVideo">
    <source src="33577-397143942_small.mp4" type="video/mp4">
    Your browser does not support HTML5 video.
</video>
<!-- ☰ Toggle Button -->
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

<?php
// 📌 Fetch latest 5 messages addressed to this counselor
$messages = [];
$stmt = $conn->prepare("
    SELECT m.id, m.message, m.subject, m.sent_at, j.fullname AS jobseeker_name
    FROM messages m
    JOIN jobseeker_profiles j ON m.sender_id = j.js_id
    WHERE m.receiver_id = ? AND m.is_read = 0
    ORDER BY m.sent_at DESC
    LIMIT 5
");

$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// 📌 Fetch recent appointments for this counselor
$appointments = [];
$stmt = $conn->prepare("
    SELECT a.id, a.date, a.time, a.status, a.notes,
           j.fullname AS jobseeker_name, u.email AS jobseeker_email
    FROM appointments a
    JOIN jobseeker_profiles j ON a.jobseeker_id = j.js_id
    JOIN users u ON j.js_id = u.user_id
    WHERE a.counselor_id = ?
    ORDER BY a.date DESC, a.time DESC
    LIMIT 5
");

$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<div class="topbar">
  <h2>Welcome, <?= htmlspecialchars($username) ?> </h2>
  <div style="display:flex; align-items:center; gap:20px;">
    <!-- 🔔 Notification Bell -->
    <div class="bell-icon" onclick="toggleMessagePopup()" style="cursor:pointer; font-size:40px; position:relative;">
      🔔
      <?php if (!empty($messages)): ?>
        <span id="msgCount" style="position:absolute; top:-6px; right:-8px; background:red; color:white; font-size:12px; padding:2px 6px; border-radius:50%;">
          <?= count($messages) ?>
        </span>
      <?php endif; ?>
    </div>
    <a href="../logout.php"><button class="logout-btn">Logout</button></a>
  </div>
</div>

<!-- 📄 Main Content -->
<div class="main">

  <?php if (!$profile_complete): ?>
  <div class="profile-reminder">
    <h3>👤 Complete Your Profile</h3>
    <p>Your counselor profile is incomplete. Update it to receive better jobseeker matches and booking opportunities.</p>
    <a href="profile.php"><button class="logout-btn">Update Profile</button></a>
  </div>
  <?php else: ?>
  <div class="profile-reminder" style="border-left: 6px solid #00ffcc; background: rgba(15, 15, 35, 0.9); padding: 20px; border-radius: 12px;">

    <h3>✅ Profile Overview</h3>

<p><strong>Name:</strong> <?= htmlspecialchars($profile['name'] ?? 'Not set') ?></p>
<p><strong>Experience:</strong> <?= htmlspecialchars($profile['experience'] ?? 'Not set') ?> years</p>
<p><strong>Specialization:</strong> <?= htmlspecialchars($profile['specialization'] ?? 'Not set') ?></p>
<p><strong>Qualifications:</strong> <?= htmlspecialchars($profile['qualifications'] ?? 'Not set') ?></p>
<p><strong>Phone:</strong> <?= htmlspecialchars($profile['phone'] ?? 'Not set') ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($profile['email'] ?? 'Not set') ?></p>
<p><strong>Bio:</strong> <?= nl2br(htmlspecialchars($profile['bio'] ?? 'Not set')) ?></p>
<p><strong>Skills:</strong> <?= htmlspecialchars($profile['skills'] ?? 'Not set') ?></p>
<p><strong>Languages:</strong> <?= htmlspecialchars($profile['languages'] ?? 'Not set') ?></p>
<p><strong>Availability:</strong> <?= htmlspecialchars($profile['availability'] ?? 'Not set') ?></p>


    <a href="profile.php">
        <button class="logout-btn" style="background:#6d4c41; color:white; border:none; padding:8px 15px; border-radius:6px; cursor:pointer;">
            ✏ Edit Profile
        </button>
    </a>
</div>

  <?php endif; ?>
<!-- 📅 Recent Appointments -->
<div class="appointments-section" style="margin-bottom:30px;">
  <h3 style="color:#00ffcc; text-shadow:0 0 8px rgba(0,255,204,0.7);">📅 Recent Appointments</h3>
  
  <?php if (empty($appointments)): ?>
    <p style="color:#ccc;">No recent appointments yet.</p>
  <?php else: ?>
    <div style="display:flex; flex-wrap:wrap; gap:20px;">
      <?php foreach ($appointments as $appt): ?>
        <div style="flex:1; min-width:250px; background:#1a1a2e; border:1px solid rgba(0,255,204,0.3);
                    padding:15px; border-radius:12px; box-shadow:0 0 12px rgba(0,255,204,0.2);">
          <h4 style="margin:0; color:#00ffcc;">👤 <?= htmlspecialchars($appt['jobseeker_name']) ?></h4>
          <p style="margin:5px 0; color:#bb86fc;">📧 <?= htmlspecialchars($appt['jobseeker_email']) ?></p>
          <p style="margin:5px 0; color:#ffcc00;">📅 <?= htmlspecialchars($appt['date']) ?> at <?= htmlspecialchars(substr($appt['time'],0,5)) ?></p>
          <p style="margin:5px 0; color:#ddd;">Status: <strong><?= htmlspecialchars($appt['status']) ?></strong></p>
          <?php if (!empty($appt['notes'])): ?>
            <p style="margin:5px 0; color:#aaa;">📝 <?= nl2br(htmlspecialchars($appt['notes'])) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

  <!-- ⭐ Jobseeker Feedback -->
  <div class="feedback-section" style="margin-top:30px;">
    <h3 style="color:#ffcc00; text-shadow:0 0 8px rgba(255,204,0,0.7);">⭐ Recent Feedback</h3>
    
    <?php if (empty($feedbacks)): ?>
      <p style="color:#ccc;">No feedback available yet.</p>
    <?php else: ?>
      <?php foreach ($feedbacks as $fb): ?>
        <div style="background:rgba(40,20,60,0.9); border-left:6px solid #ffcc00; padding:15px; margin:15px 0; border-radius:10px; box-shadow:0 0 10px rgba(255,204,0,0.3);">
          <p style="margin:0; font-weight:bold; color:#bb86fc;">👤 <?= htmlspecialchars($fb['jobseeker_name']) ?></p>
          <p style="margin:5px 0; color:#ffcc00;">Rating: <?= str_repeat("⭐", (int)$fb['rating']) ?></p>
          <p style="margin:5px 0; color:#ddd;"><?= nl2br(htmlspecialchars($fb['review'])) ?></p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}

function toggleMessagePopup() {
  let modal = document.getElementById("messageModal");
  let countBadge = document.getElementById("msgCount");

  if (modal.style.display === "flex") {
    modal.style.display = "none";
  } else {
    modal.style.display = "flex";

    // Remove count badge
    if (countBadge) countBadge.style.display = "none";

    // Mark messages as read
    fetch("mark_messages_read.php", {
      method: "POST"
    });
  }
}

</script>
<!-- 📩 Message Popup -->
<div id="messageModal" 
     style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
            background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:2000;">
  <div style="background:#1a1a2e; padding:20px; border-radius:12px; width:400px; max-height:70%; overflow-y:auto;">
    <h3 style="color:#9d4edd; margin-top:0;">📩 Latest Messages</h3>
    <?php if (empty($messages)): ?>
      <p style="color:#ccc;">No messages yet.</p>
    <?php else: ?>
      <?php foreach ($messages as $msg): ?>
        <div style="margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;">
          <p style="margin:0; font-weight:bold; color:#ffcc00;">
            <?= htmlspecialchars($msg['jobseeker_name']) ?>
          </p>
          <p style="margin:5px 0; color:#bb86fc;">
            <?= htmlspecialchars($msg['subject'] ?? 'No subject') ?>
          </p>
          <p style="margin:5px 0; color:#ddd;"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
          <small style="color:#aaa;"><?= htmlspecialchars($msg['sent_at']) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <button onclick="document.getElementById('messageModal').style.display='none'" 
            style="margin-top:10px; background:#9d4edd; color:#fff; padding:8px 15px; border:none; border-radius:6px; cursor:pointer;">
      Close
    </button>
  </div>
</div>

</body>
</html>
