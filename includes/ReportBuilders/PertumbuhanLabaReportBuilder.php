<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

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
        $data = $this->fetchData();
        $this->render($data);
    }

    private function fetchData(): array
    {
        // This logic is a direct copy from api/laporan_pertumbuhan_laba_handler.php
        $user_id = $this->params['user_id'];
        $tahun = (int)($this->params['tahun'] ?? date('Y'));
        $view_mode = $this->params['view_mode'] ?? 'monthly';
        $compare = ($this->params['compare'] ?? 'false') === 'true';
        $tahun_lalu = $tahun - 1;

        $is_cumulative = $view_mode === 'cumulative';
        if ($view_mode === 'quarterly') {
            $period_field = 'QUARTER(gl.tanggal)'; $period_alias = 'triwulan'; $period_count = 4;
        } elseif ($view_mode === 'yearly') {
            $period_field = 'YEAR(gl.tanggal)'; $period_alias = 'tahun'; $period_count = 5;
        } else {
            $period_field = 'MONTH(gl.tanggal)'; $period_alias = 'bulan'; $period_count = 12;
        }

        $period_table_parts = [];
        for ($i = 0; $i < $period_count; $i++) {
            $p_val = ($view_mode === 'yearly') ? ($tahun - ($period_count - 1) + $i) : ($i + 1);
            $period_table_parts[] = "SELECT $p_val as period";
        }
        $period_table = '(' . implode(' UNION ', $period_table_parts) . ') as p';

        $years_to_query = [$tahun];
        if ($compare) $years_to_query[] = $tahun_lalu;
        $year_placeholders = implode(',', array_fill(0, count($years_to_query), '?'));

        $stmt = $this->conn->prepare("
            SELECT p.period as $period_alias,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE 0 END), 0) as total_pendapatan,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Beban' THEN gl.debit - gl.kredit ELSE 0 END), 0) as total_beban,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE 0 END), 0) as total_pendapatan_lalu,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Beban' THEN gl.debit - gl.kredit ELSE 0 END), 0) as total_beban_lalu
            FROM $period_table
            LEFT JOIN general_ledger gl ON p.period = $period_field AND gl.user_id = ? AND YEAR(gl.tanggal) IN ($year_placeholders)
            LEFT JOIN accounts acc ON gl.account_id = acc.id AND acc.tipe_akun IN ('Pendapatan', 'Beban')
            GROUP BY p.period ORDER BY p.period ASC
        ");
        $bind_params = array_merge(['iiii', $tahun, $tahun, $tahun_lalu, $tahun_lalu, $user_id], $years_to_query);
        $stmt->bind_param(str_repeat('i', count($bind_params) - 1), ...array_slice($bind_params, 1));
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $report_data = [];
        $laba_bersih_sebelumnya = 0;
        $cumulative_pendapatan = 0; $cumulative_beban = 0;
        $cumulative_pendapatan_lalu = 0; $cumulative_beban_lalu = 0;

        foreach ($result as $row) {
            $laba_bersih = (float)$row['total_pendapatan'] - (float)$row['total_beban'];
            $laba_bersih_lalu = (float)$row['total_pendapatan_lalu'] - (float)$row['total_beban_lalu'];
            
            if ($is_cumulative) {
                $cumulative_pendapatan += (float)$row['total_pendapatan'];
                $cumulative_beban += (float)$row['total_beban'];
                $cumulative_pendapatan_lalu += (float)$row['total_pendapatan_lalu'];
                $cumulative_beban_lalu += (float)$row['total_beban_lalu'];
            }

            if ($is_cumulative && $cumulative_pendapatan == 0 && $cumulative_beban == 0) {
                $display_laba = 0; $display_laba_lalu = 0;
            } else {
                $display_laba = $is_cumulative ? ($cumulative_pendapatan - $cumulative_beban) : $laba_bersih;
                $display_laba_lalu = $is_cumulative ? ($cumulative_pendapatan_lalu - $cumulative_beban_lalu) : $laba_bersih_lalu;
            }

            $report_data[] = [
                $period_alias => (int)$row[$period_alias],
                'laba_bersih' => $display_laba,
                'laba_bersih_lalu' => $display_laba_lalu,
            ];
        }
        return $report_data;
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