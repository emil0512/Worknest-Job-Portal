<?php
session_start();
require_once "../db.php";

/**
 * Detect logged-in jobseeker ID from session
 * Adjust to your actual login system variable name.
 * Commonly: jobseeker_id, js_id, user_id
 */
$js_id = 0;
if (!empty($_SESSION['jobseeker_id'])) {
    $js_id = $_SESSION['jobseeker_id'];
} elseif (!empty($_SESSION['js_id'])) {
    $js_id = $_SESSION['js_id'];
} elseif (!empty($_SESSION['user_id'])) {
    $js_id = $_SESSION['user_id'];
}

// OPTIONAL: Fetch jobseeker's full name for confirmation
$jobseeker_name = "";
if ($js_id) {
    $stmt = $conn->prepare("SELECT fullname FROM jobseeker_profiles WHERE js_id = ?");
    $stmt->bind_param("i", $js_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $jobseeker_name = $row['fullname'];
    }
    $stmt->close();
}

// Counselor ID from query string
$counselor_id = $_GET['counselor_id'] ?? 0;
if (!$counselor_id) {
    die("Invalid counselor ID.");
}

// Fetch counselor details
$stmt = $conn->prepare("SELECT * FROM counselor_profiles WHERE counselor_id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$counselor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$counselor) {
    die("Counselor not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Book Appointment - <?= htmlspecialchars($counselor['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
<style>
 /* Body */
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
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

/* Main container */
.main {
    margin-left: 280px;
    padding: 30px;
    max-width: 700px;
}

/* Headings */
h2 {
    color: #bb86fc;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 700;
    text-shadow: 0 0 8px #6c63ff, 0 0 15px #9d4edd;
}

/* Counselor card */
.counselor-card {
    background: rgba(20,20,40,0.95);
    border-left: 8px solid #6c63ff;
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 16px;
    box-shadow: 0 0 50px rgba(108,99,255,0.5);
    color: #e0e0ff;
}
.counselor-card h3 {
    margin-top: 0;
    color: #9d4edd;
    text-shadow: 0 0 8px #6c63ff;
}
.counselor-card p {
    margin: 8px 0;
    color: #cfcfff;
}

/* Form */
form {
    background: rgba(20,20,40,0.95);
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 0 50px rgba(108,99,255,0.5);
    border-left: 8px solid #6c63ff;
}
label {
    display: block;
    margin-top: 15px;
    font-weight: 700;
    color: #bb86fc;
    text-shadow: 0 0 5px #6c63ff;
}
input[type="datetime-local"],
textarea {
    width: 100%;
    padding: 12px;
    margin-top: 6px;
    border-radius: 10px;
    border: 1px solid #9d4edd;
    background: rgba(0,0,0,0.3);
    color: #e0e0ff;
    outline: none;
    box-shadow: 0 0 10px #6c63ff inset;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
}
input:focus, textarea:focus {
    border-color: #bb86fc;
    box-shadow: 0 0 15px #9d4edd, 0 0 30px #6c63ff;
}
textarea { resize: vertical; }

/* Button */
button {
    background: linear-gradient(135deg, #6c63ff, #9d4edd);
    color: #fff;
    border: none;
    cursor: pointer;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: 700;
    margin-top: 25px;
    width: 100%;
    box-shadow: 0 0 20px #6c63ff, 0 0 40px #9d4edd;
    transition: 0.3s;
}
button:hover {
    transform: scale(1.05);
    box-shadow: 0 0 40px #6c63ff, 0 0 60px #bb86fc, 0 0 80px #9d4edd;
}

/* Warning text */
.warning-text {
    color: #ff4d4d;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 0 0 5px #ff9999;
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
/* Make datetime picker icon white */
input[type="datetime-local"]::-webkit-calendar-picker-indicator {
    filter: invert(1) brightness(2);
    cursor: pointer;
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
<div class="main">
  <h1><center>Book an Appointment</center></h1>

  <div class="counselor-card">
    <h3><?= htmlspecialchars($counselor['name']) ?></h3>
    <p><strong>Specialization:</strong> <?= htmlspecialchars($counselor['specialization']) ?></p>
    <p><strong>Experience:</strong> <?= htmlspecialchars($counselor['experience']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($counselor['email']) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($counselor['phone']) ?></p>
     </div>

  <?php if (!$js_id): ?>
    <p class="warning-text">⚠ You are not logged in. You can still view counselor details, but bookings will not be linked to your account unless you log in.</p>
  <?php endif; ?>

  <form action="payment.php" method="POST">
    <input type="hidden" name="counselor_id" value="<?= $counselor_id ?>">
    <input type="hidden" name="jobseeker_id" value="<?= $js_id ?>">

  <label for="appointment_date">Choose Date & Time</label>
<input type="datetime-local" name="appointment_date" id="appointment_date" required 
       min="<?= date('Y-m-d\TH:i') ?>">

<script>
document.addEventListener("DOMContentLoaded", function() {
    const availability = <?= json_encode($counselor['availability']); ?>;
    const input = document.getElementById("appointment_date");

    // Parse availability like "Mon–Fri (10:00 AM – 6:00 PM)"
    const match = availability.match(/([A-Za-z]+)\s*[\u2013-]\s*([A-Za-z]+)\s*\(([^–]+)[–-]([^()]+)\)/);
    if (!match) return; // Invalid format

    const dayMap = {
        "Sun": 0, "Mon": 1, "Tue": 2, "Wed": 3,
        "Thu": 4, "Fri": 5, "Sat": 6
    };

    let startDay = dayMap[match[1].substr(0,3)];
    let endDay   = dayMap[match[2].substr(0,3)];
    if (endDay < startDay) endDay += 7; // Handle wraparound like Fri–Mon

    let startTime = match[3].trim();
    let endTime   = match[4].trim();

    // Convert "10:00 AM" → "10:00"
    function to24Hour(timeStr) {
        const d = new Date("1970-01-01 " + timeStr);
        return d.toTimeString().slice(0,5);
    }

    const minTime = to24Hour(startTime);
    const maxTime = to24Hour(endTime);

  // Validate input when changed
input.addEventListener("input", function() {
    const chosen = new Date(this.value);
    const now = new Date();

    // Block past dates/times
    if (chosen < now) {
        alert("You cannot book a past date/time.");
        this.value = "";
        return;
    }

    const day = chosen.getDay(); // 0=Sun, 1=Mon...

    // Check day allowed
    let allowed = false;
    for (let d = startDay; d <= endDay; d++) {
        if (day === (d % 7)) { allowed = true; break; }
    }

    // Check time allowed
    const hhmm = this.value.split("T")[1]?.slice(0,5);
    if (!hhmm) return;

    if (!allowed || hhmm < minTime || hhmm > maxTime) {
        alert("Please choose a valid slot within " + availability);
        this.value = "";
    }
});

});
</script>


    <label for="notes">Additional Notes (Optional)</label>
    <textarea name="notes" id="notes" rows="3"></textarea>

    <button type="submit">Proceed to Payment</button>
  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const availability = <?= json_encode($counselor['availability']); ?>;
    const input = document.getElementById("appointment_date");

    // Example availability: "Mon–Fri (10:00 AM – 6:00 PM)"
    const match = availability.match(/([A-Za-z]+)\s*[\u2013-–-]\s*([A-Za-z]+)\s*\(([^–-]+)[–-]([^()]+)\)/);
    if (!match) return;

    const dayMap = {
        "Sun": 0, "Mon": 1, "Tue": 2, "Wed": 3,
        "Thu": 4, "Fri": 5, "Sat": 6
    };

    const startDay = dayMap[match[1].substr(0,3)];
    let endDay = dayMap[match[2].substr(0,3)];
    if (endDay < startDay) endDay += 7;

    // Convert to 24-hour
    function to24Hour(timeStr) {
        const d = new Date("1970-01-01 " + timeStr);
        return d.toTimeString().slice(0,5);
    }

    const startTime = to24Hour(match[3].trim());
    const endTime   = to24Hour(match[4].trim());

    // Restrict picker to future only
    input.min = new Date().toISOString().slice(0,16);

    input.addEventListener("input", function() {
        const chosen = new Date(this.value);
        const now = new Date();

        if (chosen < now) {
            alert("❌ You cannot choose a past date or time.");
            this.value = "";
            return;
        }

        const chosenDay = chosen.getDay();
        let allowed = false;

        for (let d = startDay; d <= endDay; d++) {
            if (chosenDay === (d % 7)) { allowed = true; break; }
        }

        const timePart = this.value.split("T")[1]?.slice(0,5);
        if (!allowed || !timePart || timePart < startTime || timePart > endTime) {
            alert(`⚠ Please choose a valid slot within ${availability}.`);
            this.value = "";
        }
    });

    // Tooltip info
    input.title = `Available: ${availability}`;
});
</script>


</body>
</html>
