<?php
session_start();
ob_start();
require_once __DIR__ . '/../db.php';

// Ensure logged in as jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = (int)$_SESSION['user_id'];

// Fetch job_id from GET or POST
$job_id = intval($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
if (!$job_id) { echo "Invalid access."; exit(); }

// --- Fetch jobseeker profile ---
$profile_stmt = $conn->prepare("
    SELECT jp.fullname, jp.phone, u.email, jp.linkedin, jp.github, 
           jp.expected_salary, jp.resume_path,
           jp.skills_text, jp.education_text, jp.experience_text, jp.preferred_location
    FROM jobseeker_profiles jp
    JOIN users u ON u.user_id = jp.js_id
    WHERE jp.js_id = ?
");
$profile_stmt->bind_param("i", $js_id);
$profile_stmt->execute();
$profile_data = $profile_stmt->get_result()->fetch_assoc() ?? [];
$profile_stmt->close();

// --- Build user words vector for match scoring ---
$user_text_parts = [
    $profile_data['skills_text'] ?? '',
    $profile_data['education_text'] ?? '',
    $profile_data['experience_text'] ?? ''
];



// Tokenizer
function to_words($text){
    $parts = preg_split('/\W+/u', strtolower($text??''), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique($parts));
}
$user_words = to_words(implode(' ', $user_text_parts));

// --- Fetch job for display ---
$job_stmt = $conn->prepare("SELECT title, description, location, job_type, keywords FROM jobs WHERE id=?");
$job_stmt->bind_param("i",$job_id);
$job_stmt->execute();
$job_row = $job_stmt->get_result()->fetch_assoc() ?? [];
$job_stmt->close();

// --- Fetch match score (always, before HTML) ---
$match_stmt = $conn->prepare("
    SELECT score 
    FROM job_matches 
    WHERE js_id = ? AND job_id = ? 
    LIMIT 1
");
$match_stmt->bind_param("ii", $js_id, $job_id);
$match_stmt->execute();
$match_res = $match_stmt->get_result()->fetch_assoc();
$match_score = $match_res['score'] ?? 0; // default to 0 if not found
$match_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- SERVER-SIDE VALIDATION ---
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $linkedin = trim($_POST['linkedin']);
    $github = trim($_POST['github']);
    $expected_salary = trim($_POST['expected_salary']);
    $preferred_location = trim($_POST['preferred_location']);
    $skills_text = trim($_POST['skills_text']);
    $education_text = trim($_POST['education_text']);
    $experience_text = trim($_POST['experience_text']);
    $cover_letter = trim($_POST['cover_letter']);

    $errors = [];

    if (!preg_match('/^[A-Za-z ]{2,50}$/', $fullname))
        $errors[] = "Invalid full name.";

    if (!preg_match('/^[1-9][0-9]{9}$/', $phone) || $phone === "0000000000")
        $errors[] = "Invalid phone number.";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Invalid email address.";

    if (!empty($linkedin) && !preg_match('/^https?:\/\/(www\.)?linkedin\.com\/.*/i', $linkedin))
        $errors[] = "Invalid LinkedIn URL.";

    if (!empty($github) && !preg_match('/^https?:\/\/(www\.)?github\.com\/[A-Za-z0-9_-]+\/?$/i', $github))
        $errors[] = "Invalid GitHub URL.";

    if (!preg_match('/^[0-9]+$/', $expected_salary))
        $errors[] = "Salary must be digits only.";

    foreach (['preferred_location' => $preferred_location, 'skills_text' => $skills_text, 'education_text' => $education_text, 'experience_text' => $experience_text, 'cover_letter' => $cover_letter] as $field => $val) {
        if (empty($val)) $errors[] = ucfirst(str_replace('_',' ', $field)) . " is required.";
    }

    if (!empty($errors)) {
        echo "<script>alert('❌ Validation Failed:\\n" . implode("\\n", $errors) . "'); window.history.back();</script>";
        exit();
    }

// ✅ If validation passes, insert into applications table
$insert = $conn->prepare("
    INSERT INTO applications (
        user_id, job_id, applied_on, match_score, status, notified, application_stage,
        fullname, phone, email, linkedin, github, expected_salary, preferred_location,
        cover_letter, skills_text, education_text, experience_text
    ) VALUES (?, ?, NOW(), ?, 'pending', 0, 'Submitted',
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");

// ✅ 3 integers (user_id, job_id, match_score) + 11 strings = 14 total
$insert->bind_param(
    "iiisssssssssss",
    $js_id,                 // i
    $job_id,                // i
    $match_score,           // i
    $fullname,              // s
    $phone,                 // s
    $email,                 // s
    $linkedin,              // s
    $github,                // s
    $expected_salary,       // s
    $preferred_location,    // s
    $cover_letter,          // s
    $skills_text,           // s
    $education_text,        // s
    $experience_text        // s
);

if ($insert->execute()) {
    echo "<script>alert('✅ Application submitted successfully!'); window.location.href='applied_jobs.php';</script>";
} else {
    echo "<script>alert('❌ Failed to submit application: " . addslashes($conn->error) . "');</script>";
}

$insert->close();

}


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Apply for Job</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
/* Body */
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
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
/* Form Card */
form {
    background: rgba(20, 20, 40, 0.95);
    padding: 30px;
    border-radius: 16px;
    max-width: 800px;
    margin: 40px auto;
    box-shadow: 0 0 50px rgba(108, 99, 255, 0.5);
    border-left: 8px solid #6c63ff;
    color: #e0e0ff;
}
form h2 { text-align:center; color:#bb86fc; margin-bottom:20px; }
label {
    display: block;
    margin-top: 20px;
    font-weight: 700;
    color: #bb86fc;
    text-shadow: 0 0 8px #6c63ff;
}
input, textarea {
    width: 100%;
    padding: 12px;
    margin-top: 5px;
    border-radius: 10px;
    border: 1px solid #6c63ff;
    background: rgba(0,0,0,0.3);
    color: #e0e0ff;
    outline: none;
    box-shadow: 0 0 10px rgba(108, 99, 255, 0.5);
    transition: 0.3s;
}
input:focus, textarea:focus {
    border-color: #bb86fc;
    box-shadow: 0 0 15px #9d4edd, 0 0 30px #6c63ff;
}
textarea { height:100px; resize: vertical; white-space: pre-wrap; }
.submit-btn {
    background: linear-gradient(135deg, #6c63ff, #9d4edd);
    color: #fff;
    padding: 12px 24px;
    border: none;
    font-weight: 700;
    margin-top: 20px;
    cursor: pointer;
    border-radius: 12px;
    box-shadow: 0 0 20px #6c63ff, 0 0 40px #9d4edd;
    transition: 0.3s;
}
.submit-btn:hover {
    transform: scale(1.08);
    box-shadow: 0 0 40px #6c63ff, 0 0 60px #bb86fc, 0 0 80px #9d4edd;
}

/* Readonly sections */
.section textarea[readonly] {
    background: rgba(0,0,0,0.2);
    border: 1px solid #9d4edd;
    color: #cfcfff;
    box-shadow: 0 0 10px #6c63ff inset;
}

/* Resume link */
p a {
    color: #6c63ff;
    font-weight: bold;
    text-decoration: underline;
}
p a:hover { color: #bb86fc; }

/* Match Score */
h2 + p {
    text-align: center;
    font-weight: 700;
    color: #28a745;
    font-size: 18px;
    text-shadow: 0 0 8px #6c63ff, 0 0 15px #9d4edd;
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
.back-btn {
    display: inline-block;
    background: linear-gradient(90deg, #6a11cb, #2575fc);
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(138,43,226,0.6);
    transition: all 0.3s ease;
    margin-right: 10px;
}
.back-btn:hover {
    background: linear-gradient(90deg, #2575fc, #6a11cb);
    box-shadow: 0 0 25px rgba(159,122,255,0.9);
    transform: scale(1.05);
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

<h2 style="text-align:center;">📄 Apply for Job: <?= htmlspecialchars($job_row['title'] ?? 'Unknown') ?></h2>
<p style="text-align:center;font-weight:bold;">Match Score: <?= $match_score ?>%</p>
<form method="POST" onsubmit="return validateForm();">

<input type="hidden" name="job_id" value="<?= $job_id ?>">

<label>Full Name</label>
<input type="text" name="fullname" value="<?= htmlspecialchars($profile_data['fullname'] ?? '') ?>" required>

<label>Phone Number</label>
<input type="text" name="phone" value="<?= htmlspecialchars($profile_data['phone'] ?? '') ?>" required>

<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>" required>

<label>LinkedIn Profile</label>
<input type="url" name="linkedin" value="<?= htmlspecialchars($profile_data['linkedin'] ?? '') ?>">

<label>GitHub Profile</label>
<input type="url" name="github" value="<?= htmlspecialchars($profile_data['github'] ?? '') ?>">

<label>Expected Salary</label>
<input type="text" name="expected_salary" value="<?= htmlspecialchars($profile_data['expected_salary'] ?? '') ?>">

<label>Preferred Job Location</label>
<input type="text" name="preferred_location" value="<?= htmlspecialchars($profile_data['preferred_location'] ?? '') ?>">

<div class="section">
  <label>Skills</label>
  <textarea name="skills_text"><?= htmlspecialchars($profile_data['skills_text'] ?? '') ?></textarea>
</div>

<div class="section">
  <label>Education</label>
  <textarea name="education_text"><?= htmlspecialchars($profile_data['education_text'] ?? '') ?></textarea>
</div>

<div class="section">
  <label>Experience</label>
  <textarea name="experience_text"><?= htmlspecialchars($profile_data['experience_text'] ?? '') ?></textarea>
</div>


<label>Cover Letter</label>
<textarea name="cover_letter">I am excited to apply for this position. My experience and skills align well with the requirements...</textarea>
<?php if(!empty($profile_data['resume_path'])): ?>
<p><strong>📎 Resume:</strong> <a href="<?= htmlspecialchars('/job_portal/uploads/resumes/'.basename($profile_data['resume_path'])) ?>" target="_blank">View Uploaded Resume</a></p>
<?php else: ?>
<p style="color:red;">⚠️ You haven't uploaded a resume. Please upload via your profile.</p>
<?php endif; ?>
<a href="job_details.php?job_id=<?= $job_id ?>" class="back-btn">⬅ Back to Job Details</a>
<button type="submit" class="submit-btn">📤 Submit Application</button>
</form>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("open");
}

// ---------------------- FORM VALIDATION ----------------------
function validateForm() {
    const name = document.querySelector('[name="fullname"]').value.trim();
    const phone = document.querySelector('[name="phone"]').value.trim();
    const email = document.querySelector('[name="email"]').value.trim();
    const linkedin = document.querySelector('[name="linkedin"]').value.trim();
    const github = document.querySelector('[name="github"]').value.trim();
    const salary = document.querySelector('[name="expected_salary"]').value.trim();
    const location = document.querySelector('[name="preferred_location"]').value.trim();
    const skills = document.querySelector('[name="skills_text"]').value.trim();
    const education = document.querySelector('[name="education_text"]').value.trim();
    const experience = document.querySelector('[name="experience_text"]').value.trim();
    const cover = document.querySelector('[name="cover_letter"]').value.trim();

    // --- Validation Rules ---
    const nameRegex = /^[A-Za-z ]{2,50}$/;
    const phoneRegex = /^[1-9][0-9]{9}$/;  // 10 digits, not all 0s
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const linkedinRegex = /^https?:\/\/(www\.)?linkedin\.com\/.*$/i;
    const githubRegex = /^https?:\/\/(www\.)?github\.com\/[A-Za-z0-9_-]+\/?$/i;
    const salaryRegex = /^[0-9]+$/;

    // --- Field Checks ---
    if (!nameRegex.test(name)) {
        alert("❌ Please enter a valid name (letters and spaces only).");
        return false;
    }

    if (!phoneRegex.test(phone) || phone === "0000000000") {
        alert("❌ Please enter a valid 10-digit phone number (no 0000000000).");
        return false;
    }

    if (!emailRegex.test(email)) {
        alert("❌ Please enter a valid email address (e.g., name@example.com).");
        return false;
    }

    if (linkedin && !linkedinRegex.test(linkedin)) {
        alert("❌ Please enter a valid LinkedIn URL (e.g., https://linkedin.com/in/username).");
        return false;
    }

    if (github && !githubRegex.test(github)) {
        alert("❌ Please enter a valid GitHub URL (e.g., https://github.com/username).");
        return false;
    }

    if (!salaryRegex.test(salary)) {
        alert("❌ Please enter a valid numeric salary amount.");
        return false;
    }

    if (!location || !skills || !education || !experience || !cover) {
        alert("⚠️ Please fill in all required fields before submitting.");
        return false;
    }

    return true; // ✅ All checks passed
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>
