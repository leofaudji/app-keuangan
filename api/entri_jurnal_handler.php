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
$logged_in_user_id = $_SESSION['user_id']; // Untuk logging

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

        $where_clauses = ['gl.user_id = ?'];
        $params = ['i', $user_id];

        if (!empty($search)) { $where_clauses[] = 'gl.keterangan LIKE ?'; $params[0] .= 's'; $params[] = '%' . $search . '%'; }
        if (!empty($start_date)) { $where_clauses[] = 'gl.tanggal >= ?'; $params[0] .= 's'; $params[] = $start_date; }
        if (!empty($end_date)) { $where_clauses[] = 'gl.tanggal <= ?'; $params[0] .= 's'; $params[] = $end_date; }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        // Get total count
        $count_query = "SELECT COUNT(DISTINCT CONCAT(ref_type, ref_id)) as total FROM general_ledger gl $where_sql";

        $total_stmt = $conn->prepare($count_query);
        $bind_params_total = [&$params[0]];
        for ($i = 1; $i < count($params); $i++) {
            $bind_params_total[] = &$params[$i];
        }
        call_user_func_array([$total_stmt, 'bind_param'], $bind_params_total);
        $total_stmt->execute();
        $total_records = (int)$total_stmt->get_result()->fetch_assoc()['total'];
        $total_stmt->close();

        // Get data
        $query = "
            SELECT
                gl.ref_type as source, 
                gl.ref_id as entry_id, 
                gl.nomor_referensi as ref,
                gl.tanggal,
                gl.keterangan,
                acc.nama_akun,
                gl.debit,
                gl.kredit,                
                -- Ambil audit info dari tabel sumber (transaksi atau jurnal_entries)
                COALESCE(creator_je.username, creator_t.username) as created_by_name,
                COALESCE(updater_je.username, updater_t.username) as updated_by_name,
                COALESCE(je.created_at, t.created_at) as created_at,
                COALESCE(je.updated_at, t.updated_at) as updated_at
            FROM general_ledger gl
            JOIN accounts acc ON gl.account_id = acc.id
            LEFT JOIN jurnal_entries je ON gl.ref_id = je.id AND gl.ref_type = 'jurnal'
            LEFT JOIN transaksi t ON gl.ref_id = t.id AND gl.ref_type = 'transaksi'
            LEFT JOIN users creator_je ON je.created_by = creator_je.id
            LEFT JOIN users updater_je ON je.updated_by = updater_je.id
            LEFT JOIN users creator_t ON t.created_by = creator_t.id
            LEFT JOIN users updater_t ON t.updated_by = updater_t.id
            $where_sql
        ";

        // Add ORDER BY before LIMIT clause
        $query .= " ORDER BY gl.tanggal DESC, gl.ref_id DESC, gl.debit DESC";

        // Handle pagination only if limit is not -1 (ALL)
        if ($limit != -1) {
            $query .= " LIMIT ? OFFSET ?";
        }

        //print($query) ;
        
        if ($limit != -1) { 
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

        $total_pages = 0;
        if ($limit > 0) {
            $total_pages = ceil($total_records / $limit);
        }

        $pagination = ['current_page' => $page, 'total_pages' => $total_pages, 'total_records' => $total_records];
        echo json_encode(['status' => 'success', 'data' => $entries, 'pagination' => $pagination]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'add';
        $tanggal = $_POST['tanggal'] ?? '';
        $keterangan_raw = $_POST['keterangan'] ?? '';
        if (empty($keterangan_raw)) {
            throw new Exception("Keterangan wajib diisi.");
        }
        $keterangan = trim($keterangan_raw);
        $lines = $_POST['lines'] ?? [];

        if (empty($tanggal) || empty($keterangan) || empty($lines)) {
            throw new Exception("Tanggal, keterangan, dan minimal dua baris jurnal wajib diisi.");
        }

        $total_debit = 0;
        $total_kredit = 0;

        if (count($lines) < 2) {
            throw new Exception("Jurnal harus memiliki minimal dua baris (satu debit dan satu kredit).");
        }

        check_period_lock($tanggal, $conn);

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

        if ($action === 'add') {
            $conn->begin_transaction();
            // 1. Insert header to get the new ID
            $stmt_header = $conn->prepare("INSERT INTO jurnal_entries (user_id, tanggal, keterangan, created_by) VALUES (?, ?, ?, ?)"); // user_id is the data owner, created_by is the logged in user
            $stmt_header->bind_param('issi', $user_id, $tanggal, $keterangan, $logged_in_user_id);
            $stmt_header->execute();
            $jurnal_entry_id = $conn->insert_id;
            $stmt_header->close();

            $nomor_referensi_jurnal = 'JRN-' . $jurnal_entry_id;
            // 2. Insert ke tabel detail (jurnal_details)
            $stmt_detail = $conn->prepare("INSERT INTO jurnal_details (jurnal_entry_id, account_id, debit, kredit) VALUES (?, ?, ?, ?)");
            // Sinkronisasi ke General Ledger
            $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'jurnal', ?)"); // user_id is data owner, created_by is logged in user
            foreach ($lines as $line) {
                $account_id = (int)$line['account_id'];
                $debit = (float)($line['debit'] ?? 0);
                $kredit = (float)($line['kredit'] ?? 0);
                if ($debit > 0 || $kredit > 0) {
                    $stmt_detail->bind_param('iidd', $jurnal_entry_id, $account_id, $debit, $kredit);
                    $stmt_detail->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi_jurnal, $account_id, $debit, $kredit, $jurnal_entry_id, $logged_in_user_id);
                    $stmt_gl->execute();
                }
            }
            $stmt_detail->close();
            $stmt_gl->close();

            $conn->commit();
            log_activity($_SESSION['username'], 'Tambah Entri Jurnal', "Jurnal majemuk baru '{$keterangan}' ditambahkan.");
            echo json_encode(['status' => 'success', 'message' => 'Entri jurnal berhasil ditambahkan.']);

        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $conn->begin_transaction();
            if ($id <= 0) throw new Exception("ID Jurnal tidak valid untuk diperbarui.");

            // Cek periode lock SEBELUM update
            check_period_lock($tanggal, $conn);
            // Cek juga tanggal LAMA dari jurnal yang akan diubah
            $stmt_old_date = $conn->prepare("SELECT tanggal FROM jurnal_entries WHERE id = ?");
            $stmt_old_date->bind_param('i', $id);
            $stmt_old_date->execute();
            check_period_lock($stmt_old_date->get_result()->fetch_assoc()['tanggal'], $conn);

            // 1. Update header
            $stmt_header = $conn->prepare("UPDATE jurnal_entries SET tanggal = ?, keterangan = ?, updated_by = ? WHERE id = ? AND user_id = ?"); // Check against data owner user_id
            $stmt_header->bind_param('ssiii', $tanggal, $keterangan, $logged_in_user_id, $id, $user_id);
            $stmt_header->execute();
            $stmt_header->close();

            // 2. Hapus detail lama
            $stmt_delete = $conn->prepare("DELETE FROM jurnal_details WHERE jurnal_entry_id = ?");
            $stmt_delete->bind_param('i', $id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // Hapus juga dari General Ledger
            $stmt_delete_gl = $conn->prepare("DELETE FROM general_ledger WHERE ref_id = ? AND ref_type = 'jurnal' AND user_id = ?");
            $stmt_delete_gl->bind_param('i', $id);
            $stmt_delete_gl->execute();
            $stmt_delete_gl->close();

            // 3. Insert detail baru
            $nomor_referensi_jurnal = 'JRN-' . $id;
            $stmt_detail = $conn->prepare("INSERT INTO jurnal_details (jurnal_entry_id, account_id, debit, kredit) VALUES (?, ?, ?, ?)");
            $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'jurnal', ?)");
            foreach ($lines as $line) {
                $account_id = (int)$line['account_id'];
                $debit = (float)($line['debit'] ?? 0);
                $kredit = (float)($line['kredit'] ?? 0);
                if ($debit > 0 || $kredit > 0) {
                    $stmt_detail->bind_param('iidd', $id, $account_id, $debit, $kredit);
                    $stmt_detail->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi_jurnal, $account_id, $debit, $kredit, $id, $logged_in_user_id);
                    $stmt_gl->execute();
                }
            }
            $stmt_detail->close();
            $conn->commit();
            log_activity($_SESSION['username'], 'Update Entri Jurnal', "Jurnal majemuk ID {$id} diperbarui.");
            echo json_encode(['status' => 'success', 'message' => 'Entri jurnal berhasil diperbarui.']);

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID Jurnal tidak valid untuk dihapus.");
            
            // Cek periode lock sebelum hapus
            $stmt_old_date = $conn->prepare("SELECT tanggal FROM jurnal_entries WHERE id = ?");
            $stmt_old_date->bind_param('i', $id);
            $stmt_old_date->execute();
            $old_date = $stmt_old_date->get_result()->fetch_assoc()['tanggal'];
            check_period_lock($old_date, $conn);

            $conn->begin_transaction();

            $stmt = $conn->prepare("DELETE FROM jurnal_entries WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $stmt->close();

            // Hapus juga dari General Ledger (CASCADE DELETE di DB akan menangani jurnal_details)
            $stmt_gl = $conn->prepare("DELETE FROM general_ledger WHERE ref_id = ? AND ref_type = 'jurnal' AND user_id = ?");
            $stmt_gl->bind_param('i', $id);
            $stmt_gl->execute();
            $stmt_gl->close();

            $conn->commit();
            log_activity($_SESSION['username'], 'Hapus Entri Jurnal', "Jurnal majemuk ID {$id} dihapus.");
            echo json_encode(['status' => 'success', 'message' => 'Entri jurnal berhasil dihapus.']);
        }

    }
} catch (Exception $e) {
    // Check if in transaction before rolling back, compatible with older PHP versions
    if (method_exists($conn, 'in_transaction') && $conn->in_transaction) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>