<?php include 'db_connect.php'; ?>

<?php
if (isset($_GET['id'])) {
    $encoded_id = $_GET['id'];
    $id = decode_id($encoded_id);

    if (!is_null($id)) {
        $type_arr = array('', "Admin", "Project Manager", "Employee", "Client");
        $qry = $conn->query("SELECT *, CONCAT(firstname,' ',lastname) AS name FROM users WHERE id = $id");

        if ($qry && $qry->num_rows > 0) {
            $user_row = $qry->fetch_assoc();
        } else {
            echo "<div class='p-3 text-center text-danger'>User tidak ditemukan.</div>";
            exit;
        }
    } else {
        echo "<div class='p-3 text-center text-danger'>Parameter ID tidak valid.</div>";
        exit;
    }
} else {
    echo "<div class='p-3 text-center text-danger'>Parameter ID tidak valid.</div>";
    exit;
}

$stat_map = array(
    0 => array('name' => "Pending", 'badge' => "badge-secondary"),
    1 => array('name' => "Started", 'badge' => "badge-info"),
    2 => array('name' => "On-Progress", 'badge' => "badge-primary"),
    3 => array('name' => "On-Hold", 'badge' => "badge-warning"),
    4 => array('name' => "Over Due", 'badge' => "badge-danger"),
    5 => array('name' => "Done", 'badge' => "badge-success")
);

// Query project di mana user adalah manager, team member, atau memiliki task
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
$user_avatar = !empty($user_row['avatar']) && is_file('assets/uploads/'.$user_row['avatar']) ? 'assets/uploads/'.$user_row['avatar'] : 'assets/uploads/empty-placeholder.png';
?>

<style>
  #uni_modal {
      overflow-y: auto !important;
  }
  #uni_modal .modal-dialog {
      max-height: 92vh;
  }
  #uni_modal .modal-content {
      max-height: 92vh;
      display: flex;
      flex-direction: column;
  }
  #uni_modal .modal-body {
      max-height: calc(92vh - 65px) !important;
      overflow-y: auto !important;
      -webkit-overflow-scrolling: touch;
  }
</style>

