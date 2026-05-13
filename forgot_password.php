<?php include("db.php"); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password | WorkNest</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Satisfy&display=swap" rel="stylesheet">
</head>
<body class="login-bg">
<h1 class="portal-name">Forgot Password</h1>
<div class="wrapper">
    <div class="container">
        <h2>Reset Your Password</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="Enter your registered email" required>
            <button type="submit" name="reset">Send Reset Link</button>
        </form>
<p class="switch"><a href="login.php">Back to Login</a></p>
        <?php
        if (isset($_POST['reset'])) {
            $email = $_POST['email'];
            $result = $conn->query("SELECT * FROM users WHERE email='$email'");

            if ($result->num_rows > 0) {
                $token = bin2hex(random_bytes(50));
                $link = "http://localhost/job_portal/reset_password.php?token=$token";
                echo "<p class='success'>Reset link: <a href='$link'>$link</a></p>";
            } else {
                echo "<p class='error'>No account found with that email.</p>";
            }
        }
        ?>
    </div>
</div>

</body>
</html>
