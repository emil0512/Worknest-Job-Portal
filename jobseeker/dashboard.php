<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// ✅ Jobs Searched
$jobs_searched_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM searches WHERE js_id = ?");
$jobs_searched_stmt->bind_param("i", $js_id);
$jobs_searched_stmt->execute();
$jobs_searched = $jobs_searched_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$jobs_searched_stmt->close();

// ✅ Applications Sent
$applications_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM applications WHERE user_id = ?");
$applications_stmt->bind_param("i", $js_id);
$applications_stmt->execute();
$applications_sent = $applications_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$applications_stmt->close();

// ✅ Saved Jobs
$saved_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM saved_jobs WHERE user_id = ?");
$saved_stmt->bind_param("i", $js_id);
$saved_stmt->execute();
$saved_jobs = $saved_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$saved_stmt->close();

// 🧑 Profile
$profile_stmt = $conn->prepare("SELECT * FROM jobseeker_profiles WHERE js_id = ?");
$profile_stmt->bind_param("i", $js_id);
$profile_stmt->execute();
$profile = $profile_stmt->get_result()->fetch_assoc() ?? [];
$profile_stmt->close();


// 🧠 Get profile section content
$skills_text = $profile['skills_text'] ?? '';
$education_text = $profile['education_text'] ?? '';
$experience_text = $profile['experience_text'] ?? '';

function calculateProfileCompletion($profile) {
    $filled = 0;
    $totalFields = 5;

    if (!empty($profile['fullname'])) $filled++;
    if (!empty($profile['resume_path'])) $filled++;
    if (!empty($profile['skills_text'])) $filled++;
    if (!empty($profile['education_text'])) $filled++; // ✅ Now checking textarea directly
    if (!empty($profile['experience_text'])) $filled++; // ✅ Same here

    return floor(($filled / $totalFields) * 100);
}

