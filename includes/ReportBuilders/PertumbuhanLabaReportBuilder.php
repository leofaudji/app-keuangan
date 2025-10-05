<?php
require_once __DIR__ . '/ReportBuilderInterface.php';
require_once PROJECT_ROOT . '/includes/Repositories/LaporanRepository.php';

class PertumbuhanLabaReportBuilder implements ReportBuilderInterface
{
    private $pdf;
    private $conn;
    private $params;

    public function __construct(PDF $pdf, mysqli $conn, array $params)
    {
        $this->pdf = $pdf;
        $this->conn = $conn;
        $this->params = $params;
    }

    public function build(): void
    {
        $user_id = $this->params['user_id'];
        $tahun = (int)($this->params['tahun'] ?? date('Y'));
        $view_mode = $this->params['view_mode'] ?? 'monthly';
        $compare = ($this->params['compare'] ?? 'false') === 'true';

        $repo = new LaporanRepository($this->conn);
        $data = $repo->getPertumbuhanLabaData($user_id, $tahun, $view_mode, $compare);

        $this->render($data);
    }

    private function render(array $data): void
    {
        $view_mode = $this->params['view_mode'] ?? 'monthly';
        $is_comparing = isset($this->params['compare']) && $this->params['compare'] === 'true';
        $tahun = (int)($this->params['tahun'] ?? date('Y'));

        $this->pdf->SetTitle('Laporan Pertumbuhan Laba');
        $this->pdf->report_title = 'Laporan Pertumbuhan Laba';
        $this->pdf->report_period = 'Tahun: ' . $tahun . ' (Tampilan ' . ucfirst($view_mode) . ')';
        $this->pdf->AddPage('P'); // Ubah ke Portrait

        // --- Render Chart ---
        if (isset($this->params['chart_image']) && !empty($this->params['chart_image'])) {
            $chart_image_data = $this->params['chart_image'];
            // Hapus header data URL
            $img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart_image_data));
            // Buat file sementara
            $tmp_file = tempnam(sys_get_temp_dir(), 'chart_');
            file_put_contents($tmp_file, $img_data);

            // Dapatkan ukuran gambar
            list($width, $height) = getimagesize($tmp_file);
            $aspect_ratio = $height / $width;
            $image_width = 180; // Lebar gambar di PDF
            $image_height = $image_width * $aspect_ratio;

            $this->pdf->Image($tmp_file, $this->pdf->GetX() + 5, $this->pdf->GetY(), $image_width, 0, 'PNG');
            $this->pdf->Ln($image_height + 5); // Beri jarak setelah gambar

            unlink($tmp_file); // Hapus file sementara
        }

        // Headers
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        
        $period_label = 'Periode';
        if ($view_mode === 'monthly' || $view_mode === 'cumulative') $period_label = 'Bulan';
        if ($view_mode === 'quarterly') $period_label = 'Triwulan';
        if ($view_mode === 'yearly') $period_label = 'Tahun';

        $this->pdf->Cell(50, 8, $period_label, 1, 0, 'C', true);
        $this->pdf->Cell(70, 8, 'Laba Bersih ' . $tahun, 1, 0, 'C', true);
        if ($is_comparing) {
            $this->pdf->Cell(70, 8, 'Laba Bersih ' . ($tahun - 1), 1, 0, 'C', true);
        }
        $this->pdf->Ln();

        // Data
        $this->pdf->SetFont('Helvetica', '', 9);
        $months = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
        $quarters = ["Triwulan 1", "Triwulan 2", "Triwulan 3", "Triwulan 4"];

        if (empty($data)) {
            $this->pdf->Cell($is_comparing ? 190 : 120, 10, 'Tidak ada data.', 1, 1, 'C');
            return;
        }

        foreach ($data as $row) {
            $periodName = '';
            if ($view_mode === 'quarterly') $periodName = $quarters[$row['triwulan'] - 1];
            elseif ($view_mode === 'yearly') $periodName = $row['tahun'];
            else $periodName = $months[$row['bulan'] - 1];

            $this->pdf->Cell(50, 7, $periodName, 1, 0);
            $this->pdf->Cell(70, 7, format_currency_pdf($row['laba_bersih']), 1, 0, 'R');
            if ($is_comparing) {
                $this->pdf->Cell(70, 7, format_currency_pdf($row['laba_bersih_lalu']), 1, 0, 'R');
            }
            $this->pdf->Ln();
        }

        $this->pdf->signature_date = $tahun . '-12-31';
        $this->pdf->RenderSignatureBlock();
    }
}