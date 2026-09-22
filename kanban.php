<?php
include 'db_connect.php';
session_start();

// --- Memastikan fungsi encode/decode ID tersedia ---
if (!function_exists('decode_id')) {
    die('Error: decode_id function is not available.');
}
if (!function_exists('encode_id')) {
    die('Error: encode_id function is not available.');
}

if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_type'])) {
    die('Unauthorized access.');
}

$current_user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];

$status_map_labels = [
    0 => 'Pending',       
    1 => 'Started',       
    2 => 'On Progress',   
    3 => 'Hold',          
    4 => 'Overdue',       
    5 => 'Done'           
];

// 1. --- Handle Delete Task (Activity Logging) - DECODE ID ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean(); // 🔴 PENTING: Bersihkan buffer output sebelum return JSON
    
    $id = decode_id($_POST['delete_task_id']); 
    
    if ($id === null || !is_numeric($id) || $id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid task ID provided for deletion or failed to decode.']);
        exit;
    }
    $id = intval($id);
    
    if ($login_type == 1 || $login_type == 2) {
        $task_to_delete_q = $conn->query("SELECT task, project_id FROM task_list WHERE id = $id");
        $task_to_delete = $task_to_delete_q->fetch_assoc();

        $delete = $conn->query("DELETE FROM task_list WHERE id = $id");

        if ($delete) {
            if ($task_to_delete) {
                $project_id_log = (int)$task_to_delete['project_id'];
                $task_name = $conn->real_escape_string($task_to_delete['task']);
                
                $activity_type = "TASK_DELETED";
                $description_log = "Menghapus tugas '{$task_name}' dari project ID: {$project_id_log}.";

                $log_q = $conn->prepare("
                    INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at) 
                    VALUES (?, ?, 0, ?, ?, NOW())
                ");
                $log_q->bind_param("iiss", $current_user_id, $project_id_log, $activity_type, $description_log);
                $log_q->execute();
            }
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized action']);
    }
    exit;
}

// 2. --- Update status drag & drop (Activity Logging) - DECODE ID ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean(); // 🔴 PENTING: Bersihkan buffer

    $id = decode_id($_POST['id']);
    $new_status = (int) $_POST['status'];

    if ($id === null) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid task ID.']);
        exit;
    }
    $id = intval($id);

    // Read old status BEFORE update — overdue block does NOT run on POST so this value is clean
    $old_task_q = $conn->query("SELECT task, project_id, status FROM task_list WHERE id = $id");
    $old_task = $old_task_q->fetch_assoc();
    $old_status = (int)$old_task['status'];

    $update_success = $conn->query("UPDATE task_list SET status = $new_status, date_updated = NOW() WHERE id = $id");

    if ($update_success && $old_status !== $new_status) {
        $project_id_log = (int)$old_task['project_id'];
        $task_id_log = $id;
        $task_name = $conn->real_escape_string($old_task['task']);

        $old_status_label = $status_map_labels[$old_status] ?? 'Unknown';
        $new_status_label = $status_map_labels[$new_status] ?? 'Unknown';

        $activity_type = "TASK_STATUS_UPDATE";
        $description_log = "Mengubah status tugas '{$task_name}' dari '{$old_status_label}' menjadi '{$new_status_label}' via Kanban.";

        $log_q = $conn->prepare("
            INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        // $current_user_id is correct here — this IS a real user action
        $log_q->bind_param("iiiss", $current_user_id, $project_id_log, $task_id_log, $activity_type, $description_log);
        $log_q->execute();
    }

    echo json_encode(['status' => 'success']);
    exit;
}

