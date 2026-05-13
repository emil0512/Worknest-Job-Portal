<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$job_id = intval($_GET['job_id'] ?? 0);

// Fetch job data
$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND emp_id = ?");
$stmt->bind_param("ii", $job_id, $emp_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("<div style='padding:20px;color:red;'>❌ Job not found or unauthorized access.</div>");
}
$job = $result->fetch_assoc();
$stmt->close();

$successMsg = $errorMsg = "";

// Update logic
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $job_type = trim($_POST['job_type']);
    $keywords = trim($_POST['keywords']);
    $status = trim($_POST['status']);

    if ($title && $description && $location && $job_type && $status) {
        $update = $conn->prepare("UPDATE jobs SET title=?, description=?, location=?, job_type=?, keywords=?, status=? WHERE id=? AND emp_id=?");
        $update->bind_param("ssssssii", $title, $description, $location, $job_type, $keywords, $status, $job_id, $emp_id);
        if ($update->execute()) {
            $successMsg = "✅ Job updated successfully.";
            // Refresh data
            $job = array_merge($job, $_POST);
        } else {
            $errorMsg = "❌ Error: " . $update->error;
        }
        $update->close();
    } else {
        $errorMsg = "⚠️ Please fill all required fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Job | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
 <style>
/* Global */
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #0d0d0d, #0f0f1e, #1a1a2e);
    color: #e0e0ff;
    overflow-x: hidden;
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

/* Topbar */
.emp-topbar {
    margin-left: 260px;
    background: rgba(20,20,40,0.95);
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid rgba(108,99,255,0.5);
}
.emp-topbar h2 {
    margin: 0;
    color: #bb86fc;
    text-shadow: 0 0 10px #6c63ff, 0 0 20px #9d4edd;
}
.emp-logout-btn {
    background: linear-gradient(135deg,#6c63ff,#9d4edd);
    color: #fff;
    padding: 8px 20px;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 0 5px #6c63ff,0 0 10px #9d4edd;
    transition: 0.3s;
}
.emp-logout-btn:hover {
    background: linear-gradient(135deg,#9d4edd,#6c63ff);
    transform: scale(1.05);
}

/* Main */
.emp-main {
    margin-left: 260px;
    padding: 30px;
}

/* Form Container */
.container {
    max-width: 700px;
    margin: 0 auto;
    background: rgba(30,30,50,0.9);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 0 25px rgba(108,99,255,0.5);
    border-left: 6px solid #6c63ff;
}
.container h2 {
    text-align: center;
    color: #bb86fc;
    margin-bottom: 25px;
    text-shadow: 0 0 10px #6c63ff, 0 0 20px #9d4edd;
}

/* Inputs */
input[type="text"],
textarea,
select {
    width: 100%;
    padding: 12px 15px;
    margin: 12px 0;
    border: 1px solid rgba(108,99,255,0.4);
    border-radius: 8px;
    font-size: 15px;
    background: rgba(20,20,40,0.85);
    color: #fff;
    outline: none;
    transition: 0.3s;
}
input:focus,
textarea:focus,
select:focus {
    border-color: #00ffff;
    box-shadow: 0 0 8px #00ffff, 0 0 15px #6c63ff;
}
textarea { resize: vertical; height: 120px; }

/* Buttons */
button,
a[style*="background:#ccc"] {
    background: linear-gradient(135deg,#6c63ff,#9d4edd);
    color: white !important;
    border: none;
    padding: 12px 25px;
    font-weight: bold;
    border-radius: 8px;
    cursor: pointer;
    display: inline-block;
    text-decoration: none;
    margin: 10px 0;
    transition: 0.3s;
    box-shadow: 0 0 5px #6c63ff,0 0 10px #9d4edd;
}
button:hover,
a[style*="background:#ccc"]:hover {
    background: linear-gradient(135deg,#9d4edd,#6c63ff);
    transform: scale(1.05);
}

/* Messages */
.msg {
    text-align: center;
    font-size: 16px;
    margin-bottom: 20px;
}
.success { color: #00ffcc; text-shadow: 0 0 5px #00ffcc; }
.error { color: #ff4d6d; text-shadow: 0 0 5px #ff4d6d; }

/* Back link */
.back-link {
    text-align: center;
    margin-top: 20px;
}
.back-link a {
    color: #00ffff;
    text-decoration: none;
    font-weight: 600;
}
.back-link a:hover { text-decoration: underline; }
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
<div class="emp-toggle" onclick="document.getElementById('empSidebar').classList.toggle('open')">☰</div>
<div class="emp-sidebar" id="empSidebar">
    <h3>WorkNest</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="post_job.php">📢 Post New Jobs</a>
    <a href="view_jobs.php">📋 View Job Listings</a>
  <a href="select_job_manage.php">👥 Manage Applicants</a>
    <a href="ats_dashboard.php">📈 Shortlisted Applicants</a>
    <a href="inbox.php">📥 Inbox</a>
    <a href="../logout.php">🚪 Logout</a>
</div>
<div class="emp-main">
    <div class="container">
        <h2>✏️ Edit Job</h2>

        <?php if ($successMsg): ?>
            <p class="msg success"><?php echo $successMsg; ?></p>
        <?php elseif ($errorMsg): ?>
            <p class="msg error"><?php echo $errorMsg; ?></p>
        <?php endif; ?>
<a href="view_jobs.php" style="display:inline-block; margin-bottom:20px; text-decoration:none;  padding:8px 14px; border-radius:6px; font-weight:bold;">⬅ Back to View Jobs</a>

        <form method="POST">
            <input type="text" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
            <textarea name="description" required><?php echo htmlspecialchars($job['description']); ?></textarea>
            <input type="text" name="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
            <input type="text" name="job_type" value="<?php echo htmlspecialchars($job['job_type']); ?>" required>
            <input type="text" name="keywords" value="<?php echo htmlspecialchars($job['keywords']); ?>">
            <select name="status" required>
                <option value="open" <?php echo $job['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="closed" <?php echo $job['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
            <button type="submit">Update Job</button>
        </form>

        <div class="back-link">
            <a href="view_jobs.php">← Back to Job Listings</a>
        </div>
    </div>
</div>

</body>
</html>
