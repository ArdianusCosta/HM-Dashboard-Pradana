<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['login_id'])) {
    http_response_code(401);
    exit;
}

if (isset($_POST["title"])) {
    $title = $_POST["title"];
    $start = $_POST["start"];
    $end = $_POST["end"];
    $color = $_POST["color"];
    $description = $_POST["description"];
    $project_id = !empty($_POST["project_id"]) ? intval($_POST["project_id"]) : null;

    $stmt = $conn->prepare("INSERT INTO events (title, start_event, end_event, color, description, project_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $title, $start, $end, $color, $description, $project_id);

    if ($stmt->execute()) {
        echo "Event inserted successfully";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}