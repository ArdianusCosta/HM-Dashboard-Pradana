<?php
// manual_reminder.php
require_once 'db_connect.php';
require_once 'phpmailer_config.php';

session_start();

// Hanya admin yang bisa akses
if (!isset($_SESSION['login_type']) || $_SESSION['login_type'] != 1) {
    die('
        <div style="padding:20px; text-align:center;">
            <h2 style="color:#dc3545;">⚠️ Akses Ditolak</h2>
            <p>Hanya administrator yang bisa mengakses halaman ini.</p>
            <a href="index.php" style="color:#B75301;">Kembali ke Dashboard</a>
        </div>
    ');
}

// Tanggal target (default: 2 hari dari sekarang)
$target_date = date('Y-m-d', strtotime('+2 days'));

// Handle form submission untuk tanggal custom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_date'])) {
    $target_date = $_POST['target_date'];
}

// Fungsi untuk manual check
function manual_check_events($conn, $target_date) {
    // Ambil events untuk tanggal tertentu
    $events_query = "
        SELECT e.id, e.title, e.start_event, e.end_event, e.description, 
               e.notification_sent, e.notification_sent_at
        FROM events e
        WHERE DATE(e.start_event) = '{$target_date}'
        ORDER BY e.start_event
    ";
    
    $events_result = $conn->query($events_query);
    
    if (!$events_result) {
        return false;
    }
    
    return $events_result;
}

// Ambil data untuk preview
$events_result = manual_check_events($conn, $target_date);
$total_events = $events_result ? $events_result->num_rows : 0;

// Handle trigger send
$trigger_result = null;
if (isset($_GET['action']) && $_GET['action'] === 'send' && isset($_GET['date'])) {
    $trigger_date = $_GET['date'];
    
    // SIMULASI TANGGAL: Override fungsi date() untuk testing
    // Kita perlu membuat fungsi yang override time untuk testing
    $original_time = time();
    
    // Set waktu ke 2 hari sebelum trigger_date
    $simulated_time = strtotime($trigger_date . ' -2 days');
    
    // Simpan fungsi asli
    $original_date_func = 'date';
    
    // Buat fungsi override untuk testing
    if (!function_exists('test_date')) {
        function test_date($format, $timestamp = null) {
            global $simulated_time;
            if ($timestamp === null) {
                $timestamp = $simulated_time;
            }
            return date($format, $timestamp);
        }
    }
    
    // Gunakan fungsi test_date untuk sementara
    // Karena fungsi send_event_reminders_to_all_users() pakai date(), kita perlu mock
    
    // Sebagai alternatif, buat versi khusus untuk manual trigger
    $trigger_result = manual_send_reminders_for_date($conn, $trigger_date);
}

