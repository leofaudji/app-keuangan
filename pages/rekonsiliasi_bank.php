<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-bank2"></i> Rekonsiliasi Bank</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?= base_url('/histori-rekonsiliasi') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-clock-history"></i> Lihat Histori
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="recon-akun-filter" class="form-label">Akun Kas/Bank</label>
                <select id="recon-akun-filter" class="form-select"></select>
            </div>
            <div class="col-md-3">
                <label for="recon-tanggal-akhir" class="form-label">Rekonsiliasi s/d Tanggal</label>
                <input type="date" id="recon-tanggal-akhir" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="recon-saldo-rekening" class="form-label">Saldo Akhir Rekening Koran</label>
                <input type="number" id="recon-saldo-rekening" class="form-control" placeholder="Masukkan saldo dari bank">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" id="recon-tampilkan-btn">
                    <i class="bi bi-search"></i> Mulai Rekonsiliasi
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Konten Utama -->
<div id="reconciliation-content" class="d-none">
    <!-- Ringkasan -->
    <div class="card mb-3">
        <div class="card-header">Ringkasan Rekonsiliasi</div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value" id="summary-saldo-buku">Rp 0</div>
                        <div class="stat-label">Saldo Akhir di Aplikasi</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value" id="summary-saldo-bank">Rp 0</div>
                        <div class="stat-label">Saldo Akhir di Bank</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value text-success" id="summary-cleared">Rp 0</div>
                        <div class="stat-label">Total Transaksi Cocok (Cleared)</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value text-danger" id="summary-selisih">Rp 0</div>
                        <div class="stat-label">Selisih</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekonsiliasi -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <!-- Sisi Kiri: Transaksi Aplikasi -->
                <div class="col-md-12">
                    <h5>Transaksi di Aplikasi</h5>
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th><input type="checkbox" id="check-all-app"></th>
                                    <th>Tanggal</th>
                                    <th>Keterangan</th>
                                    <th class="text-end">Pemasukan (Debit)</th>
                                    <th class="text-end">Pengeluaran (Kredit)</th>
                                </tr>
                            </thead>
                            <tbody id="app-transactions-body">
                                <!-- Data dari API -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-end">
                <button class="btn btn-success" id="save-reconciliation-btn" disabled>
                    <i class="bi bi-check-circle-fill"></i> Simpan Rekonsiliasi
                </button>
            </div>
        </div>
    </div>
</div>


<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>
