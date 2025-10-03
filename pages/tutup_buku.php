<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Akses ditolak. Anda harus menjadi Admin untuk melihat halaman ini.</div>';
    if (!$is_spa_request) {
        require_once PROJECT_ROOT . '/views/footer.php';
    }
    return; // Stop rendering
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-archive-fill"></i> Tutup Buku Periodik</h1>
</div>

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                Proses Tutup Buku
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong>PERHATIAN!</strong> Proses tutup buku akan membuat Jurnal Penutup untuk menolkan saldo akun Pendapatan dan Beban, lalu memindahkannya ke Laba Ditahan. Proses ini sebaiknya dilakukan di akhir periode (misal: 31 Desember) dan tidak dapat dibatalkan dengan mudah.
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="closing-date" class="form-label">Tanggal Tutup Buku</label>
                        <input type="date" id="closing-date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <button id="process-closing-btn" class="btn btn-danger w-100">
                            <i class="bi bi-lock-fill"></i> Proses Tutup Buku
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Histori Jurnal Penutup
            </div>
            <div class="card-body">
                <div id="closing-history-container" class="list-group">
                    <div class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>