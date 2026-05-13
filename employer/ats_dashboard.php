<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
// Handle interview scheduling or rescheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_interview'])) {
    $user_id = intval($_POST['user_id']);
    $job_id = intval($_POST['job_id']);
    $interview_datetime = $_POST['interview_datetime'];

    // Server-side validation
    $selected_dt = strtotime($interview_datetime);
    $now = time();

    if ($selected_dt === false || $selected_dt < $now) {
        echo "<script>alert('Invalid interview date/time! It cannot be in the past.');</script>";
    } else {
        // Check if an interview was already scheduled before
        $check = $conn->prepare("SELECT interview_datetime FROM applications WHERE user_id=? AND job_id=? AND application_stage='Interview Scheduled'");
        $check->bind_param("ii", $user_id, $job_id);
        $check->execute();
        $prev = $check->get_result()->fetch_assoc();
        $check->close();

        // Update the application stage and interview date/time
        $stmt = $conn->prepare("
            UPDATE applications 
            SET application_stage='Interview Scheduled', interview_datetime=? 
            WHERE user_id=? AND job_id=?");
        $stmt->bind_param("sii", $interview_datetime, $user_id, $job_id);
        $stmt->execute();
        $stmt->close();

        // Get job title for message content
        $jobStmt = $conn->prepare("SELECT title FROM jobs WHERE id=?");
        $jobStmt->bind_param("i", $job_id);
        $jobStmt->execute();
        $job = $jobStmt->get_result()->fetch_assoc();
        $job_title = $job['title'] ?? 'your applied position';
        $jobStmt->close();

        // Format interview date/time for message
        $formatted_dt = date("l, d M Y \\a\\t h:i A", strtotime($interview_datetime));

        // Decide message type (first schedule or reschedule)
        if (!empty($prev['interview_datetime'])) {
            // 🔄 Rescheduled interview
            $subject = "🔄 Interview Rescheduled - $job_title";
            $msg_text = "Dear candidate,\n\nYour interview for the position of '$job_title' has been *rescheduled*.\nThe new interview date and time is: **$formatted_dt**.\n\nPlease be available accordingly.\n\nBest regards,\nWorkNest Recruitment Team";
        } else {
            // 🎯 First-time scheduled interview
            $subject = "📅 Interview Scheduled - $job_title";
            $msg_text = "Dear candidate,\n\nCongratulations! You have been shortlisted for the position of '$job_title'.\nYour interview has been scheduled on **$formatted_dt**.\n\nPlease make sure to be available at that time.\n\nBest wishes,\nWorkNest Recruitment Team";
        }

        // Insert message for the jobseeker
        $msg = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, job_id, message, subject, seen_by_jobseeker, seen_by_employer)
            VALUES (?, ?, ?, ?, ?, 0, 1)
        ");
        $msg->bind_param("iiiss", $emp_id, $user_id, $job_id, $msg_text, $subject);
        $msg->execute();
        $msg->close();

        echo "<script>alert('Interview schedule updated and message sent to jobseeker.');</script>";
    }
}

