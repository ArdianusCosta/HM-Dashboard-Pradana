<?php
include 'db_connect.php';
require_once __DIR__ . '/helpers/status.helper.php';

?>


<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<?php
// Ambil ID user yang sedang login
$current_user_id = $_SESSION['login_id'];

// 💡 DEKLARASI FUNGSI ENCODER/DECODER UNTUK KEAMANAN
// Fungsi encode_id() diasumsikan tersedia dari db_connect.php
$encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; };


// ===== FILTER PROJECT SESUAI USER YANG LOGIN =====
$where = " WHERE 1=1 ";
if ($_SESSION['login_type'] == 2) {
    $where .= " AND (p.manager_id = '{$current_user_id}' 
                   OR CONCAT('[', REPLACE(p.user_ids, ',', '],['), ']') LIKE '%[{$current_user_id}]%') ";
} elseif ($_SESSION['login_type'] == 3) {
    $where .= " AND CONCAT('[', REPLACE(p.user_ids, ',', '],['), ']') LIKE '%[{$current_user_id}]%' ";
}

// Status mapping
$stat = [
    0 => "Pending",
    1 => "Started",
    2 => "On-Progress",
    3 => "On-Hold",
    4 => "Over Due",
    5 => "Done"
];
?>

<div class="container-fluid mb-3">
    <!-- Desktop Layout (unchanged from original) -->
    <div class="row align-items-center d-none d-md-flex">
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <h3 class="m-0">My Task</h3>
        </div>
        <div class="col-12 col-md-6">
            <div class="d-flex justify-content-center justify-content-md-end">
                <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                    <i class="fa fa-plus"></i> Add Task
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Layout -->
    <div class="d-block d-md-none">
        <!-- Mobile Header Row -->
        <div class="row align-items-center">
            <div class="col-12 mb-2">
                <h3 class="m-0">My Task</h3>
            </div>
        </div>
        
        <!-- Mobile Buttons and Search Row -->
        <div class="row align-items-center">
            <!-- Left side: Filter, Sort, and Add Task -->
            <div class="col-12 mb-2">
                <div class="d-flex flex-wrap align-items-center">
                    <!-- Filter Button -->
                    <div class="dropdown mr-2 mb-2">
                        <button class="btn dropdown-toggle text-white" 
                                type="button" 
                                id="statusMyTaskDropdownMobile"
                                data-toggle="dropdown" 
                                aria-expanded="false" 
                                style="background-color:#B75301;">
                            <i class="fa fa-filter mr-1"></i> <span id="statusMyTaskLabelMobile">All Status</span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="statusMyTaskDropdownMobile">
                            <a class="dropdown-item status-mytask-filter-mobile" href="#" data-status="-1">All Status</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item status-mytask-filter-mobile" href="#" data-status="0">Pending</a>
                            <a class="dropdown-item status-mytask-filter-mobile" href="#" data-status="1">Started</a>
                            <a class="dropdown-item status-mytask-filter-mobile" href="#" data-status="2">On-Progress</a>
                            <a class="dropdown-item status-mytask-filter-mobile" href="#" data-status="3">On-Hold</a>
                            <a class="dropdown-item status-mytask-filter-mobile" href="#" data-status="4">Over Due</a>
                            <a class="dropdown-item status-mytask-filter-mobile" href="#" data-status="5">Done</a>
                        </div>
                    </div>
                    
                    <!-- Sort Button -->
                    <div class="dropdown mr-2 mb-2">
                        <button class="btn dropdown-toggle text-white" 
                                type="button" 
                                id="sortMyTaskDropdownMobile"
                                data-toggle="dropdown" 
                                aria-expanded="false" 
                                style="background-color:#B75301;">
                            <i class="fa fa-sort mr-1"></i> <span id="sortMyTaskLabelMobile">Sort: Task Name (A-Z)</span>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="sortMyTaskDropdownMobile">
                            <a class="dropdown-item sort-mytask-option-mobile" href="#" data-sort="task-asc">Task Name (A-Z)</a>
                            <a class="dropdown-item sort-mytask-option-mobile" href="#" data-sort="task-desc">Task Name (Z-A)</a>
                            <a class="dropdown-item sort-mytask-option-mobile" href="#" data-sort="date-asc">Due Date (Earliest)</a>
                            <a class="dropdown-item sort-mytask-option-mobile" href="#" data-sort="date-desc">Due Date (Latest)</a>
                        </div>
                    </div>
                    
                    <!-- Add Task Button - Mobile -->
                    <div class="mb-2">
                        <button class="btn text-white" style="background-color:#B75301;" data-toggle="modal" data-target="#addTaskModal">
                            <i class="fa fa-plus"></i> Add Task
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search Section (Shared between mobile and desktop) -->
    <div class="row align-items-center mt-3">
        <!-- Desktop: Filter/Sort on left -->
        <div class="col-12 col-md-6 mb-2 mb-md-0 d-none d-md-block">
            <div class="d-flex flex-wrap">
                <div class="dropdown mr-2 mb-2 mb-md-0">
                    <button class="btn dropdown-toggle text-white" 
                            type="button" 
                            id="statusMyTaskDropdown"
                            data-toggle="dropdown" 
                            aria-expanded="false" 
                            style="background-color:#B75301;">
                        <i class="fa fa-filter mr-1"></i> <span id="statusMyTaskLabel">All Status</span>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="statusMyTaskDropdown">
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="-1">All Status</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="0">Pending</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="1">Started</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="2">On-Progress</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="3">On-Hold</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="4">Over Due</a>
                        <a class="dropdown-item status-mytask-filter" href="#" data-status="5">Done</a>
                    </div>
                </div>
                
                <div class="dropdown">
                    <button class="btn dropdown-toggle text-white" 
                            type="button" 
                            id="sortMyTaskDropdown"
                            data-toggle="dropdown" 
                            aria-expanded="false" 
                            style="background-color:#B75301;">
                        <i class="fa fa-sort mr-1"></i> <span id="sortMyTaskLabel">Sort: Task Name (A-Z)</span>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="sortMyTaskDropdown">
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="task-asc">Task Name (A-Z)</a>
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="task-desc">Task Name (Z-A)</a>
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="date-asc">Due Date (Earliest)</a>
                        <a class="dropdown-item sort-mytask-option" href="#" data-sort="date-desc">Due Date (Latest)</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search -->
        <div class="col-12 col-md-6">
            <div class="input-group">
                <input type="text" 
                       class="form-control" 
                       id="searchMyTask" 
                       placeholder="Search by task name or assignee...">
                <div class="input-group-append">
                    <span class="input-group-text" style="background-color:#B75301; color:white; border:none;">
                        <i class="fa fa-search"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Query project yang melibatkan user yang login
