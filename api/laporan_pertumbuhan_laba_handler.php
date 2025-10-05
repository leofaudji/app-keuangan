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

$tahun = (int)($_GET['tahun'] ?? date('Y'));
$view_mode = $_GET['view_mode'] ?? 'monthly';
$compare = isset($_GET['compare']) && $_GET['compare'] === 'true';

try {
    $repo = new LaporanRepository($conn);
    $result = $repo->getPertumbuhanLabaData($user_id, $tahun, $view_mode, $compare);

    $report_data = [];
    $laba_bersih_sebelumnya = 0;

    foreach ($result as $row) {
        $laba_bersih = (float)$row['laba_bersih'];

        // Hitung pertumbuhan
        $pertumbuhan = 0;
        if ($laba_bersih_sebelumnya != 0) {
            $pertumbuhan = (($laba_bersih - $laba_bersih_sebelumnya) / abs($laba_bersih_sebelumnya)) * 100;
        } elseif ($laba_bersih != 0) {
            $pertumbuhan = 100.0; // Anggap pertumbuhan 100% jika sebelumnya 0
        }

        $pertumbuhan_yoy = 0;
        if ($row['laba_bersih_lalu'] != 0) {
            $pertumbuhan_yoy = (($laba_bersih - (float)$row['laba_bersih_lalu']) / abs((float)$row['laba_bersih_lalu'])) * 100;
        }

        $row['pertumbuhan'] = $pertumbuhan;
        $row['pertumbuhan_yoy'] = $pertumbuhan_yoy;
        $report_data[] = $row;

        $laba_bersih_sebelumnya = $laba_bersih;
    }

    echo json_encode(['status' => 'success', 'data' => $report_data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>