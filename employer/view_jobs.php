<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}
include("../db.php");

$emp_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch jobs
$jobs = $conn->query("SELECT * FROM jobs WHERE emp_id = $emp_id GROUP BY id ORDER BY created_at DESC");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Your Job Listings | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
   <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #0a0f1c; /* Cyberpunk dark */
            color: #c0c0ff;
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
            margin-left: 280px;
            background: linear-gradient(90deg, #111633, #1a1f3d);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #5b4bff;
        }

        .emp-topbar h2 {
            margin: 0;
            color: #8ecbff;
            text-shadow: 0 0 6px rgba(142, 203, 255, 0.6);
        }

        .emp-logout-btn {
            background: #7b5bff;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }

        .emp-logout-btn:hover {
            opacity: 0.85;
        }

        /* Main content */
        .emp-main {
            margin-left: 300px;
            padding: 40px;
        }

        h2.page-title {
            text-align: center;
            color: #8ecbff;
            margin-bottom: 30px;
            text-shadow: 0 0 6px rgba(142, 203, 255, 0.6);
        }

        .msg {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .msg.success { color: #79ffb4; }
        .msg.closed { color: #ff9ef0; }

        /* Job cards */
        .job-card {
            background: #15182b;
            border-left: 6px solid #7b5bff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 0 12px rgba(123, 91, 255, 0.2);
        }

        .job-card h3 {
            margin: 0;
            color: #8ecbff;
        }

        .job-card p {
            margin: 8px 0;
            color: #b0b0ff;
        }

        .job-actions {
            margin-top: 15px;
        }

        .action-btn {
            display: inline-block;
            padding: 8px 14px;
            margin-right: 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.2s;
            color: #fff;
        }

        .action-btn.edit { background: #7b5bff; }
        .action-btn.edit:hover { opacity: 0.85; }

        .action-btn.close-btn { background: #ff4b87; }
        .action-btn.close-btn:hover { opacity: 0.85; }

        .status-open { color: #79ffb4; font-weight: bold; }
        .status-closed { color: #ff9ef0; font-weight: bold; }
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


<div class="emp-main">
    <h1>Your Job Listings</h1>

    <!-- ✅ Success / Info Messages -->
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <p class="msg success">✅ Job deleted successfully!</p>
    <?php endif; ?>

    <?php if (isset($_GET['closed']) && $_GET['closed'] == 1): ?>
        <p class="msg closed">🛑 Job post closed successfully.</p>
    <?php endif; ?>

    <!-- ✅ Jobs List -->
    <?php if ($jobs->num_rows > 0): ?>
        <?php while ($job = $jobs->fetch_assoc()): ?>
            <div class="job-card">
                <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                <p><strong>🕒 Type:</strong> <?php echo htmlspecialchars($job['job_type']); ?></p>
                <p><strong>📅 Posted on:</strong> <?php echo date("M d, Y", strtotime($job['created_at'])); ?></p>
                <p><strong>🔑 Keywords:</strong> <?php echo htmlspecialchars($job['keywords']); ?></p>
                <p><strong>Status:</strong>
                    <?php if ($job['status'] === 'open'): ?>
                        🟢 Open
                    <?php else: ?>
                        🔴 Closed
                    <?php endif; ?>
                </p>
                <div class="job-actions">
                    <a href="edit_job.php?job_id=<?php echo $job['id']; ?>" class="action-btn">✏️ Edit</a>
                    <a href="delete_job.php?job_id=<?php echo $job['id']; ?>" class="action-btn close-btn" onclick="return confirm('Are you sure you want to delete this job?');">🗑️ Delete</a>
                    <?php if ($job['status'] === 'open'): ?>
                        <a href="close_jobs.php?job_id=<?php echo $job['id']; ?>" class="action-btn close-btn" onclick="return confirm('Are you sure you want to close this job post?');">🛑 Close Job</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: #777;">You haven’t posted any jobs yet. <a href="post_job.php">Post one now</a>!</p>
    <?php endif; ?>
</div>

<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}
</script>

</body>
</html>

