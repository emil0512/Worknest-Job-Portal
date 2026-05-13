<?php
session_start();
require_once "../db.php";

// ----------------------------
// Detect logged-in jobseeker ID
// ----------------------------
$jobseeker_id = $_SESSION['jobseeker_id'] ?? $_SESSION['js_id'] ?? $_SESSION['user_id'] ?? 0;
$not_logged_in = ($jobseeker_id == 0);

// ----------------------------
// STEP 1: Booking Request from appointment.php
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $counselor_id = $_POST['counselor_id'] ?? null;
    $appointment_date = $_POST['appointment_date'] ?? null;
    $notes = $_POST['notes'] ?? '';

    if (!$counselor_id || !$appointment_date) die("Invalid booking request.");
    if ($not_logged_in) die("Please log in to continue with payment.");

    $_SESSION['pending_booking'] = [
        'jobseeker_id' => $jobseeker_id,
        'counselor_id' => $counselor_id,
        'appointment_date' => $appointment_date,
        'notes' => $notes
    ];

    header("Location: payment.php");
    exit;
}

// ----------------------------
// STEP 2: Payment Success Callback
// ----------------------------
if (isset($_GET['payment_status']) && $_GET['payment_status'] === 'success') {
    if (empty($_SESSION['pending_booking'])) die("No booking found to confirm.");

    $booking = $_SESSION['pending_booking'];
    $datetime = $booking['appointment_date'];
    $date = date('Y-m-d', strtotime($datetime));
    $time = date('H:i:s', strtotime($datetime));

    // Insert into appointments table
    $stmt = $conn->prepare("
        INSERT INTO appointments (jobseeker_id, counselor_id, date, time, notes, status)
        VALUES (?, ?, ?, ?, ?, 'Confirmed')
    ");
    $stmt->bind_param("iisss", $booking['jobseeker_id'], $booking['counselor_id'], $date, $time, $booking['notes']);
    if (!$stmt->execute()) die("Appointment booking failed: " . $stmt->error);
    $appointment_id = $stmt->insert_id;
    $stmt->close();

// ✅ Fetch Jobseeker Name
$js_stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
$js_stmt->bind_param("i", $booking['jobseeker_id']);
$js_stmt->execute();
$js_result = $js_stmt->get_result();
$jobseeker_name = $js_result->fetch_assoc()['username'] ?? "Jobseeker";
$js_stmt->close();

// ✅ Fetch Counselor Name
$c_stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
$c_stmt->bind_param("i", $booking['counselor_id']);
$c_stmt->execute();
$c_result = $c_stmt->get_result();
$counselor_name = $c_result->fetch_assoc()['username'] ?? "Counselor";
$c_stmt->close();

// ✅ Format Date & Time nicely
$formatted_date = date("F d, Y", strtotime($date));   // e.g. September 12, 2025
$formatted_time = date("h:i A", strtotime($time));    // e.g. 02:56 PM

// ✅ Build message with actual values
$subject = "Your Counseling Session is Confirmed";
$message = "
Dear {$jobseeker_name},
Your counseling session has been successfully booked.
📅Date: {$formatted_date}
⏰Time: {$formatted_time}
🧑‍💼Counselor: {$counselor_name}
Please be available on time. For any further details, contact your counselor.
Thank you!
";

$now = date("Y-m-d H:i:s");

$msg_stmt = $conn->prepare("
    INSERT INTO messages (sender_id, receiver_id, subject, message, sent_at, is_read, seen_by_jobseeker)
    VALUES (?, ?, ?, ?, ?, 0, 0)
");
$msg_stmt->bind_param("iisss", $booking['counselor_id'], $booking['jobseeker_id'], $subject, $message, $now);
$msg_stmt->execute();
$msg_stmt->close();

    // Insert payment transaction
    $amount = 250.00;
    $currency = "INR";
    $purpose = "Counseling Session Payment";
    $status = "success";
    $gateway = "razorpay";
    $txn_id = "TXN" . time();

    $payment_stmt = $conn->prepare("
        INSERT INTO payment_transactions 
        (user_id, role, amount, currency, purpose, status, gateway, txn_id, created_at)
        VALUES (?, 'jobseeker', ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$payment_stmt) die("Payment prepare failed: " . $conn->error);
    $payment_stmt->bind_param("idsssss", $booking['jobseeker_id'], $amount, $currency, $purpose, $status, $gateway, $txn_id);
    if (!$payment_stmt->execute()) die("Payment insert failed: " . $payment_stmt->error);
    $payment_id = $payment_stmt->insert_id;
    $payment_stmt->close();

// Update appointment_id to match payment_transactions.id
$update_stmt = $conn->prepare("
    UPDATE payment_transactions 
    SET appointment_id = ? 
    WHERE id = ?
");
$update_stmt->bind_param("ii", $payment_id, $payment_id);
$update_stmt->execute();
$update_stmt->close();

    // Assign counselor if not already assigned
    $stmt = $conn->prepare("SELECT id FROM counselor_assignments WHERE jobseeker_id = ? AND counselor_id = ?");
    $stmt->bind_param("ii", $booking['jobseeker_id'], $booking['counselor_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $assign_stmt = $conn->prepare("
            INSERT INTO counselor_assignments (jobseeker_id, counselor_id, assigned_at)
            VALUES (?, ?, NOW())
        ");
        $assign_stmt->bind_param("ii", $booking['jobseeker_id'], $booking['counselor_id']);
        $assign_stmt->execute();
        $assign_stmt->close();
    }
    $stmt->close();

    unset($_SESSION['pending_booking']);

    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Payment Successful</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
      body { font-family: "Poppins", sans-serif; margin:0; padding:0; background: radial-gradient(circle at top left, #0f0c29, #302b63, #24243e); display:flex; justify-content:center; align-items:center; height:100vh; }
      .success-box { background: rgba(26,26,64,0.9); padding:40px 60px; border-radius:12px; text-align:center; box-shadow: 0 0 20px rgba(159,122,255,0.5); color:#e0e0ff; max-width:500px; width:100%; }
      h2 { color:#b29bff; font-size:28px; margin-bottom:15px; text-shadow:0 0 10px #7a5cf4; }
      p { color:#e0e0ff; font-size:16px; margin-bottom:20px; }
      .txn { font-weight:bold; color:#fff; background:#302b63; padding:10px; border-radius:8px; display:inline-block; margin-bottom:25px; box-shadow: 0 0 12px rgba(159,122,255,0.4); }
      .btn { display:inline-block; text-decoration:none; background: linear-gradient(90deg, #6a11cb, #2575fc); color:#fff; padding:12px 20px; border-radius:8px; font-weight:600; transition:0.3s; margin:10px; box-shadow:0 0 12px rgba(106,17,203,0.6); }
      .btn:hover { background: linear-gradient(90deg, #2575fc, #6a11cb); box-shadow:0 0 16px rgba(159,122,255,0.8); }
    </style>
    </head>
    <body>
      <div class="success-box">
        <h2>✅ Payment Successful!</h2>
        <p>Your appointment has been booked successfully.</p>
        <p class="txn">Transaction ID: '.$txn_id.'</p>
        <div>
          <a href="dashboard.php" class="btn">🏠 Back to Dashboard</a>
          <a href="my_sessions.php" class="btn">📅 View Sessions</a>
        </div>
      </div>
    </body>
    </html>
    ';
    exit;
}

// ----------------------------
// STEP 3: Show Payment Page
// ----------------------------
if (empty($_SESSION['pending_booking'])) die("Invalid booking request.");
$booking = $_SESSION['pending_booking'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Payment Page</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
<style>
body { font-family:'Poppins', sans-serif; margin:0; background: radial-gradient(circle at top left, #0f0c29, #302b63, #24243e); color:#e0e0ff; }
.main { margin-left:260px; padding:40px 30px; max-width:700px; }
h2 { color:#b29bff; text-shadow:0 0 10px #7a5cf4; margin-bottom:20px; font-weight:600; }
p { font-size:16px; margin-bottom:12px; }
.warning-text { color:#ff6b6b; font-weight:bold; margin-bottom:20px; text-shadow:0 0 4px #ff4d4d; }
.payment-card { background: rgba(26,26,64,0.9); padding:25px 30px; border-radius:12px; box-shadow:0 0 15px rgba(159,122,255,0.4); margin-top:20px; }
.payment-card p strong { color:#b29bff; text-shadow:0 0 6px #7a5cf4; }
button { background: linear-gradient(90deg,#6a11cb,#2575fc); color:white; border:none; padding:12px 20px; border-radius:8px; font-weight:bold; cursor:pointer; width:100%; transition:0.3s; box-shadow:0 0 12px rgba(106,17,203,0.6); }
button:hover:not(:disabled) { background: linear-gradient(90deg,#2575fc,#6a11cb); box-shadow:0 0 16px rgba(159,122,255,0.8); }
button:disabled { background: rgba(127,92,255,0.5); cursor:not-allowed; }
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
<div class="main">
  <?php if ($not_logged_in): ?>
    <p class="warning-text">⚠ You are not logged in. Please log in to complete your booking.</p>
  <?php endif; ?>

  <h2>Proceed to Payment</h2>

  <div class="payment-card">
    <p><strong>Counselor ID:</strong> <?= htmlspecialchars($booking['counselor_id']) ?></p>
    <p><strong>Date & Time:</strong> <?= htmlspecialchars($booking['appointment_date']) ?></p>
    <p><strong>Amount to Pay:</strong> ₹250</p>

    <?php if(!empty($booking['notes'])): ?>
      <p><strong>Notes:</strong></p>
      <div class="notes-box"><?= htmlspecialchars($booking['notes']) ?></div>
    <?php endif; ?>

    <form action="payment.php" method="GET">
      <input type="hidden" name="payment_status" value="success">
      <button type="submit" <?= $not_logged_in ? 'disabled title="Please log in first."' : '' ?>>Pay & Confirm Booking</button>
    </form>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
