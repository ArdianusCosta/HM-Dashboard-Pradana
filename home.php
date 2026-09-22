<?php include('db_connect.php') ?>
<?php
// Pastikan sesi dimulai dan login_type serta login_id tersedia
if (!isset($_SESSION['login_type'])) {
    // Tambahkan logika redirect ke login jika sesi tidak ada
}

$user_id = $_SESSION['login_id'] ?? 0;
$login_type = $_SESSION['login_type'] ?? 1;

// --- FUNGSI ENCODER (tetap dipertahankan) ---
if (!function_exists('encode_id')) {
    function encode_id($id) {
        return base64_encode($id);
    }
}

if (!function_exists('render_activity_description')) {
    function render_activity_description($description) {
        $content = html_entity_decode((string)$description, ENT_QUOTES, 'UTF-8');
        $content = strip_tags($content, '<img>');

        return preg_replace_callback('/<img\b([^>]*)>/i', function ($match) {
            if (!preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $match[1], $src_match)) {
                return '';
            }

            $src = $src_match[1];
            $is_relative = preg_match('/^(?!\/\/)[a-z0-9._\/-]+$/i', $src);
            $is_http = filter_var($src, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $src);
            if (!$is_relative && !$is_http) {
                return '';
            }

            return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" style="max-width:100%;height:auto;border-radius:6px;margin-top:4px;">';
        }, $content);
    }
}
?>
<?php 
include 'header.php' 
?>

<head>
  <link rel="stylesheet" href="css/style.css">
