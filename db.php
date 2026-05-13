<?php
$host = "localhost";
$user = "root"; // default XAMPP user
$password = ""; // leave empty if using XAMPP default
$dbname = "job_portal_db";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
