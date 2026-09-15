<?php
include 'db_connect.php';

if (!isset($_GET['id'])) {
    die("Parameter ID tidak ditemukan.");
}

$encoded_id = $_GET['id'];
$id = decode_id($encoded_id);

if (is_null($id) || $id <= 0) {
    die("Parameter ID tidak valid.");
}

$type_arr = array('', "Admin", "Project Manager", "Employee", "Client");
$qry = $conn->query("SELECT *, CONCAT(firstname,' ',lastname) AS name FROM users WHERE id = $id");

if (!$qry || $qry->num_rows == 0) {
    die("User tidak ditemukan.");
}

$user_row = $qry->fetch_assoc();
$user_fullname = ucwords($user_row['firstname'] . ' ' . $user_row['lastname']);
$user_role = $type_arr[$user_row['type']] ?? 'Employee';
$user_email = $user_row['email'];
$user_nik = !empty($user_row['nik']) ? $user_row['nik'] : '-';

$stat_map = array(
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
);

// Query project & tasks
$projects_list = [];
$p_qry = $conn->query("
    SELECT DISTINCT p.* 
    FROM project_list p 
    LEFT JOIN task_list t ON t.project_id = p.id AND FIND_IN_SET('{$id}', t.user_ids) > 0
    WHERE p.manager_id = {$id} 
       OR FIND_IN_SET('{$id}', p.user_ids) > 0 
       OR t.id IS NOT NULL
    ORDER BY p.name ASC
");

$total_assigned_overall = 0;
$total_done_overall = 0;

if ($p_qry && $p_qry->num_rows > 0) {
    while ($p_row = $p_qry->fetch_assoc()) {
        $pid = $p_row['id'];
        
        $t_qry = $conn->query("
            SELECT * FROM task_list 
            WHERE project_id = {$pid} AND FIND_IN_SET('{$id}', user_ids) > 0 
            ORDER BY id DESC
        ");
        
        $assigned_count = $t_qry ? $t_qry->num_rows : 0;
        $done_count = 0;
        $tasks = [];
        
        if ($t_qry && $assigned_count > 0) {
            while ($t = $t_qry->fetch_assoc()) {
                if ($t['status'] == 5) {
                    $done_count++;
                }
                $tasks[] = $t;
            }
        }
        
        $kpi_pct = $assigned_count > 0 ? round(($done_count / $assigned_count) * 100, 1) : 0;
        
        $projects_list[] = [
            'id' => $pid,
            'name' => $p_row['name'],
            'status' => $p_row['status'],
            'assigned' => $assigned_count,
            'done' => $done_count,
            'kpi_pct' => $kpi_pct,
            'tasks' => $tasks
        ];
        
        $total_assigned_overall += $assigned_count;
        $total_done_overall += $done_count;
    }
}

$overall_kpi = $total_assigned_overall > 0 ? round(($total_done_overall / $total_assigned_overall) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>KPI Report PDF - <?= htmlspecialchars($user_fullname) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #fff; color: #222; padding: 20px; }
        .kpi-header-banner { background: linear-gradient(135deg, #B75301 0%, #8A3D00 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .kpi-header-banner h4 { font-weight: 800; margin-bottom: 5px; }
        .kpi-header-banner p { font-size: 13px; opacity: 0.9; margin: 0; }
        .meta-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 12px; text-align: center; }
        .stat-label { font-size: 11px; font-weight: 700; color: #6c757d; text-transform: uppercase; }
        .stat-value { font-size: 20px; font-weight: 800; color: #B75301; }
        .table th { background-color: #2C3E50; color: white; font-size: 12px; text-transform: uppercase; font-weight: 700; border: none; }
        .table td { font-size: 12px; vertical-align: middle !important; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .kpi-header-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #2C3E50 !important; color: white !important; }
            .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="no-print mb-3 text-right">
    <button onclick="window.print()" class="btn btn-primary btn-sm font-weight-bold"><i class="fa fa-print mr-1"></i> Print / Save as PDF</button>
    <button onclick="window.close()" class="btn btn-secondary btn-sm font-weight-bold ml-2"><i class="fa fa-times mr-1"></i> Close</button>
</div>

<div class="kpi-header-banner">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4>PT PRADANA NUSA ENERGI</h4>
            <p>TEAM KPI & PERFORMANCE REPORT &mdash; <?= htmlspecialchars(strtoupper($user_fullname)) ?></p>
        </div>
        <div class="text-right">
            <small class="d-block">Generated Date:</small>
            <strong><?= date('d F Y H:i') ?></strong>
        </div>
    </div>
</div>

<div class="meta-box">
    <div class="row align-items-center">
        <div class="col-6">
            <table class="table table-borderless table-sm m-0" style="font-size: 13px;">
                <tr><td width="35%" class="text-muted font-weight-bold">Employee Name:</td><td><strong><?= htmlspecialchars($user_fullname) ?></strong></td></tr>
                <tr><td class="text-muted font-weight-bold">Email Address:</td><td><?= htmlspecialchars($user_email) ?></td></tr>
                <tr><td class="text-muted font-weight-bold">Role / Position:</td><td><?= htmlspecialchars($user_role) ?></td></tr>
            </table>
        </div>
        <div class="col-6">
            <div class="row">
                <div class="col-4">
                    <div class="stat-card">
                        <div class="stat-label">Projects</div>
                        <div class="stat-value" style="color:#333;"><?= count($projects_list) ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card">
                        <div class="stat-label">Tasks Done</div>
                        <div class="stat-value" style="color:#333;"><?= $total_done_overall ?>/<?= $total_assigned_overall ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card" style="border-color:#B75301; background-color:#fdf8f4;">
                        <div class="stat-label" style="color:#B75301;">Overall KPI</div>
                        <div class="stat-value"><?= $overall_kpi ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<h6 class="font-weight-bold mb-3" style="color:#333;"><i class="fa fa-list mr-2"></i>Projects & Tasks Breakdown</h6>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th width="5%" class="text-center">No</th>
            <th width="27%">Project Name</th>
            <th width="13%" class="text-center">Project Status</th>
            <th width="30%">Task Name</th>
            <th width="12%">Content Pillar</th>
            <th width="13%" class="text-center">Task Status</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if (!empty($projects_list)):
            foreach ($projects_list as $p):
                $p_status_name = $stat_map[$p['status']] ?? 'Unknown';
                $tasks = $p['tasks'];
                if (!empty($tasks)):
                    foreach ($tasks as $t):
                        $t_status_name = $stat_map[$t['status']] ?? 'Unknown';
                        $badge_class = 'badge-secondary';
                        if ($t['status'] == 5) $badge_class = 'badge-success';
                        elseif ($t['status'] == 4) $badge_class = 'badge-danger';
                        elseif ($t['status'] == 2) $badge_class = 'badge-primary';
                        elseif ($t['status'] == 1) $badge_class = 'badge-info';
        ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars(ucwords($p['name'])) ?></strong><br><small class="text-muted"><?= $p['done'] ?>/<?= $p['assigned'] ?> Done (<?= $p['kpi_pct'] ?>%)</small></td>
                <td class="text-center"><span class="badge badge-light border"><?= $p_status_name ?></span></td>
                <td><strong><?= htmlspecialchars(ucwords($t['task'])) ?></strong></td>
                <td><?= htmlspecialchars($t['content_pillar'] ?? '-') ?></td>
                <td class="text-center"><span class="badge <?= $badge_class ?>"><?= $t_status_name ?></span></td>
            </tr>
        <?php 
                    endforeach;
                else:
        ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars(ucwords($p['name'])) ?></strong></td>
                <td class="text-center"><span class="badge badge-light border"><?= $p_status_name ?></span></td>
                <td colspan="3" class="text-muted font-italic">No tasks assigned in this project</td>
            </tr>
        <?php 
                endif;
            endforeach;
        else:
        ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No project data available.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<script>
    window.onload = function() {
        setTimeout(function(){
            window.print();
        }, 500);
    };
</script>
</body>
</html>
