<?php
session_start();
include("../db.php");
include("../functions.php"); // for addNotification()


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);
$user_id = intval($_GET['user_id'] ?? 0);

// ✅ Step 1: Verify employer owns the job
$check = $conn->prepare("SELECT title FROM jobs WHERE id = ? AND emp_id = ?");
$check->bind_param("ii", $job_id, $emp_id);
$check->execute();
$job_result = $check->get_result();

if ($job_result->num_rows === 0) {
    die("❌ Unauthorized access.");
}

$job = $job_result->fetch_assoc();
$job_title = $job['title'];

// ✅ Step 2: Check if already shortlisted
$check2 = $conn->prepare("SELECT id FROM shortlisted_applicants WHERE job_id = ? AND user_id = ?");
$check2->bind_param("ii", $job_id, $user_id);
$check2->execute();
$res2 = $check2->get_result();

// ✅ Step 3: Insert into shortlist if not already
if ($res2->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO shortlisted_applicants (job_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
$stmt->close();

addNotification(
    $conn,
    $user_id, // the jobseeker being shortlisted
    "🎯 Your application for '{$job_title}' has been shortlisted by the employer!",
    "shortlist",
    "ats_dashboard.php" // link to jobseeker’s shortlisted page
);
}

// ✅ Step 5: Redirect back
header("Location: manage_applicants.php?job_id=$job_id&shortlisted=1");
exit();
?>
