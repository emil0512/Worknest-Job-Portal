<?php
session_start();
require_once "../db.php";

// Get logged in jobseeker ID if available
$js_id = $_SESSION['jobseeker_id'] ?? null;

// Step 1: Collect jobseeker keywords if logged in
$keywords = [];
if ($js_id) {
    $stmt = $conn->prepare("
        SELECT skills_text, education_text, experience_text 
        FROM jobseeker_profiles 
        WHERE js_id = ?
    ");
    $stmt->bind_param("i", $js_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $profile = $res->fetch_assoc();
    $stmt->close();

    if ($profile) {
        $keywords = array_merge(
            explode(' ', $profile['skills_text'] ?? ''),
            explode(' ', $profile['education_text'] ?? ''),
            explode(' ', $profile['experience_text'] ?? '')
        );
    }

    // Remove duplicates and empty values
    $keywords = array_filter(array_unique($keywords));
}


// Step 2: Fetch counselors and check matches
$counselors = [];
$sql = "SELECT * FROM counselor_profiles";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
    $is_recommended = false;

    // If logged in, check for match
    if (!empty($keywords)) {
        foreach ($keywords as $kw) {
            if (
                stripos($row['specialization'], $kw) !== false ||
                stripos($row['skills'], $kw) !== false
            ) {
                $is_recommended = true;
                break;
            }
        }
    }

    $row['is_recommended'] = $is_recommended;
    $counselors[] = $row;
}

// Step 3: Sort counselors so recommended appear first
usort($counselors, function ($a, $b) {
    return $b['is_recommended'] <=> $a['is_recommended'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Book Counseling Session | WorkNest</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
<style>
/* ========================
   BODY & BACKGROUND
======================== */
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #0a0a0f, #0f0f1e, #1a1a2e, #000);
    color: #e0e0ff;
    padding: 40px;
    overflow-x: hidden;
}
#bgVideo {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    z-index: -3;
    filter: brightness(0.35) contrast(1.2) saturate(1.1);
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
   TOP-RIGHT BUTTON
======================== */
.top-right-btn {
    position: fixed;
    top: 20px; right: 20px;
    background: linear-gradient(135deg, #6c63ff, #bb86fc);
    color: #fff;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 0 20px #6c63ff, 0 0 35px #bb86fc;
    transition: 0.3s;
}
.top-right-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 0 40px #6c63ff, 0 0 60px #bb86fc;
}

/* ========================
   MAIN CONTENT
======================== */
.main {
    margin-left: 10px;
    padding: 20px;
    text-align: center;
}
.main h1 {
    font-size: 32px;
    font-weight: 700;
    color: #bb86fc;
    text-shadow: 0 0 0px #6c63ff, 0 0 25px #9d4edd;
    margin-bottom: 35px;
}

/* ========================
   COUNSELOR CARDS
======================== */
.counselor-card {
    background: rgba(25,25,45,0.9);
    border-left: 6px solid #6c63ff;
    padding: 20px;
    margin: 0 auto 25px auto;
    max-width: 800px;
    border-radius: 12px;
    color: #e0e0ff;
    transition: 0.3s;
    text-align: left;
    box-shadow: 0 0 25px rgba(108,99,255,0.4);
}
.counselor-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0 50px #6c63ff, 0 0 70px #bb86fc;
}
.counselor-card.recommended {
    border-left-color: #00d4ff;
    background: rgba(0,20,50,0.9);
    box-shadow: 0 0 40px #00d4ff55;
}
.counselor-card h2 {
    margin: 0 0 10px;
    color: #bb86fc;
    text-shadow: 0 0 0px #6c63ff;
}
.counselor-card p {
    margin: 6px 0;
    color: #cfcfff;
}
.recommended-text {
    color: #00d4ff;
    font-weight: 600;
    text-shadow: 0 0 0px #00d4ff77;
}

/* ========================
   BOOK BUTTON
======================== */
.book-btn {
    background: linear-gradient(90deg,#00ffff,#0077ff);
    color: #fff;
    padding: 12px 18px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    margin-top: 15px;
    width: 100%;
    box-shadow: 0 0 5px #00ffff, 0 0 0px #0077ff;
    transition: 0.3s;
}
.book-btn:hover {
    transform: scale(1.05);
    background: linear-gradient(90deg,#0077ff,#00ffff);
    box-shadow: 0 0 25px #00ffff, 0 0 40px #0077ff;
}

/* ========================
   SCROLLBAR
======================== */
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb {
    background: linear-gradient(#6c63ff,#bb86fc);
    border-radius:6px;
}
.sidebar::-webkit-scrollbar-track { background: rgba(25,25,45,0.2); }

/* ========================
   RESPONSIVE
======================== */
@media(max-width:900px){
    .main { margin-left: 0; }
    .sidebar { left:-280px; }
}
.back-btn {
    position: fixed;
    top: 20px;
    left: 350px;
    z-index: 1002; /* below sidebar */
    padding: 10px 18px;
    background: linear-gradient(135deg,#6c63ff,#bb86fc);
    color: #fff;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    box-shadow: 0 0 12px rgba(106,17,203,0.6);
    transition: 0.3s;
}
.back-btn:hover {
    background: linear-gradient(135deg,#bb86fc,#6c63ff);
    box-shadow: 0 0 16px rgba(159,122,255,0.8);
}



</style>


</head>
<body>
<video autoplay muted loop id="bgVideo">
    <source src="183279-870457579_small.mp4" type="video/mp4">
    Your browser does not support HTML5 video.
</video>
<a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
<a href="my_sessions.php" class="top-right-btn">My Sessions</a>
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
<div class="main">
  <h1><center>Available Counselors</center></h1>

  <?php if (empty($counselors)): ?>
    <p>No counselors found.</p>
  <?php else: ?>
    <?php foreach ($counselors as $c): ?>
      <div class="counselor-card <?= $c['is_recommended'] ? 'recommended' : '' ?>">
        <h2><?= htmlspecialchars($c['name']) ?></h2>
        <p><strong>Specialization:</strong> <?= htmlspecialchars($c['specialization']) ?></p>
        <p><strong>Experience:</strong> <?= htmlspecialchars($c['experience']) ?></p>
        <p><strong>Skills:</strong> <?= htmlspecialchars($c['skills']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($c['email']) ?></p>
        <p><strong>Phone:</strong> <?= htmlspecialchars($c['phone']) ?></p>

        <?php if ($c['is_recommended']): ?>
          <p class="recommended-text">✅ Recommended for You</p>
        <?php endif; ?>

        <form action="appointment.php" method="GET">
          <input type="hidden" name="counselor_id" value="<?= $c['counselor_id'] ?>">
          <button type="submit" class="book-btn">Book Appointment</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>

</body>
</html>