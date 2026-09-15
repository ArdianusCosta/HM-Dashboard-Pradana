<?php
/**
 * upload_image.php
 * Handles image uploads from Summernote (toolbar button & paste).
 * Returns JSON: { "success": true, "url": "..." }
 *            or { "success": false, "error": "..." }
 */

include 'db_connect.php';
session_start();

header('Content-Type: application/json');

// ── Auth check ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Validate upload ─────────────────────────────────────────────────────────
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['image']['error'] ?? 'No file received';
    echo json_encode(['success' => false, 'error' => 'Upload error: ' . $err]);
    exit;
}

$file     = $_FILES['image'];
$maxSize  = 5 * 1024 * 1024; // 5 MB
$allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Size check
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Image exceeds 5 MB limit']);
    exit;
}

// MIME check (use finfo for reliability, fall back to $_FILES type)
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type: ' . $mimeType]);
    exit;
}

// ── Build destination ────────────────────────────────────────────────────────
$uploadDir = __DIR__ . '/assets/uploads/comments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = 'img_' . uniqid('', true) . '.' . strtolower($ext);
$destPath = $uploadDir . $filename;

// ── Move file ────────────────────────────────────────────────────────────────
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file on server']);
    exit;
}

// ── Return public URL ────────────────────────────────────────────────────────
// Adjust the base path below if your app lives in a subdirectory
$publicUrl = 'assets/uploads/comments/' . $filename;

echo json_encode(['success' => true, 'url' => $publicUrl]);