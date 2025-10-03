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

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

try {
    // --- Perhitungan Arus Kas ---

    // Saldo Kas Awal Periode
    $beginning_date = date('Y-m-d', strtotime($start_date . ' -1 day'));
    $saldo_kas_awal = get_cash_balance_on_date($conn, $user_id, $beginning_date);

    // Saldo Kas Akhir Periode
    $saldo_kas_akhir = get_cash_balance_on_date($conn, $user_id, $end_date);

    // Kelompokkan arus kas
    $arus_kas_operasi = ['total' => 0, 'details' => []];
    $arus_kas_investasi = ['total' => 0, 'details' => []];
    $arus_kas_pendanaan = ['total' => 0, 'details' => []];

    // Helper untuk menambahkan detail
    $add_detail = function(&$details, $key, $amount) { if (!isset($details[$key])) { $details[$key] = 0; } $details[$key] += $amount; };

    // Ambil semua pergerakan kas dan akun lawannya dari general_ledger
    $stmt = $conn->prepare("
        SELECT 
            non_cash.nama_akun,
            non_cash.cash_flow_category,
            SUM(cash.debit - cash.kredit) as cash_mutation
        FROM general_ledger cash
        JOIN accounts cash_acc ON cash.account_id = cash_acc.id
        JOIN general_ledger non_cash_gl ON cash.ref_id = non_cash_gl.ref_id AND cash.ref_type = non_cash_gl.ref_type
        JOIN accounts non_cash ON non_cash_gl.account_id = non_cash.id
        WHERE cash.user_id = ? 
          AND cash.tanggal BETWEEN ? AND ?
          AND cash_acc.is_kas = 1
          AND non_cash.is_kas = 0
        GROUP BY non_cash.nama_akun, non_cash.cash_flow_category
    ");
    $stmt->bind_param('iss', $user_id, $start_date, $end_date);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($results as $row) {
        $jumlah = (float)$row['cash_mutation'];
        $akun_lawan = $row['nama_akun'];
        $category = $row['cash_flow_category'] ?? 'Operasi'; // Default ke Operasi

        if ($category === 'Investasi') {
            $arus_kas_investasi['total'] += $jumlah;
            $add_detail($arus_kas_investasi['details'], $akun_lawan, $jumlah);
        } elseif ($category === 'Pendanaan') {
            $arus_kas_pendanaan['total'] += $jumlah;
            $add_detail($arus_kas_pendanaan['details'], $akun_lawan, $jumlah);
        } else { // Operasi
            $arus_kas_operasi['total'] += $jumlah;
            $add_detail($arus_kas_operasi['details'], $akun_lawan, $jumlah);
        }
    }

    $kenaikan_penurunan_kas = $arus_kas_operasi['total'] + $arus_kas_investasi['total'] + $arus_kas_pendanaan['total'];

    $response = [
        'status' => 'success',
        'data' => [
            'arus_kas_operasi' => $arus_kas_operasi,
            'arus_kas_investasi' => $arus_kas_investasi,
            'arus_kas_pendanaan' => $arus_kas_pendanaan,
            'kenaikan_penurunan_kas' => $kenaikan_penurunan_kas,
            'saldo_kas_awal' => $saldo_kas_awal,
            'saldo_kas_akhir' => $saldo_kas_akhir,
            // Saldo akhir terhitung untuk verifikasi
            'saldo_kas_akhir_terhitung' => $saldo_kas_awal + $kenaikan_penurunan_kas
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>