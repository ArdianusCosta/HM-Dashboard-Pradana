<?php 
include 'db_connect.php'; 

if (!function_exists('encode_id')) {
    function encode_id($id) { return base64_encode($id); }
}
require_once __DIR__ . '/helpers/status.helper.php';

// --- PINDAHKAN LOGIKA QUERY KE SINI ---
$stat = array("Pending","Started","On-Progress","On-Hold","Over Due","Done");
$conn->query("UPDATE task_list SET status = 4 WHERE end_date < CURDATE() AND status NOT IN (3,5)");

$where = "";
if(isset($_SESSION['login_type']) && isset($_SESSION['login_id'])) {
    $login_id = $_SESSION['login_id']; 
    if($_SESSION['login_type'] == 2) {
        $where = " WHERE manager_id = '{$login_id}' OR FIND_IN_SET('{$login_id}', user_ids) ";
    } elseif($_SESSION['login_type'] == 3 || $_SESSION['login_type'] == 4) {
        // employees and clients only see projects where they are listed in user_ids
        $where = " WHERE FIND_IN_SET('{$login_id}', user_ids) ";
    }
} 
$qry = $conn->query("SELECT * FROM project_list $where ORDER BY name ASC");
?>

<head>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap4.min.css">

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> 
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<div class="col-lg-12">
    <!-- Desktop Layout (unchanged from original) -->
    <div class="pb-3 d-none d-md-block">
        <div class="row">
            <div class="col-md-6">
                <h3 class="m-0">Project Progress</h3>
            </div>

            <div class="col-md-6">
                  <div class="d-flex justify-content-end">
                      <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 3): ?>
                          <div class="card-tools">
                            <button type="button" class="btn text-white" style="background-color:#B75301;" id="new_project_btn">
                              <i class="fa fa-plus mr-2"></i> Add Project
                            </button>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
        </div>
    </div>
    
    <!-- Mobile Layout -->
    <div class="pb-3 d-block d-md-none">
        <div class="row">
            <div class="col-12 mb-2">
                <h3 class="m-0">Project Progress</h3>
            </div>
        </div>
    </div>

    <!-- Search and Sort Section -->
    <div class="mb-3">
        <div class="row align-items-center">
            <!-- Filter dan Sort di KIRI -->
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <div class="d-flex flex-wrap justify-content-center justify-content-md-start">
                    <div class="dropdown mr-2 mb-2 mb-md-0">
                        <button class="btn dropdown-toggle text-white" 
                                type="button" 
                                id="statusFilterDropdown"
                                data-toggle="dropdown" 
                                aria-expanded="false" 
                                style="background-color:#B75301;">
                            <i class="fa fa-filter mr-1"></i> <span id="statusFilterLabel">All Status</span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="statusFilterDropdown">
                            <a class="dropdown-item status-filter" href="#" data-status="-1">All Status</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item status-filter" href="#" data-status="0">Pending</a>
                            <a class="dropdown-item status-filter" href="#" data-status="1">Started</a>
                            <a class="dropdown-item status-filter" href="#" data-status="2">On-Progress</a>
                            <a class="dropdown-item status-filter" href="#" data-status="3">On-Hold</a>
                            <a class="dropdown-item status-filter" href="#" data-status="4">Over Due</a>
                            <a class="dropdown-item status-filter" href="#" data-status="5">Done</a>
                        </div>
                    </div>

                    <div class="dropdown mr-2 mb-2 mb-md-0">
                        <button class="btn dropdown-toggle text-white" 
                                type="button" 
                                id="sortDropdown"
                                data-toggle="dropdown" 
                                aria-expanded="false" 
                                style="background-color:#B75301;">
                            <i class="fa fa-sort mr-1"></i> <span id="sortLabel">Sort: Name (A-Z)</span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="sortDropdown">
                            <a class="dropdown-item sort-option" href="#" data-sort="name-asc">Name (A-Z)</a>
                            <a class="dropdown-item sort-option" href="#" data-sort="name-desc">Name (Z-A)</a>
                        </div>
                    </div>
                    
                    <!-- Add Project Button - Mobile -->
                    <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 3): ?>
                    <div class="mb-2 mb-md-0 d-md-none">
                        <button type="button" class="btn text-white" style="background-color:#B75301;" id="new_project_btn_mobile">
                            <i class="fa fa-plus mr-1"></i> Add Project
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Search di KANAN -->
            <div class="col-12 col-md-6">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           id="searchProject" 
                           placeholder="Search by project name or assignee...">
                    <div class="input-group-append">
                        <span class="input-group-text" style="background-color:#B75301; color:white; border:none;">
                            <i class="fa fa-search"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="main-project-container shadow-sm mb-4">
    
    <div class="project-table-header d-none d-md-flex align-items-center">
        <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 4): ?>
        <div style="width: 25%;" class="text-center text-uppercase font-weight-bold small text-muted">Project Title</div>
        <div style="width: 15%;" class="text-center text-uppercase font-weight-bold small text-muted">Status</div>
        <div style="width: 30%;" class="text-center text-uppercase font-weight-bold small text-muted">Progress</div>
        <div style="width: 25%;" class="text-center text-uppercase font-weight-bold small text-muted">Assignee</div>
        <?php else: ?>
        <div style="width: 40%;" class="text-center text-uppercase font-weight-bold small text-muted">Project Title</div>
        <div style="width: 20%;" class="text-center text-uppercase font-weight-bold small text-muted">Status</div>
        <div style="width: 40%;" class="text-center text-uppercase font-weight-bold small text-muted">Progress</div>
        <?php endif; ?>
        <div class="action-cell"></div>
    </div>

    <div id="project-list-container">
    <?php if($qry && $qry->num_rows > 0): while($row = $qry->fetch_assoc()): 
        $tprog = $conn->query("SELECT id FROM task_list where project_id = {$row['id']}")->num_rows;
        $cprog = $conn->query("SELECT id FROM task_list where project_id = {$row['id']} and status = 5")->num_rows;
        $prog = $tprog > 0 ? number_format(($cprog/$tprog) * 100, 2) : 0;
        $uids = !empty($row['user_ids']) ? explode(",", $row['user_ids']) : [];
    ?>
    
    <div class="project-item-row d-flex align-items-center project-row" 
        data-id="<?= $row['id'] ?>" 
        data-encoded-id="<?= encode_id($row['id']) ?>"
        data-status="<?= $row['status'] ?>" 
        data-name="<?= strtolower($row['name']) ?>" 
        data-end-date="<?= strtotime($row['end_date']) ?>"
        data-progress="<?= $prog ?>">
        
        <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 4): ?>
        <div style="width: 25%;" class="pl-4">
            <h6 class="font-weight-bold mb-0 text-dark"><?= ucwords($row['name']) ?></h6>
            <small class="text-muted">Due: <?= date("Y-m-d", strtotime($row['end_date'])) ?></small>
        </div>

        <div style="width: 15%;" class="text-center">
            <?= renderStatusBadge((int)$row['status'], $stat); ?>
        </div>

        <div style="width: 30%;" class="px-3">
            <div class="custom-progress-container">
                <div class="custom-progress-bar" 
                    style="--target-width: <?= $prog ?>%; width: 0%;">
                </div>
            </div>
            <div class="mt-1 text-left">
                <small class="text-muted font-weight-bold">
                    <span class="count-up" data-target="<?= $prog ?>"><?= $prog ?></span>% Complete
                </small>
            </div>
        </div>
        <?php else: ?>
        <div style="width: 40%;" class="pl-4">
            <h6 class="font-weight-bold mb-0 text-dark"><?= ucwords($row['name']) ?></h6>
            <small class="text-muted">Due: <?= date("Y-m-d", strtotime($row['end_date'])) ?></small>
        </div>

        <div style="width: 20%;" class="text-center">
            <?= renderStatusBadge((int)$row['status'], $stat); ?>
        </div>

        <div style="width: 40%;" class="px-3">
            <div class="custom-progress-container">
                <div class="custom-progress-bar" 
                    style="--target-width: <?= $prog ?>%; width: 0%;">
                </div>
            </div>
            <div class="mt-1 text-left">
                <small class="text-muted font-weight-bold">
                    <span class="count-up" data-target="<?= $prog ?>"><?= $prog ?></span>% Complete
                </small>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 4): ?>
        <div style="width: 25%;" class="text-center">
            <div class="d-flex align-items-center justify-content-center avatar-group">
                <?php 
                if(!empty($uids)):
                    $valid_uids = array_filter($uids, 'is_numeric');
                    if (!empty($valid_uids)):
                        $users_qry = $conn->query("SELECT avatar, firstname FROM users WHERE id IN (".implode(",", $valid_uids).") LIMIT 5");
                        while($u = $users_qry->fetch_assoc()):
                            $avatar = !empty($u['avatar']) ? 'assets/uploads/'.$u['avatar'] : 'assets/uploads/empty-placeholder.png';
                ?>
                    <img src="<?= $avatar ?>" class="rounded-circle border border-white shadow-sm" 
                        title="<?= $u['firstname'] ?>" 
                        style="width:34px; height:34px; object-fit:cover; background-color: #d1d1d1;"
                        onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
                <?php endwhile; endif; endif; ?>

                <?php if (count($uids) > 5): ?>
                    <div class="more-users-badge">+<?= count($uids) - 5 ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="action-cell">
            <div class="dropdown">
                <button class="btn btn-action-circle" type="button" data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                    <i class="fa fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow-lg border-0">
                    <a class="dropdown-item py-2" href="index.php?page=view_project&id=<?= encode_id($row['id']) ?>">
                        <i class="fa fa-eye mr-2 text-primary"></i> View
                    </a>
                    
                    <?php if(isset($_SESSION['login_type']) && in_array($_SESSION['login_type'], [1, 2])): ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item py-2 duplicate_project_trigger" 
                           href="javascript:void(0)" 
                           data-id="<?= $row['id'] ?>" 
                           data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"
                           data-toggle="modal"
                           data-target="#duplicateProjectModal">
                            <i class="fa fa-copy mr-2 text-info"></i> Duplicate
                        </a>
                        <a class="dropdown-item py-2" href="index.php?page=edit_project&id=<?= encode_id($row['id']) ?>">
                            <i class="fa fa-pencil-alt mr-2 text-dark"></i> Edit
                        </a>
                        <a class="dropdown-item py-2 text-danger delete_project_trigger" 
                           href="javascript:void(0)" 
                           data-id="<?= $row['id'] ?>" 
                           data-name="<?= htmlspecialchars(ucwords($row['name'])) ?>"
                           data-toggle="modal" 
                           data-target="#deleteProjectModal">
                            <i class="fa fa-trash mr-2"></i> Delete
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div> 
            </div> <?php endwhile; else: ?>
                <div class="p-5 text-center text-muted font-italic">Belum ada proyek yang tersedia.</div>
            <?php endif; ?>
        </div>

        <div class="modal fade" id="duplicateProjectModal" tabindex="-1" role="dialog" aria-labelledby="duplicateProjectModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #007bff; color: white;">
                        <h5 class="modal-title" id="duplicateProjectModalLabel"><i class="fa fa-copy mr-2"></i> Konfirmasi Duplikasi Proyek</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        Apakah Anda yakin ingin menduplikasi proyek: <b id="projectToDuplicateName"></b> beserta seluruh tugas di dalamnya?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="confirmDuplicateProjectBtn"><i class="fa fa-copy mr-1"></i> Duplikasi Proyek</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteProjectModal" tabindex="-1" role="dialog" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteProjectModalLabel">Confirm Deletion</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete the project: <b id="projectToDeleteName"></b>?
                        This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteProjectBtn">Delete Project</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="usersModal" tabindex="-1" role="dialog" aria-labelledby="usersModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header" style="background-color:#B75301; color:white;">
                <h5 class="modal-title" id="usersModalLabel">All Assignee</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="usersModalBody">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
            </div>
        </div>
        </div>

