<?php 
if(!isset($conn)){ 
    include 'db_connect.php'; 
} 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- Header -->
<div class="pf-header">
    <?php if(isset($id) && !empty($id)): ?>
        <div class="pf-header-title">EDIT PROJECT</div>
        <div class="pf-header-sub">Customize your project to match your needs and preferences.</div>
    <?php else: ?>
        <div class="pf-header-title">NEW PROJECT</div>
        <div class="pf-header-sub">Let's Making New Journey</div>
    <?php endif; ?>
</div>

<form action="" id="manage-project">
    <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">

    <!-- Basic Information -->
    <div class="pf-section">
        <div class="pf-section-head">
            <div class="pf-sec-num">1</div>
            <div>
                <div class="pf-sec-title">Basic Information</div>
                <div class="pf-sec-sub">Project name, status, and timeline</div>
            </div>
        </div>

        <div class="row">
            <!-- Name -->
            <div class="col-md-6 mb-3">
                <div class="form-group">
                    <label class="control-label">
                        Project Name
                        <span style="color:#ef4444;font-size:13px;">*</span>
                    </label>
                    <input type="text"
                           class="form-control form-control-sm"
                           name="name"
                           placeholder="e.g. Website Redesign Q2"
                           value="<?php echo isset($name) ? $name : '' ?>">
                </div>
            </div>

            <!-- Status -->
            <div class="col-md-6 mb-3">
                <div class="form-group">
                    <label>Status</label>
                    <!-- ORIGINAL select -->
                    <select name="status" id="status" class="custom-select custom-select-sm" onchange="updateStatusPill(this.value)">
                        <option value="0" <?php echo isset($status) && $status == 0 ? 'selected' : '' ?>>Pending</option>
                        <option value="1" <?php echo isset($status) && $status == 1 ? 'selected' : '' ?>>Started</option>
                        <option value="2" <?php echo isset($status) && $status == 2 ? 'selected' : '' ?>>On-Progress</option>
                        <option value="3" <?php echo isset($status) && $status == 3 ? 'selected' : '' ?>>On-Hold</option>
                        <option value="4" <?php echo isset($status) && $status == 4 ? 'selected' : '' ?>>Overdue</option>
                        <option value="5" <?php echo isset($status) && $status == 5 ? 'selected' : '' ?>>Done</option>
                    </select>
                    <!-- Pill preview -->
                    <div class="status-pill-preview">
                        <span class="status-pill" id="statusPillPreview"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Start Date -->
            <div class="col-md-6 mb-3">
                <div class="form-group">
                    <label class="control-label">
                        Start Date
                        <span style="color:#ef4444;font-size:13px;">*</span>
                    </label>
                    <input type="date"
                           class="form-control form-control-sm"
                           autocomplete="off"
                           name="start_date"
                           value="<?php echo isset($start_date) ? date("Y-m-d",strtotime($start_date)) : '' ?>">
                </div>
            </div>

            <!-- End Date -->
            <div class="col-md-6 mb-3">
                <div class="form-group">
                    <label class="control-label">
                        End Date
                        <span style="color:#ef4444;font-size:13px;">*</span>
                    </label>
                    <input type="date"
                           class="form-control form-control-sm"
                           autocomplete="off"
                           name="end_date"
                           value="<?php echo isset($end_date) ? date("Y-m-d",strtotime($end_date)) : '' ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Team Assignment -->
    <div class="pf-section">
        <div class="pf-section-head">
            <div class="pf-sec-num">2</div>
            <div>
                <div class="pf-sec-title">Team Assignment</div>
                <div class="pf-sec-sub">Assign a project manager and team members</div>
            </div>
        </div>

        <div class="row">
            <!-- Project Manager -->
            <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] == 1 ): ?>
            <div class="col-md-12 mb-3">
                <div class="form-group">
                    <label class="control-label">
                        Project Manager
                        <span style="color:#ef4444;font-size:13px;">*</span>
                    </label>
                    <select class="form-control form-control-sm select2" name="manager_id">
                        <option></option>
                        <?php 
                        $managers = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where type IN (1, 2) order by concat(firstname,' ',lastname) asc ");
                        while($row= $managers->fetch_assoc()):
                            $role_label = $row['type'] == 1 ? ' (Admin)' : ' (Manager)';
                        ?>
                        <option value="<?php echo $row['id'] ?>" <?php echo isset($manager_id) && $manager_id == $row['id'] ? "selected" : '' ?>><?php echo ucwords($row['name']) . $role_label ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="manager_id" value="<?php echo isset($_SESSION['login_id']) ? $_SESSION['login_id'] : '' ?>">
            <?php endif; ?>

            <!-- Project Team Members -->
            <div class="col-md-12 mb-3">
                <div class="form-group">
                    <label class="control-label">Project Team Members</label>
                    <select class="form-control form-control-sm select2" multiple="multiple" name="user_ids[]">
                        <option></option>
                        <?php 
                        $employees = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where type IN (1, 2, 3, 4) order by concat(firstname,' ',lastname) asc ");
                        while($row= $employees->fetch_assoc()):
                            if ($row['type'] == 1) {
                                $role_label = ' (Admin)';
                            } elseif ($row['type'] == 2) {
                                $role_label = ' (Manager)';
                            } else {
                                $role_label = ' (Member)'; 
                            }
                        ?>
                            <?php
                                if ($row['type'] == 4) {
                                    $role_label = ' (Client)';
                                }
                            ?>
                        <option value="<?php echo $row['id'] ?>" <?php echo isset($user_ids) && in_array($row['id'],explode(',',$user_ids)) ? "selected" : '' ?>><?php echo ucwords($row['name']) . $role_label ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="pf-section">
        <div class="pf-section-head">
            <div class="pf-sec-num">3</div>
            <div>
                <div class="pf-sec-title">Project Description</div>
                <div class="pf-sec-sub">Goals, scope, and key deliverables</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <textarea name="description"
                              id="description_summernote"
                              cols="30" rows="10"
                              class="summernote form-control"><?php echo isset($description) ? htmlspecialchars($description) : '' ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Actions -->
    <div class="pf-footer">
        <div class="pf-btn-group">
            <button class="btn btn-secondary" type="button" onclick="window.history.back()">Close</button>
            <button class="btn-save" type="submit" form="manage-project">
                Save Project
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</form>

