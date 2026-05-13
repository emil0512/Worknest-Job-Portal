<?php include("db.php"); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Set New Password | WorkNest</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Satisfy&display=swap" rel="stylesheet">
</head>
<body class="login-bg">
<h1 class="portal-name">Reset Password</h1>
<div class="wrapper">
    <div class="container">
        <h2>Create New Password</h2>

        <?php
        if (!isset($_GET['token'])) {
            echo "<p class='error'>Invalid or missing token.</p>";
            exit();
        }

        $token = $_GET['token'];
       
        ?>

        <form method="POST">
            <input type="hidden" name="email" value="<?php echo $email; ?>">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit" name="reset_password">Update Password</button>
        </form>
<p class="switch"><a href="login.php">Back to Login</a></p>
        <?php
        if (isset($_POST['reset_password'])) {
            $pass = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];
            $email = $_POST['email'];

            if ($pass !== $confirm) {
                echo "<p class='error'>Passwords do not match.</p>";
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password='$hashed' WHERE email='$email'");
                echo "<p class='success'>Password updated! <a href='login.php'>Login now</a></p>";
            }
        }
        ?>
    </div>
</div>

</body>
</html>
