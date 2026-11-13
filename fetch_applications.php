<?php
require_once '../hr_auth.php';
header('Content-Type: application/json');

if (empty($_SESSION['hr_logged_in'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

// Rate limit: enforce a minimum 10-second gap between polls per session
$now = time();
if (isset($_SESSION['last_poll']) && ($now - $_SESSION['last_poll']) < 10) {
    http_response_code(429);
    echo json_encode([]);
    exit;
}
$_SESSION['last_poll'] = $now;

require_once '../db_connect.php';

$since = $_GET['since'] ?? '';

// Reject malformed timestamps before touching the DB
if ($since !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

if ($since !== '') {
    $stmt = $conn->prepare(
        "SELECT id,name,email,phone,role,linkedin,portfolio,resume_path,status,submitted_at
         FROM career_applications WHERE submitted_at > ? ORDER BY submitted_at DESC LIMIT 100"
    );
    $stmt->bind_param('s', $since);
} else {
    $stmt = $conn->prepare(
        "SELECT id,name,email,phone,role,linkedin,portfolio,resume_path,status,submitted_at
         FROM career_applications ORDER BY submitted_at DESC LIMIT 200"
    );
}

$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Strip server path — send only the filename the JS actually needs
foreach ($apps as &$app) {
    $app['resume_path'] = basename($app['resume_path']);
}
unset($app);

echo json_encode($apps);