<style>
/* ── Google Font ── */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');


/* ── Form wrapper ── */
#manage-project {
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: #334155;
}

/* ── Section heading ── */
.pf-section {
    margin-bottom: 24px;
}
.pf-section-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 1.5px solid #e4e8f0;
    margin-bottom: 16px;
}
.pf-sec-num {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: #B75301;
    color: #fff;
    font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.pf-sec-title {
    font-size: 13px; font-weight: 700; color: #0f172a;
}
.pf-sec-sub {
    font-size: 11.5px; color: #94a3b8; margin-top: 1px;
}

/* ── Labels ── */
#manage-project .control-label,
#manage-project label {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    margin-bottom: 6px !important;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── Inputs & Selects (Bootstrap override) ── */
#manage-project .form-control,
#manage-project .custom-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 13.5px !important;
    color: #0f172a !important;
    background: #ffffff !important;
    border: 1.5px solid #e4e8f0 !important;
    border-radius: 8px !important;
    padding: 9px 13px !important;
    height: auto !important;
    box-shadow: none !important;
    transition: border-color .16s, box-shadow .16s !important;
}
#manage-project .form-control::placeholder {
    color: #94a3b8 !important;
    font-size: 13px !important;
}
#manage-project .form-control:hover,
#manage-project .custom-select:hover {
    border-color: #c4cdd8 !important;
}
#manage-project .form-control:focus,
#manage-project .custom-select:focus {
    border-color: #B75301 !important;
    box-shadow: 0 0 0 3.5px rgba(183,83,1,.10) !important;
}

/* ── Custom Select (Status) ── */
#manage-project .custom-select {
    appearance: none !important;
    -webkit-appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 12px center !important;
    padding-right: 36px !important;
    cursor: pointer !important;
}

