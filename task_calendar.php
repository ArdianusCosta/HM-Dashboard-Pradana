<?php
// task_calendar.php
include 'header.php';
include 'db_connect.php';

if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_type'])) {
  if (session_status() == PHP_SESSION_NONE) {
    session_start();
  }
  if (!isset($_SESSION['login_id']) || !isset($_SESSION['login_type'])) {
    die('Unauthorized access.');
  }
}

$current_user_id = $_SESSION['login_id'] ?? 0;
$login_type = $_SESSION['login_type'] ?? 0;

// 1. Build project restriction based on role
$where = "";
if ($login_type == 1) {
    // Admin: no restriction – see all projects
    $where = "";
} elseif ($login_type == 2) {
    $where = " WHERE manager_id = '{$current_user_id}' OR FIND_IN_SET('{$current_user_id}', user_ids) ";
} elseif ($login_type == 3 || $login_type == 4) {
    $where = " WHERE FIND_IN_SET('{$current_user_id}', user_ids) ";
}
// 2. Ambil daftar project yang diizinkan
$projects = [];
$project_q = $conn->query("SELECT id, name FROM project_list $where ORDER BY name ASC");
if ($project_q) {
  while ($row = $project_q->fetch_assoc())
    $projects[] = $row;
}
$allowed_project_ids = array_column($projects, 'id');

// --- MODIFIKASI: Default ke All Tasks ---
// Cek apakah user meminta mode spesifik
$encoded_project_id = $_GET['project_id'] ?? null;
$view_all = isset($_GET['view']) && $_GET['view'] == 'all';

// Jika tidak ada parameter URL, DEFAULT KE ALL TASKS
if (!$encoded_project_id && !$view_all) {
  $project_id = 0;
  $current_project_name = "All Tasks";
  $encoded_project_id = null;
} else {
  if ($view_all) {
    // Jika view=all, set project_id ke 0 (artinya semua project)
    $project_id = 0;
    $encoded_project_id = null;
    $current_project_name = "All Tasks";
  } else {
    // Dekode project_id dari URL jika ada
    $project_id = $encoded_project_id ? decode_id($encoded_project_id) : 0;

    // Validasi: Jika ID tidak valid, tetap ke All Tasks
    if (!in_array($project_id, $allowed_project_ids) || $project_id == 0) {
      $project_id = 0;
      $encoded_project_id = null;
      $current_project_name = "All Tasks";
    } else {
      // Set nama project yang valid
      $found_keys = array_keys(array_column($projects, 'id'), $project_id);
      $current_project_name = !empty($found_keys) ?
        $projects[$found_keys[0]]['name'] : "All Tasks";
    }
  }
}

// 4. Siapkan URL untuk FullCalendar events
$load_task_url = "loadtask.php";
if ($encoded_project_id) {
  $load_task_url .= "?project_id=" . urlencode($encoded_project_id);
}
?>
<!DOCTYPE html>
<html>

