<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    $job_id = intval($_POST['job_id']);

    // Check if the job is already saved
    $check_stmt = $conn->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $check_stmt->bind_param("ii", $js_id, $job_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        // Job already saved → unsave it
        $stmt = $conn->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
        $stmt->bind_param("ii", $js_id, $job_id);
    } else {
        // Job not saved → save it
        $stmt = $conn->prepare("INSERT INTO saved_jobs (user_id, job_id, saved_on) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $js_id, $job_id);
    }

    $check_stmt->close();

    if ($stmt->execute()) {
        header("Location: saved_jobs.php");
        exit();
    } else {
        echo "Error processing request.";
    }
} else {
    echo "Invalid request.";
}
?>
