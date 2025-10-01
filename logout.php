<?php
// bootstrap.php sudah di-require oleh index.php, jadi kita tidak perlu me-require-nya lagi.
// session_start() juga sudah dipanggil di index.php.

if (isset($_SESSION['username'])) {
    log_activity($_SESSION['username'], 'Logout', 'User logged out.');
}

// Hapus semua variabel sesi.
$_SESSION = [];

// Hancurkan sesi.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// Arahkan ke halaman login
header('Location: ' . base_url('/login'));
exit;