<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: ../login.php");
    exit();
}

$counselor_id = $_SESSION['user_id'];
$success = $error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $experience = trim($_POST['experience']);
    $specialization = trim($_POST['specialization']);
    $qualifications = trim($_POST['qualifications']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $bio = trim($_POST['bio']);
    $skills = trim($_POST['skills']);
    $languages = trim($_POST['languages']);
    $availability = trim($_POST['availability']); // exists in DB
    // languages exists in form but not in DB — ignored in save

    // Handle profile picture upload
    $photo_path = $profile_picture = null;
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "../uploads/counselor_photos/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = "counselor_" . $counselor_id . "_" . time() . "." . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $photo_path = "uploads/counselor_photos/" . $file_name;
            $profile_picture = $photo_path;
        } else {
            $error = "Failed to upload photo.";
        }
    }

    // --- Input Sanitization ---
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Sanitize all inputs
$name            = clean_input($name);
$experience      = clean_input($experience);
$specialization  = clean_input($specialization);
$qualifications  = clean_input($qualifications);
$phone           = clean_input($phone);
$email           = clean_input($email);
$bio             = clean_input($bio);
$skills          = clean_input($skills);
$languages       = clean_input($languages);
$availability    = clean_input($availability);

// --- Basic Validation Rules ---
// --- Enhanced Validation Rules ---
$errors = [];

// All fields required
if (
    empty($name) ||
    empty($experience) ||
    empty($specialization) ||
    empty($qualifications) ||
    empty($phone) ||
    empty($email) ||
    empty($bio) ||
    empty($skills) ||
    empty($languages) ||
    empty($availability)
) {
    $errors[] = "⚠️ All fields are required.";
}

// Name: alphabets, spaces, dots, hyphens allowed (no digits)
if (!preg_match("/^[A-Za-z\s.'-]{2,100}$/", $name)) {
    $errors[] = "❌ Name must contain only letters and spaces (2–100 characters).";
}

// Experience: must be a number (years)
if (!preg_match("/^[0-9]{1,2}$/", $experience)) {
    $errors[] = "❌ Experience must be a valid number of years (e.g., 5).";
}

// Email: strict validation for proper format and domain
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "❌ Please enter a valid email address (e.g., example@domain.com).";
} elseif (!preg_match("/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/", $email)) {
    $errors[] = "❌ Email must include @ and a valid domain (e.g., gmail.com).";
}

// Phone: exactly 10 digits only
if (!preg_match("/^[0-9]{10}$/", $phone)) {
    $errors[] = "❌ Phone number must be exactly 10 digits (numbers only).";
}


// Remove potential XSS <script> tags from long text fields
foreach (['bio', 'skills', 'languages', 'availability'] as $field) {
    $$field = preg_replace("/<script.*?>.*?<\/script>/is", "", $$field);
}

// If any validation error exists → do not save profile
if (empty($errors)) {
    $sql = "REPLACE INTO counselor_profiles 
    (counselor_id, name, specialization, bio, email, phone, experience, qualifications, skills, languages, availability) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issssssssss",
        $counselor_id,
        $name,
        $specialization,
        $bio,
        $email,
        $phone,
        $experience,
        $qualifications,
        $skills,
        $languages,
        $availability
    );

    if ($stmt->execute()) {
        $success = "✅ Profile updated successfully!";
    } else {
        $error = "❌ Something went wrong while saving: " . $stmt->error;
    }

    $stmt->close();
} else {
    $error = implode("<br>", $errors);
}
// Sanitize long text fields (no script tags)
$bio            = preg_replace("/<script.*?>.*?<\/script>/is", "", $bio);
$skills         = preg_replace("/<script.*?>.*?<\/script>/is", "", $skills);
$languages      = preg_replace("/<script.*?>.*?<\/script>/is", "", $languages);
$qualifications = preg_replace("/<script.*?>.*?<\/script>/is", "", $qualifications);
$experience     = preg_replace("/<script.*?>.*?<\/script>/is", "", $experience);
$specialization = preg_replace("/<script.*?>.*?<\/script>/is", "", $specialization);

