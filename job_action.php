<?php
require_once '../hr_auth.php';
if (empty($_SESSION['hr_logged_in'])) {
   header('Location: /hr/login');
    exit;
}

// CSRF verification
$submitted_csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
    header('Location: /hr/dashboard?error=' . urlencode('Security token mismatch. Please try again.'));

    exit;
}

require_once '../db_connect.php';

$action = $_POST['action'] ?? '';

function redirectWithMsg($type, $msg) {
    header("Location: /hr/dashboard?tab=jobs&{$type}=" . urlencode($msg));
    exit;
}

// ── ADD / EDIT ────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {

    $title       = trim($_POST['title']       ?? '');
    $category    = trim($_POST['category']    ?? '');
    $location    = trim($_POST['location']    ?? '');
    $type        = trim($_POST['type']        ?? '');
    $experience  = trim($_POST['experience']  ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    $raw_openings = $_POST['openings'] ?? '';
    $openings     = ($raw_openings !== '' && (int)$raw_openings > 0) ? (int)$raw_openings : null;

    $allowed_categories = ['Technology', 'Marketing', 'Business'];
    if (!$title || !$category || !$location || !$type || !$experience || !$description) {
        redirectWithMsg('error', 'Please fill in all required fields.');
    }
    if (!in_array($category, $allowed_categories, true)) {
        redirectWithMsg('error', 'Invalid category. Must be Technology, Marketing, or Business.');
    }

    // Tags: comma-separated → JSON array
    $tags = array_values(array_filter(array_map('trim', explode(',', trim($_POST['tags'] ?? '')))));

    // Responsibilities & Requirements: one per line → JSON array
    $resp = array_values(array_filter(array_map('trim', explode("\n", trim($_POST['responsibilities'] ?? '')))));
    $reqs = array_values(array_filter(array_map('trim', explode("\n", trim($_POST['requirements']    ?? '')))));

    // Always produce valid JSON — fallback to '[]' if encode fails
    $tags_json = json_encode($tags, JSON_UNESCAPED_UNICODE) ?: '[]';
    $resp_json = json_encode($resp, JSON_UNESCAPED_UNICODE) ?: '[]';
    $reqs_json = json_encode($reqs, JSON_UNESCAPED_UNICODE) ?: '[]';

    if ($action === 'add') {
        $stmt = $conn->prepare(
            "INSERT INTO job_posts
             (title,category,location,type,experience,openings,description,tags,responsibilities,requirements,is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        // s s s s s  i        s           s          s          s          i
        $stmt->bind_param('sssssissssi',
            $title, $category, $location, $type, $experience,
            $openings, $description,
            $tags_json, $resp_json, $reqs_json, $is_active
        );
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) redirectWithMsg('success', 'Job post created successfully.');
        redirectWithMsg('error', 'Failed to create job post. ' . $conn->error);
    }

    // edit
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) redirectWithMsg('error', 'Invalid job ID.');

    $stmt = $conn->prepare(
        "UPDATE job_posts
         SET title=?,category=?,location=?,type=?,experience=?,openings=?,description=?,
             tags=?,responsibilities=?,requirements=?,is_active=?
         WHERE id=?"
    );
    // s s s s s  i        s            s          s          s          i          i
    $stmt->bind_param('sssssissssii',
        $title, $category, $location, $type, $experience,
        $openings, $description,
        $tags_json, $resp_json, $reqs_json, $is_active, $id
    );
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) redirectWithMsg('success', 'Job post updated successfully.');
    redirectWithMsg('error', 'Failed to update job post. ' . $conn->error);
}

// ── TOGGLE ACTIVE ─────────────────────────────────────
if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) redirectWithMsg('error', 'Invalid job ID.');
    $stmt = $conn->prepare("UPDATE job_posts SET is_active = 1 - is_active WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    redirectWithMsg('success', 'Job status updated.');
}

// ── DELETE ────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) redirectWithMsg('error', 'Invalid job ID.');
    $stmt = $conn->prepare("DELETE FROM job_posts WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    redirectWithMsg('success', 'Job post deleted.');
}

redirectWithMsg('error', 'Invalid action.');