/* ── Status pill preview ── */
.status-pill-preview {
    display: inline-flex;
    align-items: center;
    margin-top: 6px;
}
.status-pill {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 4px 16px;
    border-radius: 999px;
    font-size: 12px; font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: background .2s, color .2s;
}

/* ── Select2 override ── */
/* Single select — Project Manager */
#manage-project .select2-container--default .select2-selection--single {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    border: 1.5px solid #e4e8f0 !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    height: 40px !important;
    padding: 0 10px !important;
    display: flex !important;
    align-items: center !important;
}
#manage-project .select2-container--default .select2-selection--single .select2-selection__rendered {
    font-size: 13.5px !important;
    color: #0f172a !important;
    line-height: normal !important;
    padding-left: 0 !important;
    padding-right: 20px !important;
    width: 100% !important;
}
#manage-project .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
    top: 0 !important;
}

/* Multiple select — Project Team Members */
#manage-project .select2-container--default .select2-selection--multiple {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    border: 1.5px solid #e4e8f0 !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    min-height: 40px !important;
    padding: 4px 10px !important;
}

#manage-project .select2-container--default .select2-selection--single:hover,
#manage-project .select2-container--default .select2-selection--multiple:hover {
    border-color: #c4cdd8 !important;
}
#manage-project .select2-container--default.select2-container--focus .select2-selection--single,
#manage-project .select2-container--default.select2-container--focus .select2-selection--multiple,
#manage-project .select2-container--default.select2-container--open .select2-selection--single,
#manage-project .select2-container--default.select2-container--open .select2-selection--multiple {
    border-color: #B75301 !important;
    box-shadow: 0 0 0 3.5px rgba(183,83,1,.10) !important;
    outline: none !important;
}
#manage-project .select2-container--default .select2-selection--single .select2-selection__rendered {
    font-size: 13.5px !important;
    color: #0f172a !important;
    line-height: 30px !important;
    padding-left: 0 !important;
}
#manage-project .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #94a3b8 !important;
}
#manage-project .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px !important;
}
#manage-project .select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: #B75301 !important;
    border: none !important;
    border-radius: 6px !important;
    color: #ffffff !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    padding: 2px 8px !important;
    margin-top: 5px !important;
}
#manage-project .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: #ffffff !important;
    margin-right: 4px !important;
}
#manage-project .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #B75301 !important;
}
#manage-project .select2-dropdown {
    border: 1.5px solid #e4e8f0 !important;
    border-radius: 8px !important;
    box-shadow: 0 8px 24px rgba(15,23,42,.10) !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 13.5px !important;
}
#manage-project .select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1.5px solid #e4e8f0 !important;
    border-radius: 6px !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 13px !important;
    padding: 6px 10px !important;
    outline: none !important;
}
#manage-project .select2-container--default .select2-search--dropdown .select2-search__field:focus {
    border-color: #B75301 !important;
    box-shadow: 0 0 0 3px rgba(183,83,1,.10) !important;
}

/* ── Summernote override ── */
#manage-project .note-editor.note-frame {
    border: 1.5px solid #e4e8f0 !important;
    border-radius: 8px !important;
    overflow: hidden !important;
    box-shadow: none !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}
#manage-project .note-editor.note-frame:focus-within {
    border-color: #B75301 !important;
    box-shadow: 0 0 0 3.5px rgba(183,83,1,.10) !important;
}
#manage-project .note-toolbar {
    background: #f8f9fb !important;
    border-bottom: 1.5px solid #e4e8f0 !important;
    padding: 6px 8px !important;
}
#manage-project .note-toolbar .note-btn {
    border-radius: 6px !important;
    border: 1px solid transparent !important;
    font-size: 12px !important;
}
#manage-project .note-toolbar .note-btn:hover {
    background: #e4e8f0 !important;
    border-color: #e4e8f0 !important;
}
#manage-project .note-editable {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 14px !important;
    color: #0f172a !important;
    padding: 12px 14px !important;
}

