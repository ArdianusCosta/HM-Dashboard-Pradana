<?php
// FILE: get_task_detail.php (Menampilkan detail task di dalam modal)

include 'db_connect.php';
session_start();

$login_type = $_SESSION['login_type'] ?? 0;

if (!isset($_REQUEST['id'])) { 
    echo "ID tidak ditemukan.";
    exit;
}

// ----------------------------------------------------
// ➡️ 1. DECODE TASK ID YANG MASUK (KRITIS)
// ----------------------------------------------------
$encoded_task_id = $_REQUEST['id'];
$id_decoded = decode_id($encoded_task_id);

if (!is_numeric($id_decoded) || $id_decoded <= 0) {
    echo "<div class='alert alert-danger p-3'>ID Task tidak valid atau tidak dapat didekode.</div>";
    exit;
}

$id = intval($id_decoded); // ID Task numerik yang aman

// Logic untuk update overdue juga di task detail (optional, tapi dipertahankan)
$conn->query("
    UPDATE task_list 
    SET status = 4 
    WHERE end_date < NOW() 
    AND status NOT IN (0,3,5)
");

$qry = $conn->query("SELECT * FROM task_list WHERE id = $id");

if ($qry->num_rows > 0) {
    $row = $qry->fetch_assoc();
    $project_id = $row['project_id']; 

    // ➡️ 2. ENKRIPSI ID UNTUK OUTBOUND LINKS
    $encoded_task_id_out = encode_id($id);
    $encoded_project_id_out = encode_id($project_id);

    $project_name = "Unknown Project";
    $proj_q = $conn->query("SELECT name FROM project_list WHERE id = $project_id LIMIT 1");
    if ($proj_q && $proj_q->num_rows > 0) {
        $project_name = $proj_q->fetch_assoc()['name'];
    }

    $creator = null;
    if (!empty($row['created_by'])) {
        $creator_q = $conn->query("SELECT firstname, lastname, avatar FROM users WHERE id = {$row['created_by']} LIMIT 1");
        if ($creator_q && $creator_q->num_rows > 0) {
            $creator = $creator_q->fetch_assoc();
        }
    }
    
    // Query untuk mengambil COMMENTS/PRODUCTIVITY
    $comments_qry = $conn->query("
        SELECT p.*, CONCAT(u.firstname, ' ', u.lastname) as uname, u.avatar 
        FROM user_productivity p 
        INNER JOIN users u ON u.id = p.user_id 
        WHERE p.task_id = $id 
        ORDER BY p.date_created DESC
    ");
    $comments_count = $comments_qry->num_rows;
    
    $statusArr = [
        0 => ['Pending',     '#9ca3af', '#f3f4f6', '#6b7280', '#d1d5db'],
        1 => ['Started',     '#3b82f6', '#eff6ff', '#1d4ed8', '#bfdbfe'],
        2 => ['On-Progress', '#6366f1', '#eef2ff', '#4338ca', '#c7d2fe'],
        3 => ['On-Hold',     '#f59e0b', '#fffbeb', '#b45309', '#fcd34d'],
        4 => ['Over Due',    '#ef4444', '#fef2f2', '#dc2626', '#fecaca'],
        5 => ['Done',        '#22c55e', '#f0fdf4', '#15803d', '#bbf7d0'],
    ];
    $status = $statusArr[$row['status']] ?? ['Unknown', 'dark'];
    $is_on_hold_overdue = ((int) $row['status'] === 3) && !empty($row['end_date']) && strtotime($row['end_date']) < strtotime(date('Y-m-d'));
?>
    
<div class="td-wrap">

    <div id="taskDescDrawer" class="desc-side-drawer">
        <div class="drawer-header">
            <h5 class="drawer-title"><i class="fa fa-align-left mr-2" style="color:#b45309;"></i> Full Description</h5>
            <button type="button" class="drawer-close-btn" onclick="closeDescDrawer()">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div class="drawer-body">
            <?php $cleaned_desc = clean_html_entities($row['description']); $raw = trim(strip_tags($cleaned_desc)); ?>
            <?= !empty($raw) ? $cleaned_desc : '<span class="text-muted">No description.</span>' ?>
        </div>
    </div>

  <!-- ════════════ LEFT ════════════ -->
  <div class="td-left">

    <h4 class="td-task-name"><?= htmlspecialchars($row['task']) ?></h4>

    <div class="mb-3">
      <span class="td-status-pill"
        style="background:<?= $status[2] ?>; color:<?= $status[3] ?>; border-color:<?= $status[4] ?>;">
        <span class="td-dot" style="background:<?= $status[1] ?>;"></span>
        <?= $status[0] ?>
      </span>

      <?php if ($is_on_hold_overdue): ?>
      <div class="alert alert-warning mt-3 mb-0 py-2 px-3" style="border-left:4px solid #b45309; font-size:0.9rem;">
        <i class="fa fa-exclamation-triangle mr-2"></i>
        This task is overdue, but it is still marked as On-Hold.
      </div>
      <?php endif; ?>
    </div>

    <hr class="td-hr">

    <!-- Project -->
    <div class="mb-3">
      <p class="td-lbl">Project</p>
      <span class="td-proj-chip"><?= htmlspecialchars($project_name) ?></span>
    </div>

    <?php if($login_type < 4): ?>
    <div class="td-grid2">

      <div>
        <p class="td-lbl">Created By</p>
        <?php if ($creator): ?>
        <div class="td-av-row">
          <img src="assets/uploads/<?= !empty($creator['avatar']) ? htmlspecialchars($creator['avatar']) : 'empty-placeholder.png' ?>"
               alt="" class="td-av" style="margin-left:0;">
          <span class="td-av-name"><?= ucwords($creator['firstname']) ?> <span class="user-lastname"><?= ucwords($creator['lastname']) ?></span></span>
        </div>
        <?php else: ?>
        <span class="td-none">Unknown</span>
        <?php endif; ?>
      </div>

      <div>
        <p class="td-lbl">Assignment User</p>
        <?php 
        $task_assigned_users = [];
        if (!empty($row['user_ids'])) {
            $uids = array_map('intval', explode(',', $row['user_ids']));
            if (!empty($uids)) {
                $uids_str = implode(',', $uids);
                $tuq = $conn->query("SELECT id, avatar, firstname, lastname FROM users WHERE id IN ($uids_str)");
                while ($u = $tuq->fetch_assoc()) { $task_assigned_users[] = $u; }
            }
        }
        ?>
        <?php if (!empty($task_assigned_users)): ?>
        <div class="td-av-stack">
          <?php foreach ($task_assigned_users as $au): ?>
          <img src="assets/uploads/<?= !empty($au['avatar']) ? htmlspecialchars($au['avatar']) : 'empty-placeholder.png' ?>"
               alt="" class="td-av"
               title="<?= ucwords($au['firstname'].' '.$au['lastname']) ?>">
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <span class="td-none" style="margin-top:4px;display:block;">No assignee</span>
        <?php endif; ?>
      </div>

      <div>
        <p class="td-lbl">Start Date</p>
        <div class="td-datebox">
          <i class="fa fa-calendar-o"></i>
          <?= date('F d, Y', strtotime($row['start_date'])) ?>
        </div>
      </div>

      <div>
        <p class="td-lbl">End Date</p>
        <div class="td-datebox">
          <i class="fa fa-calendar-check-o"></i>
          <?= date('F d, Y', strtotime($row['end_date'])) ?>
        </div>
      </div>

    </div>
    <?php endif; ?>

    <hr class="td-hr">

    <div class="mb-3">
      <p class="td-lbl">Description</p>
      <?php $cleaned_desc = clean_html_entities($row['description']); $raw = trim(strip_tags($cleaned_desc)); ?>
      
      <?php if (!empty($raw)): ?>
        <div style="font-size: 0.85rem; color: #1c1917; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
          <?= $cleaned_desc ?>
        </div>

        <div style="margin-top: 8px; text-align: right;">
          <button type="button" id="view-all-btn" onclick="toggleDescDrawer()" style="background: none; border: none; outline: none; color: #b45309; font-size: 0.8rem; font-weight: 600; cursor: pointer; padding: 0;">
            <i class="fa fa-chevron-left mr-1" style="font-size: 0.7rem;"></i> View All
          </button>
        </div>
      <?php else: ?>
        <span style="color:var(--td-text-muted);font-style:italic;font-size:0.84rem;">No description provided.</span>
      <?php endif; ?>
    </div>
            
    <!-- Pillar + Platform -->
    <div class="td-grid2" style="gap:0.85rem; margin-bottom:1rem;">
      <div>
        <p class="td-lbl">Content Pillar</p>
        <?php $pillars = array_filter(array_map('trim', explode(',', $row['content_pillar']))); ?>
        <?php if (!empty($pillars)): ?>
        <div class="td-chips">
          <?php foreach ($pillars as $p): ?>
          <span class="td-chip td-chip-p"><span class="td-chip-dot"></span><?= ucwords(htmlspecialchars($p)) ?></span>
          <?php endforeach; ?>
        </div>
        <?php else: ?><span class="td-none">—</span><?php endif; ?>
      </div>
      <div>
        <p class="td-lbl">Platform</p>
        <?php $platforms = array_filter(array_map('trim', explode(',', $row['platform']))); ?>
        <?php if (!empty($platforms)): ?>
        <div class="td-chips">
          <?php foreach ($platforms as $plat): ?>
          <span class="td-chip td-chip-pl"><span class="td-chip-dot"></span><?= htmlspecialchars($plat) ?></span>
          <?php endforeach; ?>
        </div>
        <?php else: ?><span class="td-none">—</span><?php endif; ?>
      </div>
    </div>

    <!-- Reference Links -->
    <div>
      <p class="td-lbl">Reference Links</p>
      <?php $links = array_filter(array_map('trim', explode("\n", $row['reference_links']))); ?>
      <?php if (!empty($links)): ?>
      <ul class="td-links">
        <?php foreach ($links as $link): $safe = htmlspecialchars($link); ?>
        <li>
          <i class="fa fa-link" style="color:var(--td-text-muted);font-size:0.7rem;flex-shrink:0;"></i>
          <a href="<?= $safe ?>" target="_blank" rel="noopener"><?= $safe ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <p class="td-none" style="margin-top:4px;">No links</p>
      <?php endif; ?>
    </div>

  </div><!-- /.td-left -->


  <!-- ════════════ RIGHT ════════════ -->
  <div class="td-right">

    <div class="td-cmts-hd">
      <h6 class="td-cmts-title">
        Comments <span class="td-cnt-badge"><?= $comments_count ?></span>
      </h6>
    </div>

    <div class="td-cmts-scroll" id="comments-container">
      <?php if ($comments_count > 0): ?>
        <?php while($comment = $comments_qry->fetch_assoc()): ?>
        <?php $encoded_comment_id = encode_id($comment['id']); ?>
        <div class="td-cmt-card">
          <div class="td-cmt-top">
            <img class="td-cmt-av"
                 src="assets/uploads/<?= !empty($comment['avatar']) ? htmlspecialchars($comment['avatar']) : 'empty-placeholder.png' ?>"
                 alt="">
            <div class="td-cmt-meta">
              <span class="td-cmt-name"><?= ucwords(htmlspecialchars($comment['uname'])) ?></span>
              <span class="td-cmt-time"><?= date('M d, Y h:i A', strtotime($comment['date_created'])) ?></span>
            </div>
            <?php if (isset($_SESSION['login_id']) && $_SESSION['login_id'] == $comment['user_id']): ?>
            <div class="td-cmt-actions dropdown">
              <button class="btn btn-sm" type="button" data-toggle="dropdown">
                <i class="fa fa-ellipsis-v"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item manage_progress_modal" href="javascript:void(0)"
                   data-id="<?= $encoded_comment_id ?>"
                   data-task="<?= htmlspecialchars($row['task']) ?>"
                   data-project-id="<?= $encoded_project_id_out ?>">
                  <i class="fa fa-pencil mr-1"></i> Edit
                </a>
                <a class="dropdown-item text-danger delete_progress_modal" href="javascript:void(0)"
                   data-id="<?= $comment['id'] ?>">
                  <i class="fa fa-trash mr-1"></i> Delete
                </a>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php if(!empty($comment['subject'])): ?>
          <div class="td-cmt-subj"><?= htmlspecialchars($comment['subject']) ?></div>
          <?php endif; ?>
          <div class="td-cmt-body"><?= clean_html_entities($comment['comment']) ?></div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="td-empty">
          <i class="fa fa-commenting-o"></i>
          <p>No comments yet.<br>Be the first to add one.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Add Comment -->
    <a href="#" class="td-add-btn" id="new_productivity"
       data-pid="<?= $encoded_project_id_out ?>"
       data-tid="<?= $encoded_task_id_out ?>"
       data-task="<?= htmlspecialchars($row['task']) ?>">
      <i class="fa fa-plus"></i> Add Comment
    </a>

  </div><!-- /.td-right -->

</div><!-- /.td-wrap -->

<!-- ════════════ FOOTER ════════════ -->
<div class="modal-footer td-footer custom-footer">
  <?php if($login_type < 4): ?>
  <button class="btn td-btn-edit"
          onclick="editTaskKanban('<?= $encoded_task_id_out ?>', '<?= $encoded_project_id_out ?>')">
    <i class="fa fa-pencil"></i> Edit Task
  </button>
  <button type="button" class="btn td-btn-delete"
          onclick="confirmDeleteKanban(<?= $id ?>)">
    <i class="fa fa-trash"></i> Delete
  </button>
  <?php endif; ?>
  <button type="button" class="btn td-btn-close" data-dismiss="modal">Close</button>
</div>

    <script>
        // 1. Fungsi Toggle untuk tombol "View All"
        function toggleDescDrawer() {
            const drawer = document.getElementById('taskDescDrawer');
            const btn = document.getElementById('view-all-btn');
            
            // Targetkan frame modal utama
            const modalContent = document.querySelector('#uni_modal .modal-content'); 
            
            if (drawer.classList.contains('open')) {
                drawer.classList.remove('open'); // Tutup laci
                modalContent.classList.remove('modal-shift-right'); // Kembalikan frame utama ke tengah
                btn.innerHTML = '<i class="fa fa-chevron-left mr-1" style="font-size: 0.7rem;"></i> View All';
            } else {
                drawer.classList.add('open'); // Buka laci ke kiri
                modalContent.classList.add('modal-shift-right'); // Geser frame utama ke kanan
                btn.innerHTML = '<i class="fa fa-chevron-right mr-1" style="font-size: 0.7rem;"></i> Hide View';
            }
        }

        // 2. Fungsi khusus untuk tombol "X" di dalam laci
        function closeDescDrawer() {
            const drawer = document.getElementById('taskDescDrawer');
            const btn = document.getElementById('view-all-btn');
            const modalContent = document.querySelector('#uni_modal .modal-content');
            
            drawer.classList.remove('open'); // Tutup laci
            if (modalContent) {
                modalContent.classList.remove('modal-shift-right'); // Kembalikan frame utama ke tengah
            }
            
            if (btn) {
                btn.innerHTML = '<i class="fa fa-chevron-left mr-1" style="font-size: 0.7rem;"></i> View All';
            }
        }

        // Handle task deletion (called from confirmDeleteKanban via _conf)
        function delete_task(id) {
            if (typeof start_load !== 'undefined') { start_load(); }
            $.ajax({
                url: 'ajax.php?action=delete_task',
                method: 'POST',
                data: { id: id },
                success: function(resp) {
                    if (typeof end_load !== 'undefined') { end_load(); }
                    if(resp == 1){
                        alert_toast("Task berhasil dihapus", 'success');
                        setTimeout(() => {
                            $('#uni_modal').modal('hide');
                            setTimeout(() => location.reload(), 500);
                        }, 1000);
                    } else {
                        alert_toast("Gagal menghapus task", 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    if (typeof end_load !== 'undefined') { end_load(); }
                    alert_toast("Error: " + error, 'danger');
                }
            });
        }

        function delete_progress($id){
            if (typeof start_load !== 'undefined') { start_load(); }
            // ID yang dikirim adalah ID numerik
            $.ajax({
                url:'ajax.php?action=delete_progress',
                method:'POST',
                data:{id:$id},
                success:function(resp){
                    if(resp==1){
                        alert_toast("Data successfully deleted",'success')
                        setTimeout(function(){ location.reload() },1500)
                    }
                }
            })
        }

        // 💡 1. EDIT TASK JS HANDLER: Menerima ID terenkripsi dan memanggil manage_task.php
        function editTaskKanban(encodedTaskId, encodedProjectId) {
            $('#uni_modal').modal('hide'); 
            setTimeout(function(){
                // manage_task.php harus memiliki logic decode untuk 'id' dan 'pid'
                uni_modal("<i class='fa fa-edit'></i> Edit Task",
                    `manage_task.php?id=${encodedTaskId}&pid=${encodedProjectId}`,
                    "mid-large");
            }, 300);
        }

        function confirmDeleteKanban(id) {
            $('#uni_modal').modal('hide');
            setTimeout(() => {
                if (typeof _conf === 'function') {
                    _conf("Are you sure to delete this task permanently?", "delete_task", [id]);
                } else if (typeof window.deleteKanbanTaskFromModal === 'function') {
                    window.deleteKanbanTaskFromModal(id); 
                } else {
                    console.error("Konfirmasi delete global tidak ditemukan.");
                }
            }, 400);
        }
        
        // 💡 2. EDIT PROGRESS JS HANDLER: Menerima ID terenkripsi dan memanggil manage_progress.php
        $(document).on('click', '.manage_progress_modal', function() {
            // ID Progress dan Project sudah terenkripsi dari PHP
            const encodedProgressId = $(this).data('id');
            const encodedProjectId = $(this).data('project-id'); 
            
            $('#uni_modal').modal('hide'); 
            setTimeout(() => {
                uni_modal(
                    "<i class='fa fa-edit'></i> Edit Progress", 
                    `manage_progress.php?pid=${encodedProjectId}&id=${encodedProgressId}`, 
                    'mid-large'
                );
            }, 300);
        });

        // 💡 3. ADD COMMENT JS HANDLER (FIXED): HANYA MEMBUKA MODAL
            $(document).on('click', '#new_productivity', function(e){
                e.preventDefault();
                const taskName = $(this).attr('data-task');
                const encodedPid = $(this).attr('data-pid');
                const encodedTid = $(this).attr('data-tid');

                $('#uni_modal').modal('hide');
                setTimeout(function() {
                    uni_modal(
                        "<i class='fa fa-plus'></i> New Comment for: " + taskName,
                        `manage_progress.php?pid=${encodedPid}&tid=${encodedTid}`,
                        "mid-large"
                    );
                }, 300);
            });

        // Handler untuk Delete Progress/Comment
        $(document).on('click', '.delete_progress_modal', function() {
            const progressId = $(this).data('id'); // ID numerik
            
            $('#uni_modal').modal('hide'); 
            setTimeout(() => {
                if (typeof _conf === 'function') {
                    _conf("Are you sure to delete this progress/comment?", "delete_progress", [progressId]);
                } else {
                    console.error("_conf function not found for deleting progress.");
                }
            }, 400);
        });

        // ❌ MENGHAPUS BLOK KODE YANG TIDAK PERLU / DUPLIKAT DI BAGIAN AKHIR
        // Semua handler duplikat yang ada di file Anda di bagian bawah sudah dihapus.
        
        $(document).ready(function() {
            // Sembunyikan footer default dan tampilkan footer kustom
            $('#uni_modal .modal-footer').hide(); 
            $('.custom-footer').show();
            // Atur ukuran modal menjadi lebih besar untuk tata letak 2 kolom
            $('#uni_modal .modal-dialog').removeClass('modal-md modal-lg').addClass("modal-xl");
            // Mengurangi min-height karena konten tidak terbungkus card
            $('#uni_modal .modal-content').css("min-height", "70vh"); 
        });
    </script>
    
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    /* ── EFEK LACI (DRAWER) KE KIRI ── */
    #uni_modal .modal-content { overflow: visible !important; }
    
    .td-wrap { position: relative; } /* Penting agar laci menempel pas di kiri modal */

    .desc-side-drawer {
        position: absolute;
        top: 0;
        left: 0; /* Bersembunyi pas di ujung kiri dalam frame utama */
        width: 320px; 
        height: 100%;
        background: #ffffff;
        border: 1px solid #e8e2d9;
        border-right: none; 
        border-radius: 14px 0 0 14px; 
        box-shadow: -10px 0 30px rgba(0,0,0,0.12);
        
        z-index: -1; /* Sembunyi di balik td-wrap */
        opacity: 0;
        visibility: hidden;
        
        transform: translateX(0); /* Posisi diam */
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); 
        display: flex;
        flex-direction: column;
    }

    .desc-side-drawer.open {
        left: -17px; 
        opacity: 1;
        visibility: visible;
        transform: translateX(-100%);
    }

    .drawer-header {
        padding: 1rem 1.2rem; 
        border-bottom: 1px solid #e8e2d9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .drawer-title { font-weight: 700; font-size: 0.95rem; color: #1c1917; margin: 0; }
    
    .drawer-close-btn {
        background: #fdfcfb; border: 1px solid #e8e2d9; color: #6b6560;
        border-radius: 6px; padding: 4px 10px; cursor: pointer; transition: 0.2s;
    }
    .drawer-close-btn:hover { background: #f0ece6; color: #dc2626; }

    .drawer-body {
        padding: 1.2rem; 
        overflow-y: auto;
        flex: 1;
        font-size: 0.88rem;
        line-height: 1.7;
        color: #1c1917;
    }

    /* ── ANIMASI GESER FRAME UTAMA ── */
    #uni_modal .modal-content {
        /* Tambahkan transisi agar pergeserannya mulus, menyamai kecepatan laci */
        transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    }

    /* Class penanda saat laci terbuka */
    .modal-shift-right {
        /* 160px adalah setengah dari lebar laci (320px) untuk menyeimbangkan titik tengah */
        transform: translateX(160px) !important;
    }

    /* Opsional: Matikan efek geser di layar kecil/HP agar tidak memotong layar */
    @media (max-width: 991px) {
        .modal-shift-right { transform: none !important; }

        /* Override drawer jadi slide dari bawah ke atas */
        .desc-side-drawer {
            top: auto !important;
            bottom: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 90% !important;
            border-radius: 20px 20px 0 0 !important;
            border: none !important;
            border-top: 1px solid #e8e2d9 !important;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.18) !important;
            transform: translateY(100%) !important;
            z-index: 1050 !important;
            position: fixed !important;
            opacity: 1 !important;
            visibility: hidden !important;
            transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1), visibility 0.4s !important;
        }

        .desc-side-drawer.open {
            transform: translateY(0) !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    }
    
    .drawer-body::-webkit-scrollbar { width: 5px; }
    .drawer-body::-webkit-scrollbar-thumb { background: #d1c9be; border-radius: 4px; }

    /* ── MODAL SHELL ─────────────────────────────────── */
    #uni_modal .modal-dialog {
        max-width: 920px !important;
        width: 96% !important;
        margin: 1.5rem auto;
    }
    #uni_modal .modal-content {
        background: #ffffff !important;
        border: 1px solid #e8e2d9 !important;
        border-radius: 14px !important;
        overflow: visible !important;
        position: relative !important;
        box-shadow: 0 8px 40px rgba(0,0,0,0.13) !important;
        min-height: 70vh;
        font-family: 'Inter', -apple-system, sans-serif;
        position: relative !important;
    }
    /* Lepaskan kurungan posisi dari elemen di dalamnya */
    #uni_modal .modal-body,
    .td-wrap { 
        position: static !important; 
    }
    #uni_modal .modal-content,
    #uni_modal .modal-body { 
        overflow: visible !important; 
        transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    }
    #uni_modal .modal-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e8e2d9 !important;
        padding: 1rem 1.5rem !important;
        border-top-left-radius: 14px !important;
        border-top-right-radius: 14px !important;
    }
    #uni_modal .modal-header .modal-title {
        color: #1c1917 !important;
        font-family: 'Inter', -apple-system, sans-serif !important;
        font-weight: 600 !important;
        font-size: 0.95rem !important;
    }
    #uni_modal .modal-header .close {
        color: #a09990 !important;
        opacity: 1 !important;
    }

    /* ── WRAPPER ─────────────────────────────────────── */
    .td-wrap {
        display: flex;
        flex-direction: row;
        height: 65vh; /* ⬅️ UBAH: Kunci tingginya di sini */
        font-family: 'Inter', -apple-system, sans-serif;
        color: #1c1917;
        background: #ffffff;
        position: relative !important;
        z-index: 1;
    }

    /* ── LEFT PANEL ──────────────────────────────────── */
    .td-left {
        flex: 1 1 58%;
        padding: 0.5rem 2rem 1.5rem;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #e8e2d9 transparent;
    }
    .td-left::-webkit-scrollbar { width: 4px; }
    .td-left::-webkit-scrollbar-thumb { background: #e8e2d9; border-radius: 4px; }

    /* ── RIGHT PANEL ─────────────────────────────────── */
    .td-right {
        flex: 0 0 42%;
        max-width: 42%;
        padding: 1.5rem;
        background: #f5f3f0;
        border-left: 1px solid #e8e2d9;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* ── TITLE ───────────────────────────────────────── */
    .td-task-name {
        font-size: 1.45rem;
        font-weight: 700;
        color: #1c1917;
        line-height: 1.3;
        margin: 0 0 0.75rem;
        letter-spacing: -0.025em;
    }

    /* ── STATUS PILL ─────────────────────────────────── */
    .td-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        border: 1px solid;
    }
    .td-dot { width: 7px; height: 7px; border-radius: 50%; }

    /* ── DIVIDER ─────────────────────────────────────── */
    .td-hr { border: none; border-top: 1px solid #e8e2d9; margin: 1.25rem 0; }

    /* ── LABEL ───────────────────────────────────────── */
    .td-lbl {
        font-size: 0.67rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #9c9289;
        margin: 0 0 0.45rem;
    }

    /* ── PROJECT CHIP ────────────────────────────────── */
    .td-proj-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 1.4rem;
        font-weight: 800;
        color: #1c1917;
    }

    /* ── META GRID ───────────────────────────────────── */
    .td-grid2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.1rem 1.5rem;
        margin: 1.1rem 0;
    }

    /* ── AVATAR ──────────────────────────────────────── */
    .td-av-row { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
    .td-av-stack { display: flex; align-items: center; margin-top: 4px; }
    .td-av {
        width: 34px; height: 34px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #ffffff;
        box-shadow: 0 0 0 1.5px #e8e2d9;
        margin-left: -8px;
        flex-shrink: 0;
    }
    .td-av:first-child { margin-left: 0; }
    .td-av-name { font-size: 0.82rem; font-weight: 500; color: #1c1917; line-height: 1.3; }

    /* ── DATE BOX ────────────────────────────────────── */
    .td-datebox {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #ffffff;
        border: 1.5px solid #e8e2d9;
        border-radius: 7px;
        padding: 7px 13px;
        font-size: 0.84rem;
        font-weight: 500;
        color: #1c1917;
        margin-top: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 2px 8px rgba(0,0,0,0.05);
    }
    .td-datebox i { color: #a09990; font-size: 0.78rem; }

    /* ── DESCRIPTION PREVIEW ─────────────────────────── */
    .td-desc-wrap {
        background: #ffffff;
        border: 1.5px solid #e8e2d9;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 2px 8px rgba(0,0,0,0.05);
    }
    .td-desc-preview {
        padding: 0.85rem 1.1rem 0;
        font-size: 0.875rem;
        color: #1c1917;
        line-height: 1.65;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        max-height: calc(1.65em * 3);
    }
    .td-desc-preview.no-desc {
        color: #a09990;
        font-style: italic;
        font-size: 0.84rem;
        padding-bottom: 0.85rem;
        display: block;
    }
    .td-desc-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0.4rem 0.8rem 0.5rem;
        border-top: 1px solid #f0ece6;
        margin-top: 0.6rem;
    }
    .td-desc-readmore {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #b45309;
        background: #fff7ed;
        border: 1px solid #fcd34d;
        border-radius: 20px;
        padding: 3px 11px;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.15s, border-color 0.15s;
        user-select: none;
    }
    .td-desc-readmore:hover { background: #ffedd5; border-color: #f59e0b; color: #b45309; text-decoration: none; }
    .td-desc-readmore i { font-size: 0.68rem; }
    .td-desc-wrap.no-content .td-desc-footer { display: none; }

    /* ── CHIPS ───────────────────────────────────────── */
    .td-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
    .td-chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 11px;
        border-radius: 20px;
        font-size: 0.74rem;
        font-weight: 600;
        border: 1px solid;
    }
    .td-chip-dot { width: 6px; height: 6px; border-radius: 50%; }
    .td-chip-p   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .td-chip-p   .td-chip-dot { background: #3b82f6; }
    .td-chip-pl  { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .td-chip-pl  .td-chip-dot { background: #10b981; }
    .td-none { font-size: 0.84rem; color: #a09990; }

    /* ── LINKS ───────────────────────────────────────── */
    .td-links { list-style: none; padding: 0; margin: 5px 0 0; }
    .td-links li {
        display: flex; align-items: center; gap: 8px;
        padding: 5px 0;
        border-bottom: 1px solid #f0ece6;
        font-size: 0.82rem;
    }
    .td-links li:last-child { border: none; }
    .td-links a { color: #2563eb; text-decoration: none; word-break: break-all; }
    .td-links a:hover { text-decoration: underline; }

    /* ── COMMENTS HEADER ─────────────────────────────── */
    .td-cmts-hd {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1rem; flex-shrink: 0;
    }
    .td-cmts-title {
        font-size: 0.9rem; font-weight: 700;
        color: #1c1917; margin: 0;
        display: flex; align-items: center; gap: 7px;
    }
    .td-cnt-badge {
        background: #ffffff;
        border: 1px solid #e8e2d9;
        color: #6b6560;
        font-size: 0.68rem; font-weight: 700;
        padding: 2px 8px; border-radius: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 2px 8px rgba(0,0,0,0.05);
    }

    /* ── COMMENTS SCROLL ─────────────────────────────── */
    .td-cmts-scroll {
        flex: 1; 
        overflow-y: auto; 
        padding-right: 4px;
        scrollbar-width: thin; 
        scrollbar-color: #e8e2d9 transparent;
        max-height: calc(65vh - 120px);
    }
    .td-cmts-scroll::-webkit-scrollbar { width: 4px; }
    .td-cmts-scroll::-webkit-scrollbar-thumb { background: #e8e2d9; border-radius: 4px; }

    /* ── COMMENT CARD ────────────────────────────────── */
    .td-cmt-card {
        background: #ffffff;
        border: 1.5px solid #e8e2d9;
        border-radius: 10px;
        padding: 0.9rem 1rem;
        margin-bottom: 0.6rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 2px 8px rgba(0,0,0,0.05);
    }
    .td-cmt-card:hover { border-color: #d1c9be; }
    .td-cmt-top { display: flex; align-items: flex-start; gap: 9px; margin-bottom: 0.55rem; }
    .td-cmt-av {
        width: 32px; height: 32px; border-radius: 50%;
        object-fit: cover; border: 2px solid #e8e2d9; flex-shrink: 0;
    }
    .td-cmt-meta { flex: 1; min-width: 0; }
    .td-cmt-name {
        font-size: 0.83rem; font-weight: 700; color: #1c1917;
        display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .td-cmt-time { font-size: 0.7rem; color: #a09990; }
    .td-cmt-subj { font-size: 0.8rem; font-weight: 700; color: #1c1917; margin-bottom: 3px; }
    .td-cmt-body { font-size: 0.82rem; color: #6b6560; line-height: 1.55; }

    .td-cmt-actions .btn {
        padding: 0 3px; background: transparent !important; border: none !important;
        box-shadow: none !important; color: #a09990 !important; line-height: 1.2;
    }
    .td-cmt-actions .btn:hover { color: #6b6560 !important; }
    .td-cmt-actions .dropdown-menu {
        background: #ffffff !important;
        border: 1.5px solid #e8e2d9 !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.09) !important;
        border-radius: 7px !important;
        min-width: 130px; padding: 4px 0;
    }
    .td-cmt-actions .dropdown-item { font-size: 0.81rem !important; color: #1c1917 !important; padding: 6px 14px !important; }
    .td-cmt-actions .dropdown-item:hover { background: #f9f8f7 !important; }
    .td-cmt-actions .dropdown-item.text-danger { color: #dc2626 !important; }

    /* ── EMPTY STATE ─────────────────────────────────── */
    .td-empty { text-align: center; padding: 2.5rem 1rem; color: #a09990; }
    .td-empty i { font-size: 1.8rem; display: block; margin-bottom: 0.6rem; opacity: 0.35; }
    .td-empty p { font-size: 0.82rem; margin: 0; }

    /* ── ADD COMMENT ─────────────────────────────────── */
    .td-add-btn {
        display: flex; align-items: center; justify-content: center; gap: 7px;
        width: 100%;
        background: #fff7ed;
        border: 1.5px dashed #fbbf24;
        border-radius: 10px;
        color: #b45309;
        font-size: 0.84rem; font-weight: 600;
        padding: 10px;
        text-decoration: none;
        transition: background 0.15s, border-color 0.15s;
        margin-top: 0.75rem;
        flex-shrink: 0; cursor: pointer;
    }
    .td-add-btn:hover { background: #ffedd5; border-color: #f59e0b; color: #b45309; text-decoration: none; }

    /* ── FOOTER ──────────────────────────────────────── */
    .td-footer {
        display: flex !important; align-items: center !important; gap: 0.5rem;
        padding: 0.9rem 1.5rem !important;
        background: #ffffff !important;
        border-top: 1px solid #e8e2d9 !important;
        border-bottom-left-radius: 14px !important;
        border-bottom-right-radius: 14px !important;
    }
    .td-btn-edit {
        display: inline-flex; align-items: center; gap: 6px;
        background: #78350f; color: #ffffff !important; border: none;
        border-radius: 7px; font-size: 0.82rem; font-weight: 600;
        padding: 7px 16px; transition: background 0.15s;
        font-family: 'Inter', -apple-system, sans-serif;
    }
    .td-btn-edit:hover { background: #92400e; color: #ffffff; }
    .td-btn-delete {
        display: inline-flex; align-items: center; gap: 6px;
        background: #fef2f2; color: #dc2626 !important;
        border: 1.5px solid #fecaca; border-radius: 7px;
        font-size: 0.82rem; font-weight: 600; padding: 7px 16px;
        transition: all 0.15s;
        font-family: 'Inter', -apple-system, sans-serif;
    }
    .td-btn-delete:hover { background: #fee2e2; border-color: #fca5a5; }
    .td-btn-close {
        display: inline-flex; align-items: center; gap: 6px;
        margin-left: auto; background: #ffffff;
        color: #6b6560 !important;
        border: 1.5px solid #e8e2d9; border-radius: 7px;
        font-size: 0.82rem; font-weight: 600; padding: 7px 16px;
        transition: all 0.15s;
        font-family: 'Inter', -apple-system, sans-serif;
    }
    .td-btn-close:hover { background: #f9f8f7; color: #1c1917 !important; }

    /* ── DESCRIPTION FULL MODAL ──────────────────────── */
    #descModal .modal-dialog { max-width: 700px !important; width: 95% !important; }
    #descModal .modal-content {
        background: #ffffff !important;
        border: 1px solid #e8e2d9 !important;
        border-radius: 14px !important;
        box-shadow: 0 12px 50px rgba(0,0,0,0.18) !important;
        font-family: 'Inter', -apple-system, sans-serif;
    }
    #descModal .modal-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e8e2d9 !important;
        padding: 1rem 1.5rem !important;
        display: flex; align-items: center; gap: 10px;
    }
    #descModal .modal-title {
        font-family: 'Inter', -apple-system, sans-serif !important;
        font-size: 0.92rem !important;
        font-weight: 700 !important;
        color: #1c1917 !important;
        display: flex; align-items: center; gap: 8px;
    }
    #descModal .modal-title .desc-modal-icon {
        width: 28px; height: 28px;
        background: #fff7ed;
        border: 1px solid #fcd34d;
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    #descModal .modal-title .desc-modal-icon i { color: #b45309; font-size: 0.75rem; }
    #descModal .modal-header .close { color: #a09990 !important; opacity: 1 !important; }
    #descModal .modal-body {
        padding: 1.5rem !important;
        max-height: 65vh;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #e8e2d9 transparent;
        font-size: 0.9rem;
        color: #1c1917;
        line-height: 1.75;
    }
    #descModal .modal-body::-webkit-scrollbar { width: 4px; }
    #descModal .modal-body::-webkit-scrollbar-thumb { background: #e8e2d9; border-radius: 4px; }
    #descModal .modal-body img { max-width: 100%; border-radius: 8px; margin: 0.5rem 0; }
    #descModal .modal-body p { margin: 0 0 0.75rem; }
    #descModal .modal-body p:last-child { margin: 0; }
    #descModal .modal-footer {
        background: #f9f8f7 !important;
        border-top: 1px solid #e8e2d9 !important;
        padding: 0.85rem 1.5rem !important;
        border-radius: 0 0 14px 14px;
    }
    .desc-modal-close-btn {
        background: #ffffff;
        border: 1.5px solid #e8e2d9;
        color: #6b6560;
        font-size: 0.82rem; font-weight: 600;
        padding: 7px 18px; border-radius: 7px;
        font-family: 'Inter', -apple-system, sans-serif;
        transition: all 0.15s;
    }
    .desc-modal-close-btn:hover { background: #f3f0eb; color: #1c1917; }

    /* ── RESPONSIVE ──────────────────────────────────── */
    @media (max-width: 991px) {
        #uni_modal .modal-dialog { max-width: 98% !important; margin: 0.5rem auto; }
        .td-wrap { flex-direction: column; height: auto; } /* ⬅️ TAMBAHKAN: height auto */
        .td-left { padding: 1.5rem 1.25rem; border-bottom: 1px solid #e8e2d9; }
        .td-right { flex: none; max-width: 100%; border-left: none; max-height: none; } /* ⬅️ UBAH: max-height none */
        .td-cmts-scroll { max-height: 350px; } /* ⬅️ TAMBAHKAN: Batas tinggi komentar khusus di HP */
        .td-task-name { font-size: 1.2rem; }
    }
    @media (max-width: 490px) {
        #uni_modal .modal-dialog { max-width: 100% !important; margin: 0; }
        #uni_modal .modal-content { border-radius: 0 !important; }
        .td-left { padding: 1.1rem; }
        .td-right { padding: 1rem 1.1rem; }
        .td-grid2 { grid-template-columns: 1fr; gap: 0.75rem; }
        .td-grid2 div:nth-child(3),
        .td-grid2 div:nth-child(4) { display: inline-block; width: 48%; vertical-align: top; }
        .td-grid2 div:nth-child(3) { margin-right: 4%; }
        .td-grid2 div:nth-child(3) .td-datebox,
        .td-grid2 div:nth-child(4) .td-datebox { font-size: 0.75rem; padding: 6px 8px; width: 100%; }
        .td-task-name { font-size: 1.1rem; }
        .td-footer { flex-wrap: wrap; gap: 0.4rem; justify-content: center; }
        .td-btn-close { margin-left: 0; width: 100%; justify-content: center; }
        #descModal .modal-dialog { max-width: 100% !important; margin: 0; }
        #descModal .modal-content { border-radius: 0 !important; }
    }
    </style>
    
    <?php
} else {
    echo "Data task tidak ditemukan.";
}
?>