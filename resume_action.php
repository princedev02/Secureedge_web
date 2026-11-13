<?php
require_once '../hr_auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Must be logged in
if (empty($_SESSION['hr_logged_in'])) {
    http_response_code(403);
    exit('Access denied.');
}

$action   = $_GET['action'] ?? '';
$filename = $_GET['file']   ?? '';

// Sanitize: allow only alphanumeric, dash, underscore, dot — no path traversal
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
    http_response_code(400);
    exit('Invalid file name.');
}

$base_dir  = __DIR__ . '/uploads/resumes/';
$file_path = realpath($base_dir . $filename);

// Ensure resolved path is still inside uploads/resumes/
if ($file_path === false || strpos($file_path, realpath($base_dir)) !== 0) {
    http_response_code(404);
    exit('File not found.');
}

if (!is_file($file_path)) {
    http_response_code(404);
    exit('File not found.');
}

// Determine MIME type
$ext  = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_map = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mime_map[$ext] ?? 'application/octet-stream';

if ($action === 'view') {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $filename . '"');
} elseif ($action === 'download') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    http_response_code(400);
    exit('Invalid action.');
}

header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, no-cache');
readfile($file_path);
exit;
