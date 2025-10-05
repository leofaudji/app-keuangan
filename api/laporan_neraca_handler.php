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
$per_tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // Gunakan Repository untuk konsistensi data dengan PDF
    $repo = new LaporanRepository($conn);
    $neraca_accounts = $repo->getNeracaData($user_id, $per_tanggal);

    // Hitung laba rugi berjalan dari data laba rugi, sama seperti di ReportBuilder
    // Periode laba rugi dihitung dari awal tahun hingga tanggal neraca
    $start_of_year = date('Y-01-01', strtotime($per_tanggal));
    $laba_rugi_data = $repo->getLabaRugiData($user_id, $start_of_year, $per_tanggal);
    $laba_rugi_berjalan = $laba_rugi_data['summary']['laba_bersih'];

    // Tambahkan akun virtual untuk laba rugi berjalan ke dalam data neraca
    $neraca_accounts[] = [
        'id' => 'laba_rugi_virtual', 
        'parent_id' => null, 
        'kode_akun' => '3-9999',
        'nama_akun' => 'Laba (Rugi) Periode Berjalan', 'tipe_akun' => 'Ekuitas',
        'saldo_akhir' => $laba_rugi_berjalan
    ];

    echo json_encode(['status' => 'success', 'data' => array_values($neraca_accounts)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}