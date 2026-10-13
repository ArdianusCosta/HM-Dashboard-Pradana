    <?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}


/* ==============================
   KONFIGURASI SMTP SERVER
   ============================== */
define('SMTP_HOST', 'mail.haimotion.com');
define('SMTP_USERNAME', 'dashboard@haimotion.com'); 
define('SMTP_PASSWORD', 'VHF9J}V,?[1%[#Hn'); 
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); 

define('EMAIL_FROM', 'dashboard@haimotion.com');
define('EMAIL_FROM_NAME', 'HaiMotion Dashboard');
define('APP_BASE_URL', 'https://dashboard.haimotion.com/');
define('MAIL_NAME', 'HaiMotion');

/* ==============================
   FUNGSI KIRIM EMAIL
   ============================== */
function send_task_notification_email($recipient_email, $recipient_name, $subject, $body_html) {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer class not found.");
        return false;
    }
    
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Timeout    = 3; // 3 second timeout for socket connection to prevent hanging
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->Sender     = EMAIL_FROM;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->addReplyTo('support@haimotion.com', 'HaiMotion Support');
        $mail->addCustomHeader('X-Mailer', 'HaiMotion Notifier 1.0');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->addEmbeddedImage(
            __DIR__ . '/assets/logo3bw.png',
            'logo_cid',
            'logo3bw.png'
        );

        $email_template = "
            <div style='font-family: Arial, sans-serif; color: #333; max-width:600px; margin:auto; border:1px solid #eee; border-radius:10px; overflow:hidden;'>

                <div style='background:#B75301; padding:30px; text-align:center; box-sizing:border-box;'>
                    <img src='cid:logo_cid' 
                        alt='HaiMotion'
                        style='width:100%; max-width:480px; height:auto; display:block; margin:0 auto; border:0; outline:none; text-decoration:none;'>
                </div>

                <div style='padding:20px;'>
                    <p>Halo <strong>{$recipient_name}</strong>,</p>
                    <p>{$body_html}</p>
                    <p style='margin-top:20px;'>
                        <a href='" . APP_BASE_URL . "' 
                        style='background:#B75301; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block;'>
                        Open Dashboard
                        </a>
                    </p>
                </div>

                <div style='background:#f9f9f9; padding:15px; font-size:12px; color:#777; text-align:center;'>
                    This email is an automated notification from <strong>Hai Motion Dashboard</strong>.<br>
                    Please do not reply
                </div>
            </div>
            ";

        $mail->Subject = $subject;
        $mail->Body    = $email_template;
        $mail->AltBody = strip_tags($body_html);
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function record_notification($user_id, $type, $message, $link, $conn, $send_email = false, $email_details = []) {
    $message_db = $conn->real_escape_string($message);
    $link_db = $conn->real_escape_string($link);
    $user_id_db = intval($user_id);
    $type_db = intval($type);

    $query = "
        INSERT INTO notification_list (user_id, type, message, link)
        VALUES ('{$user_id_db}', '{$type_db}', '{$message_db}', '{$link_db}')
    ";

    $insert_success = $conn->query($query);

    if ($insert_success && $send_email && !empty($email_details) && isset($email_details['email'])) {
        $full_link = APP_BASE_URL . $link;
        $html_message = "
            Anda mendapat notifikasi baru: <strong>{$message}</strong><br>
            Lihat detail di sini: <a href='{$full_link}'>{$full_link}</a>
        ";

        try {
            send_task_notification_email(
                $email_details['email'],
                $email_details['name'] ?? 'User',
                $email_details['subject'] ?? 'Notifikasi Baru dari HaiMotion Dashboard',
                $html_message
            );
        } catch (Throwable $e) {
            error_log("Email sending error logged: " . $e->getMessage());
        }
    }
}

/* ==============================
   SISTEM ANTRIAN EMAIL CHAT (ASYNC QUEUE)
   ============================== */
function queue_chat_notification_email($recipient_id, $recipient_email, $recipient_name, $subject, $message, $link, $chat_type, $source_id, $conn) {
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $recipient_id   = intval($recipient_id);
    $recipient_email= $conn->real_escape_string($recipient_email);
    $recipient_name = $conn->real_escape_string($recipient_name);
    $subject        = $conn->real_escape_string($subject);
    $message        = $conn->real_escape_string($message);
    $link           = $conn->real_escape_string($link);
    $chat_type      = ($chat_type === 'group') ? 'group' : 'personal';
    $source_id      = intval($source_id);

    $sql = "INSERT INTO chat_email_queue (recipient_id, recipient_email, recipient_name, subject, message_html, link, chat_type, source_id, status)
            VALUES ('{$recipient_id}', '{$recipient_email}', '{$recipient_name}', '{$subject}', '{$message}', '{$link}', '{$chat_type}', '{$source_id}', 'pending')";
    return $conn->query($sql);
}

