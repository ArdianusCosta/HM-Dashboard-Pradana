<?php
// FILE: admin_class.php (VERSI LENGKAP)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('db_connect.php');
require_once 'phpmailer_config.php'; 

class Action {
    private $db;

    public function __construct() {
        ob_start();
        include 'db_connect.php';
        $this->db = $conn;
    }

    // === LOG AKTIVITAS ===
    function log_activity($user_id, $project_id = null, $task_id = null, $activity_type = '', $description = '') {
        if (!$this->db) {
            error_log("log_activity: no db connection");
            return false;
        }
        $stmt = $this->db->prepare("
            INSERT INTO activity_log (user_id, project_id, task_id, activity_type, description, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            error_log("log_activity prepare error: " . $this->db->error);
            return false;
        }
        $p_id = $project_id === null ? null : (int)$project_id;
        $t_id = $task_id === null ? null : (int)$task_id;

        $stmt->bind_param("iiiss", $user_id, $p_id, $t_id, $activity_type, $description);

        $exec = $stmt->execute();
        if (!$exec) {
            error_log("log_activity execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        $stmt->close();
        return true;
    }

    // === LOGIN / LOGOUT ===
    function login() {
        extract($_POST);
        $email = $this->db->real_escape_string($email ?? '');
        $password = md5($password ?? '');
        $qry = $this->db->query("SELECT *, concat(firstname,' ',lastname) as name FROM users WHERE email = '{$email}' AND password = '{$password}'");
        if ($qry->num_rows > 0) {
            foreach ($qry->fetch_array() as $key => $value) {
                if ($key != 'password' && !is_numeric($key))
                    $_SESSION['login_' . $key] = $value;
            }
            return 1;
        } else {
            return 2;
        }
    }

    function logout() {
        session_destroy();
        foreach ($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
        header("location:login.php");
    }

    function save_user() {
        extract($_POST);
        $data = "";
        $is_update = !empty($id);

        // Build normal data
        foreach ($_POST as $k => $v) {
            if (!in_array($k, ['id','password','cpass']) && !is_numeric($k)) {
                $v = $this->db->real_escape_string($v);
                if ($k == 'type') $v = (int)$v;
                $data .= (empty($data)) ? " $k='{$v}' " : ", $k='{$v}' ";
            }
        }

        // Password
        if (!empty($password)) {
            $pwd = md5($password);
            $data .= ", password='{$pwd}' ";
        }

        // Cek duplikasi email
        $email = $this->db->real_escape_string($email);
        $check = $this->db->query(
            "SELECT id FROM users WHERE email='{$email}' " . ($is_update ? "AND id!={$id}" : "")
        )->num_rows;

        if ($check > 0) {
            return 2; // email duplicate
        }

        $new_avatar_name = "";

        if (isset($_FILES['img']) && $_FILES['img']['tmp_name'] != "") {

            // Ambil avatar lama terlebih dahulu (SEBELUM kita update)
            $old_avatar = "";
            if ($is_update) {
                $q = $this->db->query("SELECT avatar FROM users WHERE id = {$id}");
                if ($q && $q->num_rows > 0) {
                    $old_avatar = $q->fetch_assoc()['avatar'];
                }
            }

            // Generate nama file baru dengan uniqid agar tidak kena cache
            $safe_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['img']['name']);
            $new_avatar_name = uniqid("ava_", true) . "_" . $safe_name;
            $upload_path = "assets/uploads/" . $new_avatar_name;

            // Upload
            if (move_uploaded_file($_FILES['img']['tmp_name'], $upload_path)) {

                // Simpan avatar baru ke SQL
                $data .= ", avatar='{$new_avatar_name}' ";

                // Hapus avatar lama
                if ($is_update && !empty($old_avatar) && file_exists("assets/uploads/" . $old_avatar)) {
                    @unlink("assets/uploads/" . $old_avatar);
                }
            }
        }

        if (!$is_update) {
            $save = $this->db->query("INSERT INTO users SET {$data}");
        } else {
            $save = $this->db->query("UPDATE users SET {$data} WHERE id={$id}");
        }

        if ($save) {
            if (!empty($id) && isset($_SESSION['login_id']) && $_SESSION['login_id'] == $id) {

                // Update firstname (biar topbar ikut berubah)
                if (isset($_POST['firstname'])) {
                    $_SESSION['login_firstname'] = $_POST['firstname'];
                }

                // Update avatar baru
                if (!empty($new_avatar_name)) {
                    $_SESSION['login_avatar'] = $new_avatar_name;
                }
            }
            // ================================

            return 1;
        }
        return 0;
    }

    
    function delete_user() {
        if (!$this->db) return "0: No DB connection";

        $raw_id = $_POST['id'] ?? null;
        if (empty($raw_id)) return "0: Missing id";

        $id_num = function_exists('decode_id') ? intval(decode_id($raw_id)) : intval($raw_id);
        if ($id_num <= 0) return "0: Invalid id";

        if (($_SESSION['login_id'] ?? 0) == $id_num)
            return "0: Cannot delete currently logged-in user";

        // Cek apakah user ada
        $chk = $this->db->query("SELECT firstname, lastname FROM users WHERE id = {$id_num} LIMIT 1");
        if ($chk->num_rows == 0) return "0: User not found";

        $this->db->begin_transaction();
        try {

            // =============== 👇 FIX TERPENTING: HAPUS AVATAR =================
            $avatar_q = $this->db->query("SELECT avatar FROM users WHERE id = {$id_num}");
            if ($avatar_q && $avatar_q->num_rows > 0) {
                $avatar = $avatar_q->fetch_assoc()['avatar'];
                if (!empty($avatar) && file_exists('assets/uploads/'.$avatar)) {
                    @unlink('assets/uploads/'.$avatar);
                }
            }
            // ================================================================

            // 1) hapus chat personal messages & thread
            $this->db->query("DELETE cm FROM chat_messages cm
                            JOIN chat_threads ct ON cm.thread_id = ct.id
                            WHERE ct.user1_id = {$id_num} OR ct.user2_id = {$id_num}");

            // hapus thread
            $this->db->query("DELETE FROM chat_threads WHERE user1_id = {$id_num} OR user2_id = {$id_num}");

            // 2) hapus group messages
            $this->db->query("DELETE FROM group_messages WHERE sender_id = {$id_num}");

            // 3) hapus group member
            $this->db->query("DELETE FROM group_members WHERE user_id = {$id_num}");

            // 4) hapus read status
            $this->db->query("DELETE FROM user_group_read_status WHERE user_id = {$id_num}");

            // 5) hapus notif
            $this->db->query("DELETE FROM notification_list WHERE user_id = {$id_num}");

            // 6) hapus activity log
            $this->db->query("DELETE FROM activity_log WHERE user_id = {$id_num}");

            // 7) hapus progress
            $this->db->query("DELETE FROM user_productivity WHERE user_id = {$id_num}");

            // 8) update task
            $this->db->query("UPDATE task_list SET created_by = NULL WHERE created_by = {$id_num}");

            // 9) update project
            $this->db->query("UPDATE project_list SET manager_id = NULL WHERE manager_id = {$id_num}");

            // 10) hapus dari CSV id
            $tables_csv = [
                'project_list' => 'user_ids',
                'task_list' => 'user_ids'
            ];

            foreach ($tables_csv as $tbl => $col) {
                $this->db->query("
                    UPDATE {$tbl} SET {$col} = TRIM(BOTH ',' FROM 
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    CONCAT(',', {$col}, ','),
                                    ',{$id_num},', ','
                                ), ',{$id_num},', ','
                            ), ',,', ','
                        )
                    )
                    WHERE {$col} LIKE '%{$id_num}%'
                ");
            }

            // 11) delete user utama
            $del = $this->db->query("DELETE FROM users WHERE id = {$id_num}");
            if (!$del) throw new Exception("Delete failed: " . $this->db->error);

            $this->db->commit();
            return 1;

        } catch (Exception $e) {
            $this->db->rollback();
            return "0: Exception: " . $e->getMessage();
        }
    }

    // === PROJECT MANAGEMENT ===
    function save_project() {
        extract($_POST);
        $data = "";
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('id', 'user_ids')) && !is_numeric($k)) {
                if ($k == 'description') $v = htmlentities(str_replace("'", "&#x2019;", $v));
                $v = $this->db->real_escape_string($v);
                $data .= (empty($data)) ? " $k='{$v}' " : ", $k='{$v}' ";
            }
        }
        $user_ids_clean = '';
        if (isset($user_ids)) {
            $user_ids_clean = array_map('intval', $user_ids);
        }
        