// 3. --- Logika Overdue Task (GET only — never runs during drag & drop POST) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $today_date = date('Y-m-d');
    $overdue_tasks_q = $conn->query("
        SELECT id, project_id, task 
        FROM task_list 
        WHERE DATE(end_date) < '{$today_date}'
        AND status NOT IN (0, 3, 5)
    ");

    $updated_tasks = [];
    if ($overdue_tasks_q) {
        while ($task = $overdue_tasks_q->fetch_assoc()) {
            $conn->query("UPDATE task_list SET status = 4, date_updated = NOW() WHERE id = {$task['id']}");

            if ($conn->affected_rows > 0) {
                $updated_tasks[] = [
                    'id' => $task['id'],
                    'project_id' => $task['project_id'],
                    'task' => $task['task']
                ];
            }
        }

        if (!empty($updated_tasks)) {
            $activity_type = "TASK_OVERDUE_AUTO";
            $new_status_label = $status_map_labels[4];

            foreach ($updated_tasks as $t) {
                $project_id_log = (int)$t['project_id'];
                $task_id_log = (int)$t['id'];
                $task_name = $conn->real_escape_string($t['task']);

                $description_log = "Tugas '{$task_name}' otomatis diubah menjadi '{$new_status_label}' karena Due Date terlampaui.";

                $log_q = $conn->prepare("
                    INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                // FIX: use 0 — no human triggered this, prevents showing a random member's name
                $system_user_id = 0;
                $log_q->bind_param("iiiss", $system_user_id, $project_id_log, $task_id_log, $activity_type, $description_log);
                $log_q->execute();
            }
        }
    }
}

// 4. --- Render Kanban Board (hanya jika tidak ada POST request) ---
$where = "";
if ($login_type == 2) {
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids) OR manager_id = $current_user_id";
} elseif ($login_type == 3 || $login_type == 4) {
    // employees and clients only see projects where they are a member
    $where = "WHERE FIND_IN_SET($current_user_id, user_ids)";
}

$projects = [];
$project_q = $conn->query("SELECT id, name FROM project_list $where ORDER BY name ASC");
while ($row = $project_q->fetch_assoc()) $projects[] = $row;
$allowed_project_ids = array_column($projects, 'id');

$encoded_project_id = $_GET['project_id'] ?? null;
$project_id = $encoded_project_id ? decode_id($encoded_project_id) : 0;

if (!in_array($project_id, $allowed_project_ids) && !empty($allowed_project_ids)) {
    $project_id = $allowed_project_ids[0];
    $encoded_project_id = encode_id($project_id);
} elseif (empty($allowed_project_ids)) {
    $project_id = 0;
    $encoded_project_id = null;
} else {
    $encoded_project_id = encode_id($project_id);
}

$status_map = [
    0 => ['label' => 'Pending', 'color' => '#6c757d'],       
    1 => ['label' => 'Started', 'color' => '#0dcaf0'],       
    2 => ['label' => 'On Progress', 'color' => '#0d6efd'],   
    3 => ['label' => 'Hold', 'color' => '#ffc107'],          
    4 => ['label' => 'Overdue', 'color' => '#dc3545'],       
    5 => ['label' => 'Done', 'color' => '#198754']           
];

