<?php
session_start();
include("db.php");

// === HANDLE REGISTRATION FORM SUBMISSION ===
if (isset($_POST['register'])) {
    $raw_username = $_POST['username'] ?? '';
    $email        = $_POST['email'] ?? '';
    $role         = $_POST['role'] ?? '';
    $password     = $_POST['password'] ?? '';

    // Quick injection check
    if (preg_match('/\b(OR|AND|SELECT|UNION|INSERT|DELETE|UPDATE|DROP|TRUNCATE|--)\b/i', $raw_username)
        || preg_match('/[;=]/', $raw_username)) {
        $error_msg = "❌ Full name contains invalid or suspicious text.";
    } else {
        $username = preg_replace("/[^\p{L}\s\.\-']/u", "", $raw_username);
        if ($username === '' || strlen($username) === 0) $error_msg = "❌ Full name is invalid.";
        elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) $error_msg = "❌ Full name must be 3-50 chars.";
        elseif (!preg_match("/^[\p{L}\s\.\-']+$/u", $username)) $error_msg = "❌ Full name contains invalid characters.";
       elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_msg = "❌ Invalid email format.";
}
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_msg = "❌ Invalid email format.";
}
elseif (!preg_match('/^[^@]+@([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $email)) {
    $error_msg = "❌ Invalid email domain structure.";
}
else {
    // Domain typo detection
    $valid_domains = ["gmail.com", "yahoo.com", "hotmail.com", "outlook.com", "live.com", "icloud.com"];

    $domain = strtolower(explode("@", $email)[1] ?? "");

    if (!in_array($domain, $valid_domains)) {

        $closest = null;
        $shortest = 4; 

        foreach ($valid_domains as $d) {
            $lev = levenshtein($domain, $d);
            if ($lev < $shortest) {
                $closest = $d;
                $shortest = $lev;
            }
        }

        if ($closest !== null) {
            $error_msg = "⚠️ Did you mean '$closest'? Check your email domain.";
        } else {
            $error_msg = "❌ Invalid or uncommon email domain.";
        }
    }
}
}

// Role & password validation continues normally
if (!isset($error_msg)) {

    if (empty($role)) {
        $error_msg = "❌ Please select a role.";
    }
    elseif (strlen($password) < 8) {
        $error_msg = "❌ Password must be at least 8 chars.";
    }

        else {
            $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();
            if ($result->num_rows > 0) {
                $error_msg = "⚠️ An account with this email already exists.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $email, $hashed, $role);
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;

                    // Role-specific table insert
                    if ($role == "jobseeker") {
                        $profileStmt = $conn->prepare("INSERT INTO jobseeker_profiles (js_id, fullname) VALUES (?, ?)");
                        $profileStmt->bind_param("is", $user_id, $username);
                        $profileStmt->execute();
                    } elseif ($role == "employer") {
                        $empProfileStmt = $conn->prepare("INSERT INTO employer_profiles (emp_id, company_name) VALUES (?, ?)");
                        $company_name = $username;
                        $empProfileStmt->bind_param("is", $user_id, $company_name);
                        $empProfileStmt->execute();
                    } elseif ($role == "counselor") {
                        $counselorStmt = $conn->prepare("INSERT INTO counselor_profiles (counselor_id, name) VALUES (?, ?)");
                        $counselorStmt->bind_param("is", $user_id, $username);
                        $counselorStmt->execute();
                    }

                    // Store pending user & OTP
                    $_SESSION['pending_user'] = [
                        "id" => $user_id,
                        "role" => $role,
                        "username" => $username
                    ];
                    $_SESSION['otp'] = rand(100000, 999999);
                    $_SESSION['show_otp'] = true;
                    header("Location: register.php");
                    exit();
                } else {
                    $error_msg = "❌ Database error: " . $stmt->error;
                }
            }
        }
    }
}

// === HANDLE DEV MODE ADMIN CHECK ===
if (isset($_POST['dev_check'])) {
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';
    if ($admin_email === 'admin@worknest.com' && $admin_pass === 'admin123') {
        $_SESSION['dev_verified'] = true;
    } else {
        $dev_error = "❌ Invalid admin credentials";
    }
}

