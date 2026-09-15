<?php 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php'; 

$selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0; 
$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];
?>

<div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg hm-modal-dialog" role="document">
    <div class="modal-content hm-modal-content">
      <form action="" id="add-task-form">
        <div class="modal-header hm-modal-header">
          <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
          <button type="button" class="close hm-close-btn" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body hm-modal-body">
          <div class="form-group">
            <label for="project_id" class="hm-label"><b>Project</b></label>
                <div class="hm-select-wrapper">
                    <select name="project_id" id="project_id" class="form-control hm-input" required>
                    <option value="">Select Project</option>
                    <?php
                    $project_where = " WHERE 1=1 ";
                    if ($login_type == 2) {
                        $project_where .= " AND (manager_id = '$user_id' OR FIND_IN_SET('$user_id', user_ids)) ";
                    } elseif ($login_type == 3 || $login_type == 4) {
                  // employees and clients only see projects they are part of
                        $project_where .= " AND FIND_IN_SET('$user_id', user_ids) ";
                    }

                    $projects = $conn->query("
                        SELECT id, name 
                        FROM project_list p
                        $project_where
                        ORDER BY name ASC
                    ");

                    while($row = $projects->fetch_assoc()):
                        $selected = ($row['id'] == $selected_project_id) ? 'selected' : '';
                    ?>
                    <option value="<?= $row['id'] ?>" <?= $selected ?>>
                        <?= ucwords($row['name']) ?>
                    </option>
                    <?php endwhile; ?>
                    </select>
                    
                    <span class="hm-custom-arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14">
                        <path fill="none" stroke="#64748B" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 5l6 6 6-6"/>
                    </svg>
                    </span>
                </div>
            </div>

          <div class="form-group">
            <label for="task" class="hm-label"><b>Task</b></label>
            <input type="text" name="task" id="task" class="form-control hm-input" required>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="start_date" class="hm-label"><b>Start Date</b></label>
              <input type="date" name="start_date" id="start_date" class="form-control hm-input" required>
            </div>
            <div class="form-group col-md-6">
              <label for="end_date" class="hm-label"><b>End Date</b></label>
              <input type="date" name="end_date" id="end_date" class="form-control hm-input" required>
            </div>
          </div>

          <div class="form-group">
            <label for="description" class="hm-label"><b>Description</b></label>
            <textarea name="description" id="description" class="summernote form-control hm-summernote" placeholder="(text only)"></textarea>
          </div>

          <div class="form-group">
            <label class="hm-label"><b>Task Type</b></label><br>
            <?php
            $pillars = ['Edukasi', 'Tips', 'Behind The Scene', 'Testimoni', 'Portofolio', 'Awareness', 'Engagement', 'Promo', 'Lainnya'];
            foreach($pillars as $pillar):
            ?>
            <div class="form-check form-check-inline hm-pill-check">
                <input class="form-check-input content-pillar-checkbox" type="checkbox" name="content_pillar[]" value="<?= $pillar ?>" id="pillar_<?= $pillar ?>">
                <label class="form-check-label" for="pillar_<?= $pillar ?>"><?= $pillar ?></label>
            </div>
            <?php endforeach; ?>
            
            <div id="contentLainnyaContainer" class="hm-lainnya-box">
                <label for="contentLainnyaText">Please specify:</label>
                <input type="text" class="form-control" id="contentLainnyaText" name="lainnya_text" placeholder="Enter other task type...">
            </div>
          </div>

          <div class="form-group">
            <label class="hm-label"><b>Platform</b></label><br>
            <?php
            $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter', 'LinkedIn', 'Website', 'IGs', 'Reels', 'Feeds', 'Lainnya'];
            foreach($platforms as $plat):
            ?>
            <div class="form-check form-check-inline hm-pill-check">
                <input class="form-check-input platform-checkbox" type="checkbox" name="platform[]" value="<?= $plat ?>" id="platform_<?= $plat ?>">
                <label class="form-check-label" for="platform_<?= $plat ?>"><?= $plat ?></label>
            </div>
            <?php endforeach; ?>
            
            <div id="platformLainnyaContainer" class="hm-lainnya-box">
                <label for="platformLainnyaText">Please specify platform:</label>
                <input type="text" class="form-control" id="platformLainnyaText" name="platform_lainnya_text" placeholder="Enter other platform...">
            </div>
          </div>

          <div class="form-group">
            <label class="hm-label"><b>Reference</b></label>
            <textarea name="reference_links" id="reference_links" class="form-control hm-input" rows="3" placeholder="Pisahkan link dengan enter."></textarea>
          </div>

          <div class="form-group">
                <label class="hm-label"><b>Status</b></label>
                <div class="hm-select-wrapper">
                    <select name="status" id="status" class="form-control hm-input">
                    <option value="0">Pending</option>
                    <option value="1">Started</option>
                    <option value="2">On-Progress</option>
                    <option value="3">On-Hold</option>
                    <option value="4">Over Due</option>
                    <option value="5">Done</option>
                    </select> 
                    
                    <span class="hm-custom-arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14">
                        <path fill="none" stroke="#64748B" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 5l6 6 6-6"/>
                    </svg>
                    </span>
                </div>
            </div>

          <div class="form-group">
            <label class="hm-label"><b>Assign To</b></label>
            <select name="user_ids[]" id="user_ids" class="form-control select2 hm-input" multiple="multiple" style="width:100%;" required>
              <option value=""> Select project first </option>
            </select>
          </div>
        </div>
        
        <div class="modal-footer hm-modal-footer">
          <button type="button" class="btn hm-btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn hm-btn-primary">Save Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

#addTaskModal {
    --hm-orange: #B75301;
    --hm-orange-grad: linear-gradient(135deg, #B75301 0%, #f7941d 100%);
    --hm-bg: #FFFFFF;
    --hm-input-bg: #F8FAFC;
    --hm-border: #E2E8F0;
    --hm-text: #1E293B;
    --hm-muted: #64748B;
    --hm-font: 'Plus Jakarta Sans', sans-serif;
}

@media (min-width: 992px) {
    #addTaskModal .hm-modal-dialog.modal-lg {
        max-width: 850px; /* Silakan ubah angka ini sesuai kebutuhan lebar Anda */
    }
}

