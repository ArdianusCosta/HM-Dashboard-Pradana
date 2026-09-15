<?php 
    include 'header.php';
?>
<div class="pf-header">
    <?php if(isset($id) && !empty($id)): ?>
        <div class="pf-header-title">EDIT USER</div>
        <div class="pf-header-sub">Customize user details to match your needs.</div>
    <?php else: ?>
        <div class="pf-header-title">NEW USER</div>
        <div class="pf-header-sub">Let's create a new account for Hai Motion.</div>
    <?php endif; ?>
</div>

<div class="col-lg-12">
    <form action="" id="manage_user" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">

        <div class="pf-section">
            <div class="pf-section-head">
                <div class="pf-sec-num">1</div>
                <div>
                    <div class="pf-sec-title">Basic Information</div>
                    <div class="pf-sec-sub">Personal details and staff info</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            First Name <span style="color:#ef4444;font-size:13px;">*</span>
                        </label>
                        <input type="text" name="firstname" class="form-control form-control-sm" required value="<?= isset($firstname) ? $firstname : '' ?>">
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            Position <span style="color:#ef4444;font-size:13px;">*</span>
                        </label>
                        <input type="text" name="lastname" class="form-control form-control-sm" required value="<?= isset($lastname) ? $lastname : '' ?>">
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            Staff Code <span style="color:#ef4444;font-size:13px;">*</span>
                        </label>
                        <input type="text" name="nik" class="form-control form-control-sm" required value="<?= isset($nik) ? $nik : '' ?>">
                    </div>
                </div>

                <div class="col-md-12 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            Bio <span style="color:#ef4444;font-size:13px;">*</span>
                        </label>
                        <textarea name="address" rows="3" class="form-control form-control-sm" required><?= isset($address) ? $address : '' ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="pf-section">
            <div class="pf-section-head">
                <div class="pf-sec-num">2</div>
                <div>
                    <div class="pf-sec-title">Account Settings</div>
                    <div class="pf-sec-sub">Role, email, and security</div>
                </div>
            </div>

            <div class="row">
                <?php if($_SESSION['login_type'] == 1): ?>
                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            User Role <span style="color:#ef4444;font-size:13px;">*</span>
                        </label>
                        <select name="type" class="custom-select custom-select-sm">
                            <option value="4" <?= isset($type) && $type == 4 ? 'selected' : '' ?>>Client</option>
                            <option value="3" <?= isset($type) && $type == 3 ? 'selected' : '' ?>>Employee</option>
                            <option value="2" <?= isset($type) && $type == 2 ? 'selected' : '' ?>>Project Manager</option>
                            <option value="1" <?= isset($type) && $type == 1 ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="type" value="3">
                <?php endif; ?>

                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            Email (Login) <span style="color:#ef4444;font-size:13px;">*</span>
                        </label>
                        <input type="email" name="email" class="form-control form-control-sm" required value="<?= isset($email) ? $email : '' ?>">
                        <small id="msg_email"></small>
                    </div>
                </div>

                <div class="col-md-12 mb-3">
                    <div class="form-group">
                        <label class="control-label">Notification Email (Gmail)</label>
                        <input type="email" name="notification_email" class="form-control form-control-sm" value="<?= isset($notification_email) ? $notification_email : '' ?>">
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            Password <?= !isset($id) ? '<span style="color:#ef4444;font-size:13px;">*</span>' : '' ?>
                        </label>
                        <input type="password" name="password" class="form-control form-control-sm" <?= !isset($id) ? 'required' : '' ?>>
                        <small><i><?= isset($id) ? 'Kosongkan jika tidak diubah' : '' ?></i></small>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="form-group">
                        <label class="control-label">
                            Confirm Password <?= !isset($id) ? '<span style="color:#ef4444;font-size:13px;">*</span>' : '' ?>
                        </label>
                        <input type="password" name="cpass" class="form-control form-control-sm" <?= !isset($id) ? 'required' : '' ?>>
                        <small id="pass_match" data-status=''></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="pf-section">
            <div class="pf-section-head">
                <div class="pf-sec-num">3</div>
                <div>
                    <div class="pf-sec-title">Profile Picture</div>
                    <div class="pf-sec-sub">Upload and adjust user avatar</div>
                </div>
            </div>

            <div class="avatar-upload-wrap">
                <div class="avatar-ring" onclick="document.getElementById('imageInput').click()">
                    <img src="<?= isset($avatar) ? 'assets/uploads/'.$avatar : 'assets/uploads/empty-placeholder.png' ?>" alt="Profile" id="cimg">
                    <div class="avatar-overlay">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <span>CHANGE</span>
                    </div>
                    <div class="avatar-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </div>
                </div>

                <div class="upload-hint">
                    <p class="uh-title">Profile Photo</p>
                    <p class="uh-sub">Click the photo or button below to upload</p>
                    <div class="fmt-row">
                        <span class="fmt-chip">PNG</span>
                        <span class="fmt-chip">JPG</span>
                        <span class="fmt-chip">GIF</span>
                        <span style="font-size:10.5px;color:#b0b7c3;margin-left:2px">· max 5 MB</span>
                    </div>
                </div>

                <button class="btn-choose" type="button" onclick="document.getElementById('imageInput').click()">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    Choose File
                </button>

                <div class="custom-file" style="display:none">
                    <input type="file" class="custom-file-input" name="img" id="imageInput" accept="image/*">
                    <label class="custom-file-label">Choose file</label>
                </div>
            </div>
        </div>

        <div class="pf-footer">
            <div class="pf-btn-group">
                <a href="index.php" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                <button class="btn-save" type="submit">
                    Save User
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ── CROP MODAL ── -->
<div class="modal fade" id="cropModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document" style="max-width:900px;">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.2);">
 
            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 22px;background:#fff;border-bottom:1px solid #e4e8f0;">
                <div style="display:flex;align-items:center;gap:12px;">
                    
                    <div>
                        <div style="font-size:15px;font-weight:700;color:#0f172a;line-height:1.2;">Crop Avatar</div>
                        <div style="font-size:11.5px;color:#94a3b8;margin-top:1px;">Drag to reposition · Scroll to zoom</div>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" style="width:30px;height:30px;border-radius:50%;background:#f3f4f6;border:none;display:flex;align-items:center;justify-content:center;color:#6b7280;font-size:16px;opacity:1;padding:0;margin:0;">
                    <span>&times;</span>
                </button>
            </div>
 
            <!-- Toolbar -->
            <div style="display:flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;background:#fafafa;border-bottom:1px solid #e4e8f0;flex-wrap:wrap;">
                <button type="button" class="crop-tool-btn" onclick="if(window._cropper) window._cropper.rotate(-90)">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M2.5 2v6h6M2.66 15.57a10 10 0 1 0 .57-8.38"/></svg>
                    Rotate L
                </button>
                <button type="button" class="crop-tool-btn" onclick="if(window._cropper) window._cropper.rotate(90)">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"/></svg>
                    Rotate R
                </button>
                <div style="width:1px;height:24px;background:#e4e8f0;margin:0 4px;"></div>
                <button type="button" class="crop-tool-btn" onclick="if(window._cropper) window._cropper.reset()">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset
                </button>
                <div style="width:1px;height:24px;background:#e4e8f0;margin:0 4px;"></div>
                <button type="button" class="crop-tool-btn" onclick="if(window._cropper){var d=window._cropper.getData();window._cropper.scaleX(d.scaleX===-1?1:-1);}">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3M16 3h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-3M12 3v18"/></svg>
                    Flip H
                </button>
                <button type="button" class="crop-tool-btn" onclick="if(window._cropper){var d=window._cropper.getData();window._cropper.scaleY(d.scaleY===-1?1:-1);}">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3M21 16v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3M3 12h18"/></svg>
                    Flip V
                </button>
            </div>
 
            <!-- Body: crop area + preview panel -->
            <div style="display:grid;grid-template-columns:1fr 180px;background:#f8f9fb;border-bottom:1px solid #e4e8f0;">
 
                <!-- Crop canvas -->
                <div class="modal-body" style="padding:20px;max-height:520px;overflow-y:auto;background:#f8f9fb;">
                    <div class="img-container" style="text-align:center;border-radius:10px;overflow:hidden;background:#1a1a2e;">
                        <img id="imageToCrop" src="" alt="Image to crop" style="max-width:100%;max-height:1200px;width:auto;height:auto;">
                    </div>
                </div>
 
                <!-- Preview panel -->
                <div style="padding:20px 16px;display:flex;flex-direction:column;gap:16px;border-left:1px solid #e4e8f0;background:#fff;">
                    <div style="font-size:11px;font-weight:700;color:#94a3b8;letter-spacing:.06em;text-transform:uppercase;">Preview</div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:10px;">
                        <div>
                            <div id="prev-lg" style="width:100px;height:100px;border-radius:50%;overflow:hidden;border:2px solid #e4e8f0;background:#f3f4f6;"></div>
                            <div style="font-size:10px;color:#94a3b8;text-align:center;margin-top:4px;">100px</div>
                        </div>
                        <div>
                            <div id="prev-md" style="width:56px;height:56px;border-radius:50%;overflow:hidden;border:2px solid #e4e8f0;background:#f3f4f6;margin:0 auto;"></div>
                            <div style="font-size:10px;color:#94a3b8;text-align:center;margin-top:4px;">56px</div>
                        </div>
                        <div>
                            <div id="prev-sm" style="width:32px;height:32px;border-radius:50%;overflow:hidden;border:2px solid #e4e8f0;background:#f3f4f6;margin:0 auto;"></div>
                            <div style="font-size:10px;color:#94a3b8;text-align:center;margin-top:4px;">32px</div>
                        </div>
                    </div>
                    <div style="margin-top:auto;padding-top:12px;border-top:1px solid #f0f0f0;font-size:10.5px;color:#94a3b8;text-align:center;line-height:1.5;">Circle crop<br>1 : 1 ratio</div>
                </div>
            </div>
 
            <!-- Footer -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 22px;background:#fff;">
                <div style="display:flex;gap:10px;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="font-size:13px;font-weight:600;padding:8px 18px;border-radius:8px;background:#fff;border:1.5px solid #e4e8f0;color:#475569;box-shadow:none;">
                        Cancel
                    </button>
                    <button type="button" id="cropBtn" style="display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;padding:8px 22px;border-radius:8px;background:#B75301;border:none;color:#fff;box-shadow:0 2px 8px rgba(183,83,1,.28);cursor:pointer;transition:background .15s;">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                        Crop & Save
                    </button>
                </div>
            </div>
 
        </div>
    </div>