$projects = $conn->query("SELECT * FROM project_list p $where ORDER BY name ASC");

// AUTO-UPDATE OVERDUE STATUS
$conn->query("
    UPDATE task_list 
    SET status = 4 
    WHERE end_date < CURDATE() 
    AND status NOT IN (3,5)
");

// Set task filter condition: HANYA tugas yang di-assign dan dibuat oleh user yang login
$user_task_filter = " AND (
    CONCAT('[', REPLACE(t.user_ids, ',', '],['), ']') LIKE '%[{$current_user_id}]%'
    OR t.created_by = '{$current_user_id}'
) ";
?>

<?php if($projects->num_rows > 0): ?>
    <?php while ($proj = $projects->fetch_assoc()): 
        $tasks = $conn->query("SELECT * FROM task_list t WHERE t.project_id = {$proj['id']} $user_task_filter ORDER BY t.date_created DESC");
        if($tasks->num_rows == 0) continue;
        $proj_id = $proj['id'];
    ?>
    
    <div class="project-section mb-3">
        <div class="project-folder-bar d-flex align-items-center justify-content-between" 
             data-toggle="collapse" 
             data-target="#project-collapse-<?php echo $proj['id'] ?>" 
             aria-expanded="false"
             style="cursor:pointer;">
            <div class="d-flex align-items-center">
                <i class="fa fa-folder-open folder-icon-style mr-3"></i>
                <span style="font-weight: 700; color: #333;">Project: <?php echo strtoupper($proj['name']) ?></span>
                    <small class="text-muted ml-2">(<?php echo $tasks->num_rows; ?> Tugas Untuk Anda)</small>
                </span>
            </div>
            <i class="fa fa-chevron-right arrow-icon"></i>
        </div>

        <div class="collapse mt-1" id="project-collapse-<?php echo $proj_id ?>">
            <div class="task-table-wrapper shadow-sm" style="border-radius: 15px; border: 1px solid #eee; overflow: hidden;">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">NO</th>
                            <th width="40%">TASK</th>
                            <th width="15%" class="text-center">DUE DATE</th>
                            <th width="10%" class="text-center">STATUS</th>
                            <th width="15%" class="text-center">CREATED BY</th>
                            <th width="15%" class="text-center">ASSIGNED</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1; 
                        while ($row = $tasks->fetch_assoc()): 
                            $encoded_task_id = $encoder($row['id']); 
                            $desc = strip_tags(html_entity_decode($row['description']));
                        ?>
                        <tr class="task-row task-item-row" 
                            data-id="<?= $encoded_task_id ?>" 
                            data-status="<?= $row['status'] ?>" 
                            style="cursor:pointer;">
                            
                            <td class="text-center text-muted"><?php echo $i++ ?></td>
                            <td>
                                <div class="task-name"><?php echo ucwords($row['task']) ?></div>
                                <?php if(!empty($desc)): ?>
                                    <div class="task-desc-small text-truncate"><?php echo $desc ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-muted"><?php echo date("M d, Y", strtotime($row['end_date'])) ?></td>
                            <td class="text-center">
                                <?php
                                    $status_labels = [0 => "Pending", 1 => "Started", 2 => "On-Progress", 3 => "On-Hold", 4 => "Over Due", 5 => "Done"];
                                    $status_classes = [0 => 'badge-status-pending', 1 => 'badge-status-started', 2 => 'badge-status-progress', 3 => 'badge-status-hold', 4 => 'badge-status-overdue', 5 => 'badge-status-done'];
                                    $current_stat = $row['status'];
                                    $label = isset($status_labels[$current_stat]) ? strtoupper($status_labels[$current_stat]) : "N/A";
                                    $class = isset($status_classes[$current_stat]) ? $status_classes[$current_stat] : 'badge-status-pending';
                                    echo "<span class='status-badge-main {$class}'>{$label}</span>";
                                ?>
                            </td>
                            <td class="text-center">
                                <?php 
                                    $creator_q = $conn->query("SELECT avatar FROM users WHERE id = '{$row['created_by']}' LIMIT 1");
                                    $creator = $creator_q->fetch_assoc();
                                    // Cek apakah file benar-benar ada di folder
                                    $c_img = (!empty($creator['avatar']) && file_exists('assets/uploads/'.$creator['avatar'])) 
                                            ? $creator['avatar'] 
                                            : 'empty-placeholder.png';
                                ?>
                                <div class="avatar-stack-container">
                                    <img src="assets/uploads/<?php echo $c_img ?>" class="avatar-table rolling-avatar" style="--d: 0;" onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
                                </div>
                            </td>
                            <td class="text-center text-center-assigned">
                                <div class="avatar-stack-container">
                                    <?php 
                                    $u_ids = !empty($row['user_ids']) ? $row['user_ids'] : '';
                                    if($u_ids):
                                        $u_q = $conn->query("SELECT avatar FROM users WHERE id IN ($u_ids) LIMIT 5");
                                        $idx = 0;
                                        while($u = $u_q->fetch_assoc()):
                                            $img_src = !empty($u['avatar']) ? 'assets/uploads/'.$u['avatar'] : 'assets/uploads/empty-placeholder.png';
                                    ?>
                                        <img src="<?php echo $img_src ?>" 
                                            class="avatar-table rolling-avatar" 
                                            style="--d: <?php echo $idx ?>;"
                                            onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
                                    <?php $idx++; endwhile; endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endwhile; ?>
