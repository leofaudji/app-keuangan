<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-arrow-repeat"></i> Transaksi & Jurnal Berulang</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="#" class="btn btn-primary" id="add-recurring-btn">
            <i class="bi bi-plus-circle-fill"></i> Buat Template Baru
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nama Template</th>
                        <th>Jadwal</th>
                        <th>Tanggal Jalan Berikutnya</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="recurring-table-body">
                    <!-- Data dimuat oleh JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal akan menggunakan modal yang sudah ada (transaksiModal & entriJurnalModal) dengan sedikit modifikasi -->

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>