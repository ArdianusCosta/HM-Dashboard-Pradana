<?php
// FILE: view_project.php (REVISI LENGKAP - KPI TIM + MODERN UI + HIDE FOR CLIENTS)

include 'db_connect.php'; 
session_start();

// obtain role from session for convenience
$login_type = $_SESSION['login_type'] ?? 0;

// ========================================
// AUTO-UPDATE OVERDUE STATUS
// ========================================
$conn->query("
    UPDATE task_list 
    SET status = 4 
    WHERE end_date < CURDATE() 
    AND status NOT IN (3,5)
");

$id = $_GET['id'] ?? 0; 
if ($id === 0 || !is_numeric($id) || $id <= 0) {
    header("Location: index.php?page=404");
    exit;
}

$encoder = function_exists('encode_id') ? 'encode_id' : function($i) { return $i; };

// 1. Query Proyek
$qry = $conn->query("SELECT * FROM project_list WHERE id = $id");
$project_data = $qry->fetch_array();

if (!$project_data) {
    header("Location: index.php?page=404");
    exit;
}

foreach($project_data as $k => $v){
    $$k = $v;
}

$row = $project_data; 
$today = strtotime(date("Y-m-d"));
$encoded_project_id = $encoder($id);

// =======================================================
// LOGIKA UTAMA DAN PENGHITUNGAN STATUS PROYEK
// =======================================================

$tprog_qry = $conn->query("SELECT id FROM task_list where project_id = {$id}");
$tprog = ($tprog_qry === false) ? 0 : $tprog_qry->num_rows;

$cprog_qry = $conn->query("SELECT id FROM task_list where project_id = {$id} and status = 5");
$cprog = ($cprog_qry === false) ? 0 : $cprog_qry->num_rows;

$prod_qry = $conn->query("SELECT id FROM user_productivity where project_id = {$id}");
$prod = ($prod_qry === false) ? 0 : $prod_qry->num_rows;

$prog = $tprog > 0 ? ($cprog/$tprog) * 100 : 0;
$prog = $prog > 0 ?  number_format($prog,2) : $prog;

$endDate = strtotime($row['end_date']);
if($row['status'] != 5 && $row['status'] != 3 && $row['status'] != 0 && $today > $endDate){
    $row['status'] = 4;
}

if($status == 0 && $today >= strtotime($start_date)){
    if($prod > 0 || $cprog > 0){
        $status = 2;
    } else {
        $status = 0;
    }
} 
elseif($status == 0 && $today > $endDate) {
    $status = 4;
}

$manager = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where id = $manager_id");
$manager = $manager->num_rows > 0 ? $manager->fetch_array() : array();

$stat = array(
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
);

// =======================================================
// PENGHITUNGAN DATA UNTUK CHART.JS
// =======================================================

$all_tasks_qry_status = $conn->query("SELECT status, end_date FROM task_list WHERE project_id = {$id}");
$task_status_counts = array_fill(0, 6, 0); 
if ($all_tasks_qry_status->num_rows > 0) {
    while($task = $all_tasks_qry_status->fetch_assoc()) {
        $status_key = $task['status'];
        $endDate_task = strtotime($task['end_date']);
        $task_status = $status_key;
        if($status_key != 5 && $status_key != 3 && $today > $endDate_task){
            $task_status = 4; 
        }
        if (isset($task_status_counts[$task_status])) {
            $task_status_counts[$task_status]++;
        }
    }
}
$donut_labels = json_encode(array_values($stat));
$donut_data = json_encode(array_values($task_status_counts));
$donut_colors = json_encode(['#6c757d', '#17a2b8', '#007bff', '#ffc107', '#dc3545', '#28a745']);

$pillar_qry = $conn->query("SELECT content_pillar, COUNT(id) as count FROM task_list WHERE project_id = {$id} GROUP BY content_pillar HAVING content_pillar IS NOT NULL AND content_pillar != ''");
$pillar_labels_arr = [];
$pillar_data_arr = [];
if ($pillar_qry->num_rows > 0) {
    while ($p_row = $pillar_qry->fetch_assoc()) {
        $pillar_labels_arr[] = ucwords($p_row['content_pillar']);
        $pillar_data_arr[] = $p_row['count'];
    }
}
if (empty($pillar_labels_arr)) {
    $pillar_labels_arr = ['No Pillar Assigned'];
    $pillar_data_arr = [1];
}
$pie_labels = json_encode($pillar_labels_arr);
$pie_data = json_encode($pillar_data_arr);
$pie_colors = json_encode(['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de', '#e83e8c', '#6f42c1']);

$all_tasks_qry = $conn->query("SELECT user_ids, status FROM task_list WHERE project_id = {$id} AND user_ids != ''");
$user_metrics = [];
$all_user_ids_str = '';
if (!empty($user_ids)) {
    $all_user_ids_str = $user_ids;
}
if ($all_tasks_qry->num_rows > 0) {
    $all_tasks_qry->data_seek(0);
    while ($task = $all_tasks_qry->fetch_assoc()) {
        $all_user_ids_str .= ',' . $task['user_ids'];
    }
}
$all_user_ids = array_unique(array_filter(explode(',', $all_user_ids_str)));
$all_user_ids_str = implode(',', $all_user_ids);

if (!empty($all_user_ids_str)) {
    $user_names_qry = $conn->query("SELECT id, concat(firstname, ' ', lastname) as name FROM users WHERE id IN ({$all_user_ids_str})");
    while ($u_row = $user_names_qry->fetch_assoc()) {
        $user_metrics[$u_row['id']] = ['name' => ucwords($u_row['name']), 'assigned' => 0, 'done' => 0];
    }
}

if ($all_tasks_qry->num_rows > 0) {
    $all_tasks_qry->data_seek(0); 
    while ($task = $all_tasks_qry->fetch_assoc()) {
        $assigned_users = array_filter(explode(',', $task['user_ids']));
        foreach ($assigned_users as $uid) {
            $uid = (int) $uid;
            if (isset($user_metrics[$uid])) {
                $user_metrics[$uid]['assigned']++;
                if ($task['status'] == 5) {
                    $user_metrics[$uid]['done']++;
                }
            }
        }
    }
}

$bar_labels = [];
$bar_data_assigned = [];
$bar_data_done = [];
foreach ($user_metrics as $metric) {
    $bar_labels[] = $metric['name'];
    $bar_data_assigned[] = $metric['assigned'];
    $bar_data_done[] = $metric['done'];
}
$bar_labels = json_encode($bar_labels);
$bar_data_assigned = json_encode($bar_data_assigned);
$bar_data_done = json_encode($bar_data_done);
?>

<div class="vp-wrap col-lg-12">
    <div class="vp-page-header">
        <div class="vp-breadcrumb">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/></svg>
            Projects
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="vp-breadcrumb-current"><?php echo ucwords($name) ?></span>
        </div>
    </div>

    <div class="vp-card">
        <div class="vp-card-head">
            <div class="vp-card-head-title">
                <div class="vp-card-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                Project Details
            </div>
            <?php if($_SESSION['login_type'] < 3): ?>
            <a href="index.php?page=edit_project&id=<?= encode_id($id) ?>" class="vp-btn-edit">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Project
            </a>
            <?php endif; ?>
        </div>

        <div class="vp-card-body">
            <div class="vp-info-grid">
                <div class="vp-info-block">
                    <div class="vp-info-row">
                        <div class="vp-label">Project Name</div>
                        <div class="vp-project-name"><?php echo ucwords($name) ?></div>
                    </div>
                    <div class="vp-info-row">
                        <div class="vp-label">Description</div>
                        <div class="vp-value"><?php $decoded_description = html_entity_decode(html_entity_decode($description)); echo strip_tags($decoded_description); ?></div>
                    </div>

                    <?php if($login_type < 4): // hide manager for clients ?>
                    <div class="vp-info-row">
                        <div class="vp-label">Project Manager</div>
                        <?php if(isset($manager['id'])) : ?>
                        <div class="vp-manager-row mt-2">
                            <img class="vp-avatar" src="assets/uploads/<?php echo !empty($manager['avatar']) ? $manager['avatar'] : 'empty-placeholder.png' ?>" alt="Avatar">
                            <div>
                                <div class="vp-manager-name text-dark font-weight-bold" style="font-size: 14px;">
                                    <?php $manager_name_parts = explode(' ', $manager['name']); ?>
                                    <?php echo ucwords($manager_name_parts[0]) ?> <span class="user-lastname"><?php echo ucwords(implode(' ', array_slice($manager_name_parts, 1))) ?></span>
                                </div>
                                <div class="vp-manager-role text-muted" style="font-size: 12px;"><?php echo ucwords($manager['job_title'] ?? 'Project Manager') ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="vp-value" style="color:var(--clr-muted);font-style:italic">Manager deleted from database</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="vp-info-divider"></div>

                <div class="vp-info-block">
                    <div class="vp-info-row">
                        <div class="vp-label">Start Date</div>
                        <div class="vp-date-pill">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php echo date("F d, Y", strtotime($start_date)) ?>
                        </div>
                    </div>
                    <div class="vp-info-row">
                        <div class="vp-label">End Date</div>
                        <div class="vp-date-pill">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php echo date("F d, Y", strtotime($end_date)) ?>
                        </div>
                    </div>
                    <div class="vp-info-row">
                        <div class="vp-label">Status</div>
                        <?php 
                            $badgeClass = [
                                0 => "secondary",
                                1 => "info",
                                2 => "primary",
                                3 => "warning",
                                4 => "danger",
                                5 => "success"
                            ];
                            $current_status = $status;
                            $bClass = isset($badgeClass[$current_status]) ? $badgeClass[$current_status] : "secondary";
                        ?>
                        <span class="vp-badge vp-badge-<?= $bClass ?>">
                            <span class="vp-badge-dot"></span>
                            <?= $stat[$current_status] ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if($login_type < 4): // hide project team for clients ?>
        <div class="vp-assignee-section">
            <div class="vp-assignee-label">
                Assignees
                <?php if(!empty($user_ids)) : $mc = $conn->query("SELECT COUNT(id) as c FROM users WHERE id IN ($user_ids)"); $mcount = $mc->fetch_assoc()['c']; echo "($mcount)"; endif; ?>
            </div>
            <div class="vp-avatar-stack">
                <?php 
                if(!empty($user_ids)):
                    $members = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where id in ($user_ids) order by concat(firstname,' ',lastname) asc");
                    while($row_m = $members->fetch_assoc()):
                ?>
                    <img src="assets/uploads/<?php echo !empty($row_m['avatar']) ? $row_m['avatar'] : 'empty-placeholder.png' ?>" 
                         alt="<?php echo ucwords($row_m['name']) ?>"
                         class="vp-av-sm"
                         title="<?php echo ucwords($row_m['name']) ?>">
                <?php 
                    endwhile;
                else:
                ?>
                    <span style="font-size:13px;color:var(--clr-muted);font-style:italic">No team member assigned</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="vp-progress-section">
            <div class="vp-progress-header">
                <div class="vp-progress-label">Overall Progress</div>
                <div class="vp-progress-right">
                    <div class="vp-progress-pct"><?php echo $prog ?>%</div>
                </div>
            </div>
            <div class="vp-progress-track">
                <div class="vp-progress-fill" id="vpProgressFill" data-pct="<?php echo $prog ?>"></div>
            </div>
            <div class="vp-progress-sub">
                Total: <strong><?php echo $cprog ?> of <?php echo $tprog ?> tasks</strong> completed.
            </div>
        </div>
    </div>
    
    <div class="container-fluid px-0">
        <div class="row mb-4 mx-0">
            <div class="col-md-12 mb-3 px-0">
                <div class="vp-stats-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B75301" stroke-width="2.5" style="flex-shrink:0"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Project Statistics
                </div>
            </div>

            <div class="col-md-4 mb-3 pl-0 pr-1">
                <div class="vp-stat-card h-100">
                    <div class="vp-stat-head">
                        <div class="vp-stat-title">Task Status</div>
                        <div class="vp-stat-tag"><?= $tprog ?> tasks</div>
                    </div>
                    <div class="vp-chart-wrap">
                        <canvas id="doughnutChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3 px-1">
                <div class="vp-stat-card h-100">
                    <div class="vp-stat-head">
                        <div class="vp-stat-title">Task Type</div>
                        <div class="vp-stat-tag"><?= count($pillar_labels_arr) ?> types</div>
                    </div>
                    <div class="vp-chart-wrap">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <?php if($login_type < 4): ?>
            <div class="col-md-4 mb-3 pr-0 pl-1">
                <div class="vp-stat-card h-100 vp-kpi-clickable" id="teamKpiCard" title="Click to view full KPI list" role="button" tabindex="0">
                    <div class="vp-stat-head">
                        <div class="vp-stat-title">Team KPI</div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <span style="display:flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#64748b">
                                <span style="width:8px;height:8px;border-radius:2px;background:#007bff;display:inline-block"></span>Assigned
                            </span>
                            <span style="display:flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#64748b">
                                <span style="width:8px;height:8px;border-radius:2px;background:#28a745;display:inline-block"></span>Done
                            </span>
                        </div>
                    </div>
                    <div class="vp-chart-wrap">
                        <canvas id="barChart"></canvas>
                    </div>
                    <div class="vp-kpi-view-hint">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        View Full List
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <hr class="vp-divider">

        <div class="row">
            <div class="col-md-12">
                <div class="row align-items-center mb-3 px-1">
                    <div class="col-6">
                        <h5 class="m-0" style="font-weight: 800; color: #333; text-transform: uppercase; font-size: 16px;">Task List</h5>
                    </div>
                    <div class="col-md-6 text-right">
                        <?php if($_SESSION['login_type'] < 3): ?>
                        <div class="card-tools">
                            <button class="btn text-white" style="background-color:#B75301;" id="new_task">
                                <i class="fa fa-plus mr-1"></i> Add Task
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive bg-white shadow-sm" style="border-radius: 15px; border: 1px solid #eee; overflow: hidden;">
                <?php $showAssignee = $login_type < 4; ?>
                <table class="table table-hover m-0" style="table-layout: fixed; width: 100%;">
                    <colgroup>
                        <col width="5%">
                        <col width="30%">
                        <col width="35%">
                        <col width="15%">
                        <?php if($showAssignee): ?>
                        <col width="15%">
                        <?php endif; ?>
                    </colgroup>
                    
                    <thead>
                        <!-- keep existing thead -->
                    </thead>
                    
                    <tbody>
                        <?php 
                        $i = 1;
                        $tasks = $conn->query("SELECT * FROM task_list WHERE project_id = {$id} ORDER BY id DESC");
                        while($row_t = $tasks->fetch_assoc()):
                            $desc = strip_tags(html_entity_decode($row_t['description']));
                            $t_stat = $row_t['status'];
                            if (strtotime(date('Y-m-d')) > strtotime($row_t['end_date']) && !in_array($t_stat, [5, 3, 0])) $t_stat = 4;
                            $badge_class = [0=>'secondary', 1=>'info', 2=>'primary', 3=>'warning', 4=>'danger', 5=>'success'][$t_stat];
                            $encoded_task_id = $encoder($row_t['id']);
                        ?>
                        <tr class="view_task_row" 
                            data-id="<?php echo $encoded_task_id ?>" 
                            data-task="<?php echo $row_t['task'] ?>" 
                            data-end-date="<?php echo $row_t['end_date'] ?>"
                            data-created-by-avatar="<?php 
                                // Fetch creator avatar for mobile display
                                $creator_avatar = 'empty-placeholder.png';
                                if (!empty($row_t['created_by'])) {
                                    $c_q = $conn->query("SELECT avatar FROM users WHERE id = '{$row_t['created_by']}' LIMIT 1");
                                    if ($c_q && $c_q->num_rows) {
                                        $c_av = $c_q->fetch_assoc()['avatar'];
                                        if (!empty($c_av)) $creator_avatar = $c_av;
                                    }
                                }
                                echo $creator_avatar;
                            ?>"
                            style="cursor:pointer; border-bottom: 1px solid #f5f5f5;">
                            <td class="text-left align-middle pl-4"><b><?php echo $i++ ?></b></td>
                            <td class="text-left align-middle">
                                <div style="font-weight: 700; color: #333; line-height: 1.2; font-size: 15px;"><?php echo ucwords($row_t['task']) ?></div>
                            </td>
                            <td class="text-left align-middle">
                                <div class="text-muted" style="font-size: 0.85rem; font-style: italic; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $desc ?>">
                                    <?php echo $desc ?>
                                </div>
                            </td>
                            <td class="text-center align-middle">
                                <span class='badge badge-<?php echo $badge_class ?> px-3 py-2' style='min-width: 90px; border-radius: 10px; font-size: 10px; text-transform: uppercase; font-weight: 800;'>
                                    <?php echo $stat[$t_stat] ?>
                                </span>
                            </td>
                            <?php if($showAssignee): ?>
                            <td class="text-center align-middle">
                                <div class="avatar-stack-container d-flex justify-content-center align-items-center animate-stack">
                                    <?php 
                                    if(!empty($row_t['user_ids'])):
                                        $u_q = $conn->query("SELECT avatar FROM users WHERE id IN ({$row_t['user_ids']}) LIMIT 5");
                                        $idx = 0;
                                        while($av = $u_q->fetch_assoc()):
                                    ?>
                                        <img src="assets/uploads/<?php echo !empty($av['avatar']) ? $av['avatar'] : 'empty-placeholder.png' ?>" 
                                            class="rolling-avatar" 
                                            style="--d: <?php echo $idx ?>; width:30px; height:30px; object-fit:cover; border-radius:50%; border:2px solid #fff; margin-left: <?php echo ($idx > 0 ? '-10px' : '0') ?>; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    <?php $idx++; endwhile; endif; ?>
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
</div>

<?php if($login_type < 4): ?>
<!-- ===================================================== -->
<!-- TEAM KPI - FULL LIST MODAL                             -->
<!-- ===================================================== -->
<div class="modal fade" id="kpiListModal" tabindex="-1" role="dialog" aria-labelledby="kpiListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:16px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#B75301; border:none;">
                <h5 class="modal-title text-white" id="kpiListModalLabel" style="font-weight:800;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px;"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Team KPI &mdash; Full List
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div id="kpiPrintable">
                    <div class="p-4 pb-2">
                        <h5 class="text-center font-weight-bold mb-1" style="color:#333;">Team KPI Report</h5>
                        <p class="text-center text-muted mb-0" style="font-size:13px;">
                            Project: <strong><?php echo ucwords($name) ?></strong> &middot; Generated <?php echo date("F d, Y") ?>
                        </p>
                    </div>
                    <div class="table-responsive px-4 pb-4 pt-2">
                        <table class="table table-hover m-0" style="width:100%;">
                            <colgroup>
                                <col width="8%">
                                <col width="32%">
                                <col width="15%">
                                <col width="15%">
                                <col width="30%">
                            </colgroup>
                            <thead>
                                <tr style="background-color:#E3E3E3;">
                                    <th class="text-left py-3 border-0 pl-3" style="font-weight:800; color:#333; text-transform:uppercase; font-size:12px;">No</th>
                                    <th class="text-left py-3 border-0" style="font-weight:800; color:#333; text-transform:uppercase; font-size:12px;">Team Member</th>
                                    <th class="text-center py-3 border-0" style="font-weight:800; color:#333; text-transform:uppercase; font-size:12px;">Assigned</th>
                                    <th class="text-center py-3 border-0" style="font-weight:800; color:#333; text-transform:uppercase; font-size:12px;">Done</th>
                                    <th class="text-left py-3 border-0 pr-3" style="font-weight:800; color:#333; text-transform:uppercase; font-size:12px;">Completion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ki = 1;
                                foreach ($user_metrics as $metric):
                                    $kpi_pct = $metric['assigned'] > 0 ? round(($metric['done'] / $metric['assigned']) * 100, 2) : 0;
                                ?>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td class="text-left pl-3 align-middle"><b><?php echo $ki++ ?></b></td>
                                    <td class="text-left align-middle" style="font-weight:700; color:#333; font-size:14px;"><?php echo $metric['name'] ?></td>
                                    <td class="text-center align-middle"><?php echo $metric['assigned'] ?></td>
                                    <td class="text-center align-middle"><?php echo $metric['done'] ?></td>
                                    <td class="pr-3 align-middle">
                                        <div class="progress" style="height:8px; margin-bottom:4px; background-color:#e9ecef; border-radius:10px;">
                                            <div class="progress-bar" role="progressbar" style="width:<?php echo $kpi_pct ?>%; background:linear-gradient(90deg,#CD874D 10%,#B75301 80%); border-radius:10px;" aria-valuenow="<?php echo $kpi_pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small style="display:block; color:#555; font-size:11px;"><?php echo $kpi_pct ?>% Complete</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($user_metrics)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4" style="font-style:italic;">No team members with assigned tasks yet.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border:none; background:#fafafa;">
                <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius:10px; font-weight:700; font-size:12px; text-transform:uppercase;">Close</button>
                <button type="button" class="btn text-white" id="kpiSavePdfBtn" style="background-color:#B75301; border-radius:10px; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; border:none; box-shadow:0 4px 14px rgba(183,83,1,0.25);">
                    <i class="fa fa-file-pdf-o mr-1"></i> Save as PDF
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    /* import font instead of using <link> inside style */
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

/* ══════════════════════════════════════════
   REDESIGN VARIABLES & BASE
══════════════════════════════════════════ */
.vp-wrap {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #f1f3f7;
    padding: 20px;
    --clr-bg:        #f1f3f7;
    --clr-surface:   #ffffff;
    --clr-border:    #e4e8f0;
    --clr-accent:    #B75301;
    --clr-accent-dk: #8f4001;
    --clr-accent-lt: #fef3ea;
    --clr-blue:      #2563eb;
    --clr-green:     #16a34a;
    --clr-text:      #18202e;
    --clr-sub:       #64748b;
    --clr-muted:     #94a3b8;
    --radius:        14px;
    --radius-sm:     9px;
    --shadow:        0 2px 12px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.04);
    --shadow-hover:  0 8px 30px rgba(0,0,0,.10);
}

