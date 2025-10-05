<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class AnalisisRasioReportBuilder implements ReportBuilderInterface
{
    private $pdf;
    private $conn;
    private $params;
    private $ratioDefinitions;
    

    public function __construct(PDF $pdf, mysqli $conn, array $params)
    {
        $this->pdf = $pdf;
        $this->conn = $conn;
        $this->params = $params;

        // Definisi rasio disalin dari main.js untuk konsistensi
        $this->ratioDefinitions = [
            'profit_margin' => [
                'name' => 'Profit Margin (Laba Bersih / Pendapatan)',
                'format' => fn($val) => number_format($val * 100, 2) . '%',
                'interpret' => fn($val) => $val > 0.1 ? 'Sangat Baik' : ($val > 0.05 ? 'Baik' : 'Perlu Perhatian'),
            ],
            'debt_to_equity' => [
                'name' => 'Debt to Equity Ratio (Utang / Ekuitas)',
                'format' => fn($val) => number_format($val, 2),
                'interpret' => fn($val) => $val < 1 ? 'Sehat' : ($val < 2 ? 'Waspada' : 'Berisiko Tinggi'),
            ],
            'debt_to_asset' => [
                'name' => 'Debt to Asset Ratio (Utang / Aset)',
                'format' => fn($val) => number_format($val, 2),
                'interpret' => fn($val) => $val < 0.4 ? 'Sangat Sehat' : ($val < 0.6 ? 'Sehat' : 'Berisiko'),
            ],
            'return_on_equity' => [
                'name' => 'Return on Equity (ROE) (Laba Bersih / Ekuitas)',
                'format' => fn($val) => number_format($val * 100, 2) . '%',
                'interpret' => fn($val) => $val > 0.15 ? 'Sangat Baik' : ($val > 0.05 ? 'Baik' : 'Kurang Efisien'),
            ],
            'return_on_assets' => [
                'name' => 'Return on Assets (ROA) (Laba Bersih / Aset)',
                'format' => fn($val) => number_format($val * 100, 2) . '%',
                'interpret' => fn($val) => $val > 0.1 ? 'Sangat Efisien' : ($val > 0.05 ? 'Efisien' : 'Kurang Efisien'),
            ],
            'asset_turnover' => [
                'name' => 'Asset Turnover Ratio (Pendapatan / Aset)',
                'format' => fn($val) => number_format($val, 2) . 'x',
                'interpret' => fn($val) => $val > 1.5 ? 'Sangat Efisien' : ($val > 1 ? 'Efisien' : 'Kurang Efisien'),
            ]
        ];
    }

    public function build(): void
    {
        $date = $this->params['date'] ?? date('Y-m-d');
        $compare_date = $this->params['compare_date'] ?? null;

        $this->pdf->SetTitle('Analisis Rasio Keuangan');
        $this->pdf->report_title = 'Analisis Rasio Keuangan';
        $period_text = 'Per Tanggal: ' . date('d M Y', strtotime($date));
        if ($compare_date) {
            $period_text .= ' | Pembanding: ' . date('d M Y', strtotime($compare_date));
        }
        $this->pdf->report_period = $period_text;
        $this->pdf->AddPage('P');

        $data = $this->fetchData($date, $compare_date);
        $this->render($data);
    }

    private function fetchData(string $date, ?string $compare_date): array
    {
        // Gunakan Repository untuk konsistensi data
        $repo = new LaporanRepository($this->conn);

        $current_data = $repo->getFinancialSummaryData($this->params['user_id'], $date);
        $previous_data = $compare_date ? $repo->getFinancialSummaryData($this->params['user_id'], $compare_date) : null;

        // Fungsi kalkulasi rasio dipindahkan ke sini agar tidak bergantung pada file API
        $calculateRatios = function (array $data): array {
            $ratios = [];
            $div = fn($a, $b) => ($b == 0) ? 0 : $a / $b;

            $ratios['profit_margin'] = $div($data['laba_bersih'], $data['total_pendapatan']);
            $ratios['debt_to_equity'] = $div($data['total_liabilitas'], $data['total_ekuitas']);
            $ratios['debt_to_asset'] = $div($data['total_liabilitas'], $data['total_aset']);
            $ratios['return_on_equity'] = $div($data['laba_bersih'], $data['total_ekuitas']);
            $ratios['return_on_assets'] = $div($data['laba_bersih'], $data['total_aset']);
            $ratios['asset_turnover'] = $div($data['total_pendapatan'], $data['total_aset']);

            return $ratios;
        };

        $current_ratios = $calculateRatios($current_data);
        $previous_ratios = $previous_data ? $calculateRatios($previous_data) : null;

        return ['current' => $current_ratios, 'previous' => $previous_ratios];
    }

    private function render(array $data): void
    {
        $current = $data['current'];
        $previous = $data['previous'];
        $is_comparison = !!$previous;

        $this->pdf->SetFont('Helvetica', '', 10);
        $this->pdf->SetFillColor(245, 245, 245);

        foreach ($current as $key => $value) {
            if (!isset($this->ratioDefinitions[$key])) continue;

            $def = $this->ratioDefinitions[$key];
            
            $this->pdf->SetFont('Helvetica', 'B', 12);
            $this->pdf->Cell(0, 8, $def['name'], 0, 1, 'L', true);
            
            $this->pdf->SetFont('Helvetica', 'B', 16);
            $this->pdf->Cell(95, 10, $def['format']($value), 0, 0, 'L');

            if ($is_comparison && isset($previous[$key])) {
                $prev_value = $previous[$key];
                $change = $value - $prev_value;
                $change_text = ($change >= 0 ? '+' : '-') . $def['format'](abs($change));
                $this->pdf->SetFont('Helvetica', '', 12);
                $this->pdf->Cell(95, 10, 'vs ' . $def['format']($prev_value) . ' (' . $change_text . ')', 0, 1, 'R');
            } else {
                $this->pdf->Ln(10);
            }

            $this->pdf->SetFont('Helvetica', 'I', 9);
            $this->pdf->Cell(0, 5, 'Interpretasi: ' . $def['interpret']($value), 0, 1);
            $this->pdf->Ln(8);
        }

        $this->pdf->signature_date = $this->params['date'] ?? date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }
}
