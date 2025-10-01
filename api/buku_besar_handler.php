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

try {
    $action = $_GET['action'] ?? 'get_report';

    if ($action === 'get_accounts') {
        $stmt = $conn->prepare("SELECT id, kode_akun, nama_akun FROM accounts WHERE user_id = ? ORDER BY kode_akun ASC");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $accounts]);
        exit;
    }

    // Default action: get_report
    $account_id = (int)($_GET['account_id'] ?? 0);
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');

    if ($account_id === 0) {
        throw new Exception("Silakan pilih akun terlebih dahulu.");
    }

    // 1. Dapatkan info akun dan saldo awal dari tabel accounts
    $stmt_acc = $conn->prepare("SELECT nama_akun, saldo_awal, saldo_normal FROM accounts WHERE id = ? AND user_id = ?");
    $stmt_acc->bind_param('ii', $account_id, $user_id);
    $stmt_acc->execute();
    $account_info = $stmt_acc->get_result()->fetch_assoc();
    if (!$account_info) throw new Exception("Akun tidak ditemukan.");
    $stmt_acc->close();

    // 2. Hitung saldo awal pada `start_date`
    // Saldo awal = saldo_awal dari tabel + total mutasi dari awal waktu s/d (start_date - 1 hari)
    $stmt_mutasi_awal = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN (t.jenis = 'pemasukan' AND t.kas_account_id = ?) OR (t.jenis = 'transfer' AND t.kas_tujuan_account_id = ?) THEN t.jumlah ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN (t.jenis = 'pengeluaran' AND t.kas_account_id = ?) OR (t.jenis = 'transfer' AND t.kas_account_id = ?) THEN t.jumlah ELSE 0 END), 0) as total_kredit
        FROM transaksi t
        WHERE t.user_id = ? AND t.tanggal < ? AND (t.kas_account_id = ? OR t.kas_tujuan_account_id = ?)
    ");
    $stmt_mutasi_awal->bind_param('iiiisiii', $account_id, $account_id, $account_id, $account_id, $user_id, $start_date, $account_id, $account_id);
    $stmt_mutasi_awal->execute();
    $mutasi_awal = $stmt_mutasi_awal->get_result()->fetch_assoc();
    $stmt_mutasi_awal->close();

    $saldo_awal_periode = (float)$account_info['saldo_awal'] + (float)$mutasi_awal['total_debit'] - (float)$mutasi_awal['total_kredit'];

    // 3. Ambil semua transaksi yang relevan dalam rentang tanggal
    $query = "
        -- Pemasukan ke akun ini
        SELECT t.tanggal, t.keterangan, t.id as ref, t.jumlah as debit, 0 as kredit
        FROM transaksi t
        WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ? AND t.kas_account_id = ? AND t.jenis = 'pemasukan'

        UNION ALL

        -- Pengeluaran dari akun ini
        SELECT t.tanggal, t.keterangan, t.id as ref, 0 as debit, t.jumlah as kredit
        FROM transaksi t
        WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ? AND t.kas_account_id = ? AND t.jenis = 'pengeluaran'

        UNION ALL

        -- Transfer keluar dari akun ini
        SELECT t.tanggal, t.keterangan, t.id as ref, 0 as debit, t.jumlah as kredit
        FROM transaksi t
        WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ? AND t.kas_account_id = ? AND t.jenis = 'transfer'

        UNION ALL

        -- Transfer masuk ke akun ini
        SELECT t.tanggal, t.keterangan, t.id as ref, t.jumlah as debit, 0 as kredit
        FROM transaksi t
        WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ? AND t.kas_tujuan_account_id = ? AND t.jenis = 'transfer'

        ORDER BY tanggal, ref ASC
    ";

    $stmt_transaksi = $conn->prepare($query);
    $stmt_transaksi->bind_param('issiisiiisiiisii', 
        $user_id, $start_date, $end_date, $account_id,
        $user_id, $start_date, $end_date, $account_id,
        $user_id, $start_date, $end_date, $account_id,
        $user_id, $start_date, $end_date, $account_id
    );
    $stmt_transaksi->execute();
    $transactions = $stmt_transaksi->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_transaksi->close();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'account_info' => $account_info,
            'saldo_awal' => $saldo_awal_periode,
            'transactions' => $transactions
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>