<style>
/* Menargetkan semua input group agar memiliki radius membulat */
.input-group {
    border-radius: 20px !important;
    overflow: hidden; /* Penting agar elemen di dalam ikut melengkung */
}

/* Memaksa input agar melengkung di sisi kiri */
.input-group > .form-control {
    border-top-left-radius: 20px !important;
    border-bottom-left-radius: 20px !important;
    border-right: none !important;
}

/* Memaksa icon search agar melengkung di sisi kanan */
.input-group > .input-group-append > .input-group-text {
    border-top-right-radius: 20px !important;
    border-bottom-right-radius: 20px !important;
    border-left: none !important;
    background-color: #B75301 !important; /* Warna cokelat Hai Motion */
    color: white !important;
}

/* Menghilangkan efek biru Bootstrap yang bisa merusak tampilan rounded saat diklik */
.form-control:focus {
    box-shadow: none !important;
    border-color: #ced4da !important;
}

/* 1. Reset & Standardisasi Tombol Utama */
button.btn.text-white[style*="background-color:#B75301"], 
button.btn.text-white[style*="background-color: #B75301"],
#new_project_btn,
#new_project_btn_mobile {
    background-color: #B75301 !important;
    border-radius: 10px !important; /* Radius modern, tidak terlalu bulat tapi tidak kaku */
    padding: 10px 20px !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border: none !important;
    box-shadow: 0 4px 14px rgba(183, 83, 1, 0.25) !important; /* Shadow halus sewarna tombol */
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* 2. Efek Hover: Tombol sedikit naik dan shadow menebal */
button.btn.text-white[style*="background-color:#B75301"]:hover,
#new_project_btn:hover {
    background-color: #964401 !important; /* Warna cokelat lebih gelap */
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(183, 83, 1, 0.35) !important;
}

