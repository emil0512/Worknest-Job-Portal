<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];
$timezone = new DateTimeZone('Asia/Kolkata'); // set your desired timezone
$success_msg = $error_msg = "";

// Get all booked appointments for this jobseeker, latest first
$sql = "
    SELECT a.id AS appointment_id, a.date, a.time, a.notes, a.status, 
           c.name AS counselor_name, c.counselor_id
    FROM appointments a
    JOIN counselor_profiles c ON a.counselor_id = c.counselor_id
    WHERE a.jobseeker_id = ?
    ORDER BY TIMESTAMP(a.date, a.time) DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $jobseeker_id);
$stmt->execute();
$appointments = $stmt->get_result();

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = intval($_POST['appointment_id']);
    $counselor_id = intval($_POST['counselor_id']);
    $review = trim($_POST['review']);

    if (isset($_POST['rating'])) {
        $rating = intval($_POST['rating']);
        if ($rating >= 1 && $rating <= 5) {
            $insert = $conn->prepare("
                INSERT INTO ratings (rater_id, target_id, target_type, appointment_id, rating, review, created_at) 
                VALUES (?, ?, 'counselor', ?, ?, ?, NOW())
            ");
            $insert->bind_param("iiiis", $jobseeker_id, $counselor_id, $appointment_id, $rating, $review);

            if ($insert->execute()) {
                $success_msg = "✅ Review submitted successfully!";
            } else {
                $error_msg = "❌ Error: Could not save your review.";
            }
        } else {
            $error_msg = "⚠️ Please select a valid rating between 1 and 5.";
        }
    } else {
        $error_msg = "⚠️ Please select a star rating before submitting.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<title>Leave Review for Counselor</title>
<style>
/* Global */
body {
    font-family: 'Poppins', sans-serif;
    background: #0f0f1e;
    color: #e0e0ff;
    margin: 0;
    padding: 40px;
}

/* Headings */
h2 {
    text-align: center;
    color: #00ff99;
    text-shadow: 0 0 10px #00ff99;
    margin-bottom: 20px;
}
h3 {
    color: #9d4edd;
    margin-top: 0;
}

/* Appointment Card */
.appointment-card {
    background: rgba(20,20,40,0.95);
    padding: 20px;
    margin: auto;
    margin-bottom: 30px;
    max-width: 650px;
    border-radius: 12px;
    box-shadow: 0 0 20px rgba(138,43,226,0.5);
    transition: transform 0.2s ease;
}
.appointment-card:hover {
    transform: translateY(-4px);
}

/* Review Card */
.review-card {
    background: rgba(138,43,226,0.15);
    padding: 12px;
    margin-bottom: 12px;
    border-radius: 8px;
    border-left: 3px solid #9d4edd;
}
.review-card p { margin: 4px 0; }

/* Rating stars */
.rating input { display: none; }
.rating label {
    font-size: 26px;
    color: #444;
    cursor: pointer;
    transition: color 0.2s ease, text-shadow 0.2s ease;
}
.rating input:checked ~ label,
.rating label:hover,
.rating label:hover ~ label {
    color: #ffcc00;
    text-shadow: 0 0 8px #ffcc00;
}

/* Textarea & Button */
textarea {
    width: 100%;
    padding: 12px;
    margin-top: 12px;
    border-radius: 8px;
    border: 1px solid rgba(138,43,226,0.5);
    background: rgba(20,20,40,0.85);
    color: #fff;
    resize: vertical;
    font-size: 14px;
}
textarea::placeholder { color: #aaa; }

button {
    background: linear-gradient(135deg, #6c63ff, #9d4edd);
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    margin-top: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s ease, transform 0.2s ease;
}
button:hover {
    background: linear-gradient(135deg, #9d4edd, #6c63ff);
    transform: scale(1.05);
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

/* Success / Error */
.success { color: #00ff99; text-align: center; font-weight: 600; }
.error { color: #ff5555; text-align: center; font-weight: 600; }

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

<h2>📝 Leave a Review for Your Counselor</h2>
<?php if (!empty($success_msg)) echo "<p class='success'>$success_msg</p>"; ?>
<?php if (!empty($error_msg)) echo "<p class='error'>$error_msg</p>"; ?>

<?php if ($appointments->num_rows > 0): ?>
    <?php while ($row = $appointments->fetch_assoc()): ?>
        <div class="appointment-card">
            <h3><?= htmlspecialchars($row['counselor_name']) ?></h3>

            <?php
            $dt = new DateTime($row['date'] . ' ' . $row['time'], new DateTimeZone('UTC'));
            $dt->setTimezone($timezone);
            ?>
            <p><strong>📅 Date & Time:</strong> <?= $dt->format('M j, Y h:i A') ?></p>
            <p><strong>🗒 Notes:</strong> <?= htmlspecialchars($row['notes']) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($row['status']) ?></p>

            <!-- Previous reviews -->
            <?php
            $rev_sql = "SELECT rating, review, created_at FROM ratings 
                        WHERE appointment_id = ? AND target_id = ? 
                        ORDER BY created_at DESC";
            $rev_stmt = $conn->prepare($rev_sql);
            $rev_stmt->bind_param("ii", $row['appointment_id'], $row['counselor_id']);
            $rev_stmt->execute();
            $reviews = $rev_stmt->get_result();
            ?>
            <?php if ($reviews->num_rows > 0): ?>
                <h4>Previous Reviews:</h4>
                <?php while ($rev = $reviews->fetch_assoc()): 
                    $rev_dt = new DateTime($rev['created_at'], new DateTimeZone('UTC'));
                    $rev_dt->setTimezone($timezone);
                ?>
                    <div class="review-card">
                        <p>⭐ Rating: <?= intval($rev['rating']) ?>/5</p>
                        <p>💬 Review: <?= nl2br(htmlspecialchars($rev['review'])) ?></p>
                        <p style="font-size:12px;color:#555;">📅 <?= $rev_dt->format("M j, Y h:i A") ?></p>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

            <!-- Review form -->
            <form method="POST">
                <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                <input type="hidden" name="counselor_id" value="<?= $row['counselor_id'] ?>">
                <div class="rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>-<?= $row['appointment_id'] ?>" name="rating" value="<?= $i ?>">
                        <label for="star<?= $i ?>-<?= $row['appointment_id'] ?>">★</label>
                    <?php endfor; ?>
                </div>
                <textarea name="review" placeholder="Write your feedback..."></textarea>
                <button type="submit">Submit Review</button>
            </form>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="text-align:center;">No booked appointments available for review.</p>
<?php endif; ?>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
