<?php
// export_ical.php - Secure export with project permissions
session_start();
include 'db_connect.php';

// Debug mode (optional)
$debug = isset($_GET['debug']) ? true : false;

// 1. Authentication check
if (!isset($_SESSION['login_id'])) {
    if ($debug) die('DEBUG: Unauthorized access.');
    header('HTTP/1.0 401 Unauthorized');
    exit('Unauthorized access.');
}

$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];

// --- Determine allowed projects based on role ---
$allowed_ids = [];
if ($login_type == 1) {
    // Admin: all projects
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

// --- Get and format date parameters ---
$start_date = $_GET['start'] ?? date('Y-m-d', strtotime('-1 month'));
$end_date   = $_GET['end']   ?? date('Y-m-d', strtotime('+1 month'));

if (strlen($start_date) === 10) $start_date .= ' 00:00:00';
if (strlen($end_date)   === 10) $end_date   .= ' 23:59:59';

if ($debug) {
    echo "<pre style='background:#f0f0f0; padding:20px; font-family:monospace;'>";
    echo "=== iCal EXPORT DEBUG ===\n";
    echo "User ID: $user_id\n";
    echo "Date Range: $start_date to $end_date\n";
    echo "Allowed projects: " . implode(', ', $allowed_ids) . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Timezone: " . date_default_timezone_get() . "\n\n";
}

// --- Build the filtered query ---
$query = "SELECT * FROM events WHERE start_event <= ? AND end_event >= ?";
$params = [$end_date, $start_date];
$types = "ss";

if (!empty($allowed_ids)) {
    $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
    $query .= " AND (project_id IN ($placeholders) OR project_id IS NULL)";
    foreach ($allowed_ids as $pid) {
        $params[] = $pid;
        $types .= "i";
    }
} else {
    $query .= " AND project_id IS NULL";
}
$query .= " ORDER BY start_event ASC";

if ($debug) {
    echo "SQL: $query\n";
    echo "Params: " . print_r($params, true) . "\n";
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    if ($debug) die("SQL Error: " . $conn->error);
    header('HTTP/1.0 500 Internal Server Error');
    exit('Database error.');
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$event_count = $result->num_rows;

if ($debug) {
    echo "\n=== QUERY RESULTS ===\n";
    echo "Events found: $event_count\n";
}

// 2. If no events, return appropriate message
if ($event_count === 0) {
    if ($debug) {
        echo "\n=== NO EVENTS FOUND ===\n";
        echo "No events in the selected range that user can see.\n";
        echo "</pre>";
        exit();
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "No events found in the selected date range.";
    exit();
}

// 3. Check if ical_uid column exists
$check_column = $conn->query("SHOW COLUMNS FROM events LIKE 'ical_uid'");
$has_ical_uid = $check_column && $check_column->num_rows > 0;

if ($debug && !$has_ical_uid) {
    echo "NOTE: 'ical_uid' column not found. UIDs will be generated from event ID.\n";
}

$update_stmt = null;
if ($has_ical_uid) {
    $update_stmt = $conn->prepare("UPDATE events SET ical_uid = ? WHERE id = ? AND (ical_uid IS NULL OR ical_uid = '')");
}

// 4. Output iCal headers
if (!$debug) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="haimoti_events_' . date('Y-m-d') . '.ics"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
}

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//HaiMoti//Calendar Export//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:HaiMoti Calendar\r\n";
echo "X-WR-CALDESC:Exported from HaiMoti Dashboard\r\n";
echo "X-WR-TIMEZONE:Asia/Jakarta\r\n";

$exported_count = 0;
while ($event = $result->fetch_assoc()) {
    $exported_count++;

    // Generate consistent UID
    if ($has_ical_uid && !empty($event['ical_uid'])) {
        $uid = $event['ical_uid'];
    } else {
        $uid = 'haimoti-event-' . $event['id'] . '@haimoti.com';
        if ($has_ical_uid && $update_stmt) {
            $update_stmt->bind_param("si", $uid, $event['id']);
            $update_stmt->execute();
        }
    }

    if ($debug) {
        echo "\n=== EVENT #$exported_count ===\n";
        echo "ID: {$event['id']}\n";
        echo "UID: $uid\n";
        echo "Title: '{$event['title']}'\n";
    }

    // Clean text
    $summary = $event['title'];
    $summary = str_replace(["\r", "\n", "\t"], ['', ' ', ' '], $summary);
    $summary = addcslashes($summary, ",\\;");

    $description = $event['description'] ?? '';
    if (!empty($description)) {
        $description = str_replace(["\r", "\n", "\t"], ['', ' ', ' '], strip_tags($description));
        $description = addcslashes($description, ",\\;");
    }

    try {
        $start_dt = new DateTime($event['start_event']);
        $end_dt   = new DateTime($event['end_event']);

        $start_str = $start_dt->format('Ymd\THis');
        $end_str   = $end_dt->format('Ymd\THis');
        $dtstamp   = gmdate('Ymd\THis\Z');
        $created   = date('Ymd\THis\Z', strtotime($event['start_event']));

        echo "BEGIN:VEVENT\r\n";
        echo "UID:$uid\r\n";
        echo "DTSTAMP:$dtstamp\r\n";
        echo "DTSTART:$start_str\r\n";
        echo "DTEND:$end_str\r\n";
        echo "SUMMARY:$summary\r\n";
        if (!empty($description)) {
            echo "DESCRIPTION:$description\r\n";
        }
        echo "CREATED:$created\r\n";
        echo "LAST-MODIFIED:$created\r\n";
        echo "SEQUENCE:0\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "BEGIN:VALARM\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:Reminder\r\n";
        echo "TRIGGER:-PT10M\r\n";
        echo "END:VALARM\r\n";
        echo "END:VEVENT\r\n";
    } catch (Exception $e) {
        if ($debug) {
            echo "ERROR processing event ID {$event['id']}: " . $e->getMessage() . "\n";
        }
        continue;
    }
}

echo "END:VCALENDAR";

if ($update_stmt) $update_stmt->close();
$stmt->close();
$conn->close();

if ($debug) {
    echo "\n\n=== EXPORT SUMMARY ===\n";
    echo "Total events exported: $exported_count\n";
    echo "File ready for download.\n";
    echo "</pre>";
    exit();
}

if (function_exists('error_log')) {
    error_log("iCal export: $exported_count events for user $user_id");
}
?>