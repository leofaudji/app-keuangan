<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class RekonsiliasiReportBuilder implements ReportBuilderInterface
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
        $reconciliation_id = (int)($this->params['id'] ?? 0);
        $user_id = $this->params['user_id'];

        if ($reconciliation_id <= 0) {
            throw new Exception("ID Rekonsiliasi tidak valid.");
        }

        // Fetch reconciliation header
        $stmt_header = $this->conn->prepare("
            SELECT r.*, a.nama_akun 
            FROM reconciliations r 
            JOIN accounts a ON r.account_id = a.id 
            WHERE r.id = ? AND r.user_id = ?
        ");
        $stmt_header->bind_param('ii', $reconciliation_id, $user_id);
        $stmt_header->execute();
        $header = $stmt_header->get_result()->fetch_assoc();
        $stmt_header->close();
        if (!$header) throw new Exception("Data rekonsiliasi tidak ditemukan.");

        // Fetch cleared items
        $stmt_cleared = $this->conn->prepare("
            SELECT tanggal, keterangan, debit, kredit 
            FROM general_ledger 
            WHERE reconciliation_id = ? ORDER BY tanggal, id
        ");
        $stmt_cleared->bind_param('i', $reconciliation_id);
        $stmt_cleared->execute();
        $cleared_items = $stmt_cleared->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_cleared->close();

        // Fetch uncleared items (outstanding)
        $stmt_uncleared = $this->conn->prepare("
            SELECT tanggal, keterangan, debit, kredit 
            FROM general_ledger 
            WHERE account_id = ? AND tanggal <= ? AND is_reconciled = 0 
            ORDER BY tanggal, id
        ");
        $stmt_uncleared->bind_param('is', $header['account_id'], $header['statement_date']);
        $stmt_uncleared->execute();
        $uncleared_items = $stmt_uncleared->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_uncleared->close();

        $this->pdf->SetTitle('Laporan Rekonsiliasi Bank');
        $this->pdf->report_title = 'Laporan Rekonsiliasi Bank';
        $this->pdf->report_period = 'Untuk Akun ' . $header['nama_akun'] . ' per ' . date('d F Y', strtotime($header['statement_date']));
        $this->pdf->AddPage();

        $this->render($header, $cleared_items, $uncleared_items);
        $this->pdf->signature_date = $header['statement_date'];
        $this->pdf->RenderSignatureBlock();
    }

    private function render(array $header, array $cleared, array $uncleared): void
    {
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(130, 7, 'Saldo Akhir per Rekening Koran', 0, 0);
        $this->pdf->Cell(60, 7, format_currency_pdf($header['statement_balance']), 0, 1, 'R');
        $this->pdf->Ln(5);

        // --- UNCLEARED ITEMS ---
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(0, 7, 'Dikurangi: Transaksi Beredar (Outstanding)', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(25, 6, 'Tanggal', 1, 0, 'C', true);
        $this->pdf->Cell(105, 6, 'Keterangan', 1, 0, 'C', true);
        $this->pdf->Cell(60, 6, 'Jumlah', 1, 1, 'C', true);

        $total_uncleared = 0;
        if (empty($uncleared)) {
            $this->pdf->Cell(190, 6, 'Tidak ada transaksi beredar.', 1, 1, 'C');
        } else {
            foreach ($uncleared as $item) {
                $amount = $item['debit'] - $item['kredit'];
                $total_uncleared += $amount;
                $this->pdf->Cell(25, 6, date('d-m-Y', strtotime($item['tanggal'])), 1, 0);
                $this->pdf->Cell(105, 6, $item['keterangan'], 1, 0);
                $this->pdf->Cell(60, 6, format_currency_pdf($amount), 1, 1, 'R');
            }
        }
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->Cell(130, 6, 'Total Transaksi Beredar', 1, 0, 'R');
        $this->pdf->Cell(60, 6, format_currency_pdf($total_uncleared), 1, 1, 'R');
        $this->pdf->Ln(5);

        // --- FINAL CALCULATION ---
        $saldo_buku_disesuaikan = (float)$header['statement_balance'] - $total_uncleared;
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(130, 7, 'Saldo Buku yang Disesuaikan', 'T', 0);
        $this->pdf->Cell(60, 7, format_currency_pdf($saldo_buku_disesuaikan), 'T', 1, 'R');
        $this->pdf->Ln(10);

        // --- CLEARED ITEMS FOR REFERENCE ---
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(0, 7, 'Lampiran: Daftar Transaksi yang Telah Direkonsiliasi', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 9);
        $this->pdf->Cell(25, 6, 'Tanggal', 1, 0, 'C', true);
        $this->pdf->Cell(105, 6, 'Keterangan', 1, 0, 'C', true);
        $this->pdf->Cell(30, 6, 'Debit', 1, 0, 'C', true);
        $this->pdf->Cell(30, 6, 'Kredit', 1, 1, 'C', true);

        $total_debit = 0;
        $total_kredit = 0;
        foreach ($cleared as $item) {
            $total_debit += (float)$item['debit'];
            $total_kredit += (float)$item['kredit'];
            $this->pdf->Cell(25, 6, date('d-m-Y', strtotime($item['tanggal'])), 1, 0);
            $this->pdf->Cell(105, 6, $item['keterangan'], 1, 0);
            $this->pdf->Cell(30, 6, $item['debit'] > 0 ? format_currency_pdf($item['debit']) : '-', 1, 0, 'R');
            $this->pdf->Cell(30, 6, $item['kredit'] > 0 ? format_currency_pdf($item['kredit']) : '-', 1, 1, 'R');
        }
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->Cell(130, 6, 'Total', 1, 0, 'R');
        $this->pdf->Cell(30, 6, format_currency_pdf($total_debit), 1, 0, 'R');
        $this->pdf->Cell(30, 6, format_currency_pdf($total_kredit), 1, 1, 'R');
    }
}

?>