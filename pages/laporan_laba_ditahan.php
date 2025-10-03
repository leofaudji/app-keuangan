<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-graph-up"></i> Laporan Perubahan Laba Ditahan</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" id="export-re-pdf"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Cetak PDF</a></li>
                <li><a class="dropdown-item" href="#" id="export-re-csv"><i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i>Export CSV</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="re-tanggal-mulai" class="form-label">Dari Tanggal</label>
                <input type="date" id="re-tanggal-mulai" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="re-tanggal-akhir" class="form-label">Sampai Tanggal</label>
                <input type="date" id="re-tanggal-akhir" class="form-control">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" id="re-tampilkan-btn">
                    <i class="bi bi-search"></i> Tampilkan Laporan
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" id="re-report-header">
        Laporan Perubahan Laba Ditahan
    </div>
    <div class="card-body">
        <div class="table-responsive" id="re-report-content">
            <div class="alert alert-info text-center">
                Silakan pilih rentang tanggal, lalu klik "Tampilkan Laporan".
            </div>
        </div>
    </div>
</div>


<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>