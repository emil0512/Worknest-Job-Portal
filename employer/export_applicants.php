<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$emp_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);

// Validate job ownership
$checkJob = $conn->query("SELECT title FROM jobs WHERE id = $job_id AND emp_id = $emp_id");
if (!$checkJob || $checkJob->num_rows === 0) {
    die("Unauthorized or invalid job.");
}
$jobTitle = $checkJob->fetch_assoc()['title'];

// Fetch applicants
$applicants = $conn->query("
    SELECT u.username, u.email, a.match_score,
           CASE WHEN sa.user_id IS NOT NULL THEN 'Yes' ELSE 'No' END AS shortlisted
    FROM applications a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN shortlisted_applicants sa ON sa.user_id = a.user_id AND sa.job_id = a.job_id
    WHERE a.job_id = $job_id
");

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=applicants_job_{$job_id}.csv");

$output = fopen("php://output", "w");
fputcsv($output, ['Username', 'Email', 'Match Score', 'Shortlisted']);

while ($row = $applicants->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