/* ── Footer action bar ── */
.pf-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding-top: 20px;
    border-top: 1.5px solid #e4e8f0;
    margin-top: 8px;
}

.pf-btn-group { display: flex; gap: 10px; }

/* ── Button overrides ── */
#manage-project .btn-secondary,
.pf-footer .btn-secondary {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 13.5px !important;
    font-weight: 700 !important;
    padding: 9px 22px !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    border: 1.5px solid #e4e8f0 !important;
    color: #475569 !important;
    transition: all .17s !important;
    box-shadow: none !important;
}
#manage-project .btn-secondary:hover,
.pf-footer .btn-secondary:hover {
    border-color: #c4cdd8 !important;
    color: #0f172a !important;
}
.pf-footer .btn-save {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 13.5px !important;
    font-weight: 700 !important;
    padding: 9px 22px !important;
    border-radius: 8px !important;
    background: #B75301 !important;
    border: none !important;
    color: #fff !important;
    display: flex; align-items: center; gap: 6px;
    box-shadow: 0 2px 8px rgba(183,83,1,.25) !important;
    transition: all .17s !important;
    cursor: pointer;
}
.pf-footer .btn-save:hover {
    background: #8f4001 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 5px 16px rgba(183,83,1,.35) !important;
}
.pf-footer .btn-save:active { transform: translateY(0) !important; }

/* ── Form group spacing ── */
#manage-project .form-group {
    margin-bottom: 0 !important;
}
#manage-project .row {
    margin-bottom: 0 !important;
}

.pf-header {
    padding: 4px 0 22px;
    margin-bottom: 26px;
    text-align: center;
}
.pf-header-title {
    font-size: 35px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: .04em;
    margin-bottom: 5px;
}
.pf-header-sub {
    font-size: 15px;
    font-weight: 400;
    color: #94a3b8;
}
</style>

<script>
    /*STATUS PILL PREVIEW*/
    var STATUS_PILLS = {
        0: { label: 'Pending',     bg: '#6c757d', color: '#fff'     },
        1: { label: 'Started',     bg: '#1a237e', color: '#fff'     },
        2: { label: 'On-Progress', bg: '#4fc3f7', color: '#ffffff'  },
        3: { label: 'On-Hold',     bg: '#ffc107', color: '#ffffff'  },
        4: { label: 'Overdue',     bg: '#dc3545', color: '#fff'     },
        5: { label: 'Done',        bg: '#28a745', color: '#fff'     }
    };
    function updateStatusPill(val) {
        var s = STATUS_PILLS[val];
        if (!s) return;
        var el = document.getElementById('statusPillPreview');
        el.textContent    = s.label;
        el.style.background = s.bg;
        el.style.color      = s.color;
    }
    // Init pill on page load based on current selected value
    (function(){
        var sel = document.getElementById('status');
        if (sel) updateStatusPill(sel.value);
    })();

    $(document).on('focusin', function(e) {
        if ($(e.target).closest(".note-editor, .select2-container").length) {
            e.stopImmediatePropagation();
        }
    });

    $(document).ready(function(){
        
        $('.select2').select2({
            placeholder: "Select here",
            width: '100%',
            dropdownParent: $('#uni_modal')
        });
        
        $('#description_summernote').summernote({
            height: 150,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['view', ['codeview', 'help']]
            ]
        });

        $('#manage-project').submit(function(e){
            e.preventDefault()
            $.ajax({
                url:'ajax.php?action=save_project',
                data: new FormData($(this)[0]),
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                type: 'POST',
                success: function(resp) {
                    if (resp == 1) {
                        if ($('#uni_modal').length) {
                            $('#uni_modal').modal('hide');
                        }
                        if (typeof alert_toast === 'function') {
                            alert_toast('Project successfully saved', "success");
                        } else {
                            alert('Project successfully saved');
                        }
                        setTimeout(function() {
                            window.history.back(); // ← go back instead of reload
                        }, 1500);
                    } else {
                        alert('Error: ' + resp);
                    }
                }
            })
        })
    });
</script>