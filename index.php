<?php  
// Aplikasi RT - Front Controller

// Mulai sesi di setiap permintaan. Ini harus dilakukan sebelum output apa pun.
session_start();  

// Muat komponen inti
require_once 'includes/bootstrap.php';

// --- Auto Login from "Remember Me" Cookie ---
// Jalankan ini setelah bootstrap (untuk fungsi) tetapi sebelum router (untuk otentikasi)
if (empty($_SESSION['loggedin']) && isset($_COOKIE['remember_me'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_me'], 2);

    if (!empty($selector) && !empty($validator)) {
        // Fungsi attempt_login_with_cookie() didefinisikan di dalam bootstrap.php
        attempt_login_with_cookie($selector, $validator);
    }
}
// --- End Auto Login ---

require_once 'includes/Router.php';

// Router membutuhkan base path yang sudah didefinisikan di bootstrap.php
$router = new Router(BASE_PATH);

// --- Definisikan Rute (Routes) ---

// Rute untuk tamu (hanya bisa diakses jika belum login)
$router->get('/login', 'login.php', ['guest']);
$router->post('/login', 'actions/auth.php'); // Handler untuk proses login
$router->get('/forgot', 'pages/forgot_password.php', ['guest']);
$router->post('/actions/forgot_password_action.php', 'actions/forgot_password_action.php', ['guest']);
$router->get('/reset-password', 'pages/reset_password.php', ['guest']);
$router->post('/reset-password', 'actions/reset_password_action.php', ['guest']);

// Rute yang memerlukan otentikasi
$router->get('/', function() {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        header('Location: ' . base_url('/dashboard'));
    } else {
        header('Location: ' . base_url('/login'));
    }
    exit;
});
$router->get('/dashboard', 'pages/dashboard.php', ['auth']);
$router->get('/buku-panduan', 'pages/buku_panduan.php', ['auth']);
$router->get('/logout', 'logout.php');
$router->get('/my-profile/change-password', 'pages/my_profile.php', ['auth']);

// --- Rute Utama Aplikasi Keuangan ---
$router->get('/transaksi', 'pages/transaksi.php', ['auth']);
$router->get('/daftar-jurnal', 'pages/daftar_jurnal.php', ['auth']);
$router->get('/konsinyasi', 'pages/konsinyasi.php', ['auth']);
$router->get('/transaksi-berulang', 'pages/transaksi_berulang.php', ['auth']);
$router->get('/rekonsiliasi-bank', 'pages/rekonsiliasi_bank.php', ['auth']);
$router->get('/histori-rekonsiliasi', 'pages/histori_rekonsiliasi.php', ['auth']);
$router->get('/entri-jurnal', 'pages/entri_jurnal.php', ['auth']);
$router->get('/coa', 'pages/coa.php', ['auth']);
$router->get('/saldo-awal-neraca', 'pages/saldo_awal_neraca.php', ['auth']);
$router->get('/saldo-awal-lr', 'pages/saldo_awal_lr.php', ['auth']);
$router->get('/laporan', 'pages/laporan.php', ['auth']); 
$router->get('/anggaran', 'pages/anggaran.php', ['auth']);
$router->get('/tutup-buku', 'pages/tutup_buku.php', ['auth', 'admin']);
$router->get('/laporan-laba-ditahan', 'pages/laporan_laba_ditahan.php', ['auth']);
$router->get('/laporan-pertumbuhan-laba', 'pages/laporan_pertumbuhan_laba.php', ['auth']);
$router->get('/analisis-rasio', 'pages/laporan_analisis_rasio.php', ['auth']); // Nama file halaman sudah benar
$router->get('/activity-log', 'pages/activity_log.php', ['auth', 'admin']);
$router->get('/laporan-harian', 'pages/laporan_harian.php', ['auth']);
$router->get('/buku-besar', 'pages/buku_besar.php', ['auth']);
$router->get('/settings', 'pages/settings.php', ['auth']);
$router->get('/users', 'pages/users.php', ['auth', 'admin']); // Halaman manajemen pengguna

// --- Rute API (Untuk proses data via AJAX) ---
// Rute ini akan dipanggil oleh JavaScript untuk mendapatkan, menambah, mengubah, dan menghapus data tanpa reload halaman.
$router->get('/api/dashboard', 'api/dashboard_handler.php', ['auth']); // Mengambil data untuk dashboard

// API untuk Transaksi
$router->get('/api/transaksi', 'api/transaksi_handler.php', ['auth']);
$router->post('/api/transaksi', 'api/transaksi_handler.php', ['auth']);

// API untuk fitur lainnya (Rekening, Kategori, Anggaran)
$router->get('/api/coa', 'api/coa_handler.php', ['auth']);
$router->post('/api/coa', 'api/coa_handler.php', ['auth']);
$router->get('/api/laporan/neraca', 'api/laporan_neraca_handler.php', ['auth']);
$router->get('/api/laporan/laba-rugi', 'api/laporan_laba_rugi_handler.php', ['auth']);
$router->get('/api/laporan-harian', 'api/laporan_harian_handler.php', ['auth']);
$router->get('/api/csv', 'api/laporan_cetak_csv_handler.php', ['auth']); // Rute baru untuk cetak CSV
$router->get('/api/pdf', 'api/laporan_cetak_handler.php', ['auth']); // Rute baru untuk cetak PDF (GET)
$router->post('/api/pdf', 'api/laporan_cetak_handler.php', ['auth']); // Rute baru untuk cetak PDF (POST)
$router->get('/api/saldo-awal-neraca', 'api/saldo_awal_neraca_handler.php', ['auth']);
$router->post('/api/saldo-awal-neraca', 'api/saldo_awal_neraca_handler.php', ['auth']);
$router->get('/api/saldo-awal-lr', 'api/saldo_awal_lr_handler.php', ['auth']);
$router->post('/api/saldo-awal-lr', 'api/saldo_awal_lr_handler.php', ['auth']);
$router->get('/api/buku-besar-data', 'api/buku_besar_data_handler.php', ['auth']);
$router->get('/api/entri-jurnal', 'api/entri_jurnal_handler.php', ['auth']);
$router->get('/api/laporan/arus-kas', 'api/laporan_arus_kas_handler.php', ['auth']);
$router->post('/api/entri-jurnal', 'api/entri_jurnal_handler.php', ['auth']);

$router->get('/api/konsinyasi', 'api/konsinyasi_handler.php', ['auth']);
$router->post('/api/konsinyasi', 'api/konsinyasi_handler.php', ['auth']);

$router->get('/api/recurring', 'api/recurring_handler.php', ['auth']);
$router->post('/api/recurring', 'api/recurring_handler.php', ['auth']);

// API untuk Rekonsiliasi Bank
$router->get('/api/rekonsiliasi-bank', 'api/rekonsiliasi_bank_handler.php', ['auth']);
$router->post('/api/rekonsiliasi-bank', 'api/rekonsiliasi_bank_handler.php', ['auth']);
$router->get('/api/histori-rekonsiliasi', 'api/histori_rekonsiliasi_handler.php', ['auth']);
$router->post('/api/histori-rekonsiliasi', 'api/histori_rekonsiliasi_handler.php', ['auth']);

$router->get('/api/tutup-buku', 'api/tutup_buku_handler.php', ['auth', 'admin']);
$router->get('/api/laporan-laba-ditahan', 'api/laporan_laba_ditahan_handler.php', ['auth']);
$router->post('/api/tutup-buku', 'api/tutup_buku_handler.php', ['auth', 'admin']);
$router->get('/api/laporan-pertumbuhan-laba', 'api/laporan_pertumbuhan_laba_handler.php', ['auth']);
$router->get('/api/analisis-rasio', 'api/analisis_rasio_handler.php', ['auth']); // Nama file API sudah benar

$router->get('/api/activity-log', 'api/activity_log_handler.php', ['auth', 'admin']);
$router->get('/api/anggaran', 'api/anggaran_handler.php', ['auth']);
$router->post('/api/anggaran', 'api/anggaran_handler.php', ['auth']);
$router->get('/api/settings', 'api/settings_handler.php', ['auth']);
$router->post('/api/settings', 'api/settings_handler.php', ['auth']);
$router->get('/api/global-search', 'api/global_search_handler.php', ['auth']); // API untuk pencarian global
$router->get('/api/users', 'api/users_handler.php', ['auth', 'admin']); // API untuk manajemen pengguna
$router->post('/api/users', 'api/users_handler.php', ['auth', 'admin']);


// Jalankan router
$router->dispatch();