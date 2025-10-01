<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'bendahara'])) {
    echo '<div class="alert alert-danger m-3">Akses ditolak. Anda harus menjadi Admin atau Bendahara untuk melihat halaman ini.</div>';
    if (!$is_spa_request) {
        require_once PROJECT_ROOT . '/views/footer.php';
    }
    return; // Stop rendering
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-clipboard-data-fill"></i> Anggaran & Realisasi Belanja</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?= base_url('/manajemen') ?>" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Kembali ke Manajemen
        </a>
        <div class="btn-group me-2">
            <select id="anggaran-tahun-filter" class="form-select form-select-sm" style="width: auto;">
                <!-- Options will be populated by JS -->
            </select>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#anggaranModal">
            <i class="bi bi-pencil-square"></i> Kelola Anggaran
        </button>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Kategori</th>
                <th class="text-end">Jumlah Anggaran</th>
                <th class="text-end">Realisasi Belanja</th>
                <th class="text-end">Sisa Anggaran</th>
                <th>Persentase Realisasi</th>
            </tr>
        </thead>
        <tbody id="anggaran-report-table-body">
            <!-- Data akan dimuat di sini oleh JavaScript -->
            <tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>
        </tbody>
    </table>
</div>

<!-- Modal untuk Kelola Anggaran -->
<div class="modal fade" id="anggaranModal" tabindex="-1" aria-labelledby="anggaranModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="anggaranModalLabel">Kelola Anggaran Tahun <span id="modal-tahun-label"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Kelola anggaran untuk setiap kategori pengeluaran. Perubahan akan disimpan secara otomatis.</p>
        <div id="anggaran-management-container">
            <!-- Form fields will be loaded here -->
            <div class="text-center p-5"><div class="spinner-border"></div></div>
        </div>
        <hr>
        <form id="add-anggaran-form" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="new-kategori" class="form-label">Pilih Kategori</label>
                <select class="form-select" id="new-kategori" required></select>
            </div>
            <div class="col-md-5">
                <label for="new-jumlah" class="form-label">Jumlah Anggaran</label>
                <input type="number" class="form-control" id="new-jumlah" placeholder="cth: 500000">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">Tambah</button>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>