<?php
// send_event_reminders.php
session_start();

// Security key untuk mencegah akses sembarangan
$SECRET_KEY = 'secret123';

// Cek jika diakses via cron (CLI) atau via URL dengan key yang benar
$is_cli = php_sapi_name() === 'cli';
$is_valid_url = isset($_GET['key']) && $_GET['key'] === $SECRET_KEY;

if (!$is_cli && !$is_valid_url) {
    // Jika diakses via browser tanpa key, cek login admin
    if (!isset($_SESSION['login_type']) || $_SESSION['login_type'] != 1) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        exit;
    }
}

require_once 'db_connect.php';
require_once 'phpmailer_config.php';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Start output buffering untuk capture semua output
ob_start();

echo "=== Event Reminder System ===\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n";
echo "Target tanggal: " . date('Y-m-d', strtotime('+2 days')) . "\n";
echo str_repeat("=", 40) . "\n\n";

// Jalankan reminder system
$result = send_event_reminders_to_all_users($conn);

// Tampilkan hasil
echo "\nHasil: " . $result['message'] . "\n";

if (isset($result['data'])) {
    echo "\nDetail:\n";
    foreach ($result['data'] as $key => $value) {
        echo "- " . ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
    }
}

echo "\nSelesai pada: " . date('Y-m-d H:i:s') . "\n";

// Capture semua output
$output = ob_get_clean();

// Jika CLI, output ke console
if ($is_cli) {
    echo $output;
} else {
    // Jika browser, tampilkan format HTML
    echo "<pre style='background:#f8f9fa; padding:15px; border-radius:5px;'>" . htmlspecialchars($output) . "</pre>";
    
    // Link untuk test manual
    echo '<br><a href="?key=' . $SECRET_KEY . '&test=1" class="btn btn-sm btn-primary">Test Lagi</a>';
    echo ' | <a href="index.php?page=manual_reminder" class="btn btn-sm btn-secondary">Manual Reminder</a>';
}

// Juga simpan ke log file
$log_file = 'logs/event_reminders.log';
$log_entry = date('Y-m-d H:i:s') . " - " . $result['message'] . "\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

$conn->close();
?>