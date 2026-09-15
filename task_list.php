<?php include 'db_connect.php' ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<?php
$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];

$raw_project_id = $_GET['project_id'] ?? null;
$decoded_project_id = $raw_project_id ? decode_id($raw_project_id) : 0;
$selected_project_id = intval($decoded_project_id); 

$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; 
$selected_status = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : -1;

$encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; };

$stat = [
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
];
$status_options = $stat;
$status_options[-1] = "All Status";
ksort($status_options);

$where_project_dropdown = " WHERE 1=1 ";
if ($login_type == 2) {
    $where_project_dropdown .= " AND (p.manager_id = '$user_id' OR FIND_IN_SET('$user_id', p.user_ids)) ";
} elseif ($login_type == 3 || $login_type == 4) {
    // employees and clients only see projects they are part of
    $where_project_dropdown .= " AND FIND_IN_SET('$user_id', p.user_ids) ";
} 

$all_projects_q = $conn->query("SELECT id, name FROM project_list p $where_project_dropdown ORDER BY name ASC");
$all_projects = [];
while($p = $all_projects_q->fetch_assoc()) $all_projects[] = $p;

$all_users_q = $conn->query("SELECT id, firstname, lastname FROM users ORDER BY firstname ASC");
$all_users = [];
while($u = $all_users_q->fetch_assoc()) $all_users[] = $u;

$project_query_where = $where_project_dropdown;
if ($selected_project_id > 0) $project_query_where .= " AND p.id = '$selected_project_id' ";

$projects = $conn->query("SELECT * FROM project_list p $project_query_where ORDER BY name ASC");

$base_params = [
    'page' => 'task_list',
    'status' => $selected_status != -1 ? $selected_status : null,
];
$base_params = array_filter($base_params, fn($value) => $value !== null);
?>

<!-- Desktop Layout (unchanged from original) -->
<div class="container-fluid mb-3 d-none d-md-block">
    <div class="row align-items-center mb-3">
        <div class="col-12 col-md-6"><h3 class="m-0">Task List</h3></div>
        <div class="col-12 col-md-6">
            <div class="d-flex justify-content-center justify-content-md-end">
                <?php if($login_type < 3): ?>
                <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                    <i class="fa fa-plus mr-1"></i> Add New Task
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Layout -->
<div class="container-fluid mb-3 d-block d-md-none">
    <div class="row mb-3">
        <div class="col-12">
            <h3 class="m-0">Task List</h3>
        </div>
    </div>
</div>

