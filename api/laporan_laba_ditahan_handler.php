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
$user_id = $_SESSION['user_id'];

$start_date = $_GET['start_date'] ?? date('Y-01-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

try {
    // Gunakan Repository untuk mengambil data, memastikan konsistensi dengan PDF.
    $repo = new LaporanRepository($conn);
    $data = $repo->getLabaDitahanData($user_id, $start_date, $end_date);

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>