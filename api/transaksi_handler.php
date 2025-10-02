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

        if ($action === 'get_accounts_for_form') {
            $stmt = $conn->prepare("SELECT id, nama_akun, tipe_akun, is_kas FROM accounts WHERE user_id = ? ORDER BY kode_akun ASC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $all_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $accounts = [
                'kas' => array_values(array_filter($all_accounts, fn($acc) => $acc['is_kas'] == 1)),
                'pendapatan' => array_values(array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] == 'Pendapatan')),
                'beban' => array_values(array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] == 'Beban')),
            ];

            echo json_encode(['status' => 'success', 'data' => $accounts]);
            exit;
        }
        if ($action === 'get_journal_entry') {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID transaksi tidak valid.");

            $stmt = $conn->prepare("
                SELECT 
                    t.id, t.tanggal, t.jenis, t.jumlah, t.keterangan, t.nomor_referensi,
                    t.account_id, t.kas_account_id, t.kas_tujuan_account_id,
                    main_acc.nama_akun as nama_akun_utama, main_acc.saldo_normal as sn_utama,
                    kas_acc.nama_akun as nama_akun_kas, kas_acc.saldo_normal as sn_kas,
                    tujuan_acc.nama_akun as nama_akun_tujuan, tujuan_acc.saldo_normal as sn_tujuan
                FROM transaksi t
                LEFT JOIN accounts main_acc ON t.account_id = main_acc.id
                LEFT JOIN accounts kas_acc ON t.kas_account_id = kas_acc.id
                LEFT JOIN accounts tujuan_acc ON t.kas_tujuan_account_id = tujuan_acc.id
                WHERE t.id = ? AND t.user_id = ?
            ");
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$tx) throw new Exception("Transaksi tidak ditemukan.");

            $jurnal = [];
            if ($tx['jenis'] === 'pemasukan') {
                $jurnal[] = ['akun' => $tx['nama_akun_kas'], 'debit' => $tx['jumlah'], 'kredit' => 0];
                $jurnal[] = ['akun' => $tx['nama_akun_utama'], 'debit' => 0, 'kredit' => $tx['jumlah']];
            } elseif ($tx['jenis'] === 'pengeluaran') {
                $jurnal[] = ['akun' => $tx['nama_akun_utama'], 'debit' => $tx['jumlah'], 'kredit' => 0];
                $jurnal[] = ['akun' => $tx['nama_akun_kas'], 'debit' => 0, 'kredit' => $tx['jumlah']];
            } elseif ($tx['jenis'] === 'transfer') {
                $jurnal[] = ['akun' => $tx['nama_akun_tujuan'], 'debit' => $tx['jumlah'], 'kredit' => 0];
                $jurnal[] = ['akun' => $tx['nama_akun_kas'], 'debit' => 0, 'kredit' => $tx['jumlah']];
            }

            $response = ['status' => 'success', 'data' => ['transaksi' => $tx, 'jurnal' => $jurnal]];
            echo json_encode($response);
            exit;
        }

        // Default action: list transactions
        $limit = (int)($_GET['limit'] ?? 15);
        $page = (int)($_GET['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $search = $_GET['search'] ?? '';
        $bulan = $_GET['bulan'] ?? '';
        $tahun = $_GET['tahun'] ?? '';
        $akun_kas = $_GET['akun_kas'] ?? '';

        $where_clauses = ['t.user_id = ?'];
        $params = ['i', $user_id];

        if (!empty($search)) {
            $where_clauses[] = 't.keterangan LIKE ?';
            $params[0] .= 's';
            $params[] = '%' . $search . '%';
        }
        if (!empty($bulan)) {
            $where_clauses[] = 'MONTH(t.tanggal) = ?';
            $params[0] .= 'i';
            $params[] = $bulan;
        }
        if (!empty($tahun)) {
            $where_clauses[] = 'YEAR(t.tanggal) = ?';
            $params[0] .= 'i';
            $params[] = $tahun;
        }
        if (!empty($akun_kas)) {
            $where_clauses[] = '(t.kas_account_id = ? OR t.kas_tujuan_account_id = ?)';
            $params[0] .= 'ii';
            $params[] = $akun_kas;
            $params[] = $akun_kas;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        // Get total count for pagination
        $total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM transaksi t $where_sql");
        // Use call_user_func_array for dynamic binding
        $bind_params = [&$params[0]];
        for ($i = 1; $i < count($params); $i++) {
            $bind_params[] = &$params[$i];
        }
        call_user_func_array([$total_stmt, 'bind_param'], $bind_params);
        $total_stmt->execute();
        $total_records = $total_stmt->get_result()->fetch_assoc()['total'];
        $total_stmt->close();

        $query = "
            SELECT 
                t.id, t.tanggal, t.jenis, t.jumlah, t.keterangan, t.nomor_referensi,
                main_acc.nama_akun as nama_akun_utama,
                kas_acc.nama_akun as nama_akun_kas,
                tujuan_acc.nama_akun as nama_akun_tujuan
            FROM transaksi t
            LEFT JOIN accounts main_acc ON t.account_id = main_acc.id
            LEFT JOIN accounts kas_acc ON t.kas_account_id = kas_acc.id
            LEFT JOIN accounts tujuan_acc ON t.kas_tujuan_account_id = tujuan_acc.id
            $where_sql
            ORDER BY t.tanggal DESC, t.id DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[0] .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($query);
        // Re-create bind params for the new query
        $bind_params_main = [&$params[0]];
        for ($i = 1; $i < count($params); $i++) {
            $bind_params_main[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params_main);
        $stmt->execute();
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pagination = [
            'current_page' => $page,
            'total_pages' => ceil($total_records / $limit),
            'total_records' => $total_records,
            'limit' => $limit
        ];

        echo json_encode(['status' => 'success', 'data' => $transactions, 'pagination' => $pagination]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $jenis = $_POST['jenis'] ?? '';
                $tanggal = $_POST['tanggal'] ?? '';
                $jumlah = (float)($_POST['jumlah'] ?? 0);
                $keterangan = trim($_POST['keterangan'] ?? '');
                $id = (int)($_POST['id'] ?? 0); // Get ID for update
                $nomor_referensi = trim($_POST['nomor_referensi'] ?? '');

                if (empty($jenis) || empty($tanggal) || $jumlah <= 0) {
                    throw new Exception("Jenis, tanggal, dan jumlah wajib diisi.");
                }

                $account_id = null;
                $kas_account_id = null;
                $kas_tujuan_account_id = null;

                if ($jenis === 'pemasukan') {
                    $kas_account_id = (int)$_POST['kas_account_id_pemasukan'];
                    $account_id = (int)$_POST['account_id_pemasukan'];
                    if (empty($kas_account_id) || empty($account_id)) {
                        throw new Exception("Akun Kas dan Akun Pendapatan wajib diisi.");
                    }
                } elseif ($jenis === 'pengeluaran') {
                    $kas_account_id = (int)$_POST['kas_account_id_pengeluaran'];
                    $account_id = (int)$_POST['account_id_pengeluaran'];
                    if (empty($kas_account_id) || empty($account_id)) {
                        throw new Exception("Akun Kas dan Akun Beban wajib diisi.");
                    }
                } elseif ($jenis === 'transfer') {
                    $kas_account_id = (int)$_POST['kas_account_id_transfer'];
                    $kas_tujuan_account_id = (int)$_POST['kas_tujuan_account_id'];
                    // For transfer, main account_id is not strictly necessary for accounting, but we need a valid ID for the foreign key.
                    // We can use the source kas account as a placeholder.
                    $account_id = $kas_account_id;
                    if (empty($kas_account_id) || empty($kas_tujuan_account_id)) {
                        throw new Exception("Akun Kas Sumber dan Tujuan wajib diisi.");
                    }
                    if ($kas_account_id === $kas_tujuan_account_id) {
                        throw new Exception("Akun sumber dan tujuan tidak boleh sama.");
                    }
                }

                if (empty($account_id) || empty($kas_account_id)) {
                    throw new Exception("Akun tidak valid.");
                }

                // --- Logika Nomor Referensi Otomatis ---
                if (empty($nomor_referensi) && $jenis !== 'transfer') {
                    $prefix_in = get_setting('ref_pemasukan_prefix', 'INV');
                    $prefix_out = get_setting('ref_pengeluaran_prefix', 'EXP');
                    $prefix = ($jenis === 'pemasukan') ? $prefix_in : $prefix_out;
                    $date_parts = explode('-', $tanggal);
                    $year = $date_parts[0];
                    $month = $date_parts[1];

                    // Cari nomor urut terakhir untuk bulan dan tahun ini
                    $stmt_ref = $conn->prepare(
                        "SELECT nomor_referensi FROM transaksi 
                         WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ? AND jenis = ? 
                         ORDER BY id DESC LIMIT 1"
                    );
                    $stmt_ref->bind_param('iiss', $user_id, $year, $month, $jenis);
                    $stmt_ref->execute();
                    $last_ref = $stmt_ref->get_result()->fetch_assoc();
                    $stmt_ref->close();

                    $sequence = 1;
                    if ($last_ref && !empty($last_ref['nomor_referensi'])) {
                        $parts = explode('/', $last_ref['nomor_referensi']);
                        $last_sequence = (int)end($parts);
                        $sequence = $last_sequence + 1;
                    }

                    // Buat nomor referensi baru dengan format: PREFIX/TAHUN/BULAN/001
                    $nomor_referensi = sprintf('%s/%s/%s/%03d', $prefix, $year, $month, $sequence);
                }
                // --- Akhir Logika ---
                
                $conn->begin_transaction();
                
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, tanggal, jenis, jumlah, keterangan, nomor_referensi, account_id, kas_account_id, kas_tujuan_account_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('issdssiiii', $user_id, $tanggal, $jenis, $jumlah, $keterangan, $nomor_referensi, $account_id, $kas_account_id, $kas_tujuan_account_id, $user_id);
                
                if (!$stmt->execute()) {
                    $conn->rollback();
                    throw new Exception("Gagal menyimpan transaksi: " . $stmt->error);
                }
                $transaksi_id = $conn->insert_id;
                $stmt->close();

                // Sinkronisasi ke General Ledger
                $zero = 0.00;
                $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'transaksi', ?)");
                if ($jenis === 'pemasukan') {
                    // Debit Akun Kas, Kredit Akun Pendapatan
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_account_id, $jumlah, $zero, $transaksi_id, $user_id); $stmt_gl->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $account_id, $zero, $jumlah, $transaksi_id, $user_id); $stmt_gl->execute();
                } elseif ($jenis === 'pengeluaran') {
                    // Debit Akun Beban, Kredit Akun Kas
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $account_id, $jumlah, $zero, $transaksi_id, $user_id); $stmt_gl->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_account_id, $zero, $jumlah, $transaksi_id, $user_id); $stmt_gl->execute();
                } elseif ($jenis === 'transfer') {
                    // Debit Akun Kas Tujuan, Kredit Akun Kas Sumber
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_tujuan_account_id, $jumlah, $zero, $transaksi_id, $user_id); $stmt_gl->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_account_id, $zero, $jumlah, $transaksi_id, $user_id); $stmt_gl->execute();
                }
                $stmt_gl->close();

                $conn->commit();
                log_activity($_SESSION['username'], 'Tambah Transaksi', "Transaksi '{$keterangan}' sejumlah {$jumlah} ditambahkan.");
                echo json_encode(['status' => 'success', 'message' => 'Transaksi berhasil ditambahkan.']);
                break;

            case 'get_single':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID transaksi tidak valid.");

                $stmt = $conn->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $id, $user_id);
                $stmt->execute();
                $transaction = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$transaction) throw new Exception("Transaksi tidak ditemukan.");
                
                echo json_encode(['status' => 'success', 'data' => $transaction]);
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $jenis = $_POST['jenis'] ?? '';
                $tanggal = $_POST['tanggal'] ?? '';
                $jumlah = (float)($_POST['jumlah'] ?? 0);
                $keterangan = trim($_POST['keterangan'] ?? '');
                $nomor_referensi = trim($_POST['nomor_referensi'] ?? '');

                if ($id <= 0) throw new Exception("ID transaksi tidak valid untuk diperbarui.");
                if (empty($jenis) || empty($tanggal) || $jumlah <= 0) throw new Exception("Jenis, tanggal, dan jumlah wajib diisi.");

                // Logika validasi akun yang sama dengan 'add'
                $account_id = null;
                $kas_account_id = null;
                $kas_tujuan_account_id = null;

                if ($jenis === 'pemasukan') {
                    $kas_account_id = (int)$_POST['kas_account_id_pemasukan'];
                    $account_id = (int)$_POST['account_id_pemasukan'];
                    if (empty($kas_account_id) || empty($account_id)) throw new Exception("Akun Kas dan Akun Pendapatan wajib diisi.");
                } elseif ($jenis === 'pengeluaran') {
                    $kas_account_id = (int)$_POST['kas_account_id_pengeluaran'];
                    $account_id = (int)$_POST['account_id_pengeluaran'];
                    if (empty($kas_account_id) || empty($account_id)) throw new Exception("Akun Kas dan Akun Beban wajib diisi.");
                } elseif ($jenis === 'transfer') {
                    $kas_account_id = (int)$_POST['kas_account_id_transfer'];
                    $kas_tujuan_account_id = (int)$_POST['kas_tujuan_account_id'];
                    $account_id = $kas_account_id; // Placeholder
                    if (empty($kas_account_id) || empty($kas_tujuan_account_id)) throw new Exception("Akun Kas Sumber dan Tujuan wajib diisi.");
                    if ($kas_account_id === $kas_tujuan_account_id) throw new Exception("Akun sumber dan tujuan tidak boleh sama.");
                }

                if (empty($account_id) || empty($kas_account_id)) throw new Exception("Akun tidak valid.");

                $conn->begin_transaction();

                $stmt = $conn->prepare("
                    UPDATE transaksi SET 
                        jenis = ?, tanggal = ?, jumlah = ?, keterangan = ?, nomor_referensi = ?, 
                        account_id = ?, kas_account_id = ?, kas_tujuan_account_id = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->bind_param('ssdssiiiii', $jenis, $tanggal, $jumlah, $keterangan, $nomor_referensi, $account_id, $kas_account_id, $kas_tujuan_account_id, $id, $user_id);
                if (!$stmt->execute()) { $conn->rollback(); throw new Exception("Gagal memperbarui transaksi: " . $stmt->error); }
                $stmt->close();

                // Hapus entri GL lama dan buat yang baru
                $stmt_delete_gl = $conn->prepare("DELETE FROM general_ledger WHERE ref_id = ? AND ref_type = 'transaksi'");
                $stmt_delete_gl->bind_param('i', $id);
                $stmt_delete_gl->execute();
                $stmt_delete_gl->close();

                $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'transaksi', ?)");
                $zero = 0.00;
                if ($jenis === 'pemasukan') {
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_account_id, $jumlah, $zero, $id, $user_id); $stmt_gl->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $account_id, $zero, $jumlah, $id, $user_id); $stmt_gl->execute();
                } elseif ($jenis === 'pengeluaran') {
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $account_id, $jumlah, $zero, $id, $user_id); $stmt_gl->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_account_id, $zero, $jumlah, $id, $user_id); $stmt_gl->execute();
                } elseif ($jenis === 'transfer') {
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_tujuan_account_id, $jumlah, $zero, $id, $user_id); $stmt_gl->execute();
                    $stmt_gl->bind_param('isssiddii', $user_id, $tanggal, $keterangan, $nomor_referensi, $kas_account_id, $zero, $jumlah, $id, $user_id); $stmt_gl->execute();
                }
                $stmt_gl->close();

                $conn->commit();
                log_activity($_SESSION['username'], 'Update Transaksi', "Transaksi ID {$id} diperbarui.");
                echo json_encode(['status' => 'success', 'message' => 'Transaksi berhasil diperbarui.']);
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("ID transaksi tidak valid.");
                }

                $conn->begin_transaction();

                $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $id, $user_id);
                if (!$stmt->execute()) {
                    $conn->rollback();
                    throw new Exception("Gagal menghapus transaksi: " . $stmt->error);
                }
                $stmt->close();

                // Hapus juga dari General Ledger
                $stmt_gl = $conn->prepare("DELETE FROM general_ledger WHERE ref_id = ? AND ref_type = 'transaksi'");
                $stmt_gl->bind_param('i', $id);
                $stmt_gl->execute();
                $stmt_gl->close();

                $conn->commit();
                log_activity($_SESSION['username'], 'Hapus Transaksi', "Transaksi ID {$id} dihapus.");
                echo json_encode(['status' => 'success', 'message' => 'Transaksi berhasil dihapus.']);
                break;

            default:
                throw new Exception("Aksi tidak valid.");
        }
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>