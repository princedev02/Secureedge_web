<?php
require_once '../hr_auth.php';
if (empty($_SESSION['hr_logged_in'])) {
    header('Location: /hr/login');
    exit;
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
$csrf = $_SESSION['csrf_token'];
require_once '../db_connect.php';

$tab      = $_GET['tab']     ?? 'applications';
$flash_ok = htmlspecialchars($_GET['success'] ?? '', ENT_QUOTES);
$flash_err= htmlspecialchars($_GET['error']   ?? '', ENT_QUOTES);

// ── Applications
$appResult    = $conn->query(
    "SELECT id,name,email,phone,role,linkedin,portfolio,resume_path,status,submitted_at
     FROM career_applications ORDER BY submitted_at DESC"
);
$applications = $appResult ? $appResult->fetch_all(MYSQLI_ASSOC) : [];

// ── Jobs
$jobResult = $conn->query("SELECT * FROM job_posts ORDER BY created_at DESC");
$jobs      = $jobResult ? $jobResult->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

// Stats
$total      = count($applications);
$pending    = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));
$selected   = count(array_filter($applications, fn($a) => $a['status'] === 'selected'));
$rejected   = count(array_filter($applications, fn($a) => $a['status'] === 'rejected'));
$totalJobs  = count($jobs);
$activeJobs = count(array_filter($jobs, fn($j) => $j['is_active']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>HR Dashboard — SecureEdge</title>
<link rel="icon" type="image/png" href="./assets/images/se-logo-favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',sans-serif;background:#0e0d0d;color:#e0e0e0;min-height:100vh}

/* ── HEADER */
.hdr{background:#1a1a1a;border-bottom:1px solid #2a2a2a;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200}
.hdr-l{display:flex;align-items:center;gap:12px}
.hdr-l img{height:32px}
.hdr-title{font-size:18px;font-weight:600;color:#fff}
.hdr-title span{color:#e63946}
.hdr-r{display:flex;align-items:center;gap:16px}
.user-badge{font-size:13px;color:#888}
.btn-career{background:transparent;border:1px solid #333;color:#aaa;border-radius:6px;padding:7px 16px;font-family:'Outfit',sans-serif;font-size:13px;font-weight:500;text-decoration:none;transition:border-color .2s,color .2s;display:flex;align-items:center;gap:6px}
.btn-career:hover{border-color:#aaa;color:#fff}
.btn-logout{background:transparent;border:1px solid #e63946;color:#e63946;border-radius:6px;padding:7px 16px;font-family:'Outfit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:background .2s,color .2s}
.btn-logout:hover{background:#e63946;color:#fff}

/* ── FLASH */
.flash{padding:12px 32px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px}
.flash.ok{background:rgba(34,197,94,.1);border-bottom:1px solid rgba(34,197,94,.25);color:#4ade80}
.flash.err{background:rgba(230,57,70,.1);border-bottom:1px solid rgba(230,57,70,.25);color:#e63946}
.flash-close{margin-left:auto;cursor:pointer;opacity:.6;font-size:18px;background:none;border:none;color:inherit}
.flash-close:hover{opacity:1}

/* ── TABS */
.tabs{display:flex;gap:0;border-bottom:1px solid #2a2a2a;padding:0 32px;background:#111}
.tab-btn{background:none;border:none;border-bottom:2px solid transparent;color:#888;font-family:'Outfit',sans-serif;font-size:14px;font-weight:500;padding:14px 20px;cursor:pointer;transition:color .2s,border-color .2s;white-space:nowrap}
.tab-btn.active{color:#e63946;border-bottom-color:#e63946}
.tab-btn:hover:not(.active){color:#ccc}

/* ── MAIN */
.main{padding:28px 32px}

/* ── STATS */
.stats-row{display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap}
.stat-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:18px 22px;flex:1;min-width:140px}
.stat-label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px}
.stat-value{font-size:26px;font-weight:700;color:#fff;margin-top:4px}
.stat-value.red{color:#e63946}
.stat-value.green{color:#4ade80}
.stat-value.yellow{color:#facc15}
.stat-value.blue{color:#60a5fa}

/* ── CONTROLS */
.controls{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center}
.search-box{flex:1;min-width:200px;background:#1a1a1a;border:1px solid #333;border-radius:8px;padding:9px 14px;font-family:'Outfit',sans-serif;font-size:13px;color:#e0e0e0;outline:none;transition:border-color .2s}
.search-box::placeholder{color:#555}
.search-box:focus{border-color:#e63946}
.filter-sel{background:#1a1a1a;border:1px solid #333;border-radius:8px;padding:9px 14px;font-family:'Outfit',sans-serif;font-size:13px;color:#e0e0e0;outline:none;cursor:pointer}
.filter-sel:focus{border-color:#e63946}
.results-count{font-size:12px;color:#555;margin-left:auto;white-space:nowrap}

/* ── BUTTON */
.btn-add{background:#e63946;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-family:'Outfit',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s}
.btn-add:hover{background:#c1121f}

/* ── TABLE */
.tbl-wrap{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;overflow:hidden;overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:900px}
thead th{background:#111;padding:12px 14px;text-align:left;font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #2a2a2a;white-space:nowrap}
tbody tr{border-bottom:1px solid #1f1f1f;transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#1f1f1f}
td{padding:12px 14px;font-size:13px;vertical-align:middle}
.id-col{color:#555;font-size:11px}

.role-badge{background:rgba(230,57,70,.12);color:#e63946;border:1px solid rgba(230,57,70,.25);border-radius:20px;padding:2px 9px;font-size:11px;font-weight:500;white-space:nowrap}

.link-a{color:#e63946;font-size:12px;text-decoration:none;border:1px solid rgba(230,57,70,.3);border-radius:5px;padding:2px 7px;transition:background .15s;white-space:nowrap}
.link-a:hover{background:rgba(230,57,70,.15)}
.no-link{color:#444;font-size:11px}

.resume-btns{display:flex;gap:5px}
.btn-view,.btn-dl{font-family:'Outfit',sans-serif;font-size:11px;font-weight:500;padding:4px 10px;border-radius:5px;cursor:pointer;text-decoration:none;border:1px solid;transition:background .15s,color .15s;white-space:nowrap}
.btn-view{background:transparent;border-color:#4a9eff;color:#4a9eff}
.btn-view:hover{background:#4a9eff;color:#fff}
.btn-dl{background:transparent;border-color:#e63946;color:#e63946}
.btn-dl:hover{background:#e63946;color:#fff}

.date-cell{color:#888;font-size:12px;white-space:nowrap}

/* ── STATUS SELECT */
.status-sel{border:none;border-radius:16px;padding:3px 10px;font-family:'Outfit',sans-serif;font-size:11px;font-weight:600;cursor:pointer;outline:none;appearance:none;text-align:center}
.status-sel.pending {background:rgba(250,204,21,.15);color:#facc15}
.status-sel.hold    {background:rgba(251,146,60,.15);color:#fb923c}
.status-sel.selected{background:rgba(74,222,128,.15);color:#4ade80}
.status-sel.rejected{background:rgba(230,57,70,.15); color:#e63946}

/* ── ACTION BTNS */
.btn-del{background:transparent;border:1px solid #333;color:#888;border-radius:5px;padding:4px 9px;font-size:11px;cursor:pointer;transition:border-color .15s,color .15s,background .15s;white-space:nowrap;font-family:'Outfit',sans-serif}
.btn-del:hover{border-color:#e63946;color:#e63946;background:rgba(230,57,70,.08)}

/* ── JOBS TABLE */
.active-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px}
.active-dot.on{background:#4ade80}
.active-dot.off{background:#555}
.job-title-cell strong{display:block;font-size:13px;color:#e0e0e0}
.job-title-cell small{font-size:11px;color:#666}
.btn-toggle{background:transparent;border:1px solid #333;color:#888;border-radius:5px;padding:4px 9px;font-size:11px;cursor:pointer;transition:all .15s;white-space:nowrap;font-family:'Outfit',sans-serif}
.btn-toggle:hover{border-color:#4ade80;color:#4ade80}
.btn-edit{background:transparent;border:1px solid #4a9eff;color:#4a9eff;border-radius:5px;padding:4px 9px;font-size:11px;cursor:pointer;transition:all .15s;white-space:nowrap;font-family:'Outfit',sans-serif}
.btn-edit:hover{background:#4a9eff;color:#fff}

/* ── EMPTY STATE */
.empty-state{text-align:center;padding:56px 32px;color:#555}
.empty-state svg{width:42px;height:42px;margin-bottom:14px;opacity:.3}
.empty-state p{font-size:14px}

/* ── MODAL OVERLAY */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:16px;padding:32px;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;position:relative}
.modal-title{font-size:18px;font-weight:600;color:#fff;margin-bottom:24px}
.modal-close{position:absolute;top:16px;right:20px;background:none;border:none;color:#888;cursor:pointer;font-size:22px;line-height:1}
.modal-close:hover{color:#fff}

/* ── FORM FIELDS */
.fg{margin-bottom:16px}
.fg label{display:block;font-size:12px;font-weight:500;color:#aaa;margin-bottom:6px}
.fg input,.fg textarea,.fg select{width:100%;background:#111;border:1px solid #333;border-radius:7px;padding:10px 13px;font-family:'Outfit',sans-serif;font-size:13px;color:#e0e0e0;outline:none;transition:border-color .2s;resize:vertical}
.fg input:focus,.fg textarea:focus,.fg select:focus{border-color:#e63946}
.fg textarea{min-height:80px}
.fg-hint{font-size:11px;color:#555;margin-top:4px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.cb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:#aaa}
.cb-row input[type=checkbox]{width:16px;height:16px;accent-color:#e63946;cursor:pointer}
.modal-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:20px;border-top:1px solid #2a2a2a}
.btn-cancel{background:transparent;border:1px solid #333;color:#888;border-radius:7px;padding:9px 20px;font-family:'Outfit',sans-serif;font-size:13px;cursor:pointer;transition:border-color .2s,color .2s}
.btn-cancel:hover{border-color:#888;color:#fff}
.btn-save{background:#e63946;color:#fff;border:none;border-radius:7px;padding:9px 24px;font-family:'Outfit',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s}
.btn-save:hover{background:#c1121f}

/* ── RESPONSIVE */
/* ── TOAST */
.toast{position:fixed;bottom:24px;right:24px;background:#1a1a1a;border:1px solid #4ade80;border-radius:10px;padding:12px 18px;font-size:13px;color:#4ade80;z-index:9999;display:flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,.5);transform:translateY(12px);opacity:0;transition:transform .3s,opacity .3s;pointer-events:none}
.toast.show{transform:translateY(0);opacity:1}
@keyframes newrow{0%{background:rgba(74,222,128,.12)}100%{background:transparent}}
.new-row{animation:newrow 2s ease-out forwards}

/* ── LIVE BADGE */
.live-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#4ade80;margin-right:5px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

@media(max-width:600px){
    .hdr,.main{padding-left:16px;padding-right:16px}
    .tabs{padding:0 16px}
    .stats-row{flex-direction:column}
    .form-row{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- Header -->
<header class="hdr">
    <div class="hdr-l">
        <img src="./assets/images/se-logo-favicon.png" alt="SE" onerror="this.style.display='none'"/>
        <span class="hdr-title">Secure<span>Edge</span> HR Portal</span>
    </div>
    <div class="hdr-r">
        <span class="user-badge"><span class="live-dot"></span>Live</span>
        <span class="user-badge">Logged in as <strong><?= htmlspecialchars($_SESSION['hr_user']) ?></strong></span>
        <a href="/career" target="_blank" class="btn-career">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            View Career Page
        </a>
        <a href="/hr/logout" class="btn-logout">Logout</a>
    </div>
</header>

<!-- Flash message -->
<?php if ($flash_ok): ?>
<div class="flash ok" id="flashBar">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <?= $flash_ok ?>
    <button class="flash-close" onclick="document.getElementById('flashBar').remove()">&#x2715;</button>
</div>
<?php elseif ($flash_err): ?>
<div class="flash err" id="flashBar">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= $flash_err ?>
    <button class="flash-close" onclick="document.getElementById('flashBar').remove()">&#x2715;</button>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
    <button class="tab-btn <?= $tab === 'applications' ? 'active' : '' ?>" onclick="switchTab('applications')">
        Applications <span id="tabAppCount" style="background:#e63946;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:6px"><?= $total ?></span>
    </button>
    <button class="tab-btn <?= $tab === 'jobs' ? 'active' : '' ?>" onclick="switchTab('jobs')">
        Job Posts <span style="background:#333;color:#aaa;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:6px"><?= $totalJobs ?></span>
    </button>
</div>

<main class="main">

<!-- ══════════════════════════════════════
     APPLICATIONS TAB
══════════════════════════════════════ -->
<div id="tab-applications" style="display:<?= $tab === 'applications' ? 'block' : 'none' ?>">

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card"><div class="stat-label">Total</div><div class="stat-value" id="statTotal"><?= $total ?></div></div>
        <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value yellow" id="statPending"><?= $pending ?></div></div>
        <div class="stat-card"><div class="stat-label">Selected</div><div class="stat-value green" id="statSelected"><?= $selected ?></div></div>
        <div class="stat-card"><div class="stat-label">Rejected</div><div class="stat-value red" id="statRejected"><?= $rejected ?></div></div>
    </div>

    <!-- Controls -->
    <div class="controls">
        <input type="text" class="search-box" id="appSearch" placeholder="Search by name, email or role…" oninput="filterApps()"/>
        <select class="filter-sel" id="appStatus" onchange="filterApps()">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="hold">On Hold</option>
            <option value="selected">Selected</option>
            <option value="rejected">Rejected</option>
        </select>
        <select class="filter-sel" id="appSort" onchange="filterApps()">
            <option value="date_desc">Newest First</option>
            <option value="date_asc">Oldest First</option>
            <option value="name_asc">Name A→Z</option>
            <option value="name_desc">Name Z→A</option>
        </select>
        <span class="results-count" id="appCount"><?= $total ?> applications</span>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
        <table id="appTable">
            <thead>
                <tr>
                    <th class="id-col">#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>LinkedIn</th>
                    <th>Portfolio</th>
                    <th>Resume</th>
                    <th>Status</th>
                    <th>Applied On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="appBody">
            <?php if (empty($applications)): ?>
            <tr><td colspan="11">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p>No applications yet.</p>
                </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($applications as $app): ?>
            <tr
                data-name="<?= strtolower(htmlspecialchars($app['name'])) ?>"
                data-email="<?= strtolower(htmlspecialchars($app['email'])) ?>"
                data-role="<?= strtolower(htmlspecialchars($app['role'])) ?>"
                data-status="<?= $app['status'] ?>"
                data-date="<?= $app['submitted_at'] ?>"
            >
                <td class="id-col"><?= $app['id'] ?></td>
                <td><strong><?= htmlspecialchars($app['name']) ?></strong></td>
                <td style="font-size:12px"><?= htmlspecialchars($app['email']) ?></td>
                <td><?= htmlspecialchars($app['phone']) ?></td>
                <td><span class="role-badge"><?= htmlspecialchars($app['role']) ?></span></td>
                <td>
                    <?php if ($app['linkedin']): ?>
                        <a href="<?= htmlspecialchars($app['linkedin']) ?>" target="_blank" class="link-a">LinkedIn ↗</a>
                    <?php else: ?><span class="no-link">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($app['portfolio']): ?>
                        <a href="<?= htmlspecialchars($app['portfolio']) ?>" target="_blank" class="link-a">Portfolio ↗</a>
                    <?php else: ?><span class="no-link">—</span><?php endif; ?>
                </td>
                <td>
                    <div class="resume-btns">
                       <a href="/resume/view?file=<?= urlencode(basename($app['resume_path'])) ?>" target="_blank" class="btn-view">View</a>

                       <a href="/resume/download?file=<?= urlencode(basename($app['resume_path'])) ?>" class="btn-dl">Download </a>

                    </div>
                </td>
                <td>
                    <select class="status-sel <?= $app['status'] ?>" onchange="updateStatus(<?= $app['id'] ?>, this)">
                        <option value="pending"  <?= $app['status']==='pending'  ?'selected':'' ?>>Pending</option>
                        <option value="hold"     <?= $app['status']==='hold'     ?'selected':'' ?>>On Hold</option>
                        <option value="selected" <?= $app['status']==='selected' ?'selected':'' ?>>Selected</option>
                        <option value="rejected" <?= $app['status']==='rejected' ?'selected':'' ?>>Rejected</option>
                    </select>
                </td>
                <td class="date-cell"><?= date('d M Y', strtotime($app['submitted_at'])) ?></td>
                <td>
                    <button class="btn-del" onclick="deleteApp(<?= $app['id'] ?>, this)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════
     JOBS TAB
══════════════════════════════════════ -->
<div id="tab-jobs" style="display:<?= $tab === 'jobs' ? 'block' : 'none' ?>">

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card"><div class="stat-label">Total Posts</div><div class="stat-value"><?= $totalJobs ?></div></div>
        <div class="stat-card"><div class="stat-label">Active</div><div class="stat-value green"><?= $activeJobs ?></div></div>
        <div class="stat-card"><div class="stat-label">Inactive</div><div class="stat-value"><?= $totalJobs - $activeJobs ?></div></div>
    </div>

    <!-- Controls -->
    <div class="controls">
        <button class="btn-add" onclick="openJobModal()">+ Add Job Post</button>
        <span class="results-count"><?= $totalJobs ?> job post<?= $totalJobs !== 1 ? 's' : '' ?></span>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th class="id-col">#</th>
                    <th>Title / Category</th>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Experience</th>
                    <th>Openings</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($jobs)): ?>
            <tr><td colspan="8">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
                    <p>No job posts yet. Click "Add Job Post" to create one.</p>
                </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($jobs as $job): ?>
            <tr>
                <td class="id-col"><?= $job['id'] ?></td>
                <td class="job-title-cell">
                    <strong><?= htmlspecialchars($job['title']) ?></strong>
                    <small><?= htmlspecialchars($job['category']) ?></small>
                </td>
                <td style="font-size:12px"><?= htmlspecialchars($job['location']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($job['type']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($job['experience']) ?></td>
                <td style="font-size:12px"><?= $job['openings'] ?? '—' ?></td>
                <td>
                    <span>
                        <span class="active-dot <?= $job['is_active'] ? 'on' : 'off' ?>"></span>
                        <span style="font-size:12px;color:<?= $job['is_active'] ? '#4ade80' : '#555' ?>">
                            <?= $job['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </span>
                </td>
                <td>
                    <div style="display:flex;gap:5px">
                        <button class="btn-edit" onclick='editJob(<?= htmlspecialchars(json_encode($job), ENT_QUOTES) ?>)'>Edit</button>
                        <form method="POST" action="/job-action" style="display:inline" onsubmit="return confirm('Update job status!')">
                            <input type="hidden" name="action" value="toggle"/>
                            <input type="hidden" name="id" value="<?= $job['id'] ?>"/>
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>"/>
                            <button type="submit" class="btn-toggle"><?= $job['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <form method="POST" action="/job-action" style="display:inline" onsubmit="return confirm('Delete this job post?')">
                            <input type="hidden" name="action" value="delete"/>
                            <input type="hidden" name="id" value="<?= $job['id'] ?>"/>
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>"/>
                            <button type="submit" class="btn-del">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<!-- ══════════════════════════════════════
     ADD / EDIT JOB MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="jobModal" onclick="closeModalOnOverlay(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeJobModal()">&#x2715;</button>
        <div class="modal-title" id="modalTitle">Add Job Post</div>
        <form method="POST" action="/job-action" id="jobForm">
            <input type="hidden" name="action"     id="jobAction" value="add"/>
            <input type="hidden" name="id"         id="jobId"     value=""/>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>"/>

            <div class="form-row">
                <div class="fg">
                    <label>Job Title *</label>
                    <input type="text" name="title" id="jTitle" required placeholder="e.g. Full Stack Developer"/>
                </div>
                <div class="fg">
                    <label>Category *</label>
                    <select name="category" id="jCategory" required>
                        <option value="">Select a category</option>
                        <option value="Technology">Technology</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Business">Business</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="fg">
                    <label>Location *</label>
                    <input type="text" name="location" id="jLocation" required placeholder="e.g. Lucknow, WFO"/>
                </div>
                <div class="fg">
                    <label>Job Type *</label>
                    <input type="text" name="type" id="jType" required placeholder="e.g. Full-time"/>
                </div>
            </div>
            <div class="form-row">
                <div class="fg">
                    <label>Experience *</label>
                    <input type="text" name="experience" id="jExperience" required placeholder="e.g. 2+ Years"/>
                </div>
                <div class="fg">
                    <label>Openings</label>
                    <input type="number" name="openings" id="jOpenings" min="1" placeholder="e.g. 2"/>
                </div>
            </div>
            <div class="fg">
                <label>Description *</label>
                <textarea name="description" id="jDescription" required rows="3" placeholder="Brief description of the role…"></textarea>
            </div>
            <div class="fg">
                <label>Tags</label>
                <input type="text" name="tags" id="jTags" placeholder="React, Node.js, AWS"/>
                <div class="fg-hint">Comma-separated</div>
            </div>
            <div class="fg">
                <label>Responsibilities</label>
                <textarea name="responsibilities" id="jResponsibilities" rows="4" placeholder="One responsibility per line…"></textarea>
                <div class="fg-hint">One item per line</div>
            </div>
            <div class="fg">
                <label>Requirements</label>
                <textarea name="requirements" id="jRequirements" rows="4" placeholder="One requirement per line…"></textarea>
                <div class="fg-hint">One item per line</div>
            </div>
            <div class="fg">
                <label class="cb-row">
                    <input type="checkbox" name="is_active" id="jActive" checked/>
                    Active (visible on careers page)
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeJobModal()">Cancel</button>
                <button type="submit" class="btn-save" id="modalSaveBtn">Save Job Post</button>
            </div>
        </form>
    </div>
</div>

<script>
var csrfToken = '<?= $csrf ?>';
// ── TAB SWITCHING
function switchTab(name) {
    document.getElementById('tab-applications').style.display = name === 'applications' ? 'block' : 'none';
    document.getElementById('tab-jobs').style.display         = name === 'jobs'         ? 'block' : 'none';
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    event.currentTarget.classList.add('active');
    history.replaceState(null, '', '?tab=' + name);
}

// ── LIVE STATS RECALC
function recalcStats() {
    var rows     = Array.from(document.querySelectorAll('#appBody tr[data-name]'));
    var total    = rows.length;
    var pending  = rows.filter(function(r){ return r.dataset.status === 'pending';  }).length;
    var selected = rows.filter(function(r){ return r.dataset.status === 'selected'; }).length;
    var rejected = rows.filter(function(r){ return r.dataset.status === 'rejected'; }).length;
    document.getElementById('statTotal').textContent    = total;
    document.getElementById('statPending').textContent  = pending;
    document.getElementById('statSelected').textContent = selected;
    document.getElementById('statRejected').textContent = rejected;
    document.getElementById('tabAppCount').textContent  = total;
}

// ── APPLICATION SEARCH + FILTER + SORT
function filterApps() {
    var q      = document.getElementById('appSearch').value.toLowerCase().trim();
    var st     = document.getElementById('appStatus').value;
    var sort   = document.getElementById('appSort').value;
    var body   = document.getElementById('appBody');
    var rows   = Array.from(body.querySelectorAll('tr[data-name]'));

    var visible = rows.filter(function(r) {
        var matchQ  = !q  || r.dataset.name.includes(q) || r.dataset.email.includes(q) || r.dataset.role.includes(q);
        var matchSt = !st || r.dataset.status === st;
        return matchQ && matchSt;
    });
    var hidden = rows.filter(function(r) { return !visible.includes(r); });

    visible.sort(function(a, b) {
        if (sort === 'date_desc') return new Date(b.dataset.date) - new Date(a.dataset.date);
        if (sort === 'date_asc')  return new Date(a.dataset.date) - new Date(b.dataset.date);
        if (sort === 'name_asc')  return a.dataset.name.localeCompare(b.dataset.name);
        if (sort === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
        return 0;
    });

    visible.forEach(function(r) { r.style.display = ''; body.appendChild(r); });
    hidden.forEach(function(r)  { r.style.display = 'none'; });

    document.getElementById('appCount').textContent = visible.length + ' application' + (visible.length !== 1 ? 's' : '');
}

// ── STATUS UPDATE (AJAX)
function updateStatus(id, sel) {
    var newStatus = sel.value;
    sel.className = 'status-sel ' + newStatus;
    sel.closest('tr').dataset.status = newStatus;

    fetch('/update-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) { recalcStats(); }
        else { alert('Failed to update status.'); location.reload(); }
    })
    .catch(function() { alert('Network error. Please try again.'); });
}

// ── DELETE APPLICATION (AJAX)
function deleteApp(id, btn) {
    if (!confirm('Delete this application? This cannot be undone.')) return;
    btn.disabled = true;
    btn.textContent = '…';

    fetch('/delete-application', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            var row = btn.closest('tr');
            row.style.transition = 'opacity .3s';
            row.style.opacity = '0';
            setTimeout(function() { row.remove(); filterApps(); recalcStats(); }, 300);
        } else {
            alert(d.message || 'Delete failed.');
            btn.disabled = false;
            btn.textContent = 'Delete';
        }
    })
    .catch(function() {
        alert('Network error.');
        btn.disabled = false;
        btn.textContent = 'Delete';
    });
}

// ── JOB MODAL
function openJobModal() {
    document.getElementById('modalTitle').textContent = 'Add Job Post';
    document.getElementById('modalSaveBtn').textContent = 'Save Job Post';
    document.getElementById('jobAction').value = 'add';
    document.getElementById('jobId').value     = '';
    document.getElementById('jobForm').reset();
    document.getElementById('jActive').checked = true;
    document.getElementById('jobModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function editJob(job) {
    document.getElementById('modalTitle').textContent = 'Edit Job Post';
    document.getElementById('modalSaveBtn').textContent = 'Update Job Post';
    document.getElementById('jobAction').value = 'edit';
    document.getElementById('jobId').value     = job.id;

    document.getElementById('jTitle').value       = job.title       || '';
    document.getElementById('jCategory').value    = job.category    || '';
    document.getElementById('jLocation').value    = job.location    || '';
    document.getElementById('jType').value        = job.type        || '';
    document.getElementById('jExperience').value  = job.experience  || '';
    document.getElementById('jOpenings').value    = job.openings    || '';
    document.getElementById('jDescription').value = job.description || '';
    document.getElementById('jActive').checked    = job.is_active == 1;

    try { document.getElementById('jTags').value = (JSON.parse(job.tags || '[]')).join(', '); } catch(e) { document.getElementById('jTags').value = ''; }
    try { document.getElementById('jResponsibilities').value = (JSON.parse(job.responsibilities || '[]')).join('\n'); } catch(e) { document.getElementById('jResponsibilities').value = ''; }
    try { document.getElementById('jRequirements').value     = (JSON.parse(job.requirements     || '[]')).join('\n'); } catch(e) { document.getElementById('jRequirements').value = ''; }

    document.getElementById('jobModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeJobModal() {
    document.getElementById('jobModal').classList.remove('open');
    document.body.style.overflow = '';
}

function closeModalOnOverlay(e) {
    if (e.target === document.getElementById('jobModal')) closeJobModal();
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeJobModal(); });

// ── HELPERS
function esc(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

function showToast(msg) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>' + esc(msg);
    document.body.appendChild(t);
    setTimeout(function() { t.classList.add('show'); }, 10);
    setTimeout(function() { t.classList.remove('show'); setTimeout(function() { t.remove(); }, 300); }, 5000);
}

function renderAppRow(app) {
    var tr = document.createElement('tr');
    tr.className = 'new-row';
    tr.dataset.name   = (app.name  || '').toLowerCase();
    tr.dataset.email  = (app.email || '').toLowerCase();
    tr.dataset.role   = (app.role  || '').toLowerCase();
    tr.dataset.status = app.status || 'pending';
    tr.dataset.date   = app.submitted_at || '';

    var linkedinHtml  = app.linkedin  ? '<a href="'+esc(app.linkedin)+'"  target="_blank" class="link-a">LinkedIn ↗</a>'  : '<span class="no-link">—</span>';
    var portfolioHtml = app.portfolio ? '<a href="'+esc(app.portfolio)+'" target="_blank" class="link-a">Portfolio ↗</a>' : '<span class="no-link">—</span>';
    var filename = (app.resume_path || '').split('/').pop();
    var d = new Date(app.submitted_at);
    var dateStr = isNaN(d) ? app.submitted_at : d.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
    var st = app.status || 'pending';

    tr.innerHTML =
        '<td class="id-col">'+app.id+'</td>'+
        '<td><strong>'+esc(app.name)+'</strong></td>'+
        '<td style="font-size:12px">'+esc(app.email)+'</td>'+
        '<td>'+esc(app.phone)+'</td>'+
        '<td><span class="role-badge">'+esc(app.role)+'</span></td>'+
        '<td>'+linkedinHtml+'</td>'+
        '<td>'+portfolioHtml+'</td>'+
        '<td><div class="resume-btns">'+
            '<a href="/resume/view?file='+encodeURIComponent(filename)+'" target="_blank" class="btn-view">View</a>'+
            '<a href="/resume/download?file='+encodeURIComponent(filename)+'" class="btn-dl">Download</a>'+
        '</div></td>'+
        '<td>'+
            '<select class="status-sel '+st+'" onchange="updateStatus('+app.id+', this)">'+
                '<option value="pending"'  +(st==='pending'  ?' selected':'')+'>Pending</option>'+
                '<option value="hold"'     +(st==='hold'     ?' selected':'')+'>On Hold</option>'+
                '<option value="selected"' +(st==='selected' ?' selected':'')+'>Selected</option>'+
                '<option value="rejected"' +(st==='rejected' ?' selected':'')+'>Rejected</option>'+
            '</select>'+
        '</td>'+
        '<td class="date-cell">'+dateStr+'</td>'+
        '<td><button class="btn-del" onclick="deleteApp('+app.id+', this)">Delete</button></td>';
    return tr;
}

// ── LIVE POLLING FOR NEW APPLICATIONS
var latestTimestamp = (function() {
    var rows = Array.from(document.querySelectorAll('#appBody tr[data-name]'));
    if (rows.length) {
        return rows.reduce(function(max, r) { return r.dataset.date > max ? r.dataset.date : max; }, '');
    }
    // No apps yet — use current time so we only catch truly new ones
    var now = new Date();
    return now.getFullYear() + '-' +
           String(now.getMonth()+1).padStart(2,'0') + '-' +
           String(now.getDate()).padStart(2,'0') + ' ' +
           String(now.getHours()).padStart(2,'0') + ':' +
           String(now.getMinutes()).padStart(2,'0') + ':' +
           String(now.getSeconds()).padStart(2,'0');
})();

function pollNewApplications() {
    fetch('/fetch-applications?since=' + encodeURIComponent(latestTimestamp))
    .then(function(r) { return r.json(); })
    .then(function(newApps) {
        if (!newApps.length) return;

        var body = document.getElementById('appBody');

        // Remove empty-state row if present
        var emptyRow = body.querySelector('td[colspan]');
        if (emptyRow) emptyRow.closest('tr').remove();

        // Prepend each new row and track latest timestamp
        newApps.forEach(function(app) {
            var tr = renderAppRow(app);
            body.insertBefore(tr, body.firstChild);
            if (app.submitted_at > latestTimestamp) latestTimestamp = app.submitted_at;
        });

        filterApps();
        recalcStats();
        showToast(newApps.length + ' new application' + (newApps.length > 1 ? 's' : '') + ' received!');
    })
    .catch(function() { /* silent — don't interrupt the user */ });
}

// Poll every 30 seconds; pause when tab is hidden
var pollTimer = setInterval(pollNewApplications, 30000);
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(pollTimer);
    } else {
        pollNewApplications();
        pollTimer = setInterval(pollNewApplications, 30000);
    }
});

// Auto-dismiss flash after 5s
(function() {
    var f = document.getElementById('flashBar');
    if (f) setTimeout(function() { f.style.transition = 'opacity .5s'; f.style.opacity = '0'; setTimeout(function() { f.remove(); }, 500); }, 5000);
})();
</script>
</body>
</html>