</div>

<style>
/* ── Header ── */
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
 
/* ── Section ── */
.pf-section { margin-bottom: 24px; }
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
.pf-sec-title { font-size: 13px; font-weight: 700; color: #0f172a; }
.pf-sec-sub { font-size: 11.5px; color: #94a3b8; margin-top: 1px; }
 
/* ── Labels ── */
#manage_user .control-label,
#manage_user label:not(.custom-file-label) {
    font-size: 12.5px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    margin-bottom: 6px !important;
    display: flex; align-items: center; gap: 4px;
}
 
/* ── Inputs & Selects ── */
#manage_user .form-control,
#manage_user .custom-select {
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
#manage_user .form-control::placeholder { color: #94a3b8 !important; }
#manage_user .form-control:hover,
#manage_user .custom-select:hover { border-color: #c4cdd8 !important; }
#manage_user .form-control:focus,
#manage_user .custom-select:focus {
    border-color: #B75301 !important;
    box-shadow: 0 0 0 3.5px rgba(183,83,1,.10) !important;
    outline: none !important;
}
#manage_user .custom-select {
    appearance: none !important;
    -webkit-appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 12px center !important;
    padding-right: 36px !important;
    cursor: pointer !important;
}
#manage_user .form-group { margin-bottom: 0 !important; }
 
