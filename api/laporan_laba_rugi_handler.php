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

$tanggal_mulai = $_GET['start'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['end'] ?? date('Y-m-t');

// Parameter untuk perbandingan
$is_comparison = isset($_GET['compare']) && $_GET['compare'] === 'true';
$tanggal_mulai_2 = $_GET['start2'] ?? null;
$tanggal_akhir_2 = $_GET['end2'] ?? null;

try {
    function fetch_lr_data($conn, $user_id, $start, $end) {
        $stmt = $conn->prepare("
            SELECT 
                a.id, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_awal,
                COALESCE(SUM(
                    CASE
                        WHEN a.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit
                        WHEN a.tipe_akun = 'Beban' THEN gl.debit - gl.kredit
                        ELSE 0
                    END
                ), 0) as mutasi
            FROM accounts a
            LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal BETWEEN ? AND ?
            WHERE a.user_id = ? AND a.tipe_akun IN ('Pendapatan', 'Beban')
            GROUP BY a.id, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_awal
            ORDER BY a.kode_akun ASC
        ");
        $stmt->bind_param('ssi', $start, $end, $user_id);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pendapatan = [];
        $beban = [];
        foreach ($accounts as &$acc) {
            $acc['total'] = (float)$acc['saldo_awal'] + (float)$acc['mutasi'];
            if ($acc['tipe_akun'] === 'Pendapatan') {
                $pendapatan[] = $acc;
            } else {
                $beban[] = $acc;
            }
        }

        $total_pendapatan = array_sum(array_column($pendapatan, 'total'));
        $total_beban = array_sum(array_column($beban, 'total'));
        $laba_bersih = $total_pendapatan - $total_beban;

        return [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'summary' => [
                'total_pendapatan' => $total_pendapatan,
                'total_beban' => $total_beban,
                'laba_bersih' => $laba_bersih
            ]
        ];
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