/* 3. Efek Click (Active) */
button.btn.text-white[style*="background-color:#B75301"]:active {
    transform: translateY(0);
}

/* 4. Merapikan Dropdown Menu agar Searah dengan Tombol */
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
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: #FFF5EE !important; /* Warna peach sangat muda */
    color: #B75301 !important;
    padding-left: 20px !important; /* Efek geser sedikit saat hover */
}

/* 5. Khusus untuk Spasi Icon agar rapi */
button.btn i {
    font-size: 14px !important;
    margin-right: 8px !important;
}

/* Frame Putih Utama */
.main-project-container {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #eef0f2;
}

.project-table-header {
    border-radius: 20px 20px 0 0;
}

#project-list-container .project-item-row:last-child {
    border-radius: 0 0 20px 20px;
}

/* Header di dalam frame */
.project-table-header {
    background-color: #B75301;
    padding: 15px 0;
    border-bottom: 1px solid #f0f2f5;
}

/* Baris tiap proyek */
.project-item-row {
    padding: 22px 0;
    border-bottom: 1px solid #f8f9fa;
    transition: background 0.2s ease;
    cursor: pointer;
}

.project-item-row:last-child {
    border-bottom: none;
}

/* Efek Hover baris */
.project-item-row:hover {
    background-color: #fcfdfe;
}

/* Container Utama Kolom Aksi */
.main-project-container .action-cell {
    width: 5% !important;
    min-width: 60px !important; /* Memberi ruang agar bulatan tidak terpotong */
    padding: 0 !important;
    margin: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: none !important;
}

/* Gabungan Kode Baru: Tombol Aksi & Ikon (Versi Profesional Hai Motion) */
.main-project-container .btn-action-circle {
    width: 40px !important;
    height: 40px !important;
    padding: 0 !important;
    margin: 0 !important;
    border-radius: 50% !important;
    background-color: transparent !important;
    border: 1px solid transparent !important; 
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #6c757d !important;
    cursor: pointer !important;
    position: relative !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: none !important;
}

