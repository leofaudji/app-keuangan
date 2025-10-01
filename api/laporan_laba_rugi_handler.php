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

$tanggal_mulai = $_GET['start'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['end'] ?? date('Y-m-t');

try {
    // 1. Ambil semua akun Pendapatan beserta total transaksinya
    $stmt_pendapatan = $conn->prepare("
        SELECT a.id, a.kode_akun, a.nama_akun, (a.saldo_awal + COALESCE(trx.total, 0) + COALESCE(jurnal.total, 0)) as total
        FROM accounts a
        -- Subquery untuk transaksi sederhana
        LEFT JOIN (
            SELECT account_id, SUM(jumlah) as total
            FROM transaksi
            WHERE user_id = ? AND jenis = 'pemasukan' AND tanggal BETWEEN ? AND ?
            GROUP BY account_id
        ) trx ON a.id = trx.account_id
        -- Subquery untuk jurnal majemuk
        LEFT JOIN (
            SELECT jd.account_id, SUM(jd.kredit - jd.debit) as total
            FROM jurnal_details jd
            JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
            WHERE je.user_id = ? AND je.tanggal BETWEEN ? AND ?
            GROUP BY jd.account_id
        ) jurnal ON a.id = jurnal.account_id
        WHERE a.user_id = ? AND a.tipe_akun = 'Pendapatan'
        ORDER BY a.kode_akun ASC
    ");
    $stmt_pendapatan->bind_param('ississi', $user_id, $tanggal_mulai, $tanggal_akhir, $user_id, $tanggal_mulai, $tanggal_akhir, $user_id);
    $stmt_pendapatan->execute();
    $pendapatan = $stmt_pendapatan->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pendapatan->close();

    // 2. Ambil semua akun Beban beserta total transaksinya
    $stmt_beban = $conn->prepare("
        SELECT a.id, a.kode_akun, a.nama_akun, (a.saldo_awal + COALESCE(trx.total, 0) + COALESCE(jurnal.total, 0)) as total
        FROM accounts a
        -- Subquery untuk transaksi sederhana
        LEFT JOIN (
            SELECT account_id, SUM(jumlah) as total
            FROM transaksi
            WHERE user_id = ? AND jenis = 'pengeluaran' AND tanggal BETWEEN ? AND ?
            GROUP BY account_id
        ) trx ON a.id = trx.account_id
        -- Subquery untuk jurnal majemuk
        LEFT JOIN (
            SELECT jd.account_id, SUM(jd.debit - jd.kredit) as total
            FROM jurnal_details jd
            JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
            WHERE je.user_id = ? AND je.tanggal BETWEEN ? AND ?
            GROUP BY jd.account_id
        ) jurnal ON a.id = jurnal.account_id
        WHERE a.user_id = ? AND a.tipe_akun = 'Beban'
        ORDER BY a.kode_akun ASC
    ");
    $stmt_beban->bind_param('ississi', $user_id, $tanggal_mulai, $tanggal_akhir, $user_id, $tanggal_mulai, $tanggal_akhir, $user_id);
    $stmt_beban->execute();
    $beban = $stmt_beban->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_beban->close();

    // 3. Hitung summary
    $total_pendapatan = array_sum(array_column($pendapatan, 'total'));
    $total_beban = array_sum(array_column($beban, 'total'));
    $laba_bersih = $total_pendapatan - $total_beban;

    $response = [
        'status' => 'success',
        'data' => [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'summary' => [
                'total_pendapatan' => $total_pendapatan,
                'total_beban' => $total_beban,
                'laba_bersih' => $laba_bersih
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>