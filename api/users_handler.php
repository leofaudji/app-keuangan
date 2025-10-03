<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$current_user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("SELECT id, username, nama_lengkap, role, created_at FROM users ORDER BY username ASC");
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $users]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $username = trim($_POST['username'] ?? '');
                $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';

                if (empty($username) || empty($password) || empty($role)) {
                    throw new Exception("Username, password, dan role wajib diisi.");
                }
                if (strlen($password) < 6) {
                    throw new Exception("Password minimal harus 6 karakter.");
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, nama_lengkap, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('ssss', $username, $nama_lengkap, $password_hash, $role);
                if (!$stmt->execute()) {
                    if ($conn->errno == 1062) throw new Exception("Username '{$username}' sudah ada.");
                    throw new Exception("Gagal menambah pengguna: " . $stmt->error);
                }
                $stmt->close();
                $new_user_id = $conn->insert_id;


                log_activity($_SESSION['username'], 'Tambah Pengguna', "Pengguna baru '{$username}' ditambahkan.");
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil ditambahkan.']);
                break;

            case 'get_single':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("SELECT id, username, nama_lengkap, role FROM users WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$user) throw new Exception("Pengguna tidak ditemukan.");
                echo json_encode(['status' => 'success', 'data' => $user]);
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $username = trim($_POST['username'] ?? '');
                $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';

                if ($id <= 0 || empty($username) || empty($role)) {
                    throw new Exception("Data tidak lengkap.");
                }

                if (!empty($password)) {
                    if (strlen($password) < 6) throw new Exception("Password baru minimal harus 6 karakter.");
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->bind_param('ssssi', $username, $nama_lengkap, $password_hash, $role, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ? WHERE id = ?");
                    $stmt->bind_param('sssi', $username, $nama_lengkap, $role, $id);
                }

                if (!$stmt->execute()) {
                    if ($conn->errno == 1062) throw new Exception("Username '{$username}' sudah digunakan.");
                    throw new Exception("Gagal memperbarui pengguna: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Update Pengguna', "Data pengguna '{$username}' (ID: {$id}) diperbarui.");
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil diperbarui.']);
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID pengguna tidak valid.");
                if ($id === $current_user_id) throw new Exception("Anda tidak dapat menghapus akun Anda sendiri.");

                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                log_activity($_SESSION['username'], 'Hapus Pengguna', "Pengguna ID {$id} dihapus.");
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil dihapus.']);
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