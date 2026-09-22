<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['login_id'])) {
    http_response_code(401);
    exit;
}

$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];
$start = $_GET['start'] ?? null;
$end   = $_GET['end'] ?? null;
$selected_project_encoded = $_GET['project_id'] ?? null;

// --- Determine allowed project IDs based on role ---
$allowed_ids = [];
if ($login_type == 1) {
    // Admin: see all projects – fetch ALL project IDs
    $res = $conn->query("SELECT id FROM project_list");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $allowed_ids[] = $row['id'];
        }
    }
} elseif ($login_type == 2) {
    $res = $conn->query("SELECT id FROM project_list WHERE manager_id = '$user_id' OR FIND_IN_SET('$user_id', user_ids)");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $allowed_ids[] = $row['id'];
        }
    }
} elseif ($login_type == 3 || $login_type == 4) {
    $res = $conn->query("SELECT id FROM project_list WHERE FIND_IN_SET('$user_id', user_ids)");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $allowed_ids[] = $row['id'];
        }
    }
}

// --- Build SQL with project filter ---
$sql = "SELECT e.*, p.name AS project_name 
        FROM events e 
        LEFT JOIN project_list p ON e.project_id = p.id 
        WHERE 1=1";
$params = [];
$types = "";

if ($login_type == 4) {
    if (empty($allowed_ids)) {
        echo json_encode([]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
    $sql .= " AND e.project_id IS NOT NULL AND e.project_id IN ($placeholders)";
    foreach ($allowed_ids as $pid) {
        $params[] = $pid;
        $types .= "i";
    }
} else {
    // Date range
    if ($start) {
        $sql .= " AND e.start_event >= ?";
        $params[] = $start;
        $types .= "s";
    }
    if ($end) {
        $sql .= " AND e.end_event <= ?";
        $params[] = $end;
        $types .= "s";
    }

    // Project filter
    if (!empty($selected_project_encoded)) {
        $project_id = decode_id($selected_project_encoded); // assume decode_id exists
        if (in_array($project_id, $allowed_ids)) {
            $sql .= " AND e.project_id = ?";
            $params[] = $project_id;
            $types .= "i";
        } else {
            // user not allowed to see this project → return nothing
            echo json_encode([]);
            exit;
        }
    } else {
        // "All Tasks" – events from any allowed project OR personal events (project_id IS NULL)
        if (!empty($allowed_ids)) {
            $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
            $sql .= " AND (e.project_id IN ($placeholders) OR e.project_id IS NULL)";
            foreach ($allowed_ids as $pid) {
                $params[] = $pid;
                $types .= "i";
            }
        } else {
            $sql .= " AND e.project_id IS NULL";
        }
    }
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $color = $row['color'];
    // Convert legacy bg-* to hex if needed
    if (strpos($color, 'bg-') === 0) {
        $map = [
            'bg-primary' => '#007bff',
            'bg-success' => '#28a745',
            'bg-danger'  => '#dc3545',
            'bg-warning' => '#ffc107',
            'bg-info'    => '#17a2b8'
        ];
        $color = $map[$color] ?? '#007bff';
    }

    $events[] = [
        'id'              => $row['id'],
        'title'           => $row['title'],
        'start'           => $row['start_event'],
        'end'             => $row['end_event'],
        'description'     => $row['description'],
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'textColor'       => '#fff',
        'type'            => 'custom',
        'project_id'      => $row['project_id'],
        'project_name'    => $row['project_name']
    ];
}

header('Content-Type: application/json');
echo json_encode($events);