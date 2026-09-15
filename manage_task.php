<?php 
session_start();
include 'db_connect.php';

// ---------------------------------------------
// ➡️ 1. DECODE INCOMING IDs DARI URL
// ---------------------------------------------
$id_decoded = null;
$pid_decoded = null; 
$id_encoded = $_GET['id'] ?? null;
$pid_encoded = $_GET['pid'] ?? null;

// Decode Task ID (id)
if (!empty($id_encoded)) {
    $decoded = decode_id($id_encoded);
    if (is_numeric($decoded) && $decoded > 0) {
        $id_decoded = $decoded;
    }
}

// Decode Project ID (pid)
if (!empty($pid_encoded)) {
    $decoded = decode_id($pid_encoded);
    if (is_numeric($decoded) && $decoded > 0) {
        $pid_decoded = $decoded;
    }
}
// ---------------------------------------------


// 2. Fetch existing task data if $id_decoded is set (Edit Mode)
if(isset($id_decoded)){
    // Gunakan ID numerik yang sudah didekode
    $qry = $conn->query("SELECT * FROM task_list where id = ".$id_decoded);
    
    if($qry->num_rows > 0){
        $data = $qry->fetch_array();
        foreach($data as $k => $v){
            $$k = $v;
        }
        // Pastikan $pid_decoded diisi dari project_id di DB jika ini mode edit
        $pid_decoded = isset($project_id) ? $project_id : $pid_decoded;
    } else {
        $id_decoded = null; // Task tidak ditemukan, kembali ke mode 'Add New'
    }
}


// 3. Set Project ID Numerik yang aman untuk seluruh form logic
// Ambil dari pid_decoded atau dari project_id hasil query jika ada, fallback ke 0
$pid = $pid_decoded ?? (isset($project_id) ? $project_id : 0);

if ($pid === 0) {
    echo "<div class='alert alert-danger p-3'>Project ID tidak valid atau hilang.</div>";
    exit;
}

$project_user_ids = [];
$proj = $conn->query("SELECT user_ids, manager_id FROM project_list WHERE id = $pid"); // Query menggunakan $pid (numeric)
if($proj->num_rows > 0){
    $proj_data = $proj->fetch_assoc();
    
    // Tambahkan semua user_ids (anggota)
    if(!empty($proj_data['user_ids'])){
        $project_user_ids = array_merge($project_user_ids, explode(',', $proj_data['user_ids']));
    }
    
    // Tambahkan manager_id
    if(!empty($proj_data['manager_id'])){
        $project_user_ids[] = $proj_data['manager_id'];
    }
}

// Jika ini adalah task yang sudah ada, pastikan semua user yang sudah di-assign juga masuk dalam daftar
$current_users = isset($user_ids) ? explode(',', $user_ids) : [];

// Gabungkan dan filter ID unik
$potential_user_ids_array = array_unique(array_filter(array_merge($project_user_ids, $current_users)));
$potential_user_ids_string = !empty($potential_user_ids_array) ? implode(',', $potential_user_ids_array) : '0';

// Ambil data user HANYA untuk ID yang terkait dengan proyek
$all_users_data = [];
// only include users that are not clients (type 4)
$all_users_q = $conn->query("SELECT id, firstname, lastname, type FROM users WHERE id IN ($potential_user_ids_string) AND type != 4 ORDER BY CONCAT(firstname, ' ', lastname) ASC");
while($row = $all_users_q->fetch_assoc()){
    $all_users_data[] = $row;
}
?>

