<?php
require_once '../hr_auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Redirect already-authenticated users
if (!empty($_SESSION['hr_logged_in'])) {
    header('Location: /hr/dashboard');
    exit;
}

// Change these credentials before deploying to cPanel
define('HR_USERNAME', 'hr_admin');
define('HR_PASSWORD', '$2y$10$kIz4hb8ubJ/CLW7qUUdRAuDlMfNpT5Qbx9LOHzL6K7K2cvcxQ6XL6');

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECS', 900); // 15 minutes

// ── IP-based rate-limit helpers (file-backed) ────────────────────────
function rl_file(string $ip): string {
    return sys_get_temp_dir() . '/se_rl_' . md5('hr_' . $ip) . '.json';
}
function rl_get(string $ip): array {
    $f = rl_file($ip);
    $d = file_exists($f) ? json_decode(file_get_contents($f), true) : null;
    return is_array($d) ? $d : ['count' => 0, 'since' => time(), 'locked_until' => 0];
}
function rl_save(string $ip, array $d): void {
    file_put_contents(rl_file($ip), json_encode($d), LOCK_EX);
}
function rl_reset(string $ip): void {
    $f = rl_file($ip);
    if (file_exists($f)) @unlink($f);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rl = rl_get($ip);

// Reset the window if the lockout period has fully elapsed
if ($rl['locked_until'] <= time() && (time() - ($rl['since'] ?? 0)) > LOGIN_LOCKOUT_SECS) {
    $rl = ['count' => 0, 'since' => time(), 'locked_until' => 0];
    rl_save($ip, $rl);
}

$locked         = $rl['locked_until'] > time();
$lock_remaining = $locked ? (int)ceil(($rl['locked_until'] - time()) / 60) : 0;
$error          = '';
$info           = '';

if (!empty($_GET['timeout'])) {
    $info = 'Your session has expired. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
        $error = 'Security token mismatch. Please try again.';
    } elseif ($locked) {
        $error = "Too many failed attempts. Please try again in {$lock_remaining} minute(s).";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === HR_USERNAME && password_verify($password, HR_PASSWORD)) {
            session_regenerate_id(true);
            $_SESSION['hr_logged_in']   = true;
            $_SESSION['hr_user']        = $username;
            $_SESSION['hr_last_active'] = time();
            rl_reset($ip);
            header('Location: /hr/dashboard');
            exit;
        }

        // Failed attempt
        $rl['count']++;
        if ($rl['count'] >= MAX_LOGIN_ATTEMPTS) {
            $rl['locked_until'] = time() + LOGIN_LOCKOUT_SECS;
            $error = 'Too many failed attempts. Account locked for ' . ceil(LOGIN_LOCKOUT_SECS / 60) . ' minutes.';
        } else {
            $left  = MAX_LOGIN_ATTEMPTS - $rl['count'];
            $error = 'Invalid username or password. ' . $left . ' attempt(s) remaining.';
        }
        rl_save($ip, $rl);
    }
}

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>HR Login — SecureEdge</title>
    <link rel="icon" type="image/png" href="./assets/images/se-logo-favicon.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0e0d0d;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo img {
            height: 40px;
            margin-bottom: 12px;
        }

        .login-logo h1 {
            font-size: 22px;
            font-weight: 600;
            color: #fff;
        }

        .login-logo p {
            font-size: 13px;
            color: #888;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #aaa;
            margin-bottom: 8px;
        }

        .pw-wrap {
            position: relative;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 12px 44px 12px 16px;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            color: #e0e0e0;
            outline: none;
            transition: border-color 0.2s;
        }

        .pw-toggle {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            color: #555;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: #aaa; }

        input:focus {
            border-color: #e63946;
        }

        .alert-msg {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .alert-msg.error {
            background: rgba(230, 57, 70, 0.12);
            border: 1px solid rgba(230, 57, 70, 0.4);
            color: #e63946;
        }
        .alert-msg.info {
            background: rgba(96, 165, 250, 0.12);
            border: 1px solid rgba(96, 165, 250, 0.4);
            color: #60a5fa;
        }

        .btn-login {
            width: 100%;
            background: #e63946;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-login:hover  { background: #c1121f; }
        .btn-login:active { transform: scale(0.98); }
        .btn-login:disabled { background: #555; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <img src="./assets/images/se-logo-favicon.png" alt="SecureEdge" onerror="this.style.display='none'"/>
            <h1>SecureEdge HR</h1>
            <p>Career Applications Portal</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-msg error"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!empty($info)): ?>
            <div class="alert-msg info"><?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"/>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus <?= $locked ? 'disabled' : '' ?>/>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" required <?= $locked ? 'disabled' : '' ?>/>
                    <button type="button" class="pw-toggle" onclick="togglePw()" tabindex="-1" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-login" <?= $locked ? 'disabled' : '' ?>>Login</button>
        </form>
    </div>
<script>
function togglePw() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('eyeIcon');
    var show  = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.innerHTML = show
        ? '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
</script>
</body>
</html>
