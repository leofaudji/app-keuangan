<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-list-ol"></i> Daftar Entri Jurnal</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?= base_url('/entri-jurnal') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle-fill"></i> Buat Entri Jurnal Baru
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" id="search-jurnal" class="form-control" placeholder="Cari keterangan...">
            </div>
            <div class="col-md-3">
                <input type="date" id="filter-jurnal-mulai" class="form-control">
            </div>
            <div class="col-md-3">
                <input type="date" id="filter-jurnal-akhir" class="form-control">
            </div>
            <div class="col-md-2">
                <select id="filter-jurnal-limit" class="form-select">
                    <option value="10">10</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="-1">Semua</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID Jurnal</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="daftar-jurnal-table-body">
                    <!-- Data akan dimuat oleh JavaScript -->
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination justify-content-center" id="daftar-jurnal-pagination">
                <!-- Pagination akan dimuat oleh JS -->
            </ul>
        </nav>
    </div>
</div>

<!-- Modal untuk Detail Jurnal -->
<div class="modal fade" id="viewJurnalModal" tabindex="-1" aria-labelledby="viewJurnalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewJurnalModalLabel"><i class="bi bi-journal-text"></i> Detail Entri Jurnal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="view-jurnal-body">
        <!-- Konten detail jurnal akan dimuat di sini -->
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