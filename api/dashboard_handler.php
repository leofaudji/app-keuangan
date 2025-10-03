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

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

try {
    $response_data = [];

    // 0. Ambil status keseimbangan Neraca untuk hari ini
    // Menggunakan fungsi baru yang aman dari includes/functions.php
    $response_data['balance_status'] = get_balance_sheet_status($conn, $user_id, date('Y-m-d'));

    // 1. Total Saldo Kas
    $response_data['total_saldo'] = get_cash_balance_on_date($conn, $user_id, date('Y-m-d'));

    // 2. Pemasukan, Pengeluaran, dan Kategori Beban Bulan Ini (digabung dalam 1 query)
    $start_of_month = date('Y-m-01', strtotime("$tahun-$bulan-01"));
    $end_of_month = date('Y-m-t', strtotime("$tahun-$bulan-01"));

    $stmt_monthly = $conn->prepare("
        SELECT 
            a.tipe_akun,
            a.nama_akun,
            SUM(CASE WHEN a.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE gl.debit - gl.kredit END) as total
        FROM general_ledger gl
        JOIN accounts a ON gl.account_id = a.id
        WHERE gl.user_id = ? AND gl.tanggal BETWEEN ? AND ?
          AND a.tipe_akun IN ('Pendapatan', 'Beban')
        GROUP BY a.tipe_akun, a.nama_akun
    ");
    $stmt_monthly->bind_param('iss', $user_id, $start_of_month, $end_of_month);
    $stmt_monthly->execute();
    $monthly_results = $stmt_monthly->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_monthly->close();

    $pemasukan_bulan_ini = 0;
    $pengeluaran_bulan_ini = 0;
    $expense_summary = [];
    foreach ($monthly_results as $row) {
        if ($row['tipe_akun'] === 'Pendapatan') {
            $pemasukan_bulan_ini += (float)$row['total'];
        } else { // Beban
            $pengeluaran_bulan_ini += (float)$row['total'];
            $expense_summary[$row['nama_akun']] = (float)$row['total'];
        }
    }

    $response_data['pemasukan_bulan_ini'] = $pemasukan_bulan_ini;
    $response_data['pengeluaran_bulan_ini'] = $pengeluaran_bulan_ini;
    $response_data['laba_rugi_bulan_ini'] = $response_data['pemasukan_bulan_ini'] - $response_data['pengeluaran_bulan_ini'];

    // 3. Transaksi Terbaru (5 terakhir)
    $stmt_recent = $conn->prepare("
        SELECT tanggal, keterangan, ref_type, ref_id, SUM(debit) as jumlah
        FROM general_ledger
        WHERE user_id = ?
        GROUP BY ref_type, ref_id, tanggal, keterangan
        ORDER BY tanggal DESC, ref_id DESC 
        LIMIT 5
    ");
    $stmt_recent->bind_param('i', $user_id);
    $stmt_recent->execute();
    $response_data['transaksi_terbaru'] = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_recent->close();
    
    $response_data['pengeluaran_per_kategori'] = [
        'labels' => array_keys($expense_summary),
        'data' => array_values($expense_summary)
    ];

    // 5. Data untuk Grafik Tren Laba/Rugi 30 Hari
    $end_date_30 = date('Y-m-d');
    $start_date_30 = date('Y-m-d', strtotime('-29 days')); // 30 hari termasuk hari ini

    $daily_profits = [];
    // Inisialisasi semua hari dengan laba 0
    $period = new DatePeriod(
        new DateTime($start_date_30),
        new DateInterval('P1D'),
        (new DateTime($end_date_30))->modify('+1 day')
    );
    foreach ($period as $date) {
        $daily_profits[$date->format('Y-m-d')] = 0;
    }

    // Ambil laba/rugi harian dari general_ledger
    $stmt_daily_profit = $conn->prepare("
        SELECT gl.tanggal, SUM(CASE WHEN a.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit WHEN a.tipe_akun = 'Beban' THEN gl.debit - gl.kredit ELSE 0 END) as profit
        FROM general_ledger gl
        JOIN accounts a ON gl.account_id = a.id
        WHERE gl.user_id = ? AND gl.tanggal BETWEEN ? AND ? AND a.tipe_akun IN ('Pendapatan', 'Beban')
        GROUP BY tanggal
    ");
    $stmt_daily_profit->bind_param('iss', $user_id, $start_date_30, $end_date_30);
    $stmt_daily_profit->execute();
    $daily_profit_result = $stmt_daily_profit->get_result();
    while ($row = $daily_profit_result->fetch_assoc()) {
        if (isset($daily_profits[$row['tanggal']])) {
            $daily_profits[$row['tanggal']] += (float)$row['profit'];
        }
    }
    $stmt_daily_profit->close();

    $response_data['laba_rugi_harian'] = [
        'labels' => array_keys($daily_profits),
        'data' => array_values($daily_profits)
    ];

    echo json_encode(['status' => 'success', 'data' => $response_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>