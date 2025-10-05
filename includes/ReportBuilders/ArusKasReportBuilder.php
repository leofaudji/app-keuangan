<?php
require_once __DIR__ . '/ReportBuilderInterface.php';
require_once PROJECT_ROOT . '/includes/Repositories/LaporanRepository.php';

class ArusKasReportBuilder implements ReportBuilderInterface
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
        $start_date = $this->params['start'] ?? date('Y-m-01');
        $end_date = $this->params['end'] ?? date('Y-m-t');

        $this->pdf->SetTitle('Laporan Arus Kas');
        $this->pdf->report_title = 'Laporan Arus Kas';
        $this->pdf->report_period = 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
        $this->pdf->AddPage('P');

        // Gunakan Repository untuk mengambil data mentah
        $repo = new LaporanRepository($this->conn);
        $data = $repo->getArusKasData($user_id, $start_date, $end_date);

        // Proses data mentah menjadi format yang siap dirender
        $processed_data = $this->processData($data);

        $this->render($processed_data);
    }

    private function processData(array $raw_data): array
    {
        $arus_kas_operasi = ['total' => 0, 'details' => []];
        $arus_kas_investasi = ['total' => 0, 'details' => []];
        $arus_kas_pendanaan = ['total' => 0, 'details' => []];

        $add_detail = function(&$details, $key, $amount) {
            if (!isset($details[$key])) $details[$key] = 0;
            $details[$key] += $amount;
        };

        foreach ($raw_data['transactions'] as $row) {
            $jumlah = (float)$row['net_flow'];
            $akun_lawan = $row['nama_akun'];
            $category = $row['cash_flow_category'] ?? 'Operasi';

            if ($category === 'Investasi') {
                $arus_kas_investasi['total'] += $jumlah;
                $add_detail($arus_kas_investasi['details'], $akun_lawan, $jumlah);
            } elseif ($category === 'Pendanaan') {
                $arus_kas_pendanaan['total'] += $jumlah;
                $add_detail($arus_kas_pendanaan['details'], $akun_lawan, $jumlah);
            } else { // Operasi
                $arus_kas_operasi['total'] += $jumlah;
                $add_detail($arus_kas_operasi['details'], $akun_lawan, $jumlah);
            }
        }

        $kenaikan_penurunan_kas = $arus_kas_operasi['total'] + $arus_kas_investasi['total'] + $arus_kas_pendanaan['total'];

        return [
            'arus_kas_operasi' => $arus_kas_operasi,
            'arus_kas_investasi' => $arus_kas_investasi,
            'arus_kas_pendanaan' => $arus_kas_pendanaan,
            'kenaikan_penurunan_kas' => $kenaikan_penurunan_kas,
            'saldo_kas_awal' => $raw_data['saldo_kas_awal'],
            'saldo_kas_akhir_terhitung' => $raw_data['saldo_kas_awal'] + $kenaikan_penurunan_kas
        ];
    }

    private function render(array $data): void
    {
        extract($data);

        $renderSection = function($title, $sectionData) {
            $this->pdf->SetFont('Helvetica', 'B', 11);
            $this->pdf->Cell(0, 7, $title, 0, 1);
            $this->pdf->SetFont('Helvetica', '', 10);
            if (empty($sectionData['details'])) {
                $this->pdf->Cell(0, 6, 'Tidak ada aktivitas.', 0, 1);
            } else {
                foreach ($sectionData['details'] as $keterangan => $jumlah) {
                    $this->pdf->Cell(100, 6, $keterangan, 0, 0);
                    $this->pdf->Cell(90, 6, format_currency_pdf($jumlah), 0, 1, 'R');
                }
            }
            $this->pdf->SetFont('Helvetica', 'B', 10);
            $this->pdf->Cell(100, 6, 'Total Arus Kas ' . str_replace('Arus Kas dari Aktivitas ', '', $title), 'T', 0);
            $this->pdf->Cell(90, 6, format_currency_pdf($sectionData['total']), 'T', 1, 'R');
            $this->pdf->Ln(5);
        };

        $renderSection('Arus Kas dari Aktivitas Operasi', $arus_kas_operasi);
        $renderSection('Arus Kas dari Aktivitas Investasi', $arus_kas_investasi);
        $renderSection('Arus Kas dari Aktivitas Pendanaan', $arus_kas_pendanaan);

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 7, 'Kenaikan (Penurunan) Bersih Kas', 'T', 0);
        $this->pdf->Cell(90, 7, format_currency_pdf($kenaikan_penurunan_kas), 'T', 1, 'R');
        $this->pdf->Cell(100, 7, 'Saldo Kas pada Awal Periode', 0, 0);
        $this->pdf->Cell(90, 7, format_currency_pdf($saldo_kas_awal), 0, 1, 'R');
        $this->pdf->Cell(100, 7, 'Saldo Kas pada Akhir Periode', 'T', 0);
        $this->pdf->Cell(90, 7, format_currency_pdf($saldo_kas_akhir_terhitung), 'T', 1, 'R');

        $this->pdf->signature_date = $this->params['end'] ?? date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }
}