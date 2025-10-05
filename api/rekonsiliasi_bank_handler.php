<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id']; // Menggunakan ID pengguna yang sedang login
$logged_in_user_id = $_SESSION['user_id'];

try {
    $action = $_REQUEST['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'get_cash_accounts') {
            $stmt = $conn->prepare("SELECT id, nama_akun FROM accounts WHERE user_id = ? AND is_kas = 1 ORDER BY nama_akun");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $accounts]);
        } 
        elseif ($action === 'get_transactions') {
            $account_id = (int)($_GET['account_id'] ?? 0);
            $end_date = $_GET['end_date'] ?? date('Y-m-d');

            if ($account_id <= 0) {
                throw new Exception("Akun bank harus dipilih.");
            }

            // Ambil saldo awal akun dari tabel 'accounts' (lebih aman dengan user_id)
            $stmt_saldo = $conn->prepare("SELECT saldo_awal FROM accounts WHERE id = ? AND user_id = ?");
            $stmt_saldo->bind_param('ii', $account_id, $user_id);
            $stmt_saldo->execute();
            $account_data = $stmt_saldo->get_result()->fetch_assoc();
            if (!$account_data) {
                throw new Exception("Akun dengan ID {$account_id} tidak ditemukan atau Anda tidak memiliki akses.");
            }
            $saldo_awal_akun = (float)$account_data['saldo_awal'];
            $stmt_saldo->close();

            // Hitung total mutasi dari semua transaksi yang SUDAH direkonsiliasi
            $stmt_reconciled_mutation = $conn->prepare("
                SELECT COALESCE(SUM(debit - kredit), 0) as total_mutasi_reconciled
                FROM general_ledger 
                WHERE user_id = ? AND account_id = ? AND is_reconciled = 1
            ");
            $stmt_reconciled_mutation->bind_param('ii', $user_id, $account_id);
            $stmt_reconciled_mutation->execute();
            $total_mutasi_reconciled = (float)$stmt_reconciled_mutation->get_result()->fetch_assoc()['total_mutasi_reconciled'];
            $stmt_reconciled_mutation->close();
            
            // Saldo awal untuk proses rekonsiliasi adalah saldo awal akun + total mutasi yang sudah direkonsiliasi
            $saldo_buku_awal = $saldo_awal_akun + $total_mutasi_reconciled;

            // Ambil transaksi yang belum direkonsiliasi hingga tanggal akhir
            $stmt = $conn->prepare("
                SELECT id, tanggal, keterangan, debit, kredit, is_reconciled 
                FROM general_ledger 
                WHERE user_id = ? AND account_id = ? AND tanggal <= ? AND is_reconciled = 0
                ORDER BY tanggal ASC, id ASC
            ");
            $stmt->bind_param('iis', $user_id, $account_id, $end_date);
            $stmt->execute();
            $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode([
                'status' => 'success', 
                'data' => $transactions,
                'saldo_buku_awal' => $saldo_buku_awal
            ]);
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'save') {
            $account_id = (int)($_POST['account_id'] ?? 0);
            $reconciliation_date = $_POST['reconciliation_date'] ?? date('Y-m-d');
            $statement_balance = (float)($_POST['statement_balance'] ?? 0);
            $cleared_ids = $_POST['cleared_ids'] ?? [];

            if ($account_id <= 0 || empty($cleared_ids)) {
                throw new Exception("Data tidak lengkap untuk menyimpan rekonsiliasi.");
            }

            $conn->begin_transaction();

            // Hitung cleared_balance dari sisi server untuk keamanan
            $ids_placeholder = implode(',', array_fill(0, count($cleared_ids), '?'));
            $stmt_sum = $conn->prepare("SELECT COALESCE(SUM(debit - kredit), 0) as total_cleared FROM general_ledger WHERE id IN ($ids_placeholder) AND user_id = ?");
            $types_sum = str_repeat('i', count($cleared_ids)) . 'i';
            $params_sum = array_merge($cleared_ids, [$user_id]);
            $stmt_sum->bind_param($types_sum, ...$params_sum);
            $stmt_sum->execute();
            $total_cleared = (float)$stmt_sum->get_result()->fetch_assoc()['total_cleared'];
            $stmt_sum->close();

            // Hitung saldo awal rekonsiliasi dari sisi server untuk menghitung selisih
            $stmt_saldo_awal_akun = $conn->prepare("SELECT saldo_awal FROM accounts WHERE id = ? AND user_id = ?");
            $stmt_saldo_awal_akun->bind_param('ii', $account_id, $user_id);
            $stmt_saldo_awal_akun->execute();
            $saldo_awal_akun = (float)$stmt_saldo_awal_akun->get_result()->fetch_assoc()['saldo_awal'];
            $stmt_saldo_awal_akun->close();

            $stmt_reconciled_mutation = $conn->prepare("SELECT COALESCE(SUM(debit - kredit), 0) as total_mutasi_reconciled FROM general_ledger WHERE user_id = ? AND account_id = ? AND is_reconciled = 1 AND reconciliation_id IS NOT NULL");
            $stmt_reconciled_mutation->bind_param('ii', $user_id, $account_id);
            $stmt_reconciled_mutation->execute();
            $total_mutasi_reconciled = (float)$stmt_reconciled_mutation->get_result()->fetch_assoc()['total_mutasi_reconciled'];
            $stmt_reconciled_mutation->close();
            $saldo_buku_awal = $saldo_awal_akun + $total_mutasi_reconciled;
            $difference = ($saldo_buku_awal + $total_cleared) - $statement_balance;

            // Simpan header rekonsiliasi
            $stmt_header = $conn->prepare("INSERT INTO reconciliations (user_id, account_id, statement_date, statement_balance, cleared_balance, difference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_header->bind_param('iisdddi', $user_id, $account_id, $reconciliation_date, $statement_balance, $total_cleared, $difference, $logged_in_user_id);
            $stmt_header->execute();
            $reconciliation_id = $conn->insert_id;
            $stmt_header->close();

            // Update status transaksi yang dicocokkan
            $stmt = $conn->prepare("
                UPDATE general_ledger 
                SET is_reconciled = 1, reconciliation_date = ?, reconciliation_id = ?
                WHERE id IN ($ids_placeholder) AND user_id = ? AND account_id = ?
            ");
            
            $types = 'si' . str_repeat('i', count($cleared_ids)) . 'ii';
            $params = array_merge([$reconciliation_date, $reconciliation_id], $cleared_ids, [$user_id, $account_id]);
            
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();

            $conn->commit();
            log_activity($_SESSION['username'], 'Simpan Rekonsiliasi', "Menyimpan rekonsiliasi #{$reconciliation_id} untuk akun ID {$account_id} sebanyak {$affected_rows} transaksi.");
            echo json_encode(['status' => 'success', 'message' => "$affected_rows transaksi berhasil direkonsiliasi."]);
        }
    }

} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'in_transaction') && $conn->in_transaction()) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
