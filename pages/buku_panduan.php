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

    <!-- Panduan 1: Bagan Akun -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                <strong>1. Pengaturan Awal: Bagan Akun (Chart of Accounts)</strong>
            </button>
        </h2>
        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Bagan Akun adalah daftar semua akun yang digunakan dalam pencatatan keuangan. Pengaturan ini adalah langkah pertama dan paling penting.</p>
                <ol>
                    <li>Buka menu <strong>Master Data &raquo; Bagan Akun (COA)</strong>.</li>
                    <li>Sistem sudah menyediakan akun-akun standar. Anda bisa menambah, mengubah, atau menghapus akun sesuai kebutuhan.</li>
                    <li>Saat menambah akun, pastikan Anda memilih <strong>Tipe Akun</strong> yang benar (Aset, Liabilitas, Ekuitas, Pendapatan, atau Beban).</li>
                    <li>Centang kotak <strong>"Ini adalah akun Kas/Bank"</strong> untuk akun-akun yang berfungsi sebagai tempat penyimpanan uang (Kas, Bank BCA, dll.). Akun ini akan muncul di form transaksi.</li>
                </ol>
                <a href="<?= base_url('/coa') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Contoh Halaman Bagan Akun
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 2: Saldo Awal -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingTwo">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                <strong>2. Mengisi Saldo Awal</strong>
            </button>
        </h2>
        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Setelah Bagan Akun siap, langkah selanjutnya adalah mengisi saldo awal untuk setiap akun Neraca.</p>
                <ol>
                    <li>Buka menu <strong>Master Data &raquo; Saldo Awal Neraca</strong>.</li>
                    <li>Masukkan saldo akhir dari periode sebelumnya ke dalam kolom Debit atau Kredit sesuai dengan saldo normal akun.
                        <ul>
                            <li><strong>Aset:</strong> Saldo normalnya di Debit.</li>
                            <li><strong>Liabilitas & Ekuitas:</strong> Saldo normalnya di Kredit.</li>
                        </ul>
                    </li>
                    <li>Pastikan <strong>Total Debit dan Total Kredit seimbang (BALANCE)</strong> sebelum menyimpan.</li>
                </ol>
                <a href="<?= base_url('/saldo-awal-neraca') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Contoh Halaman Saldo Awal
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 3: Transaksi Harian -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingThree">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                <strong>3. Mencatat Transaksi Harian (Pemasukan & Pengeluaran)</strong>
            </button>
        </h2>
        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Untuk transaksi kas harian yang sederhana, gunakan menu Transaksi.</p>
                <ol>
                    <li>Buka menu <strong>Entri Data &raquo; Transaksi</strong>.</li>
                    <li>Klik tombol <strong>"Tambah Transaksi"</strong>.</li>
                    <li>Pilih jenis transaksi: <strong>Pengeluaran</strong> atau <strong>Pemasukan</strong>.</li>
                    <li>Isi tanggal, jumlah, dan keterangan.</li>
                    <li>Pilih akun Kas/Bank yang digunakan dan akun lawan (misal: Akun Beban untuk pengeluaran, atau Akun Pendapatan untuk pemasukan).</li>
                    <li>Klik <strong>"Simpan Transaksi"</strong>. Sistem akan otomatis membuat jurnal di belakang layar.</li>
                </ol>
                <a href="<?= base_url('/transaksi#add') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Buka Contoh Form Tambah Transaksi
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 4: Entri Jurnal Manual -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingFour">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                <strong>4. Entri Jurnal Manual (Untuk Transaksi Kompleks)</strong>
            </button>
        </h2>
        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Gunakan fitur ini untuk transaksi yang melibatkan lebih dari dua akun atau transaksi non-kas (misalnya: depresiasi, penyesuaian).</p>
                <ol>
                    <li>Buka menu <strong>Entri Data &raquo; Entri Jurnal</strong>.</li>
                    <li>Isi tanggal dan keterangan jurnal.</li>
                    <li>Klik <strong>"Tambah Baris"</strong> untuk menambahkan detail jurnal.</li>
                    <li>Pilih akun dan isi kolom Debit atau Kredit.</li>
                    <li>Pastikan <strong>Total Debit dan Total Kredit seimbang</strong> sebelum menyimpan.</li>
                </ol>
                <a href="<?= base_url('/entri-jurnal') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Contoh Halaman Entri Jurnal
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 5: Tutup Buku -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingFive">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                <strong>5. Proses Akhir Periode: Tutup Buku (Khusus Admin)</strong>
            </button>
        </h2>
        <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Proses Tutup Buku adalah langkah akuntansi yang dilakukan di akhir periode (biasanya akhir tahun) untuk menolkan saldo akun-akun sementara (Pendapatan dan Beban) dan memindahkan laba atau rugi bersih ke akun Laba Ditahan (Retained Earnings).</p>
                <div class="alert alert-warning small">
                    <strong>Penting:</strong> Fitur ini hanya dapat diakses oleh <strong>Admin</strong>. Pastikan semua transaksi pada periode tersebut sudah final sebelum melakukan tutup buku.
                </div>
                <ol>
                    <li>Buka menu <strong>Administrasi &raquo; Tutup Buku</strong>.</li>
                    <li>Pilih tanggal akhir periode yang akan ditutup (misalnya, 31 Desember 2023).</li>
                    <li>Klik tombol <strong>"Proses Tutup Buku"</strong> dan konfirmasi.</li>
                    <li>Sistem akan secara otomatis membuat Jurnal Penutup. Anda dapat melihat hasilnya di halaman <strong>Daftar Jurnal</strong>.</li>
                    <li>Setelah proses ini, semua transaksi sebelum tanggal tutup buku akan dikunci dan tidak dapat diubah atau dihapus.</li>
                </ol>
                <a href="<?= base_url('/tutup-buku') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Halaman Tutup Buku
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 6: Transaksi Berulang -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingSix">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                <strong>6. Otomatisasi dengan Transaksi Berulang</strong>
            </button>
        </h2>
        <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Fitur ini memungkinkan Anda untuk membuat template jurnal yang akan dijalankan secara otomatis oleh sistem sesuai jadwal yang Anda tentukan.</p>
                <ol>
                    <li>Pertama, buat draf jurnal yang ingin Anda jadikan template di halaman <strong>Entri Data &raquo; Entri Jurnal</strong>.</li>
                    <li>Setelah draf jurnal siap (akun dan jumlah sudah diisi), jangan klik "Simpan". Sebagai gantinya, klik tombol <strong>"Jadikan Berulang"</strong>.</li>
                    <li>Anda akan melihat modal untuk mengatur jadwal. Isi nama template (cth: "Bayar Gaji Bulanan"), frekuensi (setiap 1 bulan), dan tanggal mulai.</li>
                    <li>Klik <strong>"Simpan Template"</strong>.</li>
                    <li>Anda dapat melihat dan mengelola semua template yang sudah dibuat di halaman <strong>Entri Data &raquo; Transaksi Berulang</strong>.</li>
                    <li>Sistem akan secara otomatis membuat jurnal sesuai jadwal yang telah Anda atur.</li>
                </ol>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('/entri-jurnal') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Mulai dari Entri Jurnal
                    </a>
                    <a href="<?= base_url('/transaksi-berulang') ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Halaman Transaksi Berulang
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Panduan 7: Anggaran -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingSeven">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                <strong>7. Perencanaan & Kontrol: Anggaran (Budgeting)</strong>
            </button>
        </h2>
        <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Fitur Anggaran membantu Anda merencanakan pengeluaran dan membandingkannya dengan realisasi belanja setiap bulan.</p>
                <h5>Langkah 1: Mengatur Anggaran Tahunan</h5>
                <ol>
                    <li>Buka menu <strong>Laporan & Analisis &raquo; Anggaran</strong>.</li>
                    <li>Klik tombol <strong>"Kelola Anggaran"</strong>.</li>
                    <li>Sebuah modal akan muncul. Masukkan total anggaran untuk <strong>satu tahun penuh</strong> untuk setiap akun beban.</li>
                    <li>Klik <strong>"Simpan Anggaran"</strong>. Sistem akan otomatis menghitung anggaran bulanan (dibagi 12).</li>
                </ol>
                <h5>Langkah 2: Melihat Laporan Anggaran vs. Realisasi</h5>
                <ol>
                    <li>Di halaman Anggaran, pilih Bulan dan Tahun yang ingin Anda lihat.</li>
                    <li>Klik tombol <strong>"Tampilkan"</strong>.</li>
                    <li>Grafik dan tabel akan menunjukkan perbandingan antara anggaran bulanan dan total belanja yang sudah terjadi pada akun tersebut.</li>
                </ol>
                <a href="<?= base_url('/anggaran') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Halaman Anggaran
                </a>
            </div>
        </div>
    </div>

    <!-- Panduan 8: Analisis Rasio -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingEight">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight" aria-expanded="false" aria-controls="collapseEight">
                <strong>8. Analisis Mendalam: Analisis Rasio Keuangan</strong>
            </button>
        </h2>
        <div id="collapseEight" class="accordion-collapse collapse" aria-labelledby="headingEight" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Fitur ini secara otomatis menghitung rasio-rasio keuangan penting untuk memberikan gambaran cepat tentang kesehatan dan kinerja keuangan perusahaan Anda.</p>
                <h5>Langkah Penggunaan:</h5>
                <ol>
                    <li>Buka menu <strong>Laporan & Analisis &raquo; Analisis Rasio</strong>.</li>
                    <li>Pilih <strong>Tanggal Analisis</strong>. Sistem akan menggunakan data hingga tanggal ini untuk perhitungan.</li>
                    <li>(Opsional) Pilih <strong>Tanggal Pembanding</strong> untuk melihat perubahan rasio dari waktu ke waktu.</li>
                    <li>Klik tombol <strong>"Analisis"</strong>.</li>
                </ol>
                <hr>
                <h5>Daftar Rasio dan Penjelasannya:</h5>
                <dl class="row">
                    <dt class="col-sm-4">Profit Margin</dt>
                    <dd class="col-sm-8">
                        <p class="mb-1"><strong>Rumus:</strong> <code>(Laba Bersih / Total Pendapatan) * 100%</code></p>
                        <p class="small text-muted mb-0">Mengukur efisiensi perusahaan dalam menghasilkan laba dari pendapatannya. Semakin tinggi persentasenya, semakin baik.</p>
                    </dd>

                    <dt class="col-sm-4 mt-3">Debt to Equity Ratio</dt>
                    <dd class="col-sm-8 mt-3">
                        <p class="mb-1"><strong>Rumus:</strong> <code>Total Liabilitas / Total Ekuitas</code></p>
                        <p class="small text-muted mb-0">Mengukur seberapa besar perusahaan dibiayai oleh utang dibandingkan modal sendiri. Rasio di bawah 1.0 umumnya dianggap lebih aman.</p>
                    </dd>

                    <dt class="col-sm-4 mt-3">Debt to Asset Ratio</dt>
                    <dd class="col-sm-8 mt-3">
                        <p class="mb-1"><strong>Rumus:</strong> <code>Total Liabilitas / Total Aset</code></p>
                        <p class="small text-muted mb-0">Menunjukkan persentase aset perusahaan yang dibiayai melalui utang. Semakin rendah nilainya, semakin rendah risiko finansial.</p>
                    </dd>

                    <dt class="col-sm-4 mt-3">Return on Equity (ROE)</dt>
                    <dd class="col-sm-8 mt-3">
                        <p class="mb-1"><strong>Rumus:</strong> <code>(Laba Bersih / Total Ekuitas) * 100%</code></p>
                        <p class="small text-muted mb-0">Mengukur kemampuan perusahaan menghasilkan laba dari modal yang diinvestasikan oleh pemilik. Semakin tinggi, semakin efisien.</p>
                    </dd>

                    <dt class="col-sm-4 mt-3">Return on Assets (ROA)</dt>
                    <dd class="col-sm-8 mt-3">
                        <p class="mb-1"><strong>Rumus:</strong> <code>(Laba Bersih / Total Aset) * 100%</code></p>
                        <p class="small text-muted mb-0">Mengukur seberapa efisien perusahaan dalam menggunakan total asetnya untuk menghasilkan laba.</p>
                    </dd>
                </dl>
                <a href="<?= base_url('/analisis-rasio') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Halaman Analisis Rasio
                </a>
            </div> 
        </div>
    </div>

    <!-- Panduan 9: Pengaturan Aplikasi -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingNine">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNine" aria-expanded="false" aria-controls="collapseNine">
                <strong>9. Konfigurasi Sistem: Pengaturan Aplikasi (Khusus Admin)</strong>
            </button>
        </h2>
        <div id="collapseNine" class="accordion-collapse collapse" aria-labelledby="headingNine" data-bs-parent="#panduanAccordion">
            <div class="accordion-body">
                <p>Halaman ini adalah pusat kendali aplikasi, tempat Anda dapat menyesuaikan berbagai aspek sistem agar sesuai dengan kebutuhan Anda. Fitur ini hanya dapat diakses oleh <strong>Admin</strong>.</p>
                <h5>Area Pengaturan:</h5>
                <ul>
                    <li><strong>Umum:</strong> Mengubah nama aplikasi, logo, header laporan PDF, dan nama-nama pejabat untuk tanda tangan.</li>
                    <li><strong>Transaksi:</strong> Mengatur prefix untuk nomor referensi otomatis dan memilih akun kas default untuk pemasukan/pengeluaran.</li>
                    <li><strong>Akuntansi:</strong> Menentukan akun Laba Ditahan (Retained Earnings) yang krusial untuk proses Tutup Buku.</li>
                    <li><strong>Arus Kas:</strong> Memetakan akun-akun ke dalam kategori Laporan Arus Kas (Operasi, Investasi, Pendanaan).</li>
                    <li><strong>Konsinyasi:</strong> Memetakan akun-akun yang digunakan untuk fitur konsinyasi.</li>
                </ul>
                <h5>Langkah Penggunaan:</h5>
                <ol>
                    <li>Buka menu <strong>Administrasi &raquo; Pengaturan Aplikasi</strong>.</li>
                    <li>Pilih tab pengaturan yang ingin Anda ubah (misalnya, "Umum").</li>
                    <li>Lakukan perubahan yang diperlukan pada form.</li>
                    <li>Klik tombol <strong>"Simpan Pengaturan"</strong> di bagian bawah setiap tab untuk menerapkan perubahan.</li>
                </ol>
                <a href="<?= base_url('/settings') ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
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