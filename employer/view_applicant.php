<?php
session_start();
require_once("../db.php");

// 1. Authentication & Role check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id   = intval($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'Employer';

// 2. Validate GET params
$job_id  = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// 3. Basic validation of IDs
if ($job_id <= 0 || $user_id <= 0) {
    die("<div style='padding:20px; color:red;'>❌ Missing or invalid job/user ID.</div>");
}

// 4. Verify job belongs to this employer
$jobStmt = $conn->prepare("SELECT title FROM jobs WHERE id = ? AND emp_id = ? LIMIT 1");
$jobStmt->bind_param("ii", $job_id, $emp_id);
$jobStmt->execute();
$jobRes = $jobStmt->get_result();

if ($jobRes->num_rows === 0) {
    die("<div style='padding:20px; color:red;'>❌ Invalid job or unauthorized access.</div>");
}
$jobRow   = $jobRes->fetch_assoc();
$jobTitle = $jobRow['title'] ?? 'Job';
$jobStmt->close();

// 5. Verify user applied for this job
$appCheckStmt = $conn->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ? LIMIT 1");
$appCheckStmt->bind_param("ii", $job_id, $user_id);
$appCheckStmt->execute();
$appCheckRes = $appCheckStmt->get_result();

if ($appCheckRes->num_rows === 0) {
    die("<div style='padding:20px; color:red;'>❌ Applicant not found or has not applied for this job.</div>");
}
$appCheckStmt->close();

