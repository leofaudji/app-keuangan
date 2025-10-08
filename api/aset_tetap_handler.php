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
$logged_in_user_id = $_SESSION['user_id'];

try {
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'list') {
        $stmt = $conn->prepare("
            SELECT 
                fa.*,
                COALESCE(dep.total_depreciation, 0) as akumulasi_penyusutan,
                (fa.harga_perolehan - COALESCE(dep.total_depreciation, 0)) as nilai_buku
            FROM fixed_assets fa
            LEFT JOIN (
                SELECT 
                    SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan, 'Aset ID: ', -1), ')', 1) as asset_id,
                    SUM(kredit) as total_depreciation
                FROM general_ledger
                WHERE keterangan LIKE 'Penyusutan bulanan%' AND kredit > 0
                GROUP BY asset_id
            ) dep ON fa.id = dep.asset_id
            WHERE fa.user_id = ?
            ORDER BY fa.tanggal_akuisisi DESC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $assets]);

    } elseif ($action === 'get_accounts') {
        $stmt = $conn->prepare("SELECT id, kode_akun, nama_akun, tipe_akun FROM accounts WHERE user_id = ? AND tipe_akun IN ('Aset', 'Beban') ORDER BY kode_akun");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $all_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $accounts = [
            'aset' => array_values(array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] == 'Aset')),
            'beban' => array_values(array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] == 'Beban')),
        ];
        echo json_encode(['status' => 'success', 'data' => $accounts]);

    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $nama_aset = trim($_POST['nama_aset']);
        $tanggal_akuisisi = $_POST['tanggal_akuisisi'];
        $harga_perolehan = (float)$_POST['harga_perolehan'];
        $masa_manfaat = (int)$_POST['masa_manfaat'];
        $metode_penyusutan = $_POST['metode_penyusutan'];
        $akun_aset_id = (int)$_POST['akun_aset_id'];
        $akun_akumulasi_penyusutan_id = (int)$_POST['akun_akumulasi_penyusutan_id'];
        $akun_beban_penyusutan_id = (int)$_POST['akun_beban_penyusutan_id'];

        if (empty($nama_aset) || empty($tanggal_akuisisi) || $harga_perolehan <= 0 || $masa_manfaat <= 0) {
            throw new Exception("Data aset tidak lengkap atau tidak valid.");
        }

        if ($id > 0) { // Update
            $stmt = $conn->prepare("UPDATE fixed_assets SET nama_aset=?, tanggal_akuisisi=?, harga_perolehan=?, masa_manfaat=?, metode_penyusutan=?, akun_aset_id=?, akun_akumulasi_penyusutan_id=?, akun_beban_penyusutan_id=?, updated_by=? WHERE id=? AND user_id=?");
            $stmt->bind_param('ssdisiiiiii', $nama_aset, $tanggal_akuisisi, $harga_perolehan, $masa_manfaat, $metode_penyusutan, $akun_aset_id, $akun_akumulasi_penyusutan_id, $akun_beban_penyusutan_id, $logged_in_user_id, $id, $user_id);
        } else { // Add
            $stmt = $conn->prepare("INSERT INTO fixed_assets (user_id, nama_aset, tanggal_akuisisi, harga_perolehan, masa_manfaat, metode_penyusutan, akun_aset_id, akun_akumulasi_penyusutan_id, akun_beban_penyusutan_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssisiiii', $user_id, $nama_aset, $tanggal_akuisisi, $harga_perolehan, $masa_manfaat, $metode_penyusutan, $akun_aset_id, $akun_akumulasi_penyusutan_id, $akun_beban_penyusutan_id, $logged_in_user_id);
        }
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Aset tetap berhasil disimpan.']);

    } elseif ($action === 'get_single') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM fixed_assets WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $asset = $stmt->get_result()->fetch_assoc();
        if (!$asset) throw new Exception("Aset tidak ditemukan.");
        echo json_encode(['status' => 'success', 'data' => $asset]);

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Cek apakah sudah ada jurnal penyusutan terkait
        $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM general_ledger WHERE keterangan LIKE ?");
        $like_pattern = "%Aset ID: $id%";
        $stmt_check->bind_param('s', $like_pattern);
        $stmt_check->execute();
        if ($stmt_check->get_result()->fetch_assoc()['count'] > 0) {
            throw new Exception("Tidak dapat menghapus aset karena sudah ada jurnal penyusutan terkait.");
        }
        $stmt_check->close();

        $stmt = $conn->prepare("DELETE FROM fixed_assets WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Aset berhasil dihapus.']);

    } elseif ($action === 'post_depreciation') {
        $month = (int)$_POST['month'];
        $year = (int)$_POST['year'];
        $posting_date = date('Y-m-t', strtotime("$year-$month-01")); // Selalu post di akhir bulan

        // Ambil semua aset yang masih aktif (belum habis masa manfaatnya)
        $stmt_assets = $conn->prepare("SELECT * FROM fixed_assets WHERE user_id = ? AND tanggal_akuisisi <= ?");
        $stmt_assets->bind_param('is', $user_id, $posting_date);
        $stmt_assets->execute();
        $assets = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_assets->close();

        $conn->begin_transaction();
        $posted_count = 0;

        foreach ($assets as $asset) {
            $asset_id = $asset['id'];
            $penyusutan_bulanan = ($asset['harga_perolehan'] / $asset['masa_manfaat']) / 12;

            // Cek apakah sudah pernah diposting untuk bulan dan aset ini
            $stmt_check = $conn->prepare("SELECT id FROM general_ledger WHERE user_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND keterangan LIKE ?");
            $like_pattern = "%(Aset ID: $asset_id)%";
            $stmt_check->bind_param('iiis', $user_id, $month, $year, $like_pattern);
            $stmt_check->execute();
            $is_posted = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($is_posted) continue; // Lewati jika sudah diposting

            // Cek apakah total penyusutan sudah melebihi harga perolehan
            $stmt_total_dep = $conn->prepare("SELECT COALESCE(SUM(kredit), 0) as total FROM general_ledger WHERE keterangan LIKE ?");
            $stmt_total_dep->bind_param('s', $like_pattern);
            $stmt_total_dep->execute();
            $total_depreciated = $stmt_total_dep->get_result()->fetch_assoc()['total'];
            $stmt_total_dep->close();

            if ($total_depreciated >= $asset['harga_perolehan']) continue; // Lewati jika sudah lunas

            // Pastikan penyusutan terakhir tidak melebihi nilai sisa
            if (($total_depreciated + $penyusutan_bulanan) > $asset['harga_perolehan']) {
                $penyusutan_bulanan = $asset['harga_perolehan'] - $total_depreciated;
            }

            if ($penyusutan_bulanan <= 0) continue;

            // Buat Jurnal
            $keterangan_jurnal = "Penyusutan bulanan {$asset['nama_aset']} (Aset ID: {$asset_id})";
            $nomor_referensi = "DEP/{$year}/{$month}/{$asset_id}";
            $zero = 0.00;

            $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_type, ref_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'jurnal', 0, ?)");

            // (Dr) Beban Penyusutan
            $stmt_gl->bind_param('isssiddi', $user_id, $posting_date, $keterangan_jurnal, $nomor_referensi, $asset['akun_beban_penyusutan_id'], $penyusutan_bulanan, $zero, $logged_in_user_id);
            $stmt_gl->execute();
            // (Cr) Akumulasi Penyusutan
            $stmt_gl->bind_param('isssiddi', $user_id, $posting_date, $keterangan_jurnal, $nomor_referensi, $asset['akun_akumulasi_penyusutan_id'], $zero, $penyusutan_bulanan, $logged_in_user_id);
            $stmt_gl->execute();
            
            $stmt_gl->close();
            $posted_count++;
        }

        $conn->commit();

        if ($posted_count > 0) {
            echo json_encode(['status' => 'success', 'message' => "Berhasil memposting jurnal penyusutan untuk {$posted_count} aset."]);
        } else {
            echo json_encode(['status' => 'info', 'message' => 'Tidak ada penyusutan baru yang diposting. Kemungkinan semua sudah terposting atau sudah lunas.']);
        }

    } else {
        throw new Exception("Aksi tidak valid.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>