<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-arrow-down-up"></i> Transaksi</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transaksiModal" data-action="add" id="add-transaksi-btn">
            <i class="bi bi-plus-circle-fill"></i> Tambah Transaksi
        </button>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" id="search-transaksi" class="form-control" placeholder="Cari keterangan...">
            </div>
            <div class="col-md-3">
                <select id="filter-akun-kas" class="form-select">
                    <option value="">Semua Akun Kas/Bank</option>
                    <!-- Opsi dimuat oleh JS -->
                </select>
            </div>
            <div class="col-md-2">
                <select id="filter-bulan" class="form-select">
                    <!-- Opsi dimuat oleh JS -->
                </select>
            </div>
            <div class="col-md-2">
                <select id="filter-tahun" class="form-select">
                    <!-- Opsi dimuat oleh JS -->
                </select>
            </div>
            <div class="col-md-2">
                <select id="filter-limit" class="form-select">
                    <option value="15">15 per halaman</option>
                    <option value="30">30 per halaman</option>
                    <option value="50">50 per halaman</option>
                    <option value="100">100 per halaman</option>
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
                        <th>Tanggal</th>
                        <th>Akun</th>
                        <th>No. Ref</th>
                        <th>Keterangan</th>
                        <th class="text-end">Jumlah</th>
                        <th>Dari/Ke Akun Kas</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="transaksi-table-body">
                    <!-- Data akan dimuat oleh JavaScript -->
                    <tr>
                        <td colspan="7" class="text-center p-5">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Memuat...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination justify-content-center" id="transaksi-pagination">
                <!-- Pagination akan dimuat oleh JS -->
            </ul>
        </nav>
    </div>
</div>

<!-- Modal untuk Tambah/Edit Transaksi -->
<div class="modal fade" id="transaksiModal" tabindex="-1" aria-labelledby="transaksiModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="transaksiModalLabel">Tambah Transaksi Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="transaksi-form" novalidate>
            <input type="hidden" name="id" id="transaksi-id">
            <input type="hidden" name="action" id="transaksi-action" value="add">
            <input type="hidden" name="jenis" id="jenis" required>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="jenis" class="form-label">Jenis Transaksi</label>
                    <div id="jenis-btn-group" class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-danger" data-value="pengeluaran"><i class="bi bi-arrow-down-circle-fill me-2"></i>Pengeluaran</button>
                        <button type="button" class="btn btn-outline-success" data-value="pemasukan"><i class="bi bi-arrow-up-circle-fill me-2"></i>Pemasukan</button>
                        <button type="button" class="btn btn-outline-info" data-value="transfer"><i class="bi bi-arrow-left-right me-2"></i>Transfer</button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="tanggal" class="form-label">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="jumlah" class="form-label">Jumlah (Rp)</label>
                <input type="number" class="form-control" id="jumlah" name="jumlah" placeholder="cth: 50000" required>
            </div>

            <div class="mb-3">
                <label for="nomor_referensi" class="form-label">Nomor Referensi (Opsional)</label>
                <input type="text" class="form-control" id="nomor_referensi" name="nomor_referensi" placeholder="Kosongkan untuk nomor otomatis">
            </div>

            <!-- Dynamic Fields -->
            <div id="pemasukan-fields" class="row">
                <div class="col-md-6 mb-3"><label for="kas_account_id_pemasukan" class="form-label">Masuk ke Akun Kas</label><select class="form-select" id="kas_account_id_pemasukan" name="kas_account_id_pemasukan"></select></div>
                <div class="col-md-6 mb-3"><label for="account_id_pemasukan" class="form-label">Dari Akun Pendapatan</label><select class="form-select" id="account_id_pemasukan" name="account_id_pemasukan"></select></div>
            </div>
            <div id="pengeluaran-fields" class="row">
                <div class="col-md-6 mb-3"><label for="kas_account_id_pengeluaran" class="form-label">Keluar dari Akun Kas</label><select class="form-select" id="kas_account_id_pengeluaran" name="kas_account_id_pengeluaran"></select></div>
                <div class="col-md-6 mb-3"><label for="account_id_pengeluaran" class="form-label">Untuk Akun Beban</label><select class="form-select" id="account_id_pengeluaran" name="account_id_pengeluaran"></select></div>
            </div>
            <div id="transfer-fields" class="row" style="display: none;">
                <div class="col-md-6 mb-3"><label for="kas_account_id_transfer" class="form-label">Dari Akun Kas</label><select class="form-select" id="kas_account_id_transfer" name="kas_account_id_transfer"></select></div>
                <div class="col-md-6 mb-3"><label for="kas_tujuan_account_id" class="form-label">Ke Akun Kas</label><select class="form-select" id="kas_tujuan_account_id" name="kas_tujuan_account_id"></select></div>
            </div>

            <div class="mb-3">
                <label for="keterangan" class="form-label">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="2" placeholder="Detail transaksi..."></textarea>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="save-transaksi-btn">Simpan Transaksi</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal untuk Detail Jurnal -->
<div class="modal fade" id="jurnalDetailModal" tabindex="-1" aria-labelledby="jurnalDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="jurnalDetailModalLabel"><i class="bi bi-journal-text"></i> Detail Jurnal Transaksi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="jurnal-detail-body">
        <!-- Konten detail jurnal akan dimuat di sini oleh JavaScript -->
        <div class="text-center p-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
        </div>
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