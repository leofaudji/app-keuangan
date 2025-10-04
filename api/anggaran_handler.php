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
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'get_report') {
        $tahun = (int)($_GET['tahun'] ?? date('Y'));
        $bulan = (int)($_GET['bulan'] ?? date('m'));
        $compare = isset($_GET['compare']) && $_GET['compare'] === 'true';
        $tahun_lalu = $tahun - 1;

        $stmt = $conn->prepare("
            SELECT 
                a.id as account_id,
                a.nama_akun,
                COALESCE(ang_current.jumlah_anggaran / 12, 0) as anggaran_bulanan,
                COALESCE(realisasi_current.total_beban, 0) as realisasi_belanja,
                COALESCE(ang_prev.jumlah_anggaran / 12, 0) as anggaran_bulanan_lalu,
                COALESCE(realisasi_prev.total_beban, 0) as realisasi_belanja_lalu
            FROM accounts a
            LEFT JOIN (
                SELECT account_id, jumlah_anggaran 
                FROM anggaran 
                WHERE user_id = ? AND periode_tahun = ?
            ) ang_current ON a.id = ang_current.account_id
            LEFT JOIN (
                SELECT account_id, SUM(debit - kredit) as total_beban
                FROM general_ledger
                WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                GROUP BY account_id
            ) realisasi_current ON a.id = realisasi_current.account_id
            LEFT JOIN (
                SELECT account_id, jumlah_anggaran 
                FROM anggaran 
                WHERE user_id = ? AND periode_tahun = ?
            ) ang_prev ON a.id = ang_prev.account_id
            LEFT JOIN (
                SELECT account_id, SUM(debit - kredit) as total_beban
                FROM general_ledger
                WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                GROUP BY account_id
            ) realisasi_prev ON a.id = realisasi_prev.account_id
            WHERE a.user_id = ? AND a.tipe_akun = 'Beban'
            ORDER BY a.kode_akun
        ");
        // Bind parameters for current and previous year
        $stmt->bind_param('iiiiiiiiiii', $user_id, $tahun, $user_id, $tahun, $bulan, $user_id, $tahun_lalu, $user_id, $tahun_lalu, $bulan, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total_anggaran = 0;
        $total_realisasi = 0;

        $total_anggaran_lalu = 0;
        $total_realisasi_lalu = 0;

        foreach ($result as &$row) {
            $row['sisa_anggaran'] = (float)$row['anggaran_bulanan'] - (float)$row['realisasi_belanja'];
            $row['persentase'] = ((float)$row['anggaran_bulanan'] > 0) ? ((float)$row['realisasi_belanja'] / (float)$row['anggaran_bulanan']) * 100 : 0;
            $total_anggaran += (float)$row['anggaran_bulanan'];
            $total_realisasi += (float)$row['realisasi_belanja'];

            $row['sisa_anggaran_lalu'] = (float)$row['anggaran_bulanan_lalu'] - (float)$row['realisasi_belanja_lalu'];
            $total_anggaran_lalu += (float)$row['anggaran_bulanan_lalu'];
            $total_realisasi_lalu += (float)$row['realisasi_belanja_lalu'];
        }

        $summary = [
            'total_anggaran' => $total_anggaran,
            'total_realisasi' => $total_realisasi,
            'total_sisa' => $total_anggaran - $total_realisasi,
            'total_persentase' => $total_anggaran > 0 ? ($total_realisasi / $total_anggaran) * 100 : 0,
            'total_anggaran_lalu' => $total_anggaran_lalu,
            'total_realisasi_lalu' => $total_realisasi_lalu,
            'total_sisa_lalu' => $total_anggaran_lalu - $total_realisasi_lalu,
        ];

        echo json_encode(['status' => 'success', 'data' => $result, 'summary' => $summary]);

    } elseif ($action === 'get_trend_data') {
        $tahun = (int)($_GET['tahun'] ?? date('Y'));

        // 1. Dapatkan total anggaran tahunan untuk semua akun beban
        $stmt_budget = $conn->prepare("SELECT SUM(jumlah_anggaran) as total_tahunan FROM anggaran WHERE user_id = ? AND periode_tahun = ?");
        $stmt_budget->bind_param('ii', $user_id, $tahun);
        $stmt_budget->execute();
        $total_anggaran_tahunan = $stmt_budget->get_result()->fetch_assoc()['total_tahunan'] ?? 0;
        $anggaran_per_bulan = $total_anggaran_tahunan / 12;
        $stmt_budget->close();

        // 2. Dapatkan total realisasi untuk setiap bulan dalam setahun
        $stmt_realisasi = $conn->prepare("
            SELECT 
                m.month as bulan,
                COALESCE(SUM(gl.debit - gl.kredit), 0) as total_realisasi
            FROM 
                (SELECT 1 as month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) as m
            LEFT JOIN general_ledger gl ON m.month = MONTH(gl.tanggal) 
                AND gl.user_id = ? 
                AND YEAR(gl.tanggal) = ?
                AND gl.account_id IN (SELECT id FROM accounts WHERE user_id = ? AND tipe_akun = 'Beban')
            GROUP BY m.month
            ORDER BY m.month ASC
        ");
        $stmt_realisasi->bind_param('iii', $user_id, $tahun, $user_id);
        $stmt_realisasi->execute();
        $realisasi_bulanan = $stmt_realisasi->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_realisasi->close();

        $data = [
            'anggaran_bulanan' => array_fill(0, 12, $anggaran_per_bulan),
            'realisasi_bulanan' => array_column($realisasi_bulanan, 'total_realisasi')
        ];
        echo json_encode(['status' => 'success', 'data' => $data]);

    } elseif ($action === 'list_budget') {
        $tahun = (int)($_GET['tahun'] ?? date('Y'));

        $stmt = $conn->prepare("
            SELECT 
                a.id as account_id,
                a.nama_akun,
                COALESCE(ang.jumlah_anggaran, 0) as jumlah_anggaran
            FROM accounts a
            LEFT JOIN anggaran ang ON a.id = ang.account_id AND ang.user_id = a.user_id AND ang.periode_tahun = ?
            WHERE a.user_id = ? AND a.tipe_akun = 'Beban'
            ORDER BY a.kode_akun
        ");
        $stmt->bind_param('ii', $tahun, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $result]);

    } elseif ($action === 'save_budgets') {
        $tahun = (int)($_POST['tahun'] ?? 0);
        $budgets = $_POST['budgets'] ?? [];

        if ($tahun === 0 || empty($budgets)) {
            throw new Exception("Data anggaran tidak lengkap.");
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO anggaran (user_id, account_id, periode_tahun, jumlah_anggaran)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE jumlah_anggaran = VALUES(jumlah_anggaran)
            ");

            foreach ($budgets as $account_id => $jumlah) {
                $jumlah_float = (float)$jumlah;
                $stmt->bind_param('iiid', $user_id, $account_id, $tahun, $jumlah_float);
                $stmt->execute();
            }
            $stmt->close();
            $conn->commit();
            log_activity($_SESSION['username'], 'Update Anggaran', "Anggaran untuk tahun {$tahun} telah diperbarui.");
            echo json_encode(['status' => 'success', 'message' => "Anggaran untuk tahun {$tahun} berhasil disimpan."]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } else {
        throw new Exception("Aksi tidak valid.");
    }
} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'in_transaction') && $conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>