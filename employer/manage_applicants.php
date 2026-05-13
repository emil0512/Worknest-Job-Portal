<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!isset($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
    die("Invalid Job ID.");
}

$job_id = intval($_GET['job_id']);

// Verify that this job belongs to this employer
$stmt = $conn->prepare("SELECT title FROM jobs WHERE id = ? AND emp_id = ?");
$stmt->bind_param("ii", $job_id, $emp_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("Unauthorized or invalid job.");
}
$stmt->bind_result($job_title);
$stmt->fetch();
$stmt->close();

// Handle shortlist / reject actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];

    if ($action === 'shortlist') {
    $upd = $conn->prepare("UPDATE applications 
                           SET status='shortlisted', application_stage='Shortlisted', notified = 0 
                           WHERE job_id = ? AND user_id = ?");
    $upd->bind_param("ii", $job_id, $user_id);
    $upd->execute();
    $upd->close();


        $ins = $conn->prepare("INSERT IGNORE INTO shortlisted_applicants (job_id, user_id) VALUES (?, ?)");
        $ins->bind_param("ii", $job_id, $user_id);
        $ins->execute();
        $ins->close();

        // Notification (reuse your notification logic)
        // notify_jobseeker($user_id, $job_id, 'shortlisted');

        $msg = "Candidate shortlisted successfully.";

    } elseif ($action === 'remove_shortlist') {
        $upd = $conn->prepare("UPDATE applications 
                               SET status='pending', application_stage='Under Review' 
                               WHERE job_id = ? AND user_id = ?");
        $upd->bind_param("ii", $job_id, $user_id);
        $upd->execute();
        $upd->close();

        $del = $conn->prepare("DELETE FROM shortlisted_applicants WHERE job_id = ? AND user_id = ?");
        $del->bind_param("ii", $job_id, $user_id);
        $del->execute();
        $del->close();

        $msg = "Candidate removed from shortlist.";

 } elseif ($action === 'reject') {
    // Mark as rejected
    $upd = $conn->prepare("UPDATE applications 
                           SET status='rejected', application_stage='Rejected', notified = 0 
                           WHERE job_id = ? AND user_id = ?");
    $upd->bind_param("ii", $job_id, $user_id);
    $upd->execute();
    $upd->close();


    // Remove if previously shortlisted
    $del = $conn->prepare("DELETE FROM shortlisted_applicants WHERE job_id = ? AND user_id = ?");
    $del->bind_param("ii", $job_id, $user_id);
    $del->execute();
    $del->close();
    $msg = "Candidate rejected successfully.";
}

}

$sql = "
    SELECT 
        a.user_id,
        u.username,
        u.email,
        MAX(a.match_score) AS match_score,
        MAX(a.applied_on) AS applied_on,
        a.status,
        a.application_stage,
        COALESCE(MAX(sa.shortlisted_by_ats), 0) AS shortlisted_by_ats
    FROM applications a
    INNER JOIN users u ON a.user_id = u.user_id
    LEFT JOIN shortlisted_applicants sa ON sa.user_id = a.user_id AND sa.job_id = a.job_id
    WHERE a.job_id = ?
    GROUP BY a.user_id
    ORDER BY match_score DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Applicants | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
  body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: #0a0f1f;
    color: #e0e0ff;
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
    background: #120029;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 0 15px #5200ff88;
  }
  .emp-topbar h2 {
    color: #00d4ff;
    margin: 0;
    text-shadow: 0 0 8px #00d4ff;
  }
  .emp-logout-btn {
    background: #7b2ff7;
    color: white;
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 0 12px #7b2ff7;
  }
  .emp-logout-btn:hover {
    background: #5200ff;
    box-shadow: 0 0 18px #00d4ff;
  }

  /* Main content */
  .emp-main {
    margin-left: 280px;
    padding: 30px;
    max-width: 900px;
  }
  h2 {
    text-align: center;
    color: #9b5cff;
    margin-bottom: 40px;
    text-shadow: 0 0 10px #9b5cff, 0 0 20px #00d4ff;
  }

  /* Applicant card */
  .applicant-card {
    background: #1a0033;
    border-left: 6px solid #7b2ff7;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 12px;
    box-shadow: 0 0 12px #5200ff99;
    transition: transform 0.2s;
  }
  .applicant-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0 20px #00d4ffcc;
  }
  .applicant-card h3 {
    margin: 0;
    color: #00d4ff;
    text-shadow: 0 0 6px #00d4ff;
  }
  .applicant-card p {
    margin: 6px 0;
    color: #ccc;
  }

  /* Buttons */
  .btn {
    display: inline-block;
    margin-top: 10px;
    background: #7b2ff7;
    color: white;
    padding: 8px 14px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
    border: none;
    transition: all 0.3s;
    box-shadow: 0 0 10px #7b2ff7;
  }
  .btn:hover {
    background: #5200ff;
    box-shadow: 0 0 16px #00d4ff;
  }
  .shortlist-btn {
    background: #00d4ff;
    box-shadow: 0 0 10px #00d4ff;
    margin-right: 10px;
  }
  .shortlist-btn:hover {
    background: #009fcc;
    box-shadow: 0 0 16px #7b2ff7;
  }
  .remove-shortlist-btn {
    background: #f44336;
    box-shadow: 0 0 10px #f44336;
  }
  .remove-shortlist-btn:hover {
    background: #c62828;
    box-shadow: 0 0 16px #7b2ff7;
  }
  .message-btn {
    background: #9b5cff;
    box-shadow: 0 0 10px #9b5cff;
    margin-left: 10px;
  }
  .message-btn:hover {
    background: #7b2ff7;
    box-shadow: 0 0 16px #00d4ff;
  }

  /* Status labels */
  .status-label {
    font-weight: bold;
    color: #00d4ff;
    margin-left: 10px;
    text-shadow: 0 0 6px #00d4ff;
  }
  .not-shortlisted {
    color: #777;
    font-style: italic;
    margin-left: 10px;
  }

  /* Success message */
  .msg-success {
    background: rgba(0, 255, 128, 0.15);
    border: 1px solid #00ff99;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
    color: #00ff99;
    text-shadow: 0 0 8px #00ff99;
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

<div class="emp-main">

    <!-- Back Button -->
    <a href="select_job_manage.php" class="btn" 
       style="background:#9b5cff; box-shadow:0 0 10px #9b5cff; margin-bottom:20px; display:inline-block;">
       ⬅ Back to Job Selection
    </a>

    <h2>Applicants for Job: <?php echo htmlspecialchars($job_title); ?></h2>

    <?php if (isset($msg)): ?>
        <div class="msg-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>


    <?php if ($result->num_rows > 0): ?>
        <?php while ($applicant = $result->fetch_assoc()): ?>
            <div class="applicant-card">
             <h3>
    <?php echo htmlspecialchars($applicant['username']); ?>

    <?php if (!empty($applicant['shortlisted_by_ats']) && $applicant['shortlisted_by_ats'] == 1): ?>
        <span style="color:#00ffcc; font-size:14px; font-weight:bold; margin-left:8px;">🤖 ATS</span>
    <?php elseif (strtolower($applicant['status']) === 'shortlisted' && (empty($applicant['shortlisted_by_ats']) || $applicant['shortlisted_by_ats'] == 0)): ?>
        <span style="color:#bb86fc; font-size:14px; font-weight:bold; margin-left:8px;">⭐ Employer</span>
    <?php endif; ?>
</h3>


                <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                <p><strong>Match Score:</strong> <?php echo intval($applicant['match_score']); ?>%</p>
                <p><strong>Applied On:</strong> <?php echo date("M d, Y H:i", strtotime($applicant['applied_on'])); ?></p>
                <p>
                    <strong>Status:</strong>
                    <?php if (strtolower($applicant['status']) === 'shortlisted' || strtolower($applicant['application_stage']) === 'shortlisted'): ?>
                        <span class="status-label">Shortlisted</span>
                    <?php else: ?>
                        <span class="not-shortlisted">Not shortlisted</span>
                    <?php endif; ?>
                </p>

                <!-- Action buttons -->
<form method="POST" style="display:inline-block;">
    <input type="hidden" name="user_id" value="<?php echo $applicant['user_id']; ?>">

    <?php if (strtolower($applicant['status']) === 'shortlisted' || strtolower($applicant['application_stage']) === 'shortlisted'): ?>
        <button type="submit" name="action" value="remove_shortlist" class="btn remove-shortlist-btn">Remove from Shortlist</button>
    <?php elseif (strtolower($applicant['status']) === 'rejected' || strtolower($applicant['application_stage']) === 'rejected'): ?>
        <span class="status-label" style="color:#f44336;">❌ Rejected</span>
    <?php else: ?>
        <button type="submit" name="action" value="shortlist" class="btn shortlist-btn">Shortlist</button>
        <button type="submit" name="action" value="reject" class="btn" style="background:#f44336; box-shadow:0 0 10px #f44336;">Reject</button>
    <?php endif; ?>
</form>

<a href="view_applicant.php?job_id=<?php echo $job_id; ?>&user_id=<?php echo $applicant['user_id']; ?>" class="btn">View Profile</a>
<a href="send_message.php?job_id=<?php echo $job_id; ?>&user_id=<?php echo $applicant['user_id']; ?>" class="btn message-btn">Message</a>

            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center; color:#777;">No applicants found for this job.</p>
    <?php endif; ?>
</div>

<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}
</script>

</body>
</html>