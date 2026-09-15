<?php
if(!isset($_SESSION['login_id'])) {
    header("location:login.php");
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$current_user_id = $_SESSION['login_id'];
$encoded_thread_id = $_GET['thread_id'] ?? null;
$encoded_recipient_id = $_GET['recipient_id'] ?? null;
$encoded_group_id = $_GET['group_id'] ?? null; 

$initial_thread_id = null;
$initial_recipient_id = null;
$initial_group_id = null;

if (!function_exists('decode_id')) {
    function decode_id($id) { return $id; } // Fallback jika tidak ada enkripsi
}

// 1. Decode Thread ID
if (!empty($encoded_thread_id)) {
    $decoded_id = decode_id($encoded_thread_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_thread_id = $decoded_id;
    }
}

// 2. Decode Recipient ID
if (!empty($encoded_recipient_id)) {
    $decoded_id = decode_id($encoded_recipient_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_recipient_id = $decoded_id;
    }
}

// 3. Decode Group ID
if (!empty($encoded_group_id)) {
    $decoded_id = decode_id($encoded_group_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_group_id = $decoded_id;
    }
}

if ($initial_thread_id && !$initial_recipient_id && isset($conn)) {
    $thread_q = $conn->query("SELECT user1_id, user2_id FROM chat_threads WHERE id = '{$initial_thread_id}'");
    if ($thread_q && $thread_q->num_rows > 0) {
        $thread_data = $thread_q->fetch_assoc();
        $initial_recipient_id = ($thread_data['user1_id'] == $current_user_id) ? $thread_data['user2_id'] : $thread_data['user1_id'];
    } else {
        $initial_thread_id = '';
    }
}

// Teruskan nilai ke JavaScript, pastikan string kosong jika null/gagal
$initial_thread_id_js = $initial_thread_id === null ? '' : (string)$initial_thread_id;
$initial_recipient_id_js = $initial_recipient_id === null ? '' : (string)$initial_recipient_id;
$initial_group_id_js = $initial_group_id === null ? '' : (string)$initial_group_id;
?>

<style>
    /* ------------------------------------------- */
    /* WA / MESSENGER STYLES (UI FIXES)            */
    /* ------------------------------------------- */
    .chat-container {
        display: flex;
        height: 75vh;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
    }
    /* Group settings buttons are shown/hidden via JS based on context */
    /* Group settings buttons in header */
    .chat-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .chat-header-actions .btn-outline-light {
        border-color: rgba(255,255,255,0.5);
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }

    .chat-header-actions .btn-outline-light:hover {
        background-color: rgba(255,255,255,0.1);
        border-color: white;
    }

    /* Desktop override: header background is white, button should be dark */
    @media (min-width: 768px) {
        .chat-header-actions .btn-outline-light {
            border-color: #6c757d;
            color: #495057;
        }
        .chat-header-actions .btn-outline-light:hover {
            background-color: #f8f9fa;
            border-color: #6c757d;
        }
    }

        /* Floating action button for new group on mobile */
    .mobile-fab-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1060;
        display: block;
    }

    .mobile-fab-container .btn {
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Hide FAB when chat area is active */
    .chat-container.show-chat .mobile-fab-container {
        display: none;
    }
    
    /* --- SIDEBAR (USER LIST) --- */
    .user-list-sidebar {
        width: 350px;
        border-right: 1px solid #dee2e6;
        overflow-y: auto;
        padding: 0;
        background-color: #f8f9fa;
        min-height: 100%;
        flex-shrink: 0;
    }
    .user-item {
        display: flex; 
        align-items: center;
        padding: 12px 15px;
        border-bottom: 1px solid #f1f1f1;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .user-item .flex-grow-1 {
    min-width: 0;          /* Allows truncation inside flex */
    overflow: hidden;
    }

    .user-item .text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-item .badge-pill {
        flex-shrink: 0;        /* Prevents badge from shrinking */
        margin-left: 6px;
    }

    .user-item .d-flex {
        flex-wrap: nowrap;     /* Keeps name/time or message/badge on one line */
    }
        
    /* --- CHAT AREA --- */
    .chat-area {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        background-color: #f0f0f0; 
    }
    
    /* --- Clean Minimal Chat Header --- */
    .chat-header {
        background-color: #B75301 !important;
        z-index: 10;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        min-height: 60px;
        position: sticky;
        top: 0;
        width: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .chat-box {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        height: 1px;
    }
    
    /* --- MESSAGE BUBBLES --- */
    .message-item {
        margin: 5px 0;
        padding: 8px 12px;
        border-radius: 18px; 
        max-width: 65%; 
        position: relative;
        word-wrap: break-word; 
        font-size: 0.95em; 
        word-break: break-word;
    }
    .incoming-message {
        background-color: #ffffff; 
        margin-right: auto;
        border-bottom-left-radius: 4px; 
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    .outgoing-message {
        background-color: #B75301; 
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px; 
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    
    .sender-name {
        font-weight: bold;
        margin-bottom: 2px;
        font-size: 0.85em; 
        opacity: 0.8;
        color: #B75301;
    }
    .outgoing-message .sender-name {
        color: #ffffff; 
        opacity: 0.9;
    }
    
    .timestamp {
        font-size: 0.65em; 
        opacity: 0.6;
        display: block;
        margin-top: 3px;
        text-align: right;
    }
    .outgoing-message .timestamp {
        color: #fff;
        opacity: 0.8;
    }
    
    /* --- INPUT AREA --- */
    #chat_input_container {
        background-color: #ffffff; 
        border-top: 1px solid #dee2e6;
        padding: 10px 15px;
        flex-shrink: 0;
    }
    #message_content {
        border-radius: 20px !important; 
        padding: 10px 15px;
        min-height: 44px;
        line-height: 24px;
    }
    #chat_input_container .input-group-append button {
        border-radius: 20px !important;
        margin-left: 5px;
    }

    /* --- IMAGE ATTACHMENT --- */
    #attach_image_btn {
        border-radius: 20px !important;
        border-color: #dee2e6;
        color: #6c757d;
        background-color: #fff;
    }
    #attach_image_btn:hover {
        background-color: #f8f9fa;
        border-color: #B75301;
        color: #B75301;
    }
    #image_preview_bar {
        display: none;
        align-items: center;
        gap: 8px;
        padding: 8px 4px 4px 4px;
    }
    #image_preview_bar.active {
        display: flex;
    }
    #image_preview_bar .preview-thumb-wrap {
        position: relative;
        width: 64px;
        height: 64px;
        flex-shrink: 0;
    }
    #image_preview_bar .preview-thumb-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    #image_preview_bar .remove-preview-btn {
        position: absolute;
        top: -6px;
        right: -6px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #dc3545;
        color: #fff;
        border: none;
        font-size: 0.7rem;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }

    /* Image bubbles inside messages */
    .message-item .message-image {
        max-width: 240px;
        max-height: 240px;
        border-radius: 12px;
        display: block;
        cursor: pointer;
        margin-bottom: 4px;
    }
    .message-item .message-image + .m-0:empty {
        display: none;
    }

    /* Fullscreen image viewer */
    #image_viewer_overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.85);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        cursor: zoom-out;
    }
    #image_viewer_overlay.active {
        display: flex;
    }
    #image_viewer_overlay img {
        max-width: 90vw;
        max-height: 90vh;
        border-radius: 4px;
    }
    
    /* --- AVATAR & BADGE --- */
    .default-avatar {
        width: 40px; 
        height: 40px;
        font-size: 1.1em;
        border-radius: 50%;
        text-align: center;
        line-height: 40px;
        flex-shrink: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #6c757d; 
        color: white;
    }
    .badge-danger {
        background-color: #dc3545; 
        color: white;
        font-size: 0.7rem;
        padding: 4px 6px;
        line-height: 1;
        margin-left: 5px;
    }
    .user-item .font-weight-bold {
        font-weight: bold !important;
    }
    #recipient_header h5 {
    cursor: default;
    }
    #recipient_header h5.clickable {
        cursor: pointer;
        text-decoration: underline dotted rgba(255,255,255,0.5);
    }
    #recipient_header h5.clickable:hover {
        text-decoration: underline solid white;
    }
    @media (min-width: 768px) {
        #recipient_header h5.clickable {
            text-decoration-color: #6c757d;
        }
    }
    
    /* --- MEMBER LIST FOR MODALS --- */
    .member-list-container {
        max-height: 300px;
        overflow-y: auto; 
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 0;
        background-color: #ffffff;
    }
    
    .member-list-item {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
        user-select: none;
    }
    .member-list-item:last-child {
        border-bottom: none;
    }
    .member-list-item:hover {
        background-color: #f5f5f5;
    }

    .member-list-item input[type="checkbox"] {
        min-width: 18px;
        min-height: 18px;
        margin-right: 15px;
        cursor: pointer;
    }

    .member-list-item span {
        flex-grow: 1;
        font-size: 0.95em;
        color: #333;
    }

    /* --- SEARCH BAR --- */
    .chat-search-container {
        padding: 10px 15px;
        background: white;
        border-bottom: 1px solid #dee2e6;
    }
    
    .chat-search-input {
        border-radius: 20px;
        padding-left: 40px;
    }
    
    .chat-search-icon {
        position: absolute;
        left: 25px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: #6c757d;
        font-size: 0.8rem;
    }
    
    /* --- CHAT HEADER COMPONENTS --- */
    /* Minimal Back Button - No background, just icon */
    .back-to-chats-btn {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        color: white;
        text-decoration: none;
        font-size: 20px;
        flex-shrink: 0;
        z-index: 11;
        margin-right: 8px;
        background: none !important;
        border: none !important;
        padding: 0 !important;
    }
    
    .back-to-chats-btn i {
        font-size: 20px;
        opacity: 0.9;
    }
    
    .back-to-chats-btn:hover i {
        opacity: 1;
    }
    
    /* Title Area - Flexible and centered */
    .chat-header-title {
        flex: 1;
        min-width: 0;
        text-align: center;
        padding: 0 8px;
    }
    
    .chat-header-title h5 {
        color: white !important;
        margin: 0 !important;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 16px;
        line-height: 1.3;
        max-width: 100%;
    }
    
    /* Empty state for search */
    .no-results {
        padding: 20px;
        text-align: center;
        color: #6c757d;
        font-style: italic;
    }
    
    /* --- MOBILE RESPONSIVE STYLES --- */
    @media (max-width: 767.98px) {
        /* Main container adjustments */
        .chat-container {
            height: calc(100vh - 220px) !important;
            flex-direction: column !important;
            border: none !important;
            border-radius: 0 !important;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1050;
            background: white;
        }
        
        /* Hide footer when chat is active on mobile */
        body.chat-active footer,
        body.chat-active .footer,
        body.chat-active .main-footer,
        body.chat-active .btn-add,
        body.chat-active .action-button,
        body.chat-active .floating-action-button,
        body.chat-active #addButton,
        body.chat-active .plus-button,
        body.chat-active .fab,
        body.chat-active .speed-dial-container {
            display: none !important;
        }
        
        /* Toggle between sidebar and chat area */
        .chat-container.show-sidebar .chat-area {
            display: none !important;
        }
        
        .chat-container.show-chat .user-list-sidebar {
            display: none !important;
        }
        
        /* Sidebar adjustments */
        .user-list-sidebar {
            width: 100% !important;
            border-right: none !important;
            height: 100%;
            overflow-y: auto;
        }
        
        /* Chat area adjustments */
        .chat-area {
            width: 100% !important;
            height: 100%;
        }
        
        /* Chat header adjustments */
        .chat-header {
            padding: 12px !important;
            min-height: 56px;
            display: flex !important;
            align-items: center !important;
            width: 100% !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1020 !important;
            background-color: #B75301 !important;
        }
        
        .chat-header-title {
            flex: 1 !important;
            min-width: 0 !important;
        }
        
        .chat-header-title h5 {
            font-size: 15px;
            padding: 0 4px;
            color: white !important;
        }
        
        /* Back button adjustments */
        .back-to-chats-btn {
            width: 40px;
            height: 40px;
            margin-right: 6px;
        }
        
        .back-to-chats-btn i {
            font-size: 18px;
        }
        
        /* Chat box adjustments */
        .chat-box {
            padding: 10px 10px 80px !important;
            height: calc(100% - 120px) !important;
        }
        
        /* Message bubbles - wider on mobile */
        .message-item {
            max-width: 85% !important;
            word-break: break-word;
            min-width: 40% !important;
        }
        
        /* Input area adjustments */
        #chat_input_container {
            position: fixed !important;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 10px !important;
            border-top: 1px solid #dee2e6;
            z-index: 1010;
            margin-bottom: 0 !important;
        }
        
        #message_content {
            font-size: 16px !important;
            height: 45px;
        }
        
        /* User list items */
        .user-item {
            padding: 15px 10px !important;
            min-height: 70px;
        }
        
        /* Avatar adjustments */
        .default-avatar {
            width: 45px !important;
            height: 45px !important;
            font-size: 16px !important;
        }
        
        /* Mobile-only elements */
        .mobile-only {
            display: block !important;
        }
        
        .desktop-only {
            display: none !important;
        }
        
        /* New group button adjustment */
        #new_group_btn {
            display: none !important;
        }
        
        /* Mobile new group button */
        #new_group_btn_mobile {
            display: inline-flex !important;
            align-items: center;
            padding: 8px 15px;
            font-size: 14px;
            background-color: #B75301 !important;
            border-color: #B75301 !important;
        }
        
        /* Modal adjustments */
        .modal-dialog {
            margin: 10px !important;
            max-width: calc(100% - 20px) !important;
        }
        
        .member-list-container {
            max-height: 250px !important;
        }
        
        /* Make timestamps smaller on mobile */
        .timestamp {
            font-size: 0.6em !important;
        }
        
        /* Search bar adjustments */
        .chat-search-container {
            padding: 8px 10px;
        }
        
        .chat-search-input {
            font-size: 14px;
            padding-left: 35px;
            height: 38px;
        }
        
        .chat-search-icon {
            left: 12px;
            font-size: 14px;
        }
        
        /* Ensure no padding/margin on body/html when chat is active */
        body.chat-active {
            padding-bottom: 0 !important;
            margin-bottom: 0 !important;
            overflow: hidden;
        }
        
        body.chat-active .chat-container,
        body.chat-active .content-wrapper,
        body.chat-active .content {
            padding-bottom: 0 !important;
            margin-bottom: 0 !important;
        }
        
        /* For very small phones */
        @media (max-width: 375px) {
            .message-item {
                max-width: 90% !important;
                word-break: break-word;
            }
            
            .user-item {
                padding: 12px 8px !important;
            }
            
            .chat-header-title h5 {
                font-size: 14px !important;
            }
            
            .back-to-chats-btn {
                width: 36px;
                height: 36px;
                margin-right: 4px;
            }
            
            #new_group_btn_mobile {
                padding: 6px 12px;
                font-size: 13px;
            }
        }
        
        /* Hide speed dial on ALL mobile views */
        .speed-dial-container {
            display: none !important;
        }
    }
    
    /* --- DESKTOP STYLES --- */
    @media (min-width: 768px) {
        .mobile-only {
            display: none !important;
        }
        
        .desktop-only {
            display: block !important;
        }
        
        #new_group_btn_mobile {
            display: none !important;
        }
        
        #new_group_btn {
            display: inline-flex !important;
        }
        
        .chat-header {
            background: #ffffff !important;
            padding: 14px 20px;
            border-bottom: 1px solid #e9ecef;
            min-height: 64px;
        }
        
        .chat-header-title h5 {
            color: #333 !important;
            font-size: 17px;
            font-weight: 600;
            text-align: left;
            padding-left: 0;
        }
        
        .back-to-chats-btn {
            display: none !important;
        }
        
        /* Remove the empty mobile spacer */
        .mobile-only[style*="width: 44px"] {
            display: none !important;
        }
    }
    
    /* --- MODAL MOBILE FIXES --- */
    @media (max-width: 767.98px) {
        .modal.in .modal-dialog {
            transform: translateY(0);
            transition: transform 0.3s ease-out;
        }
        
        .modal.fade .modal-dialog {
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
        }
        
        .modal-content {
            border-radius: 20px 20px 0 0;
            margin-top: auto;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 15px 20px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        /* Bottom sheet style for modals */
        .modal.fade .modal-dialog.modal-bottom-sheet {
            transform: translateY(100%);
            margin: 0;
            width: 100%;
            max-width: 100%;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
        }
        
        .modal.show .modal-dialog.modal-bottom-sheet {
            transform: translateY(0);
        }
    }

            /* Prevents iOS zoom on input focus */
        @media (max-width: 767.98px) {
            input, textarea, select {
                font-size: 16px !important;
            }
        }
        * {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }
    /* --- GROUP SETTINGS MODAL ENHANCEMENTS --- */
    /* Member search inside modal */
    .member-search-wrapper {
        position: relative;
        margin-bottom: 8px;
    }
    .member-search-wrapper .fas {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        pointer-events: none;
        font-size: 0.8rem;
    }
    .member-search-input {
        padding-left: 36px !important;
        border-radius: 20px !important;
        font-size: 0.875rem;
        border: 1px solid #dee2e6;
    }
    .member-search-input:focus {
        border-color: #B75301;
        box-shadow: 0 0 0 0.2rem rgba(183,83,1,0.15);
    }
    /* Admin badge on member rows */
    .badge-admin {
        font-size: 0.65rem;
        padding: 2px 7px;
        border-radius: 10px;
        background-color: #B75301;
        color: #fff;
        margin-left: 6px;
        vertical-align: middle;
        font-weight: 600;
        letter-spacing: 0.03em;
    }
    /* Admin crown icon in header */
    #settings_admin_crown {
        color: #B75301;
        font-size: 0.85rem;
        margin-left: 6px;
        vertical-align: middle;
    }
    /* Section hidden for non-admins */
    .admin-only-section {
        /* shown/hidden by JS */
    }
    .non-admin-notice {
        display: none;
        background: #fff8f4;
        border: 1px solid #f5cba7;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 0.875rem;
        color: #7d4a1a;
        margin-bottom: 12px;
    }
    .non-admin-notice i { margin-right: 6px; }

    /* --- DATE DIVIDER (WhatsApp style) --- */
        .chat-date-divider {
            text-align: center;
            margin: 16px 0;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .chat-date-divider span {
            background-color: #e9ecef;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #6c757d;
            display: inline-block;
            backdrop-filter: blur(4px);
        }

    
</style>

<h3 class="m-0 mb-3">Messenger</h3>

<!-- Mobile Chat List Header -->
<div class="d-md-none mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="m-0">Chats</h4>
        <button class="btn btn-sm btn-primary" id="new_group_btn_mobile" type="button" data-toggle="modal" data-target="#groupModal" style="background-color:#B75301; border-color:#B75301;">
            <i class="fas fa-users mr-1"></i> New Group
        </button>
    </div>
</div>

<div class="p-0 chat-container container-fluid">
    
    <div class="user-list-sidebar">
        <div class="p-3 border-bottom bg-white sticky-top d-flex justify-content-between align-items-center" style="z-index: 5;">
            <h5 class="m-0 text-dark font-weight-bold">Chats</h5>
            <button class="btn btn-sm btn-primary desktop-only" id="new_group_btn" type="button" data-toggle="modal" data-target="#groupModal" style="background-color:#B75301; border-color:#B75301;">
                <i class="fas fa-users mr-1"></i> New Group
            </button>
        </div>
        
        <!-- Search Bar -->
        <div class="chat-search-container position-relative">
            <i class="fas fa-search chat-search-icon"></i>
            <input type="text" class="form-control chat-search-input" id="chat_search_input" placeholder="Search chats or contacts...">
        </div>
        
        <div id="group_list_display" class="pt-2 border-bottom">
            <h6 class="p-2 m-0 text-secondary">Groups</h6>
        </div>
        <div id="user_list_display">
            <h6 class="p-2 m-0 text-secondary">Direct Messages</h6>
        </div>
        
        <!-- Search Results Container -->
        <div id="search_results_container" style="display: none;">
            <h6 class="p-2 m-0 text-secondary">Search Results</h6>
            <div id="search_results_list"></div>
        </div>

        <!-- Mobile floating action button: New Group -->
        <div class="mobile-fab-container d-md-none">
            <button class="btn btn-primary btn-lg rounded-circle shadow" id="new_group_fab" type="button" data-toggle="modal" data-target="#groupModal" style="background-color:#B75301; border-color:#B75301; width: 60px; height: 60px; padding: 0;">
                <i class="fas fa-users" style="font-size: 24px;"></i>
            </button>
        </div>
    </div>

   <div class="chat-area">
        <div class="chat-header" id="recipient_header">
            <!-- Back button (mobile only) -->
            <a href="javascript:void(0);" class="back-to-chats-btn mobile-only" id="backToChatsBtn">
                <i class="fas fa-chevron-left"></i>
            </a>

            <!-- Title area -->
            <div class="chat-header-title">
                <h5 class="m-0 text-white text-truncate">Choose contact or group</h5>
            </div>

            <!-- Action buttons (settings) -->
            <div class="chat-header-actions">
                <button class="btn btn-sm btn-outline-light" id="group_settings_btn" type="button" style="display: none;">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>

        <!-- CHAT MESSAGES DISPLAY -->
        <div class="chat-box" id="chat_display">
            <div class="text-center p-5 text-muted">Choose contact or group for start conversation</div>
        </div>

        <!-- MESSAGE INPUT FORM -->
        <div id="chat_input_container" style="display: none;">
            <form id="chat_form">
                <input type="hidden" name="thread_id" id="form_thread_id">

                <!-- Selected image preview, shown before sending -->
                <div id="image_preview_bar">
                    <div class="preview-thumb-wrap">
                        <img id="image_preview_thumb" src="" alt="Preview">
                        <button type="button" class="remove-preview-btn" id="remove_image_preview_btn" title="Remove image">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <small class="text-muted">Image attached — add a caption or just send</small>
                </div>

                <div class="input-group">
                    <div class="input-group-prepend">
                        <button class="btn btn-outline-secondary" type="button" id="attach_image_btn" title="Attach image">
                            <i class="fas fa-image"></i>
                        </button>
                    </div>
                    <input type="file" id="message_image_input" accept="image/*" style="display:none;">
                    <input type="text" name="message_content" id="message_content" class="form-control" placeholder="Write message..">
                    <div class="input-group-append">
                        <button class="btn text-white" style="background-color:#B75301" type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted ml-2">Mention tag : '@user', '#project', '!task'</small>
            </form>
        </div>
    </div>

    <!-- Fullscreen image viewer (lightbox) -->
    <div id="image_viewer_overlay">
        <img id="image_viewer_img" src="" alt="Full size image">
    </div>

<!-- Modals (same as before) -->
<div class="modal fade" id="groupModal" tabindex="-1" role="dialog" aria-labelledby="groupModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupModalLabel">Create New Group</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="group_form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="group_name">Group Name</label>
                        <input type="text" class="form-control" id="group_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Members (Select multiple)</label>
                        <div id="new_group_member_list" class="member-list-container"></div>
                        <small class="text-muted">Choose group member</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" style="background-color:#B75301; border-color:#B75301;">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="groupSettingsModal" tabindex="-1" role="dialog" aria-labelledby="groupSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupSettingsModalLabel">
                    <i class="fas fa-cog mr-1"></i> Group Settings:
                    <span id="settings_group_name_title"></span>
                    <i class="fas fa-crown" id="settings_admin_crown" title="You are the admin" style="display:none;"></i>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="group_settings_form">
                <input type="hidden" name="group_id" id="settings_group_id">
                <div class="modal-body">

                    <!-- Notice shown to non-admin members -->
                    <div class="non-admin-notice" id="non_admin_notice">
                        <i class="fas fa-info-circle"></i>
                        Only the group admin can edit settings. You can view members below.
                    </div>

                    <!-- Group Name (admin only editable) -->
                    <div class="form-group admin-only-section" id="section_group_name">
                        <label for="settings_group_name">Group Name</label>
                        <input type="text" class="form-control" id="settings_group_name" name="name" required>
                    </div>
                    <!-- Read-only name for non-admins -->
                    <div class="form-group" id="section_group_name_readonly" style="display:none;">
                        <label>Group Name</label>
                        <p class="form-control-plaintext font-weight-bold" id="settings_group_name_display"></p>
                    </div>

                    <!-- Members section (visible to all) -->
                    <div class="form-group">
                        <label>
                            <span class="admin-only-section" id="label_members_admin">Add / Remove Members</span>
                            <span id="label_members_readonly" style="display:none;">Members</span>
                        </label>
                        <!-- Search inside member list -->
                        <div class="member-search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control member-search-input" id="settings_member_search" placeholder="Search member...">
                        </div>
                        <div id="settings_group_member_list" class="member-list-container"></div>
                        <small class="text-muted admin-only-section" id="hint_members_admin">Check to add, uncheck to remove</small>
                    </div>

                    <!-- Danger zone: admin only -->
                    <div class="admin-only-section" id="section_danger_zone">
                        <hr>
                        <button type="button" class="btn btn-danger btn-block mt-3" id="delete_group_btn">
                            <i class="fas fa-trash"></i> Delete Group
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <!-- Save only shown to admins -->
                    <button type="submit" class="btn btn-primary admin-only-section" id="btn_save_group_settings" style="background-color:#B75301; border-color:#B75301;">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/id.min.js"></script> 
<script>
    if (!document.querySelector('meta[name="viewport"]')) {
    var meta = document.createElement('meta');
    meta.name = 'viewport';
    meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes';
        document.head.appendChild(meta);
    }
    moment.locale('id'); 
    // Force hide group settings buttons initially
    $('#group_settings_btn').hide();
    // --- GLOBAL STATE ---
    let lookupData = { users: {}, projects: {}, tasks: {} }; 
    let reverseLookup = { users: {}, projects: {}, tasks: {} }; 
    
    let currentThreadId = '<?php echo $initial_thread_id_js; ?>'; 
    let currentRecipientId = '<?php echo $initial_recipient_id_js; ?>';
    let currentGroupId = '<?php echo $initial_group_id_js; ?>';
    
    let currentRecipientName = '';
    let currentChatType = ''; // 'personal' or 'group'
    let allUsersData = [];
    let allGroupsData = [];
    
    if (currentThreadId || currentRecipientId) {
        currentChatType = 'personal';
    } else if (currentGroupId) {
        currentChatType = 'group';
    }

    const currentUserId = <?php echo $current_user_id; ?>;
    let isChatBoxScrolledToBottom = true; 
    
    // --- HELPER FUNCTIONS ---
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function formatMessage(message) {
        const links = {
            'user': 'index.php?page=view_user&id=',
            'project': 'index.php?page=view_project&id=',
            'task': 'index.php?page=view_task&id='
        };
        
        let formattedMessage = message;

        const sortedUserNames = Object.keys(reverseLookup.users).sort((a, b) => b.length - a.length);
        const sortedProjectNames = Object.keys(reverseLookup.projects).sort((a, b) => b.length - a.length);
        const sortedTaskNames = Object.keys(reverseLookup.tasks).sort((a, b) => b.length - a.length);

        sortedUserNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const userRegex = new RegExp(`@(${escapedName})(?=\\s|$)`, 'gi');
            if (userRegex.test(formattedMessage)) {
                const id = reverseLookup.users[name];
                const encoded_id = lookupData.users[id]?.encoded_id || id;
                const replacement = `<a href="${links.user}${encoded_id}" class="mention-tag" target="_blank">@${name}</a>`;
                formattedMessage = formattedMessage.replace(userRegex, replacement);
            }
        });
        
        sortedProjectNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const projectRegex = new RegExp(`#(${escapedName})(?=\\s|$)`, 'gi');
            if (projectRegex.test(formattedMessage)) {
                const id = reverseLookup.projects[name];
                const encoded_id = lookupData.projects[id]?.encoded_id || id;
                const replacement = `<a href="${links.project}${encoded_id}" class="mention-tag" target="_blank">#${name}</a>`;
                formattedMessage = formattedMessage.replace(projectRegex, replacement);
            }
        });
        sortedTaskNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const taskRegex = new RegExp(`!(${escapedName})(?=\\s|$)`, 'gi');
            if (taskRegex.test(formattedMessage)) {
                const id = reverseLookup.tasks[name];
                const encoded_id = lookupData.tasks[id]?.encoded_id || id;
                const replacement = `<a href="${links.task}${encoded_id}" class="mention-tag" target="_blank">!${name}</a>`;
                formattedMessage = formattedMessage.replace(taskRegex, replacement);
            }
        });

        return formattedMessage;
    }

     function formatMessageDate(dateString) {
        if (!dateString) return '';
        const date = moment(dateString);
        const now = moment();
        
        if (date.isSame(now, 'day')) {
            return 'Today ' + date.format('HH:mm');
        } else if (date.isSame(now.clone().subtract(1, 'day'), 'day')) {
            return 'Yesterday ' + date.format('HH:mm');
        } else {
            return date.format('DD/MM/YYYY HH:mm');
        }
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    function buildChatHtml(messages) {
        if (!messages || messages.length === 0) {
            return '<div class="text-center p-5 text-muted">Start first Conversation</div>';
        }

        const groups = {};
        const today = moment().startOf('day');
        const yesterday = moment().subtract(1, 'day').startOf('day');

        messages.forEach(msg => {
            const dateKey = moment(msg.created_at).format('YYYY-MM-DD');
            if (!groups[dateKey]) groups[dateKey] = [];
            groups[dateKey].push(msg);
        });

        let html = '';
        Object.keys(groups).sort().forEach(dateKey => {
            const msgDate = moment(dateKey);
            let headerText = '';
            if (msgDate.isSame(today, 'day')) {
                headerText = 'Today';
            } else if (msgDate.isSame(yesterday, 'day')) {
                headerText = 'Yesterday';
            } else {
                headerText = msgDate.format('DD/MM/YYYY');
            }

            html += `<div class="chat-date-divider"><span>${headerText}</span></div>`;

            groups[dateKey].forEach(msg => {
                const isOutgoing = msg.sender_id == currentUserId;
                const messageClass = isOutgoing ? 'outgoing-message' : 'incoming-message';
                const showSenderName = !isOutgoing || currentChatType === 'group';
                const senderNameHtml = showSenderName ? `<span class="sender-name">${escapeHtml(msg.sender_name)}</span>` : '';
                const formattedContent = formatMessage(msg.message_content);

                // Image attachment support: backend (ajax.php) is expected to
                // return an `attachment_url` field pointing to the stored image
                // (e.g. 'assets/uploads/chat/xyz.jpg') when the message has one.
                const imageHtml = msg.attachment_url
                    ? `<img src="${escapeHtml(msg.attachment_url)}" class="message-image" alt="Attached image">`
                    : '';

                html += `
                    <div class="message-item ${messageClass}">
                        ${senderNameHtml}
                        ${imageHtml}
                        <p class="m-0">${formattedContent}</p>
                        <span class="timestamp">${formatMessageDate(msg.created_at)}</span>
                    </div>
                `;
            });
        });
        return html;
    }
    // --- MOBILE LAYOUT FUNCTIONS ---
    function initMobileInterface() {
        if ($(window).width() < 768) {
            // Don't override an already-active chat view (e.g. if this runs
            // again while the user is mid-conversation) — only default to
            // the sidebar view when neither state has been set yet.
            if (!$('.chat-container').hasClass('show-chat')) {
                $('.chat-container').addClass('show-sidebar');
            }
            
            // Add/remove chat-active class to body
            $('body').addClass('chat-active');
            
            $('#backToChatsBtn').off('click').on('click', function() {
                showChatSidebar();
                $('.user-item').removeClass('active');
                currentThreadId = '';
                currentRecipientId = '';
                currentGroupId = '';
                currentChatType = '';
                $('#recipient_header h5').text('Choose contact or group');
                $('#chat_display').html('<div class="text-center p-5 text-muted">Choose contact or group for start conversation</div>');
                $('#chat_input_container').hide();
                $('#group_settings_btn').hide();
            });
            
        } else {
            $('.chat-container').removeClass('show-sidebar show-chat');
            $('body').removeClass('chat-active');
        }
    }

    function showChatSidebar() {
    $('.chat-container').removeClass('show-chat').addClass('show-sidebar');
    $('body').removeClass('chat-active');
    
    // Restore normal scrolling
    $('html, body').css({
        'padding-bottom': '',
        'margin-bottom': ''
    });
    }

    function showChatArea() {
    $('.chat-container').removeClass('show-sidebar').addClass('show-chat');
    $('body').addClass('chat-active');
    
    // Force bottom alignment
    $('html, body').css({
        'padding-bottom': '0',
        'margin-bottom': '0'
    });
    
    // Scroll to bottom of chat
    setTimeout(() => {
        $('#chat_display').scrollTop($('#chat_display')[0].scrollHeight);
    }, 100);
    }

    // --- SEARCH FUNCTIONALITY ---
    function performSearch(query) {
        query = query.toLowerCase().trim();
        
        if (!query) {
            $('#search_results_container').hide();
            $('#group_list_display, #user_list_display').show();
            return;
        }
        
        $('#group_list_display, #user_list_display').hide();
        $('#search_results_container').show();
        
        const resultsList = $('#search_results_list');
        resultsList.empty();
        
        let hasResults = false;
        
        // Search in users
        allUsersData.forEach(user => {
            if (user.id == currentUserId) return;
            
            if (user.name.toLowerCase().includes(query)) {
                hasResults = true;
                addSearchResultItem('user', user);
            }
        });
        
        // Search in groups
        allGroupsData.forEach(group => {
            if (group.name.toLowerCase().includes(query)) {
                hasResults = true;
                addSearchResultItem('group', group);
            }
        });
        
        if (!hasResults) {
            resultsList.html('<div class="no-results">No chats or contacts found</div>');
        }
    }
    
    function addSearchResultItem(type, item) {
        let html = '';
        
        if (type === 'user') {
            var initials = (item.name || 'NN').split(' ').map(n => n[0]).join('').substring(0, 2);
            var avatarHtml = item.avatar ? `<img src="assets/uploads/${item.avatar}" class="img-circle elevation-2 mr-2" alt="User Image" style="width: 40px; height: 40px; object-fit: cover;">` : 
                                           `<div class="default-avatar mr-2">${initials}</div>`;
            
            html = `
                <div class="user-item d-flex" 
                     data-type="personal" 
                     data-thread-id="${item.thread_id || ''}" 
                     data-id="${item.id}" 
                     data-name="${item.name.trim()}">
                    ${avatarHtml}
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-dark">${item.name}</span>
                            <small class="text-muted">User</small>
                        </div>
                        <small class="text-muted">Click to start chat</small>
                    </div>
                </div>
            `;
        } else if (type === 'group') {
            html = `
                <div class="user-item d-flex align-items-center" 
                    data-type="group" 
                    data-id="${item.id}" 
                    data-name="${item.name.trim()}">
                    <div class="default-avatar mr-2 bg-info"><i class="fas fa-users"></i></div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-dark">${item.name}</span>
                            <small class="text-muted">Group</small>
                        </div>
                        <small class="text-muted">Click to open group chat</small>
                    </div>
                </div>
            `;
        }
        
        $('#search_results_list').append(html);
    }
    
    // --- CHAT LOGIC ---
    function loadChatMessages(isManualLoad = true) {
        if (!currentThreadId && !currentGroupId) {
            if(isManualLoad) $('#chat_display').html('<div class="text-center p-5 text-muted">Pilih kontak atau grup untuk memulai percakapan.</div>');
            $('#chat_input_container').hide();
            $('#group_settings_btn').hide(); 
            return;
        }

        const threadParam = currentChatType === 'personal' ? { thread_id: currentThreadId } : { group_id: currentGroupId };
        const chatAction = currentChatType === 'personal' ? 'get_personal_chat_messages' : 'get_group_chat_messages';

        var chatBox = $('#chat_display');
        if(isManualLoad) chatBox.html('<div class="text-center p-3 text-muted">Loading...</div>');
        
        if (currentChatType === 'group') {
            $('#recipient_header h5')
                .html('<div class="d-flex align-items-center"><i class="fas fa-users mr-2"></i>' + currentRecipientName + '</div>')
                .addClass('clickable');
            $('#group_settings_btn').show();
        } else {
            $('#recipient_header h5')
                .html('<div class="d-flex align-items-center"><i class="fas fa-comment-dots mr-2"></i>' + currentRecipientName + '</div>')
                .removeClass('clickable');
            $('#group_settings_btn').hide();
        }

        $('#chat_input_container').show();
        $('#form_thread_id').val(currentThreadId); 

        $.ajax({
            url: 'ajax.php?action=' + chatAction,
            method: 'POST',
            data: threadParam,
            dataType: 'json',
            success: function(response) {
                lookupData.users = response.users || {};
                lookupData.projects = response.projects || {};
                lookupData.tasks = response.tasks || {};
                
                reverseLookup.users = {};
                for (const id in lookupData.users) { reverseLookup.users[lookupData.users[id].name.trim()] = id; }
                reverseLookup.projects = {};
                for (const id in lookupData.projects) { reverseLookup.projects[lookupData.projects[id].name.trim()] = id; }
                reverseLookup.tasks = {};
                for (const id in lookupData.tasks) { reverseLookup.tasks[lookupData.tasks[id].name.trim()] = id; }
                
                const newContentHtml = buildChatHtml(response.messages);

                if (isManualLoad || chatBox.data('last-content') !== newContentHtml) {
                    chatBox.empty().append(newContentHtml);
                    chatBox.data('last-content', newContentHtml);
                    
                    if (isManualLoad || isChatBoxScrolledToBottom) {
                        chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                }
                loadChatSidebar(false); 
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (loadChatMessages):", status, error, xhr.responseText);
                if(isManualLoad) chatBox.html('<div class="text-center p-5 text-danger">Error loading messages. Check console for details.</div>');
            }
        });
    }
    
    function getOrCreateThread() {
        if (!currentRecipientId || currentRecipientId == currentUserId) return;
        
        $.ajax({
            url: 'ajax.php?action=get_or_create_thread_id',
            method: 'POST',
            data: { user2_id: currentRecipientId },
            success: function(threadId) {
                if (threadId > 0) {
                    currentThreadId = threadId;
                    loadChatMessages(); 
                } else {
                    alert_toast("Gagal membuat atau mendapatkan thread chat.", "error");
                    $('#chat_input_container').hide();
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan koneksi saat membuat thread.", "error");
                $('#chat_input_container').hide();
            }
        });
    }

    function loadGroupUserOptions(users, listContainerId, selectedMembers = [], adminIds = [], isAdminViewing = false) {
        const listContainer = $(listContainerId);
        listContainer.empty();
        
        users.forEach(user => {
            if(listContainerId === '#new_group_member_list' && user.id == currentUserId) return; 

            const isSelected = selectedMembers.includes(user.id.toString());

            // In settings modal: if not admin viewing, show read-only list (only selected members)
            if (listContainerId === '#settings_group_member_list' && !isAdminViewing && !isSelected) return;

            const checkedAttr = isSelected ? 'checked' : '';
            // Disable own checkbox OR disable all if not admin
            const isDisabled = (listContainerId === '#settings_group_member_list' && (user.id == currentUserId || !isAdminViewing)) ? 'disabled' : '';
            
            const checkbox = `<input type="checkbox" name="user_ids[]" value="${user.id}" ${checkedAttr} ${isDisabled}>`;
            const isAdmin = adminIds.includes(user.id.toString());
            const adminBadge = isAdmin ? `<span class="badge-admin">Admin</span>` : '';
            const userNameDisplay = user.id == currentUserId ? `${user.name} (You)` : user.name;

            const userHtml = `
                <label class="member-list-item" data-member-name="${user.name.toLowerCase()}">
                    ${checkbox}
                    <span>${userNameDisplay}${adminBadge}</span>
                </label>
            `;
            listContainer.append(userHtml);
        });
    }

    function loadGroupSettingsModalData(groupId) {
        if (!groupId) return;

        $.ajax({
            url: 'ajax.php?action=get_all_chat_sidebar_data', 
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                const allUsers = data.users || [];
                
                $.ajax({
                    url: 'ajax.php?action=get_group_details',
                    method: 'POST',
                    data: { group_id: groupId },
                    dataType: 'json',
                    success: function(groupData) {
                        if (groupData.id) {
                            $('#settings_group_id').val(groupData.id);
                            $('#settings_group_name').val(groupData.name);
                            $('#settings_group_name_title').text(groupData.name);
                            $('#settings_group_name_display').text(groupData.name);

                            // Determine admin IDs — support both created_by and per-member role field
                            const adminIds = groupData.members
                                .filter(m => m.role === 'admin' || m.user_id == groupData.created_by)
                                .map(m => m.user_id.toString());
                            // Fallback: if no admin field, treat creator as admin
                            if (adminIds.length === 0 && groupData.created_by) {
                                adminIds.push(groupData.created_by.toString());
                            }

                            const currentMemberIds = groupData.members.map(m => m.user_id.toString());
                            const isAdmin = adminIds.includes(currentUserId.toString());

                            // Toggle UI sections based on role
                            if (isAdmin) {
                                $('#settings_admin_crown').show();
                                $('#non_admin_notice').hide();
                                $('#section_group_name').show();
                                $('#section_group_name_readonly').hide();
                                $('#label_members_admin').show();
                                $('#label_members_readonly').hide();
                                $('#hint_members_admin').show();
                                $('#section_danger_zone').show();
                                $('#btn_save_group_settings').show();
                            } else {
                                $('#settings_admin_crown').hide();
                                $('#non_admin_notice').show();
                                $('#section_group_name').hide();
                                $('#section_group_name_readonly').show();
                                $('#label_members_admin').hide();
                                $('#label_members_readonly').show();
                                $('#hint_members_admin').hide();
                                $('#section_danger_zone').hide();
                                $('#btn_save_group_settings').hide();
                            }
                            
                            loadGroupUserOptions(allUsers, '#settings_group_member_list', currentMemberIds, adminIds, isAdmin);

                        } else {
                            alert_toast("Failed to load group details.", "error");
                        }
                    },
                    error: function() {
                        alert_toast("Connection error while loading group details.", "error");
                    }
                });
            }
        });
    }

    function loadChatSidebar(initialLoad = true) {
        $.ajax({
            url: 'ajax.php?action=get_all_chat_sidebar_data', 
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if(data.error) {
                     console.error("Backend Error:", data.debug);
                     return;
                }

                const users = data.users || [];
                const groups = data.groups || [];
                
                // Store data for search functionality
                allUsersData = [...users];
                allGroupsData = [...groups];

                // --- 1. PROSES PERSONAL CHAT ---
                $('#user_list_display h6').nextAll().remove(); 
                
                users.sort((a, b) => {
                    if (a.unread_count > 0 && b.unread_count === 0) return -1;
                    if (a.unread_count === 0 && b.unread_count > 0) return 1;
                    const timeA = a.last_message_timestamp ? new Date(a.last_message_timestamp).getTime() : 0;
                    const timeB = b.last_message_timestamp ? new Date(b.last_message_timestamp).getTime() : 0;
                    return timeB - timeA;
                });

                users.forEach(function(user) {
                    if (user.id == currentUserId) return; 

                    var activeClass = (user.id == currentRecipientId && currentChatType === 'personal') ? 'active' : '';
                    
                    var initials = (user.name || 'NN').split(' ').map(n => n[0]).join('').substring(0, 2);
                    var avatarHtml = user.avatar ? `<img src="assets/uploads/${user.avatar}" class="img-circle elevation-2 mr-2" alt="User Image" style="width: 40px; height: 40px; object-fit: cover;">` : 
                                                   `<div class="default-avatar mr-2">${initials}</div>`;

                    var unreadBadge = user.unread_count > 0 ? `<span class="badge badge-pill badge-danger">${user.unread_count}</span>` : '';
                    var nameBold = user.unread_count > 0 ? 'font-weight-bold' : '';
                    var contentBold = user.unread_count > 0 ? 'font-weight-bold' : '';
                    var lastMessageContent = user.last_message_content ? user.last_message_content.substring(0, 30) + (user.last_message_content.length > 30 ? '...' : '') : '<span class="text-muted">Start Conversation</span>';
                    var lastMessageTime = user.last_message_timestamp ? moment(user.last_message_timestamp).fromNow() : ''; 
                            
                   var userHtml = `
                        <div class="user-item d-flex ${activeClass}" 
                            data-type="personal" 
                            data-thread-id="${user.thread_id || ''}" 
                            data-id="${user.id}" 
                            data-name="${user.name.trim()}">
                            ${avatarHtml}
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-dark ${nameBold} text-truncate">${user.name}</span>
                                    <small class="text-muted flex-shrink-0 ml-2">${lastMessageTime}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted ${contentBold} text-truncate">${lastMessageContent}</small>
                                    ${unreadBadge ? `<span class="badge badge-pill badge-danger flex-shrink-0 ml-2">${user.unread_count}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    $('#user_list_display').append(userHtml);
                });
                
                // --- 2. PROSES GROUP CHAT ---
                $('#group_list_display h6').nextAll().remove(); 
                
                groups.sort((a, b) => {
                    if (a.unread_count > 0 && b.unread_count === 0) return -1;
                    if (a.unread_count === 0 && b.unread_count > 0) return 1;
                    const timeA = a.last_message_timestamp ? new Date(a.last_message_timestamp).getTime() : 0;
                    const timeB = b.last_message_timestamp ? new Date(b.last_message_timestamp).getTime() : 0;
                    return timeB - timeA;
                });

                groups.forEach(function(group) {
                    var activeClass = (group.id == currentGroupId && currentChatType === 'group') ? 'active' : '';
                    
                    var unreadBadge = group.unread_count > 0 ? `<span class="badge badge-pill badge-danger">${group.unread_count}</span>` : '';
                    var nameBold = group.unread_count > 0 ? 'font-weight-bold' : '';
                    var contentBold = group.unread_count > 0 ? 'font-weight-bold' : '';
                    
                    var lastMessagePrefix = group.last_sender_name ? `${group.last_sender_name}: ` : '';

                    var lastMessageContent = group.last_message_content ? 
                        lastMessagePrefix + group.last_message_content.substring(0, 20) + (group.last_message_content.length > 20 ? '...' : '') : 
                        'Start Group Conversation';
                    
                    var lastMessageTime = group.last_message_timestamp ? moment(group.last_message_timestamp).fromNow() : ''; 
                    
                    var groupHtml = `
                        <div class="user-item d-flex align-items-center ${activeClass}" 
                            data-type="group" 
                            data-id="${group.id}" 
                            data-name="${group.name.trim()}">
                            <div class="default-avatar mr-2 bg-info"><i class="fas fa-users"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-dark ${nameBold}">${group.name}</span>
                                    <small class="text-muted">${lastMessageTime}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted ${contentBold} text-truncate" style="max-width: 85%;">${lastMessageContent}</small>
                                    ${unreadBadge}
                                </div>
                            </div>
                        </div>
                    `;
                    $('#group_list_display').append(groupHtml);
                });

                // --- 3. Logika Inisialisasi dari URL ---
                if (initialLoad) {
                    if (currentRecipientId && currentChatType === 'personal') {
                        const selectedUser = $(`.user-item[data-id="${currentRecipientId}"][data-type="personal"]`);
                        if(selectedUser.length > 0) {
                           currentRecipientName = selectedUser.data('name');
                           selectedUser.addClass('active');
                           getOrCreateThread();
                        }
                    } else if (currentGroupId && currentChatType === 'group') {
                        const selectedGroup = $(`.user-item[data-id="${currentGroupId}"][data-type="group"]`);
                        if(selectedGroup.length > 0) {
                            currentRecipientName = selectedGroup.data('name');
                            selectedGroup.addClass('active');
                            loadChatMessages();
                        }
                    } else {
                         $('#chat_input_container').hide();
                         $('#group_settings_btn').hide();
                    }
                }
                
                // Preserve any members already checked in the "Create New Group" list.
                // loadChatSidebar runs on every 3s poll, which was wiping out the
                // user's checkbox selection mid-click — this is the "buggy add member" bug.
                const previouslyCheckedNewGroupMembers = $('#new_group_member_list input[name="user_ids[]"]:checked')
                    .map(function() { return $(this).val(); }).get();
                loadGroupUserOptions(users, '#new_group_member_list', previouslyCheckedNewGroupMembers);
                
                // Refresh the chat unread badge in navbar
                if (typeof updateChatBadge === 'function') {
                    updateChatBadge();
                }
                
            },
            error: function(xhr, status, error) {
                 console.error("AJAX Error (Load Chat Sidebar):", status, error, xhr.responseText);
                 if(initialLoad) $('#user_list_display').html('<div class="text-center p-3 text-danger">Gagal memuat daftar chat.</div>');
            }
        });
    }
    
    // Function to update chat unread badge in navbar
    function updateChatBadge() {
        $.ajax({
            url: 'ajax.php?action=get_total_unread_chat_count',
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.unread_count !== undefined) {
                    const count = resp.unread_count;
                    const badge = $('#chat-unread-count');
                    const chatNavItem = $('#chat-nav-item');
                    
                    if (badge.length > 0 && chatNavItem.length > 0) {
                        if (count > 0) {
                            // Show the chat icon and badge when there are unread messages
                            chatNavItem.show();
                            badge.text(count).show();
                        } else {
                            // Hide the entire chat icon when no unread messages
                            chatNavItem.hide();
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('updateChatBadge error:', status, error);
            }
        });
    }

    // --- EVENT HANDLERS ---
    $(document).on('click', '.user-item', function() {
        const item = $(this);
        const type = item.data('type');
        
        // Mobile: Switch to chat view
        if ($(window).width() < 768) {
            showChatArea();
        }
        
        $('.user-item').removeClass('active');
        item.addClass('active');
        
        currentThreadId = '';
        currentRecipientId = '';
        currentGroupId = '';
        currentChatType = type;
        clearImagePreview();
        
        $('#group_settings_btn').hide();
        currentRecipientName = item.data('name');

        if (type === 'personal') {
            currentRecipientId = item.data('id').toString();
            currentThreadId = item.data('thread-id') || ''; 
            getOrCreateThread(); 
        } else if (type === 'group') {
            currentGroupId = item.data('id').toString();
            loadChatMessages(true); 
        }

        // Click on group name in header opens group settings
        $(document).on('click', '#recipient_header h5', function() {
            if (currentChatType === 'group' && currentGroupId) {
                loadGroupSettingsModalData(currentGroupId);
                $('#groupSettingsModal').modal('show');
            }
        });

        item.find('.badge-danger').remove();
        item.find('.font-weight-bold').removeClass('font-weight-bold');
        
        // Clear search
        $('#chat_search_input').val('');
        $('#search_results_container').hide();
        $('#group_list_display, #user_list_display').show();
    });
    
    $('#group_settings_btn').click(function() {
        loadGroupSettingsModalData(currentGroupId);
        $('#groupSettingsModal').modal('show');
    });

    // Search functionality
    $('#chat_search_input').on('input', function() {
        const query = $(this).val();
        performSearch(query);
    });
    
    $('#chat_search_input').on('keyup', function(e) {
        if (e.key === 'Escape') {
            $(this).val('');
            $('#search_results_container').hide();
            $('#group_list_display, #user_list_display').show();
        }
    });

    $('#chat_form').submit(function(e) {
        e.preventDefault();
        
        if (!currentThreadId && !currentGroupId) return;

        var messageContent = $('#message_content').val().trim();

        // Must have either text or an attached image — not necessarily both
        if (messageContent === '' && !selectedImageFile) return;

        var action = currentChatType === 'personal' ? 'save_personal_chat_message' : 'save_group_chat_message';

        // Use FormData whenever an image is attached (needed for file upload);
        // falls back to the original plain serialize() when it's text-only.
        var ajaxData, ajaxOptions;
        if (selectedImageFile) {
            var fd = new FormData();
            fd.append('thread_id', $('#form_thread_id').val());
            fd.append('message_content', messageContent);
            fd.append('image', selectedImageFile);
            if (currentChatType === 'group') {
                fd.append('group_id', currentGroupId);
            }
            ajaxData = fd;
            ajaxOptions = { contentType: false, processData: false };
        } else {
            var formData = $(this).serialize();
            if (currentChatType === 'group') {
                formData += '&group_id=' + currentGroupId;
            }
            ajaxData = formData;
            ajaxOptions = {};
        }

        $.ajax($.extend({
            url: 'ajax.php?action=' + action,
            method: 'POST',
            data: ajaxData,
            success: function(resp) {
                if (resp == 1) {
                    $('#message_content').val('');
                    clearImagePreview();
                    loadChatMessages(true); 
                    loadChatSidebar(false);
                    updateChatBadge();
                } else {
                    alert_toast("Gagal menyimpan pesan: " + resp, "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan saat mengirim pesan.", "error");
            }
        }, ajaxOptions));
    });

    // --- IMAGE ATTACHMENT HANDLERS ---
    let selectedImageFile = null;
    const MAX_IMAGE_SIZE_MB = 5;

    $('#attach_image_btn').click(function() {
        $('#message_image_input').click();
    });

    $('#message_image_input').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            alert_toast("Please select an image file.", "error");
            $(this).val('');
            return;
        }
        if (file.size > MAX_IMAGE_SIZE_MB * 1024 * 1024) {
            alert_toast(`Image is too large. Max ${MAX_IMAGE_SIZE_MB}MB.`, "error");
            $(this).val('');
            return;
        }

        selectedImageFile = file;
        const reader = new FileReader();
        reader.onload = function(ev) {
            $('#image_preview_thumb').attr('src', ev.target.result);
            $('#image_preview_bar').addClass('active');
        };
        reader.readAsDataURL(file);
    });

    $('#remove_image_preview_btn').click(function() {
        clearImagePreview();
    });

    function clearImagePreview() {
        selectedImageFile = null;
        $('#message_image_input').val('');
        $('#image_preview_thumb').attr('src', '');
        $('#image_preview_bar').removeClass('active');
    }

    // Lightbox: click an image bubble to view full size
    $(document).on('click', '.message-image', function() {
        $('#image_viewer_img').attr('src', $(this).attr('src'));
        $('#image_viewer_overlay').addClass('active');
    });
    $('#image_viewer_overlay').click(function() {
        $(this).removeClass('active');
        $('#image_viewer_img').attr('src', '');
    });

    $('#group_form').submit(function(e) {
        e.preventDefault();
        
        const selectedMembers = $('#new_group_member_list input[name="user_ids[]"]:checked').length;
        if (selectedMembers === 0) {
            alert_toast("Pilih setidaknya satu anggota lain.", "error");
            return;
        }

        const formData = $(this).serialize();
        
        $.ajax({
            url: 'ajax.php?action=create_new_group',
            method: 'POST',
            data: formData,
            success: function(resp) {
                if (resp > 0) {
                    alert_toast("Grup berhasil dibuat!", "success");
                    $('#groupModal').modal('hide');
                    $('#group_form')[0].reset();
                    loadChatSidebar(true); 
                } else {
                    alert_toast("Gagal membuat grup. Pastikan Anda memilih setidaknya satu anggota.", "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan koneksi saat membuat grup.", "error");
            }
        });
    });
    
    $('#group_settings_form').submit(function(e) {
        e.preventDefault();
        
        // Guard: only admins can save
        if ($('#btn_save_group_settings').is(':hidden')) {
            alert_toast("Only group admins can edit settings.", "error");
            return;
        }
        
        const checkedMembers = $('#settings_group_member_list input[name="user_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!checkedMembers.includes(currentUserId.toString())) {
             checkedMembers.push(currentUserId.toString());
        }

        if (checkedMembers.length === 0) {
            alert_toast("Grup harus memiliki setidaknya satu anggota.", "error");
            return;
        }
        
        let formData = $(this).serializeArray();
        formData = formData.filter(item => item.name !== 'user_ids[]');
        
        checkedMembers.forEach(id => {
            formData.push({ name: 'user_ids[]', value: id });
        });

        $.ajax({
            url: 'ajax.php?action=update_group_settings', 
            method: 'POST',
            data: $.param(formData),
            success: function(resp) {
                if (resp == 1) {
                    alert_toast("Group update success", "success");
                    $('#groupSettingsModal').modal('hide');
                    currentRecipientName = $('#settings_group_name').val();
                    loadChatMessages(true); 
                    loadChatSidebar(false); 
                } else {
                    alert_toast("error: " + resp, "error");
                }
            },
            error: function() {
                alert_toast("error update", "error");
            }
        });
    });

    $('#delete_group_btn').click(function() {
        if (confirm("You really want to delete this group? This action cannot be undone.")) {
            $.ajax({
                url: 'ajax.php?action=delete_group',
                method: 'POST',
                data: { group_id: currentGroupId },
                success: function(resp) {
                    if (resp == 1) {
                        alert_toast("Grup delete success", "success");
                        $('#groupSettingsModal').modal('hide');
                        
                        currentThreadId = '';
                        currentRecipientId = '';
                        currentGroupId = '';
                        currentChatType = '';
                        $('#recipient_header h5').text('Choose contact or group');
                        $('#chat_display').html('<div class="text-center p-5 text-muted">Group is delete, please choose another chat</div>');
                        $('#chat_input_container').hide();
                        $('#group_settings_btn').hide();
                        loadChatSidebar(true); 
                    } else {
                        alert_toast("Error delete: " + resp, "error");
                    }
                },
                error: function() {
                    alert_toast("Error delete, please check your connection", "error");
                }
            });
        }
    });

    $('#new_group_btn_mobile').click(function(e) {
        e.preventDefault();
        $('#groupModal').modal('show');
    });

    $('#groupModal').on('hidden.bs.modal', function() {
        $('#group_form')[0].reset();
        loadChatSidebar(false);
    });

    // Live search filter inside Group Settings member list
    $('#settings_member_search').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        $('#settings_group_member_list .member-list-item').each(function() {
            const name = $(this).data('member-name') || '';
            $(this).toggle(!query || name.includes(query));
        });
    });

    // Clear member search when modal opens
    $('#groupSettingsModal').on('show.bs.modal', function() {
        $('#settings_member_search').val('');
    });
 
   

    // --- INITIALIZATION ---
    initMobileInterface();

    // On mobile, opening the on-screen keyboard shrinks the viewport height,
    // which fires a 'resize' event even though nothing actually changed
    // layout-wise. Re-running initMobileInterface() on that phantom resize
    // was forcing the chat area to hide (since it always re-added
    // 'show-sidebar'), which killed focus on the message input and made the
    // keyboard pop back closed. Only react when the *width* actually changes.
    let lastWindowWidth = $(window).width();
    $(window).on('resize', function() {
        const newWidth = $(window).width();
        if (newWidth !== lastWindowWidth) {
            lastWindowWidth = newWidth;
            initMobileInterface();
        }
    });

    loadChatSidebar(true);
    
    // --- POLLING ---
    setInterval(function(){
        if(currentThreadId || currentGroupId) {
             loadChatMessages(false); 
        }
        loadChatSidebar(false);
    }, 3000);

    
</script>