<?php else: ?>
    <div class="col-lg-12">
        <div class="card card-outline">
            <div class="card-body">
                <p class="text-center">Anda tidak memiliki tugas yang di-assign pada proyek mana pun.</p>
            </div>
        </div>
    </div>
<?php endif; ?>


<div class="modal fade" id="taskModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body" id="task_detail_content">
        </div>
    </div>
  </div>
</div>

<style>
/*Paska*/
/* CSS untuk transisi halus */
.collapse {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); /* Efek easing yang halus */
    opacity: 0;
    transform: translateY(-10px); /* Sedikit bergeser dari atas */
    display: block !important;    /* Kita paksa block tapi sembunyikan via height */
    height: 0;
    overflow: hidden;
}

.collapse.show {
    opacity: 1;
    transform: translateY(0);
    height: auto; /* Membuka sesuai isi konten */
    padding-bottom: 20px;
}

.input-group {
    border-radius: 20px !important; 
    overflow: hidden;
    border: 1px solid #ced4da !important;
    background-color: #fff;
    box-shadow: none !important;
}

#searchMyTask {
    border: none !important;
    border-top-left-radius: 20px !important;
    border-bottom-left-radius: 20px !important;
    padding-left: 20px !important;
    height: 38px !important; /* Menyamakan tinggi standar */
}

.input-group-append .input-group-text {
    border-top-right-radius: 20px !important;
    border-bottom-right-radius: 20px !important;
    border: none !important;
    background-color: #B75301 !important; /* Cokelat Hai Motion */
    color: white !important;
    padding: 0 15px !important;
    cursor: pointer;
}

button.btn.text-white[style*="background-color:#B75301"], 
button.btn.text-white[style*="background-color: #B75301"],
#statusMyTaskDropdown,
#sortMyTaskDropdown {
    background-color: #B75301 !important;
    border-radius: 10px !important; 
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

/* Efek Hover Tombol */
button.btn.text-white[style*="background-color:#B75301"]:hover,
#statusMyTaskDropdown:hover,
#sortMyTaskDropdown:hover {
    background-color: #964401 !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(183, 83, 1, 0.35) !important;
    color: white !important;
}

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

.dropdown-item:hover {
    background-color: #FFF5EE !important;
    color: #B75301 !important;
    padding-left: 20px !important;
}

/* 4. Fix Jarak antar tombol agar rapi */
.d-flex.flex-wrap {
    gap: 10px;
}

.mr-2 {
    margin-right: 0 !important; /* Gunakan gap saja agar lebih konsisten */
}

/* Menghilangkan efek biru saat fokus (Focus Ring) */
.form-control:focus {
    box-shadow: none !important;
    border-color: #ced4da !important;
}

/* Container agar tetap di tengah */
.avatar-group {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    min-height: 35px;
}

/* Keadaan Awal (Tersembunyi & Menumpuk ke Kiri) */
.rolling-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #fff;
    object-fit: cover;
    opacity: 0;
    transform: translateX(-20px); /* Geser ke kiri awal */
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Keadaan Saat Aktif (Muncul & Bergulir ke posisi semula) */
.animate-stack.show-now .rolling-avatar {
    opacity: 1;
    transform: translateX(0); /* Kembali ke posisi normal (kanan) */
}

.container-fluid {
    padding-left: 15px !important;
    padding-right: 15px !important;
}

