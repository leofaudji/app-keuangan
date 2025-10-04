<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    die('Unauthorized');
}

require_once __DIR__ . '/../includes/bootstrap.php';
// Muat kelas-kelas yang diperlukan karena kita menggunakan ReportBuilder
require_once PROJECT_ROOT . '/includes/PDF.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/ReportBuilderInterface.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/NeracaReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/LabaRugiReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/ArusKasReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/LaporanHarianReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/BukuBesarReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/BukuBesarDataTrait.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/DaftarJurnalReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/LaporanLabaDitahanReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/PertumbuhanLabaReportBuilder.php';

$conn = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

$report_type = $_GET['report'] ?? '';
$format = $_GET['format'] ?? 'csv';

if ($format !== 'csv') {
    http_response_code(400);
    die('Format tidak didukung.');
}

$filename = "laporan_{$report_type}_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

try {
    switch ($report_type) {
        case 'neraca':
            $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
            $builder = new NeracaReportBuilder(new PDF(), $conn, ['user_id' => $user_id, 'tanggal' => $tanggal]);
            
            // Gunakan reflection untuk mengakses metode private fetchData
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('fetchData');
            $method->setAccessible(true);
            $data = $method->invoke($builder, $user_id, $tanggal);

            fputcsv($output, ['Laporan Posisi Keuangan (Neraca)']);
            fputcsv($output, ['Per Tanggal:', date('d F Y', strtotime($tanggal))]);
            fputcsv($output, []); // Baris kosong

            fputcsv($output, ['Tipe Akun', 'Nama Akun', 'Saldo Akhir']);

            $asetData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Aset');
            $liabilitasData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Liabilitas');
            $ekuitasData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Ekuitas');

            $totalAset = 0;
            foreach ($asetData as $item) {
                fputcsv($output, ['Aset', $item['nama_akun'], $item['saldo_akhir']]);
                $totalAset += $item['saldo_akhir'];
            }
            fputcsv($output, ['TOTAL ASET', '', $totalAset]);
            fputcsv($output, []);

            $totalLiabilitas = 0;
            foreach ($liabilitasData as $item) {
                fputcsv($output, ['Liabilitas', $item['nama_akun'], $item['saldo_akhir']]);
                $totalLiabilitas += $item['saldo_akhir'];
            }
            fputcsv($output, ['TOTAL LIABILITAS', '', $totalLiabilitas]);
            fputcsv($output, []);

            $totalEkuitas = 0;
            foreach ($ekuitasData as $item) {
                fputcsv($output, ['Ekuitas', $item['nama_akun'], $item['saldo_akhir']]);
                $totalEkuitas += $item['saldo_akhir'];
            }
            fputcsv($output, ['TOTAL EKUITAS', '', $totalEkuitas]);
            fputcsv($output, []);

            fputcsv($output, ['TOTAL LIABILITAS DAN EKUITAS', '', $totalLiabilitas + $totalEkuitas]);
            break;

        case 'laba-rugi':
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $builder = new LabaRugiReportBuilder(new PDF(), $conn, ['user_id' => $user_id, 'start' => $start, 'end' => $end]);

            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('fetchData');
            $method->setAccessible(true);
            $data = $method->invoke($builder, $user_id, $start, $end);

            fputcsv($output, ['Laporan Laba Rugi']);
            fputcsv($output, ['Periode:', date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end))]);
            fputcsv($output, []);

            fputcsv($output, ['Kategori', 'Nama Akun', 'Total']);
            foreach ($data['pendapatan'] as $item) {
                fputcsv($output, ['Pendapatan', $item['nama_akun'], $item['total']]);
            }
            fputcsv($output, ['TOTAL PENDAPATAN', '', $data['summary']['total_pendapatan']]);
            fputcsv($output, []);

            foreach ($data['beban'] as $item) {
                fputcsv($output, ['Beban', $item['nama_akun'], $item['total']]);
            }
            fputcsv($output, ['TOTAL BEBAN', '', $data['summary']['total_beban']]);
            fputcsv($output, []);

            fputcsv($output, ['LABA (RUGI) BERSIH', '', $data['summary']['laba_bersih']]);
            break;

        case 'arus-kas':
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $builder = new ArusKasReportBuilder(new PDF(), $conn, ['user_id' => $user_id, 'start' => $start, 'end' => $end]);

            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('fetchData');
            $method->setAccessible(true);
            $data = $method->invoke($builder, $user_id, $start, $end);

            fputcsv($output, ['Laporan Arus Kas']);
            fputcsv($output, ['Periode:', date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end))]);
            fputcsv($output, []);

            fputcsv($output, ['Kategori', 'Keterangan', 'Jumlah']);
            fputcsv($output, ['Arus Kas dari Aktivitas Operasi']);
            foreach ($data['arus_kas_operasi']['details'] as $keterangan => $jumlah) {
                fputcsv($output, ['', $keterangan, $jumlah]);
            }
            fputcsv($output, ['Total Arus Kas Operasi', '', $data['arus_kas_operasi']['total']]);
            fputcsv($output, []);

            fputcsv($output, ['Arus Kas dari Aktivitas Investasi']);
            foreach ($data['arus_kas_investasi']['details'] as $keterangan => $jumlah) {
                fputcsv($output, ['', $keterangan, $jumlah]);
            }
            fputcsv($output, ['Total Arus Kas Investasi', '', $data['arus_kas_investasi']['total']]);
            fputcsv($output, []);

            fputcsv($output, ['Arus Kas dari Aktivitas Pendanaan']);
            foreach ($data['arus_kas_pendanaan']['details'] as $keterangan => $jumlah) {
                fputcsv($output, ['', $keterangan, $jumlah]);
            }
            fputcsv($output, ['Total Arus Kas Pendanaan', '', $data['arus_kas_pendanaan']['total']]);
            fputcsv($output, []);

            fputcsv($output, ['Kenaikan (Penurunan) Bersih Kas', '', $data['kenaikan_penurunan_kas']]);
            fputcsv($output, ['Saldo Kas pada Awal Periode', '', $data['saldo_kas_awal']]);
            fputcsv($output, ['Saldo Kas pada Akhir Periode', '', $data['saldo_kas_awal'] + $data['kenaikan_penurunan_kas']]);
            break;

        case 'laporan-harian':
            $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
            $builder = new LaporanHarianReportBuilder(new PDF(), $conn, ['user_id' => $user_id, 'tanggal' => $tanggal]);

            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('fetchData');
            $method->setAccessible(true);
            $data = $method->invoke($builder, $user_id, $tanggal);

            fputcsv($output, ['Laporan Transaksi Harian']);
            fputcsv($output, ['Tanggal:', date('d F Y', strtotime($tanggal))]);
            fputcsv($output, []);

            fputcsv($output, ['Saldo Awal Hari Ini', $data['saldo_awal']]);
            fputcsv($output, []);

            fputcsv($output, ['ID/Ref', 'Keterangan', 'Akun Terkait', 'Pemasukan', 'Pengeluaran']);

            if (empty($data['transaksi'])) {
                fputcsv($output, ['Tidak ada transaksi pada tanggal ini.']);
            } else {
                foreach ($data['transaksi'] as $tx) {
                    $refDisplay = $tx['ref'] ?: strtoupper($tx['source']).'-'.$tx['id'];
                    fputcsv($output, [$refDisplay, $tx['keterangan'], $tx['akun_terkait'], $tx['pemasukan'], $tx['pengeluaran']]);
                }
            }

            fputcsv($output, []);
            fputcsv($output, ['TOTAL', '', '', $data['total_pemasukan'], $data['total_pengeluaran']]);
            fputcsv($output, ['Saldo Akhir Hari Ini', '', '', '', $data['saldo_akhir']]);

            break;

        case 'buku-besar': {
            // Buat kelas anonim untuk menggunakan trait
            $dataFetcher = new class { use BukuBesarDataTrait; };

            $account_id = (int)($_GET['account_id'] ?? 0);
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');

            $data = $dataFetcher->fetchBukuBesarData($conn, $user_id, $account_id, $start_date, $end_date);

            fputcsv($output, ['Laporan Buku Besar']);
            fputcsv($output, ['Akun:', $data['account_info']['kode_akun'] . ' - ' . $data['account_info']['nama_akun']]);
            fputcsv($output, ['Periode:', date('d M Y', strtotime($start_date)) . ' s/d ' . date('d M Y', strtotime($end_date))]);
            fputcsv($output, []);

            fputcsv($output, ['Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo']);
            fputcsv($output, ['Saldo Awal', '', '', '', $data['saldo_awal']]);

            $saldoBerjalan = $data['saldo_awal'];
            $saldoNormal = $data['account_info']['saldo_normal'];

            foreach ($data['transactions'] as $tx) {
                $debit = (float)$tx['debit'];
                $kredit = (float)$tx['kredit'];
                
                if ($saldoNormal === 'Debit') {
                    $saldoBerjalan += $debit - $kredit;
                } else { // Kredit
                    $saldoBerjalan += $kredit - $debit;
                }

                fputcsv($output, [date('d-m-Y', strtotime($tx['tanggal'])), $tx['keterangan'], $debit, $kredit, $saldoBerjalan]);
            }

            fputcsv($output, []);
            fputcsv($output, ['Saldo Akhir', '', '', '', $saldoBerjalan]);

            break;
        }

        case 'daftar-jurnal': {
            $search = $_GET['search'] ?? '';
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';

            $builder = new DaftarJurnalReportBuilder(new PDF(), $conn, []);
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('fetchData');
            $method->setAccessible(true);
            $data = $method->invoke($builder, $user_id, $search, $start_date, $end_date);

            fputcsv($output, ['Daftar Entri Jurnal']);
            if (!empty($start_date) && !empty($end_date)) {
                fputcsv($output, ['Periode:', date('d M Y', strtotime($start_date)) . ' s/d ' . date('d M Y', strtotime($end_date))]);
            }
            fputcsv($output, []);
            fputcsv($output, ['No. Referensi', 'Tanggal', 'Keterangan', 'Akun', 'Debit', 'Kredit']);

            foreach ($data as $line) {
                fputcsv($output, [$line['ref'], $line['tanggal'], $line['keterangan'], $line['nama_akun'], $line['debit'], $line['kredit']]);
            }
            break;
        }

        case 'laporan-laba-ditahan': {
            $start_date = $_GET['start_date'] ?? date('Y-01-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-d');

            $builder = new LaporanLabaDitahanReportBuilder(new PDF(), $conn, ['user_id' => $user_id, 'start_date' => $start_date, 'end_date' => $end_date]);
            
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('fetchData');
            $method->setAccessible(true);
            $data = $method->invoke($builder, $user_id, $start_date, $end_date);

            fputcsv($output, ['Laporan Perubahan Laba Ditahan']);
            fputcsv($output, ['Periode:', date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date))]);
            fputcsv($output, []);

            fputcsv($output, ['Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo']);
            fputcsv($output, ['Saldo Awal per ' . date('d M Y', strtotime($start_date)), '', '', '', $data['saldo_awal']]);

            $saldoBerjalan = (float)$data['saldo_awal'];
            foreach ($data['transactions'] as $tx) {
                $debit = (float)$tx['debit'];
                $kredit = (float)$tx['kredit'];
                $saldoBerjalan += $kredit - $debit;

                fputcsv($output, [
                    date('d-m-Y', strtotime($tx['tanggal'])),
                    $tx['keterangan'],
                    $debit,
                    $kredit,
                    $saldoBerjalan
                ]);
            }
            fputcsv($output, ['Saldo Akhir per ' . date('d M Y', strtotime($end_date)), '', '', '', $saldoBerjalan]);
            break;
        }

        case 'anggaran': {
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $bulan = (int)($_GET['bulan'] ?? date('m'));
            $compare = isset($_GET['compare']) && $_GET['compare'] === 'true';
            $tahun_lalu = $tahun - 1;
            $namaBulan = DateTime::createFromFormat('!m', $bulan)->format('F');

            // Logika query disalin dari api/anggaran_handler.php yang sudah mendukung perbandingan
            $stmt = $conn->prepare("
                SELECT 
                    a.nama_akun,
                    COALESCE(ang_current.jumlah_anggaran / 12, 0) as anggaran_bulanan,
                    COALESCE(realisasi_current.total_beban, 0) as realisasi_belanja,
                    COALESCE(realisasi_prev.total_beban, 0) as realisasi_belanja_lalu
                FROM accounts a
                LEFT JOIN (
                    SELECT account_id, jumlah_anggaran 
                    FROM anggaran 
                    WHERE user_id = ? AND periode_tahun = ?
                ) ang_current ON a.id = ang_current.account_id
                LEFT JOIN (
                    SELECT account_id, SUM(debit - kredit) as total_beban
                    FROM general_ledger
                    WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                    GROUP BY account_id
                ) realisasi_current ON a.id = realisasi_current.account_id
                LEFT JOIN (
                    SELECT account_id, SUM(debit - kredit) as total_beban
                    FROM general_ledger
                    WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                    GROUP BY account_id
                ) realisasi_prev ON a.id = realisasi_prev.account_id
                WHERE a.user_id = ? AND a.tipe_akun = 'Beban'
                ORDER BY a.kode_akun
            ");
            $stmt->bind_param('iiiiiiiii', $user_id, $tahun, $user_id, $tahun, $bulan, $user_id, $tahun_lalu, $bulan, $user_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            fputcsv($output, ['Laporan Anggaran vs Realisasi']);
            fputcsv($output, ['Periode:', $namaBulan . ' ' . $tahun]);
            if ($compare) {
                fputcsv($output, ['Mode:', 'Perbandingan dengan Tahun ' . $tahun_lalu]);
            }
            fputcsv($output, []);

            if ($compare) {
                fputcsv($output, ['Akun Beban', 'Anggaran ' . $tahun, 'Realisasi ' . $tahun, 'Realisasi ' . $tahun_lalu, 'Penggunaan (%)']);
            } else {
                fputcsv($output, ['Akun Beban', 'Anggaran Bulanan', 'Realisasi Belanja', 'Sisa Anggaran', 'Penggunaan (%)']);
            }

            foreach ($data as $row) {
                $sisa_anggaran = (float)$row['anggaran_bulanan'] - (float)$row['realisasi_belanja'];
                $persentase = ((float)$row['anggaran_bulanan'] > 0) ? (((float)$row['realisasi_belanja'] / (float)$row['anggaran_bulanan']) * 100) : 0;
                if ($compare) {
                    fputcsv($output, [
                        $row['nama_akun'], $row['anggaran_bulanan'], $row['realisasi_belanja'], $row['realisasi_belanja_lalu'], number_format($persentase, 2) . '%'
                    ]);
                } else {
                    fputcsv($output, [
                        $row['nama_akun'], $row['anggaran_bulanan'], $row['realisasi_belanja'], $sisa_anggaran, number_format($persentase, 2) . '%'
                    ]);
                }
            }
            break;
        }

        case 'laporan-pertumbuhan-laba': {
            // Re-use the logic from the Report Builder
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $view_mode = $_GET['view_mode'] ?? 'monthly';
            $compare = isset($_GET['compare']) && $_GET['compare'] === 'true';

            $builder = new PertumbuhanLabaReportBuilder(new PDF(), $conn, $_GET + ['user_id' => $user_id]);
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('fetchData');
            $method->setAccessible(true);
            $data = $method->invoke($builder);

            fputcsv($output, ['Laporan Pertumbuhan Laba']);
            fputcsv($output, ['Tahun:', $tahun, 'Tampilan:', ucfirst($view_mode)]);
            fputcsv($output, []);

            $period_alias = 'bulan';
            if ($view_mode === 'quarterly') {
                $period_alias = 'triwulan';
            } elseif ($view_mode === 'yearly') {
                $period_alias = 'tahun';
            }

            $headers = [ucfirst($period_alias), 'Laba Bersih ' . $tahun];
            if ($compare) $headers[] = 'Laba Bersih ' . $tahun_lalu;
            fputcsv($output, $headers);

            $months = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            $quarters = ["Q1", "Q2", "Q3", "Q4"];

            foreach ($data as $row) {
                $periodName = '';
                if ($view_mode === 'quarterly') $periodName = $quarters[$row['triwulan'] - 1];
                elseif ($view_mode === 'yearly') $periodName = $row['tahun'];
                else $periodName = $months[$row['bulan'] - 1];

                $csv_row = [$periodName, $row['laba_bersih']];
                if ($compare) $csv_row[] = $row['laba_bersih_lalu'];
                fputcsv($output, $csv_row);
            }
            break;
        }

        default:
            fputcsv($output, ['Error: Tipe laporan tidak dikenal.']);
            break;
    }
} catch (Exception $e) {
    fputcsv($output, ['Error:', $e->getMessage()]);
}

fclose($output);
exit;
?>