<? include 'header.php'?>
<div class="container-fluid hm-modal-body" id="hm-manage-task-container">
    <form action="" id="manage-task">
        <input type="hidden" name="id" value="<?php echo isset($id_decoded) ? $id_decoded : '' ?>">
        <input type="hidden" name="project_id" value="<?php echo $pid ?>">
        <input type="hidden" name="created_by" value="<?php echo $_SESSION['login_id']; ?>"> 

        <div class="form-group">
            <label for="" class="hm-label">TASK</label>
            <input type="text" class="form-control form-control-sm hm-input" name="task" value="<?php echo isset($task) ? $task : '' ?>" required>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label for="start_date" class="hm-label">START DATE</label>
                <input type="date" id="start_date" name="start_date" class="form-control hm-input" required value="<?php echo isset($start_date) ? $start_date : '' ?>">
            </div>
            <div class="col-md-6 form-group">
                <label for="end_date" class="hm-label">END DATE</label>
                <input type="date" id="end_date" name="end_date" class="form-control hm-input" required value="<?php echo isset($end_date) ? $end_date : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="" class="hm-label">DESCRIPTION</label>
            <textarea name="description" id="" cols="30" rows="10" class="summernote form-control hm-summernote-wrapper" placeholder="(text only)">
                <?php echo isset($description) ? $description : '' ?>
            </textarea>
        </div>

        <div class="form-group">
            <label class="hm-label">TASK TYPE</label>
            <br>
            <?php
            $pillars = ['Edukasi', 'Tips', 'Behind The Scene', 'Testimoni', 'Portofolio', 'Awareness', 'Engagement', 'Promo', 'Lainnya'];
            $selected_pillars = isset($content_pillar) ? explode(',', $content_pillar) : [];
            foreach($pillars as $index => $pillar):
                $is_checked = in_array($pillar, $selected_pillars);
            ?>
            <div class="form-check form-check-inline hm-pill-check <?= $is_checked ? 'is-checked' : '' ?>">
                <input class="form-check-input hm-pill-logic content-pillar-checkbox" type="checkbox" name="content_pillar[]" id="pillar_<?= $index ?>" value="<?= $pillar ?>" <?= $is_checked ? 'checked' : '' ?>>
                <label class="form-check-label" for="pillar_<?= $index ?>"><?= $pillar ?></label>
            </div>
            <?php endforeach; ?>
            
            <div id="contentLainnyaContainer" class="hm-lainnya-box" style="<?= in_array('Lainnya', $selected_pillars) ? 'display:block;' : 'display:none;' ?>">
                <label for="contentLainnyaText" class="hm-label-normal">Please specify:</label>
                <input type="text" class="form-control hm-input" id="contentLainnyaText" name="lainnya_text" placeholder="Enter other task type...">
            </div>
        </div>

        <div class="form-group">
            <label class="hm-label">PLATFORM</label><br>
            <?php 
            $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter', 'LinkedIn', 'Website','IGs','Reels','Feeds', 'Lainnya'];
            $selected_platforms = isset($platform) ? explode(',', $platform) : [];
            foreach($platforms as $index => $plat): 
                $is_checked = in_array($plat, $selected_platforms);
            ?>
            <div class="form-check form-check-inline hm-pill-check <?= $is_checked ? 'is-checked' : '' ?>">
                <input class="form-check-input hm-pill-logic platform-checkbox" type="checkbox" name="platform[]" id="plat_<?= $index ?>" value="<?= $plat ?>" <?= $is_checked ? 'checked' : '' ?>>
                <label class="form-check-label" for="plat_<?= $index ?>"><?= $plat ?></label>
            </div>
            <?php endforeach; ?>
            
            <div id="platformLainnyaContainer" class="hm-lainnya-box" style="<?= in_array('Lainnya', $selected_platforms) ? 'display:block;' : 'display:none;' ?>">
                <label for="platformLainnyaText" class="hm-label-normal">Please specify platform:</label>
                <input type="text" class="form-control hm-input" id="platformLainnyaText" name="platform_lainnya_text" placeholder="Enter other platform...">
            </div>
        </div>

        <div class="form-group">
            <label class="hm-label">REFERENCE LINKS</label>
            <textarea name="reference_links" class="form-control hm-input" rows="3" placeholder="Pisahkan link dengan enter."><?= isset($reference_links) ? $reference_links : '' ?></textarea>
        </div>

        <div class="form-group">
            <label for="" class="hm-label">STATUS</label>
            <div class="hm-select-wrapper">
                <select name="status" id="status" class="form-control form-control-sm hm-input">
                    <option value="0" <?php echo isset($status) && $status == 0 ? 'selected' : '' ?>>Pending</option>
                    <option value="2" <?php echo isset($status) && $status == 2 ? 'selected' : '' ?>>On-Progress</option>
                    <option value="3" <?php echo isset($status) && $status == 3 ? 'selected' : '' ?>>Hold</option>
                    <option value="5" <?php echo isset($status) && $status == 5 ? 'selected' : '' ?>>Done</option>
                </select>
                <span class="hm-custom-arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14">
                        <path fill="none" stroke="#64748B" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 5l6 6 6-6"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label for="" class="control-label hm-label">ASSIGN TO</label>
            <select class="form-control form-control-sm select2" multiple="multiple" name="user_ids[]">
                <option></option>
                <?php 
                foreach($all_users_data as $user){
                    $role_label = $user['type'] == 1 ? ' (Admin)' : ($user['type'] == 2 ? ' (Manager)' : '');
                    $selected = in_array($user['id'], $current_users) ? 'selected' : '';
                    echo "<option value='{$user['id']}' $selected>".ucwords($user['firstname'].' '.$user['lastname']) . $role_label ."</option>";
                }
                ?>
            </select>
        </div>
    </form>
        
    <div class="d-flex justify-content-end pt-4 mt-2 border-top-custom" style="gap: 8px;">
        <button type="button" class="btn hm-btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" class="btn hm-btn-primary" form="manage-task">Save Task</button>
    </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