<head>
  <title>Task Calendar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Color Picker CSS -->
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/css/bootstrap-colorpicker.min.css">

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Color Picker JS -->
  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/js/bootstrap-colorpicker.min.js"></script>
  <!-- Google API -->
  <script src="https://apis.google.com/js/api.js"></script>

  <style>
      body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', sans-serif;
      }

      .calendar-container {
        max-width: 100%;
        margin: auto;
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      }

      .fc-day-header {
        background-color: #B75301;
        height: 50px;
        padding: 0;
        font-weight: bold;
        color: white;
        justify-content: center;
        align-items: center;
        text-align: center;
      }

      .fc-event-desc {
        font-size: 0.75rem;
        opacity: 0.9;
        margin-top: 2px;
        white-space: normal;
      }

      .fc-event.task-event {
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 8px;
        padding: 6px 10px;
        font-weight: 500;
        font-size: 14px;
        color: #111827;
        text-align: left;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        height: auto !important;
        line-height: 1.4;
      }

      .fc-event {
        border-radius: 8px;
        padding: 6px 10px;
        font-weight: 500;
        font-size: 14px;
        text-align: left;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        height: auto !important;
        line-height: 1.4;
        min-height: 40px;
      }

      .fc-event.bg-primary,
      .fc-event.bg-success,
      .fc-event.bg-warning,
      .fc-event.bg-danger {
        color: #fff !important;
      }

      .fc-event.bg-success {
        cursor: default;
        background-color: #28a745 !important;
        border-color: #28a745 !important;
        padding: 4px 8px !important;
        margin: 2px 0 !important;
      }

      .fc-event.holiday-event {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
        color: white !important;
        cursor: default;
        padding: 4px 8px !important;
        margin: 2px 0 !important;
        font-weight: 500;
      }

      .fc-event.task-event {
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        color: #111827 !important;
        cursor: pointer;
      }

      .fc-event.custom-event {
        padding: 4px 8px !important;
        margin: 2px 0 !important;
        color: white !important;
        font-weight: 500;
      }

      .fc-event.task-event:hover {
        background-color: #f9fafb !important;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.12);
      }

      .fc-event .fc-title {
        display: block;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 2px;
      }

      .fc-event .fc-time {
        display: block;
        font-size: 12px;
        font-weight: 400;
        color: #6b7280;
      }

      .fc-today {
        background-color: #fff3cd !important;
      }

      .fc-row {
        min-height: 40px !important;
        height: auto !important;
      }

      .fc-day,
      .fc-widget-content {
        height: auto !important;
      }

      .fc-day-grid-container {
        height: auto !important;
      }

      .fc-day-grid .fc-row .fc-content-skeleton {
        position: relative !important;
        height: auto !important;
      }

      .fc-event-container {
        overflow: visible !important;
      }

      .fc-row .fc-bg {
        height: auto !important;
      }

      .fc-day-grid-event {
        white-space: normal !important;
      }

      .filter-area {
        margin-bottom: 15px;
        padding-left: 0;
        display: flex;
        align-items: center;
      }

      .swal2-actions {
        justify-content: center !important;
      }

      .color-picker-container {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .color-preview {
        width: 30px;
        height: 30px;
        border-radius: 4px;
        border: 2px solid #ddd;
      }

      .event-modal-card {
        background-color: #fff;
        border-radius: 60px;
        border: 1px solid #ddd;
        overflow: hidden;
      }

      .input-oval {
        border: 1.5px solid #333 !important;
        border-radius: 50px !important;
        height: 48px !important;
        padding-left: 20px !important;
        font-size: 14px;
      }

      .input-oval-group {
        display: flex;
        align-items: center;
        border: 1.5px solid #333;
        border-radius: 50px;
        height: 48px;
        padding: 0 15px;
        background-color: #fff;
      }

      .color-dot-indicator {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        flex-shrink: 0;
      }

      .color-input-clean {
        border: none !important;
        background: transparent !important;
        outline: none !important;
        width: 100%;
        margin-left: 8px;
        font-size: 14px;
        color: #333;
        padding: 0 !important;
      }

      .description-box {
        border: 1.5px solid #000 !important;
        border-radius: 30px !important;
        padding: 20px !important;
      }

      .event-modal-card {
        background-color: #ffffff;
        border-radius: 50px;
        padding: 30px 30px 100px 30px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid #ddd;
        position: relative;
        width: 550px;
        max-width: 95vw;
      }

      .event-modal-card label,
      .event-modal-card h2 {
        display: block;
        text-align: center;
        width: 100%;
      }

      .input-oval {
        border: 1.5px solid #333 !important;
        border-radius: 50px !important;
        height: 48px !important;
        padding-left: 20px !important;
        font-size: 14px;
      }

      .input-oval-group {
        display: flex;
        align-items: center;
        border: 1.5px solid #333;
        border-radius: 50px;
        height: 48px;
        padding: 0 15px;
        background-color: #fff;
      }

      .color-dot-indicator {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        flex-shrink: 0;
      }

      .color-input-clean {
        border: none !important;
        background: transparent !important;
        outline: none !important;
        width: 100%;
        margin-left: 8px;
        font-size: 14px;
        color: #333;
        padding: 0 !important;
      }

      .description-box {
        border: 1.5px solid #000 !important;
        border-radius: 30px !important;
        padding: 20px !important;
      }

      .swal2-popup {
        padding-bottom: 80px !important;
      }

      .swal2-actions {
        position: absolute !important;
        bottom: 30px !important;
        left: 0 !important;
        right: 0 !important;
        margin: 0 auto !important;
        width: 90% !important;
        display: flex !important;
        flex-wrap: wrap !important;
        flex-direction: row !important;
        justify-content: center !important;
        gap: 10px !important;
        z-index: 10;
      }

      .swal2-validation-message {
        display: none !important;
        background-color: #fff !important;
        color: #dc3545 !important;
        border: 1px solid #dc3545 !important;
        border-radius: 20px !important;
        margin-top: 10px !important;
      }

      .swal2-styled.swal2-confirm,
      .swal2-styled.swal2-cancel {
        border-radius: 50px !important;
        padding: 10px 30px !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        min-width: 140px !important;
        margin: 0 !important;
        border: none !important;
        box-shadow: none !important;
      }

      .swal2-styled.swal2-confirm {
        background-color: #B75301 !important;
        color: white !important;
      }

      .swal2-styled.swal2-cancel {
        background-color: #6c757d !important;
        color: white !important;
      }

      .swal2-popup:has(.event-modal-card) {
        background: transparent !important;
        box-shadow: none !important;
        padding: 0 !important;
        width: auto !important;
      }

      #deleteEventBtn {
        border-radius: 50px !important;
        padding: 10px 30px !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        min-width: 140px !important;
        border: none !important;
        box-shadow: none !important;
        transition: background-color 0.2s;
        background-color: #dc3545 !important;
        color: white !important;
      }

      #deleteEventBtn:hover {
        background-color: #c82333 !important;
      }

      .swal2-popup:not(:has(.event-modal-card)) {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        padding: 20px !important;
        text-align: center !important;
        width: 350px !important;
        min-height: auto !important;
        border-radius: 30px !important;
      }

      .swal2-popup:not(:has(.event-modal-card)) .swal2-icon {
        margin-top: 5px !important;
        margin-bottom: 10px !important;
        transform: scale(0.8);
      }

      .swal2-popup:not(:has(.event-modal-card)) .swal2-title {
        display: block !important;
        width: 100% !important;
        margin: 5px 0 10px 0 !important;
        padding-top: 0 !important;
        text-align: center !important;
        font-size: 30px !important;
      }

      .swal2-popup:not(:has(.event-modal-card)) .swal2-html-container {
        display: block !important;
        width: 100% !important;
        margin: 0 0 15px 0 !important;
        text-align: center !important;
        font-size: 16px !important;
      }

      .swal2-popup:not(:has(.event-modal-card)) .swal2-actions {
        position: relative !important;
        bottom: auto !important;
        left: auto !important;
        right: auto !important;
        margin: 0 auto !important;
        width: 100% !important;
        justify-content: center !important;
      }

      /* ========== NEW MOBILE RESPONSIVE STYLES ========== */
      @media (max-width: 768px) {
        /* Header & button row */
        .d-flex.justify-content-between.align-items-center.mb-3 {
          flex-direction: column;
          align-items: stretch !important;
          gap: 12px;
        }

        .filter-area,
        .text-right {
          width: 100%;
          text-align: center !important;
        }

        .dropdown {
          width: 100%;
        }

        .dropdown-toggle {
          width: 100%;
          text-align: center;
        }

        .text-right .btn {
          width: calc(50% - 6px);
          margin: 0 3px;
          font-size: 13px;
          padding: 8px 12px;
        }

        /* Calendar container & fonts */
        .calendar-container {
          padding: 10px;
          border-radius: 16px;
        }

        .fc-header-toolbar .fc-left,
        .fc-header-toolbar .fc-right {
          display: flex;
          gap: 5px;
        }

        .fc-header-toolbar button {
          padding: 6px 10px;
          font-size: 12px;
        }

        .fc-header-toolbar .fc-center h2 {
          font-size: 1.2rem;
          margin: 8px 0;
        }

        .fc-day-header {
          font-size: 12px;
          height: 40px;
        }

        .fc-day-grid .fc-day-number {
          font-size: 12px;
          padding: 4px 6px 0 0;
        }

        .fc-content-skeleton {
          padding: 0 2px;
        }

        .fc-event {
          padding: 4px 6px !important;
          font-size: 11px !important;
          min-height: 32px !important;
        }

        .fc-event .fc-title {
          font-size: 11px;
        }

        .fc-event .fc-time {
          font-size: 9px;
        }

        .fc-event-desc {
          font-size: 9px;
          display: none; /* hides description on very small screens to reduce clutter */
        }

        .fc-day-grid-event {
          padding: 8px 4px !important;
        }

        /* Dropdown items */
        .dropdown-menu .dropdown-item {
          padding: 12px 20px;
          font-size: 14px;
        }

        /* Modal adjustments */
        .swal2-popup {
          width: 95% !important;
          padding: 0 !important;
        }

        .event-modal-card {
          width: 100% !important;
          padding: 20px 20px 100px 20px !important;
          border-radius: 30px !important;
        }

        .event-modal-card h2 {
          font-size: 1.5rem;
        }

        .input-oval,
        .input-oval-group {
          height: 44px !important;
          font-size: 13px;
        }

        .description-box {
          font-size: 13px;
        }

        .swal2-actions {
          flex-direction: column !important;
          gap: 8px !important;
          width: 100% !important;
          bottom: 20px !important;
        }

        .swal2-styled.swal2-confirm,
        .swal2-styled.swal2-cancel,
        #deleteEventBtn {
          width: 90% !important;
          margin: 0 auto !important;
        }
      }

      /* Extra small devices (<=576px) */
      @media (max-width: 576px) {
        .modal-dialog {
          margin: 1rem;
        }
        .modal-content {
          border-radius: 20px !important;
        }
        .modal-body .form-control {
          font-size: 14px;
          height: 44px;
        }
        .alert ul {
          padding-left: 1rem;
        }
        .modal-body {
          padding: 1rem;
          font-size: 13px;
        }
        .modal-title {
          font-size: 1.1rem;
        }
      }

      @media (max-width: 768px) {
      /* Header container */
      .d-flex.justify-content-between.align-items-center.mb-3 {
        flex-direction: column;
        align-items: stretch !important;
        gap: 12px;
      }

      /* Dropdown area - full width */
      .filter-area {
        width: 100%;
      }
      .dropdown {
        width: 100%;
      }
      .dropdown-toggle {
        width: 100%;
        text-align: center;
        justify-content: center;
      }

      /* Button container */
      .text-right {
        width: 100%;
        display: flex;
        flex-direction: row;
        gap: 10px;
        justify-content: space-between;
      }
      .text-right .btn {
        flex: 1;
        margin: 0 !important;
        font-size: 13px;
        padding: 10px 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      /* Hide extra task details on mobile */
      .fc-event.task-event .fc-content > div > div:not(:first-child) {
        display: none;
      }
      /* Show only the task title and status badge */
      .fc-event.task-event .fc-content > div > div:first-child {
        font-size: 11px;
        font-weight: 600;
      }
      .fc-event.task-event .fc-content > div > div:first-child small {
        display: inline;
      }
      /* Keep status badge visible but smaller */
      .fc-event.task-event .fc-content > div > div:has(.badge) {
        display: block !important;
        font-size: 9px;
        margin-top: 2px;
      }

      @media (max-width: 768px) {
        .fc-event.custom-event .fc-event-desc {
          display: none;
        }
        .fc-event.custom-event {
          padding: 4px 6px !important;
          font-size: 11px;
        }
      }

      @media (max-width: 768px) {
        .fc-row {
          min-height: 60px !important;
        }
        .fc-day-grid .fc-day-number {
          font-size: 12px;
          padding: 2px 4px 0 0;
        }
        .fc-content-skeleton {
          padding: 2px;
        }
        .fc-event {
          margin: 2px 0 !important;
        }
      }

      @media (max-width: 768px) {
      .dropdown-divider {
        margin: 4px 0;
      }
    }
}
    </style>
</head>

<body>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="filter-area">
      <div class="dropdown">
        <button class="btn text-white dropdown-toggle" type="button" id="projectDropdown" data-toggle="dropdown"
          aria-expanded="false" style="background-color: #B75301 !important;">
          <i class="fas fa-clipboard"></i>
          <?= htmlspecialchars($current_project_name) ?>
        </button>
        <div class="dropdown-menu" aria-labelledby="projectDropdown">
          <?php if (!empty($projects)): ?>
            <div class="dropdown-divider"></div>
            <li>
              <a class="dropdown-item <?= ($project_id == 0) ? 'active' : '' ?>"
                href="index.php?page=task_calendar&view=all">
                <i class="fas fa-layer-group mr-2"></i> All Tasks
              </a>
            </li>
          <?php endif; ?>
          <?php foreach ($projects as $project): ?>
            <li>
              <a class="dropdown-item <?= $project_id == $project['id'] ? 'active' : '' ?>"
                href="index.php?page=task_calendar&project_id=<?= encode_id($project['id']) ?>">
                <?= htmlspecialchars($project['name']) ?>
              </a>
            </li>

          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="text-right">
        <?php if ($login_type != 4): ?>
            <button id="addEventBtn" class="btn text-white mr-2" style="background-color: #B75301">
                <i class="fas fa-plus"></i> Add Event
            </button>

            <button id="printCalendar" class="btn text-white" style="background-color: #B75301">
                <i class="fas fa-file-export"></i> Export to Google Calendar
            </button>
        <?php endif; ?>
    </div>
  </div>

  <div class="container-fluid mt-4 calendar-container border border-dark rounded">
    <div id="calendar"></div>
  </div>

  <div class="modal fade" id="taskModal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow">
        <div class="modal-header">
          <h5 class="modal-title" id="taskModalLabel">Task Detail</h5>
          <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          Loading...
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Export iCal -->
    <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow" style="border-radius: 30px; overflow: hidden;">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title font-weight-bold" style="color: #333; font-size: 1.5rem;">
              <i class="fas fa-file-export mr-2" style="color: #B75301;"></i>
              Export Custom Events
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true" style="font-size: 1.8rem;">&times;</span>
            </button>
          </div>
          <div class="modal-body py-4">
            <div class="form-group mb-4">
              <label class="font-weight-bold mb-2" style="color: #555; font-size: 1rem;">
                <i class="fas fa-calendar-alt mr-2"></i>Start Date
              </label>
              <input type="date" id="exportStartDate" class="form-control" 
                    style="border-radius: 15px; border: 1.5px solid #ddd; height: 50px; padding: 0 15px;">
            </div>
            <div class="form-group mb-4">
              <label class="font-weight-bold mb-2" style="color: #555; font-size: 1rem;">
                <i class="fas fa-calendar-alt mr-2"></i>End Date
              </label>
              <input type="date" id="exportEndDate" class="form-control" 
                    style="border-radius: 15px; border: 1.5px solid #ddd; height: 50px; padding: 0 15px;">
            </div>
            <div class="alert alert-info mt-3 mb-0" style="border-radius: 15px; border-left: 4px solid #17a2b8;">
              <div class="d-flex">
                <i class="fas fa-info-circle mr-3 mt-1" style="color: #17a2b8; font-size: 1.2rem;"></i>
                <div>
                  <strong>Export Information:</strong>
                  <ul class="mb-0 pl-3" style="font-size: 0.9rem;">
                    <li>Only <strong>custom events</strong> within selected range will be exported</li>
                    <li>File will be in <strong>.ics format</strong> compatible with Google Calendar</li>
                    <li>Each event includes a <strong>10-minute reminder</strong></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn" data-dismiss="modal" 
                    style="border-radius: 25px; padding: 10px 30px; background-color: #6c757d; color: white; border: none;">
              <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <button type="button" id="confirmExport" class="btn" 
                    style="background-color: #B75301; color: white; border-radius: 25px; padding: 10px 30px; border: none;">
              <i class="fas fa-download mr-2"></i>Export to iCal
            </button>
          </div>
        </div>
      </div>
    </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

  <script>
  var allowedProjects = <?php echo json_encode($projects); ?>;
  var currentProjectId = <?php echo json_encode($project_id ?: ''); ?>;
  </script>

  <script>
/* ================= GOOGLE CALENDAR CONFIG ================= */
const GOOGLE_API_KEY = 'AIzaSyC8wEmVkA72xJgHZ-MZiZNKnHyVJSwiqFQ';
const INDONESIA_HOLIDAY_CALENDAR = 'id.indonesian#holiday@group.v.calendar.google.com';

let googleApiInitialized = false;
let googleApiInitializationPromise = null;

function initGoogleApi() {
  if (googleApiInitializationPromise) {
    return googleApiInitializationPromise;
  }

  googleApiInitializationPromise = new Promise((resolve, reject) => {
    console.log('Initializing Google API...');
    gapi.load('client', () => {
      gapi.client.init({
        apiKey: GOOGLE_API_KEY,
        discoveryDocs: [
          'https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest'
        ]
      }).then(() => {
        console.log('Google API initialized successfully');
        googleApiInitialized = true;
        resolve();
      }).catch(error => {
        console.error('Failed to initialize Google API:', error);
        reject(error);
      });
    });
  });

  return googleApiInitializationPromise;
}

function fetchIndonesiaHolidays(start, end) {
  return new Promise((resolve, reject) => {
    if (!googleApiInitialized) {
      reject('Google API not initialized');
      return;
    }

    console.log('Fetching holidays from:', start, 'to:', end);
    gapi.client.calendar.events.list({
      calendarId: INDONESIA_HOLIDAY_CALENDAR,
      timeMin: start.toISOString(),
      timeMax: end.toISOString(),
      singleEvents: true,
      orderBy: 'startTime'
    }).then(res => {
      const events = (res.result.items || []).map(e => ({
        id: 'holiday-' + e.id,
        title: e.summary,
        start: e.start.date || e.start.dateTime,
        end: e.end.date || e.end.dateTime,
        allDay: true,
        editable: false,
        className: 'holiday-event bg-success',
        description: 'Indonesian Public Holiday',
        type: 'holiday',
        // CRITICAL: Set holiday order to 1 (FIRST)
        renderingOrder: 1
      }));
      console.log('Found holiday events:', events.length);
      resolve(events);
    }).catch(error => {
      console.error('Error fetching holidays:', error);
      reject(error);
    });
  });
}

/* ================= FULLCALENDAR INITIALIZATION ================= */
$(document).ready(function () {
  let currentEventId = null;
  let calendarInitialized = false;

  // Initialize Google API FIRST, then initialize FullCalendar
  console.log('Page loaded, initializing Google API...');

  initGoogleApi()
    .then(() => {
      console.log('Google API ready, initializing FullCalendar...');
      initializeFullCalendar();
    })
    .catch(error => {
      console.error('Google API failed, initializing calendar without holidays:', error);
      initializeFullCalendar();
    });

  function initializeFullCalendar() {
    if (calendarInitialized) return;
    calendarInitialized = true;

    $('#calendar').fullCalendar({
      height: 'auto',
      contentHeight: 'auto',
      aspectRatio: 1.35,
      fixedWeekCount: false,
      editable: true,
      selectable: true,
      selectHelper: true,
      eventResizableFromStart: window.innerWidth >= 768, // ← MOBILE: disable resize on touch
      eventDurationEditable: window.innerWidth >= 768,   // ← MOBILE: disable drag on touch
      defaultView: 'month',
      eventLimit: true,
      eventLimitClick: 'popover',
      timezone: 'local',

      header: {
        left: 'prev,today,next',
        center: 'title',
        right: 'month,agendaWeek,agendaDay'
      },

      /* ✅ SINGLE SOURCE OF TRUTH FOR ORDER */
     eventOrder: function (a, b) {
        const getPriority = (e) => {
            if (e.renderingOrder) return e.renderingOrder;
            if (e.type === 'holiday') return 1;
            if (e.type === 'custom') return 2;
            if (e.type === 'task') return 3;
            return 99;
        };
        const typeDiff = getPriority(a) - getPriority(b);
        if (typeDiff !== 0) return typeDiff;

        const aStart = a.start ? new Date(a.start) : new Date(0);
        const bStart = b.start ? new Date(b.start) : new Date(0);
        return aStart - bStart;
    },
    eventOrderStrict: true,

      events: function (start, end, timezone, callback) {
        let allEvents = [];
          
        function finishLoading() {
          callback(allEvents);
        }

        if (googleApiInitialized) {
          fetchIndonesiaHolidays(start.toDate(), end.toDate())
            .then(holidayEvents => {
              holidayEvents = holidayEvents.map(e => ({
                ...e,
                type: 'holiday',
                className: 'holiday-event bg-success'
              }));
              allEvents = allEvents.concat(holidayEvents);
              loadTasks();
            })
            .catch(() => loadTasks());
        } else {
          loadTasks();
        }

        function loadTasks() {
          let taskUrl = 'loadtask.php';
          let customUrl = 'load_custom_events.php';
          let params = [];

          <?php if ($encoded_project_id): ?>
            params.push('project_id=<?= urlencode($encoded_project_id) ?>');
          <?php endif; ?>

          params.push('start=' + encodeURIComponent(start.format()));
          params.push('end=' + encodeURIComponent(end.format()));

          if (params.length) {
            taskUrl += '?' + params.join('&');
            customUrl += '?' + params.join('&');
          }

          $.when(
            $.getJSON(taskUrl),
            $.getJSON(customUrl)
          )
            .done((taskRes, customRes) => {
              /* ---------- TASK EVENTS ---------- */
              if (Array.isArray(taskRes[0])) {
                const taskEvents = taskRes[0].map(event => {
                  // Use raw date strings for all-day events to avoid timezone shifts
                  let startTime = event.start;
                  let endTime = event.end || event.start;

                  // if allDay was sent from server, preserve it
                  const isAllDay = event.allDay === true;

                  return {
                    ...event,
                    type: 'task',
                    className: (event.className || '') + ' task-event',
                    renderingOrder: 3,
                    start: startTime,
                    end: endTime,
                    allDay: isAllDay,
                    // CRITICAL: sort by day portion only so same-day tasks stay together
                    _sortTime: isAllDay ? startTime : moment(startTime).toISOString()
                  };
                });
                allEvents = allEvents.concat(taskEvents);
              }

              /* ---------- CUSTOM EVENTS ---------- */
              if (Array.isArray(customRes[0])) {
                const customEvents = customRes[0].map(event => {
                  let startTime = moment(event.start);
                  let endTime = event.end ? moment(event.end) : startTime.clone().add(1, 'hour');
                  
                  return {
                    ...event,
                    type: 'custom',
                    className: (event.className || '') + ' custom-event',
                    renderingOrder: 2,
                    start: startTime.toISOString(),
                    end: endTime.toISOString(),
                    // CRITICAL: Make all-day events or same-day events start at same "virtual" time
                    _sortTime: startTime.clone().startOf('day').toISOString()
                  };
                });
                allEvents = allEvents.concat(customEvents);
              }

              finishLoading();
            })
            .fail(() => finishLoading());
        }

      },
      eventContent: function (arg) {
        const event = arg.event;
        const isTask = event.extendedProps.type === 'task';

        let titleEl = document.createElement('div');
        titleEl.className = 'fc-event-title';
        titleEl.innerText = event.title;

        let descEl = null;

        if (event.extendedProps.description) {
          descEl = document.createElement('div');
          descEl.className = 'fc-event-desc';
          descEl.innerText = event.extendedProps.description;
        }

        return {
          domNodes: descEl ? [titleEl, descEl] : [titleEl]
        };
      },

      eventRender: function (event, element) {
        const $content = element.find('.fc-content');

        if (event.type === 'holiday') {
          element.addClass('holiday-event bg-success');
          $content.html(`
            <div style="font-weight:600;font-size:12px">
              <i class="fas fa-flag mr-1"></i> ${event.title}
            </div>
          `);
          return;
        }

        if (event.type === 'custom') {
          element.addClass('custom-event');

          // Remove the generic fc-event class styles that set white background
          element.removeClass('task-event');

          // Apply custom color with !important if needed
          if (event.color) {
            element.css({
              'backgroundColor': event.color + '!important',
              'borderColor': event.color + '!important',
              'color': '#ffffff !important'
            });
          }

          $content.html(`
            <div style="font-weight:600;font-size:12px; color: #ffffff;">
              <i class="fas fa-calendar-alt mr-1"></i> ${event.title}
            </div>
            ${event.description ? `<div class="fc-event-desc" style="color: rgba(255,255,255,0.9);">${event.description}</div>` : ''}
          `);
          return;
        }

        // task events (keep existing)
        element.addClass('task-event');

        // Inside eventRender for task events, replace renderTaskEvent with:
      if (window.innerWidth < 768) {
        // Simple mobile version: title + status only
        let statusText = event.status ? ['Pending','Started','On-Progress','Hold','Over Due','Done'][event.status] : '';
        let statusClass = ['secondary','info','primary','warning','danger','success'][event.status] || 'secondary';
        element.find('.fc-content').html(`
          <div style="font-weight:600; font-size:11px;">${event.title || 'Task'}</div>
          <span class="badge badge-${statusClass}" style="font-size:9px;">${statusText}</span>
        `);
      } else {
        renderTaskEvent(event, element);
      }
              
      },

      eventClick: function (event) {
        // HOLIDAY
        if (event.type === 'holiday') {
          Swal.fire({
            title: 'Public Holiday',
            html: `
          <div class="text-left">
            <h5>${event.title}</h5>
            <p><strong>Date:</strong> ${moment(event.start).format('dddd, MMMM D, YYYY')}</p>
            <p><strong>Type:</strong> Indonesian Public Holiday</p>
            ${event.description ? `<p><strong>Description:</strong> ${event.description}</p>` : ''}
          </div>
        `,
            icon: 'info',
            confirmButtonColor: '#28a745'
          });
          return;
        }

        if (event.type === 'custom') {
          console.log('Custom event clicked, calling showCustomDetail');
          showCustomDetail(event);
          return;
        }

        // TASK (UNCHANGED)
        if (!event.id) return;

        start_load();
        $.ajax({
          url: 'get_task_detail.php',
          method: 'POST',
          data: { id: event.id },
          success: function (response) {
            end_load();
            $('#taskModal .modal-body').html(response);
            $('#taskModal').modal('show');
          }
        });
      },

      eventAllow: function (dropInfo, draggedEvent) {
        return draggedEvent.type !== 'holiday';
      },

      windowResize: function(view) {
        $('#calendar').fullCalendar('option', 'aspectRatio', 
          window.innerWidth < 768 ? 0.8 : 1.35);
      }
    });

    console.log('FullCalendar initialized (ordering FIXED)');
  } // CHANGED: This closes the initializeFullCalendar function properly


  /* ================= EVENT MODAL FUNCTIONS WITH COLOR PICKER ================= */
  $('#addEventBtn').click(() => {
    currentEventId = null;
    showEventModal();
  });

  function showEventModal(event = null) {
    const isEdit = !!event;
    const eventType = event ? event.type : null;
    const eventId = event ? event.id : null;
    console.log('showEventModal called with event:', event);
    console.log('isEdit:', isEdit, 'eventType:', eventType);

  function escapeHtml(unsafe) {
    return unsafe.replace(/[&<>"]/g, function(m) {
      if(m === '&') return '&amp;';
      if(m === '<') return '&lt;';
      if(m === '>') return '&gt;';
      if(m === '"') return '&quot;';
      return m;
    });
  }
      
    // Extract data with fallbacks
    let defaultTitle = '';
    let defaultDescription = '';
    let defaultColor = '#007bff';
    let defaultStart = moment();
    let defaultEnd = moment().add(1, 'hour');
    
    if (isEdit && event) {
      defaultTitle = event.title || '';
      defaultDescription = event.description || '';
      defaultColor = event.color || event.backgroundColor || '#007bff';
      
      // Handle start date
      if (event.start) {
        if (event.start instanceof Date) {
          defaultStart = moment(event.start);
        } else if (typeof event.start === 'string') {
          defaultStart = moment(event.start);
        } else {
          // Try to parse it
          defaultStart = moment(event.start);
        }
      }
      
      // Handle end date
      if (event.end) {
        if (event.end instanceof Date) {
          defaultEnd = moment(event.end);
        } else if (typeof event.end === 'string') {
          defaultEnd = moment(event.end);
        } else {
          defaultEnd = moment(event.end);
        }
      } else {
        // If no end date, set it to 1 hour after start
        defaultEnd = moment(defaultStart).add(1, 'hour');
      }
      
      // Make sure end is after start
      if (defaultEnd.isSameOrBefore(defaultStart)) {
        defaultEnd = moment(defaultStart).add(1, 'hour');
      }
    }
    
    console.log('Modal defaults:', { defaultTitle, defaultStart: defaultStart.format(), defaultEnd: defaultEnd.format(), defaultColor });
    
    Swal.fire({
      html: `
        <div class="event-modal-card p-4">
          <h2 class="text-center font-weight-bold mb-4" style="color: #333;">${isEdit ? 'Edit Event' : 'Add Event'}</h2>
            <div class="text-left">
              <div class="form-row align-items-end">
                <div class="form-group col-md-7">
                  <label class="h6 font-weight-bold">Title</label>
                  <input id="eventTitle" class="form-control input-oval" placeholder="Event title" value="${defaultTitle}">
                </div>
                <div class="form-group col-md-5">
                  <label class="h6 font-weight-bold ml">Color</label>
                  <div class="input-oval-group">
                    <div id="colorPreview" class="color-dot-indicator" style="background-color: ${defaultColor}"></div>
                    <input id="eventColor" type="color" class="color-input-clean" value="${defaultColor}">
                  </div>
                </div>
                <div class="form-group col-md-12">
                  <label class="h6 font-weight-bold">Project (optional)</label>
                  <select id="eventProject" class="form-control input-oval">
                    <option value="">-- Personal Event (no project) --</option>
                    ${allowedProjects.map(p => 
                      `<option value="${p.id}" ${isEdit && event.project_id == p.id ? 'selected' : ''}>${escapeHtml(p.name)}</option>`
                    ).join('')}
                  </select>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-12 px-2">
                <label class="h6 font-weight-bold ml-2">Start Date & Time</label>
                <input id="eventStart" type="datetime-local" class="form-control input-oval" value="${defaultStart.format('YYYY-MM-DDTHH:mm')}">
              </div>
              <div class="form-group col-md-12 px-2">
                <label class="h6 font-weight-bold ml-2">End Date & Time</label>
                <input id="eventEnd" type="datetime-local" class="form-control input-oval" value="${defaultEnd.format('YYYY-MM-DDTHH:mm')}">
              </div>
            </div>
            <div class="form-row mt-2">
              <div class="form-group col-md-12 px-2">
                <label class="h6 font-weight-bold ml-2">Description (Optional)</label>
                <textarea id="eventDescription" class="form-control description-box" rows="5" placeholder="Event description">${defaultDescription}</textarea>
              </div>
            </div>
          ${isEdit && eventType !== 'holiday' ? '<div class="text-center mt-3"><button id="deleteEventBtn" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Event</button></div>' : ''}
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: isEdit ? 'Save Changes' : 'Add Event',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#B75301',
      cancelButtonColor: '#6c757d', 
      width: '500px',
      onOpen: () => {
        console.log('=== MODAL OPENED ===');
        console.log('eventId:', eventId);
        
        // Native color input sync
        $('#eventColor').on('input change', function() {
          $('#colorPreview').css('background-color', this.value);
        });
        
        // DELETE BUTTON HANDLER
        $('#deleteEventBtn').off('click').on('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          console.log('Delete button clicked! eventId:', eventId);

          if (eventId) {
            Swal.close(); // tutup modal edit

            Swal.fire({
              title: 'Delete this event?',
              text: "This action cannot be undone.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#dc3545',
              cancelButtonColor: '#6c757d',
              confirmButtonText: 'Yes, delete it!',
              cancelButtonText: 'Cancel'
            }).then(result => {
              if (result.isConfirmed) {
                // 🔁 GANTI INI: pakai delete.php, bukan ajax.php
                $.post('delete.php', { id: eventId }, function(response) {
                  console.log('Delete response:', response);
                  if (response.trim() === 'success') {   // ← periksa 'success'
                    $('#calendar').fullCalendar('refetchEvents');
                    setTimeout(() => location.reload(), 1000);
                  } else {
                    Swal.fire('Error', 'Failed to delete event.', 'error');
                  }
                }).fail(function(xhr, status, error) {
                  Swal.fire('Error', 'Failed to delete event: ' + error, 'error');
                });
              }
            });
          } else {
            Swal.fire('Error', 'No event ID found', 'error');
          }
        });
      },
      preConfirm: () => {
        const title = $('#eventTitle').val().trim();
        const start = $('#eventStart').val();
        const end = $('#eventEnd').val();
        const color = $('#eventColor').val();
        const description = $('#eventDescription').val().trim();
        const project_id = $('#eventProject').val();

        if (!title) {
          Swal.showValidationMessage('Event title is required');
          return false;
        }
        if (!start || !end) {
          Swal.showValidationMessage('Start and end times are required');
          return false;
        }
        if (new Date(start) >= new Date(end)) {
          Swal.showValidationMessage('End time must be after start time');
          return false;
        }
        
        return { title, start, end, color, description, project_id };
      }
      
    }).then(result => {
      if (result.isConfirmed) {
        const data = {
          ...result.value,
          type: 'custom',
          renderingOrder: 2
        };

        let url = 'insert.php';
        if (isEdit && event && event.id) {
          url = 'update.php';
          data.id = event.id;
        }

        console.log('Saving event data:', data);
        
        $.post(url, data, (response) => {
          console.log('Event saved:', response);
          $('#calendar').fullCalendar('refetchEvents');
          Swal.fire('Success', `Event ${isEdit ? 'updated' : 'added'} successfully!`, 'success');
        }).fail((jqXHR, textStatus, errorThrown) => {
          console.error('Failed to save event:', textStatus, errorThrown);
          Swal.fire('Error', 'Failed to save event. Please try again.', 'error');
        });
      }
    });
  }

  function confirmDelete(id) {
    Swal.fire({
      title: 'Delete this event?',
      text: "This action cannot be undone.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('delete.php', { id }, (response) => {
          console.log('Event deleted:', response);
          $('#calendar').fullCalendar('refetchEvents');
          Swal.fire('Deleted!', 'Event has been deleted.', 'success');
        }).fail(() => {
          Swal.fire('Error', 'Failed to delete event. Please try again.', 'error');
        });
      }
    });
  }

  function updateEvent(event) {
    $.post('update.php', {
      id: event.id,
      title: event.title,
      color: event.color || '#007bff',
      start: moment(event.start).format('YYYY-MM-DD HH:mm:ss'),
      end: moment(event.end).format('YYYY-MM-DD HH:mm:ss'),
      description: event.description || ''
    }, (response) => {
      console.log('Event updated:', response);
    }).fail(() => {
      console.error('Failed to update event');
    });
  }

  /* ================= TASK EVENT RENDERING (KEEPING ORIGINAL) ================= */
  function renderTaskEvent(event, element) {
    let statusMap = {
      0: '<span class="badge badge-secondary">Pending</span>',
      1: '<span class="badge badge-info">Started</span>',
      2: '<span class="badge badge-primary">On-Progress</span>',
      3: '<span class="badge badge-warning">Hold</span>',
      4: '<span class="badge badge-danger">Over Due</span>',
      5: '<span class="badge badge-success">Done</span>'
    };

    element.find('.fc-title').remove();

    function cleanHtmlText(htmlString) {
      if (!htmlString) return '';

      let textarea = document.createElement('textarea');
      textarea.innerHTML = htmlString;
      let decodedText = textarea.value;
      decodedText = decodedText.replace(/<\/?[^>]+(>|$)/g, "");
      decodedText = decodedText
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&#x27;/g, "'")
        .replace(/&apos;/g, "'")
        .replace(/<br\s*\/?>/gi, ' ')
        .replace(/<p>/gi, ' ')
        .replace(/<\/p>/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();

      return decodedText;
    }

    let cleanDescription = '';
    let fullDescription = '';

    if (event.description) {
      fullDescription = cleanHtmlText(event.description);
      cleanDescription = fullDescription.substring(0, 40) + (fullDescription.length > 40 ? '...' : '');
    }

    let html = `
    <div>
      <div style="font-weight:600; font-size:14px; margin-bottom:2px;">
        <small>Task</small><br> ${event.title || 'Untitled Task'}
      </div>
      ${event.project_name ? `<div style="font-size:12px; color:#6b7280; margin-bottom:2px;">${event.project_name}</div>` : ''}
      <div style="font-size:12px; margin-bottom:2px;">
        ${statusMap[event.status] || '<span class="badge badge-secondary">Unknown</span>'}
      </div>
      ${event.content_pillar ? `<div style="font-size:12px; color:#2563eb; font-weight:500; margin-bottom:2px;">${event.content_pillar}</div>` : ''}
      ${event.platform ? `<div style="font-size:12px; color:#059669; margin-bottom:2px;">${event.platform}</div>` : ''}
      ${cleanDescription ? `<div style="font-size:12px; color:#374151; margin-top:4px; white-space:normal; line-height:1.3;">${cleanDescription}</div>` : ''}
    </div>
    `;

    element.find('.fc-content').html(html);

    if (fullDescription) {
      element.attr('title', fullDescription);
    }
  }
  // CHANGED: Moved showCustomDetail function INSIDE the $(document).ready()
  function showCustomDetail(event) {
    console.log('Event object in showCustomDetail:', event);
    
    // Format dates safely
    let startDate = 'N/A';
    let endDate = 'N/A';
    
    try {
      if (event.start) {
        if (event.start._isAMomentObject) {
          // It's a moment object
          startDate = event.start.format('YYYY-MM-DD HH:mm');
        } else if (event.start instanceof Date) {
          startDate = moment(event.start).format('YYYY-MM-DD HH:mm');
        } else if (typeof event.start === 'string') {
          startDate = moment(event.start).format('YYYY-MM-DD HH:mm');
        } else {
          startDate = moment(event.start).format('YYYY-MM-DD HH:mm');
        }
      }
      
      if (event.end) {
        if (event.end._isAMomentObject) {
          // It's a moment object
          endDate = event.end.format('YYYY-MM-DD HH:mm');
        } else if (event.end instanceof Date) {
          endDate = moment(event.end).format('YYYY-MM-DD HH:mm');
        } else if (typeof event.end === 'string') {
          endDate = moment(event.end).format('YYYY-MM-DD HH:mm');
        } else {
          endDate = moment(event.end).format('YYYY-MM-DD HH:mm');
        }
      }
    } catch (e) {
      console.error('Error formatting dates:', e);
    }
    
    Swal.fire({
      title: event.title || 'Untitled Event',
      html: `
        <div class="text-left">
          ${event.project_name ? `<p><strong>Project:</strong> ${event.project_name}</p>` : ''}
          <p><strong>Start:</strong> ${startDate}</p>
          <p><strong>End:</strong> ${endDate}</p>
          ${event.description ? `<p><strong>Description:</strong><br>${event.description}</p>` : ''}
          ${(event.backgroundColor || event.color) ? 
            `<div style="display:flex; align-items:center; margin-top:10px;">
              <strong>Color:&nbsp;</strong>
              <div style="width:20px; height:20px; background-color:${event.backgroundColor || event.color || '#007bff'}; border-radius:3px; margin-left:5px;"></div>
            </div>` : ''}
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Edit',
      cancelButtonText: 'Close',
      confirmButtonColor: '#B75301',
      width: '600px'
    }).then(res => {
      if (res.isConfirmed) {
        console.log('Edit button clicked, preparing event data...');
        
        // Prepare the event data for editing
        const editEvent = {
          id: event.id,
          title: event.title || 'Untitled Event',
          description: event.description || '',
          // FullCalendar uses backgroundColor, not color
          color: event.backgroundColor || event.color || '#007bff',
          // Convert moment objects to Date objects
          start: event.start ? (event.start._isAMomentObject ? event.start.toDate() : new Date(event.start)) : new Date(),
          end: event.end ? (event.end._isAMomentObject ? event.end.toDate() : new Date(event.end)) : new Date(Date.now() + 3600000),
          project_id: event.project_id || '',
          type: event.type || 'custom'
        };
        
        console.log('Prepared edit event:', editEvent);
        showEventModal(editEvent);
      }
    });
  }

  function delete_task(encodedId) {
    Swal.fire({
      title: 'Hapus Tugas?',
      text: "Anda yakin ingin menghapus tugas ini? Tindakan ini tidak dapat dibatalkan!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
      reverseButtons: true
    }).then((result) => {
      if (result.isConfirmed) {
        start_load();

        $.ajax({
          url: 'ajax.php?action=delete_task',
          method: 'POST',
          data: { id: encodedId },
          success: function (resp) {
            end_load();
            if (resp.trim() === '1' || resp.trim() === '0' || resp.trim() === '2') {
              setTimeout(() => location.reload(), 100);
            } else {
              Swal.fire('Error Respon', 'Gagal terhubung atau respon server tidak valid.', 'error');
            }
          },
          error: function (xhr, status, error) {
            end_load();
            Swal.fire('Error Jaringan', 'Gagal memproses permintaan server: ' + error, 'error');
          }
        });
      }
    });
  }
      // Update the export button click handler
    $('#printCalendar').off('click').on('click', function(e) {
      e.preventDefault();
      
      // Get current calendar view range
      var calendar = $('#calendar').fullCalendar('getCalendar');
      var view = calendar.view;
      var start = view.start;
      var end = view.end;
      
      // Set default dates to current view range
      $('#exportStartDate').val(start.format('YYYY-MM-DD'));
      $('#exportEndDate').val(end.format('YYYY-MM-DD'));
      
      // Show modal
      $('#exportModal').modal('show');
    });

    // Confirm export button - REPLACE THIS SECTION
    $('#confirmExport').off('click').on('click', function() {
      var startDate = $('#exportStartDate').val();
      var endDate = $('#exportEndDate').val();
      
      // Validation
      if (!startDate || !endDate) {
        Swal.fire({
          icon: 'error',
          title: 'Missing Dates',
          text: 'Please select both start and end dates.',
          confirmButtonColor: '#B75301',
          confirmButtonText: 'OK',
          customClass: {
            popup: 'rounded-lg'
          }
        });
        return;
      }
      
      if (new Date(startDate) > new Date(endDate)) {
        Swal.fire({
          icon: 'error',
          title: 'Invalid Range',
          text: 'Start date must be before end date.',
          confirmButtonColor: '#B75301',
          confirmButtonText: 'OK',
          customClass: {
            popup: 'rounded-lg'
          }
        });
        return;
      }
      
      // Close modal
      $('#exportModal').modal('hide');
      
      // Show loading
      Swal.fire({
        title: 'Preparing Export...',
        html: `
          <div class="text-center">
            <div class="mb-3">
              <i class="fas fa-spinner fa-spin fa-2x" style="color: #B75301;"></i>
            </div>
            <p>Checking for custom events</p>
            <p class="small text-muted">From ${startDate} to ${endDate}</p>
          </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        customClass: {
          popup: 'rounded-lg'
        }
      });
      
      // Create download URL
      var exportUrl = `export_ical.php?start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
      
      // First, check if there are any events
      fetch(exportUrl)
        .then(response => {
          // Check content type
          const contentType = response.headers.get('content-type');
          
          if (contentType && contentType.includes('application/json')) {
            // It's a JSON response (no events found)
            return response.json().then(data => {
              Swal.close();
              Swal.fire({
                icon: 'warning',
                title: 'No Events Found',
                html: `
                  <div class="text-center">
                    <div class="mb-3">
                      <i class="fas fa-calendar-times fa-3x" style="color: #ffc107;"></i>
                    </div>
                    <p>${data.message}</p>
                    <p class="small text-muted">Please try a different date range or create custom events first.</p>
                    <button onclick="$('#exportModal').modal('show')" 
                            class="btn mt-3" 
                            style="background-color: #B75301; color: white; border-radius: 25px;">
                      <i class="fas fa-redo mr-2"></i>Choose Different Dates
                    </button>
                  </div>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#B75301',
                customClass: {
                  popup: 'rounded-lg'
                }
              });
            });
          } else {
            // It's an iCal file (events found)
            return response.blob().then(blob => {
              // Create download link
              const url = window.URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.style.display = 'none';
              a.href = url;
              a.download = `custom_events_${startDate}_to_${endDate}.ics`;
              document.body.appendChild(a);
              a.click();
              window.URL.revokeObjectURL(url);
              
              // Show success
              Swal.close();
              Swal.fire({
                icon: 'success',
                title: 'Export Complete!',
                html: `
                  <div class="text-center">
                    <div class="mb-3">
                      <i class="fas fa-check-circle fa-3x" style="color: #28a745;"></i>
                    </div>
                    <p>Custom events have been exported successfully.</p>
                    <div class="alert alert-light mt-3 text-left small">
                      <p class="mb-1"><strong>Next Steps:</strong></p>
                      <ol class="mb-0 pl-3">
                        <li>Save the downloaded .ics file</li>
                        <li>Open Google Calendar</li>
                        <li>Go to Settings → Import & Export</li>
                        <li>Select the .ics file to import</li>
                      </ol>
                    </div>
                    <p class="small text-muted mt-3">
                      <a href="${exportUrl}" target="_blank" 
                        style="color: #B75301; text-decoration: underline;">
                        <i class="fas fa-download mr-1"></i>Click here to download again
                      </a>
                    </p>
                  </div>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#B75301',
                customClass: {
                  popup: 'rounded-lg'
                }
              });
            });
          }
        })
        .catch(error => {
          Swal.close();
          Swal.fire({
            icon: 'error',
            title: 'Export Failed',
            text: 'An error occurred while exporting events. Please try again.',
            confirmButtonColor: '#B75301',
            confirmButtonText: 'OK',
            customClass: {
              popup: 'rounded-lg'
            }
          });
          console.error('Export error:', error);
        });
    });

}); // CHANGED: This closes the $(document).ready() function - IMPORTANT!
</script>