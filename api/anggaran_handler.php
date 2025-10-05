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
$action = $_REQUEST['action'] ?? 'get_report';

try {
    switch ($action) {
        case 'get_report':
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $bulan = (int)($_GET['bulan'] ?? date('m'));
            $compare = isset($_GET['compare']) && $_GET['compare'] === 'true';

            $repo = new LaporanRepository($conn);
            $result = $repo->getAnggaranData($user_id, $tahun, $bulan, $compare);

            echo json_encode(['status' => 'success', 'data' => $result['data'], 'summary' => $result['summary']]);
            break;

        case 'list_budget':
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $stmt = $conn->prepare("
                SELECT 
                    a.id as account_id, 
                    a.nama_akun, 
                    COALESCE(b.jumlah_anggaran, 0) as jumlah_anggaran
                FROM accounts a
                LEFT JOIN anggaran b ON a.id = b.account_id AND b.user_id = ? AND b.periode_tahun = ?
                WHERE a.user_id = ? AND a.tipe_akun = 'Beban'
                ORDER BY a.kode_akun
            ");
            $stmt->bind_param('iii', $user_id, $tahun, $user_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'save_budgets':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Metode tidak diizinkan.');
            if ($_SESSION['role'] !== 'admin') throw new Exception('Akses ditolak.');

            $tahun = (int)($_POST['tahun'] ?? 0);
            $budgets = $_POST['budgets'] ?? [];
            if ($tahun === 0 || empty($budgets)) throw new Exception('Data tidak lengkap.');

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
            log_activity($_SESSION['username'], 'Update Anggaran', "Anggaran untuk tahun {$tahun} diperbarui.");
            echo json_encode(['status' => 'success', 'message' => 'Anggaran berhasil disimpan.']);
            break;

        case 'get_trend_data':
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $anggaran_tahunan = [];
            $realisasi_bulanan = array_fill(1, 12, 0);

            $stmt_anggaran = $conn->prepare("SELECT SUM(jumlah_anggaran) as total FROM anggaran WHERE user_id = ? AND periode_tahun = ?");
            $stmt_anggaran->bind_param('ii', $user_id, $tahun);
            $stmt_anggaran->execute();
            $total_anggaran_tahunan = (float)$stmt_anggaran->get_result()->fetch_assoc()['total'];
            $anggaran_bulanan = array_fill(1, 12, $total_anggaran_tahunan / 12);

            $stmt_realisasi = $conn->prepare("SELECT MONTH(tanggal) as bulan, SUM(debit - kredit) as total FROM general_ledger gl JOIN accounts a ON gl.account_id = a.id WHERE gl.user_id = ? AND YEAR(tanggal) = ? AND a.tipe_akun = 'Beban' GROUP BY bulan");
            $stmt_realisasi->bind_param('ii', $user_id, $tahun);
            $stmt_realisasi->execute();
            $result = $stmt_realisasi->get_result();
            while ($row = $result->fetch_assoc()) {
                $realisasi_bulanan[(int)$row['bulan']] = (float)$row['total'];
            }

            echo json_encode(['status' => 'success', 'data' => ['anggaran_bulanan' => array_values($anggaran_bulanan), 'realisasi_bulanan' => array_values($realisasi_bulanan)]]);
            break;

        default:
            throw new Exception('Aksi tidak valid.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>