/* PAGE HEADER */
.vp-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.vp-breadcrumb {
    display: flex; align-items: center; gap: 7px;
    font-size: 13px; color: var(--clr-muted); font-weight: 500;
}
.vp-breadcrumb-current {
    color: var(--clr-text); font-weight: 700; font-size: 15px;
}
.vp-btn-edit {
    display: inline-flex; align-items: center; gap: 7px;
    background: #2563eb !important; color: #fff !important;
    padding: 9px 20px; border-radius: 20px;
    font-size: 13px; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;
    text-decoration: none !important; border: none; cursor: pointer;
    transition: background .18s, transform .18s, box-shadow .18s;
    box-shadow: 0 3px 10px rgba(37,99,235,.3);
}
.vp-btn-edit:hover {
    background: #1e3a8a !important; transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(30,58,138,.4) !important; color: #fff !important;
}

/* MAIN CARD */
.vp-card {
    background: #ffffff !important;
    border-radius: 14px !important;
    border: 1.5px solid #d8dde8 !important;
    box-shadow: 0 4px 20px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.05) !important;
    margin-bottom: 22px !important;
    overflow: hidden !important;
    padding: 0 !important;
}
.vp-card-head {
    padding: 14px 22px !important;
    border-bottom: 1.5px solid #e4e8f0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
    background: #ffffff !important;
}
.vp-card-body { 
    padding: 16px 22px 10px !important; 
    background: #ffffff !important; 
}
.vp-card-head-title {
    display: flex; align-items: center; gap: 11px;
    font-size: 16px; font-weight: 800;
}
.vp-card-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--clr-accent-lt);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.vp-card-body { padding: 16px 22px 10px; background: #ffffff; }

/* BADGE */
.vp-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 13px; border-radius: 100px;
    font-size: 12px; font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.vp-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink:0; }