#addTaskModal .hm-modal-content {
    background: var(--hm-bg);
    border-radius: 24px;
    border: none;
    box-shadow: 0 25px 50px -12px rgba(183, 83, 1, 0.15);
    font-family: var(--hm-font);
    overflow: hidden;
}

/* Header dengan Gradasi Hai Motion */
#addTaskModal .hm-modal-header {
    background: var(--hm-orange-grad);
    padding: 20px 25px;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#addTaskModal .modal-title {
    color: #FFFFFF !important;
    font-weight: 700;
    font-size: 18px;
    letter-spacing: -0.5px;
}

#addTaskModal .hm-close-btn {
    color: #FFFFFF;
    opacity: 0.8;
    text-shadow: none;
    font-size: 24px;
    transition: 0.2s;
}

#addTaskModal .hm-close-btn:hover {
    opacity: 1;
    transform: scale(1.1);
}

/* Body Styling */
#addTaskModal .hm-modal-body {
    padding: 30px 25px;
    color: var(--hm-text);
}

#addTaskModal .hm-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--hm-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

#addTaskModal .hm-input {
    height: 45px;
    display: flex;
    align-items: center;
    line-height: 1.5;
    background: var(--hm-input-bg) !important;
    border: 1.5px solid var(--hm-border) !important;
    border-radius: 12px !important;
    color: var(--hm-text) !important;
    font-size: 14px;
    transition: all 0.2s;
}

/* untuk panah pada setiap label form */
.hm-select-wrapper {
    position: relative;
    display: block;
}

/* Mematikan panah bawaan browser dan memberi ruang padding kanan */
.hm-select-wrapper select.hm-input {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    padding: 0 45px 0 15px !important; 
}

/* Styling dan posisi panah buatan kita */
.hm-custom-arrow {
    position: absolute;
    right: 20px; 
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none; 
    display: flex;
    align-items: center;
    justify-content: center;
}

#addTaskModal textarea.hm-input {
    height: auto !important; 
    min-height: 120px;
    padding-top: 12px !important; 
    resize: vertical; 
    align-items: flex-start;
}

#addTaskModal .hm-input:focus {
    border-color: var(--hm-orange) !important;
    background: #FFFFFF !important;
    box-shadow: 0 0 0 4px rgba(183, 83, 1, 0.1) !important;
}

/* Pill Checkbox Light Version */
.hm-pill-check {
    margin-bottom: 10px;
}

.hm-pill-check input { display: none; }

