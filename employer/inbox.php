<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = intval($_SESSION['user_id']);
$username = $_SESSION['username'];
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;


// Fetch Sent Messages (employer -> jobseeker)
$sent_sql = "
    SELECT 
        m.id AS message_id,
        m.subject,
        m.message,
        m.sent_at,
        u.username AS recipient_name,
        j.title AS job_title
    FROM messages m
    JOIN users u ON m.receiver_id = u.user_id
    LEFT JOIN jobs j ON m.job_id = j.id
    WHERE m.sender_id = ?
    ORDER BY m.sent_at DESC
";
$sent_stmt = $conn->prepare($sent_sql);
$sent_stmt->bind_param("i", $emp_id);
$sent_stmt->execute();
$sent_result = $sent_stmt->get_result();

// Fetch Inbox Messages (jobseeker -> employer)
$inbox_sql = "
    SELECT 
        m.id AS message_id,
        m.subject,
        m.message,
        m.sent_at,
        u.username AS sender_name,
        j.title AS job_title
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    LEFT JOIN jobs j ON m.job_id = j.id
    WHERE m.receiver_id = ?
    ORDER BY m.sent_at DESC
";
$inbox_stmt = $conn->prepare($inbox_sql);
$inbox_stmt->bind_param("i", $emp_id);
$inbox_stmt->execute();
$inbox_result = $inbox_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Messages | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0d0d1a, #1a0033);
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
            margin-left: 260px;
            background: rgba(25,0,51,0.9);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 3px 12px rgba(102,0,204,0.6);
        }
        .emp-topbar h2 {
            color: #a64dff;
            margin: 0;
            text-shadow: 0 0 10px #6600cc;
        }
        .emp-logout-btn {
            background: #9933ff;
            color: #fff;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 0 10px #6600cc;
        }
        .emp-logout-btn:hover {
            background: #6600cc;
            box-shadow: 0 0 12px #9933ff, 0 0 20px #6600cc;
        }

        /* Main */
        .emp-main {
            margin-left: 260px;
            padding: 30px;
            max-width: 900px;
        }
        h1 {
            color: #e0b3ff;
            text-shadow: 0 0 12px #a64dff;
            margin-bottom: 20px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #6600cc;
        }
        .tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            border-radius: 6px 6px 0 0;
            background: rgba(102,0,204,0.3);
            color: #e0e0ff;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .tab.active {
            background: #6600cc;
            color: #fff;
            box-shadow: 0 -3px 12px #9933ff inset;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Messages */
        .message-card {
            background: rgba(25,0,51,0.8);
            border-left: 6px solid #a64dff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(153,51,255,0.4);
            transition: all 0.3s ease;
        }
        .message-card:hover {
            box-shadow: 0 0 15px #a64dff, 0 0 25px #6600cc;
        }
        .message-card h4 { margin:0 0 10px; color:#e0b3ff; }
        .message-card p { margin:4px 0; color:#ccc; white-space:pre-wrap; }
        .no-messages { text-align:center; color:#888; font-style:italic; margin-top:20px; }
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

<div class="emp-main" style="position:relative;">
    <h1>Messages</h1>

    <div class="tabs">
        <div class="tab active" onclick="openTab('sent')">Sent Messages</div>
        <div class="tab" onclick="openTab('inbox')">Inbox</div>
    </div>


    <div id="sent" class="tab-content active">
        <?php if ($sent_result && $sent_result->num_rows > 0): ?>
            <?php while ($msg = $sent_result->fetch_assoc()): ?>
                <div class="message-card">
                    <h4>To: <?php echo htmlspecialchars($msg['recipient_name']); ?>
                        <?php if (!empty($msg['job_title'])): ?> | Job: <?php echo htmlspecialchars($msg['job_title']); ?> <?php endif; ?>
                    </h4>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($msg['subject']); ?></p>
                    <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                    <p><small>Sent on: <?php echo date("M d, Y h:i A", strtotime($msg['sent_at'])); ?></small></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-messages">No sent messages found.</p>
        <?php endif; ?>
    </div>

    <div id="inbox" class="tab-content">
        <?php if ($inbox_result && $inbox_result->num_rows > 0): ?>
            <?php while ($msg = $inbox_result->fetch_assoc()): ?>
                <div class="message-card">
                    <h4>From: <?php echo htmlspecialchars($msg['sender_name']); ?>
                        <?php if (!empty($msg['job_title'])): ?> | Job: <?php echo htmlspecialchars($msg['job_title']); ?> <?php endif; ?>
                    </h4>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($msg['subject']); ?></p>
                    <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                    <p><small>Received on: <?php echo date("M d, Y h:i A", strtotime($msg['sent_at'])); ?></small></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-messages">No messages in your inbox.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}

function openTab(tabName) {
    var tabs = document.querySelectorAll('.tab');
    var contents = document.querySelectorAll('.tab-content');
    tabs.forEach(t => t.classList.remove('active'));
    contents.forEach(c => c.classList.remove('active'));
    document.querySelector('.tab[onclick="openTab(\'' + tabName + '\')"]').classList.add('active');
    document.getElementById(tabName).classList.add('active');
}
</script>

</body>
</html>