// Get all jobs posted by this employer
$jobs = $conn->query("SELECT distinct * FROM jobs WHERE emp_id = $emp_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>ATS & Shortlisting | WorkNest</title>
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

.emp-logout-btn {
    background: linear-gradient(135deg,#6c63ff,#9d4edd);
    color: #fff;
    padding: 8px 20px;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 0 5px #6c63ff, 0 0 10px #9d4edd;
    transition: 0.3s;
}
.emp-logout-btn:hover {
    background: linear-gradient(135deg,#9d4edd,#6c63ff);
    transform: scale(1.05);
}

/* Main content */
.emp-main {
    margin-left: 260px;
    padding: 30px;
    max-width: 900px;
}

/* Page heading */
h1 {
    text-align: center;
    color: #bb86fc;
    text-shadow: 0 0 15px #6c63ff,0 0 30px #9d4edd;
    margin-bottom: 40px;
}

/* Job Section Card */
.job-section {
    background: rgba(20,20,40,0.95);
    border-left: 6px solid #6c63ff;
    padding: 20px;
    margin-bottom: 40px;
    border-radius: 12px;
    box-shadow: 0 0 35px rgba(108,99,255,0.5);
    transition: transform 0.3s, box-shadow 0.3s;
}
.job-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 0 70px #6c63ff,0 0 100px #9d4edd;
}
.job-section h3 {
    margin: 0 0 15px;
    color: #bb86fc;
}

/* Applicant Card */
.applicant-card {
    background: rgba(30,30,50,0.85);
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    box-shadow: 0 0 15px rgba(108,99,255,0.3);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.applicant-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0 25px #6c63ff,0 0 40px #9d4edd;
}
.applicant-card h4 {
    margin: 0 0 8px;
    color: #cfcfff;
}
.applicant-card p {
    margin: 4px 0;
    color: #aaa;
}

/* Details button */
.details-btn {
    display: inline-block;
    margin-top: 8px;
    background: linear-gradient(90deg,#00ffff,#0077ff);
    color: #fff;
    padding: 6px 12px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
    font-size: 14px;
    box-shadow: 0 0 5px #00ffff,0 0 10px #0077ff;
    transition: 0.3s;
}
.details-btn:hover {
    background: linear-gradient(90deg,#0077ff,#00ffff);
    box-shadow: 0 0 10px #00ffff,0 0 20px #0077ff;
}

/* No applicants message */
.no-applicants {
    color: #777;
    font-style: italic;
    margin-top: 10px;
}

/* Responsive */
@media(max-width:900px){

    .emp-main { margin-left: 0; }
    .emp-sidebar { left:-260px; }
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
    <h1>📈 Shortlisted Applicants by Job</h1>

    <?php if ($jobs->num_rows > 0): ?>
        <?php while ($job = $jobs->fetch_assoc()): ?>
            <?php
                $jobId = $job['id'];
                $jobTitle = htmlspecialchars($job['title']);
$sql = "
    SELECT 
        sa.user_id,
        u.username,
        u.email,
        a.match_score,
        a.applied_on,
        a.status,
        a.application_stage,
        sa.shortlisted_by_ats
    FROM shortlisted_applicants sa
    INNER JOIN applications a 
        ON a.user_id = sa.user_id AND a.job_id = sa.job_id
    INNER JOIN users u 
        ON u.user_id = sa.user_id
    WHERE sa.job_id = ?
    ORDER BY a.match_score DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $jobId);
$stmt->execute();
$applicants = $stmt->get_result();

            ?>
            <div class="job-section">
                <h3><?php echo $jobTitle; ?></h3>

                <?php if ($applicants->num_rows > 0): ?>
                    <?php while ($app = $applicants->fetch_assoc()): ?>
                        <div class="applicant-card">
    <h4>
        <?php echo htmlspecialchars($app['username']); ?>
        <?php if (!empty($app['shortlisted_by_ats']) && $app['shortlisted_by_ats'] == 1): ?>
    <span style="color:#00ffcc; font-size:14px; font-weight:bold;">🤖 ATS</span>
<?php else: ?>
    <span style="color:#ffcc00; font-size:14px; font-weight:bold;">⭐ Employer</span>
<?php endif; ?>

    </h4>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></p>
    <p><strong>Match Score:</strong> <?php echo intval($app['match_score']); ?>%</p>
    <p><strong>Applied On:</strong> <?php echo date("M d, Y H:i", strtotime($app['applied_on'])); ?></p>
    <a class="details-btn" href="view_applicant.php?job_id=<?php echo $jobId; ?>&user_id=<?php echo $app['user_id']; ?>">View Details</a>

<!-- Interview Scheduling / Rescheduling Logic -->
<?php if ($app['application_stage'] === 'Interview Scheduled'): ?>
    <p style="margin-top:10px; color:#00ffcc; font-weight:bold;">
        📅 Interview Scheduled 
        <?php 
        // Show interview date if available
        $query_dt = $conn->prepare("SELECT interview_datetime FROM applications WHERE user_id=? AND job_id=?");
        $query_dt->bind_param("ii", $app['user_id'], $jobId);
        $query_dt->execute();
        $result_dt = $query_dt->get_result()->fetch_assoc();
        $interview_dt = $result_dt['interview_datetime'] ?? null;
        $query_dt->close();

        if ($interview_dt) {
            echo "<br><small style='color:#ccc;'>(" . date("M d, Y h:i A", strtotime($interview_dt)) . ")</small>";
        }
        ?>
    </p>

    <!-- Reschedule Button -->
    <button class="details-btn" style="background:linear-gradient(90deg,#ffaa00,#ff6600);" 
            onclick="openInterviewModal(<?php echo $app['user_id']; ?>, <?php echo $jobId; ?>)">
        🔄 Reschedule Interview
    </button>

<?php else: ?>
    <!-- Schedule New Interview Button -->
    <button class="details-btn" style="background:linear-gradient(90deg,#00ff88,#00cc66);" 
            onclick="openInterviewModal(<?php echo $app['user_id']; ?>, <?php echo $jobId; ?>)">
        🎯 Select for Interview
    </button>
<?php endif; ?>

</div>


                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-applicants">No shortlisted applicants for this job.</p>
                <?php endif; ?>
                <?php $stmt->close(); ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center; color:#777;">You have not posted any jobs yet.</p>
    <?php endif; ?>
</div>

<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}
</script>
<!-- Interview Modal -->
<div id="interviewModal" style="display:none; 
    position:fixed; top:0; left:0; width:100%; height:100%; 
    background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#1a0033; padding:25px; border-radius:12px; box-shadow:0 0 20px #7b2ff7; width:320px; text-align:center;">
        <h3 style="color:#bb86fc;">📅 Schedule Interview</h3>
       <form method="POST" id="interviewForm" onsubmit="return validateInterviewDateTime()">
    <input type="hidden" name="user_id" id="modal_user_id">
    <input type="hidden" name="job_id" id="modal_job_id">
    <label style="color:#ccc;">Select Date & Time:</label><br>
    <input type="datetime-local" id="interview_datetime" name="interview_datetime" required
        style="margin-top:10px; padding:8px; border:none; border-radius:6px; width:90%;"><br><br>
    <button type="submit" name="schedule_interview" 
        style="background:#00ffcc; color:#000; padding:8px 16px; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">
        Confirm
    </button>
    <button type="button" onclick="closeInterviewModal()" 
        style="margin-left:10px; background:#f44336; color:white; padding:8px 16px; border:none; border-radius:6px; cursor:pointer;">
        Cancel
    </button>
</form>

    </div>
</div>

<script>
function openInterviewModal(user_id, job_id) {
    document.getElementById('interviewModal').style.display = 'flex';
    document.getElementById('modal_user_id').value = user_id;
    document.getElementById('modal_job_id').value = job_id;
}
function closeInterviewModal() {
    document.getElementById('interviewModal').style.display = 'none';
}
// Prevent selecting past date/time
function validateInterviewDateTime() {
    const datetimeInput = document.getElementById('interview_datetime');
    const selected = new Date(datetimeInput.value);
    const now = new Date();

    if (!datetimeInput.value) {
        alert("Please select a date and time.");
        return false;
    }

    // Check if selected datetime is in the past
    if (selected < now) {
        alert("Interview date and time cannot be in the past!");
        return false;
    }

    return true;
}

// Set minimum datetime value dynamically (HTML5 constraint)
document.addEventListener("DOMContentLoaded", () => {
    const datetimeInput = document.getElementById('interview_datetime');
    if (datetimeInput) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        datetimeInput.min = now.toISOString().slice(0, 16);
    }
});

</script>

</body>
</html>
