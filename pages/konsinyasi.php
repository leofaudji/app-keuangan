<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-box-seam"></i> Manajemen Konsinyasi</h1>
</div>

<ul class="nav nav-tabs" id="konsinyasiTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="penjualan-tab" data-bs-toggle="tab" data-bs-target="#penjualan-pane" type="button" role="tab">Penjualan Konsinyasi</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="barang-tab" data-bs-toggle="tab" data-bs-target="#barang-pane" type="button" role="tab">Kelola Barang</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="pemasok-tab" data-bs-toggle="tab" data-bs-target="#pemasok-pane" type="button" role="tab">Kelola Pemasok</button>
  </li>
</ul>

<div class="tab-content" id="konsinyasiTabContent">
    <!-- Tab Penjualan -->
    <div class="tab-pane fade show active" id="penjualan-pane" role="tabpanel">
        <div class="card card-tab">
            <div class="card-header d-flex justify-content-between">
                <span>Form Penjualan Barang Konsinyasi</span>
                <a href="#" id="view-consignment-report-link">Lihat Laporan Penjualan &raquo;</a>
            </div>
            <div class="card-body">
                <form id="consignment-sale-form">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="cs-tanggal" class="form-label">Tanggal Penjualan</label>
                            <input type="date" class="form-control" id="cs-tanggal" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cs-item-id" class="form-label">Barang</label>
                            <select class="form-select" id="cs-item-id" required></select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="cs-qty" class="form-label">Jumlah</label>
                            <input type="number" class="form-control" id="cs-qty" value="1" min="1" required>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cart-plus"></i> Jual</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Kelola Barang -->
    <div class="tab-pane fade" id="barang-pane" role="tabpanel">
        <div class="card card-tab">
            <div class="card-header d-flex justify-content-end">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#itemModal" data-action="add"><i class="bi bi-plus-circle"></i> Tambah Barang</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>Nama Barang</th><th>Pemasok</th><th class="text-end">Harga Jual</th><th class="text-end">Harga Beli</th><th class="text-end">Stok</th><th class="text-end">Aksi</th></tr>
                        </thead>
                        <tbody id="items-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Kelola Pemasok -->
    <div class="tab-pane fade" id="pemasok-pane" role="tabpanel">
        <div class="card card-tab">
            <div class="card-header d-flex justify-content-end">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal" data-action="add"><i class="bi bi-plus-circle"></i> Tambah Pemasok</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Nama Pemasok</th><th>Kontak</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody id="suppliers-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pemasok -->
<div class="modal fade" id="supplierModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="supplierModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="supplier-form">
            <input type="hidden" name="id" id="supplier-id"><input type="hidden" name="action" id="supplier-action">
            <div class="mb-3"><label for="nama_pemasok" class="form-label">Nama Pemasok</label><input type="text" class="form-control" id="nama_pemasok" name="nama_pemasok" required></div>
            <div class="mb-3"><label for="kontak" class="form-label">Kontak (No. HP/Email)</label><input type="text" class="form-control" id="kontak" name="kontak"></div>
        </form>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="save-supplier-btn">Simpan</button></div>
    </div>
  </div>
</div>

<!-- Modal Barang -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="itemModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="item-form">
            <input type="hidden" name="id" id="item-id"><input type="hidden" name="action" id="item-action">
            <div class="mb-3"><label for="supplier_id" class="form-label">Pemasok</label><select class="form-select" id="supplier_id" name="supplier_id" required></select></div>
            <div class="mb-3"><label for="nama_barang" class="form-label">Nama Barang</label><input type="text" class="form-control" id="nama_barang" name="nama_barang" required></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="harga_jual" class="form-label">Harga Jual</label><input type="number" class="form-control" id="harga_jual" name="harga_jual" required></div>
                <div class="col-md-6 mb-3"><label for="harga_beli" class="form-label">Harga Beli (Modal)</label><input type="number" class="form-control" id="harga_beli" name="harga_beli" required></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="stok_awal" class="form-label">Stok Awal Diterima</label><input type="number" class="form-control" id="stok_awal" name="stok_awal" required></div>
                <div class="col-md-6 mb-3"><label for="tanggal_terima" class="form-label">Tanggal Terima</label><input type="date" class="form-control" id="tanggal_terima" name="tanggal_terima" required></div>
            </div>
        </form>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="save-item-btn">Simpan</button></div>
    </div>
  </div>
</div>

<!-- Modal Laporan Penjualan -->
<div class="modal fade" id="consignmentReportModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Laporan Penjualan Konsinyasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="consignment-report-body"></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>