</head>
<div class="col-12">    
  <h3 class="font-weight-bold animate-title" style="color:#b75301;">
    Hi, <?php echo $_SESSION['login_name'] ?>! 
  </h3>
  <p class="animate-subtitle" >Let's finish your tasks today! </p>
  </div>
  <hr>
  
  <?php 
    // =========================================================================
    // 1. PENDEFINISIAN WHERE CLAUSE KONSISTEN
    // =========================================================================

    // Filter untuk Project List (Project Membership/Management)
    $where_project = "";
    if ($login_type == 2) { // manager

        // REVISI UTAMA UNTUK PROJECT COUNT (ROLE 2): MANAGER ID ATAU DI-ASSIGN di user_ids
        $where_project = " WHERE manager_id = '$user_id' OR FIND_IN_SET('$user_id', user_ids) > 0 ";
    } elseif ($login_type == 3 || $login_type == 4) { // employee or client

        // Anggota Tim: hanya melihat proyek yang dia di-assign (di project_list.user_ids)
        $where_project = " WHERE FIND_IN_SET('$user_id', user_ids) > 0 ";
    }
    
    // ========================================
    // AUTO-UPDATE OVERDUE STATUS
    // ========================================

    $conn->query("
        UPDATE task_list 
        SET status = 4 
        WHERE end_date < CURDATE() 
        AND status NOT IN (3,5)
    ");
    
    // Filter untuk Task List (Task Assignment)
    // Role 2 & 3: Hanya melihat tugas yang di-assign langsung ke mereka (t.user_ids)
    // Role 4 (client): melihat seluruh tugas di proyek yang mereka akses, karena mereka tidak menerima tugas langsung
    $where_task_join = "";
    $task_access_condition = "";
    if ($login_type == 2 || $login_type == 3) {
        $where_task_join = " WHERE FIND_IN_SET('$user_id', t.user_ids) > 0 ";
        $task_access_condition = " AND FIND_IN_SET('$user_id', t.user_ids) > 0 ";
    } elseif ($login_type == 4) {
        $where_task_join = " WHERE FIND_IN_SET('$user_id', p.user_ids) > 0 ";
        $task_access_condition = " AND FIND_IN_SET('$user_id', p.user_ids) > 0 ";
    }

    // Menggunakan nama variabel lama agar konsisten dengan struktur di bawah
    $where = $where_project;  // Filter untuk Project (project_list)
    $where2 = $where_task_join; // Filter untuk Task (task_list JOIN project_list)
    
    // Base Query untuk Task (untuk memudahkan penulisan status counts)
    $base_task_select = "SELECT t.id FROM task_list t INNER JOIN project_list p ON p.id = t.project_id {$where2}";
    $join_type = empty($where_task_join) ? " WHERE " : " AND "; // Menentukan apakah perlu menambah 'WHERE' atau 'AND'
  ?>

<div class="container-fluid">
  <div class="row">
        <?php if($login_type < 4): // hide Total Users for clients ?>
        <div class="col-12 col-sm-4 mb-3 scroll-motion"> <?php if($login_type == 1): ?>
            <a href="index.php?page=user_list" class="small-box bg-light shadow-sm p-3 d-block text-dark" style="text-decoration: none; border: none !important;"> <?php else: ?>
            <div class="small-box bg-light shadow-sm p-3" style="border: none !important;"> <?php endif; ?>
                            <div class="inner">
                                <h3 class="counter-value"><?php echo $conn->query('SELECT * FROM users')->num_rows ?></h3>
                                <p class="mb-0">Total Users</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-solid fa-users" style="color:#d49867;"></i>
                            </div>
            <?php if($login_type == 1): ?>
            </a>
            <?php else: ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: /* For clients: show Task Done in the top row to fill the gap */
            // build a simple query for done tasks (respecting assignment filters like the status loop below)
            $done_sql = "SELECT t.id FROM task_list t INNER JOIN project_list p ON p.id = t.project_id WHERE 1=1";
            if ($login_type != 1) {
                $done_sql .= $task_access_condition;
            }
            $done_sql .= " AND t.status = 5 ";
            $done_count = $conn->query($done_sql)->num_rows;
        ?>
        <div class="col-12 col-sm-4 mb-3 scroll-motion">
            <a href="index.php?page=task_list&status=5&expand=true" class="small-box bg-light shadow-sm p-3 d-block text-dark" style="text-decoration: none; border: none !important;">
                <div class="inner">
                    <h3 class="counter-value"><?php echo $done_count ?></h3>
                    <p class="mb-0">Task Done</p>
                </div>
                <div class="icon">
                    <i class="fa fa-solid fa-check-circle" style="color:#d49867;"></i>
                </div>
            </a>
        </div>
        <?php endif; ?>

   <div class="col-12 col-sm-4 mb-3 scroll-motion">
      <a href="index.php?page=project_list" class="small-box bg-light shadow-sm p-3 d-block text-dark" style="text-decoration: none; border: none !important;">
              <div class="inner">
                <h3 class="counter-value"><?php echo $conn->query("SELECT * FROM project_list {$where}")->num_rows; ?></h3>
                <p class="mb-0">Total Projects</p>
              </div>
              <div class="icon">
                <i class="fa fa-solid fa-folder-open" style="color:#d49867;"></i>
              </div>
      </a>
    </div>

    <div class="col-12 col-sm-4 mb-3 scroll-motion">
      <a href="index.php?page=task_list" class="small-box bg-light shadow-sm p-3 d-block text-dark" style="text-decoration: none; border: none !important;">
              <div class="inner">
                <h3 class="counter-value"><?php echo $conn->query($base_task_select)->num_rows; ?></h3>
                <p class="mb-0">Total Tasks</p>
              </div>
              <div class="icon">
                <i class="fa fa-solid fa-tasks" style="color:#d49867;"></i>
              </div>
      </a>
    </div>
  </div>
</div>

<div class="container-fluid">
  <div class="row">
   <?php 
    // Daftar Status
    $task_statuses = [
        0 => 'Task Pending',
        1 => 'Task Started',
        2 => 'Task On-Progress',
        3 => 'Task On-Hold',
        4 => 'Task Overdue',
        5 => 'Task Done'
    ];

    // Ambil tanggal hari ini
    $today = date('Y-m-d');

    foreach ($task_statuses as $status_code => $status_name):
        
        // If client, don't render the Task Done box here because we moved it up
        if ($login_type >= 4 && $status_code == 5) {
            continue;
        }

        $sql = "SELECT t.id FROM task_list t 
                INNER JOIN project_list p ON p.id = t.project_id 
                WHERE 1=1";

        if ($login_type != 1) {
            $sql .= $task_access_condition;
        }

        if ($status_code == 4) {
            $sql .= " AND (t.status = 4 OR (t.status < 5 AND t.status != 3 AND t.end_date < '$today')) "; 
        } else {
            $sql .= " AND t.status = '$status_code' ";
        }

        // Eksekusi Query
        $task_count = $conn->query($sql)->num_rows;
    ?>
    
   <div class="col mb-3">
      <a href="index.php?page=task_list&status=<?php echo $status_code ?>&expand=true"
        class="small-box bg-light shadow-sm border p-3 text-center d-block text-dark" style="text-decoration: none; border: none;">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="badge" style="background:<?php echo $status_color ?>"><?php echo $status_name ?></span>
          <h4 class="m-0 counter-value"><?php echo $task_count ?></h4>
        </div>
      </a>
    </div>

    <?php endforeach; ?>
    
  </div>
</div>

<?php
$chart_series_data = [
    0 => ['name' => 'Pending', 'data' => []],
    1 => ['name' => 'Started', 'data' => []],
    2 => ['name' => 'On-Progress', 'data' => []],
    3 => ['name' => 'On-Hold', 'data' => []],
    // 4 => Overdue biasanya dihitung logic terpisah karena based on date, tapi jika ingin placeholder:
    4 => ['name' => 'Overdue', 'data' => []], 
    5 => ['name' => 'Done', 'data' => []], // <--- BARU: Tambah array Done
];

?>

<div class="container-fluid mb-4 mt-3">
    <div class="row">
        <div class="<?php echo $login_type < 4 ? 'col-md-8' : 'col-md-12'; ?> col-12 mb-4 scroll-motion">
            <div class="card shadow-sm border-0" style="border-radius: 20px; height:100%;">
              <div class="card-body p-4">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
                      <h5 class="font-weight-bold mb-3 mb-lg-0" style="color: #333;">Project Progress Trends</h5>
                      
                      <div class="d-flex flex-wrap align-items-center chart-nav-wrapper nav-gap">
                          <div class="d-flex nav-gap align-items-center">
                              <button type="button" class="btn-capsule" id="prevTime" title="Previous">
                                  <i class="fa fa-chevron-left"></i>
                              </button>
                              <div class="btn-capsule font-weight-bold" id="resetTime">
                                  Today
                              </div>
                              <button type="button" class="btn-capsule" id="nextTime" title="Next">
                                  <i class="fa fa-chevron-right"></i>
                              </button>
                          </div>

                          <div class="d-flex nav-gap align-items-center">
                              <button type="button" class="btn-capsule filter-btn active-mode" data-mode="month">Month</button>
                              <button type="button" class="btn-capsule filter-btn" data-mode="week">Week</button>
                              <button type="button" class="btn-capsule filter-btn" data-mode="day">Day</button>
                          </div>
                      </div>
                  </div>

                  <div id="lineChartDaily" style="min-height: 300px;"></div>
              </div>
            </div>
        </div>
      
<?php if($login_type < 4): ?>
      <div class="col-md-4 col-12 mb-4 scroll-motion">
            <div class="card card-outline shadow-sm" style="border-radius: 20px; height: 100%; border: none !important;">
                <div class="card-header py-3 bg-transparent border-0">
                    <b style="font-size: 1.1rem;">Recent Activities</b>
                </div>  
                <div class="card-body py-2" style="max-height: 400px; overflow-y: auto;">
                    <ul class="timeline list-unstyled position-relative pl-3 mb-0">
                        <?php
                        // Logika filter tetap utuh (tidak ada perubahan fungsi)
                        $activity_where = " WHERE 1=1 "; 
                        if ($login_type == 2) {
                            $activity_where .= " AND (a.user_id = '$user_id' OR a.project_id IS NULL OR a.project_id IN (SELECT id FROM project_list WHERE manager_id = '$user_id' OR FIND_IN_SET('$user_id', user_ids) > 0)) ";
                        } elseif ($login_type == 3) {
                            $activity_where .= " AND (a.user_id = '$user_id' OR a.project_id IS NULL OR a.project_id IN (SELECT id FROM project_list WHERE FIND_IN_SET('$user_id', user_ids) > 0)) ";
                        }

                        $logs = $conn->query("
                            SELECT a.*, u.firstname, u.lastname, u.avatar, p.name AS project_name, t.task AS task_name, t.status AS task_status
                            FROM activity_log a
                            LEFT JOIN users u ON a.user_id = u.id
                            LEFT JOIN project_list p ON a.project_id = p.id
                            LEFT JOIN task_list t ON a.task_id = t.id
                            {$activity_where} 
                            ORDER BY a.created_at DESC LIMIT 30
                        ");

                        if ($logs && $logs->num_rows > 0):
                            while ($log = $logs->fetch_assoc()):
                                $actor_name = trim((string)($log['firstname'] ?? '') . ' ' . (string)($log['lastname'] ?? ''));
                                if (empty($actor_name) || ($log['activity_type'] ?? '') === 'TASK_OVERDUE_AUTO') {
                                    $actor_firstname = 'System';
                                    $actor_lastname = '';
                                    $avatar = 'assets/gear_icon.png';
                                } else {
                                    $actor_name_parts = explode(' ', $actor_name);
                                    $actor_firstname = array_shift($actor_name_parts);
                                    $actor_lastname = implode(' ', $actor_name_parts);
                                    $avatar = !empty($log['avatar']) ? 'assets/uploads/'.$log['avatar'] : 'assets/uploads/empty-placeholder.png';
                                }

                                $act_type = $log['activity_type'] ?? '';
                                if (strpos($act_type, 'event_') === 0) {
                                    if ($act_type === 'event_add') {
                                        $color = '#28a745'; 
                                    } elseif ($act_type === 'event_update') {
                                        $color = '#17a2b8'; 
                                    } elseif ($act_type === 'event_delete') {
                                        $color = '#dc3545'; 
                                    } else {
                                        $color = '#6f42c1'; 
                                    }
                                } else {
                                    switch ((int)$log['task_status']) {
                                        case 5: $color = '#4c9a2a'; break; 
                                        case 4: $color = '#c62828'; break; 
                                        case 3: $color = '#e66a00'; break; 
                                        case 2: $color = '#95c0dc'; break; 
                                        case 1: $color = '#f3dc80'; break; 
                                        default: $color = '#3a495c'; break; 
                                    }
                                }
                        ?>
                        <li class="timeline-item activity-item" 
                            style="cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s;"
                            <?php if (!empty($log['task_id'])): ?>
                                data-task-id="<?= encode_id($log['task_id']) ?>"
                                onclick="uni_modal('Task Details','get_task_detail.php?id=<?= encode_id($log['task_id']) ?>', 'mid-large')"
                                onmouseover="this.style.backgroundColor='#f5f5f5';"
                                onmouseout="this.style.backgroundColor='transparent';"
                            <?php elseif (strpos($act_type, 'event_') === 0): ?>
                                onclick="location.href='index.php?page=task_calendar';"
                                onmouseover="this.style.backgroundColor='#f5f5f5';"
                                onmouseout="this.style.backgroundColor='transparent';"
                            <?php endif; ?>>
                            
                            <span class="timeline-badge" style="background: <?= $color ?>;"></span>
                            <div class="d-flex align-items-center mb-1">
                                <img src="<?= $avatar ?>" class="avatar" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 8px;">
                                <div>
                                    <strong><?php if (!empty($actor_lastname)): ?><?= htmlspecialchars(ucwords($actor_firstname)) ?> <span class="user-lastname"><?= htmlspecialchars(ucwords($actor_lastname)) ?></span><?php else: ?><?= htmlspecialchars($actor_firstname) ?><?php endif; ?></strong><br>
                                    <small class="text-muted"><?= date('d M, H:i', strtotime($log['created_at'])) ?></small>
                                </div>
                            </div>
                            <div class="timeline-content">
                                <span style="font-size: 0.85rem;"><?= render_activity_description($log['description']) ?></span>
                                <?php if (!empty($log['project_name'])): ?>
                                    <br><small class="text-muted">Project: <?= $log['project_name'] ?></small>
                                <?php endif; ?>
                                <?php if (!empty($log['task_name'])): ?>
                                    <br><small class="text-info"><strong>Task: <?= htmlspecialchars($log['task_name']) ?></strong></small>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endwhile; else: ?>
                            <p class="text-muted text-center py-3">No Activities</p>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>    
    </div>
<?php endif; ?>
 </div>

<?php if ($login_type == 1): 
    // Data query for initial Team KPI overview across all projects
    $ov_kpi_qry = $conn->query("
        SELECT u.id, u.firstname, u.lastname, u.avatar, u.type, u.job_title,
               COUNT(t.id) as assigned,
               SUM(CASE WHEN t.status = 5 THEN 1 ELSE 0 END) as done
        FROM users u
        LEFT JOIN task_list t ON FIND_IN_SET(u.id, t.user_ids) > 0
        WHERE u.type IN (1, 2, 3)
        GROUP BY u.id
        ORDER BY done DESC, assigned DESC, firstname ASC
    ");
    
    $ov_user_metrics = [];
    $ov_bar_labels = [];
    $ov_bar_assigned = [];
    $ov_bar_done = [];
    
    if ($ov_kpi_qry && $ov_kpi_qry->num_rows > 0) {
        while ($u_row = $ov_kpi_qry->fetch_assoc()) {
            $assigned_cnt = (int)$u_row['assigned'];
            $done_cnt = (int)$u_row['done'];
            $pct = $assigned_cnt > 0 ? round(($done_cnt / $assigned_cnt) * 100, 1) : 0;
            $av_path = !empty($u_row['avatar']) && is_file('assets/uploads/'.$u_row['avatar']) ? 'assets/uploads/'.$u_row['avatar'] : 'assets/uploads/empty-placeholder.png';
            $full_name = ucwords(trim($u_row['firstname'] . ' ' . $u_row['lastname']));
            $role_name = !empty($u_row['job_title']) ? $u_row['job_title'] : ($u_row['type'] == 1 ? 'Admin' : ($u_row['type'] == 2 ? 'Project Manager' : 'Employee'));
            
            $ov_user_metrics[] = [
                'id' => $u_row['id'],
                'encoded_id' => encode_id($u_row['id']),
                'name' => $full_name,
                'avatar' => $av_path,
                'job_title' => $role_name,
                'assigned' => $assigned_cnt,
                'done' => $done_cnt,
                'kpi_pct' => $pct
            ];
            
            $ov_bar_labels[] = explode(' ', $full_name)[0];
            $ov_bar_assigned[] = $assigned_cnt;
            $ov_bar_done[] = $done_cnt;
        }
    }
    
    $ov_bar_labels_json = json_encode($ov_bar_labels);
    $ov_bar_assigned_json = json_encode($ov_bar_assigned);
    $ov_bar_done_json = json_encode($ov_bar_done);
?>

<!-- TEAM KPI (ALL PROJECTS) SECTION -->
<div class="row mt-3 mb-4 scroll-motion">
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 20px; border: none !important;">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-4 px-4 pb-2">
                <div class="font-weight-bold" style="font-size: 1.1rem; color: #333; letter-spacing: 0.5px;">
                    TEAM KPI
                </div>
                <div style="display:flex;gap:12px;align-items:center">
                    <span style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:#64748b">
                        <span style="width:10px;height:10px;border-radius:3px;background:#007bff;display:inline-block"></span>Assigned
                    </span>
                    <span style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:#64748b">
                        <span style="width:10px;height:10px;border-radius:3px;background:#28a745;display:inline-block"></span>Done
                    </span>
                </div>
            </div>
            <div class="card-body px-4 py-2">
                <div style="position: relative; height: 380px; width: 100%;">

                    <canvas id="overviewBarChart"></canvas>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 text-center py-3" style="border-top: 1px dashed #e2e8f0 !important; cursor: pointer;" id="overviewTeamKpiBtn" role="button" tabindex="0">
                <span style="color: #B75301; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fa fa-eye mr-1"></i> VIEW FULL LIST
                </span>
            </div>
        </div>
    </div>
</div>

<!-- TEAM KPI - FULL LIST MODAL (OVERVIEW) -->
<div class="modal fade" id="kpiOverviewModal" tabindex="-1" role="dialog" aria-labelledby="kpiOverviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden; box-shadow: 0 15px 50px rgba(0,0,0,0.2);">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #B75301 0%, #8f4001 100%); border:none; padding: 18px 24px;">
                <h5 class="modal-title font-weight-bold d-flex align-items-center mb-0" id="kpiOverviewModalLabel" style="font-size:1.15rem;">
                    <i class="fa fa-trophy text-warning mr-2" style="font-size:1.3rem;"></i>
                    Team KPI &mdash; All Projects (Best Employee Guideline)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Month Filter Bar -->
                <div class="d-flex justify-content-between align-items-center px-4 py-3 bg-light border-bottom flex-wrap" style="gap:12px;">
                    <div class="d-flex align-items-center">
                        <span class="font-weight-bold text-dark mr-2" style="font-size: 14px;">Periode:</span>
                        <select id="kpiMonthSelect" class="custom-select custom-select-sm" style="width: auto; border-radius: 8px; font-weight: 700; border-color: #B75301; color: #B75301; cursor: pointer;">
                            <option value="all" selected>Semua Waktu (All Time)</option>
                            <option value="<?= date('Y-m') ?>">Bulan Ini (<?= date('F Y') ?>)</option>
                            <option value="<?= date('Y-m', strtotime('-1 month')) ?>">Bulan Lalu (<?= date('F Y', strtotime('-1 month')) ?>)</option>
                            <?php
                            for ($m = 2; $m < 12; $m++) {
                                $ym = date('Y-m', strtotime("-$m month"));
                                $label = date('F Y', strtotime("-$m month"));
                                echo "<option value='{$ym}'>{$label}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="text-muted small">
                        <i class="fa fa-star text-warning mr-1"></i> Pedoman Penilaian Employee of the Month
                    </div>
                </div>

                <div id="kpiOverviewPrintable">
                    <div class="p-4 pb-2 text-center">
                        <h4 class="font-weight-bold mb-1" style="color:#333;">Laporan Team KPI & Ranking Best Employee</h4>
                        <p class="text-muted mb-0" style="font-size:13px;" id="kpiReportSubtitle">
                            Agregasi Seluruh Project &middot; Dibuat pada <?php echo date("d F Y") ?>
                        </p>
                    </div>

                    <div class="table-responsive px-4 pb-4 pt-2">
                        <table class="table table-hover align-middle m-0" id="kpiOverviewTable" style="width:100%;">
                            <thead>
                                <tr style="background-color:#f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th class="text-center py-3 border-0" style="font-weight:800; color:#495057; text-transform:uppercase; font-size:11px; width:8%;">RANK</th>
                                    <th class="text-left py-3 border-0" style="font-weight:800; color:#495057; text-transform:uppercase; font-size:11px; width:34%;">TEAM MEMBER</th>
                                    <th class="text-center py-3 border-0" style="font-weight:800; color:#495057; text-transform:uppercase; font-size:11px; width:14%;">ASSIGNED</th>
                                    <th class="text-center py-3 border-0" style="font-weight:800; color:#495057; text-transform:uppercase; font-size:11px; width:14%;">DONE</th>
                                    <th class="text-left py-3 border-0" style="font-weight:800; color:#495057; text-transform:uppercase; font-size:11px; width:22%;">COMPLETION</th>
                                    <th class="text-center py-3 border-0" style="font-weight:800; color:#495057; text-transform:uppercase; font-size:11px; width:8%;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="kpiOverviewTbody">
                                <?php
                                $ki_idx = 1;
                                foreach ($ov_user_metrics as $item):
                                    $kpi_pct = $item['kpi_pct'];
                                    $rank = $ki_idx++;
                                    $rank_badge = '<b>' . $rank . '</b>';
                                    $best_badge = '';
                                    
                                    if ($rank === 1 && $item['done'] > 0) {
                                        $rank_badge = '<span class="badge badge-warning text-dark px-2 py-1" style="font-size:12px;"><i class="fa fa-trophy mr-1"></i> #1</span>';
                                        $best_badge = '<span class="badge badge-pill ml-2 px-2 py-1" style="background:#B75301; color:#fff; font-size:10px; font-weight:700;"><i class="fa fa-star mr-1"></i>BEST EMPLOYEE</span>';
                                    } elseif ($rank === 2 && $item['done'] > 0) {
                                        $rank_badge = '<span class="badge badge-secondary px-2 py-1" style="font-size:12px;">#2</span>';
                                    } elseif ($rank === 3 && $item['done'] > 0) {
                                        $rank_badge = '<span class="badge badge-light border text-dark px-2 py-1" style="font-size:12px;">#3</span>';
                                    }
                                ?>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td class="text-center align-middle"><?= $rank_badge ?></td>
                                    <td class="text-left align-middle">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= $item['avatar'] ?>" class="rounded-circle border mr-3" style="width:40px; height:40px; object-fit:cover;">
                                            <div>
                                                <div style="font-weight:700; color:#333; font-size:14px;">
                                                    <?= $item['name'] ?> <?= $best_badge ?>
                                                </div>
                                                <small class="text-muted"><?= $item['job_title'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle font-weight-bold" style="font-size:14px; color:#007bff;"><?= $item['assigned'] ?></td>
                                    <td class="text-center align-middle font-weight-bold" style="font-size:14px; color:#28a745;"><?= $item['done'] ?></td>
                                    <td class="align-middle">
                                        <div class="progress" style="height:8px; margin-bottom:4px; background-color:#e9ecef; border-radius:10px;">
                                            <div class="progress-bar" role="progressbar" style="width:<?= $kpi_pct ?>%; background:linear-gradient(90deg,#CD874D 10%,#B75301 80%); border-radius:10px;"></div>
                                        </div>
                                        <small style="display:block; color:#555; font-size:11px; font-weight:600;"><?= $kpi_pct ?>% Complete</small>
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-outline-secondary uni-kpi-detail" data-id="<?= $item['encoded_id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>" title="Lihat Detail User">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ov_user_metrics)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada data tugas anggota tim.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border:none; background:#fafafa; padding: 14px 24px;">
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal" style="border-radius:10px; font-weight:700; font-size:12px; text-transform:uppercase;">Close</button>
                <button type="button" class="btn text-white px-4" id="kpiOverviewSavePdfBtn" style="background-color:#B75301; border-radius:10px; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; border:none; box-shadow:0 4px 14px rgba(183,83,1,0.25);">
                    <i class="fa fa-file-pdf-o mr-1"></i> Save as PDF
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($login_type == 1): 
    // Query all team members (type 2/3) and their task KPI
    $kpi_query = $conn->query("
        SELECT u.id, u.firstname, u.lastname, u.avatar, u.type,
               COUNT(t.id) as total_tasks,
               SUM(CASE WHEN t.status = 5 THEN 1 ELSE 0 END) as done_tasks
        FROM users u
        LEFT JOIN task_list t ON FIND_IN_SET(u.id, t.user_ids) > 0
        WHERE u.type IN (2,3)
        GROUP BY u.id
        ORDER BY u.firstname ASC
    ");
    
    $users_kpi_list = [];
    if ($kpi_query && $kpi_query->num_rows > 0) {
        while ($member = $kpi_query->fetch_assoc()) {
            $total_t = (int)$member['total_tasks'];
            $done_t = (int)$member['done_tasks'];
            $kpi_pct = $total_t > 0 ? round(($done_t / $total_t) * 100, 1) : 0;
            
            $member_avatar = !empty($member['avatar']) && is_file('assets/uploads/'.$member['avatar']) ? 'assets/uploads/'.$member['avatar'] : 'assets/uploads/empty-placeholder.png';
            $member_name = ucwords(trim($member['firstname'] . ' ' . $member['lastname']));
            
            $users_kpi_list[] = [
                'id' => $member['id'],
                'encoded_id' => encode_id($member['id']),
                'name' => $member_name,
                'avatar' => $member_avatar,
                'total' => $total_t,
                'done' => $done_t,
                'kpi' => $kpi_pct
            ];
        }
    }
    if (!empty($users_kpi_list)):
?>
    <!-- FULL WIDTH KPI PROGRESS TRACK SECTION (ADMIN ONLY) -->
    <div class="row mt-3 mb-4 scroll-motion">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-radius: 20px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 10px;">
                        <div>
                            <h5 class="font-weight-bold mb-1" style="color: #333;">
                                <i class="fa fa-chart-line text-warning mr-2"></i> KPI Progress Track
                            </h5>
                            <p class="text-muted mb-0" style="font-size: 13px;">
                                Pantau posisi pencapaian KPI setiap anggota tim secara real-time. Klik foto profil untuk melihat rincian KPI proyek & tugas.
                            </p>
                        </div>
                    </div>

                    <!-- Full Width Track Line -->
                    <div class="position-relative bg-light rounded-lg border mt-3" style="min-height: 145px; padding: 55px 30px 45px 30px;">
                        <div class="progress" style="height: 14px; background-color: #e2e8f0; border-radius: 14px; position: relative; overflow: visible;">
                            <div class="progress-bar" role="progressbar" style="width: 100%; background: linear-gradient(90deg, #ef4444 0%, #ef4444 30%, #3b82f6 30%, #3b82f6 70%, #22c55e 70%, #22c55e 100%); border-radius: 14px; opacity: 0.85;"></div>
                            
                            <!-- Milestone labels -->
                            <div class="d-flex justify-content-between w-100 position-absolute" style="top: -30px; left: 0; padding: 0 5px; font-size: 11px; font-weight: 700;">
                                <span style="color: #ef4444;"><i class="fa fa-flag mr-1"></i>0%</span>
                                <span style="color: #ef4444;">30%</span>
                                <span style="color: #3b82f6;">70%</span>
                                <span style="color: #22c55e;"><i class="fa fa-trophy mr-1"></i>100%</span>
                            </div>

                            <!-- Avatar Pins along the line -->
                            <?php foreach($users_kpi_list as $index => $u_kpi): 
                                $left_percent = min(max($u_kpi['kpi'], 2), 98);
                                if ($u_kpi['kpi'] < 30) {
                                    $badge_bg = '#ef4444'; // Merah (0-30%)
                                } elseif ($u_kpi['kpi'] < 70) {
                                    $badge_bg = '#3b82f6'; // Biru (30-70%)
                                } else {
                                    $badge_bg = '#22c55e'; // Hijau (70-100%)
                                }
                                $z_index = 10 + ($index % 20);
                            ?>
                                <div class="user-kpi-pin position-absolute" 
                                     style="left: <?= $left_percent ?>%; top: 50%; transform: translate(-50%, -50%); cursor: pointer; z-index: <?= $z_index ?>;"
                                     data-id="<?= $u_kpi['encoded_id'] ?>"
                                     data-name="<?= htmlspecialchars($u_kpi['name']) ?>"
                                     title="<?= htmlspecialchars($u_kpi['name']) ?>: <?= $u_kpi['kpi'] ?>% KPI (<?= $u_kpi['done'] ?>/<?= $u_kpi['total'] ?> Tasks)"
                                     data-toggle="tooltip">
                                    <div class="avatar-wrapper position-relative">
                                        <img src="<?= $u_kpi['avatar'] ?>" 
                                             alt="<?= htmlspecialchars($u_kpi['name']) ?>" 
                                             class="rounded-circle border border-white shadow-sm user-pin-img"
                                             style="width: 44px; height: 44px; object-fit: cover; background-color: #fff;">
                                        <span class="badge badge-pill position-absolute" 
                                              style="bottom: -11px; left: 50%; transform: translateX(-50%); font-size: 9px; font-weight: 700; padding: 2px 6px; background: <?= $badge_bg ?>; color: #fff; border: 1.5px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                            <?= $u_kpi['kpi'] ?>%
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; endif; ?>

<style>
.user-kpi-pin {
    transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275), z-index 0.25s ease !important;
}
.user-kpi-pin:hover {
    z-index: 999 !important;
    transform: translate(-50%, -50%) scale(1.35) !important;
}
.user-kpi-pin:hover .user-pin-img {
    box-shadow: 0 8px 20px rgba(0,0,0,0.3) !important;
    border-color: #B75301 !important;
}



<style>
/* --- Animasi Sambutan Baru --- */
@keyframes slideFromNavbar {
    from {
        opacity: 0;
        transform: translateX(-50px); /* Muncul dari balik sidebar */
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.animate-title {
    opacity: 0;
    display: inline-block;
    animation: slideFromNavbar 0.8s ease-out forwards;
}

.animate-subtitle {
    opacity: 0;
    display: block;
    animation: slideFromNavbar 0.8s ease-out forwards;
    animation-delay: 0.5s; /* Delay muncul setelah nama */
}

#lineChartDaily {
    min-height: 300px;
    width: 100%;
    visibility: visible !important;
    opacity: 1 !important;
    overflow: hidden;
    position: relative;
}

/* keep chart smooth inside animated cards */
.scroll-motion #lineChartDaily {
    transform: translateZ(0);
    backface-visibility: hidden;
}

/* Sedikit delay untuk Recent Activities agar muncul setelah Chart */
.col-md-4.scroll-motion {
    transition-delay: 0.2s;
}

/* Pastikan card di section ini benar-benar borderless */
.card.shadow-sm {
    border: none !important;
    overflow: visible !important;
}

/* Efek Motion saat Scroll */
.scroll-motion {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.8s ease-out, transform 0.8s ease-out;
}

.scroll-motion.active {
    opacity: 1;
    transform: translateY(0);
}

    /* Styling agar tombol terlihat seperti gaya kalender yang bersih */
    .btn-white {
        background: #fff;
        color: #555;
    }
    .btn-white:hover {
        background: #f8f9fa;
    }
    .active-mode {
        background: #f0f0f0 !important;
        font-weight: bold;
        color: #333 !important;
        border-color: #ddd !important;
    }
    .card-body h5 {
        letter-spacing: -0.5px;
    }

    @media (max-width: 575.98px) {
        .chart-nav-wrapper {
            width: 100%;
            justify-content: flex-start; /* Tombol rata kiri di HP kecil */
        }
        .chart-nav-wrapper .btn-group {
            flex: 1; /* Membuat grup tombol membagi rata lebar jika diinginkan */
        }
        .btn-white {
            padding: 8px 5px !important; /* Memperkecil padding tombol di mobile */
            font-size: 11px !important;
        }
    }

    /* 1. Styling Dasar Tombol Kapsul */
    .btn-capsule {
        background-color: #ffffff;
        border: 1px solid #e0e0e0;
        border-radius: 50px !important; /* Membuat bentuk kapsul sempurna */
        color: #333;
        font-weight: 500;
        padding: 5px 18px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        outline: none !important;
        box-shadow: none !important;
        font-size: 13px;
        height: 34px;
        cursor: pointer;
    }

    /* 2. Efek Hover */
    .btn-capsule:hover {
        background-color: #f8f9fa;
        border-color: #d0d0d0;
        color: #333;
        text-decoration: none;
    }

    /* 3. State Aktif (Warna Oranye Hai Motion) */
    .btn-capsule.active-mode {
        background-color: #b75301 !important; 
        color: #ffffff !important;
        border-color: #b75301 !important;
    }

    /* 4. Pengaturan Spasi (Gap) antar tombol */
    .nav-gap {
        gap: 8px !important;
    }

    /* 5. Lebar Minimum untuk Tombol Today/Tanggal agar tidak berubah-ubah */
    #resetTime {
        min-width: 90px;
    }

    /* Penyesuaian agar ikon di dalam tombol tetap rapi */
    .btn-capsule i {
        font-size: 11px;
        line-height: 1;
    } 
    /* ===== PROJECT TRENDS BUTTON GROUP – RESPONSIVE TWEAKS ===== */
    @media (max-width: 576px) {
        .chart-nav-wrapper {
            flex-direction: column;   /* stack groups vertically */
            align-items: center;      /* center them */
            gap: 10px;                /* space between the two groups */
            width: 100%;
        }

        .chart-nav-wrapper > div {
            width: 100%;              /* each group takes full width */
            justify-content: center;  /* center buttons inside each group */
        }

        /* Make buttons slightly smaller on very narrow screens */
        .btn-capsule {
            padding: 5px 12px;
            font-size: 12px;
            height: 32px;
        }

        #resetTime {
            min-width: 80px;          /* adjust to fit smaller text */
        }
    }

    /* Even smaller – optional */
    @media (max-width: 380px) {
        .btn-capsule {
            padding: 5px 8px;
            font-size: 11px;
        }
    }

    .custom-tooltip {
        background: #fff;
        border: 1px solid #eee;
        padding: 10px 12px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        font-size: 12px;
    }

    .tooltip-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 4px;
    }

    .tooltip-marker {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    .tooltip-label {
        flex: 1;
        color: #555;
    }

    .tooltip-value {
        font-weight: bold;
        color: #222;
    }

</style>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.scroll-motion').forEach(el => observer.observe(el));
});

// 1. Variabel Global (Sesuai kode asli Anda)
var chart; 
let viewMode = 'month';
let timeOffset = 0;
let chartHasBeenLoaded = false; // Flag untuk memastikan data hanya di-load 1x saat scroll

// 2. Fungsi Load Data (Sesuai kode asli Anda - TIDAK DIRUBAH)
function loadChartData() {
    // Jika chart belum ada (lazy loading), tunggu sampai siap
    if (!chart) {
        console.log("Chart belum loaded, akan diretry...");
        setTimeout(loadChartData, 500);
        return;
    }

    console.log("Meminta data untuk:", viewMode, "Offset:", timeOffset);
    
    $.ajax({
        url: 'get_trends_data.php',
        method: 'POST',
        data: { mode: viewMode, offset: timeOffset },
        dataType: 'json',
        success: function(res) {
            $('#resetTime').text(res.period_label);

            const allValues = res.series.flatMap(s => s.data || []);
            const rawMax = allValues.length ? Math.max(...allValues) : 5;

            const yMax = Math.max(5, Math.ceil(rawMax / 5) * 5);

            chart.updateOptions({
                xaxis: {
                    categories: res.labels
                },
                yaxis: {
                    min: 0,
                    max: yMax,
                    tickAmount: 5, // always 5 steps
                    forceNiceScale: true,
                    decimalsInFloat: 0,
                    labels: {
                        formatter: function (value) {
                            return Math.round(value);
                        }
                    }
                },

                tooltip: {
                    shared: true,
                    intersect: false,
                    custom: function({ series, dataPointIndex, w }) {

                        let seen = new Set();
                        let html = `
                            <div class="custom-tooltip">
                        `;

                        w.config.series.forEach((s, i) => {
                            const val = series[i][dataPointIndex];

                            if (seen.has(s.name)) return;
                            seen.add(s.name);

                            const color = w.globals.colors[i];

                            html += `
                                <div class="tooltip-row">
                                    <span class="tooltip-marker" style="background:${color}"></span>
                                    <span class="tooltip-label">${s.name}</span>
                                    <span class="tooltip-value">${val}</span>
                                </div>
                            `;
                        });

                        html += `</div>`;
                        return html;
                    }
                }

            }, false, true);

            chart.updateSeries(res.series, false);
        }
    });
}

// 3. Fungsi untuk inisialisasi chart (dipanggil saat lazy load)
function initializeTrendChart() {
    if (chart) return; // already initialized

    var options = {
        series: [
            { name: 'Pending', data: <?php echo json_encode($chart_series_data[0]['data'] ?? []) ?> },
            { name: 'Started', data: <?php echo json_encode($chart_series_data[1]['data'] ?? []) ?> },
            { name: 'On-Progress', data: <?php echo json_encode($chart_series_data[2]['data'] ?? []) ?> },
            { name: 'On-Hold', data: <?php echo json_encode($chart_series_data[3]['data'] ?? []) ?> },
            { name: 'Overdue', data: <?php echo json_encode($chart_series_data[4]['data'] ?? []) ?> },
            { name: 'Done', data: <?php echo json_encode($chart_series_data[5]['data'] ?? []) ?> }
        ],
        chart: {
            height: 350,
            type: 'line',
            parentHeightOffset: 0,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800
            }
        },
        colors: ['#6c757d', '#00d4ff', '#1a237e', '#ffc107', '#dc3545', '#28a745'],
        stroke: { curve: 'smooth', width: 4 },
        xaxis: { categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] },
        yaxis: { min: 0, forceNiceScale: true },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'left',
            floating: false
        }
    };

    chart = new ApexCharts(document.querySelector("#lineChartDaily"), options);
    chart.render().then(() => {
        loadChartData();

        setTimeout(() => {
            chart.updateOptions({}, true, true); // force redraw
        }, 300);
    });

}

