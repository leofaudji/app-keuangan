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
            $stmt = $conn->prepare("UPDATE suppliers SET nama_pemasok = ?, kontak = ?, updated_by = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ssiii', $nama, $kontak, $logged_in_user_id, $id, $user_id);
        } else { // Add
            $stmt = $conn->prepare("INSERT INTO suppliers (user_id, nama_pemasok, kontak, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('issi', $user_id, $nama, $kontak, $logged_in_user_id);
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

        $conn->begin_transaction();

        if ($id > 0) { // Update
            // TODO: Handle logic for updating stock and creating adjustment journals if needed.
            // For now, we only update the item details.
            $stmt = $conn->prepare("UPDATE consignment_items SET supplier_id=?, nama_barang=?, harga_jual=?, harga_beli=?, stok_awal=?, tanggal_terima=?, updated_by=? WHERE id=? AND user_id=?");
            $stmt->bind_param('isddisiii', $supplier_id, $nama_barang, $harga_jual, $harga_beli, $stok_awal, $tanggal_terima, $logged_in_user_id, $id, $user_id);
            $stmt->execute();
            $stmt->close();
        } else { // Add
            // 1. Insert item to get ID
            $stmt = $conn->prepare("INSERT INTO consignment_items (user_id, supplier_id, nama_barang, harga_jual, harga_beli, stok_awal, tanggal_terima, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisddisi', $user_id, $supplier_id, $nama_barang, $harga_jual, $harga_beli, $stok_awal, $tanggal_terima, $logged_in_user_id);
            $stmt->execute();
            $item_id = $conn->insert_id;
            $stmt->close();

            // 2. Create memo journal entry in general ledger
            $total_nilai_barang = $stok_awal * $harga_beli;
            if ($total_nilai_barang > 0) {
                $inventory_acc_id = get_setting('consignment_inventory_account', null, $conn);
                $payable_acc_id = get_setting('consignment_payable_account', null, $conn);

                if (empty($inventory_acc_id) || empty($payable_acc_id)) {
                    throw new Exception("Akun untuk Persediaan/Utang Konsinyasi belum diatur di Pengaturan. Silakan hubungi admin.");
                }

                $keterangan_jurnal = "Penerimaan barang konsinyasi: {$stok_awal} x {$nama_barang}";
                $nomor_referensi = "CIN-{$item_id}"; // Consignment In
                $zero = 0.00;

                $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_type, ref_id, consignment_item_id, qty, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'jurnal', 0, ?, ?, ?)");

                // (Dr) Persediaan Konsinyasi
                $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal_terima, $keterangan_jurnal, $nomor_referensi, $inventory_acc_id, $total_nilai_barang, $zero, $item_id, $stok_awal, $logged_in_user_id);
                $stmt_gl->execute();
                // (Cr) Utang Konsinyasi
                $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal_terima, $keterangan_jurnal, $nomor_referensi, $payable_acc_id, $zero, $total_nilai_barang, $item_id, $stok_awal, $logged_in_user_id);
                $stmt_gl->execute();
                $stmt_gl->close();
            }
        }
        $conn->commit();
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
        $tanggal = $_POST['tanggal']; // Ini akan diambil dari form, bukan dari session
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
                   (SELECT setting_value FROM settings WHERE setting_key = 'consignment_payable_account') as payable_acc_id,
                   (SELECT setting_value FROM settings WHERE setting_key = 'consignment_inventory_account') as inventory_acc_id
            FROM consignment_items ci
            JOIN suppliers s ON ci.supplier_id = s.id
            WHERE ci.id = ? AND ci.user_id = ?
        ");
        $stmt_item->bind_param('ii', $item_id, $user_id);
        $stmt_item->execute();
        $item = $stmt_item->get_result()->fetch_assoc();
        $stmt_item->close();

        if (!$item) throw new Exception("Barang tidak ditemukan.");
        if (empty($item['kas_acc_id']) || empty($item['revenue_acc_id']) || empty($item['cogs_acc_id']) || empty($item['payable_acc_id']) || empty($item['inventory_acc_id'])) {
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
        $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_type, ref_id, consignment_item_id, qty, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'jurnal', 0, ?, ?, ?)"); // ref_id 0 karena ini bukan dari tabel transaksi/jurnal_entries

        // 1. (Dr) Kas, (Cr) Pendapatan Konsinyasi
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['kas_acc_id'], $total_penjualan, $zero, $item_id, $qty, $created_by);
        $stmt_gl->execute();
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['revenue_acc_id'], $zero, $total_penjualan, $item_id, $qty, $created_by);
        $stmt_gl->execute();

        // 2. (Dr) HPP Konsinyasi, (Cr) Persediaan Konsinyasi
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['cogs_acc_id'], $total_modal, $zero, $item_id, $qty, $created_by);
        $stmt_gl->execute();
        // Mengurangi persediaan konsinyasi yang tercatat
        $stmt_gl->bind_param('isssiddiii', $user_id, $tanggal, $keterangan, $nomor_referensi, $item['inventory_acc_id'], $zero, $total_modal, $item_id, $qty, $created_by);
        $stmt_gl->execute();

        $stmt_gl->close();
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => "Penjualan {$item['nama_barang']} berhasil dicatat."]);
    }

    // --- PAYMENT ACTION ---
    elseif ($action === 'pay_debt') {
        $supplier_id = (int)$_POST['supplier_id'];
        $tanggal = $_POST['tanggal'];
        $jumlah = (float)$_POST['jumlah'];
        $kas_account_id = (int)$_POST['kas_account_id'];
        $keterangan = trim($_POST['keterangan']);
        $created_by = $_SESSION['user_id'];

        if ($supplier_id <= 0 || empty($tanggal) || $jumlah <= 0 || $kas_account_id <= 0) {
            throw new Exception("Data pembayaran tidak lengkap atau tidak valid.");
        }

        $payable_acc_id = get_setting('consignment_payable_account', null, $conn);
        if (empty($payable_acc_id)) {
            throw new Exception("Akun Utang Konsinyasi belum diatur di Pengaturan.");
        }

        $stmt_supplier = $conn->prepare("SELECT nama_pemasok FROM suppliers WHERE id = ?");
        $stmt_supplier->bind_param('i', $supplier_id);
        $stmt_supplier->execute();
        $supplier_name = $stmt_supplier->get_result()->fetch_assoc()['nama_pemasok'] ?? 'N/A';
        $stmt_supplier->close();

        $conn->begin_transaction();

        $keterangan_jurnal = "Pembayaran utang konsinyasi ke {$supplier_name}";
        if (!empty($keterangan)) {
            $keterangan_jurnal .= " - " . $keterangan;
        }
        $nomor_referensi = "CPY-" . date('YmdHis'); // Consignment Payment
        $zero = 0.00;

        $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_type, ref_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'jurnal', 0, ?)");

        // (Dr) Utang Konsinyasi
        $stmt_gl->bind_param('isssiddi', $user_id, $tanggal, $keterangan_jurnal, $nomor_referensi, $payable_acc_id, $jumlah, $zero, $created_by);
        $stmt_gl->execute();
        // (Cr) Kas/Bank
        $stmt_gl->bind_param('isssiddi', $user_id, $tanggal, $keterangan_jurnal, $nomor_referensi, $kas_account_id, $zero, $jumlah, $created_by);
        $stmt_gl->execute();
        
        $stmt_gl->close();
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Pembayaran utang konsinyasi berhasil dicatat.']);

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
    elseif ($action === 'list_payments') {
        $payable_acc_id = get_setting('consignment_payable_account', null, $conn);
        if (empty($payable_acc_id)) {
            echo json_encode(['status' => 'success', 'data' => []]); // Return empty if not configured
            exit;
        }

        $stmt = $conn->prepare("
            SELECT gl.tanggal, gl.keterangan, gl.debit as jumlah, s.nama_pemasok
            FROM general_ledger gl
            LEFT JOIN suppliers s ON SUBSTRING_INDEX(SUBSTRING_INDEX(gl.keterangan, 'ke ', -1), ' -', 1) = s.nama_pemasok
            WHERE gl.user_id = ?
              AND gl.account_id = ?
              AND gl.debit > 0
            ORDER BY gl.tanggal DESC, gl.id DESC
        ");
        $stmt->bind_param('ii', $user_id, $payable_acc_id);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    }
    elseif ($action === 'get_debt_summary_report') {
        $payable_acc_id = get_setting('consignment_payable_account', null, $conn);
        $cogs_acc_id = get_setting('consignment_cogs_account', null, $conn);

        if (empty($payable_acc_id) || empty($cogs_acc_id)) {
            throw new Exception("Akun Utang/HPP Konsinyasi belum diatur di Pengaturan.");
        }

        $start_date = $_GET['start_date'] ?? '1970-01-01';
        $end_date = $_GET['end_date'] ?? date('Y-m-d');

        $stmt = $conn->prepare("
            SELECT 
                s.id,
                s.nama_pemasok,
                COALESCE(utang.total_utang, 0) as total_utang,
                COALESCE(bayar.total_bayar, 0) as total_bayar,
                (COALESCE(utang.total_utang, 0) - COALESCE(bayar.total_bayar, 0)) as sisa_utang
            FROM 
                suppliers s
            LEFT JOIN (
                -- Subquery untuk menghitung total utang dari barang yang terjual
                SELECT 
                    ci.supplier_id,
                    SUM(gl.qty * ci.harga_beli) as total_utang
                FROM general_ledger gl
                JOIN consignment_items ci ON gl.consignment_item_id = ci.id
                WHERE gl.user_id = ?
                  AND gl.account_id = ? AND gl.tanggal BETWEEN ? AND ? -- HPP Konsinyasi
                  AND gl.debit > 0
                GROUP BY ci.supplier_id
            ) utang ON s.id = utang.supplier_id
            LEFT JOIN (
                -- Subquery untuk menghitung total pembayaran
                SELECT 
                    s_inner.id as supplier_id,
                    SUM(gl.debit) as total_bayar
                FROM general_ledger gl
                -- Join ke supplier berdasarkan nama di keterangan
                JOIN suppliers s_inner ON SUBSTRING_INDEX(SUBSTRING_INDEX(gl.keterangan, 'ke ', -1), ' -', 1) = s_inner.nama_pemasok
                WHERE gl.user_id = ?
                  AND gl.account_id = ? AND gl.tanggal BETWEEN ? AND ? -- Utang Konsinyasi
                  AND gl.debit > 0
                GROUP BY s_inner.id
            ) bayar ON s.id = bayar.supplier_id
            WHERE s.user_id = ?
            ORDER BY s.nama_pemasok
        ");
        $stmt->bind_param('isssisssi', $user_id, $cogs_acc_id, $start_date, $end_date, $user_id, $payable_acc_id, $start_date, $end_date, $user_id);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    }

} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'in_transaction') && $conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>