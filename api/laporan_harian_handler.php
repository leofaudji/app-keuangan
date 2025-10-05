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

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // Gunakan Repository untuk mengambil data, bukan query langsung.
    // Ini memastikan konsistensi data antara API dan PDF.
    $repo = new LaporanRepository($conn);
    $data = $repo->getLaporanHarianData($user_id, $tanggal);

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>