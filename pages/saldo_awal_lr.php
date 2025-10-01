<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-graph-up-arrow"></i> Saldo Awal Laba Rugi</h1>
</div>

<div class="card">
    <div class="card-header">
        Entri Saldo Awal Akun Pendapatan & Beban
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Informasi:</strong> Gunakan halaman ini untuk memasukkan total saldo akun <strong>Pendapatan</strong> dan <strong>Beban</strong> dari awal periode hingga tanggal Anda mulai menggunakan aplikasi ini (Year-to-Date). Ini akan memastikan Laporan Laba Rugi Anda akurat untuk periode berjalan.
        </div>

        <form id="saldo-lr-form">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Akun</th>
                            <th>Nama Akun</th>
                            <th>Tipe Akun</th>
                            <th class="text-end" style="width: 25%;">Total Saldo (YTD)</th>
                        </tr>
                    </thead>
                    <tbody id="saldo-lr-grid-body">
                        <!-- Grid akan dirender di sini oleh JavaScript -->
                        <tr><td colspan="4" class="text-center p-5"><div class="spinner-border"></div></td></tr>
                    </tbody>
                </table>
            </div>
            <hr>

            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary" id="save-saldo-lr-btn">
                    <i class="bi bi-save-fill"></i> Simpan Saldo Awal Laba Rugi
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