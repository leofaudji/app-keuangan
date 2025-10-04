<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

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
        $start = $this->params['start'] ?? date('Y-m-01');
        $end = $this->params['end'] ?? date('Y-m-t');
        $user_id = $this->params['user_id'];

        $this->pdf->SetTitle('Laporan Arus Kas');
        $this->pdf->report_title = 'Laporan Arus Kas';
        $this->pdf->report_period = 'Periode: ' . date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end));
        $this->pdf->AddPage();

        $data = $this->fetchData($user_id, $start, $end);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $start_date, string $end_date): array
    {
        // Logika disalin dari api/laporan_arus_kas_handler.php
        $beginning_date = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $saldo_kas_awal = get_cash_balance_on_date($this->conn, $user_id, $beginning_date);
        $saldo_kas_akhir = get_cash_balance_on_date($this->conn, $user_id, $end_date);
        
        // Logika disalin dari api/laporan_arus_kas_handler.php yang sudah direfaktor
        $arus_kas_operasi = ['total' => 0, 'details' => []];
        $arus_kas_investasi = ['total' => 0, 'details' => []];
        $arus_kas_pendanaan = ['total' => 0, 'details' => []];

        $add_detail = function(&$details, $key, $amount) { if (!isset($details[$key])) $details[$key] = 0; $details[$key] += $amount; };

        $stmt = $this->conn->prepare("SELECT non_cash.nama_akun, non_cash.cash_flow_category, SUM(cash.debit - cash.kredit) as cash_mutation FROM general_ledger cash JOIN accounts cash_acc ON cash.account_id = cash_acc.id JOIN general_ledger non_cash_gl ON cash.ref_id = non_cash_gl.ref_id AND cash.ref_type = non_cash_gl.ref_type JOIN accounts non_cash ON non_cash_gl.account_id = non_cash.id WHERE cash.user_id = ? AND cash.tanggal BETWEEN ? AND ? AND cash_acc.is_kas = 1 AND non_cash.is_kas = 0 GROUP BY non_cash.nama_akun, non_cash.cash_flow_category");
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($results as $row) {
            $jumlah = (float)$row['cash_mutation'];
            $akun_lawan = $row['nama_akun'];
            $category = $row['cash_flow_category'] ?? 'Operasi';
            if ($category === 'Investasi') { $arus_kas_investasi['total'] += $jumlah; $add_detail($arus_kas_investasi['details'], $akun_lawan, $jumlah); }
            elseif ($category === 'Pendanaan') { $arus_kas_pendanaan['total'] += $jumlah; $add_detail($arus_kas_pendanaan['details'], $akun_lawan, $jumlah); }
            else { $arus_kas_operasi['total'] += $jumlah; $add_detail($arus_kas_operasi['details'], $akun_lawan, $jumlah); }
        }

        $kenaikan_penurunan_kas = $arus_kas_operasi['total'] + $arus_kas_investasi['total'] + $arus_kas_pendanaan['total'];
        return compact('arus_kas_operasi', 'arus_kas_investasi', 'arus_kas_pendanaan', 'kenaikan_penurunan_kas', 'saldo_kas_awal', 'saldo_kas_akhir');
    }

    private function render(array $data): void
    {
        extract($data);
        
        $renderSection = function($title, $sectionData) {
            $this->pdf->SetFont('Helvetica', 'B', 10);
            $this->pdf->Cell(0, 7, $title, 'B', 1);
            $this->pdf->SetFont('Helvetica', '', 10);
            if (empty($sectionData['details'])) {
                $this->pdf->Cell(0, 6, 'Tidak ada aktivitas.', 0, 1);
            } else {
                foreach($sectionData['details'] as $keterangan => $jumlah) {
                    $this->pdf->Cell(100, 6, $keterangan, 0, 0);
                    $this->pdf->Cell(90, 6, format_currency_pdf($jumlah), 0, 1, 'R');
                }
            }
            $this->pdf->SetFont('Helvetica', 'B', 10);
            $this->pdf->Cell(100, 6, 'Total ' . $title, 'T', 0);
            $this->pdf->Cell(90, 6, format_currency_pdf($sectionData['total']), 'T', 1, 'R');
            $this->pdf->Ln(5);
        };

        $renderSection('Arus Kas dari Aktivitas Operasi', $arus_kas_operasi);
        $renderSection('Arus Kas dari Aktivitas Investasi', $arus_kas_investasi);
        $renderSection('Arus Kas dari Aktivitas Pendanaan', $arus_kas_pendanaan);

        $this->pdf->Ln(3);
        $this->pdf->Cell(100, 6, 'Kenaikan (Penurunan) Bersih Kas', 'T', 0);
        $this->pdf->Cell(90, 6, format_currency_pdf($kenaikan_penurunan_kas), 'T', 1, 'R');
        $this->pdf->Cell(100, 6, 'Saldo Kas pada Awal Periode', 0, 0);
        $this->pdf->Cell(90, 6, format_currency_pdf($saldo_kas_awal), 0, 1, 'R');
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'Saldo Kas pada Akhir Periode', 'T', 0);
        $this->pdf->Cell(90, 6, format_currency_pdf($saldo_kas_awal + $kenaikan_penurunan_kas), 'T', 1, 'R');

        $this->pdf->signature_date = $this->params['end'] ?? date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }
}