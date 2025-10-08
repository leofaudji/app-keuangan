<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-building"></i> Manajemen Aset Tetap</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#assetModal" data-action="add"><i class="bi bi-plus-circle"></i> Tambah Aset</button>
        <button class="btn btn-outline-secondary" id="print-asset-report-btn"><i class="bi bi-printer-fill"></i> Cetak Laporan</button>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Posting Penyusutan Periodik</span>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="depreciation-month" class="form-label">Bulan</label>
                        <select id="depreciation-month" class="form-select"></select>
                    </div>
                    <div class="col-md-3">
                        <label for="depreciation-year" class="form-label">Tahun</label>
                        <select id="depreciation-year" class="form-select"></select>
                    </div>
                    <div class="col-md-3">
                        <button id="post-depreciation-btn" class="btn btn-warning w-100"><i class="bi bi-send-fill"></i> Posting Jurnal Penyusutan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        Daftar Aset Tetap
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nama Aset</th>
                        <th>Tgl. Perolehan</th>
                        <th class="text-end">Harga Perolehan</th>
                        <th class="text-end">Akum. Penyusutan</th>
                        <th class="text-end">Nilai Buku</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="assets-table-body">
                    <!-- Data akan dimuat oleh JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Modal Aset -->
<div class="modal fade" id="assetModal" tabindex="-1" aria-labelledby="assetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assetModalLabel">Tambah Aset Tetap</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="asset-form">
            <input type="hidden" name="id" id="asset-id">
            <input type="hidden" name="action" id="asset-action" value="save">

            <div class="mb-3">
                <label for="nama_aset" class="form-label">Nama Aset</label>
                <input type="text" class="form-control" id="nama_aset" name="nama_aset" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="tanggal_akuisisi" class="form-label">Tanggal Perolehan</label>
                    <input type="date" class="form-control" id="tanggal_akuisisi" name="tanggal_akuisisi" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="harga_perolehan" class="form-label">Harga Perolehan</label>
                    <input type="number" class="form-control" id="harga_perolehan" name="harga_perolehan" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="masa_manfaat" class="form-label">Masa Manfaat (Tahun)</label>
                    <input type="number" class="form-control" id="masa_manfaat" name="masa_manfaat" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="metode_penyusutan" class="form-label">Metode Penyusutan</label>
                    <select class="form-select" id="metode_penyusutan" name="metode_penyusutan">
                        <option value="Garis Lurus" selected>Garis Lurus</option>
                    </select>
                </div>
            </div>
            <hr>
            <p class="text-muted">Pemetaan Akun</p>
            <div class="mb-3"><label for="akun_aset_id" class="form-label">Akun Aset</label><select class="form-select" id="akun_aset_id" name="akun_aset_id" required></select></div>
            <div class="mb-3"><label for="akun_akumulasi_penyusutan_id" class="form-label">Akun Akumulasi Penyusutan</label><select class="form-select" id="akun_akumulasi_penyusutan_id" name="akun_akumulasi_penyusutan_id" required></select></div>
            <div class="mb-3"><label for="akun_beban_penyusutan_id" class="form-label">Akun Beban Penyusutan</label><select class="form-select" id="akun_beban_penyusutan_id" name="akun_beban_penyusutan_id" required></select></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="save-asset-btn">Simpan</button>
      </div>
    </div>
  </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>