// 6. Fetch applicant & application info
$appStmt = $conn->prepare("
    SELECT 
        u.username,
        u.email,
        js.resume_path AS resume,
        js.skills_text,
        js.education_text,
        js.experience_text,
        a.applied_on,
        a.match_score,
        a.status,
        a.application_stage
    FROM applications a
    INNER JOIN users u ON a.user_id = u.user_id
    LEFT JOIN jobseeker_profiles js ON js.js_id = u.user_id
    WHERE a.job_id = ? AND a.user_id = ?
    LIMIT 1
");
$appStmt->bind_param("ii", $job_id, $user_id);
$appStmt->execute();
$appRes = $appStmt->get_result();

if ($appRes->num_rows === 0) {
    die("<div style='padding:20px; color:red;'>❌ Applicant details not found.</div>");
}
$data = $appRes->fetch_assoc();
$appStmt->close();

// 7. Check shortlist status (from application status or separate table)
$shortlisted = false;
$statusLower = strtolower($data['status'] ?? '');
$stageLower  = strtolower($data['application_stage'] ?? '');

if ($statusLower === 'shortlisted' || $stageLower === 'shortlisted') {
    $shortlisted = true;
} else {
    $sStmt = $conn->prepare("SELECT 1 FROM shortlisted_applicants WHERE job_id = ? AND user_id = ? LIMIT 1");
    $sStmt->bind_param("ii", $job_id, $user_id);
    $sStmt->execute();
    $sStmt->store_result();
    if ($sStmt->num_rows > 0) $shortlisted = true;
    $sStmt->close();
}

// 8. Helper: resolve resume URL safely
function resolve_resume_url($resume) {
    if (!$resume) return null;

    $filename = basename($resume);

    $resume_path = __DIR__ . '/../uploads/resumes/' . $filename;
    if (file_exists($resume_path)) {
        return '../uploads/resumes/' . $filename;
    }

    return null;
}

$resumeUrl = resolve_resume_url($data['resume'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Applicant Profile | WorkNest</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #0a0f1c; /* Deep cyberpunk dark */
    color: #e0e0ff;
    margin: 0;
    padding: 0;
}
/* Sidebar */
.emp-sidebar {
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

.emp-sidebar.open { left: 0; overflow-y: auto; }

.emp-sidebar::-webkit-scrollbar { width: 6px; }
.emp-sidebar::-webkit-scrollbar-thumb { background-color: #9d4edd; border-radius: 8px; }
.emp-sidebar::-webkit-scrollbar-track { background: rgba(30,30,50,0.8); }
.emp-sidebar h3 {
    margin-top: -30px;
    margin-bottom: 30px;
    color: #bb86fc;
    font-size: 28px;
    text-align: center;
}
.emp-sidebar a {
    display: block;
    padding: 16px;
    margin-bottom: 10px;
    text-decoration: none;
    color: #cfcfff;
    font-weight: 600;
    border-radius: 6px;
    transition: background 0.2s ease;
}
.emp-sidebar a:hover { background: rgba(138,43,226,0.3); }

/* Toggle button */
.emp-toggle {
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
.emp-toggle:hover { background: rgba(138,43,226,0.4); }

/* Main content */
.emp-main {
    margin-left: 300px;
    padding: 40px;
}
.back-link {
    display: inline-block;
    margin-bottom: 20px;
    background: rgba(100, 100, 255, 0.15);
    color: #a4baff;
    padding: 10px 18px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    border: 1px solid rgba(123, 91, 255, 0.4);
    transition: background 0.2s;
}
.back-link:hover {
    background: rgba(123, 91, 255, 0.3);
    color: #fff;
}

/* Profile Card */
.profile-container {
    max-width: 800px;
    background: #15182b;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 0 16px rgba(123, 91, 255, 0.3);
    margin: auto;
}
h2 {
    color: #8ecbff;
    text-align: center;
    margin-bottom: 20px;
    text-shadow: 0 0 6px rgba(142, 203, 255, 0.6);
}
p {
    color: #d0d2ff;
    margin: 10px 0;
}
.info-box {
    background: rgba(20, 25, 45, 0.9);
    padding: 20px;
    margin-top: 20px;
    border-radius: 10px;
    border: 1px solid rgba(123, 91, 255, 0.3);
}

/* Buttons */
.btn {
    display: inline-block;
    padding: 12px 18px;
    margin-top: 15px;
    margin-right: 10px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
}
.btn-resume {
    background: linear-gradient(90deg, #6b4bff, #7a68ff);
    color: white;
    border: none;
}
.btn-resume:hover {
    opacity: 0.85;
}
.btn-message {
    background: linear-gradient(90deg, #4b89ff, #67a4ff);
    color: white;
}
.btn-message:hover {
    opacity: 0.85;
}
.btn-shortlist {
    background: linear-gradient(90deg, #9d4bff, #bb68ff);
    color: white;
}
.btn-shortlist:hover {
    opacity: 0.85;
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
</style></head>
<body>
<video autoplay muted loop id="bgVideo">
    <source src="183279-870457579_small.mp4" type="video/mp4">
    Your browser does not support HTML5 video.
</video>
<div class="emp-toggle" onclick="toggleSidebar()">☰</div>

<div class="emp-sidebar" id="empSidebar">
    <h3>WorkNest</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="post_job.php">📢 Post New Jobs</a>
    <a href="view_jobs.php">📋 View Job Listings</a>
    <a href="manage_applicants.php?job_id=<?= $job_id ?>">👥 Manage Applicants</a>
    <a href="ats_dashboard.php">📈Shortlisted Applicants</a>
    <a href="inbox.php">📥 Inbox</a>
    <a href="../logout.php">🚪 Logout</a>
</div>
<div class="emp-main">
    <a href="manage_applicants.php?job_id=<?= $job_id ?>" class="back-link">⬅ Back to Manage Applicants</a>

    <div class="profile-container">
        <h2>👤 Applicant: <?= htmlspecialchars($data['username'] ?? 'Unknown') ?></h2>

        <p><strong>📧 Email:</strong> <?= htmlspecialchars($data['email'] ?? 'N/A') ?></p>
        <p><strong>Application Stage:</strong> <?= htmlspecialchars($data['application_stage'] ?? 'N/A') ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($data['status'] ?? 'N/A') ?></p>
        <p><strong>📅 Applied On:</strong> <?= !empty($data['applied_on']) ? date("M d, Y H:i", strtotime($data['applied_on'])) : 'N/A' ?></p>
        <p><strong>🎯 Match Score:</strong> <?= isset($data['match_score']) ? intval($data['match_score']) . '%' : 'N/A' ?></p>

        <div class="info-box">
            <p><strong>🎓 Education:</strong><br><?= nl2br(htmlspecialchars($data['education_text'] ?? 'Not provided.')) ?></p>
            <p><strong>💼 Experience:</strong><br><?= nl2br(htmlspecialchars($data['experience_text'] ?? 'Not provided.')) ?></p>
            <p><strong>🛠 Skills:</strong><br><?= nl2br(htmlspecialchars($data['skills_text'] ?? 'Not provided.')) ?></p>
        </div>

        <?php if ($resumeUrl): ?>
            <a href="<?= htmlspecialchars($resumeUrl) ?>" target="_blank" class="btn btn-resume">📄 View Resume</a>
        <?php else: ?>
            <p style="color:#888; margin-top:10px;"><i>❌ No resume uploaded or file not found.</i></p>
        <?php endif; ?>

        <a href="send_message.php?job_id=<?= $job_id ?>&user_id=<?= $user_id ?>" class="btn btn-message">📨 Message</a>

        <?php if (!$shortlisted): ?>
            <a href="shortlist_applicant.php?job_id=<?= $job_id ?>&user_id=<?= $user_id ?>" class="btn btn-shortlist">✅ Shortlist</a>
        <?php else: ?>
            <p style="color:green; font-weight:bold; margin-top:15px;">🎯 Already Shortlisted</p>
        <?php endif; ?>
    </div>
</div>
<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}
</script>
</body>
</html>
