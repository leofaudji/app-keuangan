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
$per_tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // 1. Ambil semua akun beserta total mutasinya dari general_ledger
    $stmt = $conn->prepare("
        SELECT
            a.id, a.parent_id, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_normal, a.saldo_awal,
            COALESCE(SUM(
                CASE
                    WHEN a.saldo_normal = 'Debit' THEN gl.debit - gl.kredit
                    ELSE gl.kredit - gl.debit
                END
            ), 0) as mutasi
        FROM accounts a
        LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal <= ?
        WHERE a.user_id = ?
        GROUP BY a.id
        ORDER BY a.kode_akun ASC
    ");
    $stmt->bind_param('si', $per_tanggal, $user_id);
    $stmt->execute();
    $accounts_result = $stmt->get_result();
    $accounts = [];
    while ($row = $accounts_result->fetch_assoc()) {
        $row['saldo_akhir'] = (float)$row['saldo_awal'] + (float)$row['mutasi'];
        $accounts[] = $row;
    }
    $stmt->close();

    // 2. Hitung Laba Rugi Berjalan dari saldo akhir akun Pendapatan dan Beban
    $total_pendapatan = 0;
    $total_beban = 0;
    foreach ($accounts as $acc) {
        if ($acc['tipe_akun'] === 'Pendapatan') {
            $total_pendapatan += $acc['saldo_akhir'];
        } elseif ($acc['tipe_akun'] === 'Beban') {
            $total_beban += $acc['saldo_akhir'];
        }
    }
    $laba_rugi_berjalan = $total_pendapatan - $total_beban;

    // 3. Buat akun virtual untuk Laba Rugi Berjalan dan tambahkan ke Ekuitas
    $accounts['laba_rugi_virtual'] = [
        'id' => 'laba_rugi_virtual', 'parent_id' => 300, 'kode_akun' => '3-9999',
        'nama_akun' => 'Laba (Rugi) Periode Berjalan', 'tipe_akun' => 'Ekuitas',
        'saldo_akhir' => $laba_rugi_berjalan
    ];

    // 4. Filter hanya akun Neraca untuk dikirim
    $neraca_accounts = array_filter($accounts, function($acc) {
        return in_array($acc['tipe_akun'], ['Aset', 'Liabilitas', 'Ekuitas']);
    });

    echo json_encode(['status' => 'success', 'data' => array_values($neraca_accounts)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}