<div class="container-fluid mb-3">
    <div class="row align-items-center">
        <!-- Filter dan Sort di KIRI -->
        <div class="col-12 col-md-8 mb-2 mb-md-0">
            <div class="d-flex flex-wrap align-items-center">
                <!-- Project Dropdown -->
                <div class="dropdown mr-2 mb-2 mb-md-0">
                    <button class="btn dropdown-toggle text-white" type="button" id="projectDropdown" data-toggle="dropdown" style="background-color:#B75301;">
                        <?php 
                            if($selected_project_id){
                                $proj_name = array_column($all_projects, 'name', 'id')[$selected_project_id] ?? "Choose Project";
                                echo "Project: " . htmlspecialchars($proj_name);
                            } else { echo "All Projects"; }
                        ?>
                    </button>
                    <div class="dropdown-menu" id="projectDropdownMenu">
                        <a class="dropdown-item <?= $selected_project_id == 0 ? 'active' : '' ?>" href="index.php?<?= http_build_query($base_params) ?>">All Projects</a>
                        <div class="dropdown-divider"></div>
                        <?php foreach($all_projects as $p): 
                            $p_params = $base_params; $p_params['project_id'] = $encoder($p['id']);
                        ?>
                            <a class="dropdown-item <?= $selected_project_id == $p['id'] ? 'active' : '' ?>" href="index.php?<?= http_build_query($p_params) ?>"><?= htmlspecialchars($p['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Status Dropdown -->
                <div class="dropdown mr-2 mb-2 mb-md-0">
                    <button class="btn dropdown-toggle text-white" type="button" id="statusDropdown" data-toggle="dropdown" style="background-color:#B75301;">
                        Status: <?= htmlspecialchars($status_options[$selected_status]) ?>
                    </button>
                    <div class="dropdown-menu" id="statusDropdownMenu">
                        <?php foreach($status_options as $key => $label): 
                            $s_params = $base_params; $s_params['status'] = $key;
                            if ($selected_project_id > 0) $s_params['project_id'] = $encoder($selected_project_id);
                            if ($key == -1) unset($s_params['status']);
                        ?>
                            <a class="dropdown-item <?= $selected_status == $key ? 'active' : '' ?>" href="index.php?<?= http_build_query($s_params) ?>"><?= htmlspecialchars($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ADDED: Sort Dropdown -->
                <div class="dropdown mr-2 mb-2 mb-md-0">
                    <button class="btn dropdown-toggle text-white" type="button" id="sortTaskListDropdown" data-toggle="dropdown" style="background-color:#B75301;">
                        <i class="fa fa-sort mr-1"></i> <span id="sortTaskListLabel">Sort: Task Name (A-Z)</span>
                    </button>
                    <div class="dropdown-menu" id="sortTaskListMenu">
                        <a class="dropdown-item sort-tasklist-option" href="#" data-sort="task-asc">Task Name (A-Z)</a>
                        <a class="dropdown-item sort-tasklist-option" href="#" data-sort="task-desc">Task Name (Z-A)</a>
                        <a class="dropdown-item sort-tasklist-option" href="#" data-sort="date-asc">Due Date (Earliest)</a>
                        <a class="dropdown-item sort-tasklist-option" href="#" data-sort="date-desc">Due Date (Latest)</a>
                    </div>
                </div>

                <?php if($login_type < 3): ?>
                    <!-- User Dropdown -->
                    <div class="dropdown mr-2 mb-2 mb-md-0">
                        <button class="btn dropdown-toggle text-white" type="button" id="userDropdown" data-toggle="dropdown" data-boundary="viewport" style="background-color:#B75301;">
                            <?php 
                                if($selected_user_id){
                                    $uname = ""; foreach($all_users as $u) if($u['id']==$selected_user_id) $uname = $u['firstname'];
                                    echo "User: " . htmlspecialchars($uname);
                                } else { echo "All Users"; }
                            ?>
                        </button>
                        <div class="dropdown-menu" id="userDropdownMenu">
                            <?php 
                                $all_users_params = $base_params;
                                unset($all_users_params['user_id']); 
                                if ($selected_project_id > 0) {
                                    $all_users_params['project_id'] = $encoder($selected_project_id);
                                }
                            ?>
                            <a class="dropdown-item" href="index.php?<?= http_build_query($all_users_params) ?>">All Users</a>
                            
                            <div class="dropdown-divider"></div>
                            
                            <?php foreach($all_users as $u): 
                                $u_params = $base_params; 
                                $u_params['user_id'] = $u['id'];
                                if ($selected_project_id > 0) {
                                    $u_params['project_id'] = $encoder($selected_project_id);
                                }
                            ?>
                                <a class="dropdown-item" href="index.php?<?= http_build_query($u_params) ?>">
                                    <?= ucwords($u['firstname']) ?> <span class="user-lastname"><?= ucwords($u['lastname']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif ?>
                
                <!-- Add Task Button - Mobile -->
                <div class="mb-2 mb-md-0 d-md-none">
                    <button type="button" class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                        <i class="fa fa-plus mr-1"></i> Add Task
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Search di KANAN -->
        <div class="col-12 col-md-4">
            <div class="input-group">
                <input type="text" class="form-control" id="searchTaskList" placeholder="Search task...">
                <div class="input-group-append">
                    <span class="input-group-text" style="background-color:#B75301; color:white; border:none;"><i class="fa fa-search"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ========================================
// AUTO-UPDATE OVERDUE STATUS
// ========================================
$conn->query("
    UPDATE task_list 
    SET status = 4 
    WHERE end_date < CURDATE() 
    AND status NOT IN (3,5)
");

if($projects->num_rows > 0):
    $p_count = 0; // Untuk ID unik accordion
    while ($proj = $projects->fetch_assoc()):
        $p_count++;
        $accordion_id = "collapse_proj_" . $p_count;

        $task_query = "SELECT t.* FROM task_list t WHERE t.project_id = {$proj['id']}";
        if ($login_type == 3) $task_query .= " AND FIND_IN_SET('$user_id', t.user_ids)";
        if ($login_type != 3 && $selected_user_id > 0) $task_query .= " AND FIND_IN_SET('$selected_user_id', t.user_ids)";
        if ($selected_status != -1) $task_query .= " AND t.status = '$selected_status'";
        $task_query .= " ORDER BY t.id DESC";
        $tasks = $conn->query($task_query);

        if(!$tasks || $tasks->num_rows == 0) continue;
?>

<div class="col-lg-12 project-card mb-3">
    <div class="card-header-custom collapsed" 
         data-toggle="collapse" 
         data-target="#<?php echo $accordion_id ?>" 
         aria-expanded="false" 
         style="background: #fff; border-radius: 20px; padding: 15px 25px; border: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
        
        <div class="d-flex align-items-center">
            <i class="fa fa-folder-open mr-3" style="color:#b75301; font-size: 1.2rem;"></i>
            <span style="font-weight: 700; color: #333;">Project: <?php echo ucwords($proj['name']) ?></span>
        </div>
        <i class="fa fa-chevron-down toggle-icon" style="transition: transform 0.3s ease; color: #b75301;"></i>
    </div>

    <div id="<?php echo $accordion_id ?>" class="collapse">
        <div class="table-responsive bg-white mt-2 shadow-sm" style="border-radius: 15px; border: 1px solid #eee; overflow: hidden;">
            <table class="table table-hover m-0" style="table-layout: fixed; width: 100%;">
                <colgroup>
                    <col width="5%">
                    <?php if($login_type < 4): ?>
                        <col width="35%">  <col width="15%">  <col width="15%">  <col width="10%">  <col width="20%">
                    <?php else: ?>
                        <col width="45%">  <col width="20%">  <col width="30%">
                    <?php endif; ?>
                </colgroup>
                
                <thead>
                    <tr class="bg-light">
                        <th class="text-left py-3 border-0" style="font-weight: 800; color: #333;">No</th>
                        <th class="text-left py-3 border-0" style="font-weight: 800; color: #333;">Task</th>
                        <th class="text-center py-3 border-0" style="font-weight: 800; color: #333;">Due Date</th>
                        <th class="text-center py-3 border-0" style="font-weight: 800; color: #333;">Status</th>
                        <?php if($login_type < 4): ?>
                        <th class="text-center py-3 border-0" style="font-weight: 800; color: #333;">Created By</th>
                        <th class="text-center py-3 border-0" style="font-weight: 800; color: #333;">Assigned</th>
                    <?php endif; ?>
                    </tr>
                </thead>    
                
                <tbody>
                    <?php $i = 1; while ($row = $tasks->fetch_assoc()): 
                        $encoded_task_id = $encoder($row['id']);
                        $encoded_project_id = $encoder($proj['id']);
                        $current_status = (int)$row['status'];
                        if (strtotime(date('Y-m-d')) > strtotime($row['end_date']) && !in_array($current_status, [5, 3, 0])) $current_status = 4;
                    ?>
                    <tr class="task-row" data-id="<?= $encoded_task_id ?>" data-pid="<?= $encoded_project_id ?>" style="cursor:pointer;">
                        <td class="text-left align-middle"><?php echo $i++ ?></td>
                        
                        <td class="text-left align-middle">
                            <div style="font-weight: 600; color: #333; line-height: 1.2;">
                                <?php echo ucwords($row['task']) ?>
                            </div>
                            
                            <div class="text-muted" style="
                                font-size: 0.8rem; 
                                font-style: italic; 
                                line-height: 1.2; 
                                margin-top: 2px;
                                display: -webkit-box;
                                -webkit-line-clamp: 1; 
                                -webkit-box-orient: vertical;
                                overflow: hidden;
                                text-overflow: ellipsis;
                            ">
                                <?php echo strip_tags(html_entity_decode($row['description'])) ?>
                            </div>
                        </td>
                        
                        <td class="text-center align-middle">
                            <span class="text-muted" style="font-size: 0.9rem;">
                                <?php echo date("M d, Y", strtotime($row['end_date'])) ?>
                            </span>
                        </td>
                        
                        <td class="text-center align-middle">
                            <?php
                                $tstatus = $stat[$current_status] ?? 'Pending';
                                $badge_class = [0=>'secondary', 1=>'info', 2=>'primary', 3=>'warning', 4=>'danger', 5=>'success'][$current_status];
                                echo "<span class='badge badge-{$badge_class} px-3 py-2' style='min-width: 85px; border-radius: 10px;'>{$tstatus}</span>";
                            ?>
                        </td>
                        
<?php if($login_type < 4): ?>
                        <td class="text-center align-middle">
                            <?php 
                                $c_img = 'empty-placeholder.png';
                                if (!empty($row['created_by'])) {
                                    $creator_q = $conn->query("SELECT avatar FROM users WHERE id = '{$row['created_by']}' LIMIT 1");
                                    $creator = $creator_q->fetch_assoc();
                                    $c_img = !empty($creator['avatar']) ? $creator['avatar'] : 'empty-placeholder.png';
                                }
                            ?>
                            <div class="avatar-stack-container d-flex justify-content-center">
                                <img src="assets/uploads/<?php echo $c_img ?>" 
                                    class="avatar-table rolling-avatar" 
                                    style="--d: 0; width:32px; height:32px; object-fit:cover; border-radius:50%; border:2px solid #fff;"
                                    onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
                            </div>
                        </td>

                        <td class="text-center align-middle">
                            <?php 
                                $u_ids = !empty($row['user_ids']) ? $row['user_ids'] : '';
                                $assigned_users = [];
                                if($u_ids) {
                                    $u_q = $conn->query("SELECT avatar FROM users WHERE id IN ($u_ids) LIMIT 5");
                                    while($u = $u_q->fetch_assoc()) $assigned_users[] = $u;
                                }
                            ?>
                            <div class="avatar-stack-container d-flex justify-content-center align-items-center">
                                <?php 
                                $idx = 0; 
                                foreach ($assigned_users as $au): 
                                ?>
                                    <img src="assets/uploads/<?= $au['avatar'] ?: 'empty-placeholder.png' ?>" 
                                        class="avatar-table rolling-avatar" 
                                        style="--d: <?= $idx ?>; width:32px; height:32px; object-fit:cover; border-radius:50%; border:2px solid #fff; margin-left: <?= ($idx > 0 ? '-10px' : '0') ?>;"
                                        onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
                                <?php $idx++; endforeach; ?>
                            </div>
                        </td>
<?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endwhile; // Tutup while projects ?>
<?php else: ?>
    <div class="col-lg-12"><div class="card card-outline"><div class="card-body text-center">No projects found.</div></div></div>
<?php endif; // Tutup if projects ?>

<style>
    /* 1. Menyelaraskan Header Accordion (Project Folder Bar) */
.card-header-custom {
    background-color: #ffffff !important;
    padding: 16px 25px !important;
    border-radius: 20px !important; /* Sama dengan radius Projects */
    border: 1px solid #eee !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03) !important;
    transition: all 0.3s ease;
}

.card-header-custom:hover {
    background-color: #fcfcfc !important;
}

/* 2. Menyelaraskan Semua Tombol Filter (All Projects, Status, User) */
#projectDropdown, #statusDropdown, #userDropdown,
button.btn.text-white[style*="background-color:#B75301"],
button.btn.text-white[style*="background-color: #B75301"] {
    background-color: #B75301 !important;
    border-radius: 10px !important; /* Radius seragam dengan tombol Add Project */
    padding: 10px 20px !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border: none !important;
    box-shadow: 0 4px 14px rgba(183, 83, 1, 0.25) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: auto !important;
}

#projectDropdown:hover, #statusDropdown:hover, #userDropdown:hover,
button.btn.text-white[style*="background-color:#B75301"]:hover {
    background-color: #964401 !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(183, 83, 1, 0.35) !important;
    color: white !important;
}

/* 3. Menyelaraskan Search Bar agar persis Projects */
.input-group {
    border-radius: 20px !important; /* Pill style */
    overflow: hidden;
    border: 1px solid #ced4da !important;
    background-color: #fff;
}

#searchTaskList {
    border: none !important;
    padding-left: 20px !important;
    height: 38px !important;
    font-size: 13px !important;
}

.input-group-append .input-group-text {
    background-color: #B75301 !important;
    color: white !important;
    border: none !important;
    padding: 0 15px !important;
}

/* 4. Mempercantik Dropdown Menu & Item */
.dropdown-menu {
    border-radius: 12px !important;
    border: none !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
    padding: 10px !important;
    margin-top: 12px !important;
}

.dropdown-item {
    border-radius: 8px !important;
    padding: 10px 15px !important;
    font-weight: 600;
    color: #555;
}

.dropdown-item.active, .dropdown-item:active {
    background-color: #B75301 !important;
    color: #fff !important;
}

.dropdown-item:hover:not(.active) {
    background-color: #FFF5EE !important;
    color: #B75301 !important;
    padding-left: 20px !important;
}

/* 5. Styling Tabel di dalam Accordion */
.table-responsive {
    border-radius: 15px !important;
    border: 1px solid #eee !important;
    margin-top: 10px;
}

.table thead th {
    background-color: #E3E3E3 !important; /* Header abu-abu sesuai mockup */
    padding: 15px !important;
    border: none !important;
    font-weight: 800 !important;
}

/* 6. Fix Jarak antar Filter */
.d-flex.flex-wrap {
    gap: 10px;
}

.mr-2 {
    margin-right: 0 !important;
}

    /* CSS UNTUK MERAPIKAN KOLOM */
    .table-fixed {
        table-layout: fixed;
        width: 100%;
    }
    
    .truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
    }

    .table-responsive {
        overflow-x: auto;
    }
    
    @media (max-width: 767.98px) {
        /* Hide columns: No, Created By, Assigned */
        .table th:nth-child(1),
        .table td:nth-child(1),
        .table th:nth-child(5),
        .table td:nth-child(5),
        .table th:nth-child(6),
        .table td:nth-child(6) {
            display: none;
        }
        
        /* Keep only Task, Due Date, and Task Status visible */
        .table th:nth-child(2),
        .table td:nth-child(2),
        .table th:nth-child(3),
        .table td:nth-child(3),
        .table th:nth-child(4),
        .table td:nth-child(4) {
            display: table-cell;
        }
        
        /* Adjust widths for the 3 columns */
        .table th:nth-child(2),
        .table td:nth-child(2) {
            width: 45% !important; /* Task column */
        }
        
        .table th:nth-child(3),
        .table td:nth-child(3) {
            width: 25% !important; /* Due Date column */
        }
        
        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 30% !important; /* Task Status column */
        }
        
        /* Make sure table fits without horizontal scroll */
        .table-responsive table {
            min-width: 100% !important;
            width: 100% !important;
        }
        
        /* Reduce font sizes for mobile */
        .table {
            font-size: 12px !important;
        }
        
        .table td b {
            font-size: 12px !important;
        }
        
        .truncate {
            font-size: 11px !important;
            -webkit-line-clamp: 2;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Compact status badges */
        .badge-status {
            min-width: 70px !important;
            font-size: 10px !important;
            padding: 3px 6px !important;
            min-width: 65px !important;
            display: inline-block;
        }
        
        /* Make all orange buttons the same size on mobile */
        button.btn.text-white[style*="background-color:#B75301"],
        button.btn.text-white[style*="background-color: #B75301"] {
            padding: 6px 12px !important;
            font-size: 13px !important;
            height: 34px !important;
            line-height: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 4px !important;
            min-width: auto !important;
            width: auto !important;
            flex-shrink: 0 !important;
        }
        
        /* Force all button icons same size */
        button.btn.text-white i.fa {
            font-size: 12px !important;
            margin-right: 5px !important;
            line-height: 1 !important;
            display: inline-block !important;
        }
        
        /* Force all button text same size */
        button.btn.text-white span {
            font-size: 13px !important;
            line-height: 1 !important;
            display: inline-block !important;
            white-space: nowrap !important;
        }
        
        /* Make sure the container lays out buttons properly */
        .d-flex.flex-wrap.align-items-center {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 8px !important;
        }
        
        /* Remove any conflicting margins */
        .d-flex.flex-wrap.align-items-center .dropdown,
        .d-flex.flex-wrap.align-items-center .mb-2 {
            margin-right: 0 !important;
            margin-bottom: 0 !important;
        }
    }
    
    /* Extra small phones */
    @media (max-width: 400px) {
        .table th:nth-child(2),
        .table td:nth-child(2) {
            width: 50% !important; /* Task column wider */
        }
        
        .table th:nth-child(3),
        .table td:nth-child(3) {
            width: 20% !important; /* Due Date narrower */
        }
        
        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 30% !important; /* Task Status */
        }
        
        .table {
            font-size: 11px !important;
        }
        
        .table td b {
            font-size: 11px !important;
        }
        
        .truncate {
            font-size: 10px !important;
            -webkit-line-clamp: 1;
        }
      
    }
    
    /* ================================================= */
    /* MOBILE-ONLY BOTTOM SHEET STYLES */
    /* ================================================= */
    
    @media (max-width: 767.98px) {
        /* Hide the original dropdown menus on mobile */
        #projectDropdownMenu,
        #statusDropdownMenu,
        #userDropdownMenu,
        #sortTaskListMenu {  /* ADDED */
            display: none !important;
        }
        
        /* Bottom Sheet */
        .bottom-sheet {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.2);
            z-index: 1060;
            padding: 20px;
            max-height: 85vh;
            overflow-y: auto;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            display: none;
        }
        
        .bottom-sheet.active {
            display: block;
            transform: translateY(0);
        }
        
        /* Sheet Backdrop */
        .sheet-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1059;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sheet-backdrop.active {
            display: block;
            opacity: 1;
        }
        
        /* Sheet Header */
        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .sheet-title {
            margin: 0;
            font-size: 1.25rem;
            color: #333;
        }
        
        .sheet-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #666;
            padding: 5px;
            cursor: pointer;
            line-height: 1;
        }
        
        /* Sheet Content */
        .sheet-content {
            padding: 10px 0;
        }
        
        /* Make sheet items look good */
        .sheet-content .dropdown-item {
            display: block;
            width: 100%;
            padding: 15px 20px;
            border: none;
            background: none;
            text-align: left;
            color: #333;
            border-bottom: 1px solid #f5f5f5;
            font-size: 1rem;
            cursor: pointer;
        }
        
        .sheet-content .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .sheet-content .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .sheet-content .dropdown-divider {
            height: 1px;
            background-color: #eee;
            margin: 10px 20px;
        }
        
        /* Prevent body scroll when sheet is open */
        body.sheet-open {
            overflow: hidden !important;
        }
    }
    
        /* Ganti blok .dropdown-menu lama dengan ini */
        .dropdown-menu {
            max-height: 70vh; /* Membatasi tinggi menu maksimal 80% layar */
            overflow-y: auto; /* Munculkan scrollbar jika nama user terlalu banyak */
        }

        /* Pastikan dropdown muncul saat diklik di desktop */
        .dropdown.show > .dropdown-menu {
            display: block !important;
        }
    
    /* Desktop styles */
    @media (min-width: 768px) {
        .bottom-sheet,
        .sheet-backdrop {
            display: none !important;
        }
    }

    /* Paska */
    /* Gaya Kartu Proyek (Accordion Header) */
    .project-card {
        margin-bottom: 15px !important;
        border-radius: 20px !important;
        overflow: hidden;
        border: none !important;
    }

    .card-header-custom {
        background-color: #ffffff !important;
        padding: 15px 25px !important;
        border-radius: 20px !important;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        border: 1px solid #eee !important;
    }

    .card-header-custom:hover {
        background-color: #f9f9f9 !important;
    }

    /* Ikon Hati/Chevron berputar saat dibuka */
    .toggle-icon {
        transition: transform 0.3s ease;
        font-size: 1.2rem;
        color: #B75301;
        transform: rotate(-90deg); /* Panah menghadap ke kanan saat tertutup */
    }

    /* Saat parent (card-header-custom) TIDAK memiliki class 'collapsed' (berarti sedang terbuka) */
    .card-header-custom:not(.collapsed) .toggle-icon {
        transform: rotate(0deg); /* Panah berputar ke atas */
    }

    .collapsed .toggle-icon {
        transform: rotate(-90deg); /* Menghadap ke samping saat tertutup */
    }

    /* Gaya Tabel di dalam Accordion */
    .project-content {
        padding: 10px 20px 20px 20px;
        background: #fff;
    }

    .table-container {
        border-radius: 15px;
        overflow: hidden;
        border: 1px solid #f0f0f0;
    }

    .table thead th {
        background-color: #f4f6f9;
        border: none;
        color: #666;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 1px;
    }

    /* LOGIKA ANIMASI AVATAR */
    .avatar-stack {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Container Utama */
    .avatar-stack-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0; 
    }

    .rolling-avatar {
        opacity: 0;
        transform: translateX(-20px) scale(0.5) rotate(-10deg); 
        transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        transition-delay: calc(var(--d) * 0.1s); /* Efek bergulir satu per satu */
        will-change: transform, opacity;
    }

    /* Pemicu Animasi saat Project Card Aktif */
    .project-card.active-roll .rolling-avatar {
        opacity: 1 !important;
        transform: translateX(0) scale(1) rotate(0deg) !important;
    }

    /* Staggered Delay untuk efek bergulir */
    .animate-roll img:nth-child(2) { transition-delay: 0.1s; }
    .animate-roll img:nth-child(3) { transition-delay: 0.2s; }
    .animate-roll img:nth-child(4) { transition-delay: 0.3s; }

    table thead th {
        font-weight: 800 !important;
        color: #333 !important;
        text-transform: uppercase;
        font-size: 13px;
    }

    /* ============================================
   MOBILE REDESIGN — TASK LIST
   ============================================ */
