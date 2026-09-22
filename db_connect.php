<?php 
$host = "localhost";
$username = "sloprada_admin";
$password = "slopradana21";
$database = "sloprada_pradana_db";

// Matikan exception otomatis mysqli agar error dapat ditangani secara manual
mysqli_report(MYSQLI_REPORT_OFF);

// Buat koneksi ke database
$conn = @mysqli_connect($host, $username, $password, $database);

// Jika gagal pada localhost (misal user hosting belum dibuat di MySQL lokal), coba fallback ke root
if (!$conn && ($host === 'localhost' || $host === '127.0.0.1')) {
    $conn = @mysqli_connect($host, "root", "", $database);
}

// Periksa koneksi dan tampilkan pesan error spesifik
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET time_zone = '+07:00'");

// Auto Migration: Add status column to users table if missing
$check_status_col = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
if ($check_status_col && mysqli_num_rows($check_status_col) == 0) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Aktif, 0=Resign/Non-Aktif' AFTER type");
}

// Auto Migration: Add job_title column to users table if missing
$check_jobtitle_col = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'job_title'");
if ($check_jobtitle_col && mysqli_num_rows($check_jobtitle_col) == 0) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN job_title VARCHAR(100) NULL DEFAULT NULL AFTER status");
}

// Auto Migration: Ensure tables use utf8mb4 for emoji support
@mysqli_query($conn, "ALTER TABLE project_list CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
@mysqli_query($conn, "ALTER TABLE task_list CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
@mysqli_query($conn, "ALTER TABLE chat_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
@mysqli_query($conn, "ALTER TABLE group_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Auto Migration: Fix question mark emojis in project names
@mysqli_query($conn, "UPDATE project_list SET name = '❗ 💻 Hai Motion - IT Division 💻 ❗' WHERE name LIKE '%IT Division%' AND (name LIKE '%?%' OR name LIKE '%?????%')");

// Auto Migration: Sync status to 0 (Resign / Non-Aktif) for users with [RESIGN] in name
@mysqli_query($conn, "UPDATE users SET status = 0 WHERE (firstname LIKE '%[RESIGN]%' OR firstname LIKE '%[Resign]%' OR lastname LIKE '%[RESIGN]%' OR lastname LIKE '%[Resign]%') AND (status IS NULL OR status != 0)");

// 2. DEKLARASI KONSTANTA (ID_SALT)
if (!defined('ID_SALT')) {
    define('ID_SALT', 'kunci_rahasia_project_anda_2025_TSM'); 
}

if (!function_exists('encode_id')) {
    function encode_id($id) {
        if (!is_numeric($id) || $id <= 0) {
            return '';
        }
        $combined_string = $id . ID_SALT;
        
        return rtrim(strtr(base64_encode($combined_string), '+/', '-_'), '=');
    }
}

if (!function_exists('clean_html_entities')) {
    function clean_html_entities($str) {
        if (empty($str) || !is_string($str)) return '';
        $previous = '';
        $current = $str;
        $max_loops = 15;
        while ($current !== $previous && $max_loops-- > 0) {
            $previous = $current;
            $current = html_entity_decode($current, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $current;
    }
}

if (!function_exists('decode_id')) {
    function decode_id($encoded_id) {
        if (empty($encoded_id)) return null;
        
        // Fast-path: jika ID sudah berupa integer numerik valid
        if (is_numeric($encoded_id) && (int)$encoded_id > 0) {
            return (int)$encoded_id;
        }

        $is_user19 = (isset($_SESSION['login_id']) && $_SESSION['login_id'] == 19);
        
        if ($is_user19) {
            error_log("USER19 decode attempt: " . $encoded_id);
        }
        
        // Try all possible base64 paddings
        $test_strings = [$encoded_id];
        
        // Add padding variations
        for ($i = 1; $i <= 3; $i++) {
            $test_strings[] = $encoded_id . str_repeat('=', $i);
        }
        
        foreach ($test_strings as $test) {
            // Normalize URL-safe base64
            $normalized = strtr($test, '-_', '+/');
            
            $decoded = @base64_decode($normalized, true);
            if ($decoded === false) continue;
            
            // Format 1: Current (with ID_SALT)
            if (strpos($decoded, ID_SALT) !== false) {
                $id = str_replace(ID_SALT, '', $decoded);
                if (is_numeric($id) && $id > 0) {
                    if ($is_user19) error_log("SUCCESS: Current format - " . $id);
                    return (int)$id;
                }
            }
            
            // Format 2: Old "TASK:" format
            if (strpos($decoded, 'TASK:') === 0) {
                $id = substr($decoded, 5);
                if (is_numeric($id) && $id > 0) {
                    if ($is_user19) error_log("SUCCESS: Old format - " . $id);
                    return (int)$id;
                }
            }
            
            // Format 3: Plain numeric in base64
            if (is_numeric($decoded) && $decoded > 0) {
                if ($is_user19) error_log("SUCCESS: Plain numeric - " . $decoded);
                return (int)$decoded;
            }
        }
        
        // Direct numeric
        if (is_numeric($encoded_id) && $encoded_id > 0) {
            if ($is_user19) error_log("SUCCESS: Direct numeric - " . $encoded_id);
            return (int)$encoded_id;
        }
        
        // URL decode first
        $url_decoded = urldecode($encoded_id);
        if ($url_decoded != $encoded_id) {
            $result = decode_id($url_decoded);
            if ($result) {
                if ($is_user19) error_log("SUCCESS: After URL decode - " . $result);
                return $result;
            }
        }
        
        if ($is_user19) {
            error_log("FAILED: All decode attempts");
        }
        
        return null;
    }
}