document.addEventListener('DOMContentLoaded', function() {
    // Set up Event Handlers DULU (sebelum chart diinisialisasi)
    $(document).on('click', '.filter-btn', function(e) {
        e.preventDefault();
        $('.filter-btn').removeClass('active-mode');
        $(this).addClass('active-mode');
        viewMode = $(this).data('mode');
        timeOffset = 0;
        loadChartData();
    });

    $(document).on('click', '#prevTime', function(e) {
        e.preventDefault();
        timeOffset--;
        loadChartData();
    });

    $(document).on('click', '#nextTime', function(e) {
        e.preventDefault();
        timeOffset++;
        loadChartData();
    });

    $(document).on('click', '#resetTime', function(e) {
        e.preventDefault();
        timeOffset = 0;
        loadChartData();
    });

    // Lazy Load Chart Container
    observeChartContainer('#lineChartDaily');
});
function observeCanvas(selector, initFunction) {
    if (typeof selector === 'string') {
        document.querySelectorAll(selector).forEach(canvas => {
            observeCanvasElement(canvas, initFunction);
        });
    } else if (selector instanceof Element) {
        observeCanvasElement(selector, initFunction);
    }
}

function observeCanvasElement(canvas, initFunction) {
    if (!window.IntersectionObserver) {
        // Fallback untuk browser lama: langsung inisialisasi
        initFunction(canvas);
        return;
    }
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                initFunction(entry.target);
                obs.unobserve(entry.target); // Hentikan observasi setelah diinisialisasi
            }
        });
    }, { threshold: 0.15 }); 
    observer.observe(canvas);
}

