<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-journal-check"></i> Saldo Awal Neraca</h1>
</div>

<div class="card">
    <div class="card-header">
        Entri Saldo Awal Akun Neraca
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <strong>Informasi:</strong> Gunakan halaman ini untuk mengatur saldo awal akun <strong>Aset, Liabilitas, dan Ekuitas</strong> pada saat pertama kali menggunakan aplikasi. Pastikan total Debit dan Kredit seimbang.
        </div>

        <form id="jurnal-form">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Akun</th>
                            <th>Nama Akun</th>
                            <th class="text-end" style="width: 20%;">Debit</th>
                            <th class="text-end" style="width: 20%;">Kredit</th>
                        </tr>
                    </thead>
                    <tbody id="jurnal-grid-body">
                        <!-- Grid akan dirender di sini oleh JavaScript -->
                        <tr><td colspan="4" class="text-center p-5"><div class="spinner-border"></div></td></tr>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold" id="total-debit">Rp 0</td>
                            <td class="text-end fw-bold" id="total-kredit">Rp 0</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <hr>

            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary" id="save-jurnal-btn">
                    <i class="bi bi-save-fill"></i> Simpan Saldo Awal Neraca
                </button>
            </div>
        </form>
    </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>