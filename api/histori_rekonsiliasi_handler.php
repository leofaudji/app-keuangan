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
        $stmt = $conn->prepare("
            SELECT r.id, r.statement_date, r.statement_balance, r.created_at, a.nama_akun
            FROM reconciliations r
            JOIN accounts a ON r.account_id = a.id
            WHERE r.user_id = ?
            ORDER BY r.statement_date DESC, r.id DESC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['status' => 'success', 'data' => $history]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'reverse') {
            $recon_id = (int)($_POST['id'] ?? 0);
            if ($recon_id <= 0) {
                throw new Exception("ID Rekonsiliasi tidak valid.");
            }

            $conn->begin_transaction();

            // 1. Reset status di general_ledger
            $stmt_gl = $conn->prepare("UPDATE general_ledger SET is_reconciled = 0, reconciliation_date = NULL, reconciliation_id = NULL WHERE reconciliation_id = ? AND user_id = ?");
            $stmt_gl->bind_param('ii', $recon_id, $user_id);
            $stmt_gl->execute();
            $affected_rows = $stmt_gl->affected_rows;
            $stmt_gl->close();

            // 2. Hapus header rekonsiliasi
            $stmt_recon = $conn->prepare("DELETE FROM reconciliations WHERE id = ? AND user_id = ?");
            $stmt_recon->bind_param('ii', $recon_id, $user_id);
            $stmt_recon->execute();
            $stmt_recon->close();

            $conn->commit();

            log_activity($_SESSION['username'], 'Batal Rekonsiliasi', "Rekonsiliasi ID {$recon_id} dibatalkan, {$affected_rows} transaksi dikembalikan.");
            echo json_encode(['status' => 'success', 'message' => 'Rekonsiliasi berhasil dibatalkan.']);
        } else {
            throw new Exception("Aksi tidak valid.");
        }
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>