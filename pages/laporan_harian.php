<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-calendar-day"></i> Laporan Transaksi Harian</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" id="export-lh-pdf"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Cetak PDF</a></li>
                <li><a class="dropdown-item" href="#" id="export-lh-csv"><i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i>Export CSV</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="lh-tanggal" class="form-label">Pilih Tanggal</label>
                <input type="date" id="lh-tanggal" class="form-control">
            </div>
            <div class="col-md-6">
                <button class="btn btn-primary w-100" id="lh-tampilkan-btn">
                    <i class="bi bi-search"></i> Tampilkan Laporan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chart and Summary Row -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">
                Ringkasan Transaksi
            </div>
            <div class="card-body" id="lh-summary-content">
                 <div class="alert alert-info text-center m-0">
                    Pilih tanggal untuk melihat ringkasan.
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-center align-items-center p-2">
                <canvas id="lh-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" id="lh-report-header">
        Detail Transaksi Harian
    </div>
    <div class="card-body" id="lh-report-content">
        <div class="alert alert-info text-center">
            Silakan pilih tanggal, lalu klik "Tampilkan Laporan".
        </div>
    </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>