@media (max-width: 490px) {

    .table-responsive table {
        display: none !important;
    }

    .mobile-task-header {
        background: #e3e3e3;
        padding: 11px 16px;
        text-align: center;
        border-bottom: 0.5px solid #d8d5d0;
        font-size: 12px;
        font-weight: 800;
        color: #333;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .mobile-task-list {
        overflow-y: auto;
        max-height: 500px;
        scrollbar-width: thin;
        scrollbar-color: #e8e2d8 transparent;
    }
    .mobile-task-list::-webkit-scrollbar { width: 3px; }
    .mobile-task-list::-webkit-scrollbar-thumb { background: #e8e2d8; border-radius: 10px; }

    .mobile-task-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 14px 16px;
        border-bottom: 0.5px solid #f5f1ec;
        cursor: pointer;
        background: #fff;
        transition: background 0.1s;
    }
    .mobile-task-row:last-child { border-bottom: none; }
    .mobile-task-row:hover { background: #f0f0f0; }
    .mobile-task-row:active { background: #e8e8e8; }

    .mobile-row-no {
        font-size: 10px;
        color: #ccc;
        font-weight: 600;
        min-width: 14px;
        flex-shrink: 0;
        padding-top: 2px;
    }

    .mobile-row-content { flex: 1; min-width: 0; }

    .mobile-row-top {
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }

    .mobile-row-name {
        flex: 1;
        min-width: 0;
        font-size: 13px;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1.4;
        word-break: break-word;
    }

    .mobile-row-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .mobile-row-date {
        font-size: 10px;
        color: #888;
        font-weight: 500;
        white-space: nowrap;
    }

    .mobile-status-badge {
        display: inline-block;
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.3px;
        padding: 4px 0;
        border-radius: 20px;
        text-transform: uppercase;
        white-space: nowrap;
        min-width: 80px;
        text-align: center;
    }
    .mobile-badge-pending  { background: #6c757d; color: #fff; }
    .mobile-badge-started  { background: #17a2b8; color: #fff; }
    .mobile-badge-progress { background: #007bff; color: #fff; }
    .mobile-badge-hold     { background: #ffc107; color: #333; }
    .mobile-badge-overdue  { background: #dc3545; color: #fff; }
    .mobile-badge-done     { background: #28a745; color: #fff; }

    .mobile-row-desc {
        font-size: 10px;
        color: #bbb;
        margin-top: 3px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: normal;
        max-width: 55%;
    }

    .mobile-row-bottom {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-top: 10px;
        padding-top: 9px;
        border-top: 0.5px solid #f5f1ec;
    }

    .mobile-av-group {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .mobile-av-label {
        font-size: 8.5px;
        font-weight: 700;
        color: #ccc;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .mobile-av-stack { display: flex; align-items: center; }

    .mobile-av-stack img {
        width: 28px !important;
        height: 28px !important;
        border-radius: 50% !important;
        border: 2px solid #fff !important;
        object-fit: cover !important;
        margin-left: -5px !important;
        opacity: 1 !important;
        transform: none !important;
    }
    .mobile-av-stack img:first-child { margin-left: 0 !important; }
}

@media (min-width: 491px) {
    .mobile-task-header,
    .mobile-task-list { display: none !important; }
}
</style>

<script>
$(document).ready(function(){
    // Tambahkan class active-roll saat folder dibuka
    $('.collapse').on('show.bs.collapse', function () {
        var card = $(this).closest('.project-card');
        // Beri delay sedikit agar animasi terlihat saat tabel mulai muncul
        setTimeout(function(){
            card.addClass('active-roll');
        }, 200);
    });

    // Reset animasi saat folder ditutup
    $('.collapse').on('hide.bs.collapse', function () {
        $(this).closest('.project-card').removeClass('active-roll');
    });

    // Jalankan pengecekan jika ada project yang sudah terbuka saat load
    $('.collapse.show').closest('.project-card').addClass('active-roll');
});

// Global variables for task_list page
var currentTaskListProjectFilter = '<?= $selected_project_id ?>';
var currentTaskListStatusFilter = <?= $selected_status ?>;
var currentTaskListUserFilter = '<?= $selected_user_id ?>';
// ADDED: sort variable
var currentTaskListSort = 'task-asc'; // default

// Check if mobile
function isMobile() {
    return $(window).width() < 768;
}

// Bottom Sheet Functions
function showBottomSheet(title, content) {
    // Remove any existing sheet
    $('.bottom-sheet, .sheet-backdrop').remove();
    
    // Create sheet HTML
    var sheetHTML = `
        <div class="bottom-sheet" id="bottomSheet">
            <div class="sheet-header">
                <h5 class="sheet-title">${title}</h5>
                <button type="button" class="sheet-close">&times;</button>
            </div>
            <div class="sheet-content">
                ${content}
            </div>
        </div>
        <div class="sheet-backdrop"></div>
    `;
    
    // Add to body
    $('body').append(sheetHTML);
    
    // Add sheet-open class to body
    $('body').addClass('sheet-open');
    
    // Show with animation
    setTimeout(function() {
        $('#bottomSheet').addClass('active');
        $('.sheet-backdrop').addClass('active');
    }, 10);
    
    // Setup close handlers
    $('.sheet-close').off('click').on('click', closeBottomSheet);
    $('.sheet-backdrop').off('click').on('click', closeBottomSheet);
    
    // ESC key to close
    $(document).off('keyup.sheet').on('keyup.sheet', function(e) {
        if (e.key === 'Escape') {
            closeBottomSheet();
        }
    });
}

function closeBottomSheet() {
    $('#bottomSheet').removeClass('active');
    $('.sheet-backdrop').removeClass('active');
    
    setTimeout(function() {
        $('.bottom-sheet, .sheet-backdrop').remove();
        $('body').removeClass('sheet-open');
    }, 300);
}

// ADDED: Sorting function
function sortTaskList(sortType) {
    // For each project table body
    $('.project-card .table-responsive table tbody').each(function() {
        var $tbody = $(this);
        var $rows = $tbody.find('.task-row').toArray();
        
        $rows.sort(function(a, b) {
            var aVal, bVal;
            
            switch(sortType) {
                case 'task-asc':
                    aVal = $(a).find('td:eq(1)').text().toLowerCase(); // Task column index 1
                    bVal = $(b).find('td:eq(1)').text().toLowerCase();
                    return aVal.localeCompare(bVal);
                case 'task-desc':
                    aVal = $(a).find('td:eq(1)').text().toLowerCase();
                    bVal = $(b).find('td:eq(1)').text().toLowerCase();
                    return bVal.localeCompare(aVal);
                case 'date-asc':
                    aVal = $(a).find('td:eq(2)').text(); // Due date column index 2
                    bVal = $(b).find('td:eq(2)').text();
                    return new Date(aVal) - new Date(bVal);
                case 'date-desc':
                    aVal = $(a).find('td:eq(2)').text();
                    bVal = $(b).find('td:eq(2)').text();
                    return new Date(bVal) - new Date(aVal);
                default:
                    return 0;
            }
        });
        
        $.each($rows, function(index, row) {
            $tbody.append(row);
        });
    });
    
    // After sorting, reapply search filter if any
    var searchValue = $('#searchTaskList').val().toLowerCase();
    if (searchValue) {
        applyTaskListSearch();
    }
}

// ADDED: Refactored search function
function applyTaskListSearch() {
    var value = $('#searchTaskList').val().toLowerCase();
    
    // MODIFIKASI: Jika input kosong, tampilkan semua tapi TUTUP semua accordion
    if (value.length === 0) {
        $(".task-row").show();
        $(".project-card").show();
        $(".collapse").collapse('hide'); // Menutup semua folder
        return;
    }
    
    $(".project-card").each(function() {
        var $card = $(this);
        var $projectName = $card.find(".card-header-custom span").text().toLowerCase();
        var $tasks = $card.find(".task-row");
        var $accordion = $card.find(".collapse");
        
        var projectMatches = $projectName.indexOf(value) > -1;
        var hasVisibleTask = false;

        $tasks.each(function() {
            var $row = $(this);
            var taskText = $row.text().toLowerCase();
            
            if (projectMatches || taskText.indexOf(value) > -1) {
                $row.show();
                hasVisibleTask = true;
            } else {
                $row.hide();
            }
        });

        if (hasVisibleTask) {
            $card.show();
            if (!$accordion.hasClass('show')) {
                $accordion.collapse('show');
            }
        } else {
            $card.hide();
            // MODIFIKASI: Jika tidak ada yang cocok, pastikan tertutup
            $accordion.collapse('hide'); 
        }
    });
}

// Initialize Mobile Interface
function initTaskListInterface() {
    if (isMobile()) {
        // MOBILE: Use bottom sheets
        
        // Hide dropdown menus
        $('#projectDropdownMenu, #statusDropdownMenu, #userDropdownMenu, #sortTaskListMenu').hide();
        
        $('.dropdown.show').removeClass('show');
        $('.dropdown-menu.show').removeClass('show');
        
        // Project Dropdown Button - Mobile
        $('#projectDropdown').off('click.mobile').on('click.mobile', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var content = `
                <a href="#" class="dropdown-item sheet-filter-item" data-type="project" data-value="0">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>All Projects</span>
                        ${currentTaskListProjectFilter === '0' || currentTaskListProjectFilter === '' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <div class="dropdown-divider"></div>
            `;
            
            <?php foreach($all_projects as $p): ?>
            content += `
                <a href="#" class="dropdown-item sheet-filter-item" data-type="project" data-value="<?= $p['id'] ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($p['name']) ?></span>
                        ${currentTaskListProjectFilter === '<?= $p['id'] ?>' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
            `;
            <?php endforeach; ?>
            
            showBottomSheet('Select Project', content);
            return false;
        });
        
        // Status Dropdown Button - Mobile
        $('#statusDropdown').off('click.mobile').on('click.mobile', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var content = `
                <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>All Status</span>
                        ${currentTaskListStatusFilter === -1 ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="0">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Pending</span>
                        ${currentTaskListStatusFilter === 0 ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="1">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Started</span>
                        ${currentTaskListStatusFilter === 1 ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>On-Progress</span>
                        ${currentTaskListStatusFilter === 2 ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>On-Hold</span>
                        ${currentTaskListStatusFilter === 3 ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Over Due</span>
                        ${currentTaskListStatusFilter === 4 ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="5">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Done</span>
                        ${currentTaskListStatusFilter === 5 ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
            `;
            
            showBottomSheet('Filter by Status', content);
            return false;
        });

        // ADDED: Sort Dropdown Button - Mobile
        $('#sortTaskListDropdown').off('click.mobile').on('click.mobile', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var content = `
                <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="task-asc">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Task Name (A-Z)</span>
                        ${currentTaskListSort === 'task-asc' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="task-desc">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Task Name (Z-A)</span>
                        ${currentTaskListSort === 'task-desc' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="date-asc">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Due Date (Earliest)</span>
                        ${currentTaskListSort === 'date-asc' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="date-desc">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Due Date (Latest)</span>
                        ${currentTaskListSort === 'date-desc' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
            `;
            
            showBottomSheet('Sort Options', content);
            return false;
        });
        
        // User Dropdown Button - Mobile (if applicable)
        <?php if($login_type < 3): ?>
        $('#userDropdown').off('click.mobile').on('click.mobile', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var content = `
                <a href="#" class="dropdown-item sheet-filter-item" data-type="user" data-value="0">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>All Users</span>
                        ${currentTaskListUserFilter === '0' || currentTaskListUserFilter === '' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
                <div class="dropdown-divider"></div>
            `;
            
            <?php foreach($all_users as $u): ?>
            content += `
                <a href="#" class="dropdown-item sheet-filter-item" data-type="user" data-value="<?= $u['id'] ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><?= ucwords($u['firstname']) ?> <span class="user-lastname"><?= ucwords($u['lastname']) ?></span></span>
                        ${currentTaskListUserFilter === '<?= $u['id'] ?>' ? '<i class="fa fa-check text-success"></i>' : ''}
                    </div>
                </a>
            `;
            <?php endforeach; ?>
            
            showBottomSheet('Select User', content);
            return false;
        });
        <?php endif; ?>
        
        // Handle sheet item clicks
        $(document).off('click.tasklist-sheet-item').on('click.tasklist-sheet-item', '.sheet-filter-item', function(e) {
            e.preventDefault();
            
            var type = $(this).data('type');
            var value = $(this).data('value');
            var baseUrl = 'index.php?page=task_list';
            
            if (type === 'sort') {
                // Update sort variable, label and apply sorting
                currentTaskListSort = value;
                var sortText = $(this).find('span').text();
                $('#sortTaskListLabel').text('Sort: ' + sortText);
                sortTaskList(currentTaskListSort);
                closeBottomSheet();
                return;
            }
            
            // For filter types (project, status, user) do page reload
            // Update current filters
            if (type === 'project') {
                currentTaskListProjectFilter = value;
            } else if (type === 'status') {
                currentTaskListStatusFilter = parseInt(value);
            } else if (type === 'user') {
                currentTaskListUserFilter = value;
            }
            
            // Build URL with parameters
            var params = [];
            if (currentTaskListProjectFilter && currentTaskListProjectFilter !== '0') {
                params.push('project_id=<?= $encoder("PROJECT_ID") ?>'.replace('PROJECT_ID', currentTaskListProjectFilter));
            }
            if (currentTaskListStatusFilter !== -1) {
                params.push('status=' + currentTaskListStatusFilter);
            }
            if (currentTaskListUserFilter && currentTaskListUserFilter !== '0') {
                params.push('user_id=' + currentTaskListUserFilter);
            }
            
            var url = baseUrl + (params.length > 0 ? '&' + params.join('&') : '');
            window.location.href = url;
        });
        
        // Disable Bootstrap dropdowns on mobile
        $('.dropdown-toggle').dropdown('dispose');
        
    } else {
        // DESKTOP: Use normal dropdowns
        $('#projectDropdownMenu, #statusDropdownMenu, #userDropdownMenu, #sortTaskListMenu').css('display', '');
        
        // Remove mobile event handlers
        $('#projectDropdown').off('click.mobile');
        $('#statusDropdown').off('click.mobile');
        $('#sortTaskListDropdown').off('click.mobile'); // ADDED
        <?php if($login_type < 3): ?>
        $('#userDropdown').off('click.mobile');
        <?php endif; ?>
        $(document).off('click.tasklist-sheet-item');
        
        // Enable Bootstrap dropdowns
        $('.dropdown-toggle').dropdown();
        
        // Close any open sheet
        closeBottomSheet();
    }
}

$(document).ready(function(){
    // Initialize interface
    initTaskListInterface();
    
    // Reinitialize on window resize
    $(window).on('resize', function() {
        initTaskListInterface();
    });
    
    // Klik Baris Task
    $('.task-row').click(function(e){
        if($(e.target).closest('.dropdown, .btn').length) return;
        uni_modal("Task Details","get_task_detail.php?id="+ $(this).data('id') ,"mid-large");
    });

    // Pencarian - menggunakan fungsi yang sudah direfactor
    $("#searchTaskList").on("keyup", function() {
        applyTaskListSearch();
    });

    // ADDED: Desktop sort option handlers
    $('.sort-tasklist-option').on('click', function(e) {
        e.preventDefault();
        var sortVal = $(this).data('sort');
        currentTaskListSort = sortVal;
        var sortText = $(this).text();
        $('#sortTaskListLabel').text('Sort: ' + sortText);
        sortTaskList(currentTaskListSort);
    });
    
    // Initialize dropdowns for desktop
    $('.dropdown-toggle').dropdown({ display: 'static' });
});

// --- LOGIKA BARU: AUTO EXPAND DARI DASHBOARD ---
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const shouldExpand = urlParams.get('expand');

    if (shouldExpand === 'true') {
        // Kita gunakan interval untuk memastikan elemen accordion sudah muncul di DOM
        let expandCount = 0;
        let checkExist = setInterval(function() {
            // Cari header accordion yang sedang tertutup (memiliki class 'collapsed')
            let collapsedHeaders = $('.card-header-custom.collapsed');

            if (collapsedHeaders.length > 0) {
                // Klik semua header untuk membuka accordion
                collapsedHeaders.click();
                
                // Tambahkan class animasi rolling avatar yang kamu buat di baris 835
                $('.project-card').addClass('active-roll');
                
                console.log("Logika Berhasil: Membuka " + collapsedHeaders.length + " project.");
                clearInterval(checkExist); // Berhenti jika sudah terbuka
            }

            // Keamanan: berhenti cek jika sudah lewat 3 detik (mencegah loop abadi)
            expandCount++;
            if (expandCount > 15) clearInterval(checkExist);
        }, 200);
    }
});

// ============================================
// MOBILE TASK LIST LAYOUT BUILDER
// ============================================
function buildMobileTaskListLayout() {
    if ($(window).width() > 490) return;

    var badgeClassMap = {
        0: 'mobile-badge-pending',
        1: 'mobile-badge-started',
        2: 'mobile-badge-progress',
        3: 'mobile-badge-hold',
        4: 'mobile-badge-overdue',
        5: 'mobile-badge-done'
    };

    var badgeLabelMap = {
        0: 'PENDING', 1: 'STARTED', 2: 'ON-PROGRESS',
        3: 'ON-HOLD', 4: 'OVER DUE', 5: 'DONE'
    };

    $('.project-card .table-responsive').each(function () {
        var $wrapper = $(this);
        if ($wrapper.find('.mobile-task-header').length) return;

        var $header = $('<div class="mobile-task-header">All Task</div>');
        var $list   = $('<div class="mobile-task-list"></div>');
        var counter = 1;

        $wrapper.find('tbody .task-row').each(function () {
            var $tr       = $(this);
            var encodedId = $tr.data('id');
            var status    = parseInt($tr.find('td:nth-child(4) .badge').text().trim().toLowerCase()
                .replace('over due','4').replace('done','5').replace('on-progress','2')
                .replace('on-hold','3').replace('started','1').replace('pending','0')) || 0;

            // Ambil status dari badge class
            var $badge    = $tr.find('td:nth-child(4) .badge');
            var badgeText = $badge.text().trim().toUpperCase();
            var badgeCls  = 'mobile-badge-pending';
            if ($badge.hasClass('badge-success'))   badgeCls = 'mobile-badge-done';
            if ($badge.hasClass('badge-danger'))    badgeCls = 'mobile-badge-overdue';
            if ($badge.hasClass('badge-warning'))   badgeCls = 'mobile-badge-hold';
            if ($badge.hasClass('badge-primary'))   badgeCls = 'mobile-badge-progress';
            if ($badge.hasClass('badge-info'))      badgeCls = 'mobile-badge-started';
            if ($badge.hasClass('badge-secondary')) badgeCls = 'mobile-badge-pending';

            var taskName  = $tr.find('td:nth-child(2) div:first-child').text().trim();
            var taskDesc  = $tr.find('td:nth-child(2) div:last-child').text().trim();
            var dueDate   = $tr.find('td:nth-child(3)').text().trim();

            var $createdImgs  = $tr.find('td:nth-child(5) img').clone()
                                   .css({opacity:1, transform:'none'});
            var $assignedImgs = $tr.find('td:nth-child(6) img').clone()
                                   .css({opacity:1, transform:'none'});

            var descHtml = taskDesc
                ? '<div class="mobile-row-desc">' + taskDesc + '</div>' : '';

            var $row = $(
                '<div class="mobile-task-row" data-id="' + encodedId + '">' +
                    '<span class="mobile-row-no">' + counter + '</span>' +
                    '<div class="mobile-row-content">' +
                        '<div class="mobile-row-top">' +
                            '<span class="mobile-row-name">' + taskName + '</span>' +
                            '<div class="mobile-row-right">' +
                                '<span class="mobile-row-date">' + dueDate + '</span>' +
                                '<span class="mobile-status-badge ' + badgeCls + '">' + badgeText + '</span>' +
                            '</div>' +
                        '</div>' +
                        descHtml +
                        '<div class="mobile-row-bottom">' +
                            '<div class="mobile-av-group">' +
                                '<span class="mobile-av-label">Created By</span>' +
                                '<div class="mobile-av-stack"></div>' +
                            '</div>' +
                            '<div class="mobile-av-group">' +
                                '<span class="mobile-av-label">Assigned</span>' +
                                '<div class="mobile-av-stack"></div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );

            $row.find('.mobile-av-stack').eq(0).append($createdImgs);
            $row.find('.mobile-av-stack').eq(1).append($assignedImgs);

            $row.on('click', function () {
                uni_modal("Task Details", "get_task_detail.php?id=" + encodedId, "mid-large");
            });

            $list.append($row);
            counter++;
        });

        $wrapper.append($header).append($list);
    });
}

// Jalankan saat load
$(document).ready(function () {
    buildMobileTaskListLayout();

    // Jalankan ulang saat accordion dibuka
    $(document).on('shown.bs.collapse', '.collapse', function () {
        buildMobileTaskListLayout();
    });
});
</script>