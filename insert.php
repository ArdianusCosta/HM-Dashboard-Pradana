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
        $user_id = $_SESSION['login_id'];
        $activity_type = 'event_add';
        $log_desc = 'Menambahkan event baru: ' . $title;
        
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at) VALUES (?, ?, NULL, ?, ?, NOW())");
        $log_stmt->bind_param("iiss", $user_id, $project_id, $activity_type, $log_desc);
        $log_stmt->execute();
        $log_stmt->close();

        echo "Event inserted successfully";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}