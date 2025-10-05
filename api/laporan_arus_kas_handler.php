<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/Repositories/LaporanRepository.php';

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
    // Gunakan Repository untuk mengambil data mentah
    $repo = new LaporanRepository($conn);
    $raw_data = $repo->getArusKasData($user_id, $start_date, $end_date);

    // Proses data mentah menjadi format yang siap dirender, sama seperti di ArusKasReportBuilder
    $arus_kas_operasi = ['total' => 0, 'details' => []];
    $arus_kas_investasi = ['total' => 0, 'details' => []];
    $arus_kas_pendanaan = ['total' => 0, 'details' => []];

    $add_detail = function(&$details, $key, $amount) {
        if (!isset($details[$key])) $details[$key] = 0;
        $details[$key] += $amount;
    };

    foreach ($raw_data['transactions'] as $row) {
        $jumlah = (float)$row['net_flow'];
        $akun_lawan = $row['nama_akun'];
        $category = $row['cash_flow_category'] ?? 'Operasi';

        if ($category === 'Investasi') {
            $arus_kas_investasi['total'] += $jumlah;
            $add_detail($arus_kas_investasi['details'], $akun_lawan, $jumlah);
        } elseif ($category === 'Pendanaan') {
            $arus_kas_pendanaan['total'] += $jumlah;
            $add_detail($arus_kas_pendanaan['details'], $akun_lawan, $jumlah);
        } else {
            $arus_kas_operasi['total'] += $jumlah;
            $add_detail($arus_kas_operasi['details'], $akun_lawan, $jumlah);
        }
    }

    $kenaikan_penurunan_kas = $arus_kas_operasi['total'] + $arus_kas_investasi['total'] + $arus_kas_pendanaan['total'];
    $saldo_kas_awal = $raw_data['saldo_kas_awal'];
    $saldo_kas_akhir_terhitung = $saldo_kas_awal + $kenaikan_penurunan_kas;

    $response = [
        'status' => 'success',
        'data' => [
            'arus_kas_operasi' => $arus_kas_operasi,
            'arus_kas_investasi' => $arus_kas_investasi,
            'arus_kas_pendanaan' => $arus_kas_pendanaan,
            'kenaikan_penurunan_kas' => $kenaikan_penurunan_kas,
            'saldo_kas_awal' => $saldo_kas_awal,
            'saldo_kas_akhir_terhitung' => $saldo_kas_akhir_terhitung
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>