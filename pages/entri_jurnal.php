<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-journal-plus"></i> Entri Jurnal</h1>
</div>

<div class="card">
    <div class="card-header">
        Buat Jurnal Umum (Majemuk)
    </div>
    <div class="card-body">
        <form id="entri-jurnal-form">
            <input type="hidden" name="id" id="jurnal-id">
            <input type="hidden" name="action" id="jurnal-action" value="add">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="jurnal-tanggal" class="form-label">Tanggal</label>
                    <input type="date" id="jurnal-tanggal" name="tanggal" class="form-control" required>
                </div>
                <div class="col-md-8">
                    <label for="jurnal-keterangan" class="form-label">Keterangan</label>
                    <input type="text" id="jurnal-keterangan" name="keterangan" class="form-control" placeholder="Deskripsi jurnal..." required>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Akun</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Kredit</th>
                            <th class="text-center" style="width: 5%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="jurnal-lines-body">
                        <!-- Baris jurnal akan ditambahkan di sini oleh JS -->
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold" id="total-jurnal-debit">Rp 0</td>
                            <td class="text-end fw-bold" id="total-jurnal-kredit">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary" id="add-jurnal-line-btn"><i class="bi bi-plus-lg"></i> Tambah Baris</button>
            <hr>
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-outline-secondary me-2" id="save-as-recurring-btn">
                    <i class="bi bi-arrow-repeat"></i> Jadikan Berulang...
                </button>
                <button type="submit" class="btn btn-primary" id="save-jurnal-entry-btn"><i class="bi bi-save-fill"></i> Simpan Entri Jurnal</button>
            </div>
        </form>
    </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>