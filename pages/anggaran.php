<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-bullseye"></i> Anggaran vs. Realisasi</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" id="export-anggaran-pdf"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Cetak PDF</a></li>
                <li><a class="dropdown-item" href="#" id="export-anggaran-csv"><i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i>Export CSV</a></li>
            </ul>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#anggaranModal" id="manage-anggaran-btn">
            <i class="bi bi-pencil-square"></i> Kelola Anggaran
        </button>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="anggaran-bulan-filter" class="form-label">Bulan</label>
                <select id="anggaran-bulan-filter" class="form-select"></select>
            </div>
            <div class="col-md-4">
                <label for="anggaran-tahun-filter" class="form-label">Tahun</label>
                <select id="anggaran-tahun-filter" class="form-select"></select>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <div class="form-check form-switch pt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="anggaran-compare-switch">
                    <label class="form-check-label" for="anggaran-compare-switch">Bandingkan</label>
                </div>
            </div>
            <div class="col-md-2 d-flex">
                <button class="btn btn-primary w-100" id="anggaran-tampilkan-btn"><i class="bi bi-search"></i> Tampilkan</button>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">Total Anggaran</h6>
                <h4 class="card-title fw-bold" id="summary-total-anggaran"><div class="spinner-border spinner-border-sm"></div></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">Total Realisasi</h6>
                <h4 class="card-title fw-bold" id="summary-total-realisasi"><div class="spinner-border spinner-border-sm"></div></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">Sisa Anggaran</h6>
                <h4 class="card-title fw-bold" id="summary-sisa-anggaran"><div class="spinner-border spinner-border-sm"></div></h4>
            </div>
        </div>
    </div>
</div>

<!-- Trend Chart -->
<div class="card mb-3">
    <div class="card-header">
        Grafik Tren Anggaran vs. Realisasi Bulanan
    </div>
    <div class="card-body">
        <canvas id="anggaran-trend-chart"></canvas>
    </div>
</div>

<!-- Chart -->
<div class="card mb-3">
    <div class="card-header">
        Grafik Perbandingan Anggaran vs. Realisasi
    </div>
    <div class="card-body">
        <canvas id="anggaran-chart"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr id="anggaran-report-table-header"></tr>
                </thead>
                <tbody id="anggaran-report-table-body"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal untuk Manajemen Anggaran -->
<div class="modal fade" id="anggaranModal" tabindex="-1" aria-labelledby="anggaranModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="anggaranModalLabel">Kelola Anggaran Tahunan (<span id="modal-tahun-label"></span>)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small">Masukkan total anggaran untuk <strong>satu tahun</strong>. Sistem akan membaginya secara otomatis menjadi anggaran bulanan.</div>
        <form id="anggaran-management-form">
            <div id="anggaran-management-container">
                <!-- Konten dimuat oleh JS -->
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="save-anggaran-btn">Simpan Anggaran</button>
      </div>
    </div>
  </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>