.vp-badge-secondary { background: #f1f5f9; color: #475569; }
.vp-badge-info      { background: #e0f2fe; color: #0369a1; }
.vp-badge-primary   { background: #dbeafe; color: #1d4ed8; }
.vp-badge-warning   { background: #fef9c3; color: #854d0e; }
.vp-badge-danger    { background: #fee2e2; color: #b91c1c; }
.vp-badge-success   { background: #dcfce7; color: #15803d; }

/* INFO GRID (Desktop 2col / Mobile 1col) */
.vp-info-grid {
    display: grid;
    grid-template-columns: 1fr 1px 1fr;
    gap: 0 28px;
}
.vp-info-divider { background: var(--clr-border); }
.vp-info-row { margin-bottom: 14px; }
.vp-info-row:last-child { margin-bottom: 0; }
.vp-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--clr-muted); margin-bottom: 5px;
}
.vp-value { font-size: 14px; font-weight: 500; color: var(--clr-text); }
.vp-project-name { font-size: 18px; font-weight: 800; line-height: 1.3; }

/* date pill */
.vp-date-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--clr-bg); padding: 6px 12px;
    border-radius: var(--radius-sm); font-size: 13px;
    font-weight: 600; color: var(--clr-sub);
    border: 1px solid var(--clr-border);
}

/* manager */
.vp-manager-row { display: flex; align-items: center; gap: 10px; }
.vp-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    object-fit: cover; border: 2.5px solid var(--clr-border); flex-shrink: 0;
}

/* assignees */
.vp-assignee-section {
    padding: 16px 22px !important;
    background: #ffffff !important;
}
.vp-assignee-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--clr-muted); margin-bottom: 10px;
}
.vp-avatar-stack { display: flex; align-items: center; flex-wrap: wrap; gap: 2px; }
.vp-av-sm {
    width: 34px; height: 34px; border-radius: 50%;
    object-fit: cover; border: 2.5px solid #fff;
    margin-left: -9px; transition: transform .15s;
    box-shadow: 0 2px 6px rgba(0,0,0,.12);
    position: relative; cursor: default;
}
.vp-av-sm:first-child { margin-left: 0; }
.vp-av-sm:hover { transform: translateY(-4px) scale(1.12); z-index: 20; }

