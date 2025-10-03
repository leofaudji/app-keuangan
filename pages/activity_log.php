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
    <h1 class="h2"><i class="bi bi-list-check"></i> Log Aktivitas Pengguna</h1>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" id="search-log" class="form-control" placeholder="Cari username, aksi, atau detail...">
            </div>
            <div class="col-md-3">
                <input type="date" id="filter-log-mulai" class="form-control">
            </div>
            <div class="col-md-3">
                <input type="date" id="filter-log-akhir" class="form-control">
            </div>
            <div class="col-md-2">
                <select id="filter-log-limit" class="form-select">
                    <option value="15">15</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>Waktu</th><th>Username</th><th>Aksi</th><th>Detail</th><th>Alamat IP</th>
                    </tr>
                </thead>
                <tbody id="activity-log-table-body"></tbody>
            </table>
        </div>
        <nav><ul class="pagination justify-content-center" id="activity-log-pagination"></ul></nav>
    </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>