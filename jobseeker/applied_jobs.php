<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];

/* ----------------------------
   Build user_words
----------------------------- */
$prof_stmt = $conn->prepare("SELECT skills_text, education_text, experience_text FROM jobseeker_profiles WHERE js_id = ?");
$prof_stmt->bind_param("i", $js_id);
$prof_stmt->execute();
$profile = $prof_stmt->get_result()->fetch_assoc() ?? ['skills_text'=>'','education_text'=>'','experience_text'=>''];
$prof_stmt->close();

$user_text_parts = [
    $profile['skills_text'],
    $profile['education_text'],
    $profile['experience_text']
];

function to_words($text) {
    $text = strtolower($text ?? '');
    $parts = preg_split('/\W+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique($parts));
}
$user_words = to_words(implode(' ', $user_text_parts));

/* ----------------------------
   Fetch applied jobs (unique)
----------------------------- */
$sql = "SELECT 
    a.id AS app_id,
    a.job_id,
    a.applied_on,
    a.status,
    a.application_stage,
    j.title,
    j.description,
    j.location,
    j.job_type,
    j.keywords,
    e.company_name,
    jm.score AS match_score
FROM applications a
INNER JOIN jobs j ON a.job_id = j.id
INNER JOIN employer_profiles e ON j.emp_id = e.emp_id
LEFT JOIN job_matches jm ON jm.job_id = a.job_id AND jm.js_id = a.user_id
WHERE a.user_id = ?
GROUP BY a.id
ORDER BY a.applied_on DESC
";


$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $js_id);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Applied Jobs | WorkNest</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
/* Body */
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
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

/* Main Content */
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
    text-shadow: 0 0 15px #6c63ff, 0 0 30px #9d4edd;
    margin-bottom: 25px;
}

/* Job Listings */
.job-listing { width: 100%; max-width: 800px; }
.job-card {
    background: rgba(20, 20, 40, 0.95);
    padding: 25px;
    margin-bottom: 25px;
    border-left: 8px solid #6c63ff;
    border-radius: 12px;
    box-shadow: 0 0 35px rgba(108, 99, 255, 0.6);
    transition: transform 0.3s, box-shadow 0.3s;
}
.job-card:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: 0 0 70px #6c63ff, 0 0 100px #9d4edd;
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

/* Match Score Bar */
.match-score-bar {
    margin-top: 10px;
    height: 18px;
    width: 100%;
    background: #222;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 0 15px #6c63ff;
}
.match-score-fill {
    height: 100%;
    color: white;
    text-align: center;
    line-height: 18px;
    font-size: 12px;
    font-weight: bold;
    transition: width 0.5s ease-in-out;
    box-shadow: 0 0 10px #6c63ff inset;
}
.score-low { background: #ff4d4d; }
.score-medium { background: #ffa500; }
.score-high { background: #28a745; }

/* Empty Message */
.empty-message {
    text-align: center;
    font-size: 18px;
    color: #cfcfff;
    margin-top: 50px;
}

/* Scrollbar for Sidebar */
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb { background-color:#7f5aff; border-radius:8px; }
.sidebar::-webkit-scrollbar-track { background:rgba(20,20,40,0.3); }

/* Responsive */
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
  filter: brightness(0.4) contrast(1.2); /* Darken for neon readability */
}
.back-btn {
    position: fixed;
    top: 40px;
    left: 350px; /* adjust if sidebar toggle overlaps */
    z-index: 1002;
    padding: 10px 18px;
    background: linear-gradient(90deg,#6a11cb,#2575fc);
    color: #fff;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    box-shadow: 0 0 12px rgba(106,17,203,0.6);
    transition: 0.3s;
}
.back-btn:hover {
    background: linear-gradient(90deg,#2575fc,#6a11cb);
    box-shadow: 0 0 16px rgba(159,122,255,0.8);
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

<a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
<div class="main">
<h1>My Applied Jobs</h1>
<div class="job-listing">

<?php if (!empty($rows)): ?>
    <?php foreach ($rows as $row): 
    $score = intval($row['match_score'] ?? 0);
$score_class = $score >= 75 ? "score-high" : ($score >= 50 ? "score-medium" : "score-low");

?>
<div class="job-card">
    <h3>
        <?= htmlspecialchars($row['title']) ?>
        <?php if (strtolower($row['status']) === 'rejected' || strtolower($row['application_stage']) === 'rejected'): ?>
            <span style="color:#ff4d4d; font-size:14px; font-weight:bold; margin-left:10px;">❌ Rejected</span>
        <?php endif; ?>
    </h3>

    <p class="company">🏢 <?= htmlspecialchars($row['company_name']) ?> — 📍 <?= htmlspecialchars($row['location']) ?></p>
    <p><?= nl2br(htmlspecialchars(mb_strimwidth(strip_tags($row['description']), 0, 120, '...'))) ?></p>
    <p>📅 Applied On: <?= date('d M Y', strtotime($row['applied_on'])) ?></p>

    <?php if (strtolower($row['status']) !== 'rejected' && strtolower($row['application_stage']) !== 'rejected'): ?>
        <div class="match-score-bar">
            <div class="match-score-fill <?= $score_class ?>" style="width: <?= $score ?>%;"><?= $score ?>%</div>
        </div>
    <?php else: ?>
        <p style="color:#ff6666; font-style:italic;">Your application was not selected for this position.</p>
    <?php endif; ?>
</div>

<?php endforeach; ?>

<?php else: ?>
    <p class="empty-message">😕 You haven’t applied to any jobs yet.</p>
<?php endif; ?>
</div>
</div>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
