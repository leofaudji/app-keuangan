<?php  
// Aplikasi RT - Front Controller

// Mulai sesi di setiap permintaan. Ini harus dilakukan sebelum output apa pun.
session_start();  

// Muat komponen inti
require_once 'includes/bootstrap.php';
require_once 'includes/Router.php';

// Router membutuhkan base path yang sudah didefinisikan di bootstrap.php
$router = new Router(BASE_PATH);

// --- Definisikan Rute (Routes) ---

// Rute untuk tamu (hanya bisa diakses jika belum login)
$router->get('/login', 'login.php', ['guest']);
$router->post('/login', 'actions/auth.php'); // Handler untuk proses login

// Rute yang memerlukan otentikasi
$router->get('/', function() {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        header('Location: ' . base_url('/dashboard'));
    } else {
        header('Location: ' . base_url('/login'));
    }
    exit;
});
$router->get('/dashboard', 'pages/dashboard.php', ['auth', 'log_access']);
$router->get('/logout', 'logout.php');
$router->get('/my-profile/change-password', 'pages/my_profile.php', ['auth']);

// --- Rute Utama Aplikasi Keuangan ---
$router->get('/transaksi', 'pages/transaksi.php', ['auth']);
$router->get('/anggaran', 'pages/anggaran.php', ['auth']);
$router->get('/daftar-jurnal', 'pages/daftar_jurnal.php', ['auth']);
$router->get('/entri-jurnal', 'pages/entri_jurnal.php', ['auth']);
$router->get('/coa', 'pages/coa.php', ['auth']);
$router->get('/saldo-awal-neraca', 'pages/saldo_awal_neraca.php', ['auth']);
$router->get('/saldo-awal-lr', 'pages/saldo_awal_lr.php', ['auth']);
$router->get('/laporan', 'pages/laporan.php', ['auth']);
$router->get('/buku-besar', 'pages/buku_besar.php', ['auth']);
$router->get('/settings', 'pages/settings.php', ['auth']);

// --- Rute API (Untuk proses data via AJAX) ---
// Rute ini akan dipanggil oleh JavaScript untuk mendapatkan, menambah, mengubah, dan menghapus data tanpa reload halaman.
$router->get('/api/dashboard', 'api/dashboard_handler.php', ['auth']); // Mengambil data untuk dashboard

// API untuk Transaksi
$router->get('/api/transaksi', 'api/transaksi_handler.php', ['auth']);
$router->post('/api/transaksi', 'api/transaksi_handler.php', ['auth']);

// API untuk fitur lainnya (Rekening, Kategori, Anggaran)
$router->get('/api/coa', 'api/coa_handler.php', ['auth']);
$router->post('/api/coa', 'api/coa_handler.php', ['auth']);
$router->get('/api/anggaran', 'api/anggaran_handler.php', ['auth']);
$router->post('/api/anggaran', 'api/anggaran_handler.php', ['auth']);
$router->get('/api/laporan/neraca', 'api/laporan_neraca_handler.php', ['auth']);
$router->get('/api/laporan/laba-rugi', 'api/laporan_laba_rugi_handler.php', ['auth']);
$router->get('/api/saldo-awal-neraca', 'api/saldo_awal_neraca_handler.php', ['auth']);
$router->post('/api/saldo-awal-neraca', 'api/saldo_awal_neraca_handler.php', ['auth']);
$router->get('/api/saldo-awal-lr', 'api/saldo_awal_lr_handler.php', ['auth']);
$router->post('/api/saldo-awal-lr', 'api/saldo_awal_lr_handler.php', ['auth']);
$router->get('/api/buku-besar', 'api/buku_besar_handler.php', ['auth']);
$router->get('/api/entri-jurnal', 'api/entri_jurnal_handler.php', ['auth']);
$router->get('/api/laporan/arus-kas', 'api/laporan_arus_kas_handler.php', ['auth']);
$router->post('/api/entri-jurnal', 'api/entri_jurnal_handler.php', ['auth']);

$router->get('/api/settings', 'api/settings_handler.php', ['auth']);
$router->post('/api/settings', 'api/settings_handler.php', ['auth']);


// Jalankan router
$router->dispatch();