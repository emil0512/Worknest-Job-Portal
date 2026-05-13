<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];

// ---- Fetch employers this jobseeker has applied to ----
$sql_employers = "
    SELECT DISTINCT u.user_id AS user_id, u.username AS employer_username
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON j.emp_id = u.user_id
    WHERE a.user_id = ?
";
$stmt = $conn->prepare($sql_employers);
$stmt->bind_param("i", $js_id);
$stmt->execute();
$result = $stmt->get_result();
$employers = [];
while ($row = $result->fetch_assoc()) {
    $employers[] = [
        'user_id' => $row['user_id'],
        'name' => $row['employer_username'],
        'type' => 'employer'
    ];
}
$stmt->close();

// ---- Fetch counselors (unique only, no duplicates) ----
$sql_counselors = "
    SELECT DISTINCT u.user_id, u.username
    FROM counselor_assignments ca
    JOIN users u ON ca.counselor_id = u.user_id
    WHERE ca.jobseeker_id = ?
";
$stmt = $conn->prepare($sql_counselors);
$stmt->bind_param("i", $js_id);
$stmt->execute();
$result = $stmt->get_result();
$counselors = [];
while ($row = $result->fetch_assoc()) {
    $counselors[] = [
        'user_id' => $row['user_id'],
        'name' => $row['username'],
        'type' => 'counselor'
    ];
}
$stmt->close();

// ---- Combine partners list ----
$partners = array_merge($employers, $counselors);

// ---- Fetch messages with selected partner ----
$chat_with = isset($_GET['chat_with']) ? intval($_GET['chat_with']) : null;
$messages = [];
if ($chat_with) {
    $sql_messages = "
        SELECT sender_id, receiver_id, message, sent_at
        FROM messages
        WHERE (sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY sent_at ASC
    ";
    $stmt = $conn->prepare($sql_messages);
    $stmt->bind_param("iiii", $js_id, $chat_with, $chat_with, $js_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Messages | WorkNest</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
  body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: radial-gradient(circle at top left, #0f0c29, #302b63, #24243e);
    color: #f5f5f5;
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
  /* Main Content */
  .main {
    margin-left: 260px;
    padding: 30px;
  }
  h1 {
    color: #9f7aff;
    text-shadow: 0 0 0px #9f7aff, 0 0 0px #6f42c1;
  }

  /* Partners List */
  .partners {
    width: 220px;
    background: rgba(42, 42, 106, 0.85);
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 0 15px rgba(159, 122, 255, 0.4);
    height: 500px;
    overflow-y: auto;
  }

  /* Partners Scrollbar */
  .partners::-webkit-scrollbar {
    width: 6px;
  }
  .partners::-webkit-scrollbar-track {
    background: rgba(26, 26, 64, 0.6);
    border-radius: 8px;
  }
  .partners::-webkit-scrollbar-thumb {
    background-color: #7a5cf4;
    border-radius: 8px;
    box-shadow: 0 0 6px rgba(159, 122, 255, 0.6);
  }
  .partners::-webkit-scrollbar-thumb:hover {
    background-color: #9f7aff;
  }
  .partners {
    scrollbar-width: thin;
    scrollbar-color: #7a5cf4 rgba(26, 26, 64, 0.6);
  }

  .partners h3 {
    color: #b29bff;
    text-shadow: 0 0 10px #7a5cf4;
  }
  .partners ul {
    list-style: none;
    padding-left: 0;
  }
  .partners li {
    margin-bottom: 10px;
  }
  .partners a {
    color: #c9c9ff;
    text-decoration: none;
    font-weight: 600;
    display: block;
    padding: 8px;
    border-radius: 6px;
    transition: 0.3s;
  }
  .partners a:hover {
    background: rgba(159, 122, 255, 0.2);
    color: #fff;
    text-shadow: 0 0 8px #9f7aff;
  }

  /* Chat container */
  .chat-container {
    flex: 1;
    background: rgba(26, 26, 64, 0.9);
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 0 15px rgba(159, 122, 255, 0.3);
    display: flex;
    flex-direction: column;
  }

  /* Messages Box */
  .messages-box {
    flex: 1;
    border: 1px solid rgba(159, 122, 255, 0.3);
    padding: 12px;
    height: 300px;
    overflow-y: auto;
    margin-bottom: 15px;
    background: rgba(15, 12, 41, 0.8);
    border-radius: 8px;
    box-shadow: inset 0 0 8px rgba(159, 122, 255, 0.2);

    /* Firefox */
    scrollbar-width: thin;
    scrollbar-color: #7a5cf4 rgba(20, 20, 50, 0.6);
  }

  /* Messages Scrollbar Webkit */
  .messages-box::-webkit-scrollbar {
    width: 6px;
  }
  .messages-box::-webkit-scrollbar-track {
    background: rgba(20, 20, 50, 0.6);
    border-radius: 8px;
  }
  .messages-box::-webkit-scrollbar-thumb {
    background-color: #7a5cf4;
    border-radius: 8px;
    box-shadow: 0 0 6px rgba(159, 122, 255, 0.6);
  }
  .messages-box::-webkit-scrollbar-thumb:hover {
    background-color: #9f7aff;
  }

  .messages-box p {
    margin: 8px 0;
    color: #e0e0ff;
  }
  .messages-box strong {
    color: #9f7aff;
  }
  .messages-box small {
    color: #888;
  }

  /* Textarea */
  textarea {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid rgba(159, 122, 255, 0.4);
    resize: none;
    background: rgba(20, 20, 50, 0.9);
    color: #f5f5f5;
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
  /* Send Button */
  button {
    background: linear-gradient(90deg, #6a11cb, #2575fc);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 8px;
    box-shadow: 0 0 12px rgba(106, 17, 203, 0.6);
    transition: 0.3s;
  }
  button:hover {
    background: linear-gradient(90deg, #2575fc, #6a11cb);
    box-shadow: 0 0 16px rgba(159, 122, 255, 0.8);
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
  <h1>💬 Messages</h1>
  <div style="display: flex; gap: 20px;">
    <div class="partners">
      <h3>Partners</h3>
      <ul>
        <?php foreach ($partners as $p): ?>
          <li>
            <a href="?chat_with=<?= intval($p['user_id']) ?>">
              <?= htmlspecialchars($p['name']) ?> (<?= $p['type'] ?>)
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="chat-container">
      <?php if ($chat_with): ?>
        <div class="messages-box">
          <?php foreach ($messages as $msg): ?>
            <p>
              <strong><?= $msg['sender_id'] == $js_id ? 'You' : 'Them' ?>:</strong>
              <?= htmlspecialchars($msg['message']) ?>
              <br><small><?= $msg['sent_at'] ?></small>
            </p>
          <?php endforeach; ?>
        </div>
        <form method="POST" action="send_message.php">
          <input type="hidden" name="receiver_id" value="<?= intval($chat_with) ?>">
          <textarea name="message" required></textarea>
          <button type="submit">Send</button>
        </form>
      <?php else: ?>
        <p>Select a partner to start chatting.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>

</body>
</html>
