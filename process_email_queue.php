<?php
// FILE: process_email_queue.php
// Pemroses antrian email chat di latar belakang (Background Worker / Cron)
session_start();

// Security key untuk akses via web cron / curl (samakan dengan konvensi reminder)
$SECRET_KEY = 'secret123';

$is_cli = php_sapi_name() === 'cli';
$is_valid_url = isset($_GET['key']) && $_GET['key'] === $SECRET_KEY;

if (!$is_cli && !$is_valid_url) {
    // Jika diakses via browser tanpa secret key, harus login
    if (!isset($_SESSION['login_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        exit;
    }
}

require_once 'db_connect.php';
require_once 'phpmailer_config.php';

date_default_timezone_set('Asia/Jakarta');

// Proses maksimal 15 email per batch untuk mencegah timeout
$result = process_chat_email_queue($conn, 15);

if ($is_cli) {
    $now = date('Y-m-d H:i:s');
    echo "[{$now}] Chat Email Queue Processed: Sent={$result['sent']}, Skipped={$result['skipped']}, Failed={$result['failed']}\n";
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $result
    ]);
}
