<?php
require_once '../hr_auth.php';
header('Content-Type: application/json');

if (empty($_SESSION['hr_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// CSRF verification
$submitted_csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit;
}

require_once '../db_connect.php';

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

// Get resume path before deleting so we can remove the file
$stmt = $conn->prepare("SELECT resume_path FROM career_applications WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit;
}

// Delete DB record
$stmt = $conn->prepare("DELETE FROM career_applications WHERE id = ?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

if ($ok) {
    // Remove resume file — verify path stays inside uploads/resumes/
    $base_dir = realpath(__DIR__ . '/uploads/resumes/');
    $abs_path = realpath(__DIR__ . '/' . $row['resume_path']);
    if ($base_dir && $abs_path && strpos($abs_path, $base_dir) === 0 && is_file($abs_path)) {
        @unlink($abs_path);
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed.']);
}
