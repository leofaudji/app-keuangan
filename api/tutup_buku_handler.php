<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$user_id = 1; // Data dimiliki oleh user_id 1
$logged_in_user_id = $_SESSION['user_id'];

try {
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'list_history') {
        $stmt = $conn->prepare("
            SELECT id, tanggal, keterangan 
            FROM jurnal_entries 
            WHERE user_id = ? AND keterangan LIKE 'Jurnal Penutup Periode%'
            ORDER BY tanggal DESC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $history]);

    } elseif ($action === 'process_closing') {
        $closing_date = $_POST['closing_date'] ?? '';
        if (empty($closing_date)) {
            throw new Exception("Tanggal tutup buku wajib diisi.");
        }

        $year = date('Y', strtotime($closing_date));
        $keterangan_jurnal = "Jurnal Penutup Periode " . $year;

        // Cek apakah jurnal penutup untuk tahun ini sudah ada
        $stmt_check = $conn->prepare("SELECT id FROM jurnal_entries WHERE user_id = ? AND keterangan = ?");
        $stmt_check->bind_param('is', $user_id, $keterangan_jurnal);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("Jurnal penutup untuk periode {$year} sudah pernah dibuat.");
        }
        $stmt_check->close();

        // Ambil ID akun Laba Ditahan dari pengaturan
        $retained_earnings_acc_id = (int)get_setting('retained_earnings_account_id', 0);
        if ($retained_earnings_acc_id === 0) {
            throw new Exception("Akun Laba Ditahan (Retained Earnings) belum diatur di Pengaturan.");
        }

        // Hitung total saldo semua akun Pendapatan dan Beban sampai tanggal tutup buku
        $stmt_balances = $conn->prepare("
            SELECT
                a.id, a.nama_akun, a.tipe_akun,
                -- Untuk akun L/R, saldo dihitung dari mutasi dalam periode saja. Saldo awal YTD dianggap 0.
                COALESCE(SUM(
                    CASE
                        WHEN a.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit
                        ELSE gl.debit - gl.kredit
                    END
                ), 0) as saldo_akhir
            FROM accounts a
            LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal <= ?
            WHERE a.user_id = ? AND a.tipe_akun IN ('Pendapatan', 'Beban')
            GROUP BY a.id
            HAVING saldo_akhir != 0
        ");
        $stmt_balances->bind_param('si', $closing_date, $user_id);
        $stmt_balances->execute();
        $accounts_to_close = $stmt_balances->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_balances->close();

        if (empty($accounts_to_close)) {
            throw new Exception("Tidak ada saldo pendapatan atau beban yang perlu ditutup.");
        }

        $total_pendapatan = 0;
        $total_beban = 0;
        $jurnal_lines = [];

        foreach ($accounts_to_close as $acc) {
            $saldo = (float)$acc['saldo_akhir'];
            if ($acc['tipe_akun'] === 'Pendapatan') {
                $total_pendapatan += $saldo;
                // Untuk menutup akun pendapatan (saldo normal Kredit), kita Debit
                $jurnal_lines[] = ['account_id' => $acc['id'], 'debit' => $saldo, 'kredit' => 0];
            } else { // Beban
                $total_beban += $saldo;
                // Untuk menutup akun beban (saldo normal Debit), kita Kredit
                $jurnal_lines[] = ['account_id' => $acc['id'], 'debit' => 0, 'kredit' => $saldo];
            }
        }

        $laba_bersih = $total_pendapatan - $total_beban;

        // Tambahkan baris untuk Laba Ditahan
        if ($laba_bersih > 0) { // Laba, menambah ekuitas (Kredit)
            $jurnal_lines[] = ['account_id' => $retained_earnings_acc_id, 'debit' => 0, 'kredit' => $laba_bersih];
        } elseif ($laba_bersih < 0) { // Rugi, mengurangi ekuitas (Debit)
            $jurnal_lines[] = ['account_id' => $retained_earnings_acc_id, 'debit' => abs($laba_bersih), 'kredit' => 0];
        }

        // --- Proses Pembuatan Jurnal ---
        $conn->begin_transaction();
        try {
            // 1. Insert header
            $stmt_header = $conn->prepare("INSERT INTO jurnal_entries (user_id, tanggal, keterangan, created_by) VALUES (?, ?, ?, ?)");
            $stmt_header->bind_param('issi', $user_id, $closing_date, $keterangan_jurnal, $logged_in_user_id);
            $stmt_header->execute();
            $jurnal_entry_id = $conn->insert_id;
            $stmt_header->close();

            $nomor_referensi_jurnal = 'CLS-' . $jurnal_entry_id;

            // 2. Insert details & general ledger
            $stmt_detail = $conn->prepare("INSERT INTO jurnal_details (jurnal_entry_id, account_id, debit, kredit) VALUES (?, ?, ?, ?)");
            $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'jurnal', ?)");

            foreach ($jurnal_lines as $line) {
                $stmt_detail->bind_param('iidd', $jurnal_entry_id, $line['account_id'], $line['debit'], $line['kredit']);
                $stmt_detail->execute();
                $stmt_gl->bind_param('isssiddii', $user_id, $closing_date, $keterangan_jurnal, $nomor_referensi_jurnal, $line['account_id'], $line['debit'], $line['kredit'], $jurnal_entry_id, $logged_in_user_id);
                $stmt_gl->execute();
            }
            $stmt_detail->close();
            $stmt_gl->close();

            // 3. Update tanggal kunci periode di pengaturan
            $stmt_lock = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('period_lock_date', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt_lock->bind_param('s', $closing_date);
            $stmt_lock->execute();
            $stmt_lock->close();

            $conn->commit();
            log_activity($_SESSION['username'], 'Tutup Buku', "Jurnal penutup untuk periode {$year} berhasil dibuat.");
            echo json_encode(['status' => 'success', 'message' => "Proses tutup buku untuk periode {$year} berhasil. Jurnal penutup telah dibuat."]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } elseif ($action === 'reverse_closing') {
        $id_to_reverse = (int)($_POST['id'] ?? 0);
        if ($id_to_reverse <= 0) {
            throw new Exception("ID Jurnal Penutup tidak valid.");
        }

        $conn->begin_transaction();
        try {
            // 1. Verifikasi bahwa ini adalah Jurnal Penutup yang paling baru
            $stmt_latest = $conn->prepare("SELECT id, tanggal FROM jurnal_entries WHERE user_id = ? AND keterangan LIKE 'Jurnal Penutup Periode%' ORDER BY tanggal DESC LIMIT 1");
            $stmt_latest->bind_param('i', $user_id);
            $stmt_latest->execute();
            $latest_closing = $stmt_latest->get_result()->fetch_assoc();
            $stmt_latest->close();

            if (!$latest_closing || (int)$latest_closing['id'] !== $id_to_reverse) {
                throw new Exception("Hanya Jurnal Penutup yang paling baru yang dapat dibatalkan.");
            }

            // 2. Ambil semua detail dari jurnal penutup yang asli
            $stmt_original = $conn->prepare("SELECT * FROM jurnal_details WHERE jurnal_entry_id = ?");
            $stmt_original->bind_param('i', $id_to_reverse);
            $stmt_original->execute();
            $original_lines = $stmt_original->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_original->close();

            if (empty($original_lines)) {
                throw new Exception("Detail Jurnal Penutup asli tidak ditemukan.");
            }

            // 3. Buat Jurnal Pembalik (Reversing Entry)
            $reversal_date = $latest_closing['tanggal'];
            $reversal_keterangan = "PEMBATALAN: Jurnal Penutup Periode " . date('Y', strtotime($reversal_date));

            // Buat header jurnal pembalik
            $stmt_header = $conn->prepare("INSERT INTO jurnal_entries (user_id, tanggal, keterangan, created_by) VALUES (?, ?, ?, ?)");
            $stmt_header->bind_param('issi', $user_id, $reversal_date, $reversal_keterangan, $logged_in_user_id);
            $stmt_header->execute();
            $reversal_jurnal_id = $conn->insert_id;
            $stmt_header->close();

            $nomor_referensi_jurnal = 'REV-' . $reversal_jurnal_id;

            // Buat detail jurnal pembalik (debit jadi kredit, kredit jadi debit)
            $stmt_detail = $conn->prepare("INSERT INTO jurnal_details (jurnal_entry_id, account_id, debit, kredit) VALUES (?, ?, ?, ?)");
            $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'jurnal', ?)");

            foreach ($original_lines as $line) {
                $reversed_debit = $line['kredit'];
                $reversed_kredit = $line['debit'];
                $stmt_detail->bind_param('iidd', $reversal_jurnal_id, $line['account_id'], $reversed_debit, $reversed_kredit);
                $stmt_detail->execute();
                $stmt_gl->bind_param('isssiddii', $user_id, $reversal_date, $reversal_keterangan, $nomor_referensi_jurnal, $line['account_id'], $reversed_debit, $reversed_kredit, $reversal_jurnal_id, $logged_in_user_id);
                $stmt_gl->execute();
            }
            $stmt_detail->close();
            $stmt_gl->close();

            // 4. Tandai Jurnal Penutup yang asli sebagai dibatalkan agar tidak muncul di histori lagi.
            // Ini menjaga jejak audit tetap utuh di database.
            $stmt_void = $conn->prepare("UPDATE jurnal_entries SET keterangan = CONCAT('[DIBATALKAN] ', keterangan) WHERE id = ?");
            $stmt_void->bind_param('i', $id_to_reverse);
            $stmt_void->execute();
            $stmt_void->close();


            // 5. Atur ulang `period_lock_date` ke tanggal tutup buku sebelumnya
            $stmt_prev_lock = $conn->prepare("SELECT tanggal FROM jurnal_entries WHERE user_id = ? AND keterangan LIKE 'Jurnal Penutup Periode%' AND id != ? ORDER BY tanggal DESC LIMIT 1");
            $stmt_prev_lock->bind_param('ii', $user_id, $id_to_reverse);
            $stmt_prev_lock->execute();
            $prev_lock = $stmt_prev_lock->get_result()->fetch_assoc();
            $stmt_prev_lock->close();

            if ($prev_lock) {
                // Update ke tanggal kunci sebelumnya
                $stmt_update_lock = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'period_lock_date'");
                $stmt_update_lock->bind_param('s', $prev_lock['tanggal']);
                $stmt_update_lock->execute();
                $stmt_update_lock->close();
            } else {
                // Jika tidak ada lagi, hapus kuncinya
                $conn->query("DELETE FROM settings WHERE setting_key = 'period_lock_date'");
            }

            $conn->commit();
            log_activity($_SESSION['username'], 'Batal Tutup Buku', "Jurnal penutup ID {$id_to_reverse} telah dibatalkan.");
            echo json_encode(['status' => 'success', 'message' => 'Jurnal penutup berhasil dibatalkan dan periode telah dibuka kembali.']);

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