<?php
require_once PROJECT_ROOT . '/includes/fpdf.php';

class PDF extends FPDF
{
    public $report_title = '';
    public $report_period = '';
    public $signature_date = null;

    // Page header
    function Header()
    {
        global $housing_name; // Pastikan variabel ini di-pass dari laporan_cetak_handler.php

        $startX = $this->GetX();
        $startY = $this->GetY();
        $logoY = $startY;
        $logoWidth = 30; // Sedikit lebih kecil untuk memberi ruang
        $logoHeight = 0;

        // --- Tambahkan Logo ---
        $logo_path = get_setting('app_logo'); // Ambil path logo dari database
        if ($logo_path) {
            $full_logo_path = PROJECT_ROOT . '/' . $logo_path;
            if (file_exists($full_logo_path)) {
                list($imgWidth, $imgHeight) = getimagesize($full_logo_path);
                $aspectRatio = $imgHeight / $imgWidth;
                $logoHeight = $logoWidth * $aspectRatio;
                $this->Image($full_logo_path, $startX, $logoY, $logoWidth, $logoHeight);
            }
        }

        // Ambil teks header dari pengaturan
        $header1 = get_setting('pdf_header_line1', 'NAMA PENGURUS');
        $header2 = get_setting('pdf_header_line2', strtoupper($housing_name ?? 'NAMA PERUSAHAAN'));
        $header3 = get_setting('pdf_header_line3', 'Alamat Sekretariat: [Alamat Sekretariat RT Anda]');

        // Simpan posisi Y saat ini sebelum menggambar teks
        $y_before_text = $this->GetY();

        $this->SetY($startY); // Atur posisi Y agar teks sejajar dengan atas logo
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 7, $header1, 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(0, 7, $header2, 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(0, 5, $header3, 0, 1, 'C');

        // Tentukan posisi Y akhir dari logo dan teks
        $y_after_logo = $logoY + $logoHeight;
        $y_after_text = $this->GetY();
        $bottom_y = max($y_after_logo, $y_after_text);

        // Gambar garis di bawah elemen yang paling bawah
        $this->Line($this->lMargin, $bottom_y + 2, $this->w - $this->rMargin, $bottom_y + 2);
        $this->SetY($bottom_y + 5); // Atur posisi Y untuk konten selanjutnya

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
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    function RenderSignatureBlock()
    {
        // Cek apakah ruang tersisa cukup untuk blok tanda tangan (sekitar 80mm).
        // Jika tidak, tambahkan halaman baru secara otomatis.
        // PageBreakTrigger adalah posisi Y di mana halaman baru akan dibuat.
        if ($this->GetY() > ($this->PageBreakTrigger - 80)) {
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
        }
        $this->SignatureBlock();
    }

    function SignatureBlock()
    {
        // Ambil data dari settings
        $ketua_name = get_setting('signature_ketua_name', '.........................');
        $bendahara_name = get_setting('signature_bendahara_name', '.........................');
        $ketua_title = 'Mengetahui,';
        $bendahara_title = 'Dibuat oleh,';
        $city = get_setting('app_city', 'Kota Anda');
        
        // Ambil path gambar stempel dan tanda tangan dari pengaturan
        $stamp_path = get_setting('stamp_image');
        $signature_path = get_setting('signature_image');
        $full_stamp_path = $stamp_path ? PROJECT_ROOT . '/' . $stamp_path : null;
        $full_signature_path = $signature_path ? PROJECT_ROOT . '/' . $signature_path : null;

        // Gunakan tanggal spesifik jika diatur, jika tidak gunakan tanggal hari ini
        $reportDate = $this->signature_date ? strtotime($this->signature_date) : time();
        
        // Array untuk nama bulan dalam bahasa Indonesia
        $indonesian_months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $day = date('d', $reportDate);
        $month_num = (int)date('n', $reportDate);
        $year = date('Y', $reportDate);

        $indonesian_month_name = $indonesian_months[$month_num];

        $formattedDate = $day . ' ' . $indonesian_month_name . ' ' . $year;

        // Atur posisi Y untuk blok tanda tangan, misal 80mm dari bawah
        $this->SetY(-80);

        $this->SetFont('Helvetica', '', 10);

        // Tanggal
        $this->Cell(95, 5, '', 0, 0); // Sel kosong untuk kolom kiri
        $this->Cell(95, 5, $city . ', ' . $formattedDate, 0, 1, 'C'); // Tanggal di kolom kanan, rata tengah
        $this->Ln(5);

        // Kolom Tanda Tangan
        $this->Cell(95, 5, $ketua_title, 0, 0, 'C');
        $this->Cell(95, 5, $bendahara_title, 0, 1, 'C');

        // Simpan posisi Y saat ini sebelum menambahkan gambar
        $y_pos_before_images = $this->GetY();

        // Render Stempel di kolom kiri (Ketua)
        if ($full_stamp_path && file_exists($full_stamp_path)) {
            // Posisi X: 10mm (margin) + (95mm (lebar kolom) - 35mm (lebar gambar)) / 2 = 40mm
            // Posisi Y: sedikit di bawah judul
            $this->Image($full_stamp_path, 40, $y_pos_before_images + 2, 35, 0, 'PNG');
        }

        // Render Tanda Tangan di kolom kanan (Bendahara)
        if ($full_signature_path && file_exists($full_signature_path)) {
            // Posisi X: 105mm (awal kolom kanan) + (95mm (lebar kolom) - 40mm (lebar gambar)) / 2 = 132.5mm
            // Posisi Y: sedikit di bawah judul
            $this->Image($full_signature_path, 132.5, $y_pos_before_images + 2, 40, 0, 'PNG');
        }

        // Kembalikan posisi Y ke bawah gambar untuk mencetak nama
        $this->SetY($y_pos_before_images + 20); // Spasi 20mm untuk area tanda tangan

        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(95, 5, $ketua_name, 0, 0, 'C');
        $this->Cell(95, 5, $bendahara_name, 0, 1, 'C');
    }
}
?>