<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "Feedback is currently disabled for all sessions.";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Feedback | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f0f8ff; padding: 40px; }
        .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #003366; }
        label { font-weight: bold; display: block; margin-top: 20px; }
        textarea, select {
            width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; margin-top: 6px;
        }
        button {
            margin-top: 20px;
            background: #007c91;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .msg { margin-top: 20px; padding: 10px; border-radius: 6px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        a.back { text-decoration: none; color: #007c91; font-weight: bold; display: inline-block; margin-top: 20px; }
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
<div class="container">
    <h2>📝 Session Feedback</h2>

    <?php if ($success_msg): ?>
        <div class="msg success"><?= $success_msg ?></div>
        <a class="back" href="my_sessions.php">← Back to My Sessions</a>
    <?php elseif ($error_msg): ?>
        <div class="msg error"><?= $error_msg ?></div>
        <a class="back" href="my_sessions.php">← Back</a>
    <?php else: ?>
        <form method="POST">
            <label for="rating">Rating (1 = Poor, 5 = Excellent)</label>
            <select name="rating" required>
                <option value="">-- Choose Rating --</option>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
            </select>

            <label for="comments">Comments (optional)</label>
            <textarea name="comments" rows="4" placeholder="Share your experience..."></textarea>

            <button type="submit">Submit Feedback</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
