<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$user_id = 1; // Semua user mengakses data yang sama

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Mengambil semua akun Neraca (Aset, Liabilitas, Ekuitas)
        $stmt = $conn->prepare("
            SELECT a.id, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_awal, a.saldo_normal
            FROM accounts a
            WHERE 
                a.user_id = ? 
                AND a.tipe_akun IN ('Aset', 'Liabilitas', 'Ekuitas')
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
            throw new Exception("Tidak ada data entri jurnal yang dikirim.");
        }

        $total_debit = 0;
        $total_kredit = 0;

        foreach ($entries as $entry) {
            $total_debit += (float)($entry['debit'] ?? 0);
            $total_kredit += (float)($entry['credit'] ?? 0);
        }

        // Validasi keseimbangan
        if (abs($total_debit - $total_kredit) > 0.001) { // Toleransi kecil untuk floating point
            throw new Exception("Jurnal tidak seimbang. Total Debit (Rp " . number_format($total_debit) . ") harus sama dengan Total Kredit (Rp " . number_format($total_kredit) . ").");
        }

        $conn->begin_transaction();

        // Reset semua saldo awal akun neraca ke 0 terlebih dahulu
        $conn->query("UPDATE accounts SET saldo_awal = 0 WHERE user_id = $user_id AND tipe_akun IN ('Aset', 'Liabilitas', 'Ekuitas')");

        $stmt = $conn->prepare("UPDATE accounts SET saldo_awal = ? WHERE id = ? AND user_id = ?");

        foreach ($entries as $entry) {
            // Tambahan: Pastikan hanya akun Neraca yang diproses untuk keamanan
            $check_stmt = $conn->prepare("SELECT tipe_akun FROM accounts WHERE id = ? AND user_id = ?");
            $check_stmt->bind_param('ii', $entry['account_id'], $user_id);
            $check_stmt->execute();
            $tipe_akun = $check_stmt->get_result()->fetch_assoc()['tipe_akun'];
            if (!in_array($tipe_akun, ['Aset', 'Liabilitas', 'Ekuitas'])) continue; // Lewati jika bukan akun neraca

            $account_id = (int)$entry['account_id'];
            $debit = (float)($entry['debit'] ?? 0);
            $credit = (float)($entry['credit'] ?? 0);

            // Saldo awal dihitung berdasarkan saldo normalnya
            if ($tipe_akun === 'Aset' || $tipe_akun === 'Beban') {
                $saldo_awal = $debit - $credit;
            } else { // Liabilitas, Ekuitas, Pendapatan
                $saldo_awal = $credit - $debit;
            }
            
            $stmt->bind_param('dii', $saldo_awal, $account_id, $user_id);
            $stmt->execute();
        }

        $stmt->close();
        $conn->commit();

        log_activity($_SESSION['username'], 'Set Saldo Awal Neraca', 'Saldo awal akun neraca telah diatur.');
        echo json_encode(['status' => 'success', 'message' => 'Saldo awal neraca berhasil disimpan.']);
    }
} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>