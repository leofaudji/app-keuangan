<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-question-circle-fill"></i> Buku Panduan Aplikasi</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?= base_url('/api/pdf?report=buku-panduan') ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer-fill"></i> Cetak PDF
        </a>
    </div>
</div>

<div class="accordion" id="panduanAccordion">

    <!-- Panduan 1: Pengaturan Awal -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                <strong>1. Pengaturan Awal (Penting!)</strong>
            </button>
        </h2>
        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Sebelum memulai pencatatan, ada dua langkah krusial yang harus dilakukan untuk memastikan data akurat.</p>
                <h5>Langkah 1.1: Menyiapkan Bagan Akun (COA)</h5>
                <ol>
                    <li>Buka menu <strong>Master Data &raquo; Bagan Akun (COA)</strong>.</li>
                    <li>Sistem sudah menyediakan akun-akun standar. Anda bisa menambah, mengubah, atau menghapus akun sesuai kebutuhan.</li>
                    <li>Saat menambah akun, pastikan Anda memilih <strong>Tipe Akun</strong> yang benar (Aset, Liabilitas, Ekuitas, Pendapatan, atau Beban).</li>
                    <li>Centang kotak <strong>"Ini adalah akun Kas/Bank"</strong> untuk akun-akun yang berfungsi sebagai tempat penyimpanan uang (Kas, Bank BCA, dll.). Akun ini akan muncul di form transaksi.</li>
                </ol>
                <hr>
                <h5>Langkah 1.2: Mengisi Saldo Awal</h5>
                <ol>
                    <li>Buka menu <strong>Master Data &raquo; Saldo Awal Neraca</strong>.</li>
                    <li>Masukkan saldo akhir dari periode sebelumnya ke dalam kolom Debit atau Kredit sesuai dengan saldo normal akun.
                        <ul>
                            <li><strong>Aset:</strong> Saldo normalnya di <strong>Debit</strong>.</li>
                            <li><strong>Liabilitas & Ekuitas:</strong> Saldo normalnya di <strong>Kredit</strong>.</li>
                        </ul>
                    </li>
                    <li>Pastikan <strong>Total Debit dan Total Kredit seimbang (BALANCE)</strong> sebelum menyimpan.</li>
                </ol>
                <div class="d-flex gap-2 mt-3">
                    <a href="<?= base_url('/coa') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Buka Bagan Akun
                    </a>
                    <a href="<?= base_url('/saldo-awal-neraca') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Buka Saldo Awal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Panduan 2: Transaksi Harian -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingTwo">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                <strong>2. Mencatat Transaksi Harian</strong>
            </button>
        </h2>
        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Gunakan menu <strong>Transaksi</strong> untuk mencatat pemasukan dan pengeluaran kas harian yang sederhana.</p>
                <ol>
                    <li>Buka menu <strong>Kas & Bank &raquo; Transaksi</strong>.</li>
                    <li>Klik tombol <strong>"Tambah Transaksi"</strong>.</li>
                    <li>Pilih jenis transaksi: <strong>Pengeluaran</strong>, <strong>Pemasukan</strong>, atau <strong>Transfer</strong> antar akun kas.</li>
                    <li>Isi tanggal, jumlah, dan keterangan.</li>
                    <li>Pilih akun Kas/Bank yang digunakan dan akun lawan (misal: Akun Beban untuk pengeluaran, atau Akun Pendapatan untuk pemasukan).</li>
                    <li>Klik <strong>"Simpan Transaksi"</strong>. Sistem akan otomatis membuat jurnal di belakang layar.</li>
                </ol>
                <a href="<?= base_url('/transaksi#add') ?>" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Buka Form Tambah Transaksi
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 3: Entri Jurnal Manual -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingThree">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                <strong>3. Entri Jurnal Manual (Untuk Transaksi Kompleks)</strong>
            </button>
        </h2>
        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Gunakan fitur ini untuk transaksi yang melibatkan lebih dari dua akun (jurnal majemuk) atau transaksi non-kas (misalnya: penyusutan, penyesuaian).</p>
                <ol>
                    <li>Buka menu <strong>Akuntansi &raquo; Entri Jurnal</strong>.</li>
                    <li>Isi tanggal dan keterangan jurnal.</li>
                    <li>Klik <strong>"Tambah Baris"</strong> untuk menambahkan detail jurnal.</li>
                    <li>Pilih akun dan isi kolom Debit atau Kredit.</li>
                    <li>Pastikan <strong>Total Debit dan Total Kredit seimbang</strong> sebelum menyimpan.</li>
                </ol>
                <a href="<?= base_url('/entri-jurnal') ?>" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Buka Halaman Entri Jurnal
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 4: Rekonsiliasi Bank -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingFour">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                <strong>4. Mencocokkan Catatan: Rekonsiliasi Bank</strong>
            </button>
        </h2>
        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Rekonsiliasi bank adalah proses membandingkan catatan transaksi kas/bank di aplikasi dengan laporan rekening koran dari bank untuk memastikan keduanya cocok.</p>
                <ol>
                    <li>Buka menu <strong>Kas & Bank &raquo; Rekonsiliasi Bank</strong>.</li>
                    <li>Pilih <strong>Akun Kas/Bank</strong> yang ingin direkonsiliasi.</li>
                    <li>Masukkan <strong>Tanggal Akhir</strong> dan <strong>Saldo Akhir</strong> sesuai yang tertera di rekening koran Anda.</li>
                    <li>Klik <strong>"Mulai Rekonsiliasi"</strong>. Sistem akan menampilkan semua transaksi yang belum dicocokkan.</li>
                    <li>Beri tanda centang pada setiap transaksi di aplikasi yang juga Anda temukan di rekening koran.</li>
                    <li>Perhatikan kartu ringkasan <strong>"Selisih"</strong>. Tujuan Anda adalah membuat selisih menjadi <strong>Rp 0</strong>.</li>
                    <li>Jika selisih sudah nol, tombol <strong>"Simpan Rekonsiliasi"</strong> akan aktif. Klik untuk menyelesaikan.</li>
                </ol>
                <div class="alert alert-info small mt-3">
                    <strong>Tips:</strong> Jika ada selisih, kemungkinan ada transaksi yang belum Anda catat (misalnya biaya admin bank) atau ada kesalahan pencatatan. Anda bisa menambahkannya melalui menu Transaksi atau Entri Jurnal.
                </div>
                <a href="<?= base_url('/rekonsiliasi-bank') ?>" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Buka Halaman Rekonsiliasi Bank
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 5: Otomatisasi -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingFive">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                <strong>5. Otomatisasi dengan Transaksi Berulang</strong>
            </button>
        </h2>
        <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Fitur ini memungkinkan Anda membuat template untuk transaksi atau jurnal yang terjadi secara rutin (misal: bayar sewa, gaji) agar dibuat otomatis oleh sistem.</p>
                <ol>
                    <li>Buat draf jurnal yang ingin diotomatisasi di halaman <strong>Akuntansi &raquo; Entri Jurnal</strong>.</li>
                    <li>Setelah draf siap, jangan klik "Simpan". Klik tombol <strong>"Jadikan Berulang"</strong>.</li>
                    <li>Atur nama template, frekuensi (misal: setiap 1 bulan), dan tanggal mulai.</li>
                    <li>Klik <strong>"Simpan Template"</strong>.</li>
                    <li>Anda dapat melihat dan mengelola semua template di halaman <strong>Pengaturan & Master &raquo; Transaksi Berulang</strong>.</li>
                </ol>
                <div class="d-flex gap-2 mt-3">
                    <a href="<?= base_url('/entri-jurnal') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Mulai dari Entri Jurnal
                    </a>
                    <a href="<?= base_url('/transaksi-berulang') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Halaman Template
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Panduan 6: Laporan & Analisis -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingSix">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                <strong>6. Melihat Laporan & Analisis</strong>
            </button>
        </h2>
        <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Semua hasil pencatatan Anda dapat dilihat dalam berbagai laporan di bawah menu <strong>Laporan & Analisis</strong>.</p>
                <ul>
                    <li><strong>Laporan Keuangan:</strong> Menampilkan Neraca, Laba Rugi, dan Arus Kas.</li>
                    <li><strong>Perubahan Laba:</strong> Menunjukkan detail perubahan pada akun Laba Ditahan.</li>
                    <li><strong>Laporan Harian:</strong> Ringkasan kas masuk dan keluar untuk tanggal tertentu.</li>
                    <li><strong>Pertumbuhan Laba:</strong> Grafik dan tabel untuk menganalisis tren laba dari waktu ke waktu.</li>
                    <li><strong>Analisis Rasio:</strong> Menghitung rasio keuangan penting (Profit Margin, ROE, dll) untuk mengukur kesehatan finansial.</li>
                    <li><strong>Anggaran:</strong> Membandingkan anggaran belanja dengan realisasi.</li>
                </ul>
                <a href="<?= base_url('/laporan') ?>" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Buka Halaman Laporan Utama
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 7: Tutup Buku -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingSeven">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                <strong>7. Proses Akhir Periode: Tutup Buku (Khusus Admin)</strong>
            </button>
        </h2>
        <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Proses Tutup Buku adalah langkah akuntansi yang dilakukan di akhir periode (biasanya akhir tahun) untuk menolkan saldo akun-akun sementara (Pendapatan dan Beban) dan memindahkan laba atau rugi bersih ke akun Laba Ditahan (Retained Earnings).</p>
                <div class="alert alert-warning small">
                    <strong>Penting:</strong> Fitur ini hanya dapat diakses oleh <strong>Admin</strong>. Pastikan semua transaksi pada periode tersebut sudah final sebelum melakukan tutup buku.
                </div>
                <ol>
                    <li>Pastikan akun Laba Ditahan sudah diatur di menu <strong>Administrasi &raquo; Pengaturan &raquo; Akuntansi</strong>.</li>
                    <li>Buka menu <strong>Administrasi &raquo; Tutup Buku</strong>.</li>
                    <li>Pilih tanggal akhir periode yang akan ditutup (misalnya, 31 Desember 2023).</li>
                    <li>Klik tombol <strong>"Proses Tutup Buku"</strong> dan konfirmasi.</li>
                    <li>Sistem akan secara otomatis membuat Jurnal Penutup. Anda dapat melihat hasilnya di halaman <strong>Daftar Jurnal</strong>.</li>
                    <li>Setelah proses ini, semua transaksi sebelum tanggal tutup buku akan dikunci dan tidak dapat diubah atau dihapus.</li>
                </ol>
                <a href="<?= base_url('/tutup-buku') ?>" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Halaman Tutup Buku
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 8: Pengaturan Aplikasi -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingEight">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight" aria-expanded="false" aria-controls="collapseEight">
                <strong>8. Konfigurasi Sistem: Pengaturan Aplikasi (Khusus Admin)</strong>
            </button>
        </h2>
        <div id="collapseEight" class="accordion-collapse collapse" aria-labelledby="headingEight" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Halaman ini adalah pusat kendali aplikasi, tempat Anda dapat menyesuaikan berbagai aspek sistem agar sesuai dengan kebutuhan Anda. Fitur ini hanya dapat diakses oleh <strong>Admin</strong>.</p>
                <h5>Area Pengaturan:</h5>
                <ul>
                    <li><strong>Umum:</strong> Mengubah nama aplikasi, logo, dan detail header laporan PDF.</li>
                    <li><strong>Transaksi:</strong> Mengatur prefix untuk nomor referensi otomatis dan memilih akun kas default.</li>
                    <li><strong>Akuntansi:</strong> Menentukan akun Laba Ditahan yang krusial untuk proses Tutup Buku.</li>
                    <li><strong>Arus Kas:</strong> Memetakan akun-akun ke dalam kategori Laporan Arus Kas (Operasi, Investasi, Pendanaan).</li>
                </ul>
                <h5>Langkah Penggunaan:</h5>
                <ol>
                    <li>Buka menu <strong>Administrasi &raquo; Pengaturan</strong>.</li>
                    <li>Pilih tab pengaturan yang ingin Anda ubah (misalnya, "Umum").</li>
                    <li>Lakukan perubahan yang diperlukan pada form.</li>
                    <li>Klik tombol <strong>"Simpan Pengaturan"</strong> di bagian bawah setiap tab untuk menerapkan perubahan.</li>
                </ol>
                <a href="<?= base_url('/settings') ?>" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Buka Halaman Pengaturan
                </a>
            </div>
        </div>
    </div>

</div>

<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>