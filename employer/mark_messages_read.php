<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') exit;

$emp_id = intval($_SESSION['user_id']);

// Update all unseen messages to seen
$stmt = $conn->prepare("UPDATE messages SET seen_by_employer = 1 WHERE receiver_id = ? AND seen_by_employer = 0");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$stmt->close();
?>
