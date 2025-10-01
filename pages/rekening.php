<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-wallet2"></i> Manajemen Rekening</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rekeningModal" data-action="add">
            <i class="bi bi-plus-circle-fill"></i> Tambah Rekening
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nama Rekening</th>
                        <th class="text-end">Saldo Awal</th>
                        <th class="text-end">Saldo Saat Ini</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="rekening-table-body">
                    <!-- Data akan dimuat oleh JavaScript -->
                    <tr>
                        <td colspan="4" class="text-center p-5">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Memuat...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal untuk Tambah/Edit Rekening -->
<div class="modal fade" id="rekeningModal" tabindex="-1" aria-labelledby="rekeningModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rekeningModalLabel">Tambah Rekening Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="rekening-form">
            <input type="hidden" name="id" id="rekening-id">
            <input type="hidden" name="action" id="rekening-action" value="add">
            <div class="mb-3">
                <label for="nama_rekening" class="form-label">Nama Rekening</label>
                <input type="text" class="form-control" id="nama_rekening" name="nama_rekening" placeholder="cth: Dompet, Bank BCA" required>
            </div>
            <div class="mb-3">
                <label for="saldo_awal" class="form-label">Saldo Awal (Rp)</label>
                <input type="number" class="form-control" id="saldo_awal" name="saldo_awal" value="0" required>
                <small class="form-text text-muted">Masukkan saldo awal rekening ini.</small>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="save-rekening-btn">Simpan</button>
      </div>
    </div>
  </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>