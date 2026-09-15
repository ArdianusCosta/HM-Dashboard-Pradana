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

$clean_filename = "KPI_Report_" . preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', $user_fullname)) . "_" . date("Ymd") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$clean_filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>KPI Report - <?= htmlspecialchars($user_fullname) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header-title { font-size: 16px; font-weight: bold; color: #ffffff; background-color: #B75301; padding: 10px; text-align: center; }
        .meta-table { margin-bottom: 15px; width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 6px; border: 1px solid #dcdcdc; }
        .meta-label { font-weight: bold; background-color: #f2f2f2; width: 20%; }
        .summary-header { background-color: #2C3E50; color: #ffffff; font-weight: bold; text-align: center; padding: 8px; }
        .summary-value { font-size: 14px; font-weight: bold; text-align: center; padding: 8px; background-color: #f8f9fa; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th { background-color: #B75301; color: #ffffff; padding: 8px; font-weight: bold; text-align: left; border: 1px solid #9A4500; }
        .data-table td { padding: 7px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .project-row { background-color: #e2e8f0; font-weight: bold; }
        .status-done { background-color: #d4edda; color: #155724; font-weight: bold; text-align: center; }
        .status-overdue { background-color: #f8d7da; color: #721c24; font-weight: bold; text-align: center; }
        .status-progress { background-color: #cce5ff; color: #004085; font-weight: bold; text-align: center; }
        .status-started { background-color: #d1ecf1; color: #0c5460; font-weight: bold; text-align: center; }
        .status-pending { background-color: #e2e3e5; color: #383d41; font-weight: bold; text-align: center; }
    </style>
</head>
<body>

    <table class="meta-table">
        <tr>
            <td colspan="4" class="header-title">KPI PERFORMANCE REPORT &mdash; PT PRADANA NUSA ENERGI</td>
        </tr>
        <tr>
            <td class="meta-label">Nama Karyawan:</td>
            <td><strong><?= htmlspecialchars($user_fullname) ?></strong></td>
            <td class="meta-label">Tanggal Export:</td>
            <td><?= date("d F Y H:i:s") ?></td>
        </tr>
        <tr>
            <td class="meta-label">Email:</td>
            <td><?= htmlspecialchars($user_email) ?></td>
            <td class="meta-label">Role / Posisi:</td>
            <td><?= htmlspecialchars($user_role) ?></td>
        </tr>
        <tr>
            <td class="meta-label">Staff NIK:</td>
            <td><?= htmlspecialchars($user_nik) ?></td>
            <td class="meta-label">Overall KPI Score:</td>
            <td><strong style="color: #B75301; font-size: 13px;"><?= $overall_kpi ?>%</strong></td>
        </tr>
    </table>

    <br>

    <table class="meta-table" style="width: 60%; margin: 0 auto 20px auto;">
        <tr>
            <td class="summary-header">TOTAL PROYEK</td>
            <td class="summary-header">TOTAL TUGAS (SELESAI / TOTAL)</td>
            <td class="summary-header">OVERALL KPI SCORE</td>
        </tr>
        <tr>
            <td class="summary-value"><?= count($projects_list) ?> Proyek</td>
            <td class="summary-value"><?= $total_done_overall ?> / <?= $total_assigned_overall ?> Tasks</td>
            <td class="summary-value" style="color: #B75301;"><?= $overall_kpi ?>%</td>
        </tr>
    </table>

    <br>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 25%;">Nama Proyek</th>
                <th style="width: 12%; text-align: center;">Status Proyek</th>
                <th style="width: 28%;">Nama Tugas</th>
                <th style="width: 12%;">Content Pillar</th>
                <th style="width: 10%; text-align: center;">Status Tugas</th>
                <th style="width: 8%; text-align: center;">Deadline</th>
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
                        foreach ($tasks as $t_index => $t):
                            $t_status_name = $stat_map[$t['status']] ?? 'Unknown';
                            $status_class = 'status-pending';
                            if ($t['status'] == 5) $status_class = 'status-done';
                            elseif ($t['status'] == 4) $status_class = 'status-overdue';
                            elseif ($t['status'] == 2) $status_class = 'status-progress';
                            elseif ($t['status'] == 1) $status_class = 'status-started';
            ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><strong><?= htmlspecialchars(ucwords($p['name'])) ?></strong> (<?= $p['done'] ?>/<?= $p['assigned'] ?> Done - <?= $p['kpi_pct'] ?>%)</td>
                    <td style="text-align: center;"><?= $p_status_name ?></td>
                    <td><?= htmlspecialchars(ucwords($t['task'])) ?></td>
                    <td><?= htmlspecialchars($t['content_pillar'] ?? '-') ?></td>
                    <td class="<?= $status_class ?>"><?= $t_status_name ?></td>
                    <td style="text-align: center;"><?= !empty($t['end_date']) ? date('d/m/Y', strtotime($t['end_date'])) : '-' ?></td>
                </tr>
            <?php 
                        endforeach;
                    else:
            ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><strong><?= htmlspecialchars(ucwords($p['name'])) ?></strong></td>
                    <td style="text-align: center;"><?= $p_status_name ?></td>
                    <td colspan="4" style="font-style: italic; color: #888;">Tidak ada tugas spesifik yang ditugaskan ke user ini pada proyek ini.</td>
                </tr>
            <?php 
                    endif;
                endforeach;
            else:
            ?>
                <tr>
                    <td colspan="7" style="text-align: center; font-style: italic; padding: 15px;">User ini belum memiliki proyek atau tugas yang ditugaskan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
