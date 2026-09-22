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