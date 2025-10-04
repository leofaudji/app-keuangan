<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$term = $_GET['term'] ?? '';

if (strlen($term) < 3) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$results = [];
$search_term = '%' . $term . '%';

try {
    // 1. Cari di General Ledger (mencakup Transaksi dan Jurnal Manual)
    $stmt_gl = $conn->prepare("
        SELECT ref_id, ref_type, tanggal, keterangan
        FROM general_ledger
        WHERE user_id = ? AND keterangan LIKE ?
        GROUP BY ref_id, ref_type, tanggal, keterangan
        ORDER BY tanggal DESC
        LIMIT 5
    ");
    $stmt_gl->bind_param('is', $user_id, $search_term);
    $stmt_gl->execute();
    $gl_results = $stmt_gl->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_gl->close();

    foreach ($gl_results as $item) {
        if ($item['ref_type'] === 'transaksi') {
            $results[] = [
                'link' => '/transaksi#tx-' . $item['ref_id'],
                'icon' => 'bi-arrow-down-up',
                'title' => $item['keterangan'],
                'subtitle' => 'Transaksi pada ' . date('d M Y', strtotime($item['tanggal'])),
                'type' => 'Transaksi'
            ];
        } else { // jurnal
            $results[] = [
                'link' => '/daftar-jurnal#JRN-' . $item['ref_id'],
                'icon' => 'bi-journal-text',
                'title' => $item['keterangan'],
                'subtitle' => 'Jurnal pada ' . date('d M Y', strtotime($item['tanggal'])),
                'type' => 'Jurnal'
            ];
        }
    }

    // 2. Cari di Bagan Akun (COA)
    $stmt_coa = $conn->prepare("
        SELECT id, kode_akun, nama_akun
        FROM accounts
        WHERE user_id = ? AND (nama_akun LIKE ? OR kode_akun LIKE ?)
        LIMIT 5
    ");
    $stmt_coa->bind_param('iss', $user_id, $search_term, $search_term);
    $stmt_coa->execute();
    $coa_results = $stmt_coa->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_coa->close();

    foreach ($coa_results as $item) {
        $results[] = [
            'link' => '/coa',
            'icon' => 'bi-journal-bookmark-fill',
            'title' => $item['nama_akun'],
            'subtitle' => 'Akun: ' . $item['kode_akun'],
            'type' => 'Bagan Akun'
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $results]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}