#hm-manage-task-container {
    --hm-orange: #B75301;
    --hm-input-bg: #F8FAFC;
    --hm-border: #E2E8F0;
    --hm-text: #1E293B;
    --hm-muted: #64748B;
    font-family: 'Plus Jakarta Sans', sans-serif;
    padding: 10px 5px;
}

#uni_modal .modal-content {
    border-radius: 24px !important; 
    border: none !important; 
}

#uni_modal .modal-header {
    border-radius: 24px 24px 0 0 !important;
}

#hm-manage-task-container .d-flex.border-top-custom {
    border-radius: 0 0 24px 24px !important;
}

.modal-header {
    background: linear-gradient(to right, #AD5617, #E99540) !important; 
    color: #FFFFFF !important;
    border-bottom: none;
    border-radius: 24px 24px 0 0 !important;
}

#hm-manage-task-container .hm-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--hm-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

#hm-manage-task-container .hm-input {
    min-height: 45px !important;
    background: var(--hm-input-bg) !important;
    border: 1.5px solid var(--hm-border) !important;
    border-radius: 12px !important;
    color: var(--hm-text) !important;
    font-size: 14px !important;
    box-shadow: none !important;
}

#hm-manage-task-container textarea.hm-input {
    min-height: 80px !important;
    padding-top: 12px !important;
}

#hm-manage-task-container .hm-input:focus {
    border-color: var(--hm-orange) !important;
    background: #FFFFFF !important;
    box-shadow: 0 0 0 3px rgba(183, 83, 1, 0.1) !important;
}

/* --- Select Custom Arrow --- */
#hm-manage-task-container .hm-select-wrapper { position: relative; }
#hm-manage-task-container .hm-select-wrapper select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    padding-right: 40px !important; 
}
#hm-manage-task-container .hm-custom-arrow {
    position: absolute; right: 15px; top: 50%;
    transform: translateY(-50%); pointer-events: none;
}

/* --- Pill Checkboxes --- */
#hm-manage-task-container .hm-pill-check { margin-bottom: 8px; margin-right: 5px; }
#hm-manage-task-container .hm-pill-check input { display: none; }
#hm-manage-task-container .hm-pill-check .form-check-label {
    background: #F1F5F9;
    color: var(--hm-muted);
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: 0.2s;
}
#hm-manage-task-container .hm-pill-check .form-check-label:hover { background: #E2E8F0; }
#hm-manage-task-container .hm-pill-check.is-checked .form-check-label {
    background: #FFF7ED;
    border-color: var(--hm-orange);
    color: var(--hm-orange);
}

/* --- Buttons --- */
.border-top-custom { border-top: 1px solid #E2E8F0; padding-top: 20px !important; }
.hm-btn-secondary {
    background: #F1F5F9; color: #475569;
    border: none; border-radius: 8px; padding: 7px 16px;
    font-weight: 600; font-size: 13px;
}

.hm-btn-primary {
    background: linear-gradient(135deg, #C26815 0%, #E2943B 100%) !important;
    color: #FFFFFF !important;
    border: none !important; 
    border-radius: 10px !important; 
    padding: 7px 18px !important;
    font-weight: 600 !important; 
    font-size: 13px !important; 
    box-shadow: 0 6px 14px -4px rgba(194, 104, 21, 0.45) !important; 
    transition: all 0.3s ease !important;
}

.hm-btn-primary:hover { 
    color: #000000 !important; 
    transform: translateY(-2px); 
    box-shadow: 0 15px 25px -5px rgba(194, 104, 21, 0.6) !important;
}

/* --- FIX SELECT2 OVERFLOW & STYLE --- */
.select2-container--default .select2-selection--multiple,
.select2-container--bootstrap4 .select2-selection--multiple {
    min-height: 45px !important;
    background: #F8FAFC !important;
    border: 1.5px solid #E2E8F0 !important;
    border-radius: 12px !important;
    padding: 4px 8px !important;
}

.select2-container--default.select2-container--focus .select2-selection--multiple,
.select2-container--bootstrap4.select2-container--focus .select2-selection--multiple {
    border-color: #B75301 !important;
    background: #FFFFFF !important;
    box-shadow: 0 0 0 3px rgba(183, 83, 1, 0.1) !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice,
.select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
    background-color: #FFF7ED !important;
    border: 1px solid #B75301 !important;
    color: #B75301 !important;
    border-radius: 6px !important;
    padding: 2px 8px !important;
    margin-top: 6px !important;
}

#uni_modal .modal-body {
    overflow: visible !important;
}
#uni_modal {
    overflow-y: auto !important;
}
.select2-container { z-index: 9999 !important; }
.select2-dropdown { z-index: 10000 !important; border-radius: 10px; border: 1.5px solid #E2E8F0; }

/* --- KOTAK LAINNYA DASHED --- */
#hm-manage-task-container .hm-lainnya-box {
    background-color: #FFF7ED;
    border: 1px dashed var(--hm-orange);
    border-radius: 12px;
    padding: 20px;
    margin-top: 12px;
    display: none;
}
#hm-manage-task-container .hm-label-normal {
    font-size: 15px;
    font-weight: 700;
    color: var(--hm-text);
    margin-bottom: 12px;
    display: block;
}

