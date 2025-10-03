<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $conn = Database::getInstance()->getConnection();
    $user_id = 1; // Semua user mengakses data yang sama

    // 1. Dapatkan ID akun Laba Ditahan dari pengaturan
    $retained_earnings_acc_id = (int)get_setting('retained_earnings_account_id', 0, $conn);
    if ($retained_earnings_acc_id === 0) {
        throw new Exception("Akun Laba Ditahan (Retained Earnings) belum diatur di Pengaturan.");
    }

    $start_date = $_GET['start_date'] ?? date('Y-01-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    // 2. Gunakan fungsi yang sama dengan Buku Besar untuk mengambil data
    // Dapatkan info akun
    $stmt_acc = $conn->prepare("SELECT kode_akun, nama_akun, saldo_awal, saldo_normal FROM accounts WHERE id = ? AND user_id = ?");
    $stmt_acc->bind_param('ii', $retained_earnings_acc_id, $user_id);
    $stmt_acc->execute();
    $account_info = $stmt_acc->get_result()->fetch_assoc();
    if (!$account_info) throw new Exception("Akun Laba Ditahan tidak ditemukan di database.");
    $stmt_acc->close();

    // Hitung saldo awal pada `start_date`
    $date_before_start = date('Y-m-d', strtotime($start_date . ' -1 day'));
    $saldo_awal = get_account_balance_on_date($conn, $user_id, $retained_earnings_acc_id, $date_before_start);

    // Ambil semua transaksi yang relevan dalam rentang tanggal dari general_ledger
    $stmt_transaksi = $conn->prepare("SELECT tanggal, keterangan, nomor_referensi, debit, kredit FROM general_ledger WHERE user_id = ? AND account_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
    $stmt_transaksi->bind_param('iiss', $user_id, $retained_earnings_acc_id, $start_date, $end_date);
    $stmt_transaksi->execute();
    $transactions = $stmt_transaksi->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_transaksi->close();

    echo json_encode(['status' => 'success', 'data' => compact('account_info', 'saldo_awal', 'transactions')]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>