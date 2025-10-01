<?php
// Cek apakah ini permintaan dari SPA via AJAX
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';

// Hanya muat header jika ini bukan permintaan SPA
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-speedometer2"></i> Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="d-flex align-items-center">
            <div class="me-2">
                <label for="dashboard-bulan-filter" class="form-label visually-hidden">Bulan</label>
                <select id="dashboard-bulan-filter" class="form-select form-select-sm">
                    <!-- Options will be populated by JS -->
                </select>
            </div>
            <div class="me-2">
                <label for="dashboard-tahun-filter" class="form-label visually-hidden">Tahun</label>
                <select id="dashboard-tahun-filter" class="form-select form-select-sm">
                    <!-- Options will be populated by JS -->
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Aksi Cepat -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="<?= base_url('/transaksi') ?>" class="card management-card text-decoration-none text-center h-100" id="dashboard-add-transaksi">
            <div class="card-body">
                <div class="icon-wrapper bg-success-subtle text-success"><i class="bi bi-plus-circle-fill"></i></div>
                <h6 class="card-title">Tambah Transaksi</h6>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= base_url('/entri-jurnal') ?>" class="card management-card text-decoration-none text-center h-100">
            <div class="card-body">
                <div class="icon-wrapper bg-warning-subtle text-warning"><i class="bi bi-journal-plus"></i></div>
                <h6 class="card-title">Buat Jurnal Umum</h6>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= base_url('/laporan') ?>" class="card management-card text-decoration-none text-center h-100">
            <div class="card-body">
                <div class="icon-wrapper bg-info-subtle text-info"><i class="bi bi-bar-chart-line-fill"></i></div>
                <h6 class="card-title">Lihat Laporan</h6>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= base_url('/coa') ?>" class="card management-card text-decoration-none text-center h-100">
            <div class="card-body">
                <div class="icon-wrapper bg-secondary-subtle text-secondary"><i class="bi bi-journal-bookmark-fill"></i></div>
                <h6 class="card-title">Bagan Akun (COA)</h6>
            </div>
        </a>
    </div>
</div>

<!-- Konten dashboard keuangan akan dirender di sini oleh JavaScript -->

<?php
// Hanya muat footer jika ini bukan permintaan SPA
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>