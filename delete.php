<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['login_id'])) {
    http_response_code(401);
    exit;
}

$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Get event's details
    $res = $conn->query("SELECT title, project_id FROM events WHERE id = $id");
    if (!$res || $res->num_rows == 0) {
        echo "error";
        exit;
    }
    $event_data = $res->fetch_assoc();
    $project_id = $event_data['project_id'];
    $event_title = $event_data['title'];

    // If not admin, check permissions
    if ($login_type != 1) {
        $allowed_ids = [];
        if ($login_type == 2) {
            $q = $conn->query("SELECT id FROM project_list WHERE manager_id = '$user_id' OR FIND_IN_SET('$user_id', user_ids)");
        } elseif ($login_type == 3 || $login_type == 4) {
            $q = $conn->query("SELECT id FROM project_list WHERE FIND_IN_SET('$user_id', user_ids)");
        } else {
            $q = false;
        }
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $allowed_ids[] = $row['id'];
            }
        }

        if ($project_id && !in_array($project_id, $allowed_ids)) {
            echo "error";
            exit;
        }
    }

    // Proceed with deletion
    $conn->query("DELETE FROM events WHERE id = $id");
    if ($conn->affected_rows > 0) {
        $activity_type = 'event_delete';
        $log_desc = 'Menghapus event: ' . $event_title;
        
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at) VALUES (?, ?, NULL, ?, ?, NOW())");
        $log_stmt->bind_param("iiss", $user_id, $project_id, $activity_type, $log_desc);
        $log_stmt->execute();
        $log_stmt->close();

        echo "success";
    } else {
        echo "error";
    }
}
?>