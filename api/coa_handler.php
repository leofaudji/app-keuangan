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

function get_saldo_normal($tipe_akun) {
    return in_array($tipe_akun, ['Aset', 'Beban']) ? 'Debit' : 'Kredit';
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Mengambil semua akun untuk ditampilkan sebagai pohon
        $stmt = $conn->prepare("SELECT id, parent_id, kode_akun, nama_akun, tipe_akun, is_kas FROM accounts WHERE user_id = ? ORDER BY kode_akun ASC");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $accounts = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['status' => 'success', 'data' => $accounts]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $kode_akun = trim($_POST['kode_akun'] ?? '');
                $nama_akun = trim($_POST['nama_akun'] ?? '');
                $tipe_akun = $_POST['tipe_akun'] ?? '';
                $is_kas = isset($_POST['is_kas']) ? 1 : 0;
                $saldo_normal = get_saldo_normal($tipe_akun);

                if (empty($kode_akun) || empty($nama_akun) || empty($tipe_akun)) {
                    throw new Exception("Kode, Nama, dan Tipe Akun tidak boleh kosong.");
                }

                $stmt = $conn->prepare("INSERT INTO accounts (user_id, parent_id, kode_akun, nama_akun, tipe_akun, saldo_normal, is_kas) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iissssi', $user_id, $parent_id, $kode_akun, $nama_akun, $tipe_akun, $saldo_normal, $is_kas);
                if (!$stmt->execute()) {
                    if ($conn->errno == 1062) { // Duplicate entry
                        throw new Exception("Kode akun '{$kode_akun}' sudah ada.");
                    }
                    throw new Exception("Gagal menyimpan akun: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Tambah Akun COA', "Akun '{$nama_akun}' ditambahkan.");
                echo json_encode(['status' => 'success', 'message' => 'Akun berhasil ditambahkan.']);
                break;

            case 'get_single':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("SELECT id, parent_id, kode_akun, nama_akun, tipe_akun, is_kas FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $id, $user_id);
                $stmt->execute();
                $account = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$account) {
                    throw new Exception("Akun tidak ditemukan.");
                }
                echo json_encode(['status' => 'success', 'data' => $account]);
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $kode_akun = trim($_POST['kode_akun'] ?? '');
                $nama_akun = trim($_POST['nama_akun'] ?? '');
                $tipe_akun = $_POST['tipe_akun'] ?? '';
                $is_kas = isset($_POST['is_kas']) ? 1 : 0;
                $saldo_normal = get_saldo_normal($tipe_akun);

                if (empty($kode_akun) || empty($nama_akun) || empty($tipe_akun)) {
                    throw new Exception("Kode, Nama, dan Tipe Akun tidak boleh kosong.");
                }

                $stmt = $conn->prepare("UPDATE accounts SET parent_id = ?, kode_akun = ?, nama_akun = ?, tipe_akun = ?, saldo_normal = ?, is_kas = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param('issssiii', $parent_id, $kode_akun, $nama_akun, $tipe_akun, $saldo_normal, $is_kas, $id, $user_id);
                if (!$stmt->execute()) {
                     if ($conn->errno == 1062) {
                        throw new Exception("Kode akun '{$kode_akun}' sudah digunakan oleh akun lain.");
                    }
                    throw new Exception("Gagal memperbarui akun: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Update Akun COA', "Akun ID {$id} diperbarui.");
                echo json_encode(['status' => 'success', 'message' => 'Akun berhasil diperbarui.']);
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);

                // Cek apakah akun ini adalah parent dari akun lain
                $stmt_check_child = $conn->prepare("SELECT COUNT(*) as count FROM accounts WHERE parent_id = ?");
                $stmt_check_child->bind_param('i', $id);
                $stmt_check_child->execute();
                if ($stmt_check_child->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Tidak dapat menghapus akun karena memiliki sub-akun.");
                }
                $stmt_check_child->close();

                // Cek apakah akun masih digunakan di transaksi
                $stmt_check_trans = $conn->prepare("SELECT COUNT(*) as count FROM transaksi WHERE account_id = ? OR kas_account_id = ? OR kas_tujuan_account_id = ?");
                $stmt_check_trans->bind_param('iii', $id, $id, $id);
                $stmt_check_trans->execute();
                if ($stmt_check_trans->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Tidak dapat menghapus akun karena sudah memiliki riwayat transaksi.");
                }
                $stmt_check_trans->close();

                $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $id, $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Gagal menghapus akun: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Hapus Akun COA', "Akun ID {$id} dihapus.");
                echo json_encode(['status' => 'success', 'message' => 'Akun berhasil dihapus.']);
                break;

            default:
                throw new Exception("Aksi tidak valid.");
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
    

?>