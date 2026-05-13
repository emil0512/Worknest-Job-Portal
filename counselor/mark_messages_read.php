<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'counselor') {
    http_response_code(403);
    exit();
}

$counselor_id = $_SESSION['user_id'];

// Mark all unread messages as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$stmt->close();
$conn->close();

echo "OK";
