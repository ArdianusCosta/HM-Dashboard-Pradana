<?php 
$host = "localhost"; 
$username = "root"; 
$password = ""; 
$database = "sloprada_pradana_db"; 

// Buat koneksi ke database
$conn = mysqli_connect($host, $username, $password, $database);


// Periksa koneksi dan tampilkan pesan error spesifik
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
mysqli_query($conn, "SET time_zone = '+07:00'");

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

if (!function_exists('decode_id')) {
    function decode_id($encoded_id) {
        if (empty($encoded_id)) return null;
        
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