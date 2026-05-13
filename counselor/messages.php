<?php
session_start();
include("../db.php");

// ✅ Check counselor login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: ../login.php");
    exit();
}

$counselor_id = $_SESSION['user_id'];

// Get unique jobseekers assigned to this counselor
$jobseekers_stmt = $conn->prepare("
    SELECT DISTINCT u.user_id AS js_id, jp.fullname
    FROM counselor_assignments ca
    JOIN users u ON ca.jobseeker_id = u.user_id
    JOIN jobseeker_profiles jp ON u.user_id = jp.js_id
    WHERE ca.counselor_id = ?
");
$jobseekers_stmt->bind_param("i", $counselor_id);
$jobseekers_stmt->execute();
$jobseekers_result = $jobseekers_stmt->get_result();

// Get selected tab and jobseeker
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'sent'; // default tab: sent
$selected_js_id = isset($_GET['js_id']) ? intval($_GET['js_id']) : null;

// Handle sending messages
if ($_SERVER["REQUEST_METHOD"] === "POST" && $selected_js_id) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $counselor_id, $selected_js_id, $message);
        $stmt->execute();
    }
}

// Fetch messages depending on the tab
$messages = [];
if ($selected_js_id) {
    if ($tab === 'sent') {
        $stmt = $conn->prepare("
            SELECT m.*, r.username AS receiver_name
            FROM messages m
            JOIN users r ON m.receiver_id = r.user_id
            WHERE sender_id = ? AND receiver_id = ?
            ORDER BY sent_at ASC
        ");
        $stmt->bind_param("ii", $counselor_id, $selected_js_id);
    } else { // inbox
        $stmt = $conn->prepare("
            SELECT m.*, s.username AS sender_name
            FROM messages m
            JOIN users s ON m.sender_id = s.user_id
            WHERE receiver_id = ? AND sender_id = ?
            ORDER BY sent_at ASC
        ");
        $stmt->bind_param("ii", $counselor_id, $selected_js_id);
    }
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Counselor Chat | WorkNest</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #0a0a1a;
    color: #d0d0f5;
    padding: 40px;
    margin: 0;
}

.container {
    max-width: 900px;
    margin: auto;
    background: rgba(20, 20, 40, 0.9);
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 0 25px rgba(157, 78, 221, 0.25);
    border: 1px solid rgba(157, 78, 221, 0.4);
}

h2 {
    color: #9d4edd;
    text-align: center;
    margin-bottom: 30px;
    font-size: 24px;
    text-shadow: 0 0 10px rgba(157, 78, 221, 0.7);
}

select, textarea, button {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 10px;
    border: none;
    font-size: 14px;
    outline: none;
}

select {
    background: #14142a;
    color: #e0e0ff;
    border: 1px solid #9d4edd;
}

textarea {
    resize: none;
    background: #14142a;
    color: #e0e0ff;
    border: 1px solid #9d4edd;
}

button {
    background: linear-gradient(90deg, #9d4edd, #007bff);
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    border: none;
}

button:hover {
    transform: scale(1.05);
    box-shadow: 0 0 15px rgba(157, 78, 221, 0.6);
}

/* Chat Box */
.chat-box {
    height: 400px;
    overflow-y: auto;
    background: #0f0f1f;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 15px;
    border: 1px solid rgba(157, 78, 221, 0.3);
}

.message {
    margin-bottom: 12px;
}

.message p {
    display: inline-block;
    padding: 12px 16px;
    border-radius: 14px;
    max-width: 65%;
    font-size: 14px;
    line-height: 1.4;
}

.message.you {
    text-align: right;
}

.message.you p {
    background: #007bff;
    color: #fff;
    box-shadow: 0 0 12px rgba(0, 123, 255, 0.6);
}

.message.them p {
    background: #2a1a3f;
    color: #cbb2ff;
    box-shadow: 0 0 12px rgba(157, 78, 221, 0.5);
}

/* Tabs */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.tab {
    flex: 1;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    border-radius: 8px;
    background: #1a1a3a;
    color: #bbb;
    font-weight: 600;
    text-decoration: none;
    transition: 0.3s;
}

.tab:hover {
    background: #9d4edd;
    color: #fff;
    box-shadow: 0 0 10px rgba(157, 78, 221, 0.5);
}

.tab.active {
    background: linear-gradient(90deg, #9d4edd, #007bff);
    color: white;
    box-shadow: 0 0 12px rgba(157, 78, 221, 0.6);
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
<div class="container">
    <h2>📩 Counselor Chat</h2>

    <!-- Tabs for Sent / Inbox -->
    <div class="tabs">
        <a href="?tab=sent" class="tab <?= $tab === 'sent' ? 'active' : '' ?>">Sent Messages</a>
        <a href="?tab=inbox" class="tab <?= $tab === 'inbox' ? 'active' : '' ?>">Inbox</a>
    </div>

    <!-- Select Jobseeker -->
    <form method="GET">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <label>Select Jobseeker:</label>
        <select name="js_id" onchange="this.form.submit()">
            <option value="">-- Choose --</option>
            <?php
            $jobseekers_result->data_seek(0); // reset pointer
            while ($row = $jobseekers_result->fetch_assoc()): ?>
                <option value="<?= $row['js_id'] ?>" <?= $selected_js_id == $row['js_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['fullname']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if ($selected_js_id): ?>
        <?php if ($tab === 'sent'): ?>
            <!-- Sent Messages + Send Form -->
            <div class="chat-box">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message you">
                            <p><?= htmlspecialchars($msg['message']) ?><br>
                            <small style="font-size: 10px;">To: <?= htmlspecialchars($msg['receiver_name']) ?> | <?= date('M d, h:i A', strtotime($msg['sent_at'])) ?></small></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No sent messages yet.</p>
                <?php endif; ?>
            </div>
            <form method="POST">
                <textarea name="message" placeholder="Type your message..." required></textarea>
                <button type="submit">Send</button>
            </form>

        <?php else: ?>
            <!-- Inbox -->
            <div class="chat-box">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message them">
                            <p><?= htmlspecialchars($msg['message']) ?><br>
                            <small style="font-size: 10px;">From: <?= htmlspecialchars($msg['sender_name']) ?> | <?= date('M d, h:i A', strtotime($msg['sent_at'])) ?></small></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No messages received yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($jobseekers_result->num_rows == 0): ?>
        <p>No jobseekers assigned to you yet.</p>
    <?php endif; ?>

</div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