// === HANDLE OTP VERIFICATION ===
if (isset($_POST['verify_otp'])) {
    $entered = $_POST['otp_input'] ?? '';
    if ($entered == $_SESSION['otp']) {
        $_SESSION['user_id'] = $_SESSION['pending_user']['id'];
        $_SESSION['role'] = $_SESSION['pending_user']['role'];
        $_SESSION['username'] = $_SESSION['pending_user']['username'];
        unset($_SESSION['pending_user'], $_SESSION['otp'], $_SESSION['show_otp'], $_SESSION['dev_verified']);

        switch ($_SESSION['role']) {
            case 'admin':     header("Location: /job_portal/admin/dashboard.php"); break;
            case 'jobseeker': header("Location: /job_portal/jobseeker/dashboard.php"); break;
            case 'employer':  header("Location: /job_portal/employer/dashboard.php"); break;
            case 'counselor': header("Location: /job_portal/counselor/dashboard.php"); break;
        }
        exit();
    } else {
        $otp_error = "❌ Incorrect OTP";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register | WorkNest</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Satisfy&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .role-btn.selected { background:#4CAF50; color:white; }
        .error-msg {color:red; margin:5px 0;}
        #devFields { display:none; margin-top:10px; }
    </style>
</head>
<body class="login-bg">

<a href="index.php" style="position:absolute;top:15px;left:20px;background-color:#4CAF50;color:white;padding:8px 16px;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold;font-family:Arial,sans-serif;">← Back</a>

<div class="wrapper">
    <h1 class="portal-name">Sign Up</h1>

    <div class="container">

        <?php if (!isset($_SESSION['show_otp'])): ?>
            <h2>Create Your Account</h2>
            <?php if(isset($error_msg)) echo "<p class='error-msg'>$error_msg</p>"; ?>

            <form method="POST" id="registerForm">
                <input type="text" name="username" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Password" required>

                <label class="role-label">👤 Choose your role:</label>
                <div class="role-buttons">
                    <input type="hidden" name="role" id="role" required>
                    <button type="button" class="role-btn" data-role="jobseeker">Job Seeker</button>
                    <button type="button" class="role-btn" data-role="employer">Employer</button>
                    <button type="button" class="role-btn" data-role="counselor">Career Counselor</button>
                </div>

                <button type="submit" name="register">Register</button>
                <p class="switch">Already have an account? <a href="login.php">Login here</a></p>
            </form>

        <?php else: ?>
            <h2>Email Verification</h2>
<?php if(isset($otp_error)) echo "<p class='error-msg'>$otp_error</p>"; ?>

<!-- OTP Verification directly -->
<form method="POST" style="margin-bottom:15px;">
    <input type="text" name="otp_input" placeholder="Enter OTP" required style="padding:10px;width:90%;margin-bottom:10px;">
    <button type="submit" name="verify_otp" style="padding:10px 20px;background:#4CAF50;color:white;border:none;border-radius:6px;">Verify OTP</button>
</form>

<!-- Development Mode Button -->
<button id="devBtn" style="padding:8px 16px;margin-bottom:10px;background:#f39c12;color:white;border:none;border-radius:6px;">Development Mode</button>

<div id="devFields">
    <?php if(isset($dev_error)) echo "<p class='error-msg'>$dev_error</p>"; ?>
    <form method="POST">
        <input type="email" name="admin_email" placeholder="Admin Email" required style="margin-bottom:5px;">
        <input type="password" name="admin_pass" placeholder="Admin Password" required style="margin-bottom:10px;">
        <button type="submit" name="dev_check" style="padding:10px 20px; background:#4CAF50; color:white; border:none; border-radius:6px;">Verify Admin</button>
    </form>
</div>

<?php if(isset($_SESSION['dev_verified']) && $_SESSION['dev_verified']): ?>
    <p>OTP for testing: <span style="background:black;color:yellow;padding:5px;"><?php echo $_SESSION['otp']; ?></span></p>
<?php endif; ?>


        <?php endif; ?>
    </div>
</div>

<script>
    const buttons = document.querySelectorAll(".role-btn");
    const roleInput = document.getElementById("role");
    buttons.forEach(btn => {
        btn.addEventListener("click", () => {
            buttons.forEach(b => b.classList.remove("selected"));
            btn.classList.add("selected");
            roleInput.value = btn.dataset.role;
        });
    });
    document.getElementById("registerForm")?.addEventListener("submit", function(e) {
        if (!roleInput.value) { e.preventDefault(); alert("⚠️ Please select a role."); }
    });

    // Development Mode toggle
    const devBtn = document.getElementById("devBtn");
    const devFields = document.getElementById("devFields");
    devBtn.addEventListener("click", () => {
        devFields.style.display = devFields.style.display === "none" ? "block" : "none";
    });
</script>

</body>
</html>
