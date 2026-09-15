<?php
// test_reminder.php - File untuk testing tanpa cron
require_once 'db_connect.php';
require_once 'phpmailer_config.php';

// Cek login admin
session_start();
if (!isset($_SESSION['login_type']) || $_SESSION['login_type'] != 1) {
    die('Admin only');
}

// Force tanggal tertentu untuk testing
$_GET['force_date'] = isset($_GET['force_date']) ? $_GET['force_date'] : date('Y-m-d', strtotime('+2 days'));

// Override fungsi date() untuk testing
if (isset($_GET['force_date'])) {
    $force_timestamp = strtotime($_GET['force_date'] . ' -2 days');
    
    // Buat fungsi date override
    if (!function_exists('test_date')) {
        function test_date($format, $timestamp = null) {
            global $force_timestamp;
            if ($timestamp === null) {
                $timestamp = $force_timestamp;
            }
            return date($format, $timestamp);
        }
    }
    
    // Simpan fungsi asli
    $GLOBALS['original_date_func'] = 'date';
    
    // Override fungsi date() sementara
    eval('function date($format, $timestamp = null) {
        global $force_timestamp;
        if ($timestamp === null) {
            $timestamp = $force_timestamp;
        }
        return \date($format, $timestamp);
    }');
}

echo "<h2>Test Event Reminder System</h2>";
echo "<p>Testing untuk tanggal: " . date('Y-m-d', strtotime('+2 days')) . "</p>";

// Jalankan sistem
$result = send_event_reminders_to_all_users($conn);

echo "<pre>";
print_r($result);
echo "</pre>";

// Link untuk test tanggal berbeda
echo "<h3>Test dengan tanggal berbeda:</h3>";
echo "<a href='?force_date=" . date('Y-m-d', strtotime('+1 day')) . "'>Besok</a> | ";
echo "<a href='?force_date=" . date('Y-m-d', strtotime('+2 days')) . "'>2 Hari Lagi</a> | ";
echo "<a href='?force_date=" . date('Y-m-d', strtotime('+3 days')) . "'>3 Hari Lagi</a>";

$conn->close();
?>