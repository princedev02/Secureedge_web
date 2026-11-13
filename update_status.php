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

$id     = (int)($_POST['id']     ?? 0);
$status = trim($_POST['status']  ?? '');

$allowed = ['pending', 'hold', 'selected', 'rejected'];

if (!$id || !in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $conn->prepare("UPDATE career_applications SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed.']);
}

$stmt->close();
$conn->close();
