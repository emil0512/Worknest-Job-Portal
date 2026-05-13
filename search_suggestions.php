<?php
include("db.php");

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$suggestions = [];

if ($keyword != '') {
    $keywordNoSpace = str_replace(' ', '', $keyword);

    // Job titles
    $stmt = $conn->prepare("
        SELECT DISTINCT title 
        FROM jobs 
        WHERE REPLACE(title, ' ', '') LIKE CONCAT('%', ?, '%')
        LIMIT 5
    ");
    $stmt->bind_param("s", $keywordNoSpace);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['title'];
    }
    $stmt->close();

    // Optional: add skills if your table has 'skills'
    /*
    $stmt = $conn->prepare("
        SELECT DISTINCT skills 
        FROM jobs 
        WHERE REPLACE(skills, ' ', '') LIKE CONCAT('%', ?, '%')
        LIMIT 5
    ");
    $stmt->bind_param("s", $keywordNoSpace);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['skills'];
    }
    $stmt->close();
    */
}

echo json_encode($suggestions);
?>
