<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$user_id = 1; // Semua user mengakses data yang sama

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // 1. Saldo Awal Hari
    $tanggal_sebelumnya = date('Y-m-d', strtotime($tanggal . ' -1 day'));
    $saldo_awal = get_cash_balance_on_date($conn, $user_id, $tanggal_sebelumnya);

    // 2. Ambil semua entri unik (berdasarkan referensi) pada hari itu dari general_ledger
    $stmt_entries = $conn->prepare("
        SELECT 
            ref_type as source,
            ref_id as id,
            gl.nomor_referensi as ref,
            keterangan,
            tanggal,
            -- Ambil total pergerakan kas (cash flow) untuk entri ini
            SUM(CASE WHEN a.is_kas = 1 THEN gl.debit ELSE 0 END) as pemasukan,
            SUM(CASE WHEN a.is_kas = 1 THEN gl.kredit ELSE 0 END) as pengeluaran,
            (SELECT GROUP_CONCAT(acc.nama_akun SEPARATOR ', ') FROM general_ledger gl_inner JOIN accounts acc ON gl_inner.account_id = acc.id WHERE gl_inner.ref_id = gl.ref_id AND gl_inner.ref_type = gl.ref_type AND acc.is_kas = 0) as akun_terkait
        FROM general_ledger gl
        JOIN accounts a ON gl.account_id = a.id
        WHERE gl.user_id = ? AND gl.tanggal = ?
        GROUP BY source, id, ref, keterangan, tanggal
        ORDER BY gl.created_at ASC
    ");
    $stmt_entries->bind_param('is', $user_id, $tanggal);
    $stmt_entries->execute();
    $all_transactions = $stmt_entries->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_entries->close();

    // 4. Hitung total pemasukan dan pengeluaran hari itu
    // Ini adalah cara yang lebih akurat: jumlahkan semua pergerakan di akun kas
    $stmt_jurnal_kas = $conn->prepare("
        SELECT 
            COALESCE(SUM(gl.debit), 0) as total_pemasukan,
            COALESCE(SUM(gl.kredit), 0) as total_pengeluaran
        FROM general_ledger gl
        JOIN accounts a ON gl.account_id = a.id
        WHERE gl.user_id = ? AND gl.tanggal = ? AND a.is_kas = 1
    ");
    $stmt_jurnal_kas->bind_param('is', $user_id, $tanggal);
    $stmt_jurnal_kas->execute();
    $mutasi_kas = $stmt_jurnal_kas->get_result()->fetch_assoc();
    $stmt_jurnal_kas->close();

    $total_pemasukan = (float)$mutasi_kas['total_pemasukan'];
    $total_pengeluaran = (float)$mutasi_kas['total_pengeluaran'];

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