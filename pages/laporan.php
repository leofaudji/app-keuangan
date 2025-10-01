<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-bar-chart-line-fill"></i> Laporan Keuangan</h1>
</div>

<ul class="nav nav-tabs" id="laporanTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="neraca-tab" data-bs-toggle="tab" data-bs-target="#neraca-pane" type="button" role="tab">Neraca</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="laba-rugi-tab" data-bs-toggle="tab" data-bs-target="#laba-rugi-pane" type="button" role="tab">Laba Rugi</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="arus-kas-tab" data-bs-toggle="tab" data-bs-target="#arus-kas-pane" type="button" role="tab">Arus Kas</button>
  </li>
</ul>

<div class="tab-content" id="laporanTabContent">
    <!-- Tab Neraca -->
    <div class="tab-pane fade show active" id="neraca-pane" role="tabpanel">
        <div class="card card-tab">
            <div class="card-header d-flex justify-content-between align-items-center" id="neraca-header">
                <div>
                    Laporan Posisi Keuangan (Neraca)
                    <span id="neraca-balance-status-badge" class="ms-2"></span>
                </div>
                <div class="d-flex align-items-center">
                    <label for="neraca-tanggal" class="form-label me-2 mb-0">Per Tanggal:</label>
                    <input type="date" id="neraca-tanggal" class="form-control form-control-sm" style="width: auto;">
                    <div class="btn-group ms-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" id="export-neraca-pdf"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Cetak PDF</a></li>
                            <li><a class="dropdown-item" href="#" id="export-neraca-csv"><i class="bi bi-filetype-csv text-success me-2"></i>Export CSV</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body" id="neraca-content">
                <div class="text-center p-5"><div class="spinner-border"></div></div>
            </div>
        </div>
    </div>

    <!-- Tab Laba Rugi -->
    <div class="tab-pane fade" id="laba-rugi-pane" role="tabpanel">
        <div class="card card-tab">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Laporan Laba Rugi</h5>
                    <div class="d-flex align-items-center">
                        <label for="laba-rugi-tanggal-mulai" class="form-label me-2 mb-0 small">Dari:</label>
                        <input type="date" id="laba-rugi-tanggal-mulai" class="form-control form-control-sm me-2" style="width: auto;">
                        <label for="laba-rugi-tanggal-akhir" class="form-label me-2 mb-0 small">Sampai:</label>
                        <input type="date" id="laba-rugi-tanggal-akhir" class="form-control form-control-sm" style="width: auto;">
                        <div class="btn-group ms-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" id="export-lr-pdf"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Cetak PDF</a></li>
                                <li><a class="dropdown-item" href="#" id="export-lr-csv"><i class="bi bi-filetype-csv text-success me-2"></i>Export CSV</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div id="laba-rugi-content">
                    <div class="text-center p-5"><div class="spinner-border"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Arus Kas -->
    <div class="tab-pane fade" id="arus-kas-pane" role="tabpanel">
        <div class="card card-tab">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Laporan Arus Kas</h5>
                    <div class="d-flex align-items-center">
                        <label for="arus-kas-tanggal-mulai" class="form-label me-2 mb-0 small">Dari:</label>
                        <input type="date" id="arus-kas-tanggal-mulai" class="form-control form-control-sm me-2" style="width: auto;">
                        <label for="arus-kas-tanggal-akhir" class="form-label me-2 mb-0 small">Sampai:</label>
                        <input type="date" id="arus-kas-tanggal-akhir" class="form-control form-control-sm" style="width: auto;">
                        <div class="btn-group ms-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" id="export-ak-pdf"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Cetak PDF</a></li>
                                <li><a class="dropdown-item" href="#" id="export-ak-csv"><i class="bi bi-filetype-csv text-success me-2"></i>Export CSV</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div id="arus-kas-content">
                    <div class="text-center p-5"><div class="spinner-border"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>