<?php
require_once __DIR__ . '/ReportBuilderInterface.php';
require_once PROJECT_ROOT . '/includes/Repositories/LaporanRepository.php';

class AnggaranReportBuilder implements ReportBuilderInterface
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
        $bulan = (int)($this->params['bulan'] ?? date('m'));
        $compare = isset($this->params['compare']) && $this->params['compare'] === 'true';
        $namaBulan = DateTime::createFromFormat('!m', $bulan)->format('F');

        $this->pdf->SetTitle('Laporan Anggaran vs Realisasi');
        $this->pdf->report_title = 'Laporan Anggaran vs Realisasi';
        $period_text = 'Periode: ' . $namaBulan . ' ' . $tahun;
        if ($compare) {
            $period_text .= ' (Dibandingkan dengan ' . ($tahun - 1) . ')';
        }
        $this->pdf->report_period = $period_text;
        $this->pdf->AddPage('P'); // Ubah ke Portrait

        $repo = new LaporanRepository($this->conn);
        $result = $repo->getAnggaranData($user_id, $tahun, $bulan, $compare);
        $this->render($result);
    }

    private function render(array $result): void
    {
        $data = $result['data'];
        $summary = $result['summary'];
        $isComparing = $summary['compare_mode'];
        $tahun = (int)($this->params['tahun'] ?? date('Y'));

        // --- Render Charts ---
        $chartImages = [
            'trend_chart_image' => $this->params['trend_chart_image'] ?? '',
            'budget_chart_image' => $this->params['budget_chart_image'] ?? ''
        ];

        $chartRendered = false;
        foreach ($chartImages as $key => $imageData) {
            if (!empty($imageData)) {
                $chartRendered = true;
                // Hapus header data URL
                $img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
                // Buat file sementara
                $tmp_file = tempnam(sys_get_temp_dir(), 'chart_');
                file_put_contents($tmp_file, $img_data);

                // Dapatkan ukuran gambar
                list($width, $height) = getimagesize($tmp_file);
                $aspect_ratio = $height / $width;
                $image_width = 180; // Lebar gambar di PDF (sesuaikan dengan lebar halaman Portrait)
                $image_height = $image_width * $aspect_ratio;

                // Gambar di tengah kolomnya
                $this->pdf->Image($tmp_file, $this->pdf->GetX() + 5, $this->pdf->GetY(), $image_width, 0, 'PNG');
                $this->pdf->Ln($image_height + 5); // Beri jarak setelah gambar

                unlink($tmp_file); // Hapus file sementara
            }
        }

        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);

        if ($isComparing) {
            $this->pdf->Cell(70, 8, 'Akun Beban', 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, 'Anggaran ' . $tahun, 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, 'Realisasi ' . $tahun, 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, 'Realisasi ' . ($tahun - 1), 1, 1, 'C', true);
        } else {
            $this->pdf->Cell(70, 8, 'Akun Beban', 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, 'Anggaran', 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, 'Realisasi', 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, 'Sisa', 1, 1, 'C', true);
        }

        $this->pdf->SetFont('Helvetica', '', 8);
        if (empty($data)) {
            $this->pdf->Cell($isComparing ? 190 : 190, 10, 'Tidak ada data anggaran untuk periode ini.', 1, 1, 'C');
        } else {
            foreach ($data as $row) {
                if ($isComparing) {
                    $this->pdf->Cell(70, 7, $row['nama_akun'], 1, 0);
                    $this->pdf->Cell(40, 7, format_currency_pdf($row['anggaran_bulanan']), 1, 0, 'R');
                    $this->pdf->Cell(40, 7, format_currency_pdf($row['realisasi_belanja']), 1, 0, 'R');
                    $this->pdf->Cell(40, 7, format_currency_pdf($row['realisasi_belanja_lalu']), 1, 1, 'R');
                } else {
                    $this->pdf->Cell(70, 7, $row['nama_akun'], 1, 0);
                    $this->pdf->Cell(40, 7, format_currency_pdf($row['anggaran_bulanan']), 1, 0, 'R');
                    $this->pdf->Cell(40, 7, format_currency_pdf($row['realisasi_belanja']), 1, 0, 'R');
                    $this->pdf->Cell(40, 7, format_currency_pdf($row['sisa_anggaran']), 1, 1, 'R');
                }
            }
        }

        // Summary
        $this->pdf->SetFont('Helvetica', 'B', 9);
        if ($isComparing) {
            $this->pdf->Cell(70, 8, 'TOTAL', 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, format_currency_pdf($summary['total_anggaran']), 1, 0, 'R', true);
            $this->pdf->Cell(40, 8, format_currency_pdf($summary['total_realisasi']), 1, 0, 'R', true);
            $this->pdf->Cell(40, 8, format_currency_pdf($summary['total_realisasi_lalu']), 1, 1, 'R', true);
        } else {
            $this->pdf->Cell(70, 8, 'TOTAL', 1, 0, 'C', true);
            $this->pdf->Cell(40, 8, format_currency_pdf($summary['total_anggaran']), 1, 0, 'R', true);
            $this->pdf->Cell(40, 8, format_currency_pdf($summary['total_realisasi']), 1, 0, 'R', true);
            $this->pdf->Cell(40, 8, format_currency_pdf($summary['total_sisa']), 1, 1, 'R', true);
        }

        // Ambil bulan dari params, sama seperti tahun
        $bulan = (int)($this->params['bulan'] ?? date('m'));
        $this->pdf->signature_date = date('Y-m-d', strtotime($tahun . '-' . $bulan . '-01'));
        $this->pdf->RenderSignatureBlock();
    }
}