/* PROGRESS */
.vp-progress-section {
    padding: 18px 22px 20px !important;
    background: #ffffff !important;
}
.vp-progress-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 10px; flex-wrap: wrap; gap: 6px;
}
.vp-progress-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--clr-muted);
}
.vp-progress-pct {
    font-size: 28px; font-weight: 800; color: var(--clr-accent);
    line-height: 1; font-variant-numeric: tabular-nums;
}
.vp-progress-track {
    height: 12px; background: #ede5dc; border-radius: 100px;
    overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,.08);
}
.vp-progress-fill {
    height: 100%; border-radius: 100px; width: 0;
    background: linear-gradient(90deg, #e07820 0%, #B75301 55%, #8f4001 100%);
    box-shadow: 0 2px 10px rgba(183,83,1,.35);
    transition: width 1.4s cubic-bezier(.4,0,.2,1);
    position: relative; overflow: hidden;
}
.vp-progress-fill::after {
    content: '';
    position: absolute; top: 0; left: -100%; width: 60%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
    animation: vp-shimmer 2.2s infinite;
}
@keyframes vp-shimmer { 0%{ left:-100% } 100%{ left:200% } }
.vp-progress-sub { font-size: 12px; color: var(--clr-muted); margin-top: 8px; }
.vp-progress-sub strong { color: var(--clr-sub); font-weight: 700; }

/* STATS CARDS */
.vp-stats-title {
    font-size: 16px; font-weight: 800; margin-bottom: 14px;
    display: flex; align-items: center; gap: 7px;
}

.vp-stat-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1.5px solid #d8dde8;
    box-shadow: 0 4px 20px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.05);
    padding: 20px 18px 16px;
    transition: box-shadow .2s, transform .2s;
}
.vp-stat-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-2px); }
.vp-kpi-clickable { cursor: pointer; position: relative; }
.vp-kpi-clickable:hover { border-color: var(--clr-accent); }
.vp-kpi-clickable:focus { outline: none; border-color: var(--clr-accent); }
.vp-kpi-view-hint {
    display: flex; align-items: center; justify-content: center; gap: 5px;
    margin-top: 12px; padding-top: 10px;
    border-top: 1.5px dashed var(--clr-border);
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
    color: var(--clr-accent);
}
.vp-stat-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px; padding-bottom: 12px;
    border-bottom: 1.5px solid var(--clr-border);
    flex-wrap: wrap; gap: 6px;
}
.vp-stat-title {
    font-size: 12px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .06em; color: var(--clr-sub);
}
.vp-stat-tag {
    font-size: 11px; font-weight: 700; padding: 3px 9px;
    border-radius: 100px; background: var(--clr-bg);
    color: var(--clr-muted); border: 1px solid var(--clr-border);
}
.vp-chart-wrap {
    position: relative;
    width: 100%;
}
.vp-chart-wrap canvas {
    max-height: none !important;
}
#doughnutChart,
#pieChart {
    height: 250px !important;
    min-height: 250px !important;
}
#barChart {
    height: 270px !important;
    min-height: 270px !important;
}

