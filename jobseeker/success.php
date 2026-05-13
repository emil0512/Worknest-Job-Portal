<?php
session_start();
require_once "../db.php";

$payment_id = $_GET['payment_id'] ?? '';
if (empty($payment_id)) {
    die("Payment failed or cancelled.");
}

if (!isset($_SESSION['booking_details'])) {
    die("No booking details found.");
}

$booking = $_SESSION['booking_details'];
unset($_SESSION['booking_details']); // Clear after use

// Store in appointments table
$stmt = $conn->prepare("INSERT INTO appointments (jobseeker_id, counselor_id, appointment_date, payment_id, status) VALUES (?, ?, ?, ?, 'confirmed')");
$stmt->bind_param("iiss", $booking['jobseeker_id'], $booking['counselor_id'], $booking['appointment_date'], $payment_id);
$stmt->execute();

// Create counselor assignment
$stmt2 = $conn->prepare("INSERT INTO counselor_assignments (jobseeker_id, counselor_id) VALUES (?, ?)");
$stmt2->bind_param("ii", $booking['jobseeker_id'], $booking['counselor_id']);
$stmt2->execute();

echo "<h2>✅ Payment Successful!</h2>";
echo "<p>Your appointment is booked on " . htmlspecialchars($booking['appointment_date']) . "</p>";
echo "<p>Payment ID: " . htmlspecialchars($payment_id) . "</p>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Payment Successful</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
<style>
  body {
    font-family: 'Poppins', sans-serif;
    background: #f0f8ff;
    margin: 0;
    padding: 0;
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

  .container {
    max-width: 600px;
    margin: 100px auto;
    background: rgba(28,28,51,0.95);
    padding: 40px 30px;
    border-radius: 12px;
    box-shadow: 0 0 15px #6c63ff22;
    backdrop-filter: blur(6px);
    color: #e0e0ff;
    text-align: center;
  }
  h2 {
    font-size: 28px;
    color: #2a7a2a;
    margin-bottom: 20px;
    text-shadow: 0 0 6px #6c63ff33;
  }
  p {
    font-size: 18px;
    margin: 10px 0;
  }
  a.button {
    display: inline-block;
    margin-top: 30px;
    padding: 12px 24px;
    background: #007c91;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: background-color 0.3s ease;
  }
  a.button:hover {
    background: #005f67;
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
<h2>✅ Payment Successful!</h2>
<p>Your appointment is booked on <strong><?= htmlspecialchars($booking['appointment_date']) ?></strong></p>
<p>Payment ID: <strong><?= htmlspecialchars($payment_id) ?></strong></p>

<a href="dashboard.php" class="button">Go to Dashboard</a>
<script>
  // Redirect after 3 seconds
  setTimeout(function() {
    window.location.href = "my_sessions.php";
  }, 3000);
</script>

</body>
</html>
