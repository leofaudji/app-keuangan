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
    <h1 class="h2"><i class="bi bi-gear-fill"></i> Pengaturan Aplikasi</h1>
</div>

<ul class="nav nav-tabs" id="settingsTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="general-settings-tab" data-bs-toggle="tab" data-bs-target="#general-settings" type="button" role="tab">Umum</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="transaksi-settings-tab" data-bs-toggle="tab" data-bs-target="#transaksi-settings" type="button" role="tab">Transaksi</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="accounting-settings-tab" data-bs-toggle="tab" data-bs-target="#accounting-settings" type="button" role="tab">Akuntansi</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="arus-kas-settings-tab" data-bs-toggle="tab" data-bs-target="#arus-kas-settings" type="button" role="tab">Arus Kas</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="konsinyasi-settings-tab" data-bs-toggle="tab" data-bs-target="#konsinyasi-settings" type="button" role="tab">Konsinyasi</button>
  </li>
</ul>

<div class="tab-content" id="settingsTabContent">
    <!-- Tab Pengaturan Umum -->
    <div class="tab-pane fade show active" id="general-settings" role="tabpanel">
        <div class="card card-tab">
            <div class="card-body">
                <form id="settings-form" enctype="multipart/form-data">
                    <div id="settings-container">
                        <div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Memuat...</span></div></div>
                    </div>
                    <hr>
                    <button type="button" class="btn btn-primary" id="save-settings-btn">
                        <i class="bi bi-save-fill"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Pengaturan Transaksi -->
    <div class="tab-pane fade" id="transaksi-settings" role="tabpanel">
        <div class="card card-tab">
            <div class="card-body">
                <form id="transaksi-settings-form">
                    <div id="transaksi-settings-container">
                        <div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Memuat...</span></div></div>
                    </div>
                    <hr>
                    <button type="button" class="btn btn-primary" id="save-transaksi-settings-btn"><i class="bi bi-save-fill"></i> Simpan Pengaturan</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Pengaturan Akuntansi -->
    <div class="tab-pane fade" id="accounting-settings" role="tabpanel">
        <div class="card card-tab">
            <div class="card-header">
                Pengaturan Akuntansi
            </div>
            <div class="card-body">
                <form id="accounting-settings-form">
                    <div id="accounting-settings-container">
                        <div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Memuat...</span></div></div>
                    </div>
                    <hr>
                    <button type="button" class="btn btn-primary" id="save-accounting-settings-btn"><i class="bi bi-save-fill"></i> Simpan Pengaturan</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Pengaturan Arus Kas -->
    <div class="tab-pane fade" id="arus-kas-settings" role="tabpanel">
        <div class="card card-tab">
            <div class="card-header">
                Pemetaan Akun untuk Laporan Arus Kas
            </div>
            <div class="card-body">
                <div class="alert alert-info small">
                    Tentukan kategori arus kas untuk setiap akun. Ini akan digunakan untuk mengelompokkan transaksi dalam Laporan Arus Kas. Akun yang tidak diklasifikasikan akan dianggap sebagai aktivitas Operasi secara default.
                </div>
                <form id="arus-kas-settings-form">
                    <div id="arus-kas-mapping-container">
                        <div class="text-center p-5"><div class="spinner-border"></div></div>
                    </div>
                    <hr>
                    <button type="button" class="btn btn-primary" id="save-arus-kas-settings-btn">
                        <i class="bi bi-save-fill"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Pengaturan Konsinyasi -->
    <div class="tab-pane fade" id="konsinyasi-settings" role="tabpanel">
        <div class="card card-tab">
            <div class="card-header">
                Pemetaan Akun untuk Transaksi Konsinyasi
            </div>
            <div class="card-body">
                <div class="alert alert-info small">
                    Pilih akun default yang akan digunakan saat mencatat penjualan barang konsinyasi.
                </div>
                <form id="konsinyasi-settings-form">
                    <div id="konsinyasi-settings-container">
                        <div class="text-center p-5"><div class="spinner-border"></div></div>
                    </div>
                    <hr><button type="button" class="btn btn-primary" id="save-konsinyasi-settings-btn"><i class="bi bi-save-fill"></i> Simpan Pengaturan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>