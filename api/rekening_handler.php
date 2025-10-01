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
        // Mengambil semua rekening beserta saldo saat ini
        $stmt = $conn->prepare("
            SELECT 
                r.id, 
                r.nama_rekening, 
                r.saldo_awal,
                (r.saldo_awal + 
                    COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE rekening_id = r.id AND jenis = 'pemasukan'), 0) -
                    COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE rekening_id = r.id AND jenis = 'pengeluaran'), 0) -
                    COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE rekening_id = r.id AND jenis = 'transfer'), 0) +
                    COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE rekening_tujuan_id = r.id AND jenis = 'transfer'), 0)
                ) as saldo_saat_ini
            FROM rekening r
            WHERE r.user_id = ?
            ORDER BY r.nama_rekening ASC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rekening = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['status' => 'success', 'data' => $rekening]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $nama_rekening = trim($_POST['nama_rekening'] ?? '');
                $saldo_awal = (float)($_POST['saldo_awal'] ?? 0);

                if (empty($nama_rekening)) {
                    throw new Exception("Nama rekening tidak boleh kosong.");
                }

                $stmt = $conn->prepare("INSERT INTO rekening (user_id, nama_rekening, saldo_awal) VALUES (?, ?, ?)");
                $stmt->bind_param('isd', $user_id, $nama_rekening, $saldo_awal);
                if (!$stmt->execute()) {
                    throw new Exception("Gagal menyimpan rekening: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Tambah Rekening', "Rekening '{$nama_rekening}' ditambahkan.");
                echo json_encode(['status' => 'success', 'message' => 'Rekening berhasil ditambahkan.']);
                break;

            case 'get_single':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("SELECT id, nama_rekening, saldo_awal FROM rekening WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $id, $user_id);
                $stmt->execute();
                $rekening = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$rekening) {
                    throw new Exception("Rekening tidak ditemukan.");
                }
                echo json_encode(['status' => 'success', 'data' => $rekening]);
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $nama_rekening = trim($_POST['nama_rekening'] ?? '');
                $saldo_awal = (float)($_POST['saldo_awal'] ?? 0);

                if (empty($nama_rekening)) {
                    throw new Exception("Nama rekening tidak boleh kosong.");
                }

                $stmt = $conn->prepare("UPDATE rekening SET nama_rekening = ?, saldo_awal = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param('sdii', $nama_rekening, $saldo_awal, $id, $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Gagal memperbarui rekening: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Update Rekening', "Rekening ID {$id} diperbarui.");
                echo json_encode(['status' => 'success', 'message' => 'Rekening berhasil diperbarui.']);
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                // Cek apakah rekening masih digunakan di transaksi
                $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM transaksi WHERE rekening_id = ? OR rekening_tujuan_id = ?");
                $stmt_check->bind_param('ii', $id, $id);
                $stmt_check->execute();
                $count = $stmt_check->get_result()->fetch_assoc()['count'];
                $stmt_check->close();

                if ($count > 0) {
                    throw new Exception("Tidak dapat menghapus rekening karena sudah memiliki riwayat transaksi.");
                }

                $stmt = $conn->prepare("DELETE FROM rekening WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $id, $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Gagal menghapus rekening: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Hapus Rekening', "Rekening ID {$id} dihapus.");
                echo json_encode(['status' => 'success', 'message' => 'Rekening berhasil dihapus.']);
                break;

            default:
                throw new Exception("Aksi tidak valid.");
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}