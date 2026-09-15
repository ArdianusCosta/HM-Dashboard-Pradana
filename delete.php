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

    // Get event's project
    $res = $conn->query("SELECT project_id FROM events WHERE id = $id");
    if ($res->num_rows == 0) {
        echo "error";
        exit;
    }
    $project_id = $res->fetch_assoc()['project_id'];

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
        echo "success";
    } else {
        echo "error";
    }
}
?>