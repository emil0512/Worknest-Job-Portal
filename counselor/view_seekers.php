<?php
session_start();
include("../db.php");

// Access control: only counselors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: ../login.php");
    exit();
}

$counselor_id = (int)$_SESSION['user_id'];
$counselor_name = $_SESSION['username'] ?? 'Your counselor';

// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];

    // Verify the appointment belongs to this counselor
    $stmt = $conn->prepare("SELECT jobseeker_id, date, time FROM appointments WHERE id = ? AND counselor_id = ?");
    $stmt->bind_param("ii", $appointment_id, $counselor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $error_msg = "Invalid appointment.";
    } else {
        $appt = $res->fetch_assoc();
        $jobseeker_id = (int)$appt['jobseeker_id'];

        // RESCHEDULE
        if ($action === 'reschedule') {
            $new_date = $_POST['new_date'] ?? null;
            $new_time = $_POST['new_time'] ?? null;

            if (!empty($new_date) && !empty($new_time)) {
                // Update appointment
                $stmt2 = $conn->prepare("UPDATE appointments SET date=?, time=?, status='Confirmed' WHERE id=?");
                $stmt2->bind_param("ssi", $new_date, $new_time, $appointment_id);
                $stmt2->execute();

                // Insert message to jobseeker
                $formatted = date("l, d M Y", strtotime($new_date)) . " at " . date("h:i A", strtotime($new_time));
                $subject = "📅 Appointment Rescheduled";
                $msg_text = "Dear Jobseeker,\n\nYour counseling session with $counselor_name has been *rescheduled*.\nNew Date & Time: **$formatted**.\n\nPlease make sure to attend at the updated schedule.\n\nBest regards,\nWorkNest Counseling Team.";

                $msg = $conn->prepare("
                    INSERT INTO messages (sender_id, receiver_id, job_id, message, subject, seen_by_jobseeker, seen_by_employer)
                    VALUES (?, ?, NULL, ?, ?, 0, 1)
                ");
                $msg->bind_param("iiss", $counselor_id, $jobseeker_id, $msg_text, $subject);
                $msg->execute();

                $success_msg = "Appointment rescheduled successfully and message sent.";
            } else {
                $error_msg = "Please provide both date and time to reschedule.";
            }

        // REFUND
        } elseif ($action === 'refund') {
            $stmt2 = $conn->prepare("UPDATE appointments SET status='Refunded' WHERE id=?");
            $stmt2->bind_param("i", $appointment_id);
            $stmt2->execute();

            // Refund latest payment
            $stmt3 = $conn->prepare("SELECT id FROM payment_transactions WHERE user_id=? AND role='jobseeker' ORDER BY created_at DESC LIMIT 1");
            $stmt3->bind_param("i", $jobseeker_id);
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            if ($res3->num_rows > 0) {
                $txn = $res3->fetch_assoc();
                $txn_id = (int)$txn['id'];
                $stmt4 = $conn->prepare("UPDATE payment_transactions SET status='refunded' WHERE id=?");
                $stmt4->bind_param("i", $txn_id);
                $stmt4->execute();
            }

            // Insert message to jobseeker
            $subject = "💸 Appointment Rejected & Refunded";
            $msg_text = "Dear Jobseeker,\n\nYour appointment with $counselor_name has been *rejected*.\nThe payment has been refunded successfully.\n\nYou may book another session if needed.\n\nBest regards,\nWorkNest Counseling Team.";

            $msg = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, job_id, message, subject, seen_by_jobseeker, seen_by_employer)
                VALUES (?, ?, NULL, ?, ?, 0, 1)
            ");
            $msg->bind_param("iiss", $counselor_id, $jobseeker_id, $msg_text, $subject);
            $msg->execute();

            $success_msg = "Appointment refunded successfully and message sent.";
        }
    }
}

// MARK AS COMPLETED
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $appointment_id = (int)$_GET['complete'];

    // Fetch jobseeker for message
    $stmt = $conn->prepare("SELECT jobseeker_id FROM appointments WHERE id=? AND counselor_id=? AND status='Confirmed'");
    $stmt->bind_param("ii", $appointment_id, $counselor_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $appt = $res->fetch_assoc();
        $jobseeker_id = (int)$appt['jobseeker_id'];

        $stmt2 = $conn->prepare("UPDATE appointments SET status='Completed' WHERE id=? AND counselor_id=?");
        $stmt2->bind_param("ii", $appointment_id, $counselor_id);
        $stmt2->execute();

        // Insert message to jobseeker
        $subject = "✅ Appointment Completed";
        $msg_text = "Dear Jobseeker,\n\nYour counseling session with $counselor_name has been *successfully completed*.\nWe hope it was helpful!\n\nThank you for using WorkNest Counseling Services.";

        $msg = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, job_id, message, subject, seen_by_jobseeker, seen_by_employer)
            VALUES (?, ?, NULL, ?, ?, 0, 1)
        ");
        $msg->bind_param("iiss", $counselor_id, $jobseeker_id, $msg_text, $subject);
        $msg->execute();

        $success_msg = "Appointment marked as completed and message sent.";
    }
}


