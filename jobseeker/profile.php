<?php
session_start();
include("../db.php");

// Redirect if not a jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Fetch jobseeker profile
$profileStmt = $conn->prepare("SELECT * FROM jobseeker_profiles WHERE js_id = ?");
$profileStmt->bind_param("i", $js_id);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();

$fullname   = $profile['fullname'] ?? '';
$resume_path = $profile['resume_path'] ?? '';
$education  = $profile['education_text'] ?? '';
$experience = $profile['experience_text'] ?? '';
$skills     = $profile['skills_text'] ?? '';

// Auto-fill fullname from users.username if empty
if (empty($fullname)) {
    $userStmt = $conn->prepare("SELECT username FROM users WHERE user_id = ? AND role = 'jobseeker'");
    $userStmt->bind_param("i", $js_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result()->fetch_assoc();
    if ($userResult) {
        $fullname = $userResult['username'];
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname   = trim($_POST['fullname']);
    $skills     = trim($_POST['skills']);
    $education  = trim($_POST['education']);
    $experience = trim($_POST['experience']);
    $parsed_text = '';

    // Handle resume upload (optional if already exists)
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $resume_name = basename($_FILES['resume']['name']);
        $resume_tmp  = $_FILES['resume']['tmp_name'];
        $file_ext = strtolower(pathinfo($resume_name, PATHINFO_EXTENSION));
        $mime = mime_content_type($resume_tmp);

        if ($file_ext !== 'pdf' || $mime !== 'application/pdf') {
            $error_msg = "Only valid PDF files are allowed.";
        } else {
            $upload_dir = "../uploads/resumes/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9_.-]/", "_", $resume_name);
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($resume_tmp, $target_file)) {
                $resume_path = "uploads/resumes/" . $filename;
            } else {
                $error_msg = "Failed to upload resume.";
            }
        }
    }

    // Stop further processing if upload error
    if (empty($error_msg)) {

        // Save or update profile
        $sql = "INSERT INTO jobseeker_profiles 
                (js_id, fullname, resume_path, education_text, experience_text, skills_text)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    fullname = VALUES(fullname),
                    resume_path = VALUES(resume_path),
                    education_text = VALUES(education_text),
                    experience_text = VALUES(experience_text),
                    skills_text = VALUES(skills_text)";
        $update = $conn->prepare($sql);
        $update->bind_param("isssss", $js_id, $fullname, $resume_path, $education, $experience, $skills);
        $update->execute();

        // Parse resume (optional if Smalot parser available)
        if (!empty($resume_path) && file_exists("../" . $resume_path)) {
            try {
                require_once '../vendor/autoload.php';
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile("../" . $resume_path);
                $parsed_text = $pdf->getText();
            } catch (Exception $e) {
                $parsed_text = '';
            }
        }

        // Match keywords
        $combined_text = strtolower(trim($skills . ' ' . $education . ' ' . $experience . ' ' . $parsed_text));
        $combined_text_clean = preg_replace('/[^a-z0-9\s]/', ' ', $combined_text);
        $all_words = array_unique(array_filter(array_map('trim', explode(' ', $combined_text_clean))));

        $matched_keywords = [];
        $q = $conn->query("SELECT keyword FROM keywords");
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $kw = strtolower(trim($row['keyword']));
                if ($kw && in_array($kw, $all_words, true)) {
                    $matched_keywords[$kw] = $kw;
                }
            }
        }
        $matched_keywords_str = implode(' ', $matched_keywords);

        // Save parsed data
        $stmt = $conn->prepare("
            INSERT INTO parsed_resume_data (js_id, parsed_at, matched_keywords, parsed_text)
            VALUES (?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE 
                parsed_at = NOW(),
                matched_keywords = VALUES(matched_keywords),
                parsed_text = VALUES(parsed_text)
        ");
        $stmt->bind_param("iss", $js_id, $matched_keywords_str, $parsed_text);
        $stmt->execute();
        $stmt->close();

        header("Location: dashboard.php");
        exit();
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | WorkNest</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: radial-gradient(circle at top left, #0f0c29, #302b63, #24243e);
            color: #e0e0ff;
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
        .sidebar h3 { margin-top: -30px; margin-bottom: 30px; color: #bb86fc; font-size: 28px; text-align: center; }
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

        .container {
            max-width: 700px;
            margin: 50px auto;
            background: rgba(26,26,64,0.9);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(159,122,255,0.5);
        }
        h2 {
            font-weight: 600;
            color: #b29bff;
            text-shadow: 0 0 10px #7a5cf4;
            margin-bottom: 20px;
        }
        label { display: block; margin: 15px 0 5px; font-weight: bold; color: #e0e0ff; }
        input[type="text"], textarea, input[type="file"] {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #555;
            background: rgba(0,0,0,0.2);
            color: #fff;
        }
        textarea { resize: vertical; }
        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: linear-gradient(90deg,#6a11cb,#2575fc);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 0 12px rgba(106,17,203,0.6);
            transition: 0.3s;
        }
        button:hover { background: linear-gradient(90deg,#2575fc,#6a11cb); box-shadow:0 0 16px rgba(159,122,255,0.8); }

        .back-btn { display: inline-block; margin-bottom: 20px; color: #b29bff; text-decoration: none; font-weight: bold; }
        .back-btn:hover { text-decoration: underline; }
        .error-msg { color: #ff6b6b; font-weight: bold; margin-bottom: 15px; text-shadow: 0 0 4px #ff4d4d; }
        .resume-link { color: #9f7aff; text-decoration: none; font-weight: bold; }
        .resume-link:hover { text-decoration: underline; }

        #bgVideo {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            z-index: -3;
            filter: brightness(0.35) contrast(1.2);
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
        <a href="book_session.php">📅 Career Guidance Session</a>
        <a href="messages.php">💬 Messages</a>
        <a href="leave_review.php">📝 Leave Review</a>
        <a href="settings.php">⚙️ Settings</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <div class="container">
        <a class="back-btn" href="dashboard.php">← Back to Dashboard</a>
        <h2>Edit Your Profile</h2>

        <?php if (!empty($error_msg)): ?>
            <p class="error-msg"><?= htmlspecialchars($error_msg) ?></p>
        <?php endif; ?>

<form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
    <label>Full Name <span style="color:#ff6b6b;">*</span></label>
    <input type="text" name="fullname" value="<?= htmlspecialchars($fullname) ?>" required>

    <label>Skills (comma-separated or free text) <span style="color:#ff6b6b;">*</span></label>
    <textarea name="skills" rows="3" required><?= htmlspecialchars($skills) ?></textarea>

    <label>Education (free format) <span style="color:#ff6b6b;">*</span></label>
    <textarea name="education" rows="4" required><?= htmlspecialchars($education) ?></textarea>

    <label>Experience (free format) <span style="color:#ff6b6b;">*</span></label>
    <textarea name="experience" rows="4" required><?= htmlspecialchars($experience) ?></textarea>

    <label>Upload Resume (PDF) <span style="color:#ff6b6b;">*</span></label>
    <input type="file" name="resume" accept=".pdf" <?= empty($resume_path) ? 'required' : '' ?>>

    <?php if (!empty($resume_path)): ?>
        <?php $viewUrl = "/job_portal/" . $resume_path; ?>
        <p>📄 Current Resume: <a class="resume-link" href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" rel="noopener noreferrer">View</a></p>
    <?php endif; ?>

    <button type="submit">Save Changes</button>
</form>

<script>
function validateForm() {
    const fullname = document.querySelector('[name="fullname"]').value.trim();
    const skills = document.querySelector('[name="skills"]').value.trim();
    const education = document.querySelector('[name="education"]').value.trim();
    const experience = document.querySelector('[name="experience"]').value.trim();
    const resume = document.querySelector('[name="resume"]');

    if (!fullname || !skills || !education || !experience) {
        alert("All fields are required.");
        return false;
    }

    // Require resume upload if not already uploaded
    if (!resume.files.length && "<?= $resume_path ?>".trim() === "") {
        alert("Please upload your resume (PDF required).");
        return false;
    }

    const file = resume.files[0];
    if (file && file.type !== "application/pdf") {
        alert("Only PDF files are allowed for resume upload.");
        return false;
    }

    return true;
}
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}
</script>

</body>
</html>