<div class="container-fluid p-2">
  <!-- USER HEADER WITH EXPORT EXCEL & EXPORT PDF BUTTONS -->
  <div class="d-flex align-items-center justify-content-between p-3 mb-3 bg-white rounded shadow-sm border flex-wrap" style="gap: 10px;">
    <div class="d-flex align-items-center">
      <img src="<?= $user_avatar ?>" class="rounded-circle border mr-3" style="width: 55px; height: 55px; object-fit: cover;">
      <div>
        <h5 class="font-weight-bold mb-1" style="color: #1a1714;"><?= htmlspecialchars(ucwords($user_row['firstname'] . ' ' . $user_row['lastname'])) ?></h5>
        <span class="badge badge-amber text-white px-2 py-1" style="background-color:#B75301; font-size:11px;"><?= $type_arr[$user_row['type']] ?? 'Employee' ?></span>
        <span class="text-muted ml-2" style="font-size:12px;"><i class="fa fa-envelope mr-1"></i><?= htmlspecialchars($user_row['email']) ?></span>
      </div>
    </div>
    <div class="d-flex align-items-center" style="gap: 8px;">
      <a href="export_user_kpi_excel.php?id=<?= $encoded_id ?>" target="_blank" class="btn btn-success btn-sm font-weight-bold shadow-sm" style="border-radius: 8px; padding: 7px 14px; background-color: #28a745; border-color: #28a745;">
        <i class="fa fa-file-excel-o mr-1"></i> Export Excel
      </a>
      <a href="export_user_kpi_pdf.php?id=<?= $encoded_id ?>" target="_blank" class="btn btn-danger btn-sm font-weight-bold shadow-sm" style="border-radius: 8px; padding: 7px 14px; background-color: #dc3545; border-color: #dc3545;">
        <i class="fa fa-file-pdf-o mr-1"></i> Export PDF
      </a>
    </div>
  </div>

  <!-- SUMMARY STRIP -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-header py-2" style="background-color: #B75301 !important; color: #000000ff !important; border-radius: 8px 8px 0 0;">
      <h6 class="card-title mb-0 font-weight-bold" style="font-size: 0.95rem; color: #000000ff !important; float: none !important;">
        <i class="fa fa-chart-line mr-2" style="color: #000000ff !important;"></i> Team KPI &mdash; Projects & Tasks Breakdown
      </h6>
    </div>
    <div class="card-body p-3">
      
      <div class="row mb-3 p-2 bg-light rounded text-center border" style="margin: 0;">
        <div class="col-4 border-right">
          <small class="text-muted d-block text-uppercase" style="font-size:10px; font-weight:700;">Total Projects</small>
          <span class="font-weight-bold" style="font-size:1.1rem; color:#333;"><?= count($projects_list) ?></span>
        </div>
        <div class="col-4 border-right">
          <small class="text-muted d-block text-uppercase" style="font-size:10px; font-weight:700;">Tasks (Done/Total)</small>
          <span class="font-weight-bold" style="font-size:1.1rem; color:#333;"><?= $total_done_overall ?> / <?= $total_assigned_overall ?></span>
        </div>
        <div class="col-4">
          <small class="text-muted d-block text-uppercase" style="font-size:10px; font-weight:700;">Overall KPI</small>
          <span class="font-weight-bold" style="font-size:1.1rem; color:#B75301;"><?= $overall_kpi ?>%</span>
        </div>
      </div>

      <!-- PROJECTS ACCORDION -->
      <?php if(!empty($projects_list)): ?>
        <div class="accordion" id="kpiModalAccordion">
          <?php foreach($projects_list as $index => $p): 
              $p_status = $stat_map[$p['status']] ?? array('name' => 'Unknown', 'badge' => 'badge-secondary');
          ?>
            <div class="card mb-2 border rounded" style="box-shadow: none;">
              <div class="card-header p-2 bg-white d-flex align-items-center justify-content-between" id="modalKpiHeading<?= $p['id'] ?>">
                <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                  <span class="badge <?= $p_status['badge'] ?>" style="font-size: 10px;"><?= $p_status['name'] ?></span>
                  <strong style="color: #2c3e50; font-size: 0.9rem;"><?= htmlspecialchars(ucwords($p['name'])) ?></strong>
                </div>
                <div class="d-flex align-items-center" style="gap: 8px;">
                  <span class="badge badge-light border" style="font-size: 11px;">
                    <b><?= $p['done'] ?></b>/<b><?= $p['assigned'] ?></b> Tasks (<b><?= $p['kpi_pct'] ?>%</b>)
                  </span>
                  <button class="btn btn-sm btn-outline-secondary py-0 px-2" type="button" data-toggle="collapse" data-target="#modalKpiCollapse<?= $p['id'] ?>" aria-expanded="false" aria-controls="modalKpiCollapse<?= $p['id'] ?>">
                    <i class="fa fa-chevron-down" style="font-size: 10px;"></i>
                  </button>
                </div>
              </div>

              <!-- KPI Progress Bar -->
              <div class="px-2 pt-1 pb-1 bg-light border-top border-bottom">
                <div class="progress" style="height: 6px; background-color: #e9ecef; border-radius: 4px;">
                  <div class="progress-bar" role="progressbar" style="width: <?= $p['kpi_pct'] ?>%; background: linear-gradient(90deg, #CD874D 0%, #B75301 100%);" aria-valuenow="<?= $p['kpi_pct'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </div>

              <!-- Collapsible Tasks List -->
              <div id="modalKpiCollapse<?= $p['id'] ?>" class="collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="modalKpiHeading<?= $p['id'] ?>" data-parent="#kpiModalAccordion">
                <div class="card-body p-2">
                  <?php if(!empty($p['tasks'])): ?>
                    <div class="table-responsive">
                      <table class="table table-sm table-hover mb-0" style="font-size: 0.8rem;">
                        <thead>
                          <tr class="text-muted" style="background-color: #f8f9fa;">
                            <th>Nama Tugas</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Deadline</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach($p['tasks'] as $t): 
                              $t_status = $stat_map[$t['status']] ?? array('name' => 'Unknown', 'badge' => 'badge-secondary');
                          ?>
                            <tr>
                              <td>
                                <span class="font-weight-bold" style="color: #333;"><?= htmlspecialchars(ucwords($t['task'])) ?></span>
                                <?php if(!empty($t['content_pillar'])): ?>
                                  <small class="text-muted d-block"><i class="fa fa-tag text-warning"></i> <?= htmlspecialchars($t['content_pillar']) ?></small>
                                <?php endif; ?>
                              </td>
                              <td class="text-center align-middle">
                                <span class="badge <?= $t_status['badge'] ?>" style="font-size: 10px;"><?= $t_status['name'] ?></span>
                              </td>
                              <td class="text-center align-middle text-muted" style="white-space: nowrap;">
                                <?= date('d M Y', strtotime($t['end_date'])) ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <p class="text-muted font-italic mb-0 p-2 text-center" style="font-size: 0.8rem;">Belum ada tugas spesifik yang ditugaskan ke user ini pada proyek ini.</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center text-muted py-3 font-italic" style="font-size: 0.85rem;">
          User ini belum terlibat dalam proyek atau tugas manapun.
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>
