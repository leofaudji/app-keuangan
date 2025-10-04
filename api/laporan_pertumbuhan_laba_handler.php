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
    $tahun = (int)($_GET['tahun'] ?? date('Y'));
    $view_mode = $_GET['view_mode'] ?? 'monthly'; // 'monthly', 'quarterly', 'yearly', or 'cumulative'
    $compare = isset($_GET['compare']) && $_GET['compare'] === 'true';
    $tahun_lalu = $tahun - 1;

    // Mode kumulatif didasarkan pada data bulanan
    $is_cumulative = $view_mode === 'cumulative';
    
    if ($view_mode === 'quarterly') {
        $period_field = 'QUARTER(gl.tanggal)';
        $period_alias = 'triwulan';
        $period_count = 4;
    } elseif ($view_mode === 'yearly') {
        $period_field = 'YEAR(gl.tanggal)';
        $period_alias = 'tahun';
        $period_count = 5; // Tampilkan 5 tahun terakhir
    } else { // monthly or cumulative
        $period_field = 'MONTH(gl.tanggal)';
        $period_alias = 'bulan';
        $period_count = 12;
    }

    // Generate derived table for periods
    $period_table_parts = [];
    for ($i = 0; $i < $period_count; $i++) {
        $p_val = ($view_mode === 'yearly') ? ($tahun - ($period_count - 1) + $i) : ($i + 1);
        $period_table_parts[] = "SELECT $p_val as period";
    }
    $period_table = '(' . implode(' UNION ', $period_table_parts) . ') as p';

    // Tentukan rentang tahun yang akan di-query
    $years_to_query = [$tahun];
    if ($compare) {
        $years_to_query[] = $tahun_lalu;
    }
    $year_placeholders = implode(',', array_fill(0, count($years_to_query), '?'));

    $stmt = $conn->prepare("
        SELECT
            p.period as $period_alias,
            -- Data Tahun Ini
            COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE 0 END), 0) as total_pendapatan,
            COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Beban' THEN gl.debit - gl.kredit ELSE 0 END), 0) as total_beban,
            -- Data Tahun Lalu
            COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE 0 END), 0) as total_pendapatan_lalu,
            COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Beban' THEN gl.debit - gl.kredit ELSE 0 END), 0) as total_beban_lalu
        FROM
            $period_table
        LEFT JOIN general_ledger gl ON p.period = $period_field
            AND gl.user_id = ?
            AND YEAR(gl.tanggal) IN ($year_placeholders)
        LEFT JOIN accounts acc ON gl.account_id = acc.id AND acc.tipe_akun IN ('Pendapatan', 'Beban')
        GROUP BY p.period
        ORDER BY p.period ASC
    ");
    $bind_params = array_merge(['iiii', $tahun, $tahun, $tahun_lalu, $tahun_lalu, $user_id], $years_to_query);
    $stmt->bind_param(str_repeat('i', count($bind_params) - 1), ...array_slice($bind_params, 1));
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $laba_bersih_sebelumnya = 0;
    // Variabel untuk perhitungan kumulatif
    $cumulative_pendapatan = 0;
    $cumulative_beban = 0;
    $cumulative_pendapatan_lalu = 0;
    $cumulative_beban_lalu = 0;

    $report_data = [];

    foreach ($result as $row) {
        $laba_bersih = (float)$row['total_pendapatan'] - (float)$row['total_beban'];
        $laba_bersih_lalu = (float)$row['total_pendapatan_lalu'] - (float)$row['total_beban_lalu'];
        
        $pertumbuhan = 0;
        if ($laba_bersih_sebelumnya != 0) {
            $pertumbuhan = (($laba_bersih - $laba_bersih_sebelumnya) / abs($laba_bersih_sebelumnya)) * 100;
        } elseif ($laba_bersih != 0) {
            $pertumbuhan = 100.0; // Anggap pertumbuhan 100% jika sebelumnya 0
        }

        $pertumbuhan_yoy = 0; // Year-over-Year growth
        if ($laba_bersih_lalu != 0) {
            $pertumbuhan_yoy = (($laba_bersih - $laba_bersih_lalu) / abs($laba_bersih_lalu)) * 100;
        } elseif ($laba_bersih != 0) {
            $pertumbuhan_yoy = 100.0;
        }

        if ($is_cumulative) {
            $cumulative_pendapatan += (float)$row['total_pendapatan'];
            $cumulative_beban += (float)$row['total_beban'];
            $cumulative_pendapatan_lalu += (float)$row['total_pendapatan_lalu'];
            $cumulative_beban_lalu += (float)$row['total_beban_lalu'];
        }

        // Untuk mode kumulatif, jika belum ada aktivitas sama sekali, jangan tampilkan apa-apa.
        if ($is_cumulative && $cumulative_pendapatan == 0 && $cumulative_beban == 0 && $cumulative_pendapatan_lalu == 0 && $cumulative_beban_lalu == 0) {
             $display_pendapatan = 0;
             $display_beban = 0;
             $display_laba = 0;
             $display_laba_lalu = 0;
        } else {
             $display_pendapatan = $is_cumulative ? $cumulative_pendapatan : (float)$row['total_pendapatan'];
             $display_beban = $is_cumulative ? $cumulative_beban : (float)$row['total_beban'];
             $display_laba = $is_cumulative ? ($cumulative_pendapatan - $cumulative_beban) : $laba_bersih;
             $display_laba_lalu = $is_cumulative ? ($cumulative_pendapatan_lalu - $cumulative_beban_lalu) : $laba_bersih_lalu;
        }

        $report_data[] = [
            $period_alias => (int)$row[$period_alias],
            'total_pendapatan' => $display_pendapatan,
            'total_beban' => $display_beban,
            'laba_bersih' => $display_laba,
            'pertumbuhan' => $pertumbuhan, // Pertumbuhan periodik tetap dihitung dari data non-kumulatif
            'laba_bersih_lalu' => $display_laba_lalu,
            'pertumbuhan_yoy' => $pertumbuhan_yoy, // Pertumbuhan YoY juga dari data non-kumulatif
        ];

        $laba_bersih_sebelumnya = $laba_bersih;
    }

    echo json_encode(['status' => 'success', 'data' => $report_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