// Fungsi Lazy Load untuk ApexCharts (Project Trends)
function observeChartContainer(selector) {
    if (!window.IntersectionObserver) {
        initializeTrendChart();
        return;
    }

    const element = document.querySelector(selector);
    if (!element) return;

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    initializeTrendChart();
                }, 850);

                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    observer.observe(element);
}
</script>
 <!-- #region -->
<div class="py-2"></div> 

<div class="row mb-4"> 
    <div class="col-12 scroll-motion"> 
        <div class="card card-outline shadow-sm" style="border-radius: 20px; overflow: hidden; border: none !important;">
            
            <div class="card-header py-3 d-flex align-items-center justify-content-between bg-transparent border-0">
                <b style="font-size: 1.1rem;">Project Progress Details</b>
            </div>

            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <div class="mb-4">
                    <span class="badge badge-secondary p-2 px-3">Pending</span>
                    <span class="badge badge-info p-2 px-3">Started</span>
                    <span class="badge badge-primary p-2 px-3">On-Progress</span>
                    <span class="badge badge-warning p-2 px-3 text-white">On-Hold</span>
                    <span class="badge badge-danger p-2 px-3">Over Due</span>
                    <span class="badge badge-success p-2 px-3">Done</span>
                </div>

                <div class="d-flex overflow-auto pb-3" style="gap: 1.5rem;">
        <?php
        $chart_scripts = "";
        // Query Project List (Logic Anda Sudah Benar)
        $qry = $conn->query("SELECT * FROM project_list {$where} ORDER BY name ASC");
        
        if ($qry->num_rows > 0):
            while ($row = $qry->fetch_assoc()):
                $task_counts = [0, 0, 0, 0, 0, 0];
                
                // Logic Filter Task (Logic Anda Sudah Benar)
                $task_count_condition = " WHERE project_id = {$row['id']} ";
                if ($login_type == 2 || $login_type == 3) {
                    $task_count_condition .= " AND FIND_IN_SET('$user_id', user_ids) > 0 ";
                }
                
                $tasks = $conn->query("SELECT status, end_date FROM task_list {$task_count_condition}");
                $overdue_count = 0;
                $today = date('Y-m-d');
                
                while ($task = $tasks->fetch_assoc()) {
                  $s = intval($task['status']);
                  if ($s >= 0 && $s <= 5) $task_counts[$s]++;
                  if ($s < 5 && $task['end_date'] < $today) {
                       $overdue_count++;
                  }
                }
                $task_counts[4] = $overdue_count; 
                
                $chart_id = "chart_" . $row['id'];
                $encoded_proj_id = encode_id($row['id']); 
        ?>
                  <a href="index.php?page=view_project&id=<?php echo $encoded_proj_id; ?>" class="card p-3 shadow-sm project-card" data-id="<?php echo $row['id'] ?>" style="min-width: 280px; cursor: pointer; text-decoration: none; color: inherit;">
                    <h6 class="font-weight-bold text-truncate"><?php echo ucwords($row['name']) ?></h6>
                    <p class="mb-2 text-muted small">Due: <?php echo date("d M Y", strtotime($row['end_date'])) ?></p>
                    <canvas id="<?php echo $chart_id ?>" height="180"></canvas>
                  </a>

                <?php
                // Script Chart Pie Kecil
                $chart_scripts .= "<script>
                  observeCanvas('#{$chart_id}', function(canvas) {
                    new Chart(canvas, {
                      type: 'pie',
                      data: {
                        labels: ['Pending','Started','On-Progress','On-Hold','Over Due','Done'],
                        datasets: [{
                          data: [{$task_counts[0]}, {$task_counts[1]}, {$task_counts[2]}, {$task_counts[3]}, {$task_counts[4]}, {$task_counts[5]}],
                          backgroundColor: ['#6c757d','#17a2b8','#007bff','#ffc107','#dc3545','#28a745'],
                          borderWidth: 1
                        }]
                      },
                      options: {
                        responsive: true,
                        plugins: { legend: { display: false }, tooltip: { enabled: true } }
                      }
                    });
                  });
                  </script>";
                endwhile;
            else: ?>
                <p class="text-center w-100 text-muted py-3">No projects assigned to you yet.</p>
            <?php
            endif;
            echo $chart_scripts;
            ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$projects = $conn->query("SELECT * FROM project_list {$where_project} ORDER BY name ASC");