.project-section {
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    width: 100% !important;
}   

.project-folder-bar {
    background-color: #ffffff;
    padding: 16px 20px;
    border-radius: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    border: 1px solid #f0f0f0;
    transition: all 0.3s;
    margin-left: 0 !important;
    width: 100% !important;
}

.project-folder-bar:hover {
    background-color: #fcfcfc;
}

.folder-icon-style {
    color: #b75301; /* Warna cokelat/emas folder */
    font-size: 1.1rem;
}

.arrow-icon {
    color: #B75301;
    font-size: 0.8rem;
    transition: transform 0.3s;
}

/* Animasi rotasi panah saat diklik */
.project-folder-bar[aria-expanded="true"] .arrow-icon {
    transform: rotate(90deg);
}

.task-table-wrapper {
    margin-left: 0 !important;
    border-radius: 15px;
    padding: 0 !important; /* Hapus padding agar header abu-abu menempel ke tepi */
    border: 1px solid #eee;
    width: 100% !important;
    overflow: hidden; /* Penting agar sudut header ikut membulat */
}

.task-table-wrapper table.table-clean thead {
    background-color: #E3E3E3 !important; /* Abu-abu yang diinginkan */
}

.task-table-wrapper table.table-clean thead th {
    background-color: #E3E3E3 !important; /* Memastikan tiap sel header berwarna abu-abu */
    color: #333 !important;
    border: none !important;
    padding: 15px !important;
}

.table-clean thead th {
    border: none !important;
    background-color: #fdfdfd;
    color: #333 !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px 10px !important;
}

.task-item-row td {
    border-top: 1px solid #f8f8f8 !important;
    padding: 15px 10px !important;
    vertical-align: middle !important;
}

.task-name {
    font-weight: 500;
    color: #333;
    font-size: 16px;
}

.task-desc-small {
    font-size: 12px;
    color: #aaa;
    max-width: 300px;
}

/* Status Badges */
.status-badge-green {
    background-color: #5cb85c;
    color: white;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 9px;
    font-weight: 700;
}

.status-badge-gray {
    background-color: #888;
    color: white;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 9px;
    font-weight: 700;
}

/* Avatars */
.avatar-table {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid #fff;
    object-fit: cover;
    background: #eee;
}

.ml-n2 { margin-left: -10px !important; }

.status-badge-main {
    display: inline-block;
    width: 95px;             /* Lebar tetap agar rata secara vertikal */
    padding: 6px 0;          /* Padding atas-bawah rata */
    border-radius: 6px;      /* Lengkungan sudut halus sesuai gambar */
    font-size: 9px;          /* Ukuran font kecil sesuai mockup */
    font-weight: 800;        /* Font sangat tebal */
    text-align: center;      /* Teks tepat di tengah */
    color: white;            /* Teks putih secara default */
    letter-spacing: 0.3px;
    line-height: 1;
}

