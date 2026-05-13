<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);

// Validate and delete job if it belongs to this employer
$stmt = $conn->prepare("DELETE FROM jobs WHERE id = ? AND emp_id = ?");
$stmt->bind_param("ii", $job_id, $emp_id);

if ($stmt->execute()) {
    header("Location: view_jobs.php?deleted=1");
} else {
    echo "<p style='padding:20px; color:red;'>❌ Failed to delete the job.</p>";
}
$stmt->close();
?>
