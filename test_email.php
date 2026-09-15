<?php
require_once 'phpmailer_config.php';

$to = 'it.haimotion@gmail.com';
$name = 'Tester';
$subject = 'Tes Email dari HaiMotion';
$body = '<h3>Halo!</h3><p>Email ini dikirim dari localhost via PHPMailer 🎉</p>';

try {
    if (send_task_notification_email($to, $name, $subject, $body)) {
        echo "✅ Email berhasil dikirim ke $to";
    } else {
        echo "❌ Gagal mengirim email.";
        // Check error log for details
        if (file_exists('error_log')) {
            $log = file_get_contents('error_log');
            echo "\n\nError Log:\n" . substr($log, -500); // Last 500 chars
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage();
}
?>