function process_chat_email_queue($conn, $max_batch = 10) {
    $results = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
    
    // Ambil ID email yang berstatus pending
    $qry = $conn->query("
        SELECT id FROM chat_email_queue 
        WHERE status = 'pending' AND attempts < 3 
        ORDER BY created_at ASC 
        LIMIT {$max_batch}
    ");

    if (!$qry || $qry->num_rows == 0) {
        return $results;
    }

    $ids = [];
    while ($r = $qry->fetch_assoc()) {
        $ids[] = intval($r['id']);
    }

    $ids_str = implode(',', $ids);
    
    // Lock item menjadi 'processing'
    $lock = $conn->query("UPDATE chat_email_queue SET status = 'processing' WHERE id IN ({$ids_str}) AND status = 'pending'");
    if (!$lock) {
        return $results;
    }

    // Ambil data item yang berhasil di-lock
    $items_qry = $conn->query("SELECT * FROM chat_email_queue WHERE id IN ({$ids_str}) AND status = 'processing'");
    if (!$items_qry) {
        return $results;
    }
    
    while ($item = $items_qry->fetch_assoc()) {
        $results['processed']++;
        $id = $item['id'];
        $recipient_id = intval($item['recipient_id']);
        $source_id = intval($item['source_id']);
        $chat_type = $item['chat_type'];
        $created_at = $item['created_at'];

        // Cek Cerdas: Apakah penerima sudah membaca pesan ini di browser?
        $already_read = false;
        if ($chat_type === 'group') {
            $read_check = $conn->query("
                SELECT last_read_at FROM user_group_read_status 
                WHERE group_id = '{$source_id}' AND user_id = '{$recipient_id}'
            ");
            if ($read_check && $read_row = $read_check->fetch_assoc()) {
                if (!empty($read_row['last_read_at']) && strtotime($read_row['last_read_at']) >= strtotime($created_at)) {
                    $already_read = true;
                }
            }
        } else {
            // Personal chat: cek apakah pesan setelah created_at sudah dibaca (is_read = 1)
            $read_check = $conn->query("
                SELECT COUNT(*) as unread_c FROM chat_messages 
                WHERE thread_id = '{$source_id}' AND sender_id != '{$recipient_id}' AND is_read = 0 AND created_at >= '{$created_at}'
            ");
            if ($read_check && $read_row = $read_check->fetch_assoc()) {
                if (intval($read_row['unread_c']) == 0) {
                    $already_read = true;
                }
            }
        }

        if ($already_read) {
            $conn->query("UPDATE chat_email_queue SET status = 'skipped', sent_at = NOW(), error_message = 'Already read by recipient in chat' WHERE id = '{$id}'");
            $results['skipped']++;
            continue;
        }

        // Jika belum dibaca, kirim email notifikasi
        $full_link = APP_BASE_URL . $item['link'];
        $html_message = "
            Anda mendapat notifikasi pesan baru: <strong>" . htmlspecialchars($item['message_html'], ENT_QUOTES, 'UTF-8') . "</strong><br><br>
            Buka percakapan langsung di sini: <br>
            <a href='{$full_link}' style='display:inline-block; margin-top:10px; background:#B75301; color:#fff; padding:8px 16px; text-decoration:none; border-radius:4px;'>Buka Chat</a>
        ";

        try {
            $send_ok = send_task_notification_email(
                $item['recipient_email'],
                $item['recipient_name'],
                $item['subject'],
                $html_message
            );

            if ($send_ok) {
                $conn->query("UPDATE chat_email_queue SET status = 'sent', sent_at = NOW(), error_message = NULL WHERE id = '{$id}'");
                $results['sent']++;
            } else {
                $conn->query("UPDATE chat_email_queue SET attempts = attempts + 1, status = IF(attempts >= 3, 'failed', 'pending'), error_message = 'SMTP Send Failed' WHERE id = '{$id}'");
                $results['failed']++;
            }
        } catch (Throwable $e) {
            $err = $conn->real_escape_string($e->getMessage());
            $conn->query("UPDATE chat_email_queue SET attempts = attempts + 1, status = IF(attempts >= 3, 'failed', 'pending'), error_message = '{$err}' WHERE id = '{$id}'");
            $results['failed']++;
        }

        usleep(150000); // 0.15s jeda
    }

    return $results;
}

/* ==============================
   SISTEM REMINDER UNTUK CUSTOM EVENTS
   ============================== */
function send_event_reminders_to_all_users($conn) {
    date_default_timezone_set('Asia/Jakarta');
    
    $two_days_later = date('Y-m-d', strtotime('+2 days'));
    $current_time = date('Y-m-d H:i:s');
    
    error_log("[" . $current_time . "] Starting event reminders for date: " . $two_days_later);
    
    $events_query = "
        SELECT e.id, e.title, e.start_event, e.end_event, e.description, e.color
        FROM events e
        WHERE DATE(e.start_event) = '{$two_days_later}'
        AND (e.notification_sent = FALSE OR e.notification_sent IS NULL)
        ORDER BY e.start_event
    ";
    
    $events_result = $conn->query($events_query);
    
    if (!$events_result) {
        error_log("Database error: " . $conn->error);
        return ['status' => 'error', 'message' => 'Database error'];
    }
    
    if ($events_result->num_rows === 0) {
        error_log("No events found for date: " . $two_days_later);
        return ['status' => 'info', 'message' => 'Tidak ada event yang perlu dikirim reminder hari ini.'];
    }
    
    $users_query = "SELECT id, firstname, lastname, email, notification_email FROM users";
    $users_result = $conn->query($users_query);
    
    if (!$users_result || $users_result->num_rows === 0) {
        error_log("No users found in database");
        return ['status' => 'error', 'message' => 'Tidak ada user yang ditemukan.'];
    }
    
    $users = [];
    while ($user = $users_result->fetch_assoc()) {
        $users[] = $user;
    }
    
    $total_emails_sent = 0;
    $total_notifications = 0;
    $events_processed = 0;
    
    while ($event = $events_result->fetch_assoc()) {
        $event_id = $event['id'];
        $event_title = htmlspecialchars($event['title']);
        $event_start = $event['start_event'];
        $event_end = $event['end_event'];
        $event_description = $event['description'] ?? '';
        
        $start_formatted = date('d F Y H:i', strtotime($event_start));
        $end_formatted = date('d F Y H:i', strtotime($event_end));
        
        $event_link = APP_BASE_URL . "index.php?page=task_calendar";
        
        $notification_message = "Reminder: Event '{$event_title}' akan dimulai besok ({$start_formatted})";
        
        foreach ($users as $user) {
            $user_id = $user['id'];
            $user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
            $user_email = !empty($user['notification_email']) ? $user['notification_email'] : $user['email'];
            
            record_notification(
                $user_id,
                5,
                $notification_message,
                "index.php?page=task_calendar",
                $conn,
                false
            );
            $total_notifications++;
            
            if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                $email_subject = "⏰ Reminder: Event {$event_title} - " . date('d M Y', strtotime($event_start));
                
                $email_body = "
                    <h3>Reminder: {$event_title}</h3>
                    <p>Event ini akan dimulai dalam <strong>2 hari lagi</strong>.</p>
                    <p><strong>Waktu:</strong> {$start_formatted} - {$end_formatted}</p>
                ";
                
                if (!empty($event_description)) {
                    $email_body .= "<p><strong>Deskripsi:</strong><br>" . nl2br(htmlspecialchars($event_description)) . "</p>";
                }
                
                $email_body .= "<p><a href='{$event_link}' style='color:#B75301;'>Lihat di Calendar</a></p>";
                
                $email_sent = send_task_notification_email(
                    $user_email,
                    $user_name,
                    $email_subject,
                    $email_body
                );
                
                if ($email_sent) {
                    $total_emails_sent++;
                    error_log("Email sent to {$user_email} for event: {$event_title}");
                } else {
                    error_log("Failed to send email to {$user_email}");
                }
            }
        }
        
        $update_query = "
            UPDATE events 
            SET notification_sent = TRUE, 
                notification_sent_at = NOW() 
            WHERE id = {$event_id}
        ";
        
        if ($conn->query($update_query)) {
            $events_processed++;
            error_log("Event {$event_id} marked as notified");
        } else {
            error_log("Failed to update event {$event_id}: " . $conn->error);
        }
    }
    
    $result_message = "Berhasil mengirim {$total_emails_sent} email dan {$total_notifications} notifikasi untuk {$events_processed} event.";
    error_log("Reminder process completed: " . $result_message);
    
    return [
        'status' => 'success',
        'message' => $result_message,
        'data' => [
            'emails_sent' => $total_emails_sent,
            'notifications_created' => $total_notifications,
            'events_processed' => $events_processed,
            'target_date' => $two_days_later
        ]
    ];
}
?>