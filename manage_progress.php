<?php 
include 'db_connect.php';

// ========================================
// AUTO-UPDATE OVERDUE STATUS
// ========================================
$conn->query("
    UPDATE task_list 
    SET status = 4 
    WHERE end_date < CURDATE() 
    AND status NOT IN (3,5)
");

// --- 1. DECODE IDs YANG MASUK DARI URL ---

// a) Decode Progress ID (ID progress saat mode Edit)
$encoded_progress_id = $_GET['id'] ?? null;
$progress_id_decoded = null; 
if ($encoded_progress_id) {
    // Asumsi decode_id() tersedia dari db_connect.php
    $decoded = decode_id($encoded_progress_id);
    if (is_numeric($decoded) && $decoded > 0) {
        $progress_id_decoded = $decoded;
    }
}

// b) Decode Project ID (ID proyek, digunakan untuk memfilter daftar task)
$encoded_project_id = $_GET['pid'] ?? null;
$project_id_decoded = null;
if ($encoded_project_id) {
    $decoded = decode_id($encoded_project_id);
    if (is_numeric($decoded) && $decoded > 0) {
        $project_id_decoded = $decoded;
    }
}

// c) Decode Task ID (ID task, digunakan jika form dibuka dari detail task)
$encoded_task_id = $_GET['tid'] ?? null;
$task_id_decoded = null;
if ($encoded_task_id) {
    $decoded = decode_id($encoded_task_id);
    if (is_numeric($decoded) && $decoded > 0) {
        $task_id_decoded = $decoded;
    }
}


// LOGIKA MODE EDIT
if($progress_id_decoded){
    // Mengambil data progress menggunakan ID numerik
    $qry = $conn->query("SELECT * FROM user_productivity where id = ".$progress_id_decoded);
    
    if($qry->num_rows > 0){
        $data = $qry->fetch_array();
        foreach($data as $k => $v){
            $$k = $v;
        }
        
        // Jika mode Edit, ambil task_id dan project_id dari DB
        $task_id_decoded = $task_id; 
        $project_id_decoded = $project_id; 
    } else {
        // Jika ID didekode tapi data tidak ada
        $progress_id_decoded = null;
    }
}

// --- 3. Set Final Form Values (ID numerik) ---
$form_progress_id = $progress_id_decoded ?? '';
$form_project_id = $project_id_decoded ?? '';
$form_task_id = $task_id_decoded ?? '';


// Jika project ID belum ditemukan (misalnya, diakses tanpa PID valid), hentikan.
if (empty($form_project_id)) {
    echo "<div class='alert alert-danger p-3 text-center'>Project ID tidak valid.</div>";
    exit;
}
?>

<div class="container-fluid">
    <form action="" id="manage-progress">
        <input type="hidden" name="id" value="<?php echo $form_progress_id ?>">
        <input type="hidden" name="project_id" value="<?php echo $form_project_id ?>">
        
        <input type="hidden" name="date" id="progress_date" value="<?php echo isset($date) ? date("Y-m-d",strtotime($date)) : '' ?>">
        
        <input type="hidden" name="start_time" id="progress_start_time" value="<?php echo isset($start_time) ? date("H:i",strtotime("2020-01-01 ".$start_time)) : '' ?>">
        
        <input type="hidden" name="end_time" id="progress_end_time" value="<?php echo isset($end_time) ? date("H:i",strtotime("2020-01-01 ".$end_time)) : '' ?>">

        <div class="col-lg-12">
            <div class="row">
                <div class="col-md-12">
                    <?php if(empty($_GET['tid'])): ?>
                     <div class="form-group">
                      <label for="" class="control-label">Task Name</label>
                      <select class="form-control form-control-sm select2" name="task_id" required>
                        <option></option>
                        <?php 
                        // Menggunakan $form_project_id (ID numerik) untuk memfilter task
                        $tasks = $conn->query("SELECT * FROM task_list where project_id = {$form_project_id} order by task asc ");
                        while($row= $tasks->fetch_assoc()):
                        ?>
                        <option value="<?php echo $row['id'] ?>" <?php echo isset($task_id) && $task_id == $row['id'] ? "selected" : '' ?>><?php echo ucwords($row['task']) ?></option>
                        <?php endwhile; ?>
                      </select>
                     </div>
                    <?php else: ?>
                    <input type="hidden" name="task_id" value="<?php echo $form_task_id ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="">Subject</label>
                        <input type="text" class="form-control form-control-sm" name="subject" value="<?php echo isset($subject) ? $subject : '' ?>" required>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="">Comment</label>
                        <textarea name="comment" id="progress_comment" cols="30" rows="10" class="summernote form-control" required="">
                            <?php echo isset($comment) ? $comment : '' ?>
                        </textarea>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-right">
                <button class="btn btn-primary mr-2">Save</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
</div>

<script>
    function initializeSummernote() {
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
                [ 'insert', [ 'picture', 'link' ] ],
                [ 'view', [ 'undo', 'redo', 'fullscreen', 'codeview', 'help' ] ]
            ],
            callbacks: {
                onImageUpload: function(files) {
                    uploadImageToServer(files[0], this);
                },
                onImagePaste: function(images) {
                    uploadImageToServer(images[0], this);
                }
            }
        });
    }

    function uploadImageToServer(file, editorContext) {
        // Validate: images only, max 5 MB
        if (!file.type.match(/^image\//)) {
            alert('Only image files are allowed.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Image size must be under 5 MB.');
            return;
        }

        var formData = new FormData();
        formData.append('image', file);

        // Show a small loading indicator inside the editor
        var $editor = $(editorContext).closest('.note-editor');
        $editor.append('<div id="img-upload-overlay" style="position:absolute;inset:0;background:rgba(255,247,237,0.75);display:flex;align-items:center;justify-content:center;z-index:999;border-radius:8px;font-size:0.82rem;color:#b45309;font-weight:600;gap:8px;"><i class="fa fa-spinner fa-spin"></i> Uploading image…</div>');

        $.ajax({
            url: 'upload_image.php',
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function(resp) {
                $('#img-upload-overlay').remove();
                try {
                    var result = typeof resp === 'string' ? JSON.parse(resp) : resp;
                    if (result.success && result.url) {
                        $(editorContext).summernote('insertImage', result.url, function($img) {
                            $img.css({ 'max-width': '100%', 'border-radius': '6px', 'margin': '4px 0' });
                        });
                    } else {
                        alert('Image upload failed: ' + (result.error || 'Unknown error'));
                    }
                } catch(e) {
                    alert('Image upload failed: Invalid server response.');
                }
            },
            error: function(xhr) {
                $('#img-upload-overlay').remove();
                alert('Image upload failed. Server error: ' + xhr.status);
            }
        });
    }

    $(document).ready(function(){
        initializeSummernote(); 
        
        $('.select2').select2({
            placeholder:"Please select here",
            width: "100%",
            dropdownParent: $('#uni_modal') 
        });
        
        function formatTime(date) {
            let hours = date.getHours();
            let minutes = date.getMinutes();
            hours = (hours < 10 ? '0' : '') + hours;
            minutes = (minutes < 10 ? '0' : '') + minutes;
            return `${hours}:${minutes}`;
        }

        $('#manage-progress').submit(function(e){
            e.preventDefault()
            
            // 1. Ambil konten summernote
            $('#progress_comment').val($('#progress_comment').summernote('code'));

            // 2. Isi hidden fields waktu jika mode input baru (ID kosong)
            if ($('#manage-progress input[name="id"]').val() == '') {
                const now = new Date();
                const today = now.toISOString().split('T')[0]; // Format YYYY-MM-DD
                const currentTime = formatTime(now); // Format HH:mm

                $('#progress_date').val(today);
                $('#progress_start_time').val(currentTime);
                $('#progress_end_time').val(currentTime);
            }

            start_load()
            $.ajax({
                url:'ajax.php?action=save_progress',
                data: new FormData($(this)[0]),
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                type: 'POST',
               success: function(resp){
                    var responseText = resp.trim();
                    // Accept any response that starts with "1" (ignores extra characters)
                    if(responseText.charAt(0) == '1'){
                        alert_toast('Comment successfully saved',"success");
                        setTimeout(function(){ location.reload(); },1500);
                    } else if (responseText.startsWith('0:')) {
                        var errorMessage = responseText.substring(2).trim();
                        alert_toast('Gagal menyimpan data! Detail Server: ' + errorMessage,"error");
                    } else {
                        alert_toast('Gagal menyimpan data! Respon Server Tidak Murni. Respon: ' + responseText,"error");
                    }
                    end_load();
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'AJAX Request Failed! Status HTTP: ' + xhr.status;
                    // Tambahkan potongan dari respon server untuk membantu debugging
                    if (xhr.responseText && xhr.responseText.length > 0) {
                        // Sertakan 200 karakter pertama dari respon untuk debugging
                        errorMessage += ' | Respon Awal Server: ' + xhr.responseText.substring(0, 200) + '...'; 
                    } else if (error) {
                         errorMessage += ' | Error: ' + error;
                    }

                    alert_toast(errorMessage, "error");
                    end_load();
                }
            })
        })
    })
</script>