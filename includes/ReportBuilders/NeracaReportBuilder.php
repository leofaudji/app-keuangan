<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class NeracaReportBuilder implements ReportBuilderInterface
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

    private function render(array $data): void
    {
        $asetData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Aset');
        $liabilitasData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Liabilitas');
        $ekuitasData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Ekuitas');

        $totalAset = array_sum(array_column($asetData, 'saldo_akhir'));
        $totalLiabilitas = array_sum(array_column($liabilitasData, 'saldo_akhir'));
        $totalEkuitas = array_sum(array_column($ekuitasData, 'saldo_akhir'));
        $totalLiabilitasEkuitas = $totalLiabilitas + $totalEkuitas;

        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell(0, 7, 'ASET', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 10);
        foreach ($asetData as $item) { $this->pdf->Cell(100, 6, $item['nama_akun'], 0, 0); $this->pdf->Cell(90, 6, format_currency_pdf($item['saldo_akhir']), 0, 1, 'R'); }
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL ASET', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalAset), 'T', 1, 'R');
        $this->pdf->Ln(8);

        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell(0, 7, 'LIABILITAS', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 10);
        if (empty($liabilitasData)) { $this->pdf->Cell(0, 6, 'Tidak ada liabilitas.', 0, 1); } 
        else { foreach ($liabilitasData as $item) { $this->pdf->Cell(100, 6, $item['nama_akun'], 0, 0); $this->pdf->Cell(90, 6, format_currency_pdf($item['saldo_akhir']), 0, 1, 'R'); } }
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL LIABILITAS', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalLiabilitas), 'T', 1, 'R');
        $this->pdf->Ln(8);

        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell(0, 7, 'EKUITAS', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 10);
        foreach ($ekuitasData as $item) { $this->pdf->Cell(100, 6, $item['nama_akun'], 0, 0); $this->pdf->Cell(90, 6, format_currency_pdf($item['saldo_akhir']), 0, 1, 'R'); }
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL EKUITAS', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalEkuitas), 'T', 1, 'R');
        $this->pdf->Ln(8);

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL LIABILITAS DAN EKUITAS', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalLiabilitasEkuitas), 'T', 1, 'R');
    }

    // Tambahkan pemanggilan di akhir build, karena render dipanggil di dalam build
    public function build(): void
    {
        $tanggal = $this->params['tanggal'] ?? date('Y-m-d');
        $user_id = $this->params['user_id'];

        $this->pdf->SetTitle('Laporan Neraca');
        $this->pdf->report_title = 'Laporan Posisi Keuangan (Neraca)';
        $this->pdf->report_period = 'Per Tanggal: ' . date('d F Y', strtotime($tanggal));
        $this->pdf->AddPage();

        // Gunakan Repository untuk konsistensi data
        $repo = new LaporanRepository($this->conn);
        $neraca_accounts = $repo->getNeracaData($user_id, $tanggal);

        // Hitung laba rugi berjalan dari data laba rugi
        $laba_rugi_data = $repo->getLabaRugiData($user_id, date('Y-01-01', strtotime($tanggal)), $tanggal);
        $laba_rugi_berjalan = $laba_rugi_data['summary']['laba_bersih'];

        // Tambahkan akun virtual untuk laba rugi berjalan ke dalam data neraca
        $neraca_accounts[] = ['id' => 'laba_rugi_virtual', 'parent_id' => null, 'nama_akun' => 'Laba (Rugi) Periode Berjalan', 'tipe_akun' => 'Ekuitas', 'saldo_akhir' => $laba_rugi_berjalan];

        $data = $neraca_accounts;

        $this->render($data);
        $this->pdf->signature_date = $tanggal;
        $this->pdf->RenderSignatureBlock();
    }
}