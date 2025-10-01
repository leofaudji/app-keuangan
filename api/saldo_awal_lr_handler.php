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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Mengambil semua akun Laba Rugi (Pendapatan, Beban)
        $stmt = $conn->prepare("
            SELECT id, kode_akun, nama_akun, tipe_akun, saldo_awal
            FROM accounts
            WHERE 
                user_id = ? 
                AND tipe_akun IN ('Pendapatan', 'Beban')
            ORDER BY kode_akun ASC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['status' => 'success', 'data' => $accounts]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $entries = $_POST['entries'] ?? [];

        if (empty($entries)) {
            throw new Exception("Tidak ada data entri yang dikirim.");
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE accounts SET saldo_awal = ? WHERE id = ? AND user_id = ?");

        foreach ($entries as $entry) {
            $account_id = (int)$entry['account_id'];
            $saldo = (float)($entry['saldo'] ?? 0);
            
            $stmt->bind_param('dii', $saldo, $account_id, $user_id);
            $stmt->execute();
        }

        $stmt->close();
        $conn->commit();

        log_activity($_SESSION['username'], 'Set Saldo Awal L/R', 'Saldo awal akun laba rugi telah diatur.');
        echo json_encode(['status' => 'success', 'message' => 'Saldo awal laba rugi berhasil disimpan.']);
    }
} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>