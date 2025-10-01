<?php
// Ambil pengaturan aplikasi dari database untuk digunakan di seluruh UI
$app_settings = [];
$settings_conn = Database::getInstance()->getConnection();
$settings_result = $settings_conn->query("SELECT setting_key, setting_value FROM settings");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $app_settings[$row['setting_key']] = $row['setting_value'];
    }
}
$app_name = htmlspecialchars($app_settings['app_name'] ?? 'Aplikasi RT');
$notification_interval = (int)($app_settings['notification_interval'] ?? 15000);
$log_cleanup_days = (int)($app_settings['log_cleanup_interval_days'] ?? 180);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $app_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <script>
        const userRole = '<?= $_SESSION['role'] ?? 'warga' ?>';
        const username = '<?= $_SESSION['username'] ?? '' ?>';
        const basePath = '<?= BASE_PATH ?>';
        const notificationInterval = <?= $notification_interval ?>;
        const logCleanupDays = <?= $log_cleanup_days ?>;
    </script>
</head>
<body class="">
<div id="spa-loading-bar"></div>
<script>
    // Apply theme and sidebar state from localStorage
    (function() {
        const theme = localStorage.getItem('theme') || 'light';
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
        }

        const isSmallScreen = window.innerWidth <= 992;
        const storedState = localStorage.getItem('sidebar-collapsed');
        if (storedState === 'true' || (storedState === null && isSmallScreen)) {
            document.body.classList.add('sidebar-collapsed');
        }
    })();
</script>
<div class="sidebar">
    <a class="navbar-brand" href="<?= base_url('/dashboard') ?>"><i class="bi bi-house-door-fill"></i> <?= $app_name ?></a>
    <ul class="sidebar-nav">
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>

        <!-- Entri Data -->
        <li class="sidebar-header">Entri Data</li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/transaksi') ?>"><i class="bi bi-arrow-down-up"></i> Transaksi</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/entri-jurnal') ?>"><i class="bi bi-journal-plus"></i> Entri Jurnal</a>
        </li>

        <!-- Laporan & Analisis -->
        <li class="sidebar-header">Laporan & Analisis</li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/laporan') ?>"><i class="bi bi-bar-chart-line-fill"></i> Laporan Keuangan</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/laporan-harian') ?>"><i class="bi bi-calendar-day"></i> Laporan Harian</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/buku-besar') ?>"><i class="bi bi-book"></i> Buku Besar</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/daftar-jurnal') ?>"><i class="bi bi-list-ol"></i> Daftar Jurnal</a>
        </li>

        <!-- Pengaturan -->
        <li class="sidebar-header">Pengaturan</li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/coa') ?>"><i class="bi bi-journal-bookmark-fill"></i> Bagan Akun (COA)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/saldo-awal-neraca') ?>"><i class="bi bi-journal-check"></i> Saldo Awal Neraca</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/saldo-awal-lr') ?>"><i class="bi bi-graph-up-arrow"></i> Saldo Awal L/R</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('/settings') ?>"><i class="bi bi-gear-fill"></i> Pengaturan</a>
        </li>
    </ul>
</div>
<div class="sidebar-overlay"></div>

<div class="content-wrapper">
    <nav class="top-navbar d-flex justify-content-between align-items-center">
        <!-- Left side: Sidebar Toggle -->
        <div class="d-flex align-items-center">
            <button class="btn" id="sidebar-toggle-btn" title="Toggle sidebar">
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>

        <!-- Right side: Clock, Search, Notifications, Profile -->
        <div class="d-flex align-items-center">
            <div id="live-clock" class="text-muted small me-3 fw-bold d-none d-md-block">
                <!-- Clock will be inserted here by JavaScript -->
            </div>
            <!-- Global Search Button -->
            <button class="btn nav-link me-2" id="global-search-btn" data-bs-toggle="modal" data-bs-target="#globalSearchModal" title="Pencarian Global">
                <i class="bi bi-search fs-5"></i>
            </button>

            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item d-flex align-items-center" href="#" id="theme-switcher"><i class="bi bi-moon-stars-fill me-2"></i><span id="theme-switcher-text">Mode Gelap</span></a></li>
                    <li><a class="dropdown-item" href="<?= base_url('/my-profile/change-password') ?>"><i class="bi bi-key-fill me-2"></i>Ganti Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= base_url('/logout') ?>" data-spa-ignore><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">

<!-- Global Search Modal -->
<div class="modal fade" id="globalSearchModal" tabindex="-1" aria-labelledby="globalSearchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="globalSearchModalLabel"><i class="bi bi-search"></i> Pencarian Global</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
            <input type="text" class="form-control form-control-lg" id="global-search-input" placeholder="Ketik untuk mencari warga, kegiatan, dokumen..." autocomplete="off">
            <span class="input-group-text" id="global-search-spinner" style="display: none;"><div class="spinner-border spinner-border-sm"></div></span>
        </div>
        <div id="global-search-results">
            <p class="text-muted text-center">Masukkan kata kunci untuk memulai pencarian.</p>
        </div>
      </div>
    </div>
  </div>
</div>