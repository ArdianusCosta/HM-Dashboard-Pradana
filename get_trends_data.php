<?php
session_start();
include('db_connect.php');

// 1. Cek Login & Parameter
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['login_id'];
$login_type = $_SESSION['login_type'];
$mode = $_POST['mode'] ?? 'month'; // week, month, day
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

// 2. Setup Rentang Waktu (Start & End)
$period_label = "";
$labels = [];
$full_dates = []; // Array bantu untuk pencocokan data
$date_format_sql = "%Y-%m-%d"; // Format default (Week/Month)

$now = new DateTime();

if ($mode == 'month') {
    // --- MODE BULANAN (Tgl 1 - 31) ---
    $now->modify("$offset month");
    $start_date = $now->format('Y-m-01');
    $end_date = $now->format('Y-m-t'); // t = hari terakhir bulan
    $period_label = $now->format('F Y'); // Contoh: February 2026
    
    // Loop semua tanggal dalam bulan tersebut
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );
    foreach ($period as $dt) {
        $labels[] = $dt->format('d'); // Label X: Tanggal (01, 02..)
        $full_dates[] = $dt->format('Y-m-d'); // Key Database
    }

} elseif ($mode == 'day') {
    // --- MODE HARIAN (Jam 00:00 - 23:00) ---
    $now->modify("$offset day");
    $start_date = $now->format('Y-m-d 00:00:00');
    $end_date = $now->format('Y-m-d 23:59:59');
    $period_label = $now->format('d M Y'); // Contoh: 12 Feb 2026
    $date_format_sql = "%H"; // Group by JAM
    
    // Loop 24 Jam
    for ($i = 0; $i < 24; $i++) {
        $labels[] = sprintf("%02d:00", $i); // Label X: Jam
        $full_dates[] = sprintf("%02d", $i); // Key Database (00, 01..)
    }

} else {
    // --- DEFAULT: WEEK (Senin - Minggu) ---
    $iso_year = $now->format('o');
    $iso_week = $now->format('W');
    $now->setISODate($iso_year, $iso_week + $offset); // Geser minggu sesuai offset
    
    $start_date = $now->format('Y-m-d'); // Senin
    $end_date = (clone $now)->modify('+6 days')->format('Y-m-d'); // Minggu
    $period_label = $now->format('d M') . " - " . (clone $now)->modify('+6 days')->format('d M Y');
    
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );
    foreach ($period as $dt) {
        $labels[] = $dt->format('D'); // Label X: Mon, Tue..
        $full_dates[] = $dt->format('Y-m-d'); // Key Database
    }
}

// 3. Siapkan Struktur Data Series (Termasuk Status 5 DONE)
// Kita isi default 0 agar grafik nyambung walau tidak ada data
$count_points = count($labels);
$series_config = [
    0 => ['name' => 'Pending',     'data' => array_fill(0, $count_points, 0)],
    1 => ['name' => 'Started',     'data' => array_fill(0, $count_points, 0)],
    2 => ['name' => 'On-Progress', 'data' => array_fill(0, $count_points, 0)],
    3 => ['name' => 'On-Hold',     'data' => array_fill(0, $count_points, 0)],
    4 => ['name' => 'Overdue',     'data' => array_fill(0, $count_points, 0)],
    5 => ['name' => 'Done',        'data' => array_fill(0, $count_points, 0)]  // <--- BARU
];

// 4. Query Database
// Filter Permission (Sama seperti home.php)
$user_filter = "";
if ($login_type == 2) {
    // Manager: Project dia atau task dia
    $user_filter = " AND (p.manager_id = '$user_id' OR FIND_IN_SET('$user_id', t.user_ids) > 0) ";
} elseif ($login_type == 3) {
    // Staff: Hanya task dia
    $user_filter = " AND FIND_IN_SET('$user_id', t.user_ids) > 0 ";
}

// Query Utama
$sql = "SELECT 
            t.status, 
            DATE_FORMAT(COALESCE(t.date_updated, t.date_created), '$date_format_sql') as time_key, 
            COUNT(*) as total
        FROM task_list t
        INNER JOIN project_list p ON p.id = t.project_id
        WHERE COALESCE(t.date_updated, t.date_created) BETWEEN '$start_date' AND '$end_date 23:59:59'
        $user_filter
        GROUP BY t.status, time_key";

$query = $conn->query($sql);

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $status = intval($row['status']);
        $time_key = $row['time_key']; // Tanggal (2026-02-12) atau Jam (14)
        $total = intval($row['total']);

        // Cari posisi index array berdasarkan waktu
        $index = array_search($time_key, $full_dates);

        // Jika data valid dan status dikenali, update angka 0 jadi total asli
        if ($index !== false && isset($series_config[$status])) {
            $series_config[$status]['data'][$index] = $total;
        }
    }
}

// 5. Kirim Balikan JSON ke AJAX
$response = [
    'period_label' => $period_label,
    'labels' => $labels,
    'series' => array_values($series_config) // Re-index array jadi [0,1,2..]
];

echo json_encode($response);
?>