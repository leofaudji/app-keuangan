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

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

try {
    $response_data = [];

    // 1. Total Saldo Kas (fungsi sudah ada di functions.php)
    $response_data['total_saldo'] = get_cash_balance_on_date($conn, $user_id, date('Y-m-d'));

    // 2. Pemasukan & Pengeluaran Bulan Ini
    // Dari transaksi sederhana
    $stmt_trx = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END), 0) as pemasukan,
            COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as pengeluaran
        FROM transaksi 
        WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ?
    ");
    $stmt_trx->bind_param('iii', $user_id, $tahun, $bulan);
    $stmt_trx->execute();
    $trx_monthly = $stmt_trx->get_result()->fetch_assoc();
    $stmt_trx->close();

    // Dari jurnal majemuk
    $stmt_jurnal = $conn->prepare("
        SELECT 
            a.tipe_akun, SUM(jd.debit) as total_debit, SUM(jd.kredit) as total_kredit
        FROM jurnal_details jd
        JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
        JOIN accounts a ON jd.account_id = a.id
        WHERE je.user_id = ? AND YEAR(je.tanggal) = ? AND MONTH(je.tanggal) = ?
        GROUP BY a.tipe_akun
    ");
    $stmt_jurnal->bind_param('iii', $user_id, $tahun, $bulan);
    $stmt_jurnal->execute();
    $jurnal_monthly = $stmt_jurnal->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_jurnal->close();

    $pemasukan_jurnal = 0;
    $pengeluaran_jurnal = 0;
    foreach ($jurnal_monthly as $item) {
        if ($item['tipe_akun'] === 'Pendapatan') {
            $pemasukan_jurnal += $item['total_kredit'] - $item['total_debit'];
        } elseif ($item['tipe_akun'] === 'Beban') {
            $pengeluaran_jurnal += $item['total_debit'] - $item['total_kredit'];
        }
    }

    $response_data['pemasukan_bulan_ini'] = (float)$trx_monthly['pemasukan'] + $pemasukan_jurnal;
    $response_data['pengeluaran_bulan_ini'] = (float)$trx_monthly['pengeluaran'] + $pengeluaran_jurnal;
    $response_data['laba_rugi_bulan_ini'] = $response_data['pemasukan_bulan_ini'] - $response_data['pengeluaran_bulan_ini'];

    // 3. Transaksi Terbaru (5 terakhir)
    $stmt_recent = $conn->prepare("
        SELECT tanggal, keterangan, jenis, jumlah 
        FROM transaksi 
        WHERE user_id = ? 
        ORDER BY tanggal DESC, id DESC 
        LIMIT 5
    ");
    $stmt_recent->bind_param('i', $user_id);
    $stmt_recent->execute();
    $response_data['transaksi_terbaru'] = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_recent->close();

    // 4. Pengeluaran per Kategori (Beban)
    $stmt_expense_cat = $conn->prepare("
        SELECT a.nama_akun as kategori, SUM(t.jumlah) as total
        FROM transaksi t
        JOIN accounts a ON t.account_id = a.id
        WHERE t.user_id = ? AND YEAR(t.tanggal) = ? AND MONTH(t.tanggal) = ? AND t.jenis = 'pengeluaran'
        GROUP BY a.id
        
        UNION ALL

        SELECT a.nama_akun as kategori, SUM(jd.debit - jd.kredit) as total
        FROM jurnal_details jd
        JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
        JOIN accounts a ON jd.account_id = a.id
        WHERE je.user_id = ? AND YEAR(je.tanggal) = ? AND MONTH(je.tanggal) = ? AND a.tipe_akun = 'Beban'
        GROUP BY a.id
    ");
    $stmt_expense_cat->bind_param('iiiiii', $user_id, $tahun, $bulan, $user_id, $tahun, $bulan);
    $stmt_expense_cat->execute();
    $expenses = $stmt_expense_cat->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_expense_cat->close();

    // Gabungkan hasil union
    $expense_summary = [];
    foreach ($expenses as $expense) {
        if (!isset($expense_summary[$expense['kategori']])) {
            $expense_summary[$expense['kategori']] = 0;
        }
        $expense_summary[$expense['kategori']] += (float)$expense['total'];
    }

    $response_data['pengeluaran_per_kategori'] = [
        'labels' => array_keys($expense_summary),
        'data' => array_values($expense_summary)
    ];

    echo json_encode(['status' => 'success', 'data' => $response_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>