.hm-pill-check .form-check-label {
    background: #F1F5F9;
    border: 1.5px solid transparent;
    color: var(--hm-muted);
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.hm-pill-check .form-check-label:hover {
    background: #E2E8F0;
}

/* State Aktif / Checked */
.hm-pill-check.is-checked .form-check-label {
    background: #FFF7ED;
    border-color: var(--hm-orange);
    color: var(--hm-orange);
    box-shadow: 0 4px 10px rgba(183, 83, 1, 0.1);
}

/* Specify Box */
.hm-lainnya-box {
    display: none;
    background: #FFF7ED;
    border: 1px dashed var(--hm-orange);
    border-radius: 12px;
    padding: 20px;
    margin-top: 15px;
}

/* Footer */
#addTaskModal .hm-modal-footer {
    border-top: 1px solid #F1F5F9;
    padding: 20px 25px;
    background: #F8FAFC;
}

.hm-btn-secondary {
    background: #E2E8F0;
    color: #475569;
    border: none;
    border-radius: 12px;
    padding: 12px 25px;
    font-weight: 600;
    font-size: 14px;
}

.hm-btn-primary {
    background: var(--hm-orange-grad);
    color: #FFFFFF;
    border: none;
    border-radius: 12px;
    padding: 12px 30px;
    font-weight: 700;
    font-size: 14px;
    box-shadow: 0 10px 15px -3px rgba(183, 83, 1, 0.3);
    transition: 0.2s;
}

.hm-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 20px -3px rgba(183, 83, 1, 0.4);
}

#addTaskModal input[type="date"].hm-input {
    display: block; /* Membatalkan display: flex dari .hm-input */
    position: relative;
}

#addTaskModal input[type="date"]::-webkit-calendar-picker-indicator {
    position: absolute;
    right: 15px; /* Menyesuaikan dengan padding kanan form */
    top: 50%;
    transform: translateY(-50%); /* Menjaga agar icon benar-benar di tengah secara vertikal */
    cursor: pointer;
    background-color: transparent;
}
</style>

<script>
$(document).ready(function(){
    // Inisialisasi editor
    $('.summernote').summernote({ height: 200 });

    // Select2 configuration
    $('.select2').select2({
        placeholder: "Select Employee",
        width: '100%',
        dropdownParent: $('#addTaskModal')
    });

    // Menjaga logika auto-load project list asli
    if($('#project_id').val()){
        $('#project_id').trigger('change');
    }

    // Fungsi AJAX asli untuk mengambil user berdasarkan project (DIPERTAHANKAN)
    $('#project_id').change(function(){
        var pid = $(this).val();
        var userSelect = $('#user_ids');
        
        userSelect.html('<option value="">Loading users...</option>');
        userSelect.prop('disabled', true);

        if(pid){
            $.ajax({
                url: 'ajax.php?action=get_project_users',
                method: 'POST',
                data: {pid: pid},
                success:function(resp){
                    userSelect.prop('disabled', false);
                    userSelect.html(resp);
                    userSelect.trigger('change');
                }
            });
        } else {
            userSelect.html('<option value="">-- Select project first --</option>');
            userSelect.prop('disabled', true);
        }
    });

    // Logic visual untuk Pill Checkbox & Lainnya
    function handlePillLogic(checkboxClass, containerId, inputId) {
        $(document).on('change', checkboxClass, function() {
            var isChecked = $(this).is(':checked');
            var val = $(this).val();
            var parent = $(this).closest('.hm-pill-check');

            if (isChecked) parent.addClass('is-checked');
            else parent.removeClass('is-checked');

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

    handlePillLogic('.content-pillar-checkbox', '#contentLainnyaContainer', '#contentLainnyaText');
    handlePillLogic('.platform-checkbox', '#platformLainnyaContainer', '#platformLainnyaText');

    // AJAX Submit (DIPERTAHANKAN)
    $('#add-task-form').submit(function(e){
        e.preventDefault();
        if (typeof start_load !== 'undefined') { start_load(); }

        $.ajax({
            url: 'ajax.php?action=save_task',
            method: 'POST',
            data: $(this).serialize(),
            success:function(resp){
                if(resp == 1){
                    alert_toast("Task successfully added", 'success');
                    $('#addTaskModal').modal('hide');
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    alert_toast("Error: " + resp, 'danger');
                }
                if (typeof end_load !== 'undefined') { end_load(); }
            }
        });
    });
});
</script>