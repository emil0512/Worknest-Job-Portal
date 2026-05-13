<?php
session_start();
include("../db.php");

// ✅ Only allow logged-in jobseekers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$js_id = $_SESSION['user_id'];

// ✅ Update all unread messages for this jobseeker
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $js_id);
$stmt->execute();
$stmt->close();

// ✅ Mark shortlisted applications as notified
$stmt2 = $conn->prepare("UPDATE applications SET notified = 1 WHERE user_id = ? AND status = 'shortlisted' AND notified = 0");
$stmt2->bind_param("i", $js_id);
$stmt2->execute();
$stmt2->close();

// ✅ Return success
echo json_encode(['success' => true]);