/* --- ROMBAK WARNA LIST DROPDOWN SELECT2 --- */
.select2-container--default .select2-results__option--highlighted[aria-selected],
.select2-container--default .select2-results__option--highlighted[data-selected] {
    background-color: #FFF7ED !important; 
    color: #B75301 !important; 
    font-weight: 600 !important;
}

.select2-container--default .select2-results__option {
    padding: 10px 15px !important;
    font-size: 14px !important;
    border-bottom: 1px solid #F8FAFC;
    transition: 0.2s;
}

.select2-dropdown { 
    z-index: 10000 !important; 
    border-radius: 12px !important; 
    border: 1.5px solid #E2E8F0 !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    overflow: hidden !important;
}
</style>

<script>
    $(document).ready(function(){
        // Animasi Box "Lainnya" Muncul/Tenggelam
        function handleLainnyaLogic(checkboxSelector, containerId, inputId) {
            $(document).on('change', checkboxSelector, function() {
                var val = $(this).val();
                var isChecked = $(this).is(':checked');

                if (val === 'Lainnya') {
                    if (isChecked) {
                        $(containerId).stop().slideDown(250);
                        $(inputId).prop('required', true).focus();
                    } else {
                        $(containerId).stop().slideUp(200);
                        $(inputId).prop('required', false).val('');
                    }
                }
            });
        }

        // Eksekusi fungsinya
        handleLainnyaLogic('.content-pillar-checkbox', '#contentLainnyaContainer', '#contentLainnyaText');
        handleLainnyaLogic('.platform-checkbox', '#platformLainnyaContainer', '#platformLainnyaText');

        $('.summernote').summernote({
            height: 200,
            toolbar: [
                [ 'style', [ 'style' ] ],
                [ 'font', [ 'bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear'] ],
                [ 'fontname', [ 'fontname' ] ],
                [ 'fontsize', [ 'fontsize' ] ],
                [ 'color', [ 'color' ] ],
                [ 'para', [ 'ol', 'ul', 'paragraph', 'height' ] ],
                [ 'table', [ 'table' ] ],
                [ 'view', [ 'undo', 'redo', 'fullscreen', 'codeview', 'help' ] ]
            ]
        });

        // Inisialisasi Select2 untuk ASSIGN TO (Class: .select2)
        $('.select2').select2({
            placeholder: "Select Employee",
            width: "100%", 
            dropdownParent: $('#hm-manage-task-container'), 
            maximumSelectionLength: 30,
            allowClear: true,
            closeOnSelect: false,
            dropdownPosition: 'below'
        });
    });

    
    $('#manage-task').off('submit').submit(function(e){
    e.preventDefault();
    start_load(); // fungsi loading spinner, kalau kamu pakai
    $.ajax({
        url: 'ajax.php?action=save_task',
        method: 'POST',
        data: $(this).serialize(),
        success: function(resp){
            if(resp == 1){
                alert_toast("Task Save", 'success');
                setTimeout(function(){
                    $('#uni_modal').modal('hide'); 
                    location.reload();
                }, 1500);
            } else if (resp == 2) {
                 alert_toast("Task gagal disimpan: Data duplikat atau error server", 'danger');
            }
             else {
                alert_toast(resp, 'danger');
            }
             end_load(); // Pastikan loading dihentikan
        },
        error: function(xhr, status, error) {
            alert_toast('AJAX Error: ' + error, "error");
            end_load();
        }
    });
});
</script>