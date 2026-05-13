<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);

// Make sure the job belongs to the employer
$check = $conn->query("SELECT * FROM jobs WHERE id = $job_id AND emp_id = $emp_id");
if ($check && $check->num_rows > 0) {
    // Update job status to 'closed'
    $conn->query("UPDATE jobs SET status = 'closed' WHERE id = $job_id");
    header("Location: view_jobs.php?closed=1");
    exit();
} else {
    echo "<p style='color:red; padding:20px;'>❌ Unauthorized or invalid job ID.</p>";
}
?>
