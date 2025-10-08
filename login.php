<?php
// File ini tidak memerlukan header/footer karena merupakan halaman mandiri
require_once __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= get_setting('app_name', 'Aplikasi Keuangan') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
    <style>
        <?php
        $login_bg_color = get_setting('login_bg_color', '#075E54');
        $login_btn_color = get_setting('login_btn_color', '#25D366');
        ?>
        .bg-primary {
            background-color: <?= htmlspecialchars($login_bg_color) ?> !important;
        }
        .btn-primary {
            --bs-btn-color: #fff;
            --bs-btn-bg: <?= htmlspecialchars($login_btn_color) ?>;
            --bs-btn-border-color: <?= htmlspecialchars($login_btn_color) ?>;
            /* Logika untuk warna hover/active bisa ditambahkan di sini jika perlu */
        }
        #login-btn:disabled .spinner-border {
            color: <?= htmlspecialchars($login_bg_color) ?> !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row vh-100">
            <div class="col-md-6 col-lg-7 d-none d-md-flex align-items-center justify-content-center bg-primary text-white p-5">
                <div>
                    <h1 class="display-4 fw-bold"><?= get_setting('app_name', 'Aplikasi Keuangan') ?></h1>
                    <p class="lead">Solusi pencatatan keuangan yang mudah dan terintegrasi.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-5 d-flex align-items-center justify-content-center">
                <div class="card shadow-lg border-0" style="width: 24rem;">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <img src="<?= base_url(get_setting('app_logo', 'assets/img/logo.png')) ?>" alt="Logo" height="50">
                            <h3 class="mt-3">Selamat Datang</h3>
                        </div>
                        <?php if (isset($_SESSION['login_error'])): ?>
                            <div class="alert alert-danger" role="alert"><?= $_SESSION['login_error']; unset($_SESSION['login_error']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['login_success'])): ?>
                            <div class="alert alert-success" role="alert"><?= $_SESSION['login_success']; unset($_SESSION['login_success']); ?></div>
                        <?php endif; ?>
                        <form id="login-form" action="<?= base_url('/login') ?>" method="POST">
                            <div class="mb-3"><input class="form-control" type="text" id="username" name="username" placeholder="Username" required autofocus></div>
                            <div class="input-group mb-3">
                                <input class="form-control" type="password" id="password" name="password" placeholder="Password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Tampilkan/Sembunyikan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
                                    <label class="form-check-label" for="remember_me"> Ingat Saya </label>
                                </div>
                                <a href="<?= base_url('/forgot') ?>">Lupa Password?</a>
                            </div>
                            <div class="mb-3"><button id="login-btn" class="btn btn-primary d-block w-100" type="submit">Login</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('login-form').addEventListener('submit', function() {
            const loginBtn = document.getElementById('login-btn');
            if (loginBtn) {
                loginBtn.disabled = true;
                loginBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...`;
            }
        });

        const togglePasswordBtn = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');
        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', function() {
                // toggle the type attribute
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // toggle the eye / eye-slash icon
                const icon = this.querySelector('i');
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            });
        }

        // Apply user-defined theme color from localStorage
        (function() {
            const savedColor = localStorage.getItem('theme_color');
            if (savedColor) {
                const style = document.createElement('style');
                style.innerHTML = `
                    .btn-primary { --bs-btn-bg: ${savedColor}; --bs-btn-border-color: ${savedColor}; }
                `;
                document.head.appendChild(style);
            }
        })();
    </script>
</body>
</html>