// Tambahkan fungsi ini di manual_reminder.php:
function manual_send_reminders_for_date($conn, $target_date) {
    // Ambil events untuk tanggal tertentu
    $events_query = "
        SELECT e.id, e.title, e.start_event, e.end_event, e.description, e.color
        FROM events e
        WHERE DATE(e.start_event) = '{$target_date}'
        AND (e.notification_sent = FALSE OR e.notification_sent IS NULL)
        ORDER BY e.start_event
    ";
    
    $events_result = $conn->query($events_query);
    
    if (!$events_result || $events_result->num_rows === 0) {
        return ['status' => 'info', 'message' => 'Tidak ada event yang perlu dikirim reminder untuk tanggal ini.'];
    }
    
    // Ambil semua user
    $users_query = "SELECT id, firstname, lastname, email, notification_email FROM users";
    $users_result = $conn->query($users_query);
    
    if (!$users_result || $users_result->num_rows === 0) {
        return ['status' => 'error', 'message' => 'Tidak ada user yang ditemukan.'];
    }
    
    $users = [];
    while ($user = $users_result->fetch_assoc()) {
        $users[] = $user;
    }
    
    $total_emails_sent = 0;
    $total_notifications = 0;
    $events_processed = 0;
    
    // Proses setiap event
    while ($event = $events_result->fetch_assoc()) {
        $event_id = $event['id'];
        $event_title = htmlspecialchars($event['title']);
        $event_start = $event['start_event'];
        $event_end = $event['end_event'];
        $event_description = $event['description'] ?? '';
        
        // Format tanggal
        $start_formatted = date('d F Y H:i', strtotime($event_start));
        $end_formatted = date('d F Y H:i', strtotime($event_end));
        
        $event_link = APP_BASE_URL . "index.php?page=task_calendar";
        $notification_message = "Reminder: Event '{$event_title}' akan dimulai besok ({$start_formatted})";
        
        // Kirim ke setiap user
        foreach ($users as $user) {
            $user_id = $user['id'];
            $user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
            $user_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];
            
            // Notifikasi dalam aplikasi
            record_notification(
                $user_id,
                5,
                $notification_message,
                "index.php?page=task_calendar",
                $conn,
                false
            );
            $total_notifications++;
            
            // Email
            if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                $email_subject = "⏰ Reminder: Event {$event_title} - " . date('d M Y', strtotime($event_start));
                
                $email_body = "
                    <h3>Reminder: {$event_title}</h3>
                    <p>Event ini akan dimulai <strong>besok</strong>.</p>
                    <p><strong>Waktu:</strong> {$start_formatted} - {$end_formatted}</p>
                ";
                
                if (!empty($event_description)) {
                    $email_body .= "<p><strong>Deskripsi:</strong><br>" . nl2br(htmlspecialchars($event_description)) . "</p>";
                }
                
                $email_body .= "<p><a href='{$event_link}' style='color:#B75301;'>Lihat di Calendar</a></p>";
                
                // Gunakan fungsi send_task_notification_email yang sudah ada
                require_once 'phpmailer_config.php';
                $email_sent = send_task_notification_email(
                    $user_email,
                    $user_name,
                    $email_subject,
                    $email_body
                );
                
                if ($email_sent) {
                    $total_emails_sent++;
                }
            }
        }
        
        // Update status
        $update_query = "
            UPDATE events 
            SET notification_sent = TRUE, 
                notification_sent_at = NOW() 
            WHERE id = {$event_id}
        ";
        
        if ($conn->query($update_query)) {
            $events_processed++;
        }
    }
    
    return [
        'status' => 'success',
        'message' => "Berhasil mengirim {$total_emails_sent} email dan {$total_notifications} notifikasi untuk {$events_processed} event.",
        'data' => [
            'emails_sent' => $total_emails_sent,
            'notifications_created' => $total_notifications,
            'events_processed' => $events_processed
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Event Reminder</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .event-card {
            border-left: 4px solid #B75301;
            margin-bottom: 10px;
        }
        .btn-primary {
            background-color: #B75301;
            border-color: #B75301;
        }
        .btn-primary:hover {
            background-color: #964000;
            border-color: #964000;
        }
        .status-badge {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 12px;
        }
        .status-sent {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h3 class="mb-0">
                            <i class="fas fa-bell mr-2"></i>Manual Event Reminder
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Form Pilih Tanggal -->
                        <form method="POST" class="mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <label><strong>Pilih Tanggal Event:</strong></label>
                                    <div class="input-group">
                                        <input type="date" name="target_date" class="form-control" 
                                               value="<?= $target_date ?>" required>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Cek Events
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Sistem akan mengecek event yang mulai pada tanggal ini.
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <label><strong>Quick Select:</strong></label>
                                    <div>
                                        <a href="?page=manual_reminder&target_date=<?= date('Y-m-d', strtotime('+2 days')) ?>" 
                                           class="btn btn-sm btn-outline-primary mr-2">+2 Hari</a>
                                        <a href="?page=manual_reminder&target_date=<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                                           class="btn btn-sm btn-outline-primary mr-2">+1 Hari</a>
                                        <a href="?page=manual_reminder&target_date=<?= date('Y-m-d', strtotime('+3 days')) ?>" 
                                           class="btn btn-sm btn-outline-primary">+3 Hari</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Hasil Trigger -->
                        <?php if ($trigger_result): ?>
                        <div class="alert alert-<?= $trigger_result['status'] == 'success' ? 'success' : 'info' ?>">
                            <h5><i class="fas fa-check-circle"></i> Hasil Pengiriman</h5>
                            <p><?= $trigger_result['message'] ?></p>
                            <?php if (isset($trigger_result['data'])): ?>
                            <ul>
                                <li>Event diproses: <?= $trigger_result['data']['events_processed'] ?></li>
                                <li>Email dikirim: <?= $trigger_result['data']['emails_sent'] ?></li>
                                <li>Notifikasi dibuat: <?= $trigger_result['data']['notifications_created'] ?></li>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Daftar Events -->
                        <div class="mt-4">
                            <h5>
                                <i class="fas fa-calendar-alt mr-2"></i>
                                Events pada <?= date('d F Y', strtotime($target_date)) ?>
                                <span class="badge badge-primary ml-2"><?= $total_events ?> events</span>
                            </h5>
                            
                            <?php if ($total_events > 0): ?>
                                <div class="row mt-3">
                                    <?php while ($event = $events_result->fetch_assoc()): ?>
                                    <div class="col-md-6">
                                        <div class="card event-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <h6 class="card-title mb-1"><?= htmlspecialchars($event['title']) ?></h6>
                                                    <span class="status-badge <?= $event['notification_sent'] ? 'status-sent' : 'status-pending' ?>">
                                                        <?= $event['notification_sent'] ? 'Terkirim' : 'Belum' ?>
                                                    </span>
                                                </div>
                                                <p class="card-text small text-muted mb-2">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?= date('H:i', strtotime($event['start_event'])) ?> - 
                                                    <?= date('H:i', strtotime($event['end_event'])) ?>
                                                </p>
                                                <?php if ($event['description']): ?>
                                                <p class="card-text small"><?= substr(htmlspecialchars($event['description']), 0, 100) ?>...</p>
                                                <?php endif; ?>
                                                <?php if ($event['notification_sent_at']): ?>
                                                <p class="card-text small">
                                                    <i class="fas fa-paper-plane mr-1"></i>
                                                    Terakhir dikirim: <?= date('d/m/Y H:i', strtotime($event['notification_sent_at'])) ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                
                                <!-- Tombol Trigger -->
                                <div class="text-center mt-4">
                                    <a href="?page=manual_reminder&action=send&date=<?= $target_date ?>" 
                                       class="btn btn-primary btn-lg"
                                       onclick="return confirm('Kirim reminder untuk <?= $total_events ?> event ini ke semua user?')">
                                        <i class="fas fa-paper-plane mr-2"></i>Kirim Reminder Sekarang
                                    </a>
                                    <p class="text-muted small mt-2">
                                        Akan mengirim ke semua user (<?= $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?> user)
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Tidak ada event yang dimulai pada tanggal <?= date('d F Y', strtotime($target_date)) ?>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Info Sistem -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Informasi Sistem</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-cog mr-2"></i>Konfigurasi</h6>
                                <ul class="small">
                                    <li>Notifikasi dikirim 2 hari sebelum event dimulai</li>
                                    <li>Semua user akan menerima notifikasi</li>
                                    <li>Notifikasi dikirim via email dan dalam aplikasi</li>
                                    <li>Email dikirim ke: notification_email (jika ada) atau email</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-history mr-2"></i>Log Terakhir</h6>
                                <?php
                                $log_query = "
                                    SELECT e.title, e.notification_sent_at, COUNT(n.id) as notifications
                                    FROM events e
                                    LEFT JOIN notification_list n ON n.link LIKE CONCAT('%event&id=', e.id)
                                    WHERE e.notification_sent = TRUE
                                    GROUP BY e.id
                                    ORDER BY e.notification_sent_at DESC
                                    LIMIT 5
                                ";
                                $log_result = $conn->query($log_query);
                                ?>
                                <?php if ($log_result && $log_result->num_rows > 0): ?>
                                <ul class="small">
                                    <?php while ($log = $log_result->fetch_assoc()): ?>
                                    <li>
                                        <?= substr($log['title'], 0, 30) ?>...
                                        <span class="text-muted">(<?= date('d/m', strtotime($log['notification_sent_at'])) ?>)</span>
                                    </li>
                                    <?php endwhile; ?>
                                </ul>
                                <?php else: ?>
                                <p class="small text-muted">Belum ada log pengiriman</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>