$profile_completion = calculateProfileCompletion($profile);
$messages = [];
// 🔔 Unread Messages
$msg_stmt = $conn->prepare("
    SELECT 
        m.id,
        m.message,
        m.subject,
        m.sent_at,
        COALESCE(c.name, e.company_name) AS sender_name
    FROM messages m
    LEFT JOIN counselor_profiles c ON m.sender_id = c.counselor_id
    LEFT JOIN employer_profiles e ON m.sender_id = e.emp_id
    WHERE m.receiver_id = ? AND m.is_read = 0
    ORDER BY m.sent_at DESC
    LIMIT 5
");
$msg_stmt->bind_param("i", $js_id);
$msg_stmt->execute();
$messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$msg_stmt->close();

// 🎯 Fetch shortlisted applications (new only)
$shortlist_stmt = $conn->prepare("
    SELECT j.title AS job_title, a.applied_on 
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.user_id = ? 
      AND a.status = 'shortlisted'
      AND a.notified = 0
    ORDER BY a.applied_on DESC LIMIT 3
");
$shortlist_stmt->bind_param("i", $js_id);
$shortlist_stmt->execute();
$shortlisted = $shortlist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$shortlist_stmt->close();

foreach ($shortlisted as $s) {
    $messages[] = [
        'sender_name' => "System",
        'subject' => "🎉 Shortlisted!",
        'message' => "You have been shortlisted for " . $s['job_title'] . 
                     ". Please wait for the company to schedule an interview. " .
                     "You will be notified by the employer once you are selected for the interview.",
        'sent_at' => $s['applied_on']
    ];
}

// ✅ Mark shortlisted as notified
$conn->query("UPDATE applications SET notified = 1 WHERE user_id = $js_id AND status = 'shortlisted' AND notified = 0");


// ❌ Fetch rejected applications (new only)
$reject_stmt = $conn->prepare("
    SELECT j.title AS job_title, a.applied_on 
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.user_id = ? 
      AND a.status = 'rejected'
      AND a.notified = 0
    ORDER BY a.applied_on DESC LIMIT 3
");
$reject_stmt->bind_param("i", $js_id);
$reject_stmt->execute();
$rejected = $reject_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reject_stmt->close();

foreach ($rejected as $r) {
    $messages[] = [
        'sender_name' => "System",
        'subject' => "❌ Application Update",
        'message' => "Your application for " . $r['job_title'] . " has been rejected.",
        'sent_at' => $r['applied_on']
    ];
}

// ✅ Mark rejected as notified
$conn->query("UPDATE applications SET notified = 1 WHERE user_id = $js_id AND status = 'rejected' AND notified = 0");
$rating_score = 0;
$rating_msg = "";

// 🎯 Get accurate match count from parsed_resume_data table
$match_count = 0;

// Fetch matched keywords for the jobseeker
$stmt = $conn->prepare("SELECT matched_keywords FROM parsed_resume_data WHERE js_id = ?");
$stmt->bind_param("i", $js_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row && !empty($row['matched_keywords'])) {
    // Convert stored matched keywords string to array
    $matched_keywords = preg_split('/[\s,;]+/', strtolower(trim($row['matched_keywords'])));
    $matched_keywords = array_filter(array_unique($matched_keywords));
    $match_count = count($matched_keywords);
}
if ($match_count == 0) {
    $rating_score = 10;
    $rating_msg = "❌ No relevant skills detected. Please enhance your resume.";
} elseif ($match_count == 1) {
    $rating_score = 20;
    $rating_msg = "⚠️ Only one match found. Try adding more technical and professional keywords.";
} elseif ($match_count == 2) {
    $rating_score = 25;
    $rating_msg = "📉 Very few skills matched. Expand your resume with industry terms.";
} elseif ($match_count == 3) {
    $rating_score = 30;
    $rating_msg = "📝 Your resume is minimal. Consider including more job-specific skills.";
} elseif ($match_count == 4) {
    $rating_score = 35;
    $rating_msg = "📂 Limited keyword alignment. Add details of tools, frameworks, and roles.";
} elseif ($match_count == 5) {
    $rating_score = 40;
    $rating_msg = "🔎 Some skills matched. Strengthen your resume with certifications and projects.";
} elseif ($match_count == 6) {
    $rating_score = 45;
    $rating_msg = "⚡ A few matches detected. Add more domain expertise and responsibilities.";
} elseif ($match_count == 7) {
    $rating_score = 50;
    $rating_msg = "📊 Decent start. Highlight measurable achievements in your experience section.";
} elseif ($match_count == 8) {
    $rating_score = 55;
    $rating_msg = "📌 Good progress. Add keywords related to tools and soft skills.";
} elseif ($match_count == 9) {
    $rating_score = 58;
    $rating_msg = "✅ Almost there. Broaden your skill list to improve compatibility.";
} elseif ($match_count == 10) {
    $rating_score = 60;
    $rating_msg = "⭐ Solid resume foundation. Keep adding more role-specific expertise.";
} elseif ($match_count == 11) {
    $rating_score = 65;
    $rating_msg = "🎯 Above average. Highlight leadership or teamwork experience too.";
} elseif ($match_count == 12) {
    $rating_score = 68;
    $rating_msg = "🚀 Good set of skills. Add accomplishments to stand out.";
} elseif ($match_count == 13) {
    $rating_score = 70;
    $rating_msg = "💡 Strong match. Include advanced certifications if possible.";
} elseif ($match_count == 14) {
    $rating_score = 72;
    $rating_msg = "📈 Very good resume. Keep polishing with industry trends.";
} elseif ($match_count == 15) {
    $rating_score = 75;
    $rating_msg = "🌟 Excellent growth. Your resume is getting competitive.";
} elseif ($match_count == 16) {
    $rating_score = 80;
    $rating_msg = "🔥 Impressive alignment. Consider tailoring for specific job roles.";
} elseif ($match_count == 17) {
    $rating_score = 85;
    $rating_msg = "🏆 Strong resume. Employers will likely notice your skills quickly.";
} elseif ($match_count == 18) {
    $rating_score = 90;
    $rating_msg = "💼 Outstanding match. Your resume is highly professional.";
} elseif ($match_count == 19) {
    $rating_score = 92;
    $rating_msg = "🎖️ Almost perfect. Just a little more refinement needed.";
} elseif ($match_count == 20) {
    $rating_score = 95;
    $rating_msg = "🚀 Excellent resume! You're highly matchable to jobs.";
} else { // greater than 20
    $rating_score = 100;
    $rating_msg = "🌍 Exceptional resume! You're fully optimized for opportunities.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Jobseeker Dashboard | WorkNest</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>/* Body */
body {
  margin: 0;
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

/* Topbar */
.topbar {
  margin-left: 260px;
  background: linear-gradient(90deg, #111, #1a1a2e, #3a0ca3);
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 25px rgba(138, 43, 226, 0.8);
  border-radius: 0 0 20px 0;
}
.topbar h2 {
  margin: 0;
  color: #bb86fc;
  text-shadow: 0 0 12px #6c63ff, 0 0 20px #9d4edd;
  animation: glowText 2s ease-in-out infinite alternate;
}

/* Buttons */
.logout-btn {
  background: linear-gradient(135deg, #6c63ff, #9d4edd);
  color: white;
  padding: 10px 22px;
  border: none;
  border-radius: 12px;
  font-weight: bold;
  cursor: pointer;
  transition: 0.3s;
  box-shadow: 0 0 20px rgba(108, 99, 255, 0.8), 0 0 40px rgba(157, 78, 221, 0.6);
}
.logout-btn:hover {
  transform: scale(1.08);
  box-shadow: 0 0 40px #6c63ff, 0 0 60px #bb86fc, 0 0 80px #9d4edd;
}

/* Main */
.main { margin-left: 260px; padding: 30px; }

/* Search */
.search-bar {
  max-width: 600px;
  margin: 0 auto 30px;
  display: flex;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 0 25px rgba(138, 43, 226, 0.6);
}
.search-bar input {
  flex: 1;
  padding: 12px 15px;
  border: none;
  background: #111;
  color: #e0e0ff;
  outline: none;
  transition: 0.3s;
}
.search-bar input:focus {
  box-shadow: 0 0 12px #6c63ff;
  border-radius: 8px;
}
.search-bar button {
  background: #6c63ff;
  color: white;
  border: none;
  padding: 12px 18px;
  font-weight: bold;
  cursor: pointer;
  transition: 0.3s;
}
.search-bar button:hover {
  background: #9d4edd;
  box-shadow: 0 0 20px #9d4edd, 0 0 40px #6c63ff;
  transform: scale(1.05);
}

/* Profile Boxes */
.profile-reminder, .profile-display {
  background: rgba(20, 20, 40, 0.9);
  padding: 20px;
  margin-bottom: 30px;
  border-radius: 16px;
  box-shadow: 0 0 25px rgba(138, 43, 226, 0.5);
  border: 1px solid rgba(157, 78, 221, 0.5);
  transition: all 0.5s ease;
}
.profile-reminder:hover, .profile-display:hover {
  box-shadow: 0 0 50px rgba(138, 43, 226, 0.8);
  transform: translateY(-3px);
}
.profile-reminder h3, .profile-display h3 {
  color: #bb86fc;
  text-shadow: 0 0 10px #6c63ff, 0 0 20px #9d4edd;
}
.profile-display p { color: #cfcfff; }

/* Progress Bar */
.progress-bar {
  height: 16px;
  background: #222;
  border-radius: 10px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #6c63ff, #9d4edd, #bb86fc);
  width: <?= $profile_completion ?>%;
  transition: width 0.5s ease-in-out;
  box-shadow: 0 0 15px #6c63ff, 0 0 25px #9d4edd;
  animation: progressGlow 2s infinite alternate;
}
@keyframes progressGlow {
  from { box-shadow: 0 0 15px #6c63ff, 0 0 25px #9d4edd; }
  to { box-shadow: 0 0 25px #6c63ff, 0 0 35px #bb86fc; }
}

/* Stats */
.stats {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}
.stat-card {
  flex: 1;
  min-width: 200px;
  background: rgba(25, 25, 50, 0.9);
  border-left: 6px solid #6c63ff;
  padding: 20px;
  border-radius: 16px;
  box-shadow: 0 0 30px rgba(108, 99, 255, 0.6);
  transition: transform 0.3s, box-shadow 0.3s;
}
.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 0 50px #6c63ff, 0 0 80px #9d4edd;
}
.stat-card h4 { color: #bb86fc; }
.stat-card p { font-size: 28px; font-weight: bold; color: #fff; }

/* Chart */
canvas {
  max-width: 700px;
  margin: 40px auto;
  display: block;
  background: linear-gradient(180deg, #111, #1a1a2e);
  border-radius: 16px;
  box-shadow: 0 0 40px rgba(138, 43, 226, 0.6);
  animation: chartGlow 3s infinite alternate;
}
@keyframes chartGlow {
  from { box-shadow: 0 0 30px #6c63ff, 0 0 50px #9d4edd; }
  to { box-shadow: 0 0 50px #6c63ff, 0 0 70px #bb86fc; }
}
#bgVideo {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;   /* Fill screen without stretching */
  z-index: -3;         /* Behind sidebar, topbar, main content */
  filter: brightness(0.25) contrast(1.2); /* Darken for neon readability */
}

  </style>
</head>
<body>
<!-- Background Video -->
<video autoplay muted loop id="bgVideo">
    <source src="33577-397143942_small.mp4" type="video/mp4">
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
<div class="topbar">
  <h2>Welcome, <?= htmlspecialchars($username) ?> 🎉</h2>
  <div style="display:flex; align-items:center; gap:15px;">
    <div class="bell-icon" onclick="toggleMessagePopup()" style="cursor:pointer; font-size:30px; position:relative;">
      🔔
      <?php if (!empty($messages)): ?>
        <span id="msgCount" style="position:absolute; top:-6px; right:-8px; background:red; color:white; font-size:12px; padding:2px 6px; border-radius:50%;">
          <?= count($messages) ?>
        </span>
      <?php endif; ?>
    </div>
    <a href="applied_jobs.php"><button class="logout-btn">My Jobs</button></a>
    <a href="my_sessions.php"><button class="logout-btn">My Sessions</button></a>
    <a href="../logout.php"><button class="logout-btn">Logout</button></a>
</div>

</div>


<div class="main">
  <form class="search-bar" action="search_jobs.php" method="get">
    <input type="text" name="q" placeholder="Keyword, title, company..." />
    <input type="text" name="location" placeholder="Location" />
    <button type="submit">Search</button>
</form>


  <?php if ($profile_completion < 100): ?>
    <div class="profile-reminder">
      <h3>👋 Complete Your Profile</h3>
      <p>Your profile is <?= $profile_completion ?>% complete.</p>
      <div class="progress-bar"><div class="progress-bar-fill"></div></div>
      <a href="profile.php"><button class="logout-btn" style="margin-top:15px;">Update Profile</button></a>
    </div>
  <?php else: ?>
    <div class="profile-display" id="profileDisplay">
      <h3>🎯 Your Profile (100% Complete)</h3>
      <p><strong>Full Name:</strong> <?= htmlspecialchars($profile['fullname']) ?></p>
      <p><strong>Skills:</strong> <?= htmlspecialchars($skills_text) ?></p>
<p><strong>Education:</strong><br><?= nl2br(htmlspecialchars($education_text)) ?></p>
<p><strong>Experience:</strong><br><?= nl2br(htmlspecialchars($experience_text)) ?></p>

<?php if (!empty($profile['resume_path'])): ?>
    <p>
        <strong>Resume:</strong> 
        <a href="<?= htmlspecialchars('/job_portal/uploads/resumes/' . basename($profile['resume_path'])) ?>" target="_blank">View</a> 
        | 
        <a href="<?= htmlspecialchars('/job_portal/uploads/resumes/' . basename($profile['resume_path'])) ?>" download>Download</a>
    </p>
<?php endif; ?>

<?php if ($rating_score > 0): ?>
    <p><strong>Resume Rating:</strong> <?= htmlspecialchars($rating_score) ?>%</p>
    <p><?= htmlspecialchars($rating_msg) ?></p>
<?php endif; ?>

</div> <!-- Close your content container -->
<?php endif; ?> <!-- Close the outermost if block -->


    <!-- Appointment Updates Card -->
    <?php
    $appt_stmt = $conn->prepare("
        SELECT a.id, a.date, a.time, a.status, c.name 
        FROM appointments a
        JOIN counselor_profiles c ON a.counselor_id = c.counselor_id
        WHERE a.jobseeker_id = ? 
          AND (a.status = 'Refunded' OR a.date > CURDATE() OR a.time > CURTIME())
        ORDER BY a.date DESC, a.time DESC LIMIT 3
    ");
    $appt_stmt->bind_param("i", $js_id);
    $appt_stmt->execute();
    $appointments = $appt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $appt_stmt->close();
    ?>

    <?php if (!empty($appointments)): ?>
      <div class="profile-display" style="margin-top:25px;">
        <h3 style="color:#ffcc00;">📅 Appointment Updates</h3>
        <?php foreach ($appointments as $appt): ?>
          <div style="padding:10px; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:10px;">
            <p>With <strong><?= htmlspecialchars($appt['name']) ?></strong></p>
            <p style="color:#bb86fc;">Date: <?= htmlspecialchars($appt['date']) ?> | Time: <?= htmlspecialchars($appt['time']) ?></p>
            <small>Status: <?= htmlspecialchars($appt['status']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="stats">
    <div class="stat-card"><h4>Jobs Searched</h4><p><?= $jobs_searched ?></p></div>
    <div class="stat-card"><h4>Applications Sent</h4><p><?= $applications_sent ?></p></div>
    <div class="stat-card"><h4>Saved Jobs</h4><p><?= $saved_jobs ?></p></div>
  </div>

</div>
<script>
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
    fetch("mark_messages_read.php", { method: "POST" });
  }
}

</script>
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
           <?= htmlspecialchars($msg['sender_name']) ?>
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
