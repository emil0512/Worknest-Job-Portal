<?php
session_start();
include("../db.php");

// ✅ Match score function
function calculate_match_score($resume_keywords, $job_title, $job_desc, $job_keywords) {
    if (empty($resume_keywords)) return 0;

    $resume_words = array_unique(preg_split('/\W+/', strtolower($resume_keywords), -1, PREG_SPLIT_NO_EMPTY));
    if (empty($resume_words)) return 0;

    // Tokenize job fields
    $title_words = array_unique(preg_split('/\W+/', strtolower($job_title), -1, PREG_SPLIT_NO_EMPTY));
    $desc_words  = array_unique(preg_split('/\W+/', strtolower($job_desc), -1, PREG_SPLIT_NO_EMPTY));
    $key_words   = array_unique(preg_split('/\W+/', strtolower($job_keywords), -1, PREG_SPLIT_NO_EMPTY));

    // Count matches with boosting
    $score = 0;
    foreach ($resume_words as $w) {
        if (in_array($w, $title_words)) $score += 5;   // Title matches strongest
        if (in_array($w, $key_words)) $score += 3;    // Keywords medium boost
        if (in_array($w, $desc_words)) $score += 1;   // Description lowest
    }

    // Normalize score to 0–100
    $max_possible = (count($title_words) * 5) + (count($key_words) * 3) + (count($desc_words) * 1);
    $score = ($max_possible > 0) ? intval(($score / $max_possible) * 100) : 0;

    return min(100, $score);
}

// Redirect if not an employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$emp_id   = $_SESSION['user_id'];
$successMsg = $errorMsg = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $job_type    = trim($_POST['job_type'] ?? '');
    $keywords    = trim($_POST['keywords'] ?? '');
    $status      = 'open';

    // ✅ Require all fields to be non-empty
    if ($title && $description && $location && $job_type && $keywords) {
        // Check for duplicate job
        $checkStmt = $conn->prepare(
            "SELECT id FROM jobs WHERE emp_id = ? AND title = ? AND description = ? AND location = ? AND job_type = ? AND keywords = ?"
        );
        $checkStmt->bind_param("isssss", $emp_id, $title, $description, $location, $job_type, $keywords);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $errorMsg = "⚠️ You have already posted a similar job!";
        } else {
            // Insert new job
            $stmt = $conn->prepare(
                "INSERT INTO jobs (emp_id, title, description, location, job_type, keywords, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issssss", $emp_id, $title, $description, $location, $job_type, $keywords, $status);

       if ($stmt->execute()) {
    $successMsg = "✅ Job posted successfully!";

    // --- Update match scores for all users ---
    $new_job_id = $conn->insert_id;
    $users = $conn->query("SELECT js_id, matched_keywords FROM parsed_resume_data");

    while ($user = $users->fetch_assoc()) {
        $js_id = $user['js_id'];
        $resume_keywords = $user['matched_keywords'] ?? '';
        $score = calculate_match_score($resume_keywords, $title, $description, $keywords);

        // ✅ Ensure js_id exists in users before inserting to avoid foreign key error
        $checkUser = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $checkUser->bind_param("i", $js_id);
        $checkUser->execute();
        $checkUserResult = $checkUser->get_result();

        if ($checkUserResult->num_rows > 0) {
            $stmt2 = $conn->prepare(
                "INSERT INTO job_matches (js_id, job_id, score)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE score = VALUES(score), updated_at = CURRENT_TIMESTAMP"
            );
            $stmt2->bind_param("iii", $js_id, $new_job_id, $score);
            $stmt2->execute();
            $stmt2->close();
        }
        $checkUser->close();
    } // <-- properly closes the while loop

    // --- End match score update ---
} else {
    $errorMsg = "❌ Error: " . $stmt->error;
}

            $stmt->close();
        }
        $checkStmt->close();
    } else {
        $errorMsg = "⚠️ Please fill in all required fields (Title, Description, Location, Job Type, and Keywords).";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post a Job | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #0a0a1a;
            color: #d0d0f5;
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
        .emp-sidebar h3 { margin-top: -30px; margin-bottom: 30px; color: #bb86fc; font-size: 28px; text-align: center; }
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

        /* Main content */
        .emp-main { margin-left: 260px; padding: 30px; }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #1a1a2e;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(50, 50, 150, 0.2);
            border: 1px solid #2d2d4d;
        }
        h2 { text-align: center; color: #9d4edd; margin-bottom: 25px; text-shadow: 0 0 6px rgba(157, 78, 221, 0.5); }
        input[type="text"], textarea {
            width: 95%;
            padding: 12px 15px;
            margin: 10px 0;
            border: 1px solid #3a3a6a;
            border-radius: 8px;
            font-size: 15px;
            background: #0f0f1f;
            color: #d0d0f5;
        }
        textarea { resize: vertical; height: 120px; }
        button {
            background: #4a00e0;
            color: white;
            border: none;
            padding: 12px 25px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s, box-shadow 0.3s;
        }
        button:hover { background: #52057b; box-shadow: 0 0 10px rgba(157, 78, 221, 0.5); }

        .msg { text-align: center; font-size: 16px; margin-bottom: 20px; }
        .success { color: #00ffcc; }
        .error   { color: #ff4b5c; }

        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { text-decoration: none; color: #9d4edd; font-weight: 600; transition: color 0.3s; }
        .back-link a:hover { color: #ffffff; }

        #bgVideo {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            z-index: -3;
            filter: brightness(0.35) contrast(1.2);
        }
.back-dashboard {
    position: fixed;
    top: 50px;
    left: 500px;
    background: rgba(138,43,226,0.8);
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
}
.back-dashboard:hover {
    background: #9d4edd;
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(0,0,0,0.4);
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
<a href="dashboard.php" class="back-dashboard">← Back to Dashboard</a>
    <div class="emp-main">
        <div class="container">
            <h2>Post a New Job</h2>

            <?php if (!empty($successMsg)): ?>
                <p class="msg success"><?= $successMsg ?></p>
            <?php endif; ?>
            <?php if (!empty($errorMsg)): ?>
                <p class="msg error"><?= $errorMsg ?></p>
            <?php endif; ?>

            <form method="POST" action="">
               <input type="text" name="title" placeholder="Job Title" required>
<textarea name="description" placeholder="Job Description" required></textarea>
<input type="text" name="location" placeholder="Location" required>
<input type="text" name="job_type" placeholder="Job Type (e.g., Full-Time, Part-Time)" required>
<input type="text" name="keywords" placeholder="Keywords (comma-separated)" required>
  <button type="submit">Post Job</button>
            </form>

            <div class="back-link">
                <a href="dashboard.php">← Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById("empSidebar").classList.toggle("open");
        }
    </script>
</body>
</html>
