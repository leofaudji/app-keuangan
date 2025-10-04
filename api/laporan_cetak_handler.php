<?php
// Pastikan sesi dimulai di awal, karena file ini dipanggil di tab baru
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/PDF.php';
// Muat semua kelas builder
require_once PROJECT_ROOT . '/includes/ReportBuilders/ReportBuilderInterface.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/NeracaReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/LabaRugiReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/ArusKasReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/LaporanHarianReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/BukuBesarReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/DaftarJurnalReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/LaporanLabaDitahanReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/AnggaranReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/PertumbuhanLabaReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/AnalisisRasioReportBuilder.php';
require_once PROJECT_ROOT . '/includes/ReportBuilders/BukuPanduanReportBuilder.php';

$conn = Database::getInstance()->getConnection();

$report_type = $_REQUEST['report'] ?? '';

// Ambil nama perumahan dari settings untuk header PDF
global $housing_name;
$housing_name = get_setting('app_name', 'Aplikasi Keuangan');

function format_currency_pdf($number) {
    if ($number < 0) {
        return '(Rp ' . number_format(abs($number), 0, ',', '.') . ')';
    }
    return 'Rp ' . number_format($number, 0, ',', '.');
}

$pdf = new PDF();
$pdf->AliasNbPages(); // Penting untuk mengetahui total halaman

// Parameter yang akan diteruskan ke builder
$params = $_REQUEST; // Gunakan $_REQUEST untuk menerima GET dan POST
$params['user_id'] = $_SESSION['user_id'];

// Peta dari tipe laporan ke kelas builder-nya
$builder_map = [
    'neraca' => NeracaReportBuilder::class,
    'laba-rugi' => LabaRugiReportBuilder::class,
    'arus-kas' => ArusKasReportBuilder::class,
    'laporan-harian' => LaporanHarianReportBuilder::class,
    'buku-besar' => BukuBesarReportBuilder::class,
    'daftar-jurnal' => DaftarJurnalReportBuilder::class,
    'laporan-laba-ditahan' => LaporanLabaDitahanReportBuilder::class,
    'anggaran' => AnggaranReportBuilder::class,
    'laporan-pertumbuhan-laba' => PertumbuhanLabaReportBuilder::class,
    'analisis-rasio' => AnalisisRasioReportBuilder::class,
    'buku-panduan' => BukuPanduanReportBuilder::class,
];

try {
    if (isset($builder_map[$report_type])) {
        $builder_class = $builder_map[$report_type];
        
        // Buat instance builder dengan dependensi yang diperlukan
        $builder = new $builder_class($pdf, $conn, $params);
        
        // Jalankan proses build
        $builder->build();

    } else {
        // Jika tipe laporan tidak dikenal
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'Error: Tipe Laporan Tidak Dikenal', 0, 1, 'C');
    }
} catch (Exception $e) {
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'Terjadi Kesalahan', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->MultiCell(0, 6, 'Error: ' . $e->getMessage());
}

$pdf->Output('I', str_replace(' ', '_', $pdf->report_title) . '.pdf');
?>