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

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // 1. Saldo Awal Hari
    $tanggal_sebelumnya = date('Y-m-d', strtotime($tanggal . ' -1 day'));
    $saldo_awal = get_cash_balance_on_date($conn, $user_id, $tanggal_sebelumnya);

    // 2. Ambil semua transaksi sederhana pada hari itu
    $stmt_trx = $conn->prepare("
        SELECT 
            'transaksi' as source,
            t.id,
            t.jenis,
            t.keterangan,
            t.jumlah,
            t.nomor_referensi,
            main_acc.nama_akun as akun_utama,
            kas_acc.nama_akun as akun_kas,
            tujuan_acc.nama_akun as akun_tujuan
        FROM transaksi t
        LEFT JOIN accounts main_acc ON t.account_id = main_acc.id
        LEFT JOIN accounts kas_acc ON t.kas_account_id = kas_acc.id
        LEFT JOIN accounts tujuan_acc ON t.kas_tujuan_account_id = tujuan_acc.id
        WHERE t.user_id = ? AND t.tanggal = ?
    ");
    $stmt_trx->bind_param('is', $user_id, $tanggal);
    $stmt_trx->execute();
    $transaksi_sederhana = $stmt_trx->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_trx->close();

    // 3. Ambil semua jurnal umum pada hari itu
    $stmt_jurnal = $conn->prepare("
        SELECT 
            'jurnal' as source,
            je.id,
            je.keterangan,
            SUM(jd.debit) as jumlah
        FROM jurnal_entries je
        JOIN jurnal_details jd ON je.id = jd.jurnal_entry_id
        WHERE je.user_id = ? AND je.tanggal = ?
        GROUP BY je.id, je.keterangan
    ");
    $stmt_jurnal->bind_param('is', $user_id, $tanggal);
    $stmt_jurnal->execute();
    $jurnal_umum = $stmt_jurnal->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_jurnal->close();

    $all_transactions = array_merge($transaksi_sederhana, $jurnal_umum);

    // 4. Hitung total pemasukan dan pengeluaran hari itu
    $total_pemasukan = 0;
    $total_pengeluaran = 0;

    foreach ($transaksi_sederhana as $tx) {
        if ($tx['jenis'] === 'pemasukan') {
            $total_pemasukan += (float)$tx['jumlah'];
        } elseif ($tx['jenis'] === 'pengeluaran') {
            $total_pengeluaran += (float)$tx['jumlah'];
        }
    }

    // Tambahkan mutasi kas dari jurnal umum
    $stmt_jurnal_kas = $conn->prepare("
        SELECT COALESCE(SUM(jd.debit - jd.kredit), 0) as mutasi_kas
        FROM jurnal_details jd
        JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
        JOIN accounts a ON jd.account_id = a.id
        WHERE je.user_id = ? AND je.tanggal = ? AND a.is_kas = 1
    ");
    $stmt_jurnal_kas->bind_param('is', $user_id, $tanggal);
    $stmt_jurnal_kas->execute();
    $mutasi_kas_jurnal = (float)$stmt_jurnal_kas->get_result()->fetch_assoc()['mutasi_kas'];
    $stmt_jurnal_kas->close();

    if ($mutasi_kas_jurnal > 0) $total_pemasukan += $mutasi_kas_jurnal;
    if ($mutasi_kas_jurnal < 0) $total_pengeluaran += abs($mutasi_kas_jurnal);

    $saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

    echo json_encode([
        'status' => 'success',
        'data' => [
            'saldo_awal' => $saldo_awal,
            'transaksi' => $all_transactions,
            'total_pemasukan' => $total_pemasukan,
            'total_pengeluaran' => $total_pengeluaran,
            'saldo_akhir' => $saldo_akhir
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>