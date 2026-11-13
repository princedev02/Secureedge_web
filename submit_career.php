<?php
header('Content-Type: application/json');

require_once '../db_connect.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Sanitize text inputs
function clean($value) {
    return strip_tags(trim($value));
}

$name      = clean($_POST['name']      ?? '');
$email     = clean($_POST['email']     ?? '');
$phone     = clean($_POST['phone']     ?? '');
$role      = clean($_POST['role']      ?? '');
$linkedin  = clean($_POST['linkedin']  ?? '');
$portfolio = clean($_POST['portfolio'] ?? '');

// Basic validation
if (!$name || !$email || !$phone || !$role) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

if ($linkedin && (!filter_var($linkedin, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $linkedin))) {
    echo json_encode(['success' => false, 'message' => 'LinkedIn URL must start with http:// or https://']);
    exit;
}

if ($portfolio && (!filter_var($portfolio, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $portfolio))) {
    echo json_encode(['success' => false, 'message' => 'Portfolio URL must start with http:// or https://']);
    exit;
}

if (!preg_match('/^\d{10}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone must be exactly 10 digits.']);
    exit;
}

// Handle resume upload
if (empty($_FILES['resume']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Resume file is required.']);
    exit;
}

$allowed_types = ['application/pdf', 'application/msword',
                  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$file_mime = $finfo->file($_FILES['resume']['tmp_name']);

if (!in_array($file_mime, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Only PDF, DOC, or DOCX files are allowed.']);
    exit;
}

$max_size = 5 * 1024 * 1024; // 5 MB
if ($_FILES['resume']['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'Resume file must be under 5 MB.']);
    exit;
}

$upload_dir = __DIR__ . '/uploads/resumes/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$ext           = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
$safe_filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$dest_path     = $upload_dir . $safe_filename;

if (!move_uploaded_file($_FILES['resume']['tmp_name'], $dest_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save resume. Please try again.']);
    exit;
}

$resume_path = 'uploads/resumes/' . $safe_filename;

// Insert into DB
$stmt = $conn->prepare(
    "INSERT INTO career_applications (name, email, phone, role, linkedin, portfolio, resume_path)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param('sssssss', $name, $email, $phone, $role, $linkedin, $portfolio, $resume_path);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Application submitted successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save application. Please try again.']);
}

$stmt->close();
$conn->close();