/* DIVIDER */
.vp-divider {
    border: none; border-top: 1.5px solid var(--clr-border);
    margin: 6px 0 22px;
}

/* FADE-UP ANIMATION */
@keyframes vp-fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
.vp-card     { animation: vp-fadeUp .35s ease both; }
.vp-stat-card { animation: vp-fadeUp .35s ease both; }


/*RESPONSIVE*/

/* TABLET (≤ 992px) */
@media (max-width: 992px) {
    .vp-info-grid {
        grid-template-columns: 1fr 1px 1fr;
        gap: 0 20px;
    }
}

/* MOBILE (≤ 640px) */
@media (max-width: 640px) {
    .vp-card-body { padding: 16px; }
    .vp-assignee-section { padding: 14px 16px; }
    .vp-progress-section { padding: 14px 16px 16px; }
    .vp-card-head { padding: 14px 16px; }

    /* Info grid jadi 1 kolom di mobile */
    .vp-info-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .vp-info-divider { display: none; }
    .vp-info-block:last-child { 
        margin-top: 18px; 
        padding-top: 18px;
        border-top: 1.5px solid var(--clr-border);
    }

    /* Progress % lebih kecil */
    .vp-progress-pct { font-size: 22px; }

    /* Breadcrumb wrap */
    .vp-page-header { flex-direction: column; align-items: flex-start; }
    .vp-btn-edit { width: 100%; justify-content: center; }
}

    /* Styling Tombol Add Task agar Identik dengan Task_list */
    #new_task {
        background-color: #B75301 !important;
        color: white !important;
        border-radius: 10px !important;
        padding: 10px 22px !important;
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
    }

    #new_task:hover {
        background-color: #964401 !important;
        transform: translateY(-2px); /* Efek melayang saat hover */
        box-shadow: 0 6px 20px rgba(183, 83, 1, 0.35) !important;
        color: white !important;
        text-decoration: none;
    }

    #new_task:active {
        transform: translateY(0);
        box-shadow: 0 4px 14px rgba(183, 83, 1, 0.25) !important;
    }

    #new_task i {
        font-size: 12px !important;
        margin-right: 6px !important;
    }

 /* CSS Motion Effect Assignee */
    .rolling-avatar {
        opacity: 0;
        transform: translateX(-20px) scale(0.5) rotate(-10deg); 
        transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        transition-delay: calc(var(--d) * 0.1s); 
        will-change: transform, opacity;
    }

    /* Aktif ketika class 'active-roll' ditambahkan oleh Observer */
    .animate-stack.active-roll .rolling-avatar {
        opacity: 1 !important;
        transform: translateX(0) scale(1) rotate(0deg) !important;
    }

    .table-hover tbody tr:hover {
        background-color: #fcfcfc !important;
    }

    /* ============================================
       MOBILE TASK LIST REDESIGN (ADDED)
       ============================================ */
    @media (max-width: 490px) {
        /* Hide the standard table */
        .table-responsive table {
            display: none !important;
        }

        /* Mobile card container */
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

        /* Individual task card */
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
            max-width: 100%;
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
        .mobile-row-date {
            font-size: 10px;
            color: #888;
            font-weight: 500;
            white-space: nowrap;
        }
    }

    /* Hide mobile cards on larger screens */
    @media (min-width: 491px) {
        .mobile-task-header,
        .mobile-task-list { display: none !important; }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function(){
        var fill = document.getElementById('vpProgressFill');
        if(fill){
            var pct = fill.getAttribute('data-pct') || '0';
            setTimeout(function(){ fill.style.width = pct + '%'; }, 350);
        }
    });

    function uni_modal(title, url, size = "mid-large") {
        if ($('#uni_modal .summernote').length) {
            $('#uni_modal .summernote').summernote('destroy');
        }
        if (typeof start_load !== 'undefined') { start_load(); } 
        
        $.ajax({
            url: url,
            success: function(resp) {
                if (resp) {
                    $('#uni_modal .modal-title').html(title);
                    $('#uni_modal .modal-body').html(resp);
                    $('#uni_modal .modal-dialog').removeClass('mid-large large').addClass(size); 
                    if ($('#uni_modal .select2').length) {
                        $('#uni_modal .select2').select2({
                            dropdownParent: $('#uni_modal'), 
                            width: '100%'
                        });
                    }
                    if ($('#uni_modal .summernote').length) {
                        $('#uni_modal .summernote').summernote({ 
                            height: 200,
                            toolbar: [
                                ['style', ['style']],
                                ['font', ['bold', 'italic', 'underline', 'clear']],
                                ['color', ['color']],
                                ['para', ['ul', 'ol', 'paragraph']],
                                ['insert', ['link', 'picture']],
                                ['view', ['codeview']]
                            ]
                        });
                    }
                    $('#uni_modal').modal('show');
                }
                if (typeof end_load !== 'undefined') { end_load(); } 
            },
            error: function() {
                if (typeof end_load !== 'undefined') { end_load(); } 
            }
        });
    }

    function delete_progress($id){
        if (typeof start_load !== 'undefined') { start_load(); }
        $.ajax({
            url:'ajax.php?action=delete_progress',
            method:'POST',
            data:{id:$id},
            success:function(resp){
                if(resp==1){
                    alert_toast("Data successfully deleted",'success')
                    setTimeout(function(){ location.reload() },1500)
                }
            }
        })
    }
    function delete_task(id){
        if (typeof start_load !== 'undefined') { start_load(); }
        $.ajax({
            url: 'ajax.php?action=delete_task',
            method: 'POST',
            data: { id: id },
            success: function(resp){
                if(resp == 1){
                    alert_toast("Task successfully deleted", 'success')
                    setTimeout(function(){  location.reload() }, 1500)
                }
            }
        })
    }
    
    function edit_task(encodedTaskId, taskName = 'Task'){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>';
        uni_modal("Edit Task: " + taskName, "manage_task.php?pid=" + encodedProjectId + "&id=" + encodedTaskId, "mid-large");
    }

    $('#new_task').click(function(){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>';
        uni_modal("New Task For <?php echo ucwords($name) ?>", "manage_task.php?pid=" + encodedProjectId + "&id=", "mid-large");
    })
    $('.edit_task').click(function(){
        const taskId = $(this).attr('data-id');
        const taskName = $(this).attr('data-task');
        edit_task(taskId, taskName);
    });
    $('.view_task').click(function(){
        uni_modal("Task Details","view_task.php?id="+$(this).attr('data-id'),"mid-large")
    })
    $('.delete_task').click(function(){
        _conf("Are you sure to delete this task?", "delete_task", [$(this).attr('data-id')])
    })
    $('#new_productivity').click(function(){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>';
        uni_modal("<i class='fa fa-plus'></i> New Progress","manage_progress.php?pid=" + encodedProjectId,'large')
    })
    $('.manage_progress').click(function(){
        const encodedProjectId = '<?php echo $encoded_project_id; ?>';
        uni_modal("<i class='fa fa-edit'></i> Edit Progress","manage_progress.php?pid=" + encodedProjectId + "&id=" + $(this).attr('data-id'),'large')
    })
    $('.delete_progress').click(function(){
        _conf("Are you sure to delete this progress?","delete_progress",[$(this).attr('data-id')])
    })
    $('.view_task_row').click(function(e) {
        if (
            !$(e.target).closest('.dropdown').length &&
            !$(e.target).is('button') &&
            !$(e.target).is('a')
        ) {
            const taskId = $(this).data('id'); 
            const taskName = $(this).data('task'); 
            uni_modal("Task: " + taskName, "get_task_detail.php?id=" + taskId, "mid-large");
        }
    });
    $('#uni_modal').off('hidden.bs.modal');

    // ============================================
    // TEAM KPI - FULL LIST MODAL + SAVE AS PDF
    // ============================================
    $('#teamKpiCard').on('click', function(){
        $('#kpiListModal').modal('show');
    });
    $('#teamKpiCard').on('keypress', function(e){
        if (e.which === 13 || e.which === 32) {
            $('#kpiListModal').modal('show');
        }
    });

    $('#kpiSavePdfBtn').click(function(){
        var content = $('#kpiPrintable').clone();

        // Flatten styling for a clean print output
        content.find('.progress').css({'border': '1px solid #000', 'background': 'none'});
        content.find('.progress-bar').css({'background-color': '#000', 'background-image': 'none'});

        var printWindow = window.open('', '', 'width=900,height=600');
        var headContent = `
            <html>
                <head>
                    <title>Team KPI Report - <?php echo ucwords($name) ?></title>
                    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 12px; }
                        .text-center { text-align: center; }
                    </style>
                </head>
                <body>
        `;

        printWindow.document.write(headContent + content.html() + '</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function(){
            printWindow.print();
            printWindow.close();
        }, 1000);
    });

    $(document).ready(function() {
        if (typeof Chart === 'undefined') {
            console.error("Chart.js library is not loaded.");
            return; 
        }
        
        const barLabels = <?php echo $bar_labels; ?>;
        const barDataAssigned = <?php echo $bar_data_assigned; ?>;
        const barDataDone = <?php echo $bar_data_done; ?>;

        const barCtx = document.getElementById('barChart');
        if (barCtx) {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [
                        {
                            label: 'Tasks Assigned',
                            data: barDataAssigned,
                            backgroundColor: '#007bff',
                            borderColor: '#007bff',
                            borderWidth: 1
                        },
                        {
                            label: 'Tasks Done',
                            data: barDataDone,
                            backgroundColor: '#28a745',
                            borderColor: '#28a745',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle' } },
                    scales: {
                        yAxes: [{ ticks: { beginAtZero: true, stepSize: 1 } }],
                        xAxes: [{ ticks: { display: false }, gridLines: { display: false }, barPercentage: 0.8, categoryPercentage: 0.6 }]
                    }
                }
            });
        }

        const donutLabels = <?php echo $donut_labels; ?>;
        const donutData = <?php echo $donut_data; ?>;
        const donutColors = <?php echo $donut_colors; ?>;

        const doughnutCtx = document.getElementById('doughnutChart');
        if (doughnutCtx) {
            new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels: donutLabels,
                    datasets: [{ data: donutData, backgroundColor: donutColors, borderColor: '#fff', borderWidth: 2 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle' } },
                }
            });
        }

        const pieLabels = <?php echo $pie_labels; ?>;
        const pieData = <?php echo $pie_data; ?>;
        const pieColors = <?php echo $pie_colors; ?>;

        const pieCtx = document.getElementById('pieChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: pieLabels,
                    datasets: [{ data: pieData, backgroundColor: pieColors, borderColor: '#fff', borderWidth: 2 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle' } },
                }
            });
        }
    });

    $(document).ready(function() {
        const observerOptions = { root: null, threshold: 0.2 };
        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    $(entry.target).addClass('active-roll');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-stack').forEach(stack => {
            observer.observe(stack);
        });
    });
         // ============================================
        // MOBILE TASK LIST BUILDER (FIXED)
        // ============================================
        function buildMobileTaskList() {
            if ($(window).width() > 490) return;
            var $wrapper = $('.table-responsive');
            if ($wrapper.length === 0 || $wrapper.find('.mobile-task-header').length > 0) return;

            var $header = $('<div class="mobile-task-header">All Task</div>');
            var $list   = $('<div class="mobile-task-list"></div>');
            var counter = 1;

            $wrapper.find('tbody tr.view_task_row').each(function() {
                var $tr = $(this);
                var encodedId = $tr.data('id');
                var taskName = $tr.find('td:nth-child(2) div').first().text().trim();
                var taskDesc = $tr.find('td:nth-child(3) div').text().trim();
                
                // Status badge
                var $badge = $tr.find('td:nth-child(4) .badge');
                var badgeText = $badge.text().trim().toUpperCase();
                var badgeCls = 'mobile-badge-pending';
                if ($badge.hasClass('badge-success'))   badgeCls = 'mobile-badge-done';
                if ($badge.hasClass('badge-danger'))    badgeCls = 'mobile-badge-overdue';
                if ($badge.hasClass('badge-warning'))   badgeCls = 'mobile-badge-hold';
                if ($badge.hasClass('badge-primary'))   badgeCls = 'mobile-badge-progress';
                if ($badge.hasClass('badge-info'))      badgeCls = 'mobile-badge-started';
                if ($badge.hasClass('badge-secondary')) badgeCls = 'mobile-badge-pending';

                // Due date from data attribute
                var endDate = $tr.data('end-date');
                var formattedDate = '';
                if (endDate) {
                    var d = new Date(endDate);
                    if (!isNaN(d)) {
                        formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    } else {
                        formattedDate = endDate;
                    }
                }

                // Creator avatar from data attribute
                var creatorAvatar = $tr.data('created-by-avatar') || 'empty-placeholder.png';
                var $creatorImg = $('<img>').attr('src', 'assets/uploads/' + creatorAvatar)
                    .css({width:'28px', height:'28px', objectFit:'cover', borderRadius:'50%', border:'2px solid #fff'});

                // Assigned avatars from column 5 (if exists)
                var $assigneeImgs = $tr.find('td:nth-child(5) img').clone()
                                    .css({opacity:1, transform:'none'});

                var descHtml = taskDesc
                    ? '<div class="mobile-row-desc">' + taskDesc + '</div>' : '';

                // Build card with date in the top row
                var $row = $(
                    '<div class="mobile-task-row" data-id="' + encodedId + '">' +
                        '<span class="mobile-row-no">' + counter + '</span>' +
                        '<div class="mobile-row-content">' +
                            '<div class="mobile-row-top">' +
                                '<span class="mobile-row-name">' + taskName + '</span>' +
                                '<div class="mobile-row-right">' +
                                    (formattedDate ? '<span class="mobile-row-date">' + formattedDate + '</span>' : '') +
                                    '<span class="mobile-status-badge ' + badgeCls + '">' + badgeText + '</span>' +
                                '</div>' +
                            '</div>' +
                            descHtml +
                            '<div class="mobile-row-bottom">' +
                                '<div class="mobile-av-group">' +
                                    '<span class="mobile-av-label">Created By</span>' +
                                    '<div class="mobile-av-stack"></div>' +
                                '</div>' +
                                ($assigneeImgs.length ? 
                                    '<div class="mobile-av-group">' +
                                        '<span class="mobile-av-label">Assigned</span>' +
                                        '<div class="mobile-av-stack"></div>' +
                                    '</div>' : '') +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );

                // Insert creator avatar
                $row.find('.mobile-av-stack').eq(0).append($creatorImg);
                // Insert assignee avatars if present
                if ($assigneeImgs.length) {
                    $row.find('.mobile-av-stack').eq(1).append($assigneeImgs);
                }

                $row.on('click', function() {
                    uni_modal("Task Details", "get_task_detail.php?id=" + encodedId, "mid-large");
                });

                $list.append($row);
                counter++;
            });

            $wrapper.append($header).append($list);
        }

        // Initialize mobile layout on page load
        $(document).ready(function() {
            buildMobileTaskList();
            
            // Re-run on window resize (debounced)
            var resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    // Remove existing mobile elements if screen becomes large
                    if ($(window).width() > 490) {
                        $('.mobile-task-header, .mobile-task-list').remove();
                    } else {
                        // Rebuild if mobile and elements are missing
                        if ($('.table-responsive').find('.mobile-task-header').length === 0) {
                            buildMobileTaskList();
                        }
                    }
                }, 150);
            });
        });
</script>