<?php
session_start();
session_unset();
session_destroy();
// Expire the session cookie in the browser
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
header('Location: /hr/login');
exit;