$project_stats = [];
while($proj = $projects->fetch_assoc()){
    $pid = $proj['id'];
    
    // Logic Task Filter
    $task_filter_condition = " project_id = $pid ";
    if ($login_type == 2 || $login_type == 3) {
        $task_filter_condition .= " AND FIND_IN_SET('$user_id', user_ids) > 0 ";
    }
    
    $task_base_query = "SELECT user_ids, status, content_pillar, platform, end_date FROM task_list WHERE {$task_filter_condition}";
    $tasks_for_stats = $conn->query($task_base_query);
    
    $total_tasks = $tasks_for_stats->num_rows;
    $status_data = [0,0,0,0,0,0]; 
    $pillar_data = [];
    $platform_data = [];
    $overdue_count = 0;
    $today = date('Y-m-d');
    
    if ($tasks_for_stats->num_rows > 0) {
        $tasks_for_stats->data_seek(0);
        while($row_task = $tasks_for_stats->fetch_assoc()){
            $s = intval($row_task['status']);
            if ($s >= 0 && $s <= 5) $status_data[$s]++;
            if ($s < 5 && $row_task['end_date'] < $today) $overdue_count++;

            $pillars = array_filter(array_map('trim', explode(',', $row_task['content_pillar'])));
            foreach ($pillars as $p_val) $pillar_data[$p_val] = ($pillar_data[$p_val] ?? 0) + 1;

            $platforms = array_filter(array_map('trim', explode(',', $row_task['platform'])));
            foreach ($platforms as $pl_val) $platform_data[$pl_val] = ($platform_data[$pl_val] ?? 0) + 1;
        }
    }
    $status_data[4] = $overdue_count; 
    
    // Manager Data
    $manager = null;
    if(!empty($proj['manager_id'])){
        $m_qry = $conn->query("SELECT id, firstname, lastname, avatar FROM users WHERE id = {$proj['manager_id']}");
        if($m_qry->num_rows > 0){
            $m = $m_qry->fetch_assoc();
            $manager = ['id' => $m['id'], 'name' => ucwords($m['firstname'].' '.$m['lastname']), 'avatar' => !empty($m['avatar']) ? 'assets/uploads/'.$m['avatar'] : 'assets/uploads/empty-placeholder.png'];
        }
    }

    // Member Data
    $members = [];
    if(!empty($proj['user_ids'])){
        $uids = array_filter(explode(",", $proj['user_ids']));
        if(count($uids) > 0){
            $users_qry = $conn->query("SELECT id, firstname, lastname, avatar FROM users WHERE id IN (".implode(",", $uids).")");
            while($u = $users_qry->fetch_assoc()){
                $members[] = ['id' => $u['id'], 'name' => ucwords($u['firstname'].' '.$u['lastname']), 'avatar' => !empty($u['avatar']) ? 'assets/uploads/'.$u['avatar'] : 'assets/uploads/empty-placeholder.png'];
            }
        }
    }

    $project_stats[] = [
        'id' => $proj['id'], 'name' => $proj['name'], 'total_tasks' => $total_tasks,
        'status' => $status_data, 'pillar' => $pillar_data, 'platform' => $platform_data,
        'manager' => $manager, 'members' => $members
    ];
}
?>