        // Automatically add project manager to team members if not already there
        $manager_id = intval($manager_id ?? 0);
        if ($manager_id > 0) {
            if (!in_array($manager_id, $user_ids_clean)) {
                $user_ids_clean[] = $manager_id;
            }
        }
        
        $data .= ", user_ids='" . implode(',', $user_ids_clean) . "' ";
    

        if (empty($id)) {
            $save = $this->db->query("INSERT INTO project_list SET $data");
            if ($save) {
                $pid = $this->db->insert_id;
                $this->log_activity($_SESSION['login_id'], $pid, null, 'project_add', 'Menambahkan project baru: ' . ($name ?? ''));
                
                // REVISI PESAN SUKSES TAMBAH PROJECT
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($name).'** berhasil ditambahkan! 🚀';
                return 1;
            }
        } else {
            $save = $this->db->query("UPDATE project_list SET $data WHERE id = $id");
            if ($save) {
                $this->log_activity($_SESSION['login_id'], $id, null, 'project_update', 'Mengupdate project: ' . ($name ?? ''));
                
                // REVISI PESAN SUKSES UPDATE PROJECT
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($name).'** berhasil diperbarui! 💾';
                return 1;
            }
        }
        
        // REVISI PESAN GAGAL
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Operasi proyek gagal. ';
        return 0;
    }

    function delete_project() {
        extract($_POST);
        $id = intval($id);
        $qry = $this->db->query("SELECT name FROM project_list WHERE id = $id");
        $project_name = $qry->num_rows > 0 ? $qry->fetch_assoc()['name'] : 'Proyek';
        
        $delete = $this->db->query("DELETE FROM project_list WHERE id = $id");
        
        if ($delete) {
            $this->log_activity($_SESSION['login_id'], $id, null, 'project_delete', 'Menghapus project: ' . $project_name);
            
            // REVISI PESAN SUKSES HAPUS PROJECT
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Proyek **'.htmlspecialchars($project_name).'** berhasil dihapus. 🗑️';
            return 1;
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus proyek. ';
        return 0;
    }

    function duplicate_project() {
        if (!isset($_SESSION['login_type']) || !in_array($_SESSION['login_type'], [1, 2])) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Akses ditolak. Hanya Admin dan Project Manager yang diperbolehkan menduplikasi proyek.';
            return 0;
        }

        $raw_id = $_POST['id'] ?? 0;
        if (function_exists('decode_id') && !is_numeric($raw_id)) {
            $id = intval(decode_id($raw_id));
        } else {
            $id = intval($raw_id);
        }

        if ($id <= 0) return 0;

        $proj_q = $this->db->query("SELECT * FROM project_list WHERE id = {$id}");
        if (!$proj_q || $proj_q->num_rows == 0) return 0;

        $proj = $proj_q->fetch_assoc();
        $orig_name = $proj['name'];
        $new_name = $orig_name . ' (Copy)';

        // Prepare project duplication
        unset($proj['id']);
        $proj['name'] = $new_name;

        $cols = array();
        $vals = array();
        foreach ($proj as $col => $val) {
            $cols[] = "`{$col}`";
            if (is_null($val)) {
                $vals[] = "NULL";
            } else {
                $vals[] = "'" . $this->db->real_escape_string($val) . "'";
            }
        }

        $insert_proj_sql = "INSERT INTO project_list (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")";
        $save_proj = $this->db->query($insert_proj_sql);

        if (!$save_proj) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menduplikasi proyek.';
            return 0;
        }

        $new_project_id = $this->db->insert_id;

        // Log activity
        if (isset($_SESSION['login_id'])) {
            $this->log_activity($_SESSION['login_id'], $new_project_id, null, 'project_add', 'Menduplikasi project dari: ' . $orig_name);
        }

        // Duplicate associated tasks from task_list
        $tasks_q = $this->db->query("SELECT * FROM task_list WHERE project_id = {$id}");
        if ($tasks_q && $tasks_q->num_rows > 0) {
            while ($task = $tasks_q->fetch_assoc()) {
                unset($task['id']);
                $task['project_id'] = $new_project_id;

                $t_cols = array();
                $t_vals = array();
                foreach ($task as $col => $val) {
                    $t_cols[] = "`{$col}`";
                    if (is_null($val)) {
                        $t_vals[] = "NULL";
                    } else {
                        $t_vals[] = "'" . $this->db->real_escape_string($val) . "'";
                    }
                }
                $insert_task_sql = "INSERT INTO task_list (" . implode(", ", $t_cols) . ") VALUES (" . implode(", ", $t_vals) . ")";
                $this->db->query($insert_task_sql);
            }
        }

        $_SESSION['notification']['status'] = 'success';
        $_SESSION['notification']['message'] = 'Proyek **' . htmlspecialchars($new_name) . '** berhasil diduplikasi beserta seluruh tugasnya! 📋';
        return 1;
    }



    // === TASK MANAGEMENT ===
    function save_task() {
    $task = $this->db->real_escape_string($_POST['task'] ?? '');
    $description = $this->db->real_escape_string(htmlentities(str_replace("'", "&#x2019;", $_POST['description'] ?? '')));
    $project_id = intval($_POST['project_id'] ?? 0);
    $status = intval($_POST['status'] ?? 0);

    $user_ids_array = [];
    $user_ids_string = '';
    if (isset($_POST['user_ids'])) {
        if (is_array($_POST['user_ids'])) {
            $user_ids_array = array_map('intval', $_POST['user_ids']);
        } else {
            $user_ids_array = array_map('intval', array_filter(explode(',', $_POST['user_ids'])));
        }
        // remove any client ids (type 4) for safety
        if (!empty($user_ids_array)) {
            $filtered = [];
            foreach ($user_ids_array as $uid) {
                $uid = intval($uid);
                $res = $this->db->query("SELECT type FROM users WHERE id={$uid} LIMIT 1");
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    if (intval($row['type']) !== 4) {
                        $filtered[] = $uid;
                    }
                }
            }
            $user_ids_array = $filtered;
        }
        $user_ids_string = implode(',', $user_ids_array);
    }

    $start_date = $this->db->real_escape_string($_POST['start_date'] ?? '');
    $end_date   = $this->db->real_escape_string($_POST['end_date'] ?? '');
    
    // =============================================
    // HANDLE CONTENT PILLAR WITH "LAINNYA" FUNCTIONALITY
    // =============================================
    $content_pillar_array = [];
    if (isset($_POST['content_pillar']) && is_array($_POST['content_pillar'])) {
        $content_pillar_array = $_POST['content_pillar'];
        
        // Check if "Lainnya" is selected with custom text
        if (in_array('Lainnya', $content_pillar_array)) {
            $lainnya_text = trim($_POST['lainnya_text'] ?? '');
            
            if (!empty($lainnya_text)) {
                // Replace "Lainnya" with custom text
                $key = array_search('Lainnya', $content_pillar_array);
                $content_pillar_array[$key] = $this->db->real_escape_string($lainnya_text);
            }
        }
        
        // Convert array to comma-separated string
        $content_pillar = implode(', ', array_map('trim', $content_pillar_array));
    } else {
        $content_pillar = $this->db->real_escape_string($_POST['content_pillar'] ?? '');
    }
    
    // =============================================
    // HANDLE PLATFORM WITH "LAINNYA" FUNCTIONALITY
    // =============================================
    $platform_array = [];
    if (isset($_POST['platform']) && is_array($_POST['platform'])) {
        $platform_array = $_POST['platform'];
        
        // Check if "Lainnya" is selected with custom text
        if (in_array('Lainnya', $platform_array)) {
            $platform_lainnya_text = trim($_POST['platform_lainnya_text'] ?? '');
            
            if (!empty($platform_lainnya_text)) {
                // Replace "Lainnya" with custom text
                $key = array_search('Lainnya', $platform_array);
                $platform_array[$key] = $this->db->real_escape_string($platform_lainnya_text);
            }
        }
        
        // Convert array to comma-separated string
        $platform = implode(', ', array_map('trim', $platform_array));
    } else {
        $platform = $this->db->real_escape_string($_POST['platform'] ?? '');
    }
    
    $reference_links = $this->db->real_escape_string($_POST['reference_links'] ?? '');

    if (empty($task) || $project_id <= 0) {
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Nama tugas dan ID proyek tidak boleh kosong.';
        return 0;
    }

    $is_new = empty($_POST['id']);
    $task_id = 0;

    if ($is_new) {
        $created_by = intval($_SESSION['login_id'] ?? 0);
        $sql = "
            INSERT INTO task_list
            (project_id, task, description, status, user_ids, start_date, end_date, content_pillar, platform, reference_links, created_by, date_created)
            VALUES
            ({$project_id}, '{$task}', '{$description}', {$status}, '{$this->db->real_escape_string($user_ids_string)}',
            '{$start_date}', '{$end_date}', '{$content_pillar}', '{$platform}', '{$reference_links}', {$created_by}, NOW())
        ";
    } else {
        $task_id = intval($_POST['id']);
        $sql = "
            UPDATE task_list SET
                project_id = {$project_id},
                task = '{$task}',
                description = '{$description}',
                status = {$status},
                user_ids = '{$this->db->real_escape_string($user_ids_string)}',
                start_date = '{$start_date}',
                end_date = '{$end_date}',
                content_pillar = '{$content_pillar}',
                platform = '{$platform}',
                reference_links = '{$reference_links}',
                date_updated = NOW()
            WHERE id = {$task_id}
        ";
    }

    $save = $this->db->query($sql);
    if (!$save) {
        error_log("save_task SQL ERROR: " . $this->db->error);
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menyimpan tugas.';
        return 0;
    }

    // 🔴 AMBIL insert_id LANGSUNG SETELAH INSERT
    if ($is_new) {
        $task_id = (int) $this->db->insert_id;
        if ($task_id <= 0) {
            error_log("CRITICAL: insert_id = 0 setelah INSERT task");
            return 1; // task tersimpan, tapi log & notif dibatalkan
        }
    }

    // AUTO UPDATE OVERDUE
    $this->db->query("
        UPDATE task_list
        SET status = 4
        WHERE end_date < CURDATE()
        AND status NOT IN (3,5)
    ");

    $action_type = $is_new ? 'task_add' : 'task_update';

    $proj = $this->db
        ->query("SELECT manager_id, name FROM project_list WHERE id = {$project_id}")
        ->fetch_assoc();

    $project_name = $proj['name'] ?? 'Unknown Project';
    $manager_id = intval($proj['manager_id'] ?? 0);

    $log_desc = $is_new
        ? "Menambahkan task baru: {$task} pada project: {$project_name}"
        : "Mengupdate task: {$task}";

    // 🔐 GUARD: task_id TIDAK BOLEH 0
    if ($task_id > 0) {
        $this->log_activity(
            intval($_SESSION['login_id'] ?? 0),
            $project_id,
            $task_id,
            $action_type,
            $log_desc
        );
    }

    // NOTIFIKASI
    $link = "index.php?page=view_task&id=" . (function_exists('encode_id') ? encode_id($task_id) : $task_id);
    $message_prefix = $is_new ? "baru ditugaskan" : "diperbarui";
    $message_text = "Tugas **".htmlspecialchars($task)."** telah {$message_prefix} di project **".htmlspecialchars($project_name)."**.";
    $email_subject = "[TASK] Tugas {$task} {$message_prefix}";

    $_SESSION['notification']['status'] = 'success';
    $_SESSION['notification']['message'] = 'Tugas berhasil disimpan.';

    $recipients_ids = array_unique(array_merge([$manager_id], $user_ids_array));
    if (!empty($recipients_ids)) {
        $ids = implode(',', array_filter($recipients_ids));
        $users = $this->db->query("SELECT id, email, notification_email, firstname, lastname FROM users WHERE id IN ({$ids})");
        while ($user = $users->fetch_assoc()) {
            if ($user['id'] == ($_SESSION['login_id'] ?? 0) && !$is_new) continue;

            record_notification(
                $user['id'],
                1,
                $message_text,
                $link,
                $this->db,
                true,
                [
                    'email' => $user['notification_email'] ?: $user['email'],
                    'name'  => ucwords($user['firstname'].' '.$user['lastname']),
                    'subject' => $email_subject
                ]
            );
        }
    }

    return 1;
}


    public function delete_task() {
        extract($_POST);
        // Decode ID if it's encoded (handles encoded IDs from task_calendar.php)
        $raw_id = $_POST['id'] ?? null;
        $id = function_exists('decode_id') ? intval(decode_id($raw_id)) : intval($raw_id);
        
        $task_info_qry = $this->db->query("
            SELECT t.task, t.project_id, p.name as project_name
            FROM task_list t
            INNER JOIN project_list p ON p.id = t.project_id
            WHERE t.id = $id
        ");

        $project_id = null;
        $task_name = "Tugas ID: {$id}";
        $project_name = "Unknown Project";

        if ($task_info_qry && $task_info_qry->num_rows > 0) {
            $info = $task_info_qry->fetch_assoc();
            $project_id = intval($info['project_id']);
            $task_name = $this->db->real_escape_string($info['task']);
            $project_name = $this->db->real_escape_string($info['project_name']);
        }
        
        // HAPUS NOTIFIKASI TERKAIT & TUGAS UTAMA
        $this->db->begin_transaction();
        
        try {
            // Hapus notifikasi terkait
            $this->db->query("DELETE FROM notification_list WHERE link LIKE '%view_task.php?id=" . (function_exists('encode_id') ? encode_id($id) : $id) . "%'");
            
            // Hapus entri user_productivity/progress
            $this->db->query("DELETE FROM user_productivity WHERE task_id = $id");

            // Hapus tugas utama
            $delete_task = $this->db->query("DELETE FROM task_list WHERE id = $id");

            if (!$delete_task) {
                throw new Exception("Gagal menghapus tugas utama.");
            }
            
            $this->log_activity($_SESSION['login_id'] ?? 0, $project_id, $id, 'task_delete', 'Menghapus task: ' . $task_name);
            $this->db->commit();
            
            $_SESSION['notification']['status'] = 'success'; 
            $_SESSION['notification']['message'] = 'Tugas **'.htmlspecialchars($task_name).'** berhasil dihapus. 🗑️';
            return 1;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Task Deletion Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menghapus tugas.';
            return 0;
        }
    }

    // === PROGRESS (TASK ACTIVITY) ===
        function save_progress() {
            // --- Get and sanitize inputs ---
            $id          = trim($_POST['id'] ?? '');
            $project_id  = intval($_POST['project_id'] ?? 0);
            $task_id     = intval($_POST['task_id'] ?? 0);
            $subject     = trim($_POST['subject'] ?? '');
            $comment     = trim($_POST['comment'] ?? '');
            $date        = trim($_POST['date'] ?? date("Y-m-d"));
            $start_time  = trim($_POST['start_time'] ?? date("H:i"));
            $end_time    = trim($_POST['end_time'] ?? date("H:i"));
            $user_id     = intval($_SESSION['login_id'] ?? 0);

            // --- Calculate duration in minutes ---
            $duration = (strtotime($end_time) - strtotime($start_time)) / 60;
            if ($duration < 0) $duration = 0;

            // --- Use $this->db consistently (no global $conn) ---
            if (empty($id)) {
                // INSERT mode – prepared statement
                $stmt = $this->db->prepare("
                    INSERT INTO user_productivity 
                    (project_id, task_id, subject, comment, date, start_time, end_time, user_id, time_rendered, date_created) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                if (!$stmt) {
                    return "0: Prepare failed: " . $this->db->error;
                }
                $stmt->bind_param("iisssssii", $project_id, $task_id, $subject, $comment, $date, $start_time, $end_time, $user_id, $duration);
                
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    return "0: INSERT failed: " . $error;
                }
                $stmt->close();

                // --- After successful insert: notifications & activity log ---
                // Fetch task details for notifications
                $task_details_q = $this->db->query("
                    SELECT t.task, t.project_id, p.manager_id, t.user_ids AS task_users 
                    FROM task_list t 
                    INNER JOIN project_list p ON p.id = t.project_id 
                    WHERE t.id = {$task_id}
                ");
                
                if ($task_details_q && $task_details_q->num_rows > 0) {
                    $tr = $task_details_q->fetch_assoc();
                    $task_name = $tr['task'];
                    $manager_id = (int)$tr['manager_id'];
                    $task_user_ids = array_map('intval', array_filter(explode(',', $tr['task_users'])));
                    
                    $recipients_ids = array_unique(array_merge([$manager_id], $task_user_ids));
                    
                    if (!empty($recipients_ids)) {
                        // Exclude current user
                        $filtered = array_filter($recipients_ids, function($uid) use ($user_id) {
                            return $uid != $user_id;
                        });
                        if (!empty($filtered)) {
                            $ids_str = implode(',', $filtered);
                            $users_q = $this->db->query("SELECT id, email, notification_email, firstname, lastname FROM users WHERE id IN ({$ids_str})");
                            
                            $current_user_name = ucwords($_SESSION['login_firstname'] . ' ' . $_SESSION['login_lastname']);
                            $link = "index.php?page=view_task&id=" . (function_exists('encode_id') ? encode_id($task_id) : $task_id);
                            $message = "Task **{$task_name}** mendapat komentar baru dari {$current_user_name}.";
                            $email_subject = "[KOMENTAR BARU] Task: {$task_name}";
                            
                            while ($user = $users_q->fetch_assoc()) {
                                $target_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];
                                $full_name = ucwords($user['firstname'] . ' ' . $user['lastname']);
                                $email_details = [
                                    'email' => $target_email,
                                    'name'  => $full_name,
                                    'subject' => $email_subject
                                ];
                                record_notification($user['id'], 4, $message, $link, $this->db, true, $email_details);
                            }
                        }
                    }
                    $this->log_activity(
                        $user_id,
                        $project_id,
                        $task_id,
                        'progress_add',
                        'Menambahkan Komentar pada task: ' . $task_name . '<br>' . $comment
                    );
                }
                
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Progress tugas berhasil disimpan!';
                return "1";
                
            } else {
                // UPDATE mode
                $id = intval($id);
                $stmt = $this->db->prepare("
                    UPDATE user_productivity SET
                        project_id = ?,
                        task_id = ?,
                        subject = ?,
                        comment = ?,
                        date = ?,
                        start_time = ?,
                        end_time = ?,
                        time_rendered = ?
                    WHERE id = ?
                ");
                if (!$stmt) {
                    return "0: Prepare failed: " . $this->db->error;
                }
                $stmt->bind_param("iisssssii", $project_id, $task_id, $subject, $comment, $date, $start_time, $end_time, $duration, $id);
                
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    return "0: UPDATE failed: " . $error;
                }
                $stmt->close();
                
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Progress tugas berhasil diperbarui!';
                return "1";
            }
        }


    function delete_progress() {
        extract($_POST);
        $id = intval($id);
        $qry = $this->db->query("
            SELECT p.*, t.task, t.project_id 
            FROM user_productivity p 
            LEFT JOIN task_list t ON p.task_id = t.id 
            WHERE p.id = $id
        ");
        $project_id = null;
        $task_name = '';

        if ($qry && $qry->num_rows > 0) {
            $row = $qry->fetch_assoc();
            $task_name = $row['task'] ?? 'Task';
            $project_id = $row['project_id'] ?? null;
            
            $delete = $this->db->query("DELETE FROM user_productivity WHERE id = $id");
            if ($delete) {
                $this->log_activity($_SESSION['login_id'], $project_id, $row['task_id'] ?? null, 'progress_delete', 'Menghapus progress pada task: ' . $task_name);
                
                $_SESSION['notification']['status'] = 'success';
                $_SESSION['notification']['message'] = 'Progres tugas berhasil dihapus!';
                return 1;
            }
        }
        
        $_SESSION['notification']['status'] = 'error';
        $_SESSION['notification']['message'] = 'Gagal menghapus progres tugas. 🛑'; 
        return 0;
    }
    
    // 1. Mendapatkan ID thread yang ada atau membuat yang baru
    function get_or_create_thread_id() {
        extract($_POST);
        $user1 = $_SESSION['login_id'];
        $user2 = $this->db->real_escape_string($user2_id);
        
        $id1 = min($user1, $user2);
        $id2 = max($user1, $user2);

        $sql_check = "SELECT id FROM chat_threads WHERE user1_id = '{$id1}' AND user2_id = '{$id2}'";
        $result = $this->db->query($sql_check);

        if ($result->num_rows > 0) {
            $thread = $result->fetch_assoc();
            return $thread['id'];
        } else {
            $sql_create = "INSERT INTO chat_threads (user1_id, user2_id, last_message_at) VALUES ('{$id1}', '{$id2}', NOW())";
            $save = $this->db->query($sql_create);

            if ($save) {
                return $this->db->insert_id;
            } else {
                error_log("Error creating thread: " . $this->db->error);
                return 0;
            }
        }
    }

    // Helper: validates and stores an optional chat image ($_FILES['image']).
    // Returns the stored filename (string) if an image was uploaded, '' if no
    // image was sent at all, or false if validation/upload failed (and an
    // error notification has already been set).
    function handle_chat_image_upload($field = 'image') {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE || $_FILES[$field]['tmp_name'] == '') {
            return '';
        }

        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal mengunggah gambar. 🛑';
            return false;
        }

        $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $max_size = 5 * 1024 * 1024; // 5MB, keep in sync with front-end limit

        if ($_FILES[$field]['size'] > $max_size) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Ukuran gambar maksimal 5MB. 🛑';
            return false;
        }

        // Verify actual file content, not just the client-supplied extension/mime
        $detected_mime = @mime_content_type($_FILES[$field]['tmp_name']);
        if (!isset($allowed_mimes[$detected_mime])) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Format gambar tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP. 🛑';
            return false;
        }

        $upload_dir = "assets/uploads/chat/";
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }

        $new_name = uniqid("chatimg_", true) . "." . $allowed_mimes[$detected_mime];
        $upload_path = $upload_dir . $new_name;

        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $upload_path)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menyimpan gambar di server. 🛑';
            return false;
        }

        return $new_name;
    }

    // 2. Menyimpan pesan chat personal (Push Notifikasi Email)
    function save_personal_chat_message() {
        extract($_POST);
        $sender_id = $_SESSION['login_id'];
        $thread_id = $this->db->real_escape_string($thread_id);
        
        $message = $this->db->real_escape_string(htmlentities($message_content ?? '')); 
        
        if (empty($thread_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID thread chat tidak valid. 🛑'; 
            return 0;
        }

        // Handle optional image attachment
        $attachment_name = $this->handle_chat_image_upload();
        if ($attachment_name === false) {
            // handle_chat_image_upload() already set the error notification
            return 0;
        }

        if (empty($message) && empty($attachment_name)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Pesan tidak boleh kosong. 🛑'; 
            return 0;
        }

        $attachment_sql = $attachment_name !== '' ? "'{$attachment_name}'" : 'NULL';

        // Save message with is_read = 0 so recipient sees it as unread
        $sql = "INSERT INTO chat_messages (thread_id, sender_id, message_content, attachment, is_read) VALUES ('{$thread_id}', '{$sender_id}', '{$message}', {$attachment_sql}, 0)";
        $save = $this->db->query($sql);

        if ($save) {
            $this->db->query("UPDATE chat_threads SET last_message_at = NOW() WHERE id = '{$thread_id}'");
            
            $sql_thread = $this->db->query("SELECT user1_id, user2_id FROM chat_threads WHERE id = '{$thread_id}'");
            $thread_data = $sql_thread->fetch_assoc();
            $recipient_id = ($thread_data['user1_id'] == $sender_id) ? $thread_data['user2_id'] : $thread_data['user1_id'];
            
            $sender_name = $this->db->query("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = '{$sender_id}'")->fetch_assoc()['name'];
            $decoded_message = html_entity_decode($message);
            if ($decoded_message === '' && !empty($attachment_name)) {
                $preview_message = "📷 Image";
            } else {
                $preview_message = substr($decoded_message, 0, 50) . (strlen($decoded_message) > 50 ? '...' : '');
            }
            $notification_message = "Pesan baru dari **{$sender_name}**: " . $preview_message;
            
            $encoded_thread_id = function_exists('encode_id') ? encode_id($thread_id) : $thread_id;
            $link = "index.php?page=chat&thread_id={$encoded_thread_id}"; 
            
            $recipient_q = $this->db->query("SELECT id, email, notification_email, firstname, lastname FROM users WHERE id = '{$recipient_id}'");
            $recipient_user = $recipient_q->fetch_assoc();
            
            if ($recipient_user) {
                $full_name = ucwords($recipient_user['firstname'] . ' ' . $recipient_user['lastname']);
                $target_email = !empty($recipient_user['notification_email']) ? $recipient_user['notification_email'] : $recipient_user['email'];

                $email_details = [
                    'email' => $target_email,
                    'name'  => $full_name,
                    'subject' => "[CHAT BARU] Pesan dari {$sender_name}"
                ];
                
                record_notification($recipient_user['id'], 4, $notification_message, $link, $this->db, true, $email_details);
            }
            
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pesan berhasil terkirim! ✉️';
            
            return 1;
        } else {
            error_log("save_personal_chat_message error: " . $this->db->error);
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal mengirim pesan chat. 🛑'; 
            return 0;
        }
    }

    // 3. Memuat pesan chat untuk thread tertentu (Tandai Dibaca)
    function get_personal_chat_messages() {
        $encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; }; 
        $current_user_id = $_SESSION['login_id'];

        extract($_POST);
        $thread_id = $this->db->real_escape_string($thread_id);
        
        $this->db->query("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE thread_id = '{$thread_id}' 
            AND sender_id != '{$current_user_id}' 
            AND is_read = 0
        ");
        
        $data = ['users' => [], 'projects' => [], 'tasks' => []];
        $users_q = $this->db->query("SELECT id, CONCAT(firstname, ' ', lastname) as name FROM users");
        while($row = $users_q->fetch_assoc()) {
            $data['users'][$row['id']] = ['name' => $row['name'], 'encoded_id' => $encoder($row['id'])];
        }
        $projects_q = $this->db->query("SELECT id, name FROM project_list");
        while($row = $projects_q->fetch_assoc()) {
            $data['projects'][$row['id']] = ['name' => $row['name'], 'encoded_id' => $encoder($row['id'])];
        }
        $tasks_q = $this->db->query("SELECT id, task FROM task_list");
        while($row = $tasks_q->fetch_assoc()) {
            $data['tasks'][$row['id']] = ['name' => $row['task'], 'encoded_id' => $encoder($row['id'])];
        }

        $messages_q = $this->db->query("
            SELECT 
                cm.*, 
                CONCAT(u.firstname, ' ', u.lastname) as sender_name,
                u.avatar
            FROM chat_messages cm
            JOIN users u ON u.id = cm.sender_id
            WHERE cm.thread_id = '{$thread_id}'
            ORDER BY cm.created_at ASC
        ");
        
        $messages = [];
        while($row = $messages_q->fetch_assoc()) {
            $row['message_content'] = html_entity_decode($row['message_content']);
            $row['attachment_url'] = !empty($row['attachment']) ? "assets/uploads/chat/" . $row['attachment'] : null;
            $messages[] = $row;
        }
        
        $data['messages'] = $messages;

        return json_encode($data);
    }
    
    // 4. Membuat Grup Chat
    function create_new_group() {
        extract($_POST);
        $name = $this->db->real_escape_string($name ?? 'New Group');
        $user_ids = $user_ids ?? [];
        $created_by = $_SESSION['login_id'];

        if (empty($name) || empty($user_ids)) {
             $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Nama grup dan anggota tidak boleh kosong. 🛑'; 
            return 0; 
        }

        $this->db->begin_transaction();

        try {
            $sql_group = "INSERT INTO chat_groups (name, created_by, last_message_at) VALUES ('{$name}', '{$created_by}', NOW())";
            $save_group = $this->db->query($sql_group);

            if (!$save_group) {
                throw new Exception("Gagal membuat entri grup chat.");
            }
            $group_id = $this->db->insert_id;
            $user_ids_clean = array_map('intval', $user_ids);
            
            // Pastikan pembuat grup masuk sebagai anggota dan admin
            if (!in_array($created_by, $user_ids_clean)) {
                $user_ids_clean[] = $created_by;
            }

            $member_values = [];
            foreach ($user_ids_clean as $user_id) {
                $is_admin = ($user_id == $created_by) ? 1 : 0;
                $member_values[] = "('{$group_id}', '{$user_id}', '{$is_admin}')";
            }
            
            if (!empty($member_values)) {
                $sql_members = "INSERT INTO group_members (group_id, user_id, is_admin) VALUES " . implode(', ', $member_values);
                $this->db->query($sql_members);
            }
            
            $this->db->commit();
            
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Grup chat **'.htmlspecialchars($name).'** berhasil dibuat! 👥✨';
            return 1;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Create Group Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal membuat grup chat. 🛑'; 
            return 0;
        }
    }


    // 5. Mengambil semua data sidebar (Grup + Personal)
    function get_all_chat_sidebar_data() {
        $current_user_id = $_SESSION['login_id'];
        
        // A. Ambil Data Personal
        $sql_personal = "
            SELECT 
                u.id, 
                CONCAT(u.firstname, ' ', u.lastname) AS name, 
                u.avatar,
                t.id AS thread_id,
                t.last_message_at AS last_message_timestamp,
                (SELECT CASE WHEN cm_last.message_content IS NOT NULL AND cm_last.message_content <> '' THEN cm_last.message_content WHEN cm_last.attachment IS NOT NULL THEN '📷 Image' ELSE cm_last.message_content END FROM chat_messages cm_last WHERE cm_last.thread_id = t.id ORDER BY cm_last.created_at DESC LIMIT 1) AS last_message_content,
                (SELECT COUNT(cm_unread.id) FROM chat_messages cm_unread WHERE cm_unread.thread_id = t.id AND cm_unread.sender_id != '{$current_user_id}' AND cm_unread.is_read = 0) AS unread_count
            FROM users u
            LEFT JOIN chat_threads t 
                ON (t.user1_id = u.id AND t.user2_id = '{$current_user_id}') OR (t.user2_id = u.id AND t.user1_id = '{$current_user_id}')
            WHERE u.id != '{$current_user_id}'
        ";
        
        $result_personal = $this->db->query($sql_personal);
        $users = [];
        if ($result_personal) {
            while ($row = $result_personal->fetch_assoc()) {
                $row['unread_count'] = (int)($row['unread_count'] ?? 0);
                $row['last_message_content'] = html_entity_decode($row['last_message_content'] ?? '');
                $users[] = $row;
            }
        }

        // B. Ambil Data Group
        $sql_group = "
            SELECT 
                g.id, 
                g.name, 
                g.last_message_at AS last_message_timestamp,
                (SELECT CASE WHEN gm_last.message_content IS NOT NULL AND gm_last.message_content <> '' THEN gm_last.message_content WHEN gm_last.attachment IS NOT NULL THEN '📷 Image' ELSE gm_last.message_content END FROM group_messages gm_last WHERE gm_last.group_id = g.id ORDER BY gm_last.created_at DESC LIMIT 1) AS last_message_content,
                (SELECT CONCAT(u.firstname, ' ', u.lastname) FROM group_messages gm_last INNER JOIN users u ON u.id = gm_last.sender_id WHERE gm_last.group_id = g.id ORDER BY gm_last.created_at DESC LIMIT 1) AS last_sender_name,
                (
                    SELECT COUNT(gm_unread.id) 
                    FROM group_messages gm_unread
                    LEFT JOIN user_group_read_status r ON r.group_id = gm_unread.group_id AND r.user_id = '{$current_user_id}'
                    WHERE gm_unread.group_id = g.id 
                    AND gm_unread.sender_id != '{$current_user_id}'
                    AND gm_unread.created_at > IFNULL(r.last_read_at, '2000-01-01')
                ) AS unread_count
            FROM chat_groups g
            INNER JOIN group_members m ON m.group_id = g.id
            WHERE m.user_id = '{$current_user_id}'
        ";
        
        $result_group = $this->db->query($sql_group);
        $groups = [];
        if ($result_group) {
            while ($row = $result_group->fetch_assoc()) {
                $row['unread_count'] = (int)($row['unread_count'] ?? 0);
                $row['last_message_content'] = html_entity_decode($row['last_message_content'] ?? '');
                $groups[] = $row;
            }
        }
        
        return json_encode(['users' => $users, 'groups' => $groups]);
    }


    // 6. Menyimpan pesan Grup
    function save_group_chat_message() {
        extract($_POST);
        $sender_id = $_SESSION['login_id'];
        $group_id = $this->db->real_escape_string($group_id);
        $message = $this->db->real_escape_string(htmlentities($message_content ?? '')); 

        if (empty($group_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID grup chat tidak valid. 🛑'; 
            return 0;
        }

        // Handle optional image attachment
        $attachment_name = $this->handle_chat_image_upload();
        if ($attachment_name === false) {
            return 0;
        }

        if (empty($message) && empty($attachment_name)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Pesan tidak boleh kosong. 🛑'; 
            return 0;
        }

        $attachment_sql = $attachment_name !== '' ? "'{$attachment_name}'" : 'NULL';

        $sql = "INSERT INTO group_messages (group_id, sender_id, message_content, attachment) VALUES ('{$group_id}', '{$sender_id}', '{$message}', {$attachment_sql})";
        $save = $this->db->query($sql);

        if ($save) {
            $this->db->query("UPDATE chat_groups SET last_message_at = NOW() WHERE id = '{$group_id}'");
            
            // Notifikasi untuk semua anggota grup (kecuali diri sendiri)
            $members_q = $this->db->query("SELECT u.id, u.email, u.notification_email, u.firstname, u.lastname FROM group_members m INNER JOIN users u ON u.id = m.user_id WHERE m.group_id = '{$group_id}' AND u.id != '{$sender_id}'");
            $group_name = $this->db->query("SELECT name FROM chat_groups WHERE id = '{$group_id}'")->fetch_assoc()['name'];
            $sender_name = $this->db->query("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = '{$sender_id}'")->fetch_assoc()['name'];
            
            $decoded_message = html_entity_decode($message);
            if ($decoded_message === '' && !empty($attachment_name)) {
                $preview_message = "📷 Image";
            } else {
                $preview_message = substr($decoded_message, 0, 50) . (strlen($decoded_message) > 50 ? '...' : '');
            }
            $notification_message = "Pesan baru di grup **{$group_name}** dari {$sender_name}: " . $preview_message;
            $encoded_group_id = function_exists('encode_id') ? encode_id($group_id) : $group_id;
            $link = "index.php?page=chat&group_id={$encoded_group_id}"; 
            $email_subject = "[GROUP CHAT] Pesan di {$group_name}";

            while ($user = $members_q->fetch_assoc()) {
                $target_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];
                $email_details = [
                    'email' => $target_email, 'name'  => ucwords($user['firstname'] . ' ' . $user['lastname']), 'subject' => $email_subject
                ];
                record_notification($user['id'], 5, $notification_message, $link, $this->db, true, $email_details);
            }

            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pesan grup berhasil terkirim! 💬';
            return 1;
        } else {
            error_log("save_group_chat_message error: " . $this->db->error);
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal mengirim pesan grup. 🛑';
            return 0;
        }
    }

    // 7. Memuat pesan Grup (Tandai Dibaca)
    function get_group_chat_messages() {
        $encoder = function_exists('encode_id') ? 'encode_id' : function($id) { return $id; }; 
        $current_user_id = $_SESSION['login_id'];
        extract($_POST);
        $group_id = $this->db->real_escape_string($group_id);

        // Tandai grup sebagai sudah dibaca (mengupdate timestamp last_read_at)
        $this->db->query("
            INSERT INTO user_group_read_status (group_id, user_id, last_read_at)
            VALUES ('{$group_id}', '{$current_user_id}', NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ");

        $data = ['users' => [], 'projects' => [], 'tasks' => []]; 

        $messages_q = $this->db->query("
            SELECT gm.*, CONCAT(u.firstname, ' ', u.lastname) as sender_name, u.avatar
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = '{$group_id}'
            ORDER BY gm.created_at ASC
        ");
        
        $messages = [];
        while($row = $messages_q->fetch_assoc()) {
            $row['message_content'] = html_entity_decode($row['message_content']);
            $row['attachment_url'] = !empty($row['attachment']) ? "assets/uploads/chat/" . $row['attachment'] : null;
            $messages[] = $row;
        }
        
        $data['messages'] = $messages;

        // Fetch lookup data for mentions (simplified here)
        $users_q = $this->db->query("SELECT id, CONCAT(firstname, ' ', lastname) as name FROM users");
        while($row = $users_q->fetch_assoc()) {
            $data['users'][$row['id']] = ['name' => $row['name'], 'encoded_id' => $encoder($row['id'])];
        }
        $projects_q = $this->db->query("SELECT id, name FROM project_list");
        while($row = $projects_q->fetch_assoc()) {
            $data['projects'][$row['id']] = ['name' => $row['name'], 'encoded_id' => $encoder($row['id'])];
        }
        $tasks_q = $this->db->query("SELECT id, task FROM task_list");
        while($row = $tasks_q->fetch_assoc()) {
            $data['tasks'][$row['id']] = ['name' => $row['task'], 'encoded_id' => $encoder($row['id'])];
        }
        
        return json_encode($data);
    }
    
    // 8. Mengambil Detail Grup (untuk Modal Settings)
    function get_group_details() {
        $group_id = $this->db->real_escape_string($_POST['group_id'] ?? null);
        
        if (empty($group_id)) {
            return json_encode(['error' => 'Invalid ID']);
        }

        $group_q = $this->db->query("SELECT id, name, created_by FROM chat_groups WHERE id = '{$group_id}'");
        if ($group_q->num_rows == 0) {
            return json_encode(['error' => 'Group not found']);
        }
        $group_data = $group_q->fetch_assoc();

        $members_q = $this->db->query("
            SELECT gm.user_id, CONCAT(u.firstname, ' ', u.lastname) AS name, gm.is_admin
            FROM group_members gm
            INNER JOIN users u ON u.id = gm.user_id
            WHERE gm.group_id = '{$group_id}'
        ");
        
        $members = [];
        while ($row = $members_q->fetch_assoc()) {
            $members[] = $row;
        }

        $group_data['members'] = $members;
        return json_encode($group_data);
    }
    
    // 9. Mengupdate Nama dan Anggota Grup
    function update_group_settings() {
        extract($_POST);
        $group_id = $this->db->real_escape_string($group_id ?? null);
        $name = $this->db->real_escape_string($name ?? '');
        $user_ids = $user_ids ?? []; 
        $current_user_id = $_SESSION['login_id']; // Pengguna yang melakukan setting

        if (empty($group_id) || empty($name)) {
             $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Nama grup tidak boleh kosong. 🛑'; 
            return 0;
        }
        
        // 💡 PERBAIKAN KRITIS: Selalu masukkan kembali user yang sedang login
        $user_ids_clean = array_map('intval', $user_ids);
        if (!in_array($current_user_id, $user_ids_clean)) {
            $user_ids_clean[] = $current_user_id;
        }
        
        if (empty($user_ids_clean)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Grup harus memiliki setidaknya satu anggota (Anda). 🛑'; 
            return 0;
        }
        
        $this->db->begin_transaction();

        try {
            // A. Update Nama Grup
            $update_name = $this->db->query("UPDATE chat_groups SET name = '{$name}' WHERE id = '{$group_id}'");
            if (!$update_name) {
                throw new Exception("Gagal update nama.");
            }

            // B. Hapus semua anggota lama
            $delete_members = $this->db->query("DELETE FROM group_members WHERE group_id = '{$group_id}'");
            if (!$delete_members) {
                 throw new Exception("Gagal menghapus anggota lama.");
            }

            // C. Tambahkan anggota baru
            $member_values = [];
            
            $admin_q = $this->db->query("SELECT created_by FROM chat_groups WHERE id = '{$group_id}'")->fetch_assoc();
            $created_by_id = $admin_q['created_by'] ?? 0;
            
            foreach (array_unique($user_ids_clean) as $user_id) {
                $is_admin = ($user_id == $created_by_id) ? 1 : 0;
                $member_values[] = "('{$group_id}', '{$user_id}', '{$is_admin}')";
            }
            
            if (!empty($member_values)) {
                $sql_members = "INSERT INTO group_members (group_id, user_id, is_admin) VALUES " . implode(', ', $member_values);
                $this->db->query($sql_members);
            }
            
            $this->db->commit();
            
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Pengaturan grup **'.htmlspecialchars($name).'** berhasil diperbarui! ⚙️';
            return 1;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update Group Settings Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal memperbarui pengaturan grup. 🛑'; 
            return 0; 
        }
    }

    // 10. Menghapus Grup Chat
    function delete_group() {
        $group_id = $this->db->real_escape_string($_POST['group_id'] ?? null);
        $group_name = $this->db->query("SELECT name FROM chat_groups WHERE id = '{$group_id}'")->fetch_assoc()['name'] ?? 'Grup Chat';

        if (empty($group_id)) {
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'ID grup chat tidak valid. 🛑'; 
            return 0;
        }
       
        $this->db->begin_transaction();

        try {
            // 1. Hapus Anggota
            $this->db->query("DELETE FROM group_members WHERE group_id = '{$group_id}'");
            
            // 2. Hapus Pesan
            $this->db->query("DELETE FROM group_messages WHERE group_id = '{$group_id}'");
            
            // 3. Hapus Status Baca
            $this->db->query("DELETE FROM user_group_read_status WHERE group_id = '{$group_id}'");
            
            // 4. Hapus Grup Utama
            $delete_group = $this->db->query("DELETE FROM chat_groups WHERE id = '{$group_id}'");
            if (!$delete_group) {
                throw new Exception("Gagal menghapus entri grup utama.");
            }
            
            $this->db->commit();
            
            $_SESSION['notification']['status'] = 'success';
            $_SESSION['notification']['message'] = 'Grup chat **'.htmlspecialchars($group_name).'** berhasil dihapus! 🗑️';
            return 1; 
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Grup Deletion Error: " . $e->getMessage());
            $_SESSION['notification']['status'] = 'error';
            $_SESSION['notification']['message'] = 'Gagal menghapus grup chat. 🛑'; 
            return 0; 
        }
    }
    
    // 11. Get Total Unread Chat Count
    function get_total_unread_chat_count() {
        try {
            if (!isset($_SESSION['login_id'])) {
                return json_encode(['unread_count' => 0, 'error' => 'Not logged in']);
            }
            
            $current_user_id = $_SESSION['login_id'];
            
            // Count unread personal chat messages
            $personal_q = $this->db->query("
                SELECT COUNT(*) as count 
                FROM chat_messages cm
                WHERE cm.sender_id != '{$current_user_id}' 
                AND cm.is_read = 0
                AND cm.thread_id IN (
                    SELECT id FROM chat_threads 
                    WHERE (user1_id = '{$current_user_id}' OR user2_id = '{$current_user_id}')
                )
            ");
            
            if (!$personal_q) {
                return json_encode(['unread_count' => 0, 'error' => 'Database error']);
            }
            
            $personal_count = 0;
            if ($personal_q->num_rows > 0) {
                $personal_count = intval($personal_q->fetch_assoc()['count']);
            }
            
            // Count unread group messages
            $group_q = $this->db->query("
                SELECT COUNT(*) as count
                FROM group_messages gm
                LEFT JOIN user_group_read_status r ON r.group_id = gm.group_id AND r.user_id = '{$current_user_id}'
                WHERE gm.sender_id != '{$current_user_id}'
                AND gm.created_at > IFNULL(r.last_read_at, '2000-01-01')
                AND gm.group_id IN (
                    SELECT group_id FROM group_members WHERE user_id = '{$current_user_id}'
                )
            ");
            
            if (!$group_q) {
                return json_encode(['unread_count' => 0, 'error' => 'Database error']);
            }
            
            $group_count = 0;
            if ($group_q->num_rows > 0) {
                $group_count = intval($group_q->fetch_assoc()['count']);
            }
            
            $total = $personal_count + $group_count;
            return json_encode(['unread_count' => $total]);
        } catch (Exception $e) {
            return json_encode(['unread_count' => 0, 'error' => $e->getMessage()]);
        }
    }
    
    // 12. Destructor
    function __destruct() {
        if ($this->db) $this->db->close();
        // ob_end_flush();
    }
}