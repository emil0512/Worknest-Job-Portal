<?php
session_start();
include '../config/db.php';
include '../includes/pdfparser.php';
use Smalot\PdfParser\Parser;

$parser = new Parser();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$errors = [];
$success = "";

// Fetch existing profile data
$existing_profile = ['fullname' => '', 'skills' => '', 'education' => '', 'experience' => ''];

$profile_sql = $conn->prepare("SELECT fullname, skills, education, experience FROM jobseeker_profiles WHERE js_id = ?");
$profile_sql->bind_param("i", $js_id);
$profile_sql->execute();
$profile_result = $profile_sql->get_result();
if ($profile_result->num_rows > 0) {
    $existing_profile = $profile_result->fetch_assoc();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $skills = trim($_POST['skills']);
    $education = trim($_POST['education']);
    $experience = trim($_POST['experience']);
    $resume_path = null;
    $resume_text = "";

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $file_tmp = $_FILES['resume']['tmp_name'];
        $file_name = $_FILES['resume']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'pdf') {
            $errors[] = "Only PDF files are allowed.";
        } else {
            $new_filename = "resume_" . $js_id . "_" . time() . ".pdf";
            $destination = "../uploads/resumes/" . $new_filename;
            if (!file_exists("../uploads/resumes")) {
                mkdir("../uploads/resumes", 0777, true);
            }
            if (move_uploaded_file($file_tmp, $destination)) {
                $resume_path = $destination;

                // Parse resume
                try {
                    $pdf = $parser->parseFile($resume_path);
                    $resume_text = strtolower($pdf->getText());
                } catch (Exception $e) {
                    $errors[] = "Failed to parse PDF.";
                }
            }
        }
    }

  

    // Insert/Update jobseeker profile
    $check = $conn->prepare("SELECT js_id FROM jobseeker_profiles WHERE js_id = ?");
    $check->bind_param("i", $js_id);
    $check->execute();
    $check_result = $check->get_result();

 if ($check_result->num_rows > 0) {
    $sql = "UPDATE jobseeker_profiles SET fullname=?, skills=?, education=?, experience=?";
    if ($resume_path) $sql .= ", resume_path=?";
    $sql .= " WHERE js_id=?";
    $stmt = $conn->prepare($sql);
    if ($resume_path) {
        $stmt->bind_param("sssssi", $fullname, $skills, $education, $experience, $resume_path, $js_id);
    } else {
        $stmt->bind_param("ssssi", $fullname, $skills, $education, $experience, $js_id);
    }
} else {
    $stmt = $conn->prepare("INSERT INTO jobseeker_profiles (js_id, fullname, skills, education, experience, resume_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $js_id, $fullname, $skills, $education, $experience, $resume_path);
}


    if ($stmt->execute()) {

        // Keyword matching if resume was parsed
        if (!empty($resume_text)) {
            $categories = ['skills', 'job_titles', 'education_fields', 'certifications', 'experience_levels'];
            $matches = [];

            foreach ($categories as $category) {
                $matches[$category] = [];
                $result = $conn->query("SELECT keyword FROM keywords WHERE category = '$category'");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $keyword = strtolower($row['keyword']);
                        if (stripos($resume_text, $keyword) !== false) {
                            $matches[$category][] = $keyword;
                        }
                    }
                }
            }

            // Store parsed results in DB
            $stmt_parsed = $conn->prepare("
                INSERT INTO parsed_resume_data (js_id, skills, job_titles, education_fields, certifications, experience_levels)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    skills=VALUES(skills),
                    job_titles=VALUES(job_titles),
                    education_fields=VALUES(education_fields),
                    certifications=VALUES(certifications),
                    experience_levels=VALUES(experience_levels)
            ");
            $stmt_parsed->bind_param(
                "isssss",
                $js_id,
                implode(', ', $matches['skills']),
                implode(', ', $matches['job_titles']),
                implode(', ', $matches['education_fields']),
                implode(', ', $matches['certifications']),
                implode(', ', $matches['experience_levels'])
            );
            $stmt_parsed->execute();
        }

        $success = "Profile updated and resume parsed successfully!";
        $existing_profile = [
            'fullname' => $fullname,
            'skills' => $skills,
            'education' => $education,
            'experience' => $experience
        ];
    } else {
        $errors[] = "Error updating profile. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Profile - Jobseeker | WorkNest</title>
    <link rel="stylesheet" href="../assets/style.css">
<style>
body { font-family: 'Poppins', sans-serif; background: #f0f8ff; margin:0; padding:0; }
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
.container {
  max-width:700px; margin:100px auto; background:white; padding:30px; border-radius:12px; box-shadow:0 2px 15px rgba(0,0,0,0.1);
}
h2 { color:#003366; text-align:center; margin-bottom:20px; }
input, textarea { width:100%; padding:12px; margin-bottom:15px; border-radius:6px; border:1px solid #ccc; font-size:15px; }
input[type="submit"] { background:#007c91; color:white; border:none; font-weight:bold; cursor:pointer; }
input[type="submit"]:hover { background:#005f67; }
.success { background:#d4edda; color:#155724; padding:10px; border-radius:6px; margin-bottom:15px; }
.error { background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-bottom:15px; }
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
    <a href="search_jobs.php">🔍 Search Jobs</a>
<a href="applied_jobs.php">📄 Applied Jobs</a>
    <a href="book_session.php">📅 Career Guidance<center>Session</center></a>
    <a href="messages.php">💬 Messages</a>
    <a href="leave_review.php">📝 Leave Review</a>
    <a href="settings.php">⚙️ Settings</a>
    <a href="../logout.php">🚪 Logout</a>
</div>
<div class="container">
    <h2>🧑‍💼 Update Your Profile</h2>

    <?php if (!empty($errors)): ?>
        <div class='error'><ul><?php foreach ($errors as $error) echo "<li>$error</li>"; ?></ul></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class='success'><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Full Name</label>
        <input type="text" name="fullname" required value="<?= htmlspecialchars($existing_profile['fullname']) ?>">

        <label>Skills</label>
        <textarea name="skills" required placeholder="e.g., PHP, MySQL, JavaScript..."><?= htmlspecialchars($existing_profile['skills']) ?></textarea>

        <label>Education</label>
        <textarea name="education" required><?= htmlspecialchars($existing_profile['education']) ?></textarea>

        <label>Experience</label>
        <textarea name="experience" required><?= htmlspecialchars($existing_profile['experience']) ?></textarea>

        <label>Upload Resume (PDF)</label>
        <input type="file" name="resume" accept=".pdf">

        <input type="submit" value="Update Profile">
    </form>
</div>
<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
