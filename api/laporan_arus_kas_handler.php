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

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

try {
    // --- Perhitungan Arus Kas ---

    // Saldo Kas Awal Periode
    $beginning_date = date('Y-m-d', strtotime($start_date . ' -1 day'));
    $saldo_kas_awal = get_cash_balance_on_date($conn, $user_id, $beginning_date);

    // Saldo Kas Akhir Periode
    $saldo_kas_akhir = get_cash_balance_on_date($conn, $user_id, $end_date);

    // Ambil semua transaksi dalam periode
    $stmt_trx = $conn->prepare("
        SELECT t.jenis, t.jumlah, a.nama_akun as nama_akun_lawan, a.cash_flow_category
        FROM transaksi t
        LEFT JOIN accounts a ON t.account_id = a.id -- Akun lawan
        WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ?
    ");
    $stmt_trx->bind_param('iss', $user_id, $start_date, $end_date);
    $stmt_trx->execute();
    $transactions = $stmt_trx->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_trx->close();

    // Ambil semua baris jurnal dalam periode
    $stmt_jurnal = $conn->prepare("
        SELECT je.id as jurnal_id, jd.debit, jd.kredit, a.nama_akun, a.cash_flow_category, a.is_kas
        FROM jurnal_details jd
        JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
        JOIN accounts a ON jd.account_id = a.id
        WHERE je.user_id = ? AND je.tanggal BETWEEN ? AND ?
    ");
    $stmt_jurnal->bind_param('iss', $user_id, $start_date, $end_date);
    $stmt_jurnal->execute();
    $journal_lines = $stmt_jurnal->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_jurnal->close();

    // Kelompokkan arus kas
    $arus_kas_operasi = ['total' => 0, 'details' => []];
    $arus_kas_investasi = ['total' => 0, 'details' => []];
    $arus_kas_pendanaan = ['total' => 0, 'details' => []];

    // Helper untuk menambahkan detail
    function add_detail(&$details, $key, $amount) {
        if (!isset($details[$key])) {
            $details[$key] = 0;
        }
        $details[$key] += $amount;
    }

    // Proses dari tabel transaksi (sederhana)
    foreach ($transactions as $tx) {
        if ($tx['jenis'] === 'transfer') continue; // Abaikan transfer internal

        $jumlah = (float)$tx['jumlah'] * ($tx['jenis'] === 'pemasukan' ? 1 : -1);
        $akun_lawan = $tx['nama_akun_lawan'] ?? 'Lain-lain';

        if ($tx['cash_flow_category'] === 'Operasi') {
            $arus_kas_operasi['total'] += $jumlah;
            add_detail($arus_kas_operasi['details'], $akun_lawan, $jumlah);
        } elseif ($tx['cash_flow_category'] === 'Investasi') {
            $arus_kas_investasi['total'] += $jumlah;
            add_detail($arus_kas_investasi['details'], $akun_lawan, $jumlah);
        } elseif ($tx['cash_flow_category'] === 'Pendanaan') {
            $arus_kas_pendanaan['total'] += $jumlah;
            add_detail($arus_kas_pendanaan['details'], $akun_lawan, $jumlah);
        } else {
            // Jika tidak terklasifikasi, masukkan ke Operasi
            $arus_kas_operasi['total'] += $jumlah;
            add_detail($arus_kas_operasi['details'], $akun_lawan, $jumlah);
        }
    }

    // Proses dari tabel jurnal (majemuk)
    $grouped_journals = [];
    foreach ($journal_lines as $line) {
        $grouped_journals[$line['jurnal_id']][] = $line;
    }

    foreach ($grouped_journals as $jurnal_id => $lines) {
        $cash_mutation = 0;
        $non_cash_lines = [];
        foreach ($lines as $line) { 
            if ($line['is_kas'] == 1) {
                $cash_mutation += (float)$line['debit'] - (float)$line['kredit'];
            } else {
                $non_cash_lines[] = $line;
            }
        }

        if ($cash_mutation != 0) {
            // Asumsikan jurnal sederhana (1 kas, 1 non-kas) untuk kategorisasi
            if (count($non_cash_lines) === 1) {
                $akun_lawan = $non_cash_lines[0];
                if ($akun_lawan['cash_flow_category'] === 'Operasi') {
                    $arus_kas_operasi['total'] += $cash_mutation;
                    add_detail($arus_kas_operasi['details'], $akun_lawan['nama_akun'], $cash_mutation);
                } elseif ($akun_lawan['cash_flow_category'] === 'Investasi') {
                    $arus_kas_investasi['total'] += $cash_mutation;
                    add_detail($arus_kas_investasi['details'], $akun_lawan['nama_akun'], $cash_mutation);
                } elseif ($akun_lawan['cash_flow_category'] === 'Pendanaan') {
                    $arus_kas_pendanaan['total'] += $cash_mutation;
                    add_detail($arus_kas_pendanaan['details'], $akun_lawan['nama_akun'], $cash_mutation);
                } else {
                    $arus_kas_operasi['total'] += $cash_mutation;
                    add_detail($arus_kas_operasi['details'], $akun_lawan['nama_akun'], $cash_mutation);
                }
            }
        }
    }

    $kenaikan_penurunan_kas = $arus_kas_operasi['total'] + $arus_kas_investasi['total'] + $arus_kas_pendanaan['total'];

    $response = [
        'status' => 'success',
        'data' => [
            'arus_kas_operasi' => $arus_kas_operasi,
            'arus_kas_investasi' => $arus_kas_investasi,
            'arus_kas_pendanaan' => $arus_kas_pendanaan,
            'kenaikan_penurunan_kas' => $kenaikan_penurunan_kas,
            'saldo_kas_awal' => $saldo_kas_awal,
            'saldo_kas_akhir' => $saldo_kas_akhir,
            // Saldo akhir terhitung untuk verifikasi
            'saldo_kas_akhir_terhitung' => $saldo_kas_awal + $kenaikan_penurunan_kas
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>