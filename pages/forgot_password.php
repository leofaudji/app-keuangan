<?php
// File ini tidak memerlukan header/footer karena merupakan halaman mandiri
require_once __DIR__ . '/../includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?= get_setting('app_name', 'Aplikasi Keuangan') ?></title>
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
                            <h3 class="mt-3">Lupa Password</h3>
                            <p class="text-muted">Masukkan email Anda untuk menerima link reset password.</p>
                        </div>
                        <?php if (isset($_SESSION['reset_error'])): ?>
                            <div class="alert alert-danger" role="alert"><?= $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['reset_success'])): ?>
                            <div class="alert alert-success" role="alert"><?= $_SESSION['reset_success']; unset($_SESSION['reset_success']); ?></div>
                        <?php endif; ?>
                        <form action="<?= base_url('/actions/forgot_password_action.php') ?>" method="POST">
                            <div class="mb-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
                            <div class="mb-3"><button class="btn btn-primary d-block w-100" type="submit">Kirim Link Reset</button></div>
                        </form>
                        <p class="text-center"><a href="<?= base_url('/login') ?>">Kembali ke Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>