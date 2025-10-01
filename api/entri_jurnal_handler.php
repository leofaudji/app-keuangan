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
        $action = $_GET['action'] ?? 'list';

        if ($action === 'get_single') {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID Jurnal tidak valid.");

            // Get header
            $stmt_header = $conn->prepare("SELECT id, tanggal, keterangan FROM jurnal_entries WHERE id = ? AND user_id = ?");
            $stmt_header->bind_param('ii', $id, $user_id);
            $stmt_header->execute();
            $header = $stmt_header->get_result()->fetch_assoc();
            $stmt_header->close();
            if (!$header) throw new Exception("Entri Jurnal tidak ditemukan.");

            // Get details
            $stmt_details = $conn->prepare("
                SELECT jd.account_id, jd.debit, jd.kredit, a.kode_akun, a.nama_akun FROM jurnal_details jd
                JOIN accounts a ON jd.account_id = a.id
                WHERE jd.jurnal_entry_id = ?
                ORDER BY jd.id ASC
            ");
            $stmt_details->bind_param('i', $id);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_details->close();

            echo json_encode(['status' => 'success', 'data' => ['header' => $header, 'details' => $details]]);
            exit;
        }

        // Default action: list
        $limit = (int)($_GET['limit'] ?? 15);
        $page = (int)($_GET['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $search = $_GET['search'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';

        $where_clauses = ['je.user_id = ?'];
        $params = ['i', $user_id];

        if (!empty($search)) { $where_clauses[] = 'je.keterangan LIKE ?'; $params[0] .= 's'; $params[] = '%' . $search . '%'; }
        if (!empty($start_date)) { $where_clauses[] = 'je.tanggal >= ?'; $params[0] .= 's'; $params[] = $start_date; }
        if (!empty($end_date)) { $where_clauses[] = 'je.tanggal <= ?'; $params[0] .= 's'; $params[] = $end_date; }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        // Get total count
        $total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM jurnal_entries je $where_sql");
        $bind_params_total = [&$params[0]];
        for ($i = 1; $i < count($params); $i++) {
            $bind_params_total[] = &$params[$i];
        }
        call_user_func_array([$total_stmt, 'bind_param'], $bind_params_total);
        $total_stmt->execute();
        $total_records = $total_stmt->get_result()->fetch_assoc()['total'];
        $total_stmt->close();

        // Get data
        $query = "
            SELECT je.id, je.tanggal, je.keterangan, SUM(jd.debit) as total
            FROM jurnal_entries je
            JOIN jurnal_details jd ON je.id = jd.jurnal_entry_id
            $where_sql
            GROUP BY je.id, je.tanggal, je.keterangan
            ORDER BY je.tanggal DESC, je.id DESC 
        ";

        // Handle pagination only if limit is not -1 (ALL)
        if ($limit != -1) {
            $query .= " LIMIT ? OFFSET ?";
            $params[0] .= 'ii'; 
            $params[] = $limit; 
            $params[] = $offset;
        }
        $stmt = $conn->prepare($query);
        $bind_params_main = [&$params[0]];
        for ($i = 1; $i < count($params); $i++) {
            $bind_params_main[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params_main);
        $stmt->execute();
        $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pagination = ['current_page' => $page, 'total_pages' => ceil($total_records / $limit), 'total_records' => $total_records];
        echo json_encode(['status' => 'success', 'data' => $entries, 'pagination' => $pagination]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'add';
        $tanggal = $_POST['tanggal'] ?? '';
        $keterangan = trim($_POST['keterangan'] ?? '');
        $lines = $_POST['lines'] ?? [];

        if (empty($tanggal) || empty($keterangan) || empty($lines)) {
            throw new Exception("Tanggal, keterangan, dan minimal dua baris jurnal wajib diisi.");
        }

        $total_debit = 0;
        $total_kredit = 0;

        if (count($lines) < 2) {
            throw new Exception("Jurnal harus memiliki minimal dua baris (satu debit dan satu kredit).");
        }

        foreach ($lines as $line) {
            if (empty($line['account_id'])) {
                throw new Exception("Setiap baris jurnal harus memiliki akun yang dipilih.");
            }
            $total_debit += (float)($line['debit'] ?? 0);
            $total_kredit += (float)($line['kredit'] ?? 0);
        }

        // Validasi keseimbangan
        if (abs($total_debit - $total_kredit) > 0.01) { // Toleransi kecil untuk floating point
            throw new Exception("Jurnal tidak seimbang. Total Debit (Rp " . number_format($total_debit) . ") harus sama dengan Total Kredit (Rp " . number_format($total_kredit) . ").");
        }
        if ($total_debit === 0) {
            throw new Exception("Total jurnal tidak boleh nol.");
        }

        $conn->begin_transaction();

        if ($action === 'add') {
            // 1. Insert header to get the new ID
            $stmt_header = $conn->prepare("INSERT INTO jurnal_entries (user_id, tanggal, keterangan) VALUES (?, ?, ?)");
            $stmt_header->bind_param('iss', $user_id, $tanggal, $keterangan);
            $stmt_header->execute();
            $jurnal_entry_id = $conn->insert_id;
            $stmt_header->close();

            // 2. Insert ke tabel detail (jurnal_details)
            $stmt_detail = $conn->prepare("INSERT INTO jurnal_details (jurnal_entry_id, account_id, debit, kredit) VALUES (?, ?, ?, ?)");
            foreach ($lines as $line) {
                $account_id = (int)$line['account_id'];
                $debit = (float)($line['debit'] ?? 0);
                $kredit = (float)($line['kredit'] ?? 0);
                if ($debit > 0 || $kredit > 0) {
                    $stmt_detail->bind_param('iidd', $jurnal_entry_id, $account_id, $debit, $kredit);
                    $stmt_detail->execute();
                }
            }
            $stmt_detail->close();

            log_activity($_SESSION['username'], 'Tambah Entri Jurnal', "Jurnal majemuk baru '{$keterangan}' ditambahkan.");
            echo json_encode(['status' => 'success', 'message' => 'Entri jurnal berhasil ditambahkan.']);

        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID Jurnal tidak valid untuk diperbarui.");

            // 1. Update header
            $stmt_header = $conn->prepare("UPDATE jurnal_entries SET tanggal = ?, keterangan = ? WHERE id = ? AND user_id = ?");
            $stmt_header->bind_param('ssii', $tanggal, $keterangan, $id, $user_id);
            $stmt_header->execute();
            $stmt_header->close();

            // 2. Hapus detail lama
            $stmt_delete = $conn->prepare("DELETE FROM jurnal_details WHERE jurnal_entry_id = ?");
            $stmt_delete->bind_param('i', $id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // 3. Insert detail baru
            $stmt_detail = $conn->prepare("INSERT INTO jurnal_details (jurnal_entry_id, account_id, debit, kredit) VALUES (?, ?, ?, ?)");
            foreach ($lines as $line) {
                $account_id = (int)$line['account_id'];
                $debit = (float)($line['debit'] ?? 0);
                $kredit = (float)($line['kredit'] ?? 0);
                if ($debit > 0 || $kredit > 0) {
                    $stmt_detail->bind_param('iidd', $id, $account_id, $debit, $kredit);
                    $stmt_detail->execute();
                }
            }
            $stmt_detail->close();
            log_activity($_SESSION['username'], 'Update Entri Jurnal', "Jurnal majemuk ID {$id} diperbarui.");
            echo json_encode(['status' => 'success', 'message' => 'Entri jurnal berhasil diperbarui.']);

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID Jurnal tidak valid untuk dihapus.");
            $stmt = $conn->prepare("DELETE FROM jurnal_entries WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $stmt->close();
            log_activity($_SESSION['username'], 'Hapus Entri Jurnal', "Jurnal majemuk ID {$id} dihapus.");
            echo json_encode(['status' => 'success', 'message' => 'Entri jurnal berhasil dihapus.']);
        }

        $conn->commit();
    }
} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>