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

<!-- Konten dashboard keuangan akan dirender di sini oleh JavaScript -->

<?php
// Hanya muat footer jika ini bukan permintaan SPA
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>