$tasks = [0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
if ($project_id) {
    $query = $conn->query("
        SELECT t.*, p.name AS project_name, 
               GROUP_CONCAT(CONCAT(u.firstname,' ',u.lastname) SEPARATOR ', ') AS assigned_names,
               GROUP_CONCAT(u.avatar SEPARATOR ',') AS avatars
        FROM task_list t
        INNER JOIN project_list p ON t.project_id = p.id
        LEFT JOIN users u ON FIND_IN_SET(u.id, t.user_ids)
        WHERE t.project_id = $project_id
        GROUP BY t.id
        ORDER BY t.id ASC
    ");
    while ($row = $query->fetch_assoc()) {
        $row['encoded_id'] = encode_id($row['id']); 
        
        if (isset($tasks[$row['status']])) {
            $tasks[$row['status']][] = $row;
        } else {
             $tasks[0][] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Kanban Board - 6 Columns</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
body {
    background-color: #f4f5f7;
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #172b4d;
    height: 100vh;
    overflow-x: auto; 
    padding: 20px 0;
}
.container-fluid-custom {
    padding: 0 20px;
    min-width: 1840px; 
}
.filter-area {
    margin-bottom: 25px;
    padding-left: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.kanban-board-wrapper {
    display: flex;
    flex-wrap: nowrap;
    gap: 15px;
    padding-bottom: 10px;
    align-items: flex-start;
}
.kanban-column-container {
    flex: 0 0 300px; 
    max-height: 80vh;
}
.kanban-column {
    background: #ebecf0;
    border-radius: 8px;
    padding: 8px;
    min-height: 100px;
    max-height: 100%;
    overflow-y: auto;
    box-shadow: 0 1px 0 rgba(9, 30, 66, 0.25);
    transition: background 0.2s;
}
.kanban-column.drag-over {
    background: #dfe1e6;
}
.kanban-header {
    font-weight: 600;
    padding: 5px 10px 10px;
    font-size: 16px;
    color: #172b4d;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.task-count {
    background-color: rgba(9, 30, 66, 0.08);
    border-radius: 50%;
    padding: 2px 7px;
    font-size: 12px;
    font-weight: 500;
    color: #42526e;
}
.task-card {
    background: #fff;
    border-radius: 5px;
    padding: 10px 12px;
    margin-bottom: 8px;
    cursor: pointer;
    box-shadow: 0 1px 0 rgba(9, 30, 66, 0.25);
    transition: all 0.2s;
    position: relative;
}
.task-card:hover {
    background: #f4f5f7;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.task-card.dragging {
    opacity: 0.5;
    border: 2px dashed #0079bf;
}
.task-title {
    font-weight: 500;
    font-size: 14px;
    color: #172b4d;
    line-height: 1.3;
}
.task-desc {
    font-size: 12px;
    color: #5e6c84;
    margin-top: 5px;
}
.task-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
}
.task-avatars {
    display: flex;
}
.task-avatars img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid #fff;
    margin-left: -6px;
    object-fit: cover;
}
.task-avatars img:first-child {
    margin-left: 0;
}
.task-date {
    font-size: 11px;
    color: #5e6c84;
}
.delete-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    background: none;
    border: none;
    color: #dc3545;
    opacity: 0;
    transition: opacity 0.2s;
    padding: 2px 5px;
    font-size: 14px;
    line-height: 1;
}
.task-card:hover .delete-btn {
    opacity: 0.8;
}
.delete-btn:hover {
    opacity: 1;
}

/* --- UX Dropdown Project --- */
/* 1. Kotak Utama Dropdown */
.filter-area .dropdown-menu {
    border-radius: 12px !important;
    border: none !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
    padding: 10px !important;
    margin-top: 12px !important;
    max-height: 500px !important; 
    overflow-y: auto !important;
}

.filter-area .dropdown-menu li {
    list-style: none;
    margin: 0;
    padding: 0;
}

/* Custom Scrollbar agar tetap rapi */
.filter-area .dropdown-menu::-webkit-scrollbar {
    width: 6px;
}
.filter-area .dropdown-menu::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 10px;
}

/* 2. Styling Masing-masing Pilihan (Item) */
.filter-area .dropdown-item {
    border-radius: 8px !important;
    padding: 10px 15px !important;
    font-weight: 600 !important;
    color: #555 !important;
    transition: all 0.2s ease !important; 
}

/* 3. Saat Item Sedang Aktif / Dipilih */
.filter-area .dropdown-item.active, 
.filter-area .dropdown-item:active {
    background-color: #B75301 !important;
    color: #fff !important;
}

.filter-area .dropdown-item:hover:not(.active) {
    background-color: #FFF5EE !important;
    color: #B75301 !important;
    padding-left: 20px !important; 
}

#projectDropdown {
    border-radius: 10px !important; 
    padding: 10px 20px !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border: none !important;
    /* Shadow luar bawaan */
    box-shadow: 0 4px 14px rgba(183, 83, 1, 0.25) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: auto !important;
}

#projectDropdown:hover {
    transform: translateY(-2px);
    box-shadow: inset 0 0 0 100px rgba(0, 0, 0, 0.15), 
                0 6px 20px rgba(183, 83, 1, 0.35) !important;
    color: white !important;
}
</style>
</head>
<body>