<div class="row">
    <?php foreach($project_stats as $p): ?>
    <?php $encoded_id = encode_id($p['id']); ?>
    
    <div class="col-12 mb-4 scroll-motion">
        <div class="card shadow-sm project-card-link" data-id="<?= $p['id'] ?>" data-encoded-id="<?= $encoded_id ?>" style="cursor: pointer; border-radius: 20px; overflow: hidden;">
            <div class="card-header py-2">
                <h5 class="m-0">
                    <b><?= $p['name'] ?></b> (<?= $p['total_tasks'] ?> Tasks)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="<?= $login_type >= 4 ? 'col-md-4' : 'col-md-3' ?> px-3">
                        <h6 class="text font-weight-bold">Task Status </h6>
                        <div class="chart-container" style="max-height:250px; padding-bottom: 20px;">
                            <canvas id="statusChart_<?= $p['id'] ?>"></canvas>
                        </div>
                    </div>
                    <div class="<?= $login_type >= 4 ? 'col-md-4' : 'col-md-3' ?> px-3">
                        <h6 class="text font-weight-bold">Task Type </h6>
                        <div class="chart-container" style="max-height:250px; padding-bottom: 20px;">
                            <canvas id="pillarChart_<?= $p['id'] ?>"></canvas>
                        </div>
                    </div>
                    <div class="<?= $login_type >= 4 ? 'col-md-4' : 'col-md-3' ?> px-3">
                        <h6 class="font-weight-bold">Platform</h6>
                        <div class="chart-container" style="max-height:250px; padding-top:30px">
                            <canvas id="platformChart_<?= $p['id'] ?>"></canvas>
                        </div>
                    </div>
                    <?php if($login_type < 4): ?>
                    <div class="col-md-3 px-3">
                        <h6 class="font-weight-bold">Assignment</h6>
                        <div class="p-2 mb-3 rounded" style="background-color: #f8f9fa;">
                            <small class="text-muted d-block">Project Manager</small>
                            <?php if($p['manager']): ?>
                                <div class="d-flex align-items-center">
                                    <img src="<?= $p['manager']['avatar'] ?>" class="rounded-circle border mr-2" style="width:35px; height:35px; object-fit:cover;">
                                    <strong class="text-truncate" title="<?= $p['manager']['name'] ?>"><?= ucwords(explode(' ', $p['manager']['name'])[0]) ?> <span class="user-lastname"><?= ucwords(array_slice(explode(' ', $p['manager']['name']), 1) ? implode(' ', array_slice(explode(' ', $p['manager']['name']), 1)) : '') ?></span></strong>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No Manager Assigned</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">Team Members (<?= count($p['members']) ?>)</small>
                        <div class="d-flex flex-wrap align-items-center mt-1 assignment-list">
                            <?php if(!empty($p['members'])): ?>
                                <?php foreach($p['members'] as $m): ?>
                                    <img src="<?= $m['avatar'] ?>" class="rounded-circle border border-white avatar-member" style="width:30px; height:30px; object-fit:cover;" title="<?= $m['name'] ?>">
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No Members</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
.container-fluid {
    padding-left: 15px !important;
    padding-right: 15px !important;
}

