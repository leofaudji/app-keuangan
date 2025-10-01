<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-book"></i> Buku Besar (General Ledger)</h1>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="bb-akun-filter" class="form-label">Pilih Akun</label>
                <select id="bb-akun-filter" class="form-select">
                    <option value="">Memuat akun...</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="bb-tanggal-mulai" class="form-label">Dari Tanggal</label>
                <input type="date" id="bb-tanggal-mulai" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="bb-tanggal-akhir" class="form-label">Sampai Tanggal</label>
                <input type="date" id="bb-tanggal-akhir" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" id="bb-tampilkan-btn">
                    <i class="bi bi-search"></i> Tampilkan
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" id="bb-report-header">
        Laporan Buku Besar
    </div>
    <div class="card-body">
        <div class="table-responsive" id="bb-report-content">
            <div class="alert alert-info text-center">
                Silakan pilih akun dan rentang tanggal, lalu klik "Tampilkan".
            </div>
        </div>
    </div>
</div>


<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>