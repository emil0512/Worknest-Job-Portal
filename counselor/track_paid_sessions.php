<?php
session_start();
include("../db.php");

// ✅ Check counselor login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: ../login.php");
    exit();
}

// 🔹 Ensure payment_transactions.appointment_id references appointments.id
// If missing, populate it based on jobseeker mapping (optional)
// $conn->query("UPDATE payment_transactions pt JOIN appointments a ON pt.user_id = a.jobseeker_id SET pt.appointment_id = a.id WHERE pt.appointment_id IS NULL");

$counselor_id = $_SESSION['user_id'];

$sql = "
SELECT 
    pt.id,
    pt.user_id,
    pt.role,
    pt.amount,
    pt.currency,
    pt.gateway,
    pt.txn_id,
    pt.created_at,
    a.status AS status,
    a.counselor_id
FROM payment_transactions pt
INNER JOIN appointments a 
    ON pt.appointment_id = a.id
WHERE a.counselor_id = ?
ORDER BY pt.created_at DESC
";


$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
// 🔹 Helper for status styling
function statusClass($status) {
    $map = [
        'success'  => 'success',
        'pending'  => 'pending',
        'failed'   => 'failed',
        'refunded' => 'refunded'
    ];
    return $map[strtolower($status)] ?? 'pending';
}

// 🔹 Helper to get user name
function getUserName($conn, $role, $userId) {
    if ($role === 'jobseeker') {
        $res = $conn->query("SELECT fullname FROM jobseeker_profiles WHERE js_id = " . intval($userId) . " LIMIT 1");
        if ($res && $res->num_rows > 0) return $res->fetch_assoc()['fullname'];
    } elseif ($role === 'employer') {
        $res = $conn->query("SELECT company_name FROM employer_profiles WHERE emp_id = " . intval($userId) . " LIMIT 1");
        if ($res && $res->num_rows > 0) return $res->fetch_assoc()['company_name'];
    }
    return "Unknown";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Transactions | WorkNest</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0a1f, #1a0033);
        color: #e0e0ff;
        margin: 0;
        padding: 30px;
    }

    h1 {
        text-align: center;
        color: #bb86fc;
        margin-bottom: 30px;
        font-size: 26px;
        text-shadow: 0 0 12px rgba(187,134,252,0.8);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        max-width: 1200px;
        margin: auto;
        background: rgba(20, 20, 40, 0.95);
        box-shadow: 0 0 25px rgba(138, 43, 226, 0.4);
        border-radius: 12px;
        overflow: hidden;
    }

    th, td {
        border: 1px solid rgba(187,134,252,0.3);
        padding: 14px;
        text-align: center;
        font-size: 14px;
        color: #e0e0ff;
    }

    th {
        background: linear-gradient(135deg, #5a189a, #7b2cbf);
        color: #fff;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    tr:nth-child(even) {
        background: rgba(138,43,226,0.15);
    }

    tr:hover {
        background: rgba(187,134,252,0.2);
        transition: 0.3s;
    }

    /* Status labels */
    .success { color: #00ffcc; font-weight: 600; text-shadow: 0 0 8px #00ffcc; }
    .pending { color: #ffcc00; font-weight: 600; text-shadow: 0 0 8px #ffcc00; }
    .failed { color: #ff4d6d; font-weight: 600; text-shadow: 0 0 8px #ff4d6d; }
    .refunded { color: #4dff91; font-weight: 600; text-shadow: 0 0 8px #4dff91; }


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

    @media screen and (max-width: 768px) {
        table, th, td { font-size: 12px; }
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
<h1>Payment Transactions</h1>

<table>
<thead>
<tr>
    <th>User Name</th>
    <th>Amount</th>
    <th>Currency</th>
    <th>Status</th> <!-- Appointment status -->
    <th>Transaction ID</th>
    <th>Gateway</th>
    <th>Payment Date</th>
</tr>
</thead>
<tbody>
<?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php $userName = getUserName($conn, $row['role'], $row['user_id']); ?>
        <tr>
            <td><?= htmlspecialchars($userName) ?></td>
            <td><?= number_format($row['amount'], 2) ?></td>
            <td><?= htmlspecialchars($row['currency']) ?></td>
            <td class="<?= statusClass($row['status'] ?? 'pending') ?>">
                <?= htmlspecialchars($row['status'] ?? 'pending') ?>
            </td>
            <td><?= htmlspecialchars($row['txn_id']) ?></td>
            <td><?= htmlspecialchars($row['gateway']) ?></td>
            <td><?= date("M j, Y h:i A", strtotime($row['created_at'])) ?></td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr><td colspan="7" style="text-align:center; padding:20px;">😔 No payment transactions found.</td></tr>
<?php endif; ?>
</tbody>
</table>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
}
</script>
</body>
</html>
