<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class BukuPanduanReportBuilder implements ReportBuilderInterface
{
    private $pdf;
    
    public function __construct(PDF $pdf, mysqli $conn, array $params)
    {
        $this->pdf = $pdf;
        // Koneksi dan params tidak digunakan untuk laporan statis ini, tapi tetap ada untuk konsistensi interface.
    }

    public function build(): void
    {
        $this->pdf->SetTitle('Buku Panduan Aplikasi');
        $this->pdf->report_title = 'Buku Panduan Aplikasi';
        $this->pdf->report_period = 'Versi ' . date('Y.m');
        $this->pdf->AddPage('P');

        $this->render();
    }

    private function render(): void
    {
        $guideContent = $this->getGuideContent();

        foreach ($guideContent as $section) {
            $this->pdf->SetFont('Helvetica', 'B', 12);
            $this->pdf->SetFillColor(240, 240, 240);
            $this->pdf->Cell(0, 10, $section['title'], 0, 1, 'L', true);
            $this->pdf->Ln(2);

            $this->pdf->SetFont('Helvetica', '', 10);
            foreach ($section['content'] as $item) {
                if ($item['type'] === 'p') {
                    $this->pdf->MultiCell(0, 6, $item['text']);
                    $this->pdf->Ln(2);
                } elseif ($item['type'] === 'ol') {
                    $counter = 1;
                    foreach ($item['items'] as $li) {
                        $this->pdf->Cell(5);
                        $this->pdf->Cell(5, 6, $counter . '.');
                        $this->pdf->MultiCell(0, 6, $li);
                        $counter++;
                    }
                    $this->pdf->Ln(2);
                } elseif ($item['type'] === 'alert') {
                    $this->pdf->SetFillColor(255, 243, 205); // Warna kuning muda
                    $this->pdf->SetTextColor(133, 100, 4);
                    $this->pdf->SetFont('Helvetica', 'I', 9);
                    $this->pdf->MultiCell(0, 6, 'Penting: ' . $item['text'], 'L', 1, 'L', true);
                    $this->pdf->SetTextColor(0, 0, 0);
                    $this->pdf->SetFont('Helvetica', '', 10);
                    $this->pdf->Ln(3);
                }
            }
            $this->pdf->Ln(5);
        }

        // Tambahkan tanda tangan seperti laporan lainnya
        $this->pdf->signature_date = date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }

    private function getGuideContent(): array
    {
        // Konten panduan sekarang didefinisikan di sini dalam bentuk array terstruktur.
        return [
            [
                'title' => 'Alur Kerja Aplikasi (Workflow)',
                'content' => [
                    ['type' => 'p', 'text' => 'Berikut adalah alur kerja yang direkomendasikan untuk menggunakan aplikasi ini secara efektif.'],
                    ['type' => 'ol', 'items' => [
                        'Tahap 1 - Setup Awal: Lakukan konfigurasi awal di menu Administrasi dan Master Data. Siapkan Bagan Akun dan isi Saldo Awal.',
                        'Tahap 2 - Operasional Harian: Catat semua transaksi pemasukan, pengeluaran, dan jurnal manual yang terjadi setiap hari.',
                        'Tahap 3 - Proses Periodik: Lakukan proses bulanan seperti Rekonsiliasi Bank dan Posting Penyusutan Aset.',
                        'Tahap 4 - Pelaporan & Analisis: Gunakan menu Laporan untuk memantau kinerja dan kesehatan keuangan.',
                        'Tahap 5 - Akhir Periode: Lakukan proses Tutup Buku di akhir tahun untuk memfinalisasi laporan tahunan.',
                    ]],
                ],
            ],
            [
                'title' => '1. Pengaturan Awal: Bagan Akun (COA)',
                'content' => [
                    ['type' => 'p', 'text' => 'Bagan Akun adalah daftar semua akun yang digunakan dalam pencatatan keuangan. Pengaturan ini adalah langkah pertama dan paling penting.'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Master Data > Bagan Akun (COA).',
                        'Sistem sudah menyediakan akun-akun standar. Anda bisa menambah, mengubah, atau menghapus akun sesuai kebutuhan.',
                        'Saat menambah akun, pastikan Anda memilih Tipe Akun yang benar (Aset, Liabilitas, Ekuitas, Pendapatan, atau Beban).',
                        'Centang kotak "Ini adalah akun Kas/Bank" untuk akun-akun yang berfungsi sebagai tempat penyimpanan uang (Kas, Bank, dll.).',
                    ]],
                ],
            ],
            [
                'title' => '2. Mengisi Saldo Awal',
                'content' => [
                    ['type' => 'p', 'text' => 'Setelah Bagan Akun siap, langkah selanjutnya adalah mengisi saldo awal untuk setiap akun Neraca.'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Master Data > Saldo Awal Neraca.',
                        'Masukkan saldo akhir dari periode sebelumnya ke dalam kolom Debit atau Kredit sesuai dengan saldo normal akun.',
                        'Pastikan Total Debit dan Total Kredit seimbang (BALANCE) sebelum menyimpan.',
                    ]],
                ],
            ],
            [
                'title' => '3. Mencatat Transaksi Harian',
                'content' => [
                    ['type' => 'p', 'text' => 'Untuk transaksi kas harian yang sederhana, gunakan menu Transaksi.'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Entri Data > Transaksi.',
                        'Klik tombol "Tambah Transaksi".',
                        'Pilih jenis transaksi: Pengeluaran atau Pemasukan.',
                        'Isi tanggal, jumlah, keterangan, dan pilih akun Kas/Bank serta akun lawan.',
                        'Klik "Simpan Transaksi". Sistem akan otomatis membuat jurnal di belakang layar.',
                    ]],
                ],
            ],
            [
                'title' => '4. Entri Jurnal Manual',
                'content' => [
                    ['type' => 'p', 'text' => 'Gunakan fitur ini untuk transaksi yang melibatkan lebih dari dua akun atau transaksi non-kas (misalnya: depresiasi, penyesuaian).'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Entri Data > Entri Jurnal.',
                        'Isi tanggal dan keterangan jurnal.',
                        'Klik "Tambah Baris" untuk menambahkan detail jurnal.',
                        'Pilih akun dan isi kolom Debit atau Kredit.',
                        'Pastikan Total Debit dan Total Kredit seimbang sebelum menyimpan.',
                    ]],
                ],
            ],
            [
                'title' => '5. Proses Akhir Periode: Tutup Buku (Khusus Admin)',
                'content' => [
                    ['type' => 'p', 'text' => 'Proses Tutup Buku adalah langkah akuntansi yang dilakukan di akhir periode (biasanya akhir tahun) untuk menolkan saldo akun-akun sementara (Pendapatan dan Beban) dan memindahkan laba atau rugi bersih ke akun Laba Ditahan (Retained Earnings).'],
                    ['type' => 'alert', 'text' => 'Fitur ini hanya dapat diakses oleh Admin. Pastikan semua transaksi pada periode tersebut sudah final sebelum melakukan tutup buku.'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Administrasi > Tutup Buku.',
                        'Pilih tanggal akhir periode yang akan ditutup (misalnya, 31 Desember 2023).',
                        'Klik tombol "Proses Tutup Buku" dan konfirmasi.',
                        'Sistem akan secara otomatis membuat Jurnal Penutup. Anda dapat melihat hasilnya di halaman Daftar Jurnal.',
                        'Setelah proses ini, semua transaksi sebelum tanggal tutup buku akan dikunci dan tidak dapat diubah atau dihapus.',
                    ]],
                ],
            ],
            [
                'title' => '5. Mengelola Aset Tetap & Penyusutan',
                'content' => [
                    ['type' => 'p', 'text' => 'Fitur ini membantu Anda mencatat aset tetap (seperti peralatan, kendaraan) dan menghitung penyusutannya secara otomatis setiap bulan.'],
                    ['type' => 'p', 'text' => 'Langkah 1: Menambah Aset Baru'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Kas & Bank > Aset Tetap.',
                        'Klik "Tambah Aset".',
                        'Isi detail aset seperti Nama, Tanggal Perolehan, Harga Perolehan, dan Masa Manfaat (dalam tahun).',
                        'Penting: Petakan akun-akun yang sesuai. Anda mungkin perlu membuat akun baru di Bagan Akun terlebih dahulu (Akun Aset, Akun Akumulasi Penyusutan, dan Akun Beban Penyusutan).',
                        'Klik "Simpan".',
                    ]],
                    ['type' => 'p', 'text' => 'Langkah 2: Memposting Jurnal Penyusutan'],
                    ['type' => 'ol', 'items' => [
                        'Di halaman Aset Tetap, pilih Bulan dan Tahun pada bagian "Posting Penyusutan Periodik".',
                        'Klik tombol "Posting Jurnal Penyusutan".',
                        'Sistem akan otomatis menghitung penyusutan bulanan untuk semua aset yang aktif dan membuat jurnalnya. Jurnal yang sudah pernah dibuat untuk periode yang sama tidak akan dibuat ulang.',
                    ]],
                ],
            ],
            [
                'title' => '6. Otomatisasi dengan Transaksi Berulang',
                'content' => [
                    ['type' => 'p', 'text' => 'Fitur ini memungkinkan Anda untuk membuat template jurnal yang akan dijalankan secara otomatis oleh sistem sesuai jadwal yang Anda tentukan.'],
                    ['type' => 'ol', 'items' => [
                        'Pertama, buat draf jurnal yang ingin Anda jadikan template di halaman Entri Data > Entri Jurnal.',
                        'Setelah draf jurnal siap (akun dan jumlah sudah diisi), jangan klik "Simpan". Sebagai gantinya, klik tombol "Jadikan Berulang".',
                        'Anda akan melihat modal untuk mengatur jadwal. Isi nama template (cth: "Bayar Gaji Bulanan"), frekuensi (setiap 1 bulan), dan tanggal mulai.',
                        'Klik "Simpan Template".',
                        'Anda dapat melihat dan mengelola semua template yang sudah dibuat di halaman Entri Data > Transaksi Berulang.',
                        'Sistem akan secara otomatis membuat jurnal sesuai jadwal yang telah Anda atur.',
                    ]],
                ],
            ],
            [
                'title' => '7. Perencanaan & Kontrol: Anggaran (Budgeting)',
                'content' => [
                    ['type' => 'p', 'text' => 'Fitur Anggaran membantu Anda merencanakan pengeluaran dan membandingkannya dengan realisasi belanja setiap bulan.'],
                    ['type' => 'p', 'text' => 'Langkah 1: Mengatur Anggaran Tahunan'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Laporan & Analisis > Anggaran.',
                        'Klik tombol "Kelola Anggaran".',
                        'Sebuah modal akan muncul. Masukkan total anggaran untuk satu tahun penuh untuk setiap akun beban.',
                        'Klik "Simpan Anggaran". Sistem akan otomatis menghitung anggaran bulanan (dibagi 12).',
                    ]],
                    ['type' => 'p', 'text' => 'Langkah 2: Melihat Laporan Anggaran vs. Realisasi'],
                     ['type' => 'ol', 'items' => [
                        'Di halaman Anggaran, pilih Bulan dan Tahun yang ingin Anda lihat.',
                        'Klik tombol "Tampilkan".',
                        'Grafik dan tabel akan menunjukkan perbandingan antara anggaran bulanan dan total belanja yang sudah terjadi pada akun tersebut.',
                    ]],
                ],
            ],
            [
                'title' => '7. Melihat Laporan & Analisis',
                'content' => [
                    ['type' => 'p', 'text' => 'Semua hasil pencatatan Anda dapat dilihat dalam berbagai laporan di bawah menu Laporan & Analisis.'],
                    ['type' => 'ol', 'items' => [
                        'Laporan Keuangan: Menampilkan Neraca, Laba Rugi, dan Arus Kas.',
                        'Perubahan Laba: Menunjukkan detail perubahan pada akun Laba Ditahan.',
                        'Laporan Harian: Ringkasan kas masuk dan keluar untuk tanggal tertentu.',
                        'Analisis Rasio: Menghitung rasio keuangan penting untuk mengukur kesehatan finansial.',
                        'Anggaran: Membandingkan anggaran belanja dengan realisasi.',
                    ]],
                ],
            ],
            [
                'title' => '8. Analisis Mendalam: Analisis Rasio Keuangan',
                'content' => [
                    ['type' => 'p', 'text' => 'Fitur ini secara otomatis menghitung rasio-rasio keuangan penting untuk memberikan gambaran cepat tentang kesehatan dan kinerja keuangan perusahaan Anda.'],
                    ['type' => 'p', 'text' => 'Langkah Penggunaan:'],
                    ['type' => 'ol', 'items' => [
                        'Buka menu Laporan & Analisis > Analisis Rasio.',
                        'Pilih Tanggal Analisis. Sistem akan menggunakan data hingga tanggal ini untuk perhitungan.',
                        '(Opsional) Pilih Tanggal Pembanding untuk melihat perubahan rasio dari waktu ke waktu.',
                        'Klik tombol "Analisis".',
                    ]],
                ],
            ],
            [
                'title' => '9. Proses Akhir Periode: Tutup Buku (Khusus Admin)',
                'content' => [
                    ['type' => 'p', 'text' => 'Proses Tutup Buku adalah langkah akuntansi yang dilakukan di akhir periode (biasanya akhir tahun) untuk menolkan saldo akun-akun sementara (Pendapatan dan Beban) dan memindahkan laba atau rugi bersih ke akun Laba Ditahan (Retained Earnings).'],
                    ['type' => 'alert', 'text' => 'Fitur ini hanya dapat diakses oleh Admin. Pastikan semua transaksi pada periode tersebut sudah final sebelum melakukan tutup buku.'],
                    ['type' => 'ol', 'items' => [
                        'Pastikan akun Laba Ditahan sudah diatur di menu Administrasi > Pengaturan > Akuntansi.',
                        'Buka menu Administrasi > Tutup Buku.',
                        'Pilih tanggal akhir periode yang akan ditutup (misalnya, 31 Desember 2023).',
                        'Klik tombol "Proses Tutup Buku" dan konfirmasi.',
                        'Sistem akan secara otomatis membuat Jurnal Penutup. Anda dapat melihat hasilnya di halaman Daftar Jurnal.',
                        'Setelah proses ini, semua transaksi sebelum tanggal tutup buku akan dikunci dan tidak dapat diubah atau dihapus.',
                    ]],
                ],
            ],
        ];
    }
}