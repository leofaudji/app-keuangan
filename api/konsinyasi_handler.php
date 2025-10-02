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
    $action = $_REQUEST['action'] ?? '';

    // --- SUPPLIER ACTIONS ---
    if ($action === 'list_suppliers') {
        $result = $conn->query("SELECT * FROM suppliers WHERE user_id = $user_id ORDER BY nama_pemasok ASC");
        echo json_encode(['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
    } elseif ($action === 'save_supplier') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_pemasok'] ?? '');
        $kontak = trim($_POST['kontak'] ?? '');
        if (empty($nama)) throw new Exception("Nama pemasok wajib diisi.");

        if ($id > 0) { // Update
            $stmt = $conn->prepare("UPDATE suppliers SET nama_pemasok = ?, kontak = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ssii', $nama, $kontak, $id, $user_id);
        } else { // Add
            $stmt = $conn->prepare("INSERT INTO suppliers (user_id, nama_pemasok, kontak) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $user_id, $nama, $kontak);
        }
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Pemasok berhasil disimpan.']);
    } elseif ($action === 'delete_supplier') {
        $id = (int)($_POST['id'] ?? 0);
        // Cek keterkaitan sebelum hapus
        $res = $conn->query("SELECT COUNT(*) as count FROM consignment_items WHERE supplier_id = $id");
        if ($res->fetch_assoc()['count'] > 0) throw new Exception("Tidak dapat menghapus pemasok karena masih memiliki barang konsinyasi.");
        
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Pemasok berhasil dihapus.']);
    }

    // --- ITEM ACTIONS ---
    elseif ($action === 'list_items') {
        $result = $conn->query("
            SELECT 
                ci.*,
                s.nama_pemasok,
                (ci.stok_awal - COALESCE((SELECT SUM(qty) FROM general_ledger WHERE consignment_item_id = ci.id AND ref_type = 'jurnal' AND debit > 0 AND account_id = (SELECT setting_value FROM settings WHERE setting_key = 'consignment_cogs_account')), 0)) as stok_saat_ini
            FROM consignment_items ci
            JOIN suppliers s ON ci.supplier_id = s.id
            WHERE ci.user_id = $user_id
            ORDER BY ci.nama_barang ASC
        ");
        echo json_encode(['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
    } elseif ($action === 'save_item') {
        $id = (int)($_POST['id'] ?? 0);
        $supplier_id = (int)$_POST['supplier_id'];
        $nama_barang = trim($_POST['nama_barang']);
        $harga_jual = (float)$_POST['harga_jual'];
        $harga_beli = (float)$_POST['harga_beli'];
        $stok_awal = (int)$_POST['stok_awal'];
        $tanggal_terima = $_POST['tanggal_terima'];

        if (empty($nama_barang) || $harga_jual <= 0 || $harga_beli <= 0 || $stok_awal < 0) {
            throw new Exception("Data barang tidak lengkap atau tidak valid.");
        }

        if ($id > 0) { // Update
            $stmt = $conn->prepare("UPDATE consignment_items SET supplier_id=?, nama_barang=?, harga_jual=?, harga_beli=?, stok_awal=?, tanggal_terima=? WHERE id=? AND user_id=?");
            $stmt->bind_param('isddisii', $supplier_id, $nama_barang, $harga_jual, $harga_beli, $stok_awal, $tanggal_terima, $id, $user_id);
        } else { // Add
            $stmt = $conn->prepare("INSERT INTO consignment_items (user_id, supplier_id, nama_barang, harga_jual, harga_beli, stok_awal, tanggal_terima) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisddis', $user_id, $supplier_id, $nama_barang, $harga_jual, $harga_beli, $stok_awal, $tanggal_terima);
        }
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Barang konsinyasi berhasil disimpan.']);
    } elseif ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        // Cek keterkaitan sebelum hapus
        $res = $conn->query("SELECT COUNT(*) as count FROM general_ledger WHERE consignment_item_id = $id");
        if ($res->fetch_assoc()['count'] > 0) throw new Exception("Tidak dapat menghapus barang karena sudah ada riwayat penjualan.");

        $stmt = $conn->prepare("DELETE FROM consignment_items WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Barang berhasil dihapus.']);
    } elseif ($action === 'get_single_item') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM consignment_items WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) throw new Exception("Barang tidak ditemukan.");
        echo json_encode(['status' => 'success', 'data' => $item]);
    }

    // --- SALE ACTION ---
    elseif ($action === 'sell_item') {
        $item_id = (int)$_POST['item_id'];
        $qty = (int)$_POST['qty'];
        $tanggal = $_POST['tanggal'];
        $created_by = $_SESSION['user_id'];

        if ($item_id <= 0 || $qty <= 0 || empty($tanggal)) {
            throw new Exception("Data penjualan tidak valid.");
        }

        // Ambil detail barang dan akun-akun terkait
        $stmt_item = $conn->prepare("
            SELECT ci.*, s.nama_pemasok,
                   (SELECT setting_value FROM settings WHERE setting_key = 'consignment_cash_account') as kas_acc_id,
                   (SELECT setting_value FROM settings WHERE setting_key = 'consignment_revenue_account') as revenue_acc_id,
                   (SELECT setting_value FROM settings WHERE setting_key = 'consignment_cogs_account') as cogs_acc_id,
                   (SELECT setting_value FROM settings WHERE setting_key = 'consignment_payable_account') as payable_acc_id
            FROM consignment_items ci
            JOIN suppliers s ON ci.supplier_id = s.id
            WHERE ci.id = ? AND ci.user_id = ?
        ");
        $stmt_item->bind_param('ii', $item_id, $user_id);
        $stmt_item->execute();
        $item = $stmt_item->get_result()->fetch_assoc();
        $stmt_item->close();

        if (!$item) throw new Exception("Barang tidak ditemukan.");
        if (empty($item['kas_acc_id']) || empty($item['revenue_acc_id']) || empty($item['cogs_acc_id']) || empty($item['payable_acc_id'])) {
            throw new Exception("Akun untuk konsinyasi belum diatur di Pengaturan. Silakan hubungi admin.");
        }

        $total_penjualan = $qty * (float)$item['harga_jual'];
        $total_modal = $qty * (float)$item['harga_beli'];
        $keterangan = "Penjualan konsinyasi: $qty x {$item['nama_barang']} ({$item['nama_pemasok']})";
        
        // --- Logika Nomor Referensi Otomatis untuk Penjualan Konsinyasi ---
        $prefix = 'CSL'; // Consignment Sale
        $date_parts = explode('-', $tanggal);
        $year = $date_parts[0];
        $month = $date_parts[1];

        $stmt_ref = $conn->prepare(
            "SELECT nomor_referensi FROM general_ledger 
             WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ? AND nomor_referensi LIKE ? 
             ORDER BY id DESC LIMIT 1"
        );
        $like_prefix = $prefix . '%';
        $stmt_ref->bind_param('iiss', $user_id, $year, $month, $like_prefix);
        $stmt_ref->execute();
        $last_ref = $stmt_ref->get_result()->fetch_assoc();
        $stmt_ref->close();

        $sequence = 1;
        if ($last_ref && !empty($last_ref['nomor_referensi'])) {
            $parts = explode('/', $last_ref['nomor_referensi']);
            $sequence = (int)end($parts) + 1;
        }
        $nomor_referensi = sprintf('%s/%s/%s/%03d', $prefix, $year, $month, $sequence);
        // --- Akhir Logika ---

        $conn->begin_transaction();

        $zero = 0.00;
        // Buat 4 entri di General Ledger
        $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_type, ref_id, consignment_item_id, qty, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'jurnal', 0, ?, ?, ?)");

        // 1. (Dr) Kas, (Cr) Pendapatan Konsinyasi
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['kas_acc_id'], $total_penjualan, $zero, $item_id, $qty, $created_by); $stmt_gl->execute();
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['revenue_acc_id'], $zero, $total_penjualan, $item_id, $qty, $created_by); $stmt_gl->execute();

        // 2. (Dr) HPP Konsinyasi, (Cr) Utang Konsinyasi
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['cogs_acc_id'], $total_modal, $zero, $item_id, $qty, $created_by); $stmt_gl->execute();
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['payable_acc_id'], $zero, $total_modal, $item_id, $qty, $created_by); $stmt_gl->execute();
        
        $stmt_gl->close();
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => "Penjualan {$item['nama_barang']} berhasil dicatat."]);
    }

    // --- REPORT ACTION ---
    elseif ($action === 'get_sales_report') {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');

        $stmt = $conn->prepare("
            SELECT 
                s.nama_pemasok,
                ci.nama_barang,
                SUM(gl.qty) as total_terjual, ci.harga_beli, (SUM(gl.qty) * ci.harga_beli) as total_utang
            FROM general_ledger gl
            JOIN consignment_items ci ON gl.consignment_item_id = ci.id
            JOIN suppliers s ON ci.supplier_id = s.id
            WHERE gl.user_id = ?
              AND gl.tanggal BETWEEN ? AND ?
              AND gl.ref_type = 'jurnal' 
              AND gl.consignment_item_id IS NOT NULL 
              AND gl.debit > 0 
              AND gl.account_id = (SELECT setting_value FROM settings WHERE setting_key = 'consignment_cogs_account')
            GROUP BY s.nama_pemasok, ci.nama_barang, ci.harga_beli
            ORDER BY s.nama_pemasok, ci.nama_barang
        ");
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['status' => 'success', 'data' => $report]);
    }

} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'in_transaction') && $conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>