/* Efek Hover Profesional */
.main-project-container .btn-action-circle:hover, 
.main-project-container .dropdown.show .btn-action-circle {
    background-color: #FFF5EE !important; /* Warna peach khas Hai Motion */
    color: #B75301 !important; /* Cokelat Utama */
    border: 1px solid rgba(183, 83, 1, 0.1) !important;
    box-shadow: 0 4px 10px rgba(183, 83, 1, 0.12) !important;
    transform: translateY(-1px);
}

/* Styling Ikon agar Terkunci di Tengah */
.main-project-container .btn-action-circle i {
    margin: 0 !important;
    padding: 0 !important;
    font-size: 18px !important;
    line-height: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: transform 0.3s ease !important;
    pointer-events: none;
}

/* Animasi Ikon saat Hover */
.main-project-container .btn-action-circle:hover i {
    transform: scale(1.1);
}

.more-users-badge {
    background-color: #B75301; /* Warna cokelat Hai Motion */
    color: white;
    font-size: 12px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
}

/* Penyesuaian Avatar agar overlap rapi */
.avatar-group img, .more-users-badge {
    width: 34px;
    height: 34px;
    object-fit: cover;
    margin-left: -10px;
    box-shadow: 0 0 0 2px #fff;
    border-radius: 50%;
}
.action-btn, .action-btn:focus, .action-btn:active {
    outline: none !important;
    box-shadow: none !important;
    color: #6c757d;
}

.dropdown-item {
    font-size: 0.95rem;
    font-weight: 500;
    color: #444;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    color: #B75301;
}

@keyframes progressAnimation {
    0% { width: 0%; }
    100% { width: var(--target-width); }
}

/* Efek Motion: Datang dari Kiri ke Kanan */
@keyframes slideAssigneeLeftToRight {
    0% {
        opacity: 0;
        transform: translateX(-30px); /* Muncul dari sisi kiri */
    }
    100% {
        opacity: 1;
        transform: translateX(0); /* Berhenti di posisi aslinya */
    }
}