/* ── Profile Picture ── */
.avatar-upload-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 10px 0 6px;
}
.avatar-ring {
    position: relative;
    width: 130px; height: 130px;
    border-radius: 50%;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.avatar-ring::before {
    content: '';
    position: absolute; inset: -8px;
    border-radius: 50%;
    border: 2px dashed #d1d5db;
    transition: border-color .25s;
}
.avatar-ring:hover::before { border-color: #B75301; }
#cimg {
    width: 130px; height: 130px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 4px 18px rgba(0,0,0,.12);
    display: block;
    transition: opacity .2s;
}
.avatar-ring:hover #cimg { opacity: .85; }
.avatar-overlay {
    position: absolute; inset: 0;
    border-radius: 50%;
    background: rgba(183,83,1,.42);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 4px; opacity: 0;
    transition: opacity .2s;
    color: #fff; pointer-events: none;
}
.avatar-ring:hover .avatar-overlay { opacity: 1; }
.avatar-overlay span { font-size: 10px; font-weight: 700; letter-spacing: .06em; }
.avatar-badge {
    position: absolute; top: 4px; right: 4px;
    width: 32px; height: 32px; border-radius: 50%;
    background: #B75301;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(183,83,1,.4);
    border: 2.5px solid #fff;
    transition: transform .5s cubic-bezier(.4,0,.2,1), background .15s;
    z-index: 2;
}
.avatar-ring:hover .avatar-badge { transform: rotate(360deg); background: #8f4101; }
.upload-hint { text-align: center; }
.upload-hint .uh-title { font-size: 13px; font-weight: 700; color: #0f172a; margin: 0 0 3px; }
.upload-hint .uh-sub { font-size: 11.5px; color: #9ca3af; margin: 0; }
.fmt-row { display: flex; align-items: center; gap: 5px; justify-content: center; margin-top: 6px; }
.fmt-chip { padding: 2px 7px; background: #f3f4f6; border-radius: 4px; font-size: 10px; font-weight: 700; color: #9ca3af; letter-spacing: .05em; }
.btn-choose {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 20px; background: #B75301; color: #fff;
    border: none; border-radius: 8px;
    font-size: 12.5px; font-weight: 700; cursor: pointer;
    transition: background .15s, transform .1s, box-shadow .15s;
    box-shadow: 0 3px 10px rgba(183,83,1,.28);
}
.btn-choose:hover { background: #8f4101; transform: translateY(-1px); }
 
/* ── Footer ── */
.pf-footer {
    display: flex; align-items: center; justify-content: flex-end;
    flex-wrap: wrap; gap: 10px;
    padding-top: 20px; border-top: 1.5px solid #e4e8f0;
    margin-top: 8px;
}
.pf-btn-group { display: flex; gap: 10px; }
#manage_user .btn-secondary,
.pf-footer .btn-secondary {
    font-size: 13.5px !important; font-weight: 700 !important;
    padding: 9px 22px !important; border-radius: 8px !important;
    background: #ffffff !important; border: 1.5px solid #e4e8f0 !important;
    color: #475569 !important; transition: all .17s !important;
    box-shadow: none !important;
}
#manage_user .btn-secondary:hover,
.pf-footer .btn-secondary:hover { border-color: #c4cdd8 !important; color: #0f172a !important; }
.pf-footer .btn-save {
    font-size: 13.5px !important; font-weight: 700 !important;
    padding: 9px 22px !important; border-radius: 8px !important;
    background: #B75301 !important; border: none !important; color: #fff !important;
    display: flex; align-items: center; gap: 6px;
    box-shadow: 0 2px 8px rgba(183,83,1,.25) !important;
    transition: all .17s !important; cursor: pointer;
}
.pf-footer .btn-save:hover { background: #8f4001 !important; transform: translateY(-1px) !important; }
 
/* ── Crop toolbar ── */
.crop-tool-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 13px; border: 1.5px solid #e4e8f0;
    border-radius: 7px; background: #fff;
    font-size: 12px; font-weight: 600; color: #475569;
    cursor: pointer; transition: all .15s;
}
.crop-tool-btn:hover { border-color: #B75301; color: #B75301; background: rgba(183,83,1,.04); }
.crop-tool-btn svg { flex-shrink: 0; }
 
/* ── Cropper ── */
.img-container { position: relative; max-height: 600px; overflow: hidden; }
#imageToCrop { max-width: 100% !important; max-height: 600px !important; width: 100% !important; height: auto !important; }
.cropper-container { position: relative; overflow: hidden; max-height: 600px !important; }
.cropper-canvas, .cropper-crop-box { max-height: 600px !important; }
</style>

<!-- Include Cropper.js library -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
    let cropper = null;
    let croppedImageData = null;

    // Handle image selection
    $('#imageInput').on('change', function(e) {
        const file = e.target.files[0];
        
        // Quick validation
        if (!file) return;
        
        if (!file.type.match('image.*')) {
            alert('Please select an image file');
            $(this).val('');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) { // 5MB
            alert('File size must be less than 5MB');
            $(this).val('');
            return;
        }
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                // Use object URL instead of data URL for better performance
                const objectURL = URL.createObjectURL(file);
                $('#imageToCrop').attr('src', objectURL);
                
                // Clean up previous object URL if exists
                if (window.lastImageURL) {
                    URL.revokeObjectURL(window.lastImageURL);
                }
                window.lastImageURL = objectURL;
                
                // Destroy existing cropper if it exists
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                
                // Set container size before initializing cropper
                const container = document.getElementById('imageToCrop').parentElement;
                container.style.minHeight = '600px';
                container.style.display = 'flex';
                container.style.alignItems = 'center';
                container.style.justifyContent = 'center';
                
                // Initialize cropper for circular image - FIXED SETTINGS
                $('#cropModal').one('shown.bs.modal', function() {
                    cropper = new Cropper(document.getElementById('imageToCrop'), {
                        aspectRatio: 1,
                        viewMode: 0,
                        autoCropArea: 0.8,
                        responsive: true,
                        restore: true,
                        guides: true,
                        center: true,
                        highlight: true,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                        dragMode: 'move',
                        movable: true,
                        rotatable: true,
                        scalable: true,
                        zoomable: true,
                        zoomOnTouch: true,
                        zoomOnWheel: true,
                        wheelZoomRatio: 0.1,
                        minContainerWidth: 800,
                        minContainerHeight: 600,
                        preview: [
                            document.getElementById('prev-lg'),
                            document.getElementById('prev-md'),
                            document.getElementById('prev-sm'),
                        ],
                    });
                    window._cropper = cropper;
                });
                
                // Show crop modal
                $('#cropModal').modal('show');
                
                // REMOVE THIS setTimeout block - it was restricting movement
                // setTimeout(function() {
                //     cropper.setCanvasData({
                //         left: 0,
                //         top: 0,
                //         width: 800,
                //         height: 800
                //     });
                // }, 300);
            };
            reader.readAsDataURL(file);
        }
    });

    // Handle crop button
    $('#cropBtn').on('click', function() {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({
                width: 500, // Set explicit size instead of max
                height: 500,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            // Convert canvas to blob and update file input
            canvas.toBlob(function(blob) {
                const file = new File([blob], 'avatar.png', { type: 'image/png' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                document.getElementById('imageInput').files = dataTransfer.files;
                
                // Display cropped image
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#cimg').attr('src', e.target.result);
                };
                reader.readAsDataURL(blob);
                
                // Close modal
                $('#cropModal').modal('hide');
            }, 'image/png', 0.9); // Add quality parameter
        }
    });

    // CRITICAL FIX: Clean up when modal closes
    $('#cropModal').on('hidden.bs.modal', function() {
        // Clean up cropper instance
        if (cropper) {
            cropper.destroy();
            cropper = null;
            window._cropper = null;
            window._flipX = 1; 
            window._flipY = 1;
        }
        
        // Clear image source to free memory
        $('#imageToCrop').attr('src', '');
        
        // Revoke object URL to prevent memory leaks
        if (window.lastImageURL) {
            URL.revokeObjectURL(window.lastImageURL);
            window.lastImageURL = null;
        }
    });

    $('[name="password"], [name="cpass"]').keyup(function() {
        let pass = $('[name="password"]').val();
        let cpass = $('[name="cpass"]').val();
        if (cpass === '' || pass === '') {
            $('#pass_match').attr('data-status', '');
        } else if (cpass === pass) {
            $('#pass_match').attr('data-status', '1').html('<span class="text-success">Password matched.</span>');
        } else {
            $('#pass_match').attr('data-status', '2').html('<span class="text-danger">Passwords do not match.</span>');
        }
    });
    $('[name="email"]').on('blur', function() {
        let email = $(this).val();
        let id = $('[name="id"]').val();
        $.post('ajax.php?action=check_email', { email: email, id: id }, function(resp) {
            if (resp == 1) {
                $('#msg_email').html('<div class="text-danger">Email sudah digunakan.</div>');
                $('[name="email"]').addClass("border-danger");
            } else {
                $('#msg_email').html('');
                $('[name="email"]').removeClass("border-danger");
            }
        });
    });
    $('#manage_user').submit(function(e) {
        e.preventDefault();
        if ($('[name="password"]').val() !== '' && $('[name="cpass"]').val() !== '') {
            if ($('#pass_match').attr('data-status') != 1) {
                alert("Password tidak cocok.");
                return false;
            }
        }
        start_load();
        $.ajax({
            url: 'ajax.php?action=save_user',
            data: new FormData(this),
            cache: false,
            contentType: false,
            processData: false,
            method: 'POST',
                success:function(resp){
                    if(resp == 1){
                        end_load();
                        var userRole = <?php echo $_SESSION['login_type']; ?>;
                        alert_toast("User saved success.", "success");
                        setTimeout(function() {
                            if (userRole == 1) {
                                window.location.replace("index.php?page=user_list");
                            } 
                            else if (userRole == 2 || userRole == 3) {
                                window.location.replace("index.php");
                            } 
                        }, 1200);
                    }
                }
        });
    });
</script>