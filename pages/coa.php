<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-journal-bookmark-fill"></i> Bagan Akun (Chart of Accounts)</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#coaModal" data-action="add">
            <i class="bi bi-plus-circle-fill"></i> Tambah Akun
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="coa-tree-container">
            <!-- Pohon COA akan dirender di sini oleh JavaScript -->
            <div class="text-center p-5">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Memuat...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Tambah/Edit Akun COA -->
<div class="modal fade" id="coaModal" tabindex="-1" aria-labelledby="coaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="coaModalLabel">Tambah Akun Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="coa-form">
            <input type="hidden" name="id" id="coa-id">
            <input type="hidden" name="action" id="coa-action" value="add">
            
            <div class="mb-3">
                <label for="parent_id" class="form-label">Akun Induk (Parent)</label>
                <select class="form-select" id="parent_id" name="parent_id">
                    <!-- Opsi akan dimuat oleh JS -->
                </select>
            </div>
            <div class="mb-3">
                <label for="kode_akun" class="form-label">Kode Akun</label>
                <input type="text" class="form-control" id="kode_akun" name="kode_akun" required>
            </div>
            <div class="mb-3">
                <label for="nama_akun" class="form-label">Nama Akun</label>
                <input type="text" class="form-control" id="nama_akun" name="nama_akun" required>
            </div>
            <div class="mb-3">
                <label for="tipe_akun" class="form-label">Tipe Akun</label>
                <select class="form-select" id="tipe_akun" name="tipe_akun" required>
                    <option value="Aset">Aset</option>
                    <option value="Liabilitas">Liabilitas</option>
                    <option value="Ekuitas">Ekuitas</option>
                    <option value="Pendapatan">Pendapatan</option>
                    <option value="Beban">Beban</option>
                </select>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_kas" name="is_kas" value="1">
                <label class="form-check-label" for="is_kas">
                    Ini adalah akun Kas/Bank (bisa menerima/mengirim uang)
                </label>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="save-coa-btn">Simpan</button>
      </div>
    </div>
  </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>