/* Warna Status Berdasarkan Gambar Referensi Anda */
.badge-status-pending { background-color: #6c757d !important; } /* Abu-abu */
.badge-status-started { background-color: #17a2b8 !important; } /* Cyan/Biru Muda */
.badge-status-progress { background-color: #007bff !important; } /* Biru */
.badge-status-hold { background-color: #ffc107 !important; color: #333 !important; } /* Kuning, Teks Hitam */
.badge-status-overdue { background-color: #dc3545 !important; } /* Merah */
.badge-status-done { background-color: #28a745 !important; }    /* Hijau */

/* Badge Status Sesuai Gambar */
.badge-status-done {
    background-color: #28a745;
    color: white;
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
}

.badge-status-pending {
    background-color: #6c757d;
    color: white;
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
}

/* Avatar Styling */
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    object-fit: cover;
}

.ml-n2 {
    margin-left: -12px !important;
}

.task-title {
        font-weight: 500 !important; /* Menggunakan Medium untuk kesan bersih */
        color: #333;
        margin-bottom: 2px;
        font-size: 17px;
    }

    /* Mengatur deskripsi tugas (sub-text) */
    .task-desc-truncate {
        font-weight: 400;
        font-size: 12px;
        color: #888;
        line-height: 1.4;
    }
/* Container Project Pill */
    .project-header-pill {
        display: inline-block;
        background: white;
        border-radius: 50px;
        padding: 15px 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        border: 1px solid #eee;
    }

    /* Card Table Baru */
    .custom-card-task {
        border-radius: 25px !important;
        border: none !important;
        box-shadow: 0 8px 30px rgba(0,0,0,0.05) !important;
        overflow: hidden;
        background: white;
        letter-spacing: 0.5px;  
    }

    /* Header Tabel Abu-abu sesuai Mockup */
    .table-custom-head {
        background-color: #E3E3E3 !important;
    }

    .table-custom-head th {
        border: none !important;
        padding: 15px !important;
        color: #333 !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        font-size: 12.5px;
        letter-spacing: 0.5px;
    }

    /* Baris Tabel */
    .task-row td {
        padding: 15px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f0f0f0 !important;
    }

    /* Badge Status Hijau Bulat */
    .badge-done-custom {
        background-color: #28a745 !important;
        color: white !important;
        padding: 6px 20px !important;
        border-radius: 10px !important;
        font-weight: 800;
        font-size: 9.5px;
        min-width: 65px;
        display: inline-block;
    }

    .task-row td {
        padding: 1rem 0.75rem !important;
        vertical-align: middle !important;
        border-top: 1px solid #f2f2f2 !important;
        background: white;
    }

    /* Efek hover lembut */
    .task-row:hover td {
        background-color: #fcfcfc !important;
    }


    /* Styling Avatar agar persis seperti di desain */
    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid #fff;
        box
    }
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
table p {
    margin: unset !important;
}
/* Tambahkan padding agar baris lebih lega */
table td {
    vertical-align: middle !important;
    padding-top: 15px !important;
    padding-bottom: 15px !important;
}

/* Header Tabel Abu-abu sesuai Mockup */
.table-custom-head {
    background-color: #E3E3E3 !important; /* Warna abu-abu yang kamu inginkan */
}

.table-custom-head th {
    border: none !important;
    padding: 15px !important;
    color: #333 !important; /* Warna teks gelap agar terbaca jelas */
    font-weight: 800 !important;
    text-transform: uppercase;
    font-size: 12.5px;
    letter-spacing: 0.5px;
    vertical-align: middle !important;
}

/* Update: Header Bold & Kapital */
table thead th {
    font-weight: 800 !important;
    color: #333 !important;
    text-transform: uppercase;
    font-size: 13px;
    border: none !important;
    padding-bottom: 15px !important;
}
.badge-status {
    min-width: 90px;
    display: inline-block;
    font-size: 10px !important;
    padding: 3px 6px !important;
}

/* Tambahkan: Ringkasan Deskripsi 1 Baris */
.task-desc-truncate {
    font-size: 0.8rem;
    font-style: italic;
    line-height: 1.2;
    margin-top: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 1; 
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #6c757d;
}

/* Tambahkan: Logika Animasi Avatar Rolling */
.avatar-stack-container {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    gap: 0 !important;
}

.rolling-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #fff;
    object-fit: cover;
    opacity: 0;
    transform: translateX(-20px) scale(0.5); /* Start dari kiri dan mengecil */
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Efek spring/membal */
    transition-delay: calc(var(--d) * 0.1s); /* Jeda antar foto */
    will-change: transform, opacity;
    margin-left: -10px; 
}

.project-section.active-roll .rolling-avatar {
    opacity: 1 !important;
    transform: translateX(0) scale(1) !important;
}
/* Hilangkan margin negatif pada foto pertama agar tetap center */
.rolling-avatar:first-child {
    margin-left: 0;
}

/* Ensure ALL dropdown menus are hidden by default */
.dropdown-menu {
    display: none !important;
}

/* Show dropdown only when parent has .show class */
.dropdown.show > .dropdown-menu {
    display: block !important;
}

/* Mobile Layout */
@media (max-width: 767.98px) {

    .task-table-wrapper table {
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
    .mobile-task-row:hover { background: #f5f5f5; }
    .mobile-task-row:active { background: #e8e8e8; }

    .mobile-row-no {
        font-size: 10px;
        color: #ccc;
        font-weight: 600;
        min-width: 14px;
        flex-shrink: 0;
        padding-top: 2px;
    }

    .mobile-row-content {
        flex: 1;
        min-width: 0;
    }

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
        min-width: 60px;
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
        -webkit-line-clamp: 1;
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

    .mobile-av-stack {
        display: flex;
        align-items: center;
    }

    .mobile-av-stack img {
        width: 26px !important;
        height: 26px !important;
        border-radius: 50% !important;
        border: 2px solid #fff !important;
        object-fit: cover !important;
        margin-left: -5px !important;
        opacity: 1 !important;
        transform: none !important;
    }
    .mobile-av-stack img:first-child { margin-left: 0 !important; }
}

@media (min-width: 768px) {
    .mobile-task-header,
    .mobile-task-list { display: none !important; }
}
</style>

<script>
    var currentStatusFilter = -1; // -1 = All Status
    var currentSort = 'name-asc';
    
    // Check if mobile
    function isMobile() {
        return $(window).width() < 768;
    }
$(document).ready(function(){
    // Fungsi untuk mendapatkan parameter URL (Mengambil HASH ID)
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    };

    $('.summernote').summernote({ height: 200 });

   
    $('#user_ids').select2({
        placeholder: "Select users",
        dropdownParent: $('#addTaskModal')
    });

    const encodedTaskId = getUrlParameter('id');
    const pageName = getUrlParameter('page'); 

    // 💡 3. Cek apakah ID Tugas (HASH) ada di URL
    if (encodedTaskId) {
        // Otomatis buka modal detail tugas
        uni_modal("Task Details", "get_task_detail.php?id=" + encodedTaskId, "mid-large");
        
        // Opsional: Hapus parameter ID dari URL
        if (history.replaceState) {
            let cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + (pageName ? '?page=' + pageName : '');
            history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    }
    
    // 💡 4. Event handler untuk baris tugas di mytask.php
    $('.task-row').click(function(e){
        if($(e.target).closest('.dropdown, .dropdown-toggle, .dropdown-menu, .new_productivity, .edit_task, .delete_task').length) return;

        // Task ID sudah terenkripsi dari data-id
        var encodedTaskId = $(this).data('id'); 
        uni_modal("Task Details","get_task_detail.php?id="+ encodedTaskId ,"mid-large");
    });
});

$(document).ready(function(){
    // Menghapus class animasi lama jika ada agar tidak bentrok
    $('.project-section').removeClass('active-roll');

    // Trigger saat folder diklik dan mulai terbuka
    $('.collapse').on('show.bs.collapse', function () {
        var section = $(this).closest('.project-section');
        
        // Beri sedikit delay agar tabel muncul dulu baru foto bergulir
        setTimeout(function(){
            section.addClass('active-roll');
        }, 150);
    });

    // Reset saat folder ditutup agar bisa diulang
    $('.collapse').on('hide.bs.collapse', function () {
        $(this).closest('.project-section').removeClass('active-roll');
    });
});

// Edit Task (buka modal)
$('.edit_task').click(function(){
    // ID Task dan Project sudah terenkripsi
    var encodedTaskId = $(this).data('id');
    var encodedProjectId = $(this).data('pid');
    
    uni_modal("<i class='fa fa-edit'></i> Edit Task",
        "manage_task.php?id=" + encodedTaskId + "&pid=" + encodedProjectId,
        "mid-large");
});

// Add Productivity (modal-xl)
$('.new_productivity').click(function(){
    // ID Task dan Project sudah terenkripsi
    var encodedPid = $(this).attr('data-pid');
    var encodedTid = $(this).attr('data-tid');
    uni_modal("<i class='fa fa-plus'></i> New Comment for: " + $(this).attr('data-task'),
        "manage_progress.php?pid=" + encodedPid + "&tid=" + encodedTid,
        "mid-large");
});

// Delete Task (menggunakan ID numerik untuk AJAX)
$('.delete_task').click(function(){
    var numericId = $(this).attr('data-id'); 
    _conf("Are you sure to delete this task?", "delete_task", [numericId]);
});


// Function delete (assuming it is globally defined or defined here)
function delete_task(id){
    // ID yang diterima adalah ID numerik
    $.ajax({
        url: 'ajax.php?action=delete_task',
        method: 'POST',
        data: { id: id },
        success: function(resp){
            if(resp == 1){
                alert("Task berhasil dihapus");
                setTimeout(() => location.reload(), 1500);
            } else {
                alert("Gagal menghapus task");
            }
        }
    });
}

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

var currentMyTaskStatusFilter = -1; // -1 = All Status

$('.status-mytask-filter').on('click', function(e) {
    e.preventDefault();
    currentMyTaskStatusFilter = parseInt($(this).data('status'));
    
    // Update label
    $('#statusMyTaskLabel').text($(this).text());
    
    // Apply filter
    applyMyTaskFilters();
});

$('#searchMyTask').on('keyup', function() {
    applyMyTaskFilters();
});

function applyMyTaskFilters() {
    var searchValue = $('#searchMyTask').val().toLowerCase();
    
    // Perbaikan: Lakukan looping pada '.project-section' agar Header Project ikut tersembunyi jika kosong
    $('.project-section').each(function() {
        var $section = $(this);
        var visibleTasksInProject = 0;

        // Perbaikan selektor: Cari '.task-row' di dalam section ini
        $section.find('.task-row').each(function() {
            var $row = $(this);
            var taskStatus = parseInt($row.data('status'));
            
            // Perbaikan selektor teks: Cari nama task pada class '.task-name'
            var taskName = $row.find('.task-name').text().toLowerCase();
            
            // Logika filter status
            var matchStatus = (currentMyTaskStatusFilter === -1 || taskStatus === currentMyTaskStatusFilter);
            
            // Logika filter pencarian
            var matchSearch = (searchValue === '' || taskName.indexOf(searchValue) !== -1);

            if (matchStatus && matchSearch) {
                $row.show();
                visibleTasksInProject++;
            } else {
                $row.hide();
            }
        });

        // JIKA tidak ada tugas yang cocok dalam project ini, sembunyikan SELURUH project section
        if (visibleTasksInProject > 0) {
            $section.show();
            // Buka otomatis folder jika user sedang memfilter/mencari sesuatu
            if(searchValue !== '' || currentMyTaskStatusFilter !== -1) {
                $section.find('.collapse').addClass('show');
            }
        } else {
            $section.hide();
        }
    });
    
    // Opsional: Jalankan pengecekan hasil kosong global jika diperlukan
    if (typeof checkMyTaskNoResults === "function") {
        checkMyTaskNoResults();
    }
}
// ============================================
// FITUR SORT MY TASK
// ============================================
var currentMyTaskSort = 'task-asc';

$('.sort-mytask-option').on('click', function(e) {
    e.preventDefault();
    currentMyTaskSort = $(this).data('sort');
    
    // Update label
    $('#sortMyTaskLabel').text('Sort: ' + $(this).text());
    
    // Sort tasks
    sortMyTasks(currentMyTaskSort);
});

function sortMyTasks(sortType) {
    // Ganti selektor ke wrapper tabel yang baru
    $('.task-table-wrapper').each(function() {
        var $tbody = $(this).find('tbody');
        var $rows = $tbody.find('.task-row').toArray();
        
        $rows.sort(function(a, b) {
            var aVal, bVal;
            
            switch(sortType) {
                case 'task-asc':
                    aVal = $(a).find('.task-name').text().toLowerCase();
                    bVal = $(b).find('.task-name').text().toLowerCase();
                    return aVal.localeCompare(bVal);
                    
                case 'task-desc':
                    aVal = $(a).find('.task-name').text().toLowerCase();
                    bVal = $(b).find('.task-name').text().toLowerCase();
                    return bVal.localeCompare(aVal);
                    
                case 'date-asc':
                    aVal = $(a).find('td:eq(2)').text(); // Kolom Due Date
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
}

function checkMyTaskNoResults() {
    $('.card.card-outline').each(function() {
        var $tbody = $(this).find('tbody');
        var visibleRows = $tbody.find('.task-row:visible').length;
        
        $tbody.find('.no-results-row').remove();
        
        if (visibleRows === 0) {
            // Gunakan colspan="6" agar pesan benar-benar memenuhi lebar tabel My Task
            $tbody.append(`
                <tr class="no-results-row">
                    <td colspan="6" class="text-center py-5">
                        <div class="no-data-container">
                            <i class="fa fa-folder-open mb-3" style="font-size: 3rem; color: #ddd;"></i>
                            <p class="text-muted" style="font-size: 1.1rem; font-weight: 500;">No tasks found for this filter</p>
                        </div>
                    </td>
                </tr>
            `);
        }
    });
}

// ============================================
    // INITIALIZATION - MOBILE VS DESKTOP
    // ============================================
    
    // Mobile-specific filter/sort handlers
$('.status-mytask-filter-mobile').on('click', function(e) {
    e.preventDefault();
    currentMyTaskStatusFilter = parseInt($(this).data('status'));
    $('#statusMyTaskLabelMobile').text($(this).text());
    applyMyTaskFilters();
});

$('.sort-mytask-option-mobile').on('click', function(e) {
    e.preventDefault();
    currentMyTaskSort = $(this).data('sort');
    $('#sortMyTaskLabelMobile').text('Sort: ' + $(this).text());
    sortMyTasks(currentMyTaskSort);
});

// Also update the existing desktop handlers to sync with mobile
$('.status-mytask-filter').on('click', function(e) {
    e.preventDefault();
    currentMyTaskStatusFilter = parseInt($(this).data('status'));
    $('#statusMyTaskLabel').text($(this).text());
    $('#statusMyTaskLabelMobile').text($(this).text()); // Sync mobile label
    applyMyTaskFilters();
});

$('.sort-mytask-option').on('click', function(e) {
    e.preventDefault();
    currentMyTaskSort = $(this).data('sort');
    var sortText = $(this).text();
    $('#sortMyTaskLabel').text('Sort: ' + sortText);
    $('#sortMyTaskLabelMobile').text('Sort: ' + sortText); // Sync mobile label
    sortMyTasks(currentMyTaskSort);
});
    
    function initInterface() {
        if (isMobile()) {
            // MOBILE: Use bottom sheets
            
            // Hide dropdown menus - USE THE CORRECT IDs FOR MYTASK PAGE
            $('#statusMyTaskDropdown + .dropdown-menu, #sortMyTaskDropdown + .dropdown-menu').hide();
            
                        // Status Filter Button - USE CORRECT ID FOR MOBILE
            $('#statusMyTaskDropdownMobile').off('click.mobile').on('click.mobile', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var content = `
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>All Status</span>
                            ${currentMyTaskStatusFilter === -1 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="0">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Pending</span>
                            ${currentMyTaskStatusFilter === 0 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Started</span>
                            ${currentMyTaskStatusFilter === 1 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>On-Progress</span>
                            ${currentMyTaskStatusFilter === 2 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>On-Hold</span>
                            ${currentMyTaskStatusFilter === 3 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Over Due</span>
                            ${currentMyTaskStatusFilter === 4 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="status" data-value="5">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Done</span>
                            ${currentMyTaskStatusFilter === 5 ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                `;
                
                showBottomSheet('Filter by Status', content);
                return false;
            });
            
            // Sort Button - USE CORRECT ID FOR MOBILE
            $('#sortMyTaskDropdownMobile').off('click.mobile').on('click.mobile', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var content = `
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="task-asc">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Task Name (A-Z)</span>
                            ${currentMyTaskSort === 'task-asc' ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="task-desc">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Task Name (Z-A)</span>
                            ${currentMyTaskSort === 'task-desc' ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="date-asc">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Due Date (Earliest)</span>
                            ${currentMyTaskSort === 'date-asc' ? '<i class="fa fa-check text-success"></i>' : ''}
                        </div>
                    </a>
                    <a href="#" class="dropdown-item sheet-filter-item" data-type="sort" data-value="date-desc">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Due Date (Latest)</span>
                            ${currentMyTaskSort === 'date-desc' ? '<i class="fa fa-check text-success"></i>' : ''}
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
                    currentMyTaskStatusFilter = parseInt(value);
                    var statusText = $(this).find('span').text();
                    $('#statusMyTaskLabel').text(statusText);
                    applyMyTaskFilters();
                } else if (type === 'sort') {
                    currentMyTaskSort = value;
                    var sortText = $(this).find('span').text();
                    $('#sortMyTaskLabel').text('Sort: ' + sortText);
                    sortMyTasks(currentMyTaskSort);
                }
                
                closeBottomSheet();
            });
            
            // Disable Bootstrap dropdowns on mobile
            $('.dropdown-toggle').dropdown('dispose');
            
         } else {
            // DESKTOP: Use normal dropdowns
            
            // Show dropdown menus - USE CORRECT IDs
            $('#statusMyTaskDropdown + .dropdown-menu, #sortMyTaskDropdown + .dropdown-menu').show();
            
            // Remove mobile event handlers - USE CORRECT IDs
            $('#statusMyTaskDropdown').off('click.mobile');
            $('#sortMyTaskDropdown').off('click.mobile');
            $(document).off('click.sheet-item');
            
            // Enable Bootstrap dropdowns
            $('.dropdown-toggle').dropdown();
            
            // Desktop filter/sort handlers
            $('.status-mytask-filter').off('click.desktop').on('click.desktop', function(e) {
                e.preventDefault();
                currentMyTaskStatusFilter = parseInt($(this).data('status'));
                $('#statusMyTaskLabel').text($(this).text());
                applyMyTaskFilters();
            });
            
            $('.sort-mytask-option').off('click.desktop').on('click.desktop', function(e) {
                e.preventDefault();
                currentMyTaskSort = $(this).data('sort');
                $('#sortMyTaskLabel').text('Sort: ' + $(this).text());
                sortMyTasks(currentMyTaskSort);
            });
            
            // Close any open sheet
            closeBottomSheet();
        }
    }

    // Initialize interface based on screen size
    initInterface();
    
    // Reinitialize on window resize
    $(window).on('resize', function() {
        initInterface();
    });
    
    $('#searchMyTask').on('keyup', applyMyTaskFilters);
    
    // Initialize dropdowns for desktop
    $('.dropdown-toggle').dropdown({ display: 'static' });

$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const shouldExpand = urlParams.get('expand');

    if (shouldExpand === 'true') {
        let checkExist = setInterval(function() {
            let panels = $('.project-section .collapse');
            
            if (panels.length > 0) {
                // Gunakan delay antar project agar terlihat mengalir satu per satu
                panels.each(function(index) {
                    setTimeout(() => {
                        $(this).addClass('show');
                        $(this).closest('.project-section').find('.project-folder-bar').attr('aria-expanded', 'true');
                        $(this).closest('.project-section').addClass('active-roll');
                    }, index * 150); // Jeda 150ms tiap folder
                });

                console.log("Smooth Expand Aktif.");
                clearInterval(checkExist);
            }
        }, 200);
    }
});

// ============================================
// MOBILE TASK LAYOUT BUILDER
// ============================================
function buildMobileTaskLayout() {
    if ($(window).width() >= 768) return;

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

    $('.task-table-wrapper').each(function () {
        var $wrapper = $(this);
        if ($wrapper.find('.mobile-task-header').length) return;

        var $header = $('<div class="mobile-task-header">All Task</div>');
        var $list   = $('<div class="mobile-task-list"></div>');
        var counter = 1;

        $wrapper.find('tbody .task-item-row').each(function () {
            var $tr        = $(this);
            var encodedId  = $tr.data('id');
            var status     = parseInt($tr.data('status'));
            var taskName   = $tr.find('td:nth-child(2) .task-name').text().trim();
            var taskDesc   = $tr.find('td:nth-child(2) .task-desc-small').text().trim();
            var dueDate    = $tr.find('td:nth-child(3)').text().trim();
            var badgeCls   = badgeClassMap[status] || 'mobile-badge-pending';
            var badgeLbl   = badgeLabelMap[status] || 'PENDING';

            var $createdImgs  = $tr.find('td:nth-child(5) img').clone()
                                   .css({opacity:1, transform:'none'});
            var $assignedImgs = $tr.find('td:nth-child(6) img').clone()
                                   .css({opacity:1, transform:'none'});

            var descHtml = taskDesc
                ? '<div class="mobile-row-desc">' + taskDesc + '</div>' : '';

            var $row = $(
                '<div class="mobile-task-row" data-id="' + encodedId + '" data-status="' + status + '">' +
                    '<span class="mobile-row-no">' + counter + '</span>' +
                    '<div class="mobile-row-content">' +
                        '<div class="mobile-row-top">' +
                            '<span class="mobile-row-name">' + taskName + '</span>' +
                            '<div class="mobile-row-right">' +
                                '<span class="mobile-row-date">' + dueDate + '</span>' +
                                '<span class="mobile-status-badge ' + badgeCls + '">' + badgeLbl + '</span>' +
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

// Jalankan saat halaman load
$(document).ready(function () {
    buildMobileTaskLayout();

    // Jalankan ulang saat collapse project dibuka
    $(document).on('shown.bs.collapse', '.collapse', function () {
        buildMobileTaskLayout();
    });
});
</script>