<div class="container-fluid-custom">
    <div class="filter-area">
        <div class="dropdown">
            <button class="btn text-white dropdown-toggle" type="button" id="projectDropdown"
                data-toggle="dropdown" aria-expanded="false" style="background-color: #B75301 !important;">
                <i class="fas fa-clipboard"></i>
                <?= htmlspecialchars(array_column($projects, 'name', 'id')[$project_id] ?? 'Choose Project') ?>
            </button>
            <div class="dropdown-menu" aria-labelledby="projectDropdown">
                <?php foreach ($projects as $project): ?>
                    <li>
                        <a class="dropdown-item <?= $project_id == $project['id'] ? 'active' : '' ?>"
                           href="index.php?page=kanban&project_id=<?= encode_id($project['id']) ?>">
                            <?= htmlspecialchars($project['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($project_id): ?>
    <div class="kanban-board-wrapper">
        <?php foreach ($status_map as $status => $info): ?>
            <div class="kanban-column-container">
                <div class="kanban-header" style="color:<?= $info['color'] ?>;">
                    <span><?= $info['label'] ?></span>
                    <span class="task-count"><?= count($tasks[$status]) ?></span>
                </div>
                <div class="kanban-column" data-status="<?= $status ?>">
                    <?php foreach ($tasks[$status] as $task): ?>
                        <div class="task-card" data-id="<?= $task['encoded_id'] ?>" draggable="true">
                            <div class="task-title"><?= htmlspecialchars($task['task']) ?></div>
                            <?php if ($login_type == 1 || $login_type == 2): ?>
                                <button class="delete-btn" data-id="<?= $task['encoded_id'] ?>" title="Delete Task" onclick="event.stopPropagation(); deleteKanbanTask('<?= $task['encoded_id'] ?>')">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                                <div class="task-desc mt-1">
                                    <div class="task-desc mt-1">
                                        <?php 
                                            $clean_description = strip_tags(html_entity_decode($task['description'] ?? ''));
                                            $short_description = htmlspecialchars(substr($clean_description, 0, 80));
                                            echo $short_description;
                                            if (strlen($clean_description) > 80) {
                                                echo '...';
                                            }
                                        ?>
                                    </div>
                                </div>
                            <div class="task-footer">
                                <div class="task-avatars">
                                    <?php 
                                    $avatars = explode(',', $task['avatars'] ?? '');
                                    $shown = 0;
                                    foreach ($avatars as $av) {
                                        if ($av && file_exists("assets/uploads/$av")) {
                                            echo "<img src='assets/uploads/$av' alt='user' title='{$task['assigned_names']}'>";
                                            $shown++;
                                            if ($shown >= 3) break;
                                        }
                                    }
                                    if ($shown == 0) echo "<img src='assets/uploads/empty-placeholder.png' alt='user'>";
                                    ?>
                                </div>
                                <div class="task-date">
                                    <?= date('d M', strtotime($task['start_date'])) ?> - <?= date('d M', strtotime($task['end_date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4 ms-2">You don't assigned any project</div>
    <?php endif; ?>
</div>

<div class="modal fade" id="uni_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      </div>
      </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<!-- Add this after your other script includes -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Sortable on each column
    $('.kanban-column').each(function() {
        new Sortable(this, {
            group: 'kanban',           // Allow dragging between columns
            animation: 200,            // Smooth animation
            sort: true,               // Allow reordering within column
            scroll: true,            // 🔥 AUTO-SCROLL ENABLED
            scrollSensitivity: 30,    // How close to edge to trigger scroll
            scrollSpeed: 15,          // Scroll speed
            bubbleScroll: true,       // Continue scrolling even when mouse leaves container
            draggable: '.task-card',  // What elements are draggable
            ghostClass: 'dragging',   // Class for ghost element
            dragClass: 'dragging',
            
            onEnd: function(evt) {
                const encodedTaskId = $(evt.item).data('id');
                const newStatus = $(evt.to).data('status');
                
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: { id: encodedTaskId, status: newStatus },
                    success: function(resp) {
                        console.log(`Task moved to status ${newStatus}`);
                        // Update task count
                        updateTaskCounts();
                    }
                });
            }
        });
    });
    
    // Helper function to update task counts
    function updateTaskCounts() {
        $('.kanban-column').each(function() {
            const $column = $(this);
            const count = $column.find('.task-card').length;
            $column.prev('.kanban-header').find('.task-count').text(count);
        });
    }
});
</script>
</body>
</html>