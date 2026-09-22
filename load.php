<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['login_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$user_id = (int) ($_SESSION['login_id'] ?? 0);
$login_type = (int) ($_SESSION['login_type'] ?? 0);

$allowed_ids = [];
if ($login_type == 1) {
    $res = $conn->query("SELECT id FROM project_list");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $allowed_ids[] = (int) $row['id'];
        }
    }
} elseif ($login_type == 2) {
    $res = $conn->query("SELECT id FROM project_list WHERE manager_id = '$user_id' OR FIND_IN_SET('$user_id', user_ids)");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $allowed_ids[] = (int) $row['id'];
        }
    }
} elseif ($login_type == 3 || $login_type == 4) {
    $res = $conn->query("SELECT id FROM project_list WHERE FIND_IN_SET('$user_id', user_ids)");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $allowed_ids[] = (int) $row['id'];
        }
    }
}

$data = [];

if ($login_type == 4) {
    if (empty($allowed_ids)) {
        echo json_encode($data);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
    $query = "SELECT id, title, start_event, end_event, color, description, project_id FROM events WHERE project_id IS NOT NULL AND project_id IN ($placeholders) ORDER BY id";
    $stmt = $conn->prepare($query);
    $types = str_repeat('i', count($allowed_ids));
    $stmt->bind_param($types, ...$allowed_ids);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    if (!empty($allowed_ids)) {
        $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
        $query = "SELECT id, title, start_event, end_event, color, description, project_id FROM events WHERE (project_id IN ($placeholders) OR project_id IS NULL) ORDER BY id";
        $stmt = $conn->prepare($query);
        $types = str_repeat('i', count($allowed_ids));
        $stmt->bind_param($types, ...$allowed_ids);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT id, title, start_event, end_event, color, description, project_id FROM events WHERE project_id IS NULL ORDER BY id";
        $result = mysqli_query($conn, $query);
    }
}

if (isset($result) && $result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = array(
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'start' => $row['start_event'],
            'end' => $row['end_event'],
            'className' => [$row['color']],
            'description' => $row['description'],
            'project_id' => $row['project_id']
        );
    }
}

echo json_encode($data);
?>