/* Targetkan elemen di dalam grup avatar */
.avatar-group img, .avatar-group .more-users-badge {
    opacity: 0;
    animation: slideAssigneeLeftToRight 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

/* Staggered Delay: Memberikan efek mengalir satu per satu */
.avatar-group img:nth-child(1) { animation-delay: 0.1s; }
.avatar-group img:nth-child(2) { animation-delay: 0.2s; }
.avatar-group img:nth-child(3) { animation-delay: 0.3s; }
.avatar-group img:nth-child(4) { animation-delay: 0.4s; }
.avatar-group img:nth-child(5) { animation-delay: 0.5s; }
.avatar-group .more-users-badge { animation-delay: 0.6s; }

.dropdown-menu {
    display: block !important;
    visibility: hidden;
    opacity: 0;
    /* Ubah origin ke top agar jatuhnya dari arah tombol */
    transform-origin: top center; 
    /* Start position: agak di atas dan mengecil */
    transform: translateY(-20px) scale(0.95); 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
    border-radius: 12px !important;
    margin-top: 10px !important;
}

.dropdown.show .dropdown-menu {
    visibility: visible;
    opacity: 1;
    /* End position: posisi normal tanpa ada sumbu X */
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

.dropdown.show .dropdown-item {
    animation: dropInItem 0.3s ease forwards;
}

/* Frame Bulat tombol saat aktif */
.btn-action-circle:focus, 
.dropdown.show .btn-action-circle {
    background-color: #f0f2f5;
    color: #B75301;
}

@keyframes dropInItem {
    from {
        opacity: 0;
        transform: translateY(-10px); /* Pastikan Y, bukan X */
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Progress Bar Cokelat */
.custom-progress-container {
    height: 10px;
    background: #f0f2f5;
    border-radius: 10px;
    overflow: hidden;
    width: 100%;
}
.custom-progress-bar {
    height: 100%;
    /* Menggunakan Linear Gradient dari Cokelat Muda ke Cokelat Tua */
    background: linear-gradient(90deg, #CD874D 10%, #B75301 80%); 
    border-radius: 10px;
    /* Tetap mempertahankan animasi motion Anda */
    animation: progressAnimation 2s ease-in-out forwards;
}

.project-item-row .text-muted.small {
    font-size: 0.75rem;
    display: block;
    margin-top: 5px;
}

.project-table-header div {
    color: #ffffff !important; 
    font-weight: bold;
    font-size: 0.85rem; 
}

/* ================================================= */
/* 2. REVISI: Ukuran Modal-XL dikecilkan (sesuai permintaan) */
/* ================================================= */
.modal-xl {
    max-width: 80% !important; /* Dikecilkan dari 90% */
    width: 100% !important;
}

/* ================================================= */
/* 2. REVISI: CUSTOM CSS SELECT2 (Warna Biru) */
/* ================================================= */
/* Warna biru untuk garis border dan fokus */
.select2-container--bootstrap4 .select2-selection--multiple:focus,
.select2-container--bootstrap4.select2-container--focus .select2-selection--multiple {
    border-color: #007bff !important; /* Biru Bootstrap Primary */
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
}
/* Warna biru untuk badge yang dipilih */
.select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
    background-color: #007bff !important; /* Biru Bootstrap Primary */
    border-color: #007bff !important;
    color: white !important;
}
/* Warna X (tombol hapus) pada badge */
.select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
    color: rgba(255, 255, 255, 0.7) !important; 
}


/* CSS LAIN */
table p { margin: 0 !important; }
table td { vertical-align: middle !important; }

/* Menghapus margin-left-8px untuk elemen pertama di d-flex assignment */
.project-assignment .d-flex img:first-child,
.project-assignment .d-flex button:first-child {
    margin-left: 0px !important;
}

.table-responsive {
  overflow-x: auto; 
  -webkit-overflow-scrolling: touch;
}

.table-responsive table {
  min-width: 768px; 
}

.progress-custom {
    border-radius: 10px !important; 
    height: 10px; 
    overflow: hidden; 
}

.progress-bar-custom {
    background-color: #B75301 !important; 
    border-radius: 10px !important;
    height: 100%;
}
  .badge-status {
        border-radius: 20px !important; /* Gunakan nilai pixel yang tinggi agar bulat sempurna (pill style) */
        min-width: 90px;
        display: inline-block;
    }

/*Styling Buat mobile view*/
/* Quick fix: Allow table to be more responsive */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table-responsive table {
    min-width: 600px; /* Reduced from 768px */
}

@media (max-width: 767.98px) {
    /* Hide less important columns on very small screens */
    .table th:nth-child(4), /* Assignee column */
    .table td:nth-child(4) {
        display: none;
    }
    
    .table th:nth-child(5), /* Actions column */
    .table td:nth-child(5) {
        display: none;
    }
    
    .table-responsive table {
        min-width: 400px;
    }
}

@media (max-width: 767.98px) {
    /* Adjust header container */
    .pb-3 {
        padding-bottom: 0.5rem !important;
    }
    
    /* Stack header columns */
    .pb-3 .row {
        flex-direction: column;
        margin-bottom: 0.75rem;
    }
    
    .pb-3 .col-md-6 {
        width: 100%;
        padding: 0.25rem 0;
    }
    
    /* Reduce button size */
    #new_project_btn,
    .btn.dropdown-toggle {
        padding: 0.3rem 0.75rem !important;
        font-size: 0.8rem !important;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Adjust icons */
    #new_project_btn i,
    .btn.dropdown-toggle i {
        font-size: 0.75rem !important;
        margin-right: 0.3rem !important;
    }
    
    /* Make filter/sort buttons inline */
    .d-flex.flex-wrap {
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        margin-bottom: 0.5rem;
        -webkit-overflow-scrolling: touch;
    }
    
    .d-flex.flex-wrap > div {
        flex: 0 0 auto;
        margin-right: 0.5rem !important;
        margin-bottom: 0 !important;
    }
    
    /* Remove dropdown arrow on very small screens */
    @media (max-width: 400px) {
        .btn.dropdown-toggle:after {
            display: none;
        }
        
        #new_project_btn span,
        .btn.dropdown-toggle span {
            font-size: 0.7rem;
        }
    }
    
    /* Search bar adjustments */
    #searchProject {
        height: 34px;
        font-size: 0.85rem;
    }
    
    .input-group-append .input-group-text {
        height: 34px;
        padding: 0 0.75rem;
    }
}

/* For very small phones */
@media (max-width: 360px) {
    #new_project_btn span,
    #statusFilterLabel,
    #sortLabel {
        font-size: 0.65rem;
    }
    
    #new_project_btn i,
    .btn.dropdown-toggle i {
        margin-right: 0.2rem !important;
    }
}

/* ================================================= */
/* MOBILE-ONLY BOTTOM SHEET STYLES */
/* ================================================= */

@media (max-width: 767.98px) {
    /* Hide the original dropdown menus on mobile */
    #statusFilterDropdown + .dropdown-menu,
    #sortDropdown + .dropdown-menu {
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
        background-color: #f8f9fa9c;
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

/* Mobile styles (keep your existing mobile styles) */
@media (max-width: 767.98px) {
    /* Your existing mobile bottom sheet styles here */
}

/* Desktop styles */
@media (min-width: 768px) {
    .bottom-sheet,
    .sheet-backdrop {
        display: none !important;
    }
}

@media (max-width: 767.98px) {
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
    .d-flex.flex-wrap.justify-content-center.justify-content-md-start {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 8px !important;
    }
    
    /* Remove any conflicting margins */
    .d-flex.flex-wrap.justify-content-center.justify-content-md-start .dropdown,
    .d-flex.flex-wrap.justify-content-center.justify-content-md-start .mb-2 {
        margin-right: 0 !important;
        margin-bottom: 0 !important;
    }
    
    /* Adjust the Add Project button specifically */
    #new_project_btn_mobile {
        padding: 6px 12px !important;
        font-size: 13px !important;
        height: 34px !important;
    }
}

@media (max-width: 490px) {
    .project-table-header {
        display: block !important; 
        padding: 12px 0 !important;
        background-color: #B75301 !important; 
        border-radius: 8px 8px 0 0; 
    }

    .project-table-header > div {
        display: none !important;
    }

    /* 3. TEKS "ALL PROJECTS" DI TENGAH */
    .project-table-header::before {
        content: "All Projects";
        display: block;
        width: 100%;
        color: #ffffff !important;
        font-weight: bold;
        font-size: 14px;
        letter-spacing: 1px;
        text-transform: uppercase;
        text-align: center !important; 
        padding: 2px 0;
    }

    /* Setiap baris project: susun dalam grid 2 baris */
    .project-item-row {
        display: grid !important;
        grid-template-columns: 1fr 95px 40px !important; 
        grid-template-rows: auto auto !important;
        padding: 15px 12px !important;
        gap: 8px 12px !important;
        align-items: center !important;
    }
    
    /* Judul Proyek (Baris 1 Kolom 1) */
    .project-item-row > div:nth-child(1) {
        grid-column: 1 !important;
        grid-row: 1 !important;
        padding-left: 0 !important; 
        width: 100% !important;
    }

    /* Status Badge (Baris 1 Kolom 2) */
    .project-item-row > div:nth-child(2) {
        grid-column: 2 !important;
        grid-row: 1 !important;
        display: flex !important;
        justify-content: center !important; 
        align-items: center !important;
        width: 100% !important;
        padding: 0 !important;
    }

    /* 2. ATUR BADGE-NYA SENDIRI */
    .project-item-row > div:nth-child(2) .badge-status {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: unset !important;
        width: 90px !important;
        font-size: 0.7rem !important;
        text-align: center !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        margin: 0 !important; 
    }

    /* Action Cell (Baris 1 & 2 Kolom 3) */
    .action-cell {
        grid-column: 3 !important;
        grid-row: 1 / span 2 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        margin: 0 !important;
    }

    /* Progress Bar */
    .project-item-row > div:nth-child(3) {
        grid-column: 1 !important;
        grid-row: 2 !important;
        padding: 0 !important; 
        width: 80% !important; 
        min-width: 0 !important;
    }

    /* Pastikan kontainer progress bar memenuhi ruang */
    .custom-progress-container {
        width: 100% !important;
        margin-right: 0 !important;
    }

    /* Assignee/Avatar (Baris 2 Kolom 2) */
    .project-item-row > div:nth-child(4) {
        grid-column: 2 !important;
        grid-row: 2 !important;
        width: 100% !important; 
        padding: 0 !important;  
        display: flex !important;
        justify-content: center !important; /* Memastikan grup ada di tengah kolom */
        align-items: center !important;
    }

    /* 2. KUNCI UTAMA: Atur Grup Avatar agar lebarnya sama dengan Badge */
    .avatar-group {
        display: flex !important;
        width: auto !important; 
        max-width: 95px !important; 
        justify-content: center !important; 
        align-items: center !important;
    }

    /* 3. Ukuran & Efek Tumpuk Foto */
    .avatar-group img,
    .avatar-group .more-users-badge {
        width: 24px !important;  
        height: 24px !important; 
        font-size: 8px !important; 
        margin-left: -10px !important; 
        flex-shrink: 0 !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .avatar-group img:first-child,
    .avatar-group .more-users-badge:first-child {
        margin-left: 0 !important; 
    }
}
</style>

<script>
function alert_toast(message, type = 'success') {
    console.log("Notifikasi:", message, type);
    alert(`[${type.toUpperCase()}] ${message}`); 
}

function uni_modal(title, url, size = 'lg') { 
    var $modal = $('#uni_modal');
    
    $modal.find('.modal-dialog')
        .removeClass('modal-sm modal-md modal-lg modal-xl')
        .addClass('modal-' + size); 

    $modal.find('.modal-header').css('background-color', '#B75301').css('color', 'white');
    $modal.find('.modal-title').html(title).css('color', 'white'); 
    $modal.find('.close').css('color', 'white');

    $modal.find('.modal-body').html('Loading...');
    
    $.ajax({
        url: url,
        success: function(resp){
            if(resp){
                $modal.find('.modal-body').html(resp);
                
                if ($.fn.summernote) {
                    $modal.find('.summernote').summernote({
                        height: 200,
                    });
                }

                if ($.fn.select2) {
                    $modal.find('.select2').select2({
                        placeholder: "Select an Option",
                        width: '100%',
                        theme: 'bootstrap4', 
                        dropdownParent: $modal 
                    });
                }
                
                $modal.modal('show');
            }
        }
    });
}

$(document).ready(function() {
    $('.count-up').each(function() {
        var $this = $(this);
        var countTo = parseFloat($this.attr('data-target')); // Nilai target dari database

        $({ countNum: 0 }).animate({
            countNum: countTo
        },
        {
            duration: 2000, // Durasi 2 detik (samakan dengan durasi animasi bar)
            easing: 'swing',
            step: function() {
                // Mengubah angka saat bergulir dengan 2 desimal
                $this.text(this.countNum.toFixed(2));
            },
            complete: function() {
                // Memastikan angka akhir tepat sesuai database
                $this.text(this.countNum.toFixed(2));
            }
        });
    });
}); 

$(document).ready(function(){
    // Global variables
    var currentStatusFilter = -1; // -1 = All Status
    var currentSort = 'name-asc';
    
    // Check if mobile
    function isMobile() {
        return $(window).width() < 768;
    }
    
    // Modal cleanup
    $('#uni_modal').on('hidden.bs.modal', function (e) {
        if ($.fn.summernote) {
            $(this).find('.summernote').summernote('destroy');
        }
        
        if ($.fn.select2) {
            $(this).find('.select2-hidden-accessible').each(function() {
                $(this).select2('destroy'); 
            });
            $(this).find('.select2-container').remove(); 
        }
        $(this).find('.modal-body').html(''); 
    });
    
    // Row click handler
    $(document).on('click', '.project-row', function(e){
        if ($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .view_all_users').length === 0) {
            var encoded_pid = $(this).data('encoded-id'); 
            window.location.href = "index.php?page=view_project&id=" + encoded_pid;
        }
    });
    
    // New project button
    $('#new_project_btn').click(function(){
        window.location.href = "index.php?page=new_project"; 
    });

        // New project button - Mobile
    $('#new_project_btn_mobile').click(function(){
        window.location.href = "index.php?page=new_project"; 
    });

    // Delete project function
    function delete_project(id){
        $.ajax({
            url: 'ajax.php?action=delete_project',
            method: 'POST',
            data: { id },
            success: function(resp){
                if(resp.trim() == 1){
                    alert_toast("Project delete success", "success");
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert_toast("error delete project", "danger");
                }
            }
        });
    }

    // Delete project triggers
    $(document).on('click', '.delete_project_trigger', function(e){
        e.stopPropagation();
        var id = $(this).data('id'); 
        var name = $(this).data('name');

        $('#confirmDeleteProjectBtn').data('id', id);
        $('#projectToDeleteName').text(name);
    });

    $(document).on('click', '#confirmDeleteProjectBtn', function(){
        var id = $(this).data('id');
        $('#deleteProjectModal').modal('hide'); 
        delete_project(id); 
    });

    // Duplicate project trigger
    $(document).on('click', '.duplicate_project_trigger', function(e){
        e.stopPropagation();
        var id = $(this).data('id');
        var name = $(this).data('name');

        $('#confirmDuplicateProjectBtn').data('id', id);
        $('#projectToDuplicateName').text(name);
    });

    $(document).on('click', '#confirmDuplicateProjectBtn', function(){
        var id = $(this).data('id');
        $('#duplicateProjectModal').modal('hide'); 

        start_load();
        $.ajax({
            url: 'ajax.php?action=duplicate_project',
            method: 'POST',
            data: { id: id },
            success: function(resp){
                if (resp.trim() == 1) {
                    alert_toast("Proyek berhasil diduplikasi!", "success");
                    setTimeout(function(){
                        location.reload();
                    }, 1000);
                } else {
                    alert_toast("Gagal menduplikasi proyek", "danger");
                    end_load();
                }
            },
            error: function(){
                alert_toast("Terjadi kesalahan sistem", "danger");
                end_load();
            }
        });
    });

    // View all users
    $(document).on('click', '.view_all_users', function(e){
        e.stopPropagation();
        const users = $(this).data('users'); 
        const modalBody = $('#usersModalBody');
        let htmlContent = '';

        if (Array.isArray(users) && users.length > 0) {
            $('#usersModalLabel').text(`All Assigned Members (${users.length})`);
            
            users.forEach(user => {
                const avatar = user.avatar ? `assets/uploads/${user.avatar}` : 'assets/uploads/empty-placeholder.png';
                const fullName = `${user.firstname} <span class="user-lastname">${user.lastname}</span>`;

                htmlContent += `
                    <div class="d-flex align-items-center mb-2">
                        <img src="${avatar}" 
                             class="rounded-circle border border-secondary mr-3" 
                             style="width:45px; height:45px; object-fit:cover;" 
                             alt="${user.firstname} ${user.lastname}"
                             onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
                        <b>${fullName}</b>
                    </div>
                `;
            });
        } else {
            $('#usersModalLabel').text(`All Assigned Members (0)`);
            htmlContent = '<p class="text-center text-muted">No members assigned to this project.</p>';
        }

        modalBody.html(htmlContent);
    });

    // ============================================
    // BOTTOM SHEET FUNCTIONS
    // ============================================
    
    // Show bottom sheet
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
    
    // Close bottom sheet
    function closeBottomSheet() {
        $('#bottomSheet').removeClass('active');
        $('.sheet-backdrop').removeClass('active');
        
        setTimeout(function() {
            $('.bottom-sheet, .sheet-backdrop').remove();
            $('body').removeClass('sheet-open');
        }, 300);
    }
    
    // ============================================
    // FILTER/SORT FUNCTIONS
    // ============================================
    
function applyFilters() {
    var searchValue = $('#searchProject').val().toLowerCase();
    
    $('.project-row').each(function() {
        // Ambil data dari atribut data-* yang kita buat di PHP
        var projectStatus = parseInt($(this).attr('data-status'));
        var projectName = $(this).attr('data-name'); // Sudah di-lowercase di PHP
        var shouldShow = true;
        
        // 1. Filter berdasarkan status
        if (currentStatusFilter !== -1 && projectStatus !== currentStatusFilter) {
            shouldShow = false;
        }
        
        // 2. Filter berdasarkan search (Nama Proyek)
        if (shouldShow && searchValue !== '') {
            if (projectName.indexOf(searchValue) === -1) {
                shouldShow = false;
            }
        }
        
        // Eksekusi Tampilan
        if (shouldShow) {
            $(this).attr('style', 'display: flex !important'); // Pastikan flex tetap aktif
        } else {
            $(this).attr('style', 'display: none !important');
        }
    });
    
    // Toggle pesan jika tidak ada hasil
    var visibleRows = $('.project-row:visible').length;
    if (visibleRows === 0) {
        if (!$('#noResultsRow').length) {
            $('#project-list-container').append('<div id="noResultsRow" class="p-5 text-center text-muted font-italic">No projects found.</div>');
        }
    } else {
        $('#noResultsRow').remove();
    }
}

function sortProjects(sortType) {
    var $container = $('#project-list-container');
    var $rows = $('.project-row').toArray();
    
    $rows.sort(function(a, b) {
        var aVal, bVal;
        
        switch(sortType) {
            case 'name-asc':
                aVal = $(a).attr('data-name');
                bVal = $(b).attr('data-name');
                return aVal.localeCompare(bVal);
                
            case 'name-desc':
                aVal = $(a).attr('data-name');
                bVal = $(b).attr('data-name');
                return bVal.localeCompare(aVal);
                
            case 'date-asc':
                aVal = parseInt($(a).attr('data-end-date'));
                bVal = parseInt($(b).attr('data-end-date'));
                return aVal - bVal;
            
            case 'progress-desc':
                aVal = parseFloat($(a).attr('data-progress'));
                bVal = parseFloat($(b).attr('data-progress'));
                return bVal - aVal;
                
            default:
                return 0;
        }
    });
    
    // Susun ulang di DOM
    $.each($rows, function(index, row) {
        $container.append(row);
    });
}
    
    // ============================================
    // INITIALIZATION - MOBILE VS DESKTOP
    // ============================================
    
    function initInterface() {
        if (isMobile()) {
            // MOBILE: Use bottom sheets
            
            // Hide dropdown menus
            $('#statusFilterDropdown + .dropdown-menu, #sortDropdown + .dropdown-menu').hide();
            
            // Status Filter Button
                        // Status Filter Button
            $('#statusFilterDropdown').off('click.mobile').on('click.mobile', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var content = `
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>All Status</span>
                            ${currentStatusFilter === -1 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="0">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Pending</span>
                            ${currentStatusFilter === 0 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Started</span>
                            ${currentStatusFilter === 1 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>On-Progress</span>
                            ${currentStatusFilter === 2 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>On-Hold</span>
                            ${currentStatusFilter === 3 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Over Due</span>
                            ${currentStatusFilter === 4 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="5">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Done</span>
                            ${currentStatusFilter === 5 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                `;
                
                showBottomSheet('Filter by Status', content);
                return false;
            });
            
            // Sort Button
            $('#sortDropdown').off('click.mobile').on('click.mobile', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var content = `
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="name-asc">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Name (A-Z)</span>
                            ${currentSort === 'name-asc' ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="name-desc">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Name (Z-A)</span>
                            ${currentSort === 'name-desc' ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                `;
                
                showBottomSheet('Sort Options', content);
                return false;
            });
            
            // Handle sheet item clicks
            $(document).off('click.sheet-item').on('click.sheet-item', '.sheet-filter-item', function(e) {
                e.preventDefault();
                
                var type = $(this).data('type');
                var value = $(this).data('value');
                
                if (type === 'status') {
                    currentStatusFilter = parseInt(value);
                    var statusText = $(this).find('span').text();
                    $('#statusFilterLabel').text(statusText);
                    applyFilters();
                } else if (type === 'sort') {
                    currentSort = value;
                    var sortText = $(this).find('span').text();
                    $('#sortLabel').text('Sort: ' + sortText);
                    sortProjects(currentSort);
                }
                
                closeBottomSheet();
            });
            
            // Disable Bootstrap dropdowns on mobile
            $('.dropdown-toggle').dropdown('dispose');
            
        } else {
            // DESKTOP: Use normal dropdowns
            
            // Show dropdown menus
            $('#statusFilterDropdown + .dropdown-menu, #sortDropdown + .dropdown-menu').show();
            
            // Remove mobile event handlers
            $('#statusFilterDropdown').off('click.mobile');
            $('#sortDropdown').off('click.mobile');
            $(document).off('click.sheet-item');
            
            // Enable Bootstrap dropdowns
            $('.dropdown-toggle').dropdown();
            
            // Desktop filter/sort handlers
            $('.status-filter').off('click.desktop').on('click.desktop', function(e) {
                e.preventDefault();
                currentStatusFilter = parseInt($(this).data('status'));
                $('#statusFilterLabel').text($(this).text());
                applyFilters();
            });
            
            $('.sort-option').off('click.desktop').on('click.desktop', function(e) {
                e.preventDefault();
                currentSort = $(this).data('sort');
                $('#sortLabel').text('Sort: ' + $(this).text());
                sortProjects(currentSort);
            });
            
            // Close any open sheet
            closeBottomSheet();
        }
    }
    
    // ============================================
    // INITIAL SETUP
    // ============================================
    
    // Initialize interface based on screen size
    initInterface();
    
    // Reinitialize on window resize
    $(window).on('resize', function() {
        initInterface();
    });
    
    // Search functionality
    $('#searchProject').on('keyup', applyFilters);
    
    // Initialize dropdowns for desktop
    $('.dropdown-toggle').dropdown({ display: 'static' });
});
</script>