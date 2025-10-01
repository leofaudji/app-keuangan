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
$per_tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // 1. Ambil SEMUA akun untuk menghitung mutasi secara komprehensif
    $stmt_accounts = $conn->prepare(" 
        SELECT id, parent_id, kode_akun, nama_akun, tipe_akun, saldo_normal, saldo_awal 
        FROM accounts 
        WHERE user_id = ?
        ORDER BY kode_akun ASC
    ");
    $stmt_accounts->bind_param('i', $user_id);
    $stmt_accounts->execute();
    $accounts_result = $stmt_accounts->get_result();
    $accounts = [];
    while ($row = $accounts_result->fetch_assoc()) {
        $accounts[$row['id']] = $row;
        // Saldo awal harus disesuaikan dengan saldo normal.
        // Akun dengan saldo normal Kredit (Liabilitas, Ekuitas) disimpan sebagai negatif,
        // tapi untuk perhitungan mutasi, kita mulai dari nilai absolutnya (positif).
        // Tanda negatif/positif akan ditentukan oleh mutasi Debit/Kredit.
        // Namun, dalam sistem ini, kita akan tetap menggunakan nilai aslinya karena mutasi sudah benar.
        $accounts[$row['id']]['saldo_akhir'] = (float)$row['saldo_awal'];
    }
    $stmt_accounts->close();

    // 2. Proses mutasi dari transaksi sederhana
    $stmt_transactions = $conn->prepare("
        SELECT 
            t.jenis, t.jumlah, t.account_id, t.kas_account_id, t.kas_tujuan_account_id
        FROM transaksi t
        WHERE t.user_id = ? AND t.tanggal <= ?
    ");
    $stmt_transactions->bind_param('is', $user_id, $per_tanggal);
    $stmt_transactions->execute();
    $transactions_result = $stmt_transactions->get_result();

    while ($tx = $transactions_result->fetch_assoc()) {
        $jumlah = (float)$tx['jumlah'];
        if ($tx['jenis'] === 'pemasukan') {
            if (isset($accounts[$tx['kas_account_id']])) $accounts[$tx['kas_account_id']]['saldo_akhir'] += $jumlah; // Aset (Kas) bertambah
            if (isset($accounts[$tx['account_id']])) $accounts[$tx['account_id']]['saldo_akhir'] += $jumlah; // Pendapatan bertambah
        } elseif ($tx['jenis'] === 'pengeluaran') {
            if (isset($accounts[$tx['kas_account_id']])) $accounts[$tx['kas_account_id']]['saldo_akhir'] -= $jumlah; // Aset (Kas) berkurang
            // Akun Beban bertambah
            if (isset($accounts[$tx['account_id']]) && $accounts[$tx['account_id']]['tipe_akun'] === 'Beban') {
                $accounts[$tx['account_id']]['saldo_akhir'] += $jumlah;
            }
            // Jika pengeluaran untuk membayar Liabilitas, saldo Liabilitas berkurang
            if (isset($accounts[$tx['account_id']]) && $accounts[$tx['account_id']]['tipe_akun'] === 'Liabilitas') {
                $accounts[$tx['account_id']]['saldo_akhir'] -= $jumlah;
            }
        } elseif ($tx['jenis'] === 'transfer') {
            // Kas sumber berkurang (Kredit), Kas tujuan bertambah (Debit)
            if (isset($accounts[$tx['kas_account_id']])) $accounts[$tx['kas_account_id']]['saldo_akhir'] -= $jumlah;
            if (isset($accounts[$tx['kas_tujuan_account_id']])) $accounts[$tx['kas_tujuan_account_id']]['saldo_akhir'] += $jumlah;
        }
    }
    $stmt_transactions->close();

    // 3. Proses mutasi dari Jurnal Umum (Majemuk)
    $stmt_jurnal = $conn->prepare("
        SELECT jd.account_id, jd.debit, jd.kredit
        FROM jurnal_details jd
        JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
        WHERE je.user_id = ? AND je.tanggal <= ?
    ");
    $stmt_jurnal->bind_param('is', $user_id, $per_tanggal);
    $stmt_jurnal->execute();
    $jurnal_result = $stmt_jurnal->get_result();
    while ($jurnal_line = $jurnal_result->fetch_assoc()) {
        if (isset($accounts[$jurnal_line['account_id']])) {
            $account = &$accounts[$jurnal_line['account_id']];
            if ($account['saldo_normal'] === 'Debit') {
                // Untuk Aset & Beban: saldo bertambah oleh debit, berkurang oleh kredit
                $account['saldo_akhir'] += (float)$jurnal_line['debit'] - (float)$jurnal_line['kredit'];
            } else { // Saldo Normal adalah Kredit
                // Untuk Liabilitas, Ekuitas, Pendapatan: saldo bertambah oleh kredit, berkurang oleh debit
                $account['saldo_akhir'] += (float)$jurnal_line['kredit'] - (float)$jurnal_line['debit'];
            }
        }
    }
    $stmt_jurnal->close();

    // 4. Hitung Laba Rugi Berjalan dari saldo akhir akun Pendapatan dan Beban
    $total_pendapatan = 0;
    $total_beban = 0;
    foreach ($accounts as $acc) {
        if ($acc['tipe_akun'] === 'Pendapatan') {
            $total_pendapatan += $acc['saldo_akhir'];
        } elseif ($acc['tipe_akun'] === 'Beban') {
            $total_beban += $acc['saldo_akhir'];
        }
    }
    $laba_rugi_berjalan = $total_pendapatan - $total_beban;

    // 5. Buat akun virtual untuk Laba Rugi Berjalan dan tambahkan ke Ekuitas
    $accounts['laba_rugi_virtual'] = [
        'id' => 'laba_rugi_virtual', 'parent_id' => 300, 'kode_akun' => '3-9999', // Parent ID 300 adalah root Ekuitas
        'nama_akun' => 'Laba (Rugi) Periode Berjalan', 'tipe_akun' => 'Ekuitas',
        'saldo_akhir' => $laba_rugi_berjalan
    ];

    // Filter hanya akun Neraca untuk dikirim ke frontend
    $neraca_accounts = array_filter($accounts, function($acc) {
        return in_array($acc['tipe_akun'], ['Aset', 'Liabilitas', 'Ekuitas']);
    });

    echo json_encode(['status' => 'success', 'data' => array_values($neraca_accounts)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>