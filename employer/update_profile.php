<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$success = $error = "";

// Fetch current profile data
$stmt = $conn->prepare("SELECT * FROM employer_profiles WHERE emp_id = ?");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $website = trim($_POST['website']);

   // Handle logo upload (Required + Validate image)
$logo_path = $profile['logo'] ?? null;

if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $target_dir = "../uploads/employer_logos/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $tmp_name = $_FILES['logo']['tmp_name'];
    $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $mime = mime_content_type($tmp_name);

    // Validate extension and MIME type
    if (!in_array($file_ext, $allowed_ext) || strpos($mime, 'image/') !== 0) {
        $error = "Only valid image files (JPG, PNG, GIF, WEBP) are allowed for logo.";
    } else {
        $file_name = "employer_" . $emp_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($tmp_name, $target_file)) {
            $logo_path = "uploads/employer_logos/" . $file_name;
        } else {
            $error = "Failed to upload logo.";
        }
    }
} elseif (empty($profile['logo'])) {
    $error = "Company logo is required and must be a valid image.";
}

// ✅ Proceed only if no previous errors exist
if (empty($error)) {
    // Validate required fields (now including location and website)
    if ($company_name && $description && $location && $website) {
        $stmt = $conn->prepare("REPLACE INTO employer_profiles (emp_id, company_name, description, logo, location, website) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $emp_id, $company_name, $description, $logo_path, $location, $website);

        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh profile data
            $profile = [
                'company_name' => $company_name,
                'description'  => $description,
                'logo'         => $logo_path,
                'location'     => $location,
                'website'      => $website
            ];
        } else {
            $error = "Something went wrong: " . $stmt->error;
        }
      } else {
        $error = "All fields (Company Name, Description, Location, and Website) are required.";
    }

}
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Employer Profile | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a1a;
            color: #e0e0ff;
            margin: 0;
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
        .emp-sidebar h3 { margin-top: -30px; margin-bottom: 30px; color: #bb86fc; font-size: 28px; text-align: center; }
        .emp-sidebar a { display: block; padding: 16px; margin-bottom: 10px; text-decoration: none; color: #cfcfff; font-weight: 600; border-radius: 6px; transition: background 0.2s ease; }
        .emp-sidebar a:hover { background: rgba(138,43,226,0.3); }

        /* Toggle button */
        .emp-toggle { position: fixed; top: 20px; left: 10px; font-size: 18px; background: rgba(138,43,226,0.2); color: #bb86fc; padding: 8px 14px; border-radius: 6px; cursor: pointer; z-index: 1001; transition: background 0.2s ease; }
        .emp-toggle:hover { background: rgba(138,43,226,0.4); }

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
        h2 { color: #00eaff; text-align: center; margin-bottom: 20px; }

        label { font-weight: 600; display: block; margin-top: 15px; color: #c084fc; }
        input, textarea { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #4b0082; border-radius: 8px; background: #0f0f2f; color: #e0e0ff; outline: none; transition: 0.3s; }
        input:focus, textarea:focus { border-color: #00eaff; box-shadow: 0 0 10px #00eaff; }

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
        button:hover { transform: scale(1.05); box-shadow: 0 0 25px #00eaff; }

        .msg { padding: 12px; border-radius: 6px; margin-top: 15px; font-weight: 600; }
        .success { background: rgba(0, 255, 128, 0.1); color: #00ffae; border-left: 4px solid #00ffae; }
        .error { background: rgba(255, 0, 64, 0.1); color: #ff4c6a; border-left: 4px solid #ff4c6a; }

        .profile-logo { text-align: center; margin-bottom: 20px; }
        .profile-logo img { width: 120px; height: 120px; object-fit: cover; border-radius: 14px; border: 4px solid #a855f7; box-shadow: 0 0 20px #a855f7, 0 0 35px #00eaff; }

        #bgVideo { position: fixed; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: -3; filter: brightness(0.35) contrast(1.2); }
    </style>
</head>
<body>
<video autoplay muted loop id="bgVideo">
    <source src="183279-870457579_small.mp4" type="video/mp4">
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

<div class="container">
    <h2>👤 Company Profile</h2>
    <?php if ($success): ?><div class="msg success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= $error ?></div><?php endif; ?>

   <?php if (!empty($profile['logo'])): ?>
<div class="profile-logo">
    <img src="<?= htmlspecialchars('../uploads/employer_logos/' . basename($profile['logo'])) ?>" alt="Company Logo">
</div>
<?php endif; ?>


    <form method="POST" enctype="multipart/form-data">
        <label>Company Name *</label>
        <input type="text" name="company_name" value="<?= htmlspecialchars($profile['company_name'] ?? '') ?>" required>

        <label>Description *</label>
        <textarea name="description" required><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>

       <label>Company Logo *</label>
<input type="file" name="logo" accept="image/*" required>

       <label>Location *</label>
<input type="text" name="location" value="<?= htmlspecialchars($profile['location'] ?? '') ?>" required>

<label>Website *</label>
<input type="url" name="website" value="<?= htmlspecialchars($profile['website'] ?? '') ?>" required>


        <button type="submit">💾 Save Profile</button>
    </form>
</div>
<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}

// ✅ Frontend validation for image upload
document.querySelector("form").addEventListener("submit", function(e) {
    const logoInput = document.querySelector('input[name="logo"]');
    if (!logoInput.files.length) {
        alert("Please upload your company logo (image file required).");
        e.preventDefault();
        return;
    }

    const file = logoInput.files[0];
    const validTypes = ["image/jpeg", "image/png", "image/gif", "image/webp"];
    if (!validTypes.includes(file.type)) {
        alert("Invalid file type. Please upload a valid image (JPG, PNG, GIF, WEBP).");
        e.preventDefault();
    }
});
</script>
</body>
</html>