/* Prevent clipping of the absolutely positioned chart legend */
#lineChartDaily {
    overflow: visible !important;
}

/* Allow the card to fully display its contents without clipping */
.card.shadow-sm {
    overflow: visible !important;
}
/* Memastikan row mengambil lebar penuh tanpa margin yang tidak sejajar */
.row {
    width: auto !important;
}


/* Pastikan card-body tidak menciutkan konten di dalamnya */
.card-body {
    width: 100% !important;
}

/* Pastikan list item memiliki posisi relatif */
.timeline-item {
    position: relative;
    list-style: none;
}

/* Menghilangkan sudut tajam pada header agar mengikuti lengkungan card */
.card {
    overflow: hidden !important;
}

.card-header {
    border-top-left-radius: inherit !important;
    border-top-right-radius: inherit !important;
}

.card-body .timeline.pl-3::before { 
    content: '';
    padding-left: 0 !important;
    position: absolute;
    top: 0;
    bottom: 0;
    left: 12px !important;
    width: 2px;
    background-color: #e9ecef; 
    z-index: 0;
}

.card-body .timeline-item .timeline-badge {
    position: absolute;
    top: 5px; 
    left: 10px !important; 
    transform: translateX(-50%) !important; 
    width: 10px; 
    height: 10px; 
    border-radius: 50%;
    z-index: 1;
    border: 2px solid white; 
}
.card-body .timeline-item > div {
  padding-left: 20px !important;
}

/* Menghapus margin default dari item (jika ada) */
.timeline li {
    margin-bottom: 15px; 
}

/* Pastikan kartu Project Progress (atas) berfungsi sebagai link */
.project-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    transform: translateY(-2px);
    transition: all 0.2s ease-in-out;
}
</style>

<script>
$(document).ready(function(){
  // Event listener untuk mengklik kartu proyek di bagian bawah (yang memiliki grafik detail)
  $(document).on('click', '.project-card-link', function(e){
    // Pastikan klik bukan pada elemen interaktif di dalam kartu (misalnya canvas chart)
    if ($(e.target).closest('canvas').length === 0) {
      var encoded_pid = $(this).data('encoded-id'); 
      if(encoded_pid){
        window.location.href = "index.php?page=view_project&id=" + encoded_pid;
      }
    }
  });
});

<?php foreach($project_stats as $p): ?>

// === STATUS CHART (Doughnut) ===
observeCanvas('#statusChart_<?= $p['id'] ?>', function(canvas) {
  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: ['Pending','Started','On-Progress','On-Hold','Over Due','Done'],
      datasets: [{
        data: [
          <?= $p['status'][0] ?? 0 ?>,
          <?= $p['status'][1] ?? 0 ?>,
          <?= $p['status'][2] ?? 0 ?>,
          <?= $p['status'][3] ?? 0 ?>,
          <?= $p['status'][4] ?? 0 ?>, 
          <?= $p['status'][5] ?? 0 ?>
        ],
        backgroundColor: ['#3a495c','#E6B800','#2A80B9','#B0B0B0','#C62828','#4C9A2A'],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      }
    }
  });
});

