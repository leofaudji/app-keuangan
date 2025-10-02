<?php
require_once PROJECT_ROOT . '/includes/fpdf.php';

class PDF extends FPDF
{
    public $report_title = '';
    public $report_period = '';

    // Page header
    function Header()
    {
        global $housing_name; // Pastikan variabel ini di-pass dari laporan_cetak_handler.php

        // --- Tambahkan Logo ---
        $logo_path = get_setting('app_logo'); // Ambil path logo dari database
        if ($logo_path) {
            $full_logo_path = PROJECT_ROOT . '/' . $logo_path;
            if (file_exists($full_logo_path)) {
                // Image(file, x, y, width)
                $this->Image($full_logo_path, 12, 8, 32);
            }
        }

        // Ambil teks header dari pengaturan
        $header1 = get_setting('pdf_header_line1', 'NAMA PENGURUS');
        $header2 = get_setting('pdf_header_line2', strtoupper($housing_name ?? 'NAMA PERUSAHAAN'));
        $header3 = get_setting('pdf_header_line3', 'Alamat Sekretariat: [Alamat Sekretariat RT Anda]');

        $this->SetY(10); // Atur posisi Y agar teks sejajar
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 7, $header1, 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(0, 7, $header2, 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(0, 5, $header3, 0, 1, 'C');
        $this->Line(10, 38, $this->w - 10, 38); // Sesuaikan posisi garis
        $this->Ln(10);

        // Report Title and Period
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 12, $this->report_title, 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->Cell(0, 0, $this->report_period, 0, 1, 'C');
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        // Panggil blok tanda tangan hanya di halaman terakhir
        if ($this->PageNo() == $this->AliasNbPages) {
            $this->SignatureBlock();
        }

        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SignatureBlock()
    {
        // Ambil data dari settings
        $ketua_name = get_setting('signature_ketua_name', '.........................');
        $bendahara_name = get_setting('signature_bendahara_name', '.........................');
        $ketua_title = 'Ketua RT';
        $bendahara_title = 'Bendahara';
        $city = get_setting('app_city', 'Kota Anda');

        // Atur posisi Y untuk blok tanda tangan, misal 80mm dari bawah
        $this->SetY(-80);

        $this->SetFont('Helvetica', '', 10);

        // Tanggal
        $this->Cell(0, 5, $city . ', ' . date('d F Y'), 0, 1, 'R');
        $this->Ln(5);

        // Kolom Tanda Tangan
        $this->Cell(95, 5, 'Mengetahui,', 0, 0, 'C');
        $this->Cell(95, 5, 'Dibuat oleh,', 0, 1, 'C');
        $this->Cell(95, 5, $ketua_title, 0, 0, 'C');
        $this->Cell(95, 5, $bendahara_title, 0, 1, 'C');
        $this->Ln(20); // Spasi untuk tanda tangan
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(95, 5, $ketua_name, 0, 0, 'C');
        $this->Cell(95, 5, $bendahara_name, 0, 1, 'C');
    }
}
?>