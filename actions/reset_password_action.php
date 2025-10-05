<?php
session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('/login'));
    exit;
}

$token = $_POST['token'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($token) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['reset_error'] = 'Semua field wajib diisi.';
    header('Location: ' . base_url('/reset-password?token=' . $token));
    exit;
}

if (strlen($new_password) < 6) {
    $_SESSION['reset_error'] = 'Password minimal harus 6 karakter.';
    header('Location: ' . base_url('/reset-password?token=' . $token));
    exit;
}

if ($new_password !== $confirm_password) {
    $_SESSION['reset_error'] = 'Password dan konfirmasi tidak cocok.';
    header('Location: ' . base_url('/reset-password?token=' . $token));
    exit;
}

$conn = Database::getInstance()->getConnection();
$stmt = $conn->prepare("SELECT id, username FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['login_error'] = 'Token reset tidak valid atau sudah kedaluwarsa.';
    header('Location: ' . base_url('/login'));
    exit;
}

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
$stmt_update->bind_param("si", $hashed_password, $user['id']);
$stmt_update->execute();
$stmt_update->close();

log_activity($user['username'], 'Reset Password', 'Password berhasil direset melalui email.');
$_SESSION['login_success'] = 'Password Anda telah berhasil direset. Silakan login dengan password baru.';
header('Location: ' . base_url('/login'));
exit;