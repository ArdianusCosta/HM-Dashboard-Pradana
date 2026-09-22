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
    $title = $_POST['title'];
    $color = $_POST['color'];
    $start = $_POST['start'];
    $end = $_POST['end'];
    $description = $_POST['description'];
    $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;

    // --- Permission check: user must have access to the event's project ---
    // First, get current project_id of the event
    $res = $conn->query("SELECT project_id FROM events WHERE id = $id");
    if ($res->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Event not found']);
        exit;
    }
    $old_project_id = $res->fetch_assoc()['project_id'];

    // If admin, skip all project checks
    if ($login_type != 1) {
        // Determine allowed projects for non-admin users
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

        // If event has a project, user must have access to that project
        if ($old_project_id && !in_array($old_project_id, $allowed_ids)) {
            echo json_encode(['status' => 'error', 'message' => 'You cannot edit this event']);
            exit;
        }
        // Also, if new project_id is set, it must be allowed
        if ($project_id && !in_array($project_id, $allowed_ids)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid project']);
            exit;
        }
    }

    // Proceed with update
    $stmt = $conn->prepare("UPDATE events SET title=?, start_event=?, end_event=?, color=?, description=?, project_id=? WHERE id=?");
    $stmt->bind_param("sssssii", $title, $start, $end, $color, $description, $project_id, $id);

    if ($stmt->execute()) {
        $user_id = $_SESSION['login_id'];
        $activity_type = 'event_update';
        $log_desc = 'Mengupdate event: ' . $title;
        
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at) VALUES (?, ?, NULL, ?, ?, NOW())");
        $log_stmt->bind_param("iiss", $user_id, $project_id, $activity_type, $log_desc);
        $log_stmt->execute();
        $log_stmt->close();

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
}
?>