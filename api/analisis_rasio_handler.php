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

try {
    $date = $_GET['date'] ?? date('Y-m-d');
    $compare_date = $_GET['compare_date'] ?? null;

    $repo = new LaporanRepository($conn);
    $current_data = $repo->getFinancialSummaryData($user_id, $date);
    $previous_data = $compare_date ? $repo->getFinancialSummaryData($user_id, $compare_date) : null;

    // --- Hitung Rasio ---
    function calculateRatios(array $data): array
    {
        $ratios = [];
        $div = fn($a, $b) => ($b == 0) ? 0 : $a / $b;

        $ratios['profit_margin'] = $div($data['laba_bersih'], $data['total_pendapatan']);
        $ratios['debt_to_equity'] = $div($data['total_liabilitas'], $data['total_ekuitas']);
        $ratios['debt_to_asset'] = $div($data['total_liabilitas'], $data['total_aset']);
        $ratios['return_on_equity'] = $div($data['laba_bersih'], $data['total_ekuitas']);
        $ratios['return_on_assets'] = $div($data['laba_bersih'], $data['total_aset']);
        $ratios['asset_turnover'] = $div($data['total_pendapatan'], $data['total_aset']);

        return $ratios;
    }

    $current_ratios = calculateRatios($current_data);
    $previous_ratios = $previous_data ? calculateRatios($previous_data) : null;

    $response = [
        'current' => $current_ratios,
        'previous' => $previous_ratios,
    ];

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>