// === CONTENT PILLAR CHART (Pie) ===
observeCanvas('#pillarChart_<?= $p['id'] ?>', function(canvas) {
  new Chart(canvas, {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_keys($p['pillar'])) ?>,
      datasets: [{
        data: <?= json_encode(array_values($p['pillar'])) ?>,
        backgroundColor: ['#3a495c','#E6B800','#2A80B9','#B0B0B0','#C62828','#4C9A2A', '#f9d276', '#ff007f'],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      }
    }
  });
});

// === PLATFORM CHART (Bar) ===
observeCanvas('#platformChart_<?= $p['id'] ?>', function(canvas) {
  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($p['platform'])) ?>,
      datasets: [{
        label: 'Tasks',
        data: <?= json_encode(array_values($p['platform'])) ?>,
        backgroundColor: '#E66A00'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
});

<?php endforeach; ?>

$(document).ready(function(){
    if ($.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }
});

$(document).on('click', '.user-kpi-pin', function(e){
    e.preventDefault();
    var encodedId = $(this).data('id');
    var name = $(this).data('name');
    uni_modal("<i class='fa fa-chart-line mr-2'></i> Team KPI Breakdown &mdash; " + name, "view_user_kpi.php?id=" + encodedId, "large");
});

// === OVERVIEW TEAM KPI BAR CHART & MODAL JS ===
var ovBarChart = null;

function renderOverviewBarChart(labels, assignedData, doneData) {
    var canvas = document.getElementById('overviewBarChart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    
    if (ovBarChart) {
        ovBarChart.destroy();
    }

    // Determine max value for dynamic stepSize
    var maxVal = Math.max.apply(null, assignedData.concat(doneData).concat([0]));
    var stepSize = maxVal <= 10 ? 1 : (maxVal <= 50 ? 10 : (maxVal <= 200 ? 50 : 100));
    
    ovBarChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Tasks Assigned',
                    data: assignedData,
                    backgroundColor: 'rgba(0, 123, 255, 0.85)',
                    borderColor: '#007bff',
                    borderWidth: 0,
                    borderRadius: 4,
                    borderSkipped: false
                },
                {
                    label: 'Tasks Done',
                    data: doneData,
                    backgroundColor: 'rgba(40, 167, 69, 0.85)',
                    borderColor: '#28a745',
                    borderWidth: 0,
                    borderRadius: 4,
                    borderSkipped: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'top',
                align: 'center',
                labels: {
                    usePointStyle: false,
                    boxWidth: 32,
                    boxHeight: 14,
                    padding: 16,
                    fontColor: '#333',
                    fontSize: 12,
                    fontStyle: 'normal'
                }
            },
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        stepSize: stepSize,
                        fontColor: '#666',
                        fontSize: 11,
                        padding: 4
                    },
                    gridLines: {
                        color: 'rgba(0,0,0,0.07)',
                        drawBorder: false
                    }
                }],
                xAxes: [{
                    ticks: {
                        display: true,
                        fontColor: '#444',
                        fontSize: 11,
                        maxRotation: 30,
                        minRotation: 0,
                        padding: 4
                    },
                    gridLines: { display: false },
                    barPercentage: 0.7,
                    categoryPercentage: 0.75
                }]
            },
            tooltips: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(30,30,40,0.92)',
                titleFontSize: 13,
                bodyFontSize: 12,
                cornerRadius: 8,
                padding: 10
            }
        }
    });
}

<?php if (isset($ov_bar_labels_json)): ?>
$(document).ready(function(){
    renderOverviewBarChart(<?= $ov_bar_labels_json ?>, <?= $ov_bar_assigned_json ?>, <?= $ov_bar_done_json ?>);
});
<?php endif; ?>

$('#overviewTeamKpiBtn').on('click', function(){
    $('#kpiOverviewModal').modal('show');
});

$('#kpiMonthSelect').on('change', function(){
    var selectedMonth = $(this).val();
    $.ajax({
        url: 'ajax.php?action=get_overview_kpi',
        method: 'POST',
        data: { month: selectedMonth },
        dataType: 'json',
        success: function(resp) {
            if (resp && resp.status === 1) {
                var data = resp.data;
                var $tbody = $('#kpiOverviewTbody');
                $tbody.empty();
                
                if (!data || data.length === 0) {
                    $tbody.append('<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data KPI anggota tim untuk periode ini.</td></tr>');
                    renderOverviewBarChart([], [], []);
                    return;
                }
                
                var labels = [];
                var assignedData = [];
                var doneData = [];
                
                $.each(data, function(idx, item) {
                    labels.push(item.name.split(' ')[0]);
                    assignedData.push(item.assigned);
                    doneData.push(item.done);
                    
                    var rank = idx + 1;
                    var rankBadge = '<b>' + rank + '</b>';
                    var bestBadge = '';
                    
                    if (rank === 1 && item.done > 0) {
                        rankBadge = '<span class="badge badge-warning text-dark px-2 py-1" style="font-size:12px;"><i class="fa fa-trophy mr-1"></i> #1</span>';
                        bestBadge = '<span class="badge badge-pill ml-2 px-2 py-1" style="background:#B75301; color:#fff; font-size:10px; font-weight:700;"><i class="fa fa-star mr-1"></i>BEST EMPLOYEE</span>';
                    } else if (rank === 2 && item.done > 0) {
                        rankBadge = '<span class="badge badge-secondary px-2 py-1" style="font-size:12px;">#2</span>';
                    } else if (rank === 3 && item.done > 0) {
                        rankBadge = '<span class="badge badge-light border text-dark px-2 py-1" style="font-size:12px;">#3</span>';
                    }
                    
                    var rowHtml = `
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td class="text-center align-middle">${rankBadge}</td>
                            <td class="text-left align-middle">
                                <div class="d-flex align-items-center">
                                    <img src="${item.avatar}" class="rounded-circle border mr-3" style="width:40px; height:40px; object-fit:cover;">
                                    <div>
                                        <div style="font-weight:700; color:#333; font-size:14px;">
                                            ${item.name} ${bestBadge}
                                        </div>
                                        <small class="text-muted">${item.job_title}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center align-middle font-weight-bold" style="font-size:14px; color:#007bff;">${item.assigned}</td>
                            <td class="text-center align-middle font-weight-bold" style="font-size:14px; color:#28a745;">${item.done}</td>
                            <td class="align-middle">
                                <div class="progress" style="height:8px; margin-bottom:4px; background-color:#e9ecef; border-radius:10px;">
                                    <div class="progress-bar" role="progressbar" style="width:${item.kpi_pct}%; background:linear-gradient(90deg,#CD874D 10%,#B75301 80%); border-radius:10px;"></div>
                                </div>
                                <small style="display:block; color:#555; font-size:11px; font-weight:600;">${item.kpi_pct}% Complete</small>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-sm btn-outline-secondary uni-kpi-detail" data-id="${item.encoded_id}" data-name="${item.name}" title="Lihat Detail User">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                });
                
                renderOverviewBarChart(labels, assignedData, doneData);
            }
        }
    });
});

$(document).on('click', '.uni-kpi-detail', function(){
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#kpiOverviewModal').modal('hide');
    uni_modal("<i class='fa fa-chart-line mr-2'></i> Team KPI Breakdown &mdash; " + name, "view_user_kpi.php?id=" + id, "large");
});

$('#kpiOverviewSavePdfBtn').click(function(){
    var content = $('#kpiOverviewPrintable').clone();
    content.find('.progress').css({'border': '1px solid #000', 'background': 'none'});
    content.find('.progress-bar').css({'background-color': '#000', 'background-image': 'none'});
    content.find('.btn, button').remove();

    var printWindow = window.open('', '', 'width=900,height=600');
    var headContent = `
        <html>
            <head>
                <title>Laporan Team KPI & Best Employee - All Projects</title>
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
</script>

<?php include 'footer.php' ?>