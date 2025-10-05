<?php
session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('/login'));
    exit;
}

$email = $_POST['email'] ?? '';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['reset_error'] = 'Format email tidak valid.';
    header('Location: ' . base_url('/forgot'));
    exit;
}

$conn = Database::getInstance()->getConnection();

// Cari user berdasarkan email. Asumsikan email ada di tabel users.
// Jika belum ada, Anda perlu menambahkan kolom email ke tabel users.
// ALTER TABLE users ADD email VARCHAR(255) NULL UNIQUE;
$stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['reset_error'] = 'Email tidak terdaftar di sistem.';
    header('Location: ' . base_url('/forgot'));
    exit;
}

try {
    $token = bin2hex(random_bytes(32));
    $expires = new DateTime('now + 1 hour');
    $expires_str = $expires->format('Y-m-d H:i:s');

    $stmt_update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $token, $expires_str, $user['id']);
    $stmt_update->execute();
    $stmt_update->close();

    $reset_link = base_url('/reset-password?token=' . $token);

    // Kirim email menggunakan PHPMailer
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = Config::get('SMTP_HOST');
    $mail->SMTPAuth   = true;
    $mail->Username   = Config::get('SMTP_USERNAME');
    $mail->Password   = Config::get('SMTP_PASSWORD');
    $mail->SMTPSecure = Config::get('SMTP_SECURE');
    $mail->Port       = Config::get('SMTP_PORT');
    $mail->setFrom(Config::get('SMTP_FROM_EMAIL'), Config::get('SMTP_FROM_NAME'));
    $mail->addAddress($email, $user['username']);
    $mail->isHTML(true);
    $mail->Subject = 'Reset Password Aplikasi Keuangan';
    $mail->Body    = "Halo {$user['username']},<br><br>Anda menerima email ini karena ada permintaan untuk mereset password akun Anda.<br>Klik link di bawah ini untuk mereset password:<br><a href='{$reset_link}'>{$reset_link}</a><br><br>Jika Anda tidak merasa melakukan permintaan ini, abaikan saja email ini.<br>Link ini akan kedaluwarsa dalam 1 jam.<br><br>Terima kasih.";

    $mail->send();

    $_SESSION['reset_success'] = 'Link reset password telah dikirim ke email Anda.';
    header('Location: ' . base_url('/forgot'));
    exit;

} catch (Exception $e) {
    $_SESSION['reset_error'] = 'Gagal mengirim email. Error: ' . $mail->ErrorInfo;
    header('Location: ' . base_url('/forgot'));
    exit;
}