<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$term = $_GET['term'] ?? '';

if (strlen($term) < 3) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$results = [];
$search_term = '%' . $term . '%';

try {
    // 1. Cari di General Ledger (mencakup Transaksi dan Jurnal Manual)
    $stmt_gl = $conn->prepare("
        SELECT ref_id, ref_type, tanggal, keterangan
        FROM general_ledger
        WHERE user_id = ? AND (keterangan LIKE ? OR nomor_referensi LIKE ?)
        GROUP BY ref_id, ref_type, tanggal, keterangan
        ORDER BY tanggal DESC
        LIMIT 5
    ");
    $stmt_gl->bind_param('iss', $user_id, $search_term, $search_term);
    $stmt_gl->execute();
    $gl_results = $stmt_gl->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_gl->close();

    foreach ($gl_results as $item) {
        if ($item['ref_type'] === 'transaksi') {
            $results[] = [
                'link' => '/transaksi#tx-' . $item['ref_id'],
                'icon' => 'bi-arrow-down-up',
                'title' => $item['keterangan'],
                'subtitle' => 'Transaksi pada ' . date('d M Y', strtotime($item['tanggal'])),
                'type' => 'Transaksi'
            ];
        } else { // jurnal
            $results[] = [
                'link' => '/daftar-jurnal#JRN-' . $item['ref_id'],
                'icon' => 'bi-journal-text',
                'title' => $item['keterangan'],
                'subtitle' => 'Jurnal pada ' . date('d M Y', strtotime($item['tanggal'])),
                'type' => 'Jurnal'
            ];
        }
    }

    // 2. Cari di Bagan Akun (COA)
    $stmt_coa = $conn->prepare("
        SELECT id, kode_akun, nama_akun
        FROM accounts
        WHERE user_id = ? AND (nama_akun LIKE ? OR kode_akun LIKE ?)
        LIMIT 5
    ");
    $stmt_coa->bind_param('iss', $user_id, $search_term, $search_term);
    $stmt_coa->execute();
    $coa_results = $stmt_coa->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_coa->close();

    foreach ($coa_results as $item) {
        $results[] = [
            'link' => '/coa',
            'icon' => 'bi-journal-bookmark-fill',
            'title' => $item['nama_akun'],
            'subtitle' => 'Akun: ' . $item['kode_akun'],
            'type' => 'Bagan Akun'
        ];
    }

    // 3. Cari di Pemasok (Suppliers)
    $stmt_sup = $conn->prepare("
        SELECT id, nama_pemasok
        FROM suppliers
        WHERE user_id = ? AND nama_pemasok LIKE ?
        LIMIT 3
    ");
    $stmt_sup->bind_param('is', $user_id, $search_term);
    $stmt_sup->execute();
    $sup_results = $stmt_sup->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_sup->close();

    foreach ($sup_results as $item) {
        $results[] = [
            'link' => '/konsinyasi', // Arahkan ke halaman konsinyasi
            'icon' => 'bi-truck',
            'title' => $item['nama_pemasok'],
            'subtitle' => 'Pemasok Konsinyasi',
            'type' => 'Pemasok'
        ];
    }

    // 4. Cari di Pengguna (Users) - Hanya untuk Admin
    if ($_SESSION['role'] === 'admin') {
        $stmt_users = $conn->prepare("
            SELECT id, username, nama_lengkap
            FROM users
            WHERE username LIKE ? OR nama_lengkap LIKE ?
            LIMIT 3
        ");
        $stmt_users->bind_param('ss', $search_term, $search_term);
        $stmt_users->execute();
        $user_results = $stmt_users->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_users->close();

        foreach ($user_results as $item) {
            $results[] = [
                'link' => '/users', // Arahkan ke halaman manajemen pengguna
                'icon' => 'bi-person-fill-gear',
                'title' => $item['nama_lengkap'] . ' (@' . $item['username'] . ')',
                'subtitle' => 'Pengguna Sistem',
                'type' => 'Pengguna'
            ];
        }
    }

    // 5. Cari di Template Transaksi Berulang
    $stmt_recurring = $conn->prepare("
        SELECT id, name, next_run_date
        FROM recurring_templates
        WHERE user_id = ? AND name LIKE ?
        LIMIT 3
    ");
    $stmt_recurring->bind_param('is', $user_id, $search_term);
    $stmt_recurring->execute();
    $recurring_results = $stmt_recurring->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_recurring->close();

    foreach ($recurring_results as $item) {
        $results[] = [
            'link' => '/transaksi-berulang',
            'icon' => 'bi-arrow-repeat',
            'title' => $item['name'],
            'subtitle' => 'Jadwal berikutnya: ' . date('d M Y', strtotime($item['next_run_date'])),
            'type' => 'Template Berulang'
        ];
    }

    // 6. Cari di Barang Konsinyasi
    $stmt_citems = $conn->prepare("
        SELECT id, nama_barang, harga_jual
        FROM consignment_items
        WHERE user_id = ? AND nama_barang LIKE ?
        LIMIT 3
    ");
    $stmt_citems->bind_param('is', $user_id, $search_term);
    $stmt_citems->execute();
    $citems_results = $stmt_citems->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_citems->close();

    foreach ($citems_results as $item) {
        $results[] = [
            'link' => '/konsinyasi', // Arahkan ke halaman konsinyasi
            'icon' => 'bi-box-seam',
            'title' => $item['nama_barang'],
            'subtitle' => 'Harga Jual: ' . number_format($item['harga_jual'], 0, ',', '.'),
            'type' => 'Barang Konsinyasi'
        ];
    }

    // 7. Cari di Menu Aplikasi
    $all_menus = [
        ['link' => '/dashboard', 'icon' => 'bi-speedometer2', 'title' => 'Dashboard', 'subtitle' => 'Halaman utama', 'type' => 'Menu'],
        ['link' => '/transaksi', 'icon' => 'bi-arrow-down-up', 'title' => 'Transaksi', 'subtitle' => 'Catat pemasukan & pengeluaran', 'type' => 'Menu'],
        ['link' => '/entri-jurnal', 'icon' => 'bi-journal-plus', 'title' => 'Entri Jurnal', 'subtitle' => 'Buat jurnal manual', 'type' => 'Menu'],
        ['link' => '/daftar-jurnal', 'icon' => 'bi-list-ol', 'title' => 'Daftar Jurnal', 'subtitle' => 'Lihat semua entri jurnal', 'type' => 'Menu'],
        ['link' => '/buku-besar', 'icon' => 'bi-book', 'title' => 'Buku Besar', 'subtitle' => 'Lihat riwayat transaksi per akun', 'type' => 'Menu'],
        ['link' => '/coa', 'icon' => 'bi-journal-bookmark-fill', 'title' => 'Bagan Akun (COA)', 'subtitle' => 'Kelola daftar akun', 'type' => 'Menu'],
        ['link' => '/laporan', 'icon' => 'bi-bar-chart-line-fill', 'title' => 'Laporan Keuangan', 'subtitle' => 'Lihat Neraca, Laba Rugi, dll.', 'type' => 'Menu'],
        ['link' => '/anggaran', 'icon' => 'bi-bullseye', 'title' => 'Anggaran', 'subtitle' => 'Rencanakan dan lacak anggaran', 'type' => 'Menu'],
        ['link' => '/rekonsiliasi-bank', 'icon' => 'bi-bank2', 'title' => 'Rekonsiliasi Bank', 'subtitle' => 'Cocokkan catatan bank', 'type' => 'Menu'],
        ['link' => '/konsinyasi', 'icon' => 'bi-box-seam-fill', 'title' => 'Konsinyasi', 'subtitle' => 'Kelola barang titipan', 'type' => 'Menu'],
    ];

    if ($_SESSION['role'] === 'admin') {
        $all_menus[] = ['link' => '/users', 'icon' => 'bi-people-fill', 'title' => 'Manajemen Pengguna', 'subtitle' => 'Tambah atau edit pengguna', 'type' => 'Admin'];
        $all_menus[] = ['link' => '/settings', 'icon' => 'bi-gear-fill', 'title' => 'Pengaturan', 'subtitle' => 'Konfigurasi aplikasi', 'type' => 'Admin'];
        $all_menus[] = ['link' => '/tutup-buku', 'icon' => 'bi-archive-fill', 'title' => 'Tutup Buku', 'subtitle' => 'Proses akhir periode akuntansi', 'type' => 'Admin'];
        $all_menus[] = ['link' => '/activity-log', 'icon' => 'bi-list-check', 'title' => 'Log Aktivitas', 'subtitle' => 'Lihat semua aktivitas pengguna', 'type' => 'Admin'];
    }

    $search_term_plain = str_replace('%', '', $term);
    $menu_results = array_filter($all_menus, function($menu) use ($search_term_plain) {
        return stripos($menu['title'], $search_term_plain) !== false || stripos($menu['subtitle'], $search_term_plain) !== false;
    });

    foreach ($menu_results as $item) {
        // Cek agar tidak ada duplikat jika sudah ditemukan di pencarian lain
        $is_duplicate = false;
        foreach ($results as $existing_result) {
            if ($existing_result['link'] === $item['link']) {
                $is_duplicate = true;
                break;
            }
        }
        if (!$is_duplicate) {
            $results[] = $item;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $results]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}