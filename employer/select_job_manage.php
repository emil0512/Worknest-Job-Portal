<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

include("../db.php");
$emp_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch all jobs posted by this employer
$jobsResult = $conn->query("SELECT DISTINCT id, title FROM jobs WHERE emp_id = $emp_id ORDER BY created_at DESC");


?>

<!DOCTYPE html>
<html>
<head>
    <title>Select Job to Manage Applicants | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
  body {
    font-family: 'Poppins', sans-serif;
    background: #0a0a1a;
    margin: 30px;
    color: #d0d0f5;
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

h2 {
    color: #9d4edd;
    margin-bottom: 20px;
    text-align: center;
    text-shadow: 0 0 6px rgba(157, 78, 221, 0.5);
}

.job-list {
    max-width: 600px;
    margin: 0 auto;
    background: #1a1a2e;
    border-radius: 12px;
    box-shadow: 0 0 15px rgba(50, 50, 150, 0.2);
    padding: 20px;
    border: 1px solid #2d2d4d;
}

.job-item {
    padding: 15px 20px;
    border-bottom: 1px solid #2d2d4d;
    cursor: pointer;
    color: #d0d0f5;
    font-weight: 600;
    border-radius: 8px;
    transition: background 0.3s, color 0.3s;
    text-decoration: none;
    display: block;
}

.job-item:hover {
    background: rgba(157, 78, 221, 0.15);
    color: #ffffff;
}

.no-jobs {
    text-align: center;
    font-size: 18px;
    color: #888;
    padding: 40px 0;
}

.back-link {
    display: block;
    max-width: 600px;
    margin: 20px auto;
    text-align: center;
    color: #9d4edd;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.3s;
}

.back-link:hover {
    color: #ffffff;
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

<div class="emp-toggle" onclick="toggleSidebar()">☰</div>

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


<h2>Select a Job to Manage Applicants</h2>

<?php if ($jobsResult && $jobsResult->num_rows > 0): ?>
    <div class="job-list">
        <?php while ($job = $jobsResult->fetch_assoc()): ?>
            <a class="job-item" href="manage_applicants.php?job_id=<?php echo $job['id']; ?>">
                <?php echo htmlspecialchars($job['title']); ?>
            </a>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <p class="no-jobs">You have not posted any jobs yet.</p>
<?php endif; ?>

<a class="back-link" href="dashboard.php">← Back to Dashboard</a>
<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}
</script>

</body>
</html>
