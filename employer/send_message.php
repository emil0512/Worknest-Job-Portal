<?php
session_start();
include("../db.php");

// ✅ Check if employer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ✅ Get job_id and user_id from GET
if (!isset($_GET['job_id'], $_GET['user_id']) || !is_numeric($_GET['job_id']) || !is_numeric($_GET['user_id'])) {
    die("Invalid request.");
}

$job_id = intval($_GET['job_id']);
$receiver_id = intval($_GET['user_id']);

// ✅ Fetch job title
$stmt = $conn->prepare("SELECT title FROM jobs WHERE id = ? AND emp_id = ?");
$stmt->bind_param("ii", $job_id, $emp_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    die("Unauthorized or invalid job.");
}
$stmt->bind_result($job_title);
$stmt->fetch();
$stmt->close();

// ✅ Fetch jobseeker info
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    die("Invalid jobseeker.");
}
$stmt->bind_result($js_username, $js_email);
$stmt->fetch();
$stmt->close();

// ✅ Handle form submission
$msg_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if ($subject !== '' && $message !== '') {
        $ins = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, job_id, subject, message, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->bind_param("iiiss", $emp_id, $receiver_id, $job_id, $subject, $message);
        $ins->execute();
        $ins->close();

        $msg_success = "Message sent successfully!";
    } else {
        $msg_success = "Subject and message cannot be empty.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Send Message | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
       body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0d0d1a, #1a0033);
            color: #e0e0ff;
        }

        /* Centered container */
        .container {
            max-width: 600px;
            margin: 60px auto;
            background: rgba(25, 0, 51, 0.85);
            padding: 40px;
            border-radius: 14px;
            box-shadow: 0 0 20px #5200ff99;
            border: 1px solid rgba(138,43,226,0.5);
        }

        h2 {
            color: #9b5cff;
            text-align: center;
            margin-bottom: 30px;
            font-size: 26px;
            text-shadow: 0 0 10px #9b5cff, 0 0 20px #00d4ff;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-weight: 600;
            color: #cfcfff;
        }

        input, textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #7b2ff7;
            background: rgba(30, 0, 51, 0.7);
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 0 8px #5200ff44 inset;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 12px #00d4ff inset;
            background: rgba(20,0,40,0.9);
        }

        button {
            background: #7b2ff7;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 0 12px #7b2ff7;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        button:hover {
            background: #5200ff;
            box-shadow: 0 0 20px #00d4ff, 0 0 10px #5200ff inset;
        }

        .msg-success {
            text-align: center;
            background: rgba(0, 255, 128, 0.15);
            border: 1px solid #00ff99;
            padding: 12px;
            border-radius: 8px;
            color: #00ff99;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 0 0 6px #00ff99;
        }

        a.back-btn {
            display: inline-block;
            margin-top: 20px;
            color: #00d4ff;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        a.back-btn:hover {
            text-decoration: underline;
            text-shadow: 0 0 6px #00d4ff;
        }

        /* Disabled inputs (Job & Recipient) */
        input[disabled] {
            color: #ccc;
            background: rgba(20,0,40,0.6);
            border: 1px solid #5200ff44;
            box-shadow: 0 0 6px #5200ff33 inset;
        }  </style>
</head>
<body>

<div class="container">
    <h2>Send Message to <?php echo htmlspecialchars($js_username); ?></h2>

    <?php if($msg_success): ?>
        <div class="msg-success"><?php echo htmlspecialchars($msg_success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Job</label>
        <input type="text" value="<?php echo htmlspecialchars($job_title); ?>" disabled>

        <label>Recipient</label>
        <input type="text" value="<?php echo htmlspecialchars($js_username . " (" . $js_email . ")"); ?>" disabled>

        <label>Subject</label>
        <input type="text" name="subject" required>

        <label>Message</label>
        <textarea name="message" rows="5" required></textarea>

        <button type="submit">Send Message</button>
    </form>

    <a class="back-btn" href="select_job_manage.php?job_id=<?php echo $job_id; ?>">← Back to Applicants</a>
</div>

</body>
</html>
