<?php
session_start();
include("db.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | WorkNest</title>
  <link rel="stylesheet" href="css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Satisfy&display=swap" rel="stylesheet">
</head>
<body class="login-bg">

<!-- 🔙 Back Button (inline styled) -->
<a href="index.php" 
   style="position: absolute; top: 15px; left: 20px; 
          background-color: #4CAF50; color: white; 
          padding: 8px 16px; text-decoration: none; 
          border-radius: 6px; font-size: 14px; 
          font-weight: bold; font-family: Arial, sans-serif;
          transition: background-color 0.3s;">
    ← Back
</a>

<h1 class="portal-name">Login</h1>

<div class="wrapper">

  <div class="container">
    <h2>Welcome Back</h2>

    <form method="POST">
      <input type="email" name="email" placeholder="Email Address" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" name="login">Login</button>
      <p class="switch">Don’t have an account? <a href="register.php">Register here</a></p>
    </form>

    <p class="switch"><a href="forgot_password.php">Forgot Password?</a></p>

    <?php
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        // ✅ Admin bypass login
        if ($email === "admin@worknest.com" && $password === "admin123") {
            $_SESSION['user_id'] = 0; // Arbitrary value for admin
            $_SESSION['username'] = "Admin";
            $_SESSION['role'] = "admin";

            header("Location: admin/dashboard.php");
            exit();
        }

        // ✅ Regular user login via database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $hashedPassword = $row['password'];

            if (password_verify($password, $hashedPassword)) {
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['username'] = $row['username'];

    // ✅ Log login activity
    $user_id = $row['user_id'];
    $email = $row['email'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $conn->query("INSERT INTO login_activity (user_id, email, login_time, ip_address) VALUES ($user_id, '$email', NOW(), '$ip_address')");

    switch ($row['role']) {
        case 'jobseeker':
            header("Location: jobseeker/dashboard.php");
            break;
        case 'employer':
            header("Location: employer/dashboard.php");
            break;
        case 'counselor':
            header("Location: counselor/dashboard.php");
            break;
    }
    exit();
}
 else {
                echo "<p class='error'>❌ Incorrect password. Please try again.</p>";
            }
        } else {
            echo "<p class='error'>⚠️ No account found with this email. <a href='register.php'>Register now</a></p>";
        }
    }
    ?>
  </div>
</div>

</body>
</html>


