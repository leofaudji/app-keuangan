<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-graph-up-arrow"></i> Laporan Pertumbuhan Laba</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" id="export-lpl-pdf"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Cetak PDF</a></li>
                <li><a class="dropdown-item" href="#" id="export-lpl-csv"><i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i>Export CSV</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="lpl-tahun-filter" class="form-label">Tahun</label>
                <select id="lpl-tahun-filter" class="form-select"></select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Tampilan</label>
                <div class="btn-group w-100" role="group" id="lpl-view-mode">
                    <input type="radio" class="btn-check" name="view_mode" id="lpl-view-monthly" value="monthly" autocomplete="off">
                    <label class="btn btn-outline-primary" for="lpl-view-monthly">Bulanan</label>
                    <input type="radio" class="btn-check" name="view_mode" id="lpl-view-quarterly" value="quarterly" autocomplete="off" checked>
                    <label class="btn btn-outline-primary" for="lpl-view-quarterly">Triwulanan</label>
                    <input type="radio" class="btn-check" name="view_mode" id="lpl-view-yearly" value="yearly" autocomplete="off">
                    <label class="btn btn-outline-primary" for="lpl-view-yearly">Tahunan</label>
                    <input type="radio" class="btn-check" name="view_mode" id="lpl-view-cumulative" value="cumulative" autocomplete="off">
                    <label class="btn btn-outline-primary" for="lpl-view-cumulative">Kumulatif (YTD)</label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <div class="form-check form-switch pt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="lpl-compare-switch">
                    <label class="form-check-label" for="lpl-compare-switch">Bandingkan</label>
                </div>
            </div>
            <div class="col-md-2 d-flex">
                <button class="btn btn-primary w-100" id="lpl-tampilkan-btn"><i class="bi bi-search"></i> Tampilkan</button>
            </div>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="card mb-3">
    <div class="card-header">
        Grafik Pertumbuhan Laba Bersih Bulanan
    </div>
    <div class="card-body">
        <canvas id="lpl-chart"></canvas>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header">
        Detail Data Pertumbuhan Laba
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr id="lpl-report-table-header"></tr>
                </thead>
                <tbody id="lpl-report-table-body"></tbody>
            </table>
        </div>
    </div>
</div>


<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>