if (empty($errors)) {
    $sql = "REPLACE INTO counselor_profiles 
    (counselor_id, name, specialization, bio, email, phone, experience, qualifications, skills, languages, availability) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issssssssss",
        $counselor_id,
        $name,
        $specialization,
        $bio,
        $email,
        $phone,
        $experience,
        $qualifications,
        $skills,
        $languages,
        $availability
    );

    if ($stmt->execute()) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Something went wrong: " . $stmt->error;
    }

    $stmt->close();
} else {
    // Join multiple validation messages
    $error = implode("<br>", $errors);
}

}

// Fetch current profile data
$stmt = $conn->prepare("SELECT * FROM counselor_profiles WHERE counselor_id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Counselor Profile | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
 body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a1a;
            color: #e0e0ff;
            margin: 0;
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

        /* Container */
        .container {
            max-width: 850px;
            margin: auto;
            background: linear-gradient(145deg, rgba(20,20,50,0.95), rgba(10,10,30,0.95));
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 0 25px rgba(138, 43, 226, 0.6);
            margin-top: 70px;
        }
        h2 {
            color: #00eaff;
            text-align: center;
            text-shadow: 0 0 0px #00eaff;
        }

        /* Inputs */
        label {
            font-weight: 600;
            display: block;
            margin-top: 15px;
            color: #c084fc;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px;
            margin-top: 5px;
            border: 1px solid #4b0082;
            border-radius: 8px;
            background: #0f0f2f;
            color: #e0e0ff;
            outline: none;
            transition: 0.3s;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #00eaff;
            box-shadow: 0 0 10px #00eaff;
        }

        /* Button */
        button {
            margin-top: 25px;
            background: linear-gradient(90deg, #a855f7, #00eaff);
            color: white;
            border: none;
            padding: 14px 20px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 0 15px #a855f7;
            transition: transform 0.2s, box-shadow 0.3s;
        }
        button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px #00eaff;
        }

        /* Messages */
        .msg { padding: 12px; border-radius: 6px; margin-top: 15px; font-weight: 600; }
        .success { background: rgba(0, 255, 128, 0.1); color: #00ffae; border-left: 4px solid #00ffae; }
        .error { background: rgba(255, 0, 64, 0.1); color: #ff4c6a; border-left: 4px solid #ff4c6a; }

        /* Profile Photo */
        .profile-photo {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-photo img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #a855f7;
            box-shadow: 0 0 20px #a855f7, 0 0 35px #00eaff;
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
    <h1><center>👤 Counselor Profile</center></h1>

    <?php if ($success): ?><div class="msg success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= $error ?></div><?php endif; ?>
<form method="POST">
    <label>Name *</label>
    <input type="text" name="name" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>

    <label>Years of Experience *</label>
    <input type="text" name="experience" value="<?= htmlspecialchars($profile['experience'] ?? '') ?>" required>

    <label>Specialization *</label>
    <input type="text" name="specialization" value="<?= htmlspecialchars($profile['specialization'] ?? '') ?>" required>

    <label>Qualifications *</label>
    <textarea name="qualifications" required><?= htmlspecialchars($profile['qualifications'] ?? '') ?></textarea>

    <label>Phone *</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" required>

    <label>Email *</label>
    <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required>

    <label>Bio</label>
    <textarea name="bio"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>

    <label>Skills (comma separated)</label>
    <input type="text" name="skills" value="<?= htmlspecialchars($profile['skills'] ?? '') ?>">

    <label>Languages</label>
    <input type="text" name="languages" value="<?= htmlspecialchars($profile['languages'] ?? '') ?>">

    <label>Availability</label>
    <input type="text" name="availability" value="<?= htmlspecialchars($profile['availability'] ?? '') ?>">

    <button type="submit">💾 Save Profile</button>
</form>
</div>
<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