// Mark completed
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $appointment_id = (int)$_GET['complete'];
    $stmt = $conn->prepare("UPDATE appointments SET status='Completed' WHERE id=? AND counselor_id=? AND status='Confirmed'");
    $stmt->bind_param("ii", $appointment_id, $counselor_id);
    $stmt->execute();
}

// --- Fetch assigned jobseekers ---
$sql = "SELECT DISTINCT u.user_id, u.username, u.email, p.fullname, p.resume_path
        FROM users u
        JOIN counselor_assignments ca ON u.user_id = ca.jobseeker_id
        LEFT JOIN jobseeker_profiles p ON u.user_id = p.js_id
        WHERE ca.counselor_id = ?
        ORDER BY COALESCE(p.fullname, u.username) ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$jobseekers = $stmt->get_result();
$stmt->close();

// Function to fetch appointments
function fetchAppointments(mysqli $conn, int $jobseeker_id, int $counselor_id) {
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE jobseeker_id=? AND counselor_id=? ORDER BY date DESC, time DESC");
    $stmt->bind_param("ii", $jobseeker_id, $counselor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    return $res;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assigned Jobseekers | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
 body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #0a0f1f, #1a103d);
    color: #e0e0ff;
    padding: 40px;
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

/* Heading */
h1 {
    text-align: center;
    color: #8ab4ff;
    margin-bottom: 40px;
    text-shadow: 0 0 12px #6c63ff;
}

/* Seeker Cards */
.seeker-list { max-width: 900px; margin: auto; }
.seeker-card {
    background: rgba(25, 20, 50, 0.85);
    border-left: 4px solid #6c63ff;
    padding: 20px;
    margin-bottom: 35px;
    border-radius: 12px;
    box-shadow: 0 0 15px rgba(108, 99, 255, 0.3);
    transition: transform 0.2s;
}
.seeker-card:hover {
    transform: translateY(-3px);
}
.seeker-card h3 {
    margin: 0 0 10px;
    color: #e0e0ff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Resume Links */
.resume-link {
    margin-top: 8px;
    font-weight: 600;
    text-decoration: none;
    color: #00e5ff;
}
.resume-link:hover {
    text-decoration: underline;
    color: #6c63ff;
}

/* Appointments */
.appointments {
    margin-top: 20px;
    border-top: 1px solid rgba(108, 99, 255, 0.3);
    padding-top: 15px;
}
.appointment-card {
    border: 1px solid rgba(108, 99, 255, 0.4);
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    background: rgba(15, 10, 30, 0.9);
    box-shadow: 0 0 10px rgba(108, 99, 255, 0.2);
}
.appointment-header {
    font-weight: 600;
    color: #8ab4ff;
    margin-bottom: 10px;
    display:flex;
    gap:10px;
    align-items:center;
}

/* Status Tags */
.status {
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 12px;
    display: inline-block;
    box-shadow: 0 0 6px rgba(0,0,0,0.4);
}
.status.Confirmed { background: #4caf50; color: #fff; }
.status.Completed { background: #9e9e9e; color: #000; }
.status.Refunded  { background: #f44336; color: #fff; }

/* Buttons */
.action-buttons button,
.reschedule-form button,
button[type="submit"] {
    background: #6c63ff;
    color: #fff;
    border: none;
    padding: 7px 14px;
    margin-right: 10px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    box-shadow: 0 0 8px #6c63ff;
}
.action-buttons button:hover,
.reschedule-form button:hover,
button[type="submit"]:hover {
    background: #00e5ff;
    box-shadow: 0 0 12px #00e5ff;
}

/* Reschedule Form */
.reschedule-form {
    margin-top: 10px;
    background: rgba(108, 99, 255, 0.15);
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(108, 99, 255, 0.4);
}
.reschedule-form label {
    display: block;
    margin: 6px 0 3px;
    font-weight: 600;
    color: #d1c4ff;
}
.reschedule-form input[type="date"],
.reschedule-form input[type="time"] {
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #6c63ff;
    background: #0a0f1f;
    color: #e0e0ff;
}

/* Messages */
.message-btn {
    font-size: 0.8em;
    background: #6c63ff;
    color: #fff;
    padding: 5px 12px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s;
}
.message-btn:hover {
    background: #00e5ff;
    box-shadow: 0 0 10px #00e5ff;
}

/* Alerts */
.error-msg { color: #ff4d6d; font-weight: 600; margin: 0 0 15px; text-align:center; }
.success-msg { color: #4dff91; font-weight: 600; margin: 0 0 15px; text-align:center; }
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

<h1>Your Assigned Jobseekers</h1>

<div class="seeker-list">
    <?php if (!empty($error_msg)) echo "<p class='error-msg'>" . htmlspecialchars($error_msg) . "</p>"; ?>
    <?php if (!empty($success_msg)) echo "<p class='success-msg'>" . htmlspecialchars($success_msg) . "</p>"; ?>

    <?php if ($jobseekers->num_rows > 0): ?>
        <?php while ($js = $jobseekers->fetch_assoc()): ?>
            <div class="seeker-card">
                <h3>
                    <?= htmlspecialchars($js['fullname'] ?: $js['username']) ?>
                    <a class="message-btn" href="messages.php?js_id=<?= (int)$js['user_id'] ?>">💬 Message</a>
                </h3>
                <div class="seeker-info">
                    <p><strong>Email:</strong> <?= htmlspecialchars($js['email']) ?></p>
                    <?php if (!empty($js['resume_path'])): ?>
                        <a class="resume-link" href="../uploads/resumes/<?= htmlspecialchars(basename($js['resume_path'])) ?>" target="_blank">📄 View Resume</a>
                    <?php else: ?>
                        <p><em>No resume uploaded.</em></p>
                    <?php endif; ?>
                </div>

                <?php
                $appointments = fetchAppointments($conn, (int)$js['user_id'], $counselor_id);
                if ($appointments->num_rows > 0):
                ?>
                    <div class="appointments">
                        <h4>Appointments</h4>
                        <?php while ($appt = $appointments->fetch_assoc()): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <span><?= htmlspecialchars($appt['date']) ?> at <?= htmlspecialchars($appt['time']) ?></span>
                                    <span class="status <?= htmlspecialchars($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span>
                                </div>
                                <div class="appointment-info">
                                    <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($appt['notes'] ?: '-')) ?></p>
                                </div>

                                <?php if ($appt['status'] === 'Confirmed'): ?>
                                    <!-- Reschedule Form -->
                                    <form method="POST" style="margin-top:10px;">
                                        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">
                                        <div class="action-buttons">
                                            <a href="?complete=<?= (int)$appt['id'] ?>" onclick="return confirm('Mark this session as completed?')">
                                                <button type="button">✔ Mark Completed</button>
                                            </a>
                                            <button type="button" onclick="toggleReschedule(<?= (int)$appt['id'] ?>)">❌ Reject & Reschedule</button>
                                        </div>

                                        <div id="reschedule-form-<?= (int)$appt['id'] ?>" class="reschedule-form" style="display:none;">
                                          <label for="new_date_<?= (int)$appt['id'] ?>">New Date:</label>
<input type="date" 
       id="new_date_<?= (int)$appt['id'] ?>" 
       name="new_date" 
       min="<?= date('Y-m-d') ?>" 
       required>

<label for="new_time_<?= (int)$appt['id'] ?>">New Time:</label>
<input type="time" 
       id="new_time_<?= (int)$appt['id'] ?>" 
       name="new_time" 
       required>

<button type="submit" name="action" value="reschedule">Submit Reschedule</button>

                                        </div>
                                    </form>

                                    <!-- Refund Form -->
                                    <form method="POST" style="margin-top:10px;">
                                        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">
                                        <input type="hidden" name="action" value="refund">
                                        <button type="submit"style="background: #795548; color: white; border: none; padding: 7px 14px; 
               margin-right: 10px; border-radius: 6px; cursor: pointer; font-weight: 600;" onclick="return confirm('Reject and refund this appointment?')">💸 Reject & Refund</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p><em>No appointments found.</em></p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center;">🫤 No jobseekers assigned to you yet.</p>
    <?php endif; ?>
</div>

<script>
function toggleReschedule(id) {
    const box = document.getElementById('reschedule-form-' + id);
    const showing = box.style.display === 'block';
    box.style.display = showing ? 'none' : 'block';
}
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
document.querySelectorAll('.reschedule-form').forEach(form => {
    const dateInput = form.querySelector('input[type="date"]');
    const timeInput = form.querySelector('input[type="time"]');

    function updateTimeMin() {
        const today = new Date();
        const selectedDate = new Date(dateInput.value);
        if (!dateInput.value) return;

        if (selectedDate.toDateString() === today.toDateString()) {
            // Set min time to current time
            const h = String(today.getHours()).padStart(2, '0');
            const m = String(today.getMinutes()).padStart(2, '0');
            timeInput.min = `${h}:${m}`;
        } else {
            timeInput.min = "00:00"; // reset for future dates
        }
    }

    // Update min time whenever date changes
    dateInput.addEventListener('change', updateTimeMin);

    form.addEventListener('submit', function(e) {
        if (!dateInput.value || !timeInput.value) return;

        const [year, month, day] = dateInput.value.split("-").map(Number);
        const [hours, minutes] = timeInput.value.split(":").map(Number);
        const selectedDate = new Date(year, month - 1, day, hours, minutes);
        const now = new Date();

        if (selectedDate <= now) {
            e.preventDefault();
            alert("You cannot select a past date or time.");
            return false;
        }
    });

    // Initialize min time on page load
    updateTimeMin();
});
</script>
</body>
</html>
