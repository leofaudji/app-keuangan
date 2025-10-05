<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('Token tidak valid.');
}

$conn = Database::getInstance()->getConnection();
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die('Token tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan reset password kembali.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= get_setting('app_name', 'Aplikasi Keuangan') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body>
    <div class="container-fluid">
        <div class="row vh-100 justify-content-center align-items-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <img src="<?= base_url(get_setting('app_logo', 'assets/img/logo.png')) ?>" alt="Logo" height="50">
                            <h3 class="mt-3">Buat Password Baru</h3>
                        </div>
                        <?php if (isset($_SESSION['reset_error'])): ?>
                            <div class="alert alert-danger" role="alert"><?= $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
                        <?php endif; ?>
                        <form action="<?= base_url('/reset-password') ?>" method="POST">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Password Baru</label>
                                <input class="form-control" type="password" name="new_password" id="new_password" required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                <input class="form-control" type="password" name="confirm_password" id="confirm_password" required>
                            </div>
                            <div class="mb-3"><button class="btn btn-primary d-block w-100" type="submit">Reset Password</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>