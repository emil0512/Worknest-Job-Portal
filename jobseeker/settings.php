<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Fetch current hashed password
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $js_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $error = "❌ Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $error = "❌ New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "❌ Password must be at least 6 characters.";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update->bind_param("si", $hashed, $js_id);
        if ($update->execute()) {
            $success = "✅ Password updated successfully!";
        } else {
            $error = "❌ Something went wrong.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Account Settings | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
        /* --------------------------
           BODY & BACKGROUND
        --------------------------- */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0f0f1e, #1a1a3b);
            color: #e0e0ff;
            padding: 40px 20px;
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


        /* --------------------------
           MAIN CONTAINER
        --------------------------- */
        .container {
            max-width: 600px;
            margin: auto;
            background: rgba(28,28,51,0.95);
            padding: 30px 25px;
            border-radius: 12px;
            box-shadow: 0 0 15px #6c63ff22;
            backdrop-filter: blur(6px);
        }

        h2 {
            text-align: center;
            color: #bb86fc;
            margin-bottom: 25px;
            text-shadow: 0 0 6px #6c63ff33;
        }

        label {
            font-weight: 600;
            display: block;
            margin-top: 18px;
            margin-bottom: 6px;
            color: #cfcfff;
        }

        input[type="password"] {
            width: 90%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #555577;
            background: #1c1c33;
            color: #e0e0ff;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }
        input[type="password"]:focus {
            border-color: #6c63ff;
            box-shadow: 0 0 5px #6c63ff33;
        }

        button {
            margin-top: 20px;
            width: 100%;
            background: #6c63ff;
            color: #fff;
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        button:hover {
            background: #7d73ff;
            transform: translateY(-1px);
        }

        .msg {
            margin-top: 20px;
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        .success {
            background: #2e8b5740;
            color: #d4edda;
            border-left: 4px solid #50c878;
        }
        .error {
            background: #b3393940;
            color: #f8d7da;
            border-left: 4px solid #b33939;
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
        a.back-btn {
            text-decoration: none;
            color: #6c63ff;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }
        a.back-btn:hover { color: #bb86fc; }
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
<div class="container">
    <a class="back-btn" href="dashboard.php">← Back to Dashboard</a>
    <h2>⚙️ Account Settings</h2>

    <?php if ($success): ?><div class="msg success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= $error ?></div><?php endif; ?>

    <form method="POST">
        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Change Password</button>
    </form>
</div>
<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
