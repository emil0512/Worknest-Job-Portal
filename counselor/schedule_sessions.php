<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: ../login.php");
    exit();
}

$counselor_id = (int)$_SESSION['user_id'];

// Mark as completed
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $appointment_id = (int)$_GET['complete'];
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'Completed' 
        WHERE id = ? AND counselor_id = ? AND status = 'Confirmed'
    ");
    $stmt->bind_param("ii", $appointment_id, $counselor_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch sessions (deduplicated by appointment id)
$stmt = $conn->prepare("
    SELECT a.id, a.date, a.time, a.status, jp.fullname 
    FROM appointments a
    JOIN jobseeker_profiles jp ON a.jobseeker_id = jp.js_id
    WHERE a.counselor_id = ?
    GROUP BY a.id, a.date, a.time, a.status, jp.fullname
    ORDER BY a.date ASC, a.time ASC
");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$sessions = $stmt->get_result();
$stmt->close();

// Helper to get CSS class for status
function statusClass($status) {
    $map = [
        'Pending' => 'status-pending',
        'Confirmed' => 'status-upcoming',
        'Completed' => 'status-completed',
        'Rejected' => 'status-rejected'
    ];
    return $map[$status] ?? 'status-upcoming';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Schedule Sessions | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a1a;
            color: #e0e0ff;
            margin: 0;
            padding: 40px;
        }
        h1 {
            text-align: center;
            color: #00eaff;
                    }

        /* Table */
        .session-table {
            width: 100%;
            max-width: 950px;
            margin: 40px auto;
            border-collapse: collapse;
            background: rgba(20,20,50,0.95);
            border-radius: 14px;
            box-shadow: 0 0 25px rgba(138, 43, 226, 0.6);
            overflow: hidden;
        }
        .session-table th, .session-table td {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: left;
        }
        .session-table th {
            background: linear-gradient(90deg, #6a0dad, #1e3a8a);
            color: #fff;
            text-shadow: 0 0 8px #00eaff;
        }
        .session-table tr:nth-child(even) {
            background: rgba(15,15,35,0.9);
        }

        /* Status Pills */
        .status-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
            text-transform: capitalize;
            box-shadow: 0 0 8px rgba(0,0,0,0.5);
        }
        .status-pending { background: rgba(255, 152, 0, 0.2); color: #ff9800; border: 1px solid #ff9800; }
        .status-upcoming { background: rgba(0, 255, 200, 0.2); color: #00ffc8; border: 1px solid #00ffc8; }
        .status-completed { background: rgba(0, 255, 128, 0.2); color: #00ff80; border: 1px solid #00ff80; }
        .status-rejected { background: rgba(255, 76, 106, 0.2); color: #ff4c6a; border: 1px solid #ff4c6a; }

        /* Buttons */
        .complete-btn {
            background: linear-gradient(90deg, #a855f7, #00eaff);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 0 15px #a855f7;
            transition: transform 0.2s, box-shadow 0.3s;
        }
        .complete-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px #00eaff;
        }

        .feedback-history-btn {
            text-align: center;
            margin-top: 30px;
        }
        .feedback-history-btn button {
            padding: 14px 28px;
            background: linear-gradient(90deg, #a855f7, #00eaff);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 0 15px #a855f7;
            transition: transform 0.2s, box-shadow 0.3s;
        }
        .feedback-history-btn button:hover {
            transform: scale(1.07);
            box-shadow: 0 0 25px #00eaff;
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

<h1>📅 Scheduled Sessions</h1>

<table class="session-table">
    <tr>
        <th>Jobseeker</th>
        <th>Date</th>
        <th>Time</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php if ($sessions->num_rows > 0): ?>
        <?php while ($row = $sessions->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['fullname']) ?></td>
                <td><?= htmlspecialchars(date("F j, Y", strtotime($row['date']))) ?></td>
                <td><?= htmlspecialchars(date("g:i A", strtotime($row['time']))) ?></td>
                <td><span class="status-pill <?= statusClass($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                <td>
                    <?php if ($row['status'] === 'Confirmed'): ?>
                        <a href="?complete=<?= (int)$row['id'] ?>" onclick="return confirm('Mark this session as completed?')">
                            <button class="complete-btn">✔ Mark Completed</button>
                        </a>
                    <?php else: ?>
                        — 
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="5" style="text-align:center;">😔 No sessions scheduled yet.</td></tr>
    <?php endif; ?>
</table>

<div class="feedback-history-btn">
  <a href="session_feedback_history.php">
    <button>📝 View Session Feedback History</button>
  </a>
</div>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
