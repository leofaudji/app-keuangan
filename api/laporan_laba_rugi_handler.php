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

$tanggal_mulai = $_GET['start'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['end'] ?? date('Y-m-t');

// Parameter untuk perbandingan
$is_comparison = isset($_GET['compare']) && $_GET['compare'] === 'true';
$tanggal_mulai_2 = $_GET['start2'] ?? null;
$tanggal_akhir_2 = $_GET['end2'] ?? null;

try {
    function fetch_lr_data($conn, $user_id, $start, $end) {
        $repo = new LaporanRepository($conn);
        return $repo->getLabaRugiData($user_id, $start, $end);
    }

    $data_current = fetch_lr_data($conn, $user_id, $tanggal_mulai, $tanggal_akhir);
    $response_data = ['current' => $data_current];

    if ($is_comparison && $tanggal_mulai_2 && $tanggal_akhir_2) {
        $data_previous = fetch_lr_data($conn, $user_id, $tanggal_mulai_2, $tanggal_akhir_2);
        $response_data['previous'] = $data_previous;
    }

    echo json_encode(['status' => 'success', 'data' => $response_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}