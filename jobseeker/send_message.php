<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$sender_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'], $_POST['message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message']);

    if ($receiver_id > 0 && !empty($message)) {
        // Check if both sender and receiver exist in users table
        $check_user = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
        $check_user->bind_param("i", $receiver_id);
        $check_user->execute();
        $check_user->bind_result($count);
        $check_user->fetch();
        $check_user->close();

        if ($count > 0) {
            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Receiver does not exist in users table. Cannot send message.");
        }
    }
}

header("Location: messages.php?chat_with=" . intval($receiver_id));
exit();
