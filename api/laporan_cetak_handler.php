<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// Memuat file handler agar fungsinya tersedia
require_once PROJECT_ROOT . '/api/laporan_neraca_handler.php';
require_once PROJECT_ROOT . '/api/laporan_laba_rugi_handler.php';
require_once PROJECT_ROOT . '/api/laporan_arus_kas_handler.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

$conn = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

$report_type = $_GET['report'] ?? '';
$format = $_GET['format'] ?? 'pdf';

// Ambil nama perumahan dari settings
$settings_result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'app_name'");
$housing_name = $settings_result ? $settings_result->fetch_assoc()['setting_value'] : 'NAMA PERUMAHAN';

function outputCsv($filename, $data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
}

function generate_neraca_csv($conn, $user_id) {
    $per_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
    $data = get_neraca_data($conn, $user_id, $per_tanggal);
    if ($data['status'] !== 'success') die("Gagal mengambil data Neraca: " . ($data['message'] ?? 'Unknown error'));
    $csv_data = [['Tipe Akun', 'Kode Akun', 'Nama Akun', 'Saldo']];
    foreach ($data['data'] as $row) {
        $csv_data[] = [$row['tipe_akun'], $row['kode_akun'], $row['nama_akun'], $row['saldo_akhir']];
    }
    outputCsv('Laporan_Neraca_' . $per_tanggal . '.csv', $csv_data);
}

function generate_laba_rugi_csv($conn, $user_id) {
    $start_date = $_GET['start'] ?? date('Y-m-01');
    $end_date = $_GET['end'] ?? date('Y-m-t');
    $data = get_laba_rugi_data($conn, $user_id, $start_date, $end_date);
    if ($data['status'] !== 'success') die("Gagal mengambil data Laba Rugi: " . ($data['message'] ?? 'Unknown error'));
    $csv_data = [['Tipe', 'Kode Akun', 'Nama Akun', 'Total']];
    foreach ($data['data']['pendapatan'] as $row) { $csv_data[] = ['Pendapatan', $row['kode_akun'], $row['nama_akun'], $row['total']]; }
    $csv_data[] = ['','','TOTAL PENDAPATAN', $data['data']['summary']['total_pendapatan']];
    $csv_data[] = []; // Baris kosong
    foreach ($data['data']['beban'] as $row) { $csv_data[] = ['Beban', $row['kode_akun'], $row['nama_akun'], $row['total']]; }
    $csv_data[] = ['','','TOTAL BEBAN', $data['data']['summary']['total_beban']];
    $csv_data[] = []; // Baris kosong
    $csv_data[] = ['','','LABA (RUGI) BERSIH', $data['data']['summary']['laba_bersih']];
    outputCsv('Laporan_Laba_Rugi_' . $start_date . '_sd_' . $end_date . '.csv', $csv_data);
}

function generate_arus_kas_csv($conn, $user_id) {
    $start_date = $_GET['start'] ?? date('Y-m-01');
    $end_date = $_GET['end'] ?? date('Y-m-t');
    $data = get_arus_kas_data($conn, $user_id, $start_date, $end_date);
    if ($data['status'] !== 'success') die("Gagal mengambil data Arus Kas: " . ($data['message'] ?? 'Unknown error'));
    $d = $data['data'];
    $csv_data = [
        ['Deskripsi', 'Jumlah'],
        ['ARUS KAS DARI AKTIVITAS OPERASI', ''],
    ];
    foreach($d['arus_kas_operasi']['details'] as $key => $val) { $csv_data[] = [$key, $val]; }
    $csv_data[] = ['Total Arus Kas Operasi', $d['arus_kas_operasi']['total']];
    $csv_data[] = [];
    $csv_data[] = ['ARUS KAS DARI AKTIVITAS INVESTASI', ''];
    foreach($d['arus_kas_investasi']['details'] as $key => $val) { $csv_data[] = [$key, $val]; }
    $csv_data[] = ['Total Arus Kas Investasi', $d['arus_kas_investasi']['total']];
    $csv_data[] = [];
    $csv_data[] = ['ARUS KAS DARI AKTIVITAS PENDANAAN', ''];
    foreach($d['arus_kas_pendanaan']['details'] as $key => $val) { $csv_data[] = [$key, $val]; }
    $csv_data[] = ['Total Arus Kas Pendanaan', $d['arus_kas_pendanaan']['total']];
    $csv_data[] = [];
    $csv_data[] = ['Kenaikan (Penurunan) Bersih Kas', $d['kenaikan_penurunan_kas']];
    $csv_data[] = ['Saldo Kas pada Awal Periode', $d['saldo_kas_awal']];
    $csv_data[] = ['Saldo Kas pada Akhir Periode', $d['saldo_kas_akhir_terhitung']];
    outputCsv('Laporan_Arus_Kas_' . $start_date . '_sd_' . $end_date . '.csv', $csv_data);
}

switch ($report_type) {
    case 'neraca':
        if ($format === 'csv') generate_neraca_csv($conn, $user_id);
        break;
    case 'laba-rugi':
        if ($format === 'csv') generate_laba_rugi_csv($conn, $user_id);
        break;
    case 'arus-kas':
        if ($format === 'csv') generate_arus_kas_csv($conn, $user_id);
        break;
    default:
        http_response_code(400);
        die('Tipe laporan atau format tidak valid.');
}
?>