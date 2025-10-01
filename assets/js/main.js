// =================================================================================
// APLIKASI RT - SINGLE PAGE APPLICATION (SPA) CORE
// =================================================================================
/**
 * Displays a toast notification.
 * @param {string} message The message to display.
 * @param {string} type The type of toast: 'success', 'error', or 'info'.
 * @param {string|null} title Optional title for the toast.
 */
function showToast(message, type = 'success', title = null) {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;

    const toastId = 'toast-' + Date.now();
    let toastIcon, defaultTitle;

    switch (type) {
        case 'error':
            toastIcon = '<i class="bi bi-x-circle-fill text-danger me-2"></i>';
            defaultTitle = 'Error';
            break;
        case 'info':
            toastIcon = '<i class="bi bi-bell-fill text-info me-2"></i>';
            defaultTitle = 'Notifikasi Baru';
            break;
        case 'success':
        default:
            toastIcon = '<i class="bi bi-check-circle-fill text-success me-2"></i>';
            defaultTitle = 'Sukses';
            break;
    }

    const toastTitle = title || defaultTitle;

    const toastHTML = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                ${toastIcon}
                <strong class="me-auto">${toastTitle}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 8000 });
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
}

/**
 * Formats a number into accounting-style currency string.
 * Negative numbers are shown in red and parentheses.
 * @param {number} value The number to format.
 * @returns {string} The formatted HTML string.
 */
function formatCurrencyAccounting(value) {
    const formatter = new Intl.NumberFormat('id-ID', { 
        style: 'decimal', // Use decimal to avoid currency symbol inside parentheses
        minimumFractionDigits: 0 
    });

    if (value < 0) {
        return `<span class="text-danger">(Rp ${formatter.format(Math.abs(value))})</span>`;
    } else if (value > 0) {
        return `Rp ${formatter.format(value)}`;
    } else {
        return `Rp 0`;
    }
}

/**
 * Updates the active link in the sidebar based on the current URL.
 * @param {string} path The path of the page being navigated to.
 */
function updateActiveSidebarLink(path) {
    const sidebarLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    sidebarLinks.forEach(link => {
        link.classList.remove('active');
        const linkPath = new URL(link.href).pathname;
        const cleanCurrentPath = path.length > 1 ? path.replace(/\/$/, "") : path;
        const cleanLinkPath = linkPath.length > 1 ? linkPath.replace(/\/$/, "") : linkPath;
        if (cleanLinkPath === cleanCurrentPath) {
            link.classList.add('active');
        }
    });
}

/**
 * Main navigation function for the SPA.
 * Fetches page content and injects it into the main content area.
 * @param {string} url The URL to navigate to.
 * @param {boolean} pushState Whether to push a new state to the browser history.
 */
async function navigate(url, pushState = true) {
    const mainContent = document.querySelector('.main-content');
    const loadingBar = document.getElementById('spa-loading-bar');
    if (!mainContent) return;

    // --- Start Loading ---
    if (loadingBar) {
        loadingBar.classList.remove('is-finished'); // Reset state
        loadingBar.classList.add('is-loading');
    }

    // 1. Mulai animasi fade-out
    mainContent.classList.add('is-transitioning');

    // 2. Tunggu animasi fade-out selesai (durasi harus cocok dengan CSS)
    await new Promise(resolve => setTimeout(resolve, 200));

    try {
        const response = await fetch(url, {
            headers: {
                'X-SPA-Request': 'true' // Custom header to tell the backend this is an SPA request
            }
        });

        // --- Finish Loading ---
        if (loadingBar) {
            loadingBar.classList.add('is-finished');
        }

        if (!response.ok) {
            throw new Error(`Server responded with status ${response.status}`);
        }

        const html = await response.text();

        if (pushState) {
            history.pushState({ path: url }, '', url);
        }

        // 3. Ganti konten saat tidak terlihat
        mainContent.innerHTML = html;
        updateActiveSidebarLink(new URL(url).pathname);
        
        // 4. Mulai animasi fade-in
        mainContent.classList.remove('is-transitioning');

        runPageScripts(new URL(url).pathname); // Run scripts for the new page

        // Handle hash for scrolling to a specific item
        const hash = new URL(url).hash;
        if (hash) { 
            // Use a small timeout to ensure the element is rendered by the page script
            setTimeout(() => {
                const element = document.querySelector(hash);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Add a temporary highlight effect
                    element.classList.add('highlight-item');
                    setTimeout(() => element.classList.remove('highlight-item'), 3000);
                }
            }, 300); // 300ms delay should be enough
        } 
    } catch (error) {
        console.error('Navigation error:', error);
        let errorMessage = 'Gagal memuat halaman. Silakan coba lagi.';
        if (error.message.includes('403')) {
            errorMessage = 'Akses Ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.';
        } else if (error.message.includes('404')) {
            errorMessage = 'Halaman tidak ditemukan. Halaman yang Anda cari tidak ada atau telah dipindahkan.';
        }
        mainContent.innerHTML = `<div class="alert alert-danger m-3">${errorMessage}</div>`;
        // Tampilkan juga pesan error dengan fade-in
        mainContent.classList.remove('is-transitioning');
    } finally {
        // Hide the loading bar after a short delay to let the 'finished' animation complete
        if (loadingBar) {
            setTimeout(() => {
                loadingBar.classList.remove('is-loading');
                loadingBar.classList.remove('is-finished');
            }, 500); // 500ms delay
        }
    }
}

/**
 * A client-side router to run page-specific JavaScript after content is loaded.
 * @param {string} path The current page's path.
 */
function runPageScripts(path) {
    // Normalisasi path untuk mencocokkan rute, menghapus base path dan query string.
    const cleanPath = path.replace(basePath, '').split('?')[0].replace(/\/$/, "") || '/';

    if (cleanPath === '/dashboard') {
        initDashboardPage();
    } else if (cleanPath === '/transaksi') {
        initTransaksiPage();
    } else if (cleanPath === '/entri-jurnal') {
        initEntriJurnalPage();
    } else if (cleanPath === '/coa') {
        initCoaPage();
    } else if (cleanPath === '/saldo-awal-neraca') {
        initSaldoAwalNeracaPage();
    } else if (cleanPath === '/saldo-awal-lr') {
        initSaldoAwalLRPage();
    } else if (cleanPath === '/laporan') {
        initLaporanPage();
    } else if (cleanPath === '/laporan-harian') {
        initLaporanHarianPage();
    } else if (cleanPath === '/buku-besar') {
        initBukuBesarPage();
    } else if (cleanPath === '/settings') {
        initSettingsPage();
    } else if (cleanPath === '/my-profile/change-password') {
        initMyProfilePage();
    }
}

// =================================================================================
// PAGE-SPECIFIC INITIALIZATION FUNCTIONS
// =================================================================================

function initDashboardPage() {
    const bulanFilter = document.getElementById('dashboard-bulan-filter');
    const tahunFilter = document.getElementById('dashboard-tahun-filter');

    // Event listener untuk tombol "Tambah Transaksi"
    const addTransaksiBtn = document.getElementById('dashboard-add-transaksi');
    if (addTransaksiBtn) {
        addTransaksiBtn.addEventListener('click', (e) => {
            e.preventDefault();
            navigate(addTransaksiBtn.href + '#add'); // Tambahkan hash untuk memicu modal
        });
    }
    if (!bulanFilter || !tahunFilter) return;

    function setupFilters() {
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;

        // Populate years
        for (let i = 0; i < 5; i++) {
            const year = currentYear - i;
            tahunFilter.add(new Option(year, year));
        }

        // Populate months
        const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        months.forEach((month, index) => {
            bulanFilter.add(new Option(month, index + 1));
        });

        // Set default to current month and year
        bulanFilter.value = currentMonth;
        tahunFilter.value = currentYear;
    }

    async function fetchDashboardData(bulan, tahun) {
        // Hapus container dashboard lama jika ada, untuk mencegah duplikasi
        const oldDashboardContent = document.getElementById('dashboard-content-wrapper');
        if (oldDashboardContent) {
            oldDashboardContent.remove();
        }

        const newWidgetsHtml = `
            <div id="dashboard-content-wrapper" class="mt-4">
                <!-- Baris Statistik Utama -->
                <div class="row g-3">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card text-white bg-primary h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Saldo Kas</h5>
                            <h2 class="fw-bold" id="total-saldo-widget"><div class="spinner-border spinner-border-sm"></div></h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card text-white bg-success h-100">
                        <div class="card-body">
                            <h5 class="card-title">Pemasukan Bulan Ini</h5>
                            <h2 class="fw-bold" id="pemasukan-widget"><div class="spinner-border spinner-border-sm"></div></h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card text-white bg-danger h-100">
                        <div class="card-body">
                            <h5 class="card-title">Pengeluaran Bulan Ini</h5>
                            <h2 class="fw-bold" id="pengeluaran-widget"><div class="spinner-border spinner-border-sm"></div></h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card text-white bg-info h-100">
                        <div class="card-body">
                            <h5 class="card-title">Laba/Rugi Bulan Ini</h5>
                            <h2 class="fw-bold" id="laba-rugi-widget"><div class="spinner-border spinner-border-sm"></div></h2>
                            <small id="laba-rugi-subtitle"></small>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Baris Grafik Tren dan Status Neraca -->
                <div class="row g-3">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card h-100" id="balance-status-card">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                             <div id="balance-status-icon" class="fs-1"><div class="spinner-border"></div></div>
                             <h5 class="card-title mt-2" id="balance-status-text">Memeriksa Status...</h5>
                             <small class="text-muted">Keseimbangan Neraca</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tren Laba/Rugi (30 Hari Terakhir)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="profit-loss-trend-chart"></canvas>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Baris Kategori Pengeluaran dan Transaksi Terbaru -->
                <div class="row g-3">
                <div class="col-lg-5 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Pengeluaran per Kategori</h5>
                        </div>
                        <div class="card-body d-flex justify-content-center align-items-center">
                            <div style="position: relative; height:250px; width:100%">
                                <canvas id="expense-category-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Transaksi Terbaru</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr><th>Tanggal</th><th>Keterangan</th><th class="text-end">Jumlah</th></tr>
                                    </thead>
                                    <tbody id="recent-transactions-widget">
                                        <tr><td colspan="3" class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        `;
        // Sisipkan setelah elemen h1 dan filter
        const borderBottom = document.querySelector('.main-content .row.g-3.mb-4'); // Sisipkan setelah baris tombol aksi
        if (borderBottom) {
            borderBottom.insertAdjacentHTML('afterend', newWidgetsHtml);
        }

        // Ambil data dari API
        try {
            const response = await fetch(`${basePath}/api/dashboard?bulan=${bulan}&tahun=${tahun}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const data = result.data;
            const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

            document.getElementById('total-saldo-widget').textContent = currencyFormatter.format(data.total_saldo);

            // Render Balance Status
            const balanceCard = document.getElementById('balance-status-card');
            const balanceIcon = document.getElementById('balance-status-icon');
            const balanceText = document.getElementById('balance-status-text');
            const balanceStatus = data.balance_status;

            // Hapus event listener lama jika ada untuk mencegah duplikasi
            balanceCard.style.cursor = 'default';
            balanceCard.onclick = null;

            if (balanceStatus.is_balanced) {
                balanceCard.classList.add('bg-success-subtle');
                balanceIcon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                balanceText.textContent = 'Balance';
            } else {
                balanceCard.classList.add('bg-danger-subtle');
                balanceCard.style.cursor = 'pointer'; // Jadikan kartu bisa diklik
                balanceIcon.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                balanceText.textContent = 'Tidak Balance';

                // Tambahkan event listener untuk membuka modal detail
                balanceCard.onclick = () => {
                    const detailModalEl = document.getElementById('detailModal');
                    const detailModal = new bootstrap.Modal(detailModalEl);
                    document.getElementById('detailModalLabel').textContent = 'Detail Ketidakseimbangan Neraca';
                    const modalBody = document.getElementById('detailModalBody');

                    let journalDetailsHtml = '';
                    if (balanceStatus.unbalanced_journals && balanceStatus.unbalanced_journals.length > 0) {
                        journalDetailsHtml = `
                            <h5 class="mt-4">Jurnal Tidak Seimbang Terdeteksi</h5>
                            <p class="text-muted">Berikut adalah daftar entri jurnal yang kemungkinan menjadi penyebab ketidakseimbangan. Klik pada ID Jurnal untuk memperbaikinya.</p>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID Jurnal</th>
                                            <th>Tanggal</th>
                                            <th>Keterangan</th>
                                            <th class="text-end">Total Debit</th>
                                            <th class="text-end">Total Kredit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${balanceStatus.unbalanced_journals.map(j => `
                                            <tr>
                                                <td><a href="${basePath}/entri-jurnal?edit_id=${j.id}">JRN-${String(j.id).padStart(5, '0')}</a></td>
                                                <td>${new Date(j.tanggal).toLocaleDateString('id-ID')}</td>
                                                <td>${j.keterangan}</td>
                                                <td class="text-end">${currencyFormatter.format(j.total_debit)}</td>
                                                <td class="text-end">${currencyFormatter.format(j.total_kredit)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }

                    modalBody.innerHTML = `
                        <p>Neraca Anda tidak seimbang. Berikut adalah rincian perhitungannya:</p>
                        <dl class="row">
                            <dt class="col-sm-6">Total Aset</dt><dd class="col-sm-6 text-end">${currencyFormatter.format(balanceStatus.total_aset)}</dd>
                            <dt class="col-sm-6">Total Liabilitas + Ekuitas</dt><dd class="col-sm-6 text-end">${currencyFormatter.format(balanceStatus.total_liabilitas_ekuitas)}</dd>
                            <dt class="col-sm-6 border-top pt-2">Selisih</dt><dd class="col-sm-6 text-end border-top pt-2 fw-bold text-danger">${currencyFormatter.format(balanceStatus.selisih)}</dd>
                        </dl>
                        ${journalDetailsHtml}
                    `;
                    detailModal.show();
                };
            }
            document.getElementById('pemasukan-widget').textContent = currencyFormatter.format(data.pemasukan_bulan_ini);
            document.getElementById('pengeluaran-widget').textContent = currencyFormatter.format(data.pengeluaran_bulan_ini);
            
            const labaRugiWidget = document.getElementById('laba-rugi-widget');
            const labaRugiSubtitle = document.getElementById('laba-rugi-subtitle');
            labaRugiWidget.textContent = currencyFormatter.format(data.laba_rugi_bulan_ini);
            if (data.laba_rugi_bulan_ini < 0) {
                labaRugiWidget.parentElement.parentElement.classList.replace('bg-info', 'bg-warning');
                labaRugiSubtitle.textContent = 'Rugi';
            } else {
                labaRugiSubtitle.textContent = 'Laba';
            }

            // Render recent transactions
            const txWidget = document.getElementById('recent-transactions-widget');
            txWidget.innerHTML = '';
            if (data.transaksi_terbaru.length > 0) {
                data.transaksi_terbaru.forEach(tx => {
                    const isIncome = tx.jenis === 'pemasukan';
                    const row = `
                        <tr>
                            <td>${new Date(tx.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short'})}</td>
                            <td>${tx.keterangan}</td>
                            <td class="text-end ${isIncome ? 'text-success' : 'text-danger'}">${currencyFormatter.format(tx.jumlah)}</td>
                        </tr>
                    `;
                    txWidget.insertAdjacentHTML('beforeend', row);
                });
            } else {
                txWidget.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Tidak ada transaksi.</td></tr>';
            }

            // Render chart
            const chartCanvas = document.getElementById('expense-category-chart');
            if (window.expenseChart) window.expenseChart.destroy();
            window.expenseChart = new Chart(chartCanvas, {
                type: 'doughnut',
                data: {
                    labels: data.pengeluaran_per_kategori.labels,
                    datasets: [{
                        data: data.pengeluaran_per_kategori.data,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right' } }
                }
            });

            // Render profit loss trend chart
            const trendChartCanvas = document.getElementById('profit-loss-trend-chart');
            if (window.trendChart) window.trendChart.destroy();
            window.trendChart = new Chart(trendChartCanvas, {
                type: 'line',
                data: {
                    labels: data.laba_rugi_harian.labels.map(d => new Date(d).toLocaleDateString('id-ID', {day: '2-digit', month: 'short'})),
                    datasets: [{
                        label: 'Laba / Rugi Harian',
                        data: data.laba_rugi_harian.data,
                        fill: true,
                        backgroundColor: 'rgba(0, 122, 255, 0.1)',
                        borderColor: 'rgba(0, 122, 255, 1)',
                        tension: 0.3,
                        pointBackgroundColor: 'rgba(0, 122, 255, 1)',
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            showToast(`Gagal memuat data dashboard: ${error.message}`, 'error');
        }
    }

    // --- Event Listeners ---
    const filterHandler = () => fetchDashboardData(bulanFilter.value, tahunFilter.value);
    bulanFilter.addEventListener('change', filterHandler);
    tahunFilter.addEventListener('change', filterHandler);

    setupFilters();
    fetchDashboardData(bulanFilter.value, tahunFilter.value);
}

function initTransaksiPage() {
    const tableBody = document.getElementById('transaksi-table-body');
    const modalEl = document.getElementById('transaksiModal');
    const jurnalDetailModalEl = document.getElementById('jurnalDetailModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('transaksi-form');

    // Cek jika URL memiliki hash '#add', buka modal secara otomatis
    if (window.location.hash === '#add') {
        // Gunakan timeout kecil untuk memastikan modal siap
        setTimeout(() => document.getElementById('add-transaksi-btn')?.click(), 100);
    }

    const saveBtn = document.getElementById('save-transaksi-btn');
    const jenisBtnGroup = document.getElementById('jenis-btn-group');

    // Filter elements
    const searchInput = document.getElementById('search-transaksi');
    const akunKasFilter = document.getElementById('filter-akun-kas');
    const bulanFilter = document.getElementById('filter-bulan');
    const tahunFilter = document.getElementById('filter-tahun');
    const limitSelect = document.getElementById('filter-limit');
    const paginationContainer = document.getElementById('transaksi-pagination');

    if (!tableBody) return;

    // Load saved limit from localStorage
    const savedLimit = localStorage.getItem('transaksi_limit');
    if (savedLimit) limitSelect.value = savedLimit;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    function setupFilters() {
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;

        for (let i = 0; i < 5; i++) {
            tahunFilter.add(new Option(currentYear - i, currentYear - i));
        }
        const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        bulanFilter.innerHTML = '<option value="">Semua Bulan</option>';
        months.forEach((month, index) => {
            bulanFilter.add(new Option(month, index + 1));
        });

        bulanFilter.value = currentMonth;
        tahunFilter.value = currentYear;
    }

    async function loadAccountsForForm() {
        try {
            const response = await fetch(`${basePath}/api/transaksi?action=get_accounts_for_form`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const { kas, pendapatan, beban } = result.data;

            // Populate filter
            akunKasFilter.innerHTML = '<option value="">Semua Akun Kas/Bank</option>';
            kas.forEach(acc => akunKasFilter.add(new Option(acc.nama_akun, acc.id)));

            // Populate modal dropdowns
            const kasSelects = ['kas_account_id_pemasukan', 'kas_account_id_pengeluaran', 'kas_account_id_transfer', 'kas_tujuan_account_id'];
            kasSelects.forEach(id => {
                const select = document.getElementById(id);
                select.innerHTML = '';
                kas.forEach(acc => select.add(new Option(acc.nama_akun, acc.id)));
            });

            const pendapatanSelect = document.getElementById('account_id_pemasukan');
            pendapatanSelect.innerHTML = '';
            pendapatan.forEach(acc => pendapatanSelect.add(new Option(acc.nama_akun, acc.id)));

            const bebanSelect = document.getElementById('account_id_pengeluaran');
            bebanSelect.innerHTML = '';
            beban.forEach(acc => bebanSelect.add(new Option(acc.nama_akun, acc.id)));

        } catch (error) {
            showToast(`Gagal memuat daftar akun: ${error.message}`, 'error');
        }
    }

    async function loadTransaksi(page = 1) {
        const params = new URLSearchParams({
            page,
            limit: limitSelect.value,
            search: searchInput.value,
            bulan: bulanFilter.value,
            tahun: tahunFilter.value,
            akun_kas: akunKasFilter.value,
        });

        tableBody.innerHTML = `<tr><td colspan="7" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;
        try {
            const response = await fetch(`${basePath}/api/transaksi?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(tx => {
                    let akunUtama, akunKas, jumlahDisplay;
                    const jumlahFormatted = currencyFormatter.format(tx.jumlah);

                    if (tx.jenis === 'pemasukan') {
                        akunUtama = `<span class="badge bg-success">Pemasukan</span> ${tx.nama_akun_utama}`;
                        akunKas = `Ke: ${tx.nama_akun_kas}`;
                        jumlahDisplay = `<span class="text-success fw-bold">+ ${jumlahFormatted}</span>`;
                    } else if (tx.jenis === 'pengeluaran') {
                        akunUtama = `<span class="badge bg-danger">Pengeluaran</span> ${tx.nama_akun_utama}`;
                        akunKas = `Dari: ${tx.nama_akun_kas}`;
                        jumlahDisplay = `<span class="text-danger fw-bold">- ${jumlahFormatted}</span>`;
                    } else { // transfer
                        akunUtama = `<span class="badge bg-info">Transfer</span>`;
                        akunKas = `Dari: ${tx.nama_akun_kas}<br>Ke: ${tx.nama_akun_tujuan}`;
                        jumlahDisplay = `<span class="text-info fw-bold">${jumlahFormatted}</span>`;
                    }

                    const row = `
                        <tr>
                            <td>${new Date(tx.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'})}</td>
                            <td>${akunUtama}</td>
                            <td><small class="text-muted">${tx.nomor_referensi || '-'}</small></td>
                            <td>${tx.keterangan}</td>
                            <td class="text-end">${jumlahDisplay}</td>
                            <td><small>${akunKas}</small></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-danger delete-btn" data-id="${tx.id}" data-keterangan="${tx.keterangan}" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                <button class="btn btn-sm btn-secondary view-journal-btn" data-id="${tx.id}" title="Lihat Jurnal"><i class="bi bi-journal-text"></i></button>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center">Tidak ada transaksi ditemukan.</td></tr>';
            }
            renderPagination(paginationContainer, result.pagination, loadTransaksi);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    function toggleFormFields() {
        const jenis = document.getElementById('jenis').value;
        document.getElementById('pemasukan-fields').style.display = jenis === 'pemasukan' ? 'flex' : 'none';
        document.getElementById('pengeluaran-fields').style.display = jenis === 'pengeluaran' ? 'flex' : 'none';
        document.getElementById('transfer-fields').style.display = jenis === 'transfer' ? 'flex' : 'none';
    }

    // --- Event Listeners ---
    if (jenisBtnGroup) {
        jenisBtnGroup.addEventListener('click', (e) => {
            const button = e.target.closest('button');
            if (!button) return;

            const selectedValue = button.dataset.value;
            document.getElementById('jenis').value = selectedValue;

            // Update button styles
            const buttons = jenisBtnGroup.querySelectorAll('button');
            buttons.forEach(btn => {
                btn.classList.remove('active', 'btn-danger', 'btn-success', 'btn-info');
                btn.classList.add(`btn-outline-${btn.dataset.value === 'pengeluaran' ? 'danger' : (btn.dataset.value === 'pemasukan' ? 'success' : 'info')}`);
            });
            button.classList.add('active', `btn-${button.dataset.value === 'pengeluaran' ? 'danger' : (button.dataset.value === 'pemasukan' ? 'success' : 'info')}`);
            toggleFormFields();
        });
    }

    saveBtn.addEventListener('click', async () => {
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            showToast('Harap isi semua field yang wajib.', 'error');
            return;
        }
        form.classList.remove('was-validated');

        const formData = new FormData(form);
        const originalBtnHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

        try {
            const response = await fetch(`${basePath}/api/transaksi`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                modal.hide();
                loadTransaksi(1); // Kembali ke halaman pertama setelah menambah data
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    });

    tableBody.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            const { id, keterangan } = deleteBtn.dataset;
            if (confirm(`Yakin ingin menghapus transaksi "${keterangan}"?`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                const response = await fetch(`${basePath}/api/transaksi`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') loadTransaksi(1); // Kembali ke halaman pertama setelah menghapus
            }
        }

        const viewJournalBtn = e.target.closest('.view-journal-btn');
        const editBtn = e.target.closest('.edit-btn');

        if (editBtn) {
            const id = editBtn.dataset.id;
            try {
                const response = await fetch(`${basePath}/api/transaksi?action=get_single&id=${id}`);
                const result = await response.json();
                if (result.status !== 'success') throw new Error(result.message);

                const tx = result.data;
                document.getElementById('transaksiModalLabel').textContent = 'Edit Transaksi';
                form.reset();
                form.classList.remove('was-validated');
                document.getElementById('transaksi-id').value = tx.id;
                document.getElementById('transaksi-action').value = 'update';
                jenisBtnGroup.querySelector(`button[data-value="${tx.jenis}"]`).click(); // Simulate click to set value and style
                document.getElementById('tanggal').value = tx.tanggal;
                document.getElementById('jumlah').value = tx.jumlah;
                document.getElementById('nomor_referensi').value = tx.nomor_referensi;
                document.getElementById('keterangan').value = tx.keterangan;
                toggleFormFields(); // Update visible fields based on 'jenis'
                
                // Set selected values for dropdowns
                if (tx.jenis === 'pemasukan') { document.getElementById('kas_account_id_pemasukan').value = tx.kas_account_id; document.getElementById('account_id_pemasukan').value = tx.account_id; } 
                else if (tx.jenis === 'pengeluaran') { document.getElementById('kas_account_id_pengeluaran').value = tx.kas_account_id; document.getElementById('account_id_pengeluaran').value = tx.account_id; } 
                else if (tx.jenis === 'transfer') { document.getElementById('kas_account_id_transfer').value = tx.kas_account_id; document.getElementById('kas_tujuan_account_id').value = tx.kas_tujuan_account_id; }
                modal.show();
            } catch (error) { showToast(`Gagal memuat data transaksi: ${error.message}`, 'error'); }
        }

        if (viewJournalBtn) {
            const id = viewJournalBtn.dataset.id;
            const jurnalModal = new bootstrap.Modal(jurnalDetailModalEl);
            const modalBody = document.getElementById('jurnal-detail-body');
            modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border"></div></div>';
            jurnalModal.show();

            try {
                const response = await fetch(`${basePath}/api/transaksi?action=get_journal_entry&id=${id}`);
                const result = await response.json();
                if (result.status !== 'success') throw new Error(result.message);

                const { transaksi, jurnal } = result.data;
                let tableHtml = `
                    <p><strong>Tanggal:</strong> ${new Date(transaksi.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'})}</p>
                    <p><strong>No. Referensi:</strong> ${transaksi.nomor_referensi || '-'}</p>
                    <p><strong>Keterangan:</strong> ${transaksi.keterangan}</p>
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Akun</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                jurnal.forEach(entry => {
                    tableHtml += `
                        <tr>
                            <td>${entry.akun}</td>
                            <td class="text-end">${entry.debit > 0 ? currencyFormatter.format(entry.debit) : '-'}</td>
                            <td class="text-end">${entry.kredit > 0 ? currencyFormatter.format(entry.kredit) : '-'}</td>
                        </tr>
                    `;
                });
                tableHtml += `</tbody></table>`;
                modalBody.innerHTML = tableHtml;
            } catch (error) {
                modalBody.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            }
        }
    });

    modalEl.addEventListener('show.bs.modal', (e) => {
        const button = e.relatedTarget;
        if (button && button.dataset.action === 'add') { 
            // Ambil pengaturan default dari API
            fetch(`${basePath}/api/settings`).then(res => res.json()).then(result => {
                const settings = result.data || {};
                document.getElementById('transaksiModalLabel').textContent = 'Tambah Transaksi Baru';
                form.reset();
                form.classList.remove('was-validated');
                document.getElementById('transaksi-id').value = '';
                document.getElementById('transaksi-action').value = 'add';
                document.getElementById('tanggal').valueAsDate = new Date();
                
                // Set default to 'pengeluaran' by simulating a click
                jenisBtnGroup.querySelector('button[data-value="pengeluaran"]').click();

                // Set default cash accounts
                if (settings.default_cash_in) document.getElementById('kas_account_id_pemasukan').value = settings.default_cash_in;
                if (settings.default_cash_out) document.getElementById('kas_account_id_pengeluaran').value = settings.default_cash_out;
                if (settings.default_cash_out) document.getElementById('kas_account_id_transfer').value = settings.default_cash_out;
            });
        }
    });

    let debounceTimer;
    const combinedFilterHandler = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadTransaksi(1), 300);
        localStorage.setItem('transaksi_limit', limitSelect.value); // Save limit on change
    };

    [searchInput, akunKasFilter, bulanFilter, tahunFilter, limitSelect].forEach(el => {
        el.addEventListener('change', combinedFilterHandler);
    });
    searchInput.addEventListener('input', combinedFilterHandler);

    // --- Initial Load ---
    setupFilters();
    loadAccountsForForm().then(() => {
        loadTransaksi();
    });
}

function initCoaPage() {
    const treeContainer = document.getElementById('coa-tree-container');
    const modalEl = document.getElementById('coaModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('coa-form');
    const saveBtn = document.getElementById('save-coa-btn');

    if (!treeContainer || !modalEl || !form || !saveBtn) return;

    let flatAccounts = []; // Store flat list for populating dropdown

    function buildTree(list, parentId = null) {
        const children = list.filter(item => item.parent_id == parentId);
        if (children.length === 0) return null;

        return children.map(child => ({
            ...child,
            children: buildTree(list, child.id)
        }));
    }

    function renderTree(nodes, container, level = 0) {
        const ul = document.createElement('ul');
        ul.className = `list-group ${level > 0 ? 'ms-4 mt-2' : 'list-group-flush'}`;

        nodes.forEach(node => {
            const li = document.createElement('li');
            // Gunakan 'list-group-item' untuk semua, karena Bootstrap 5 menangani border dengan baik.
            li.className = 'list-group-item'; 
            li.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold">${node.kode_akun}</span> - ${node.nama_akun}
                        <small class="text-muted">(${node.tipe_akun})</small>
                        ${node.is_kas == 1 ? '<span class="badge bg-success ms-2">Akun Kas</span>' : ''}
                    </div>
                    <div>
                        <button class="btn btn-sm btn-info edit-btn" data-id="${node.id}" title="Edit"><i class="bi bi-pencil-fill"></i></button>
                        <button class="btn btn-sm btn-danger delete-btn" data-id="${node.id}" data-nama="${node.nama_akun}" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                    </div>
                </div>
            `;
            ul.appendChild(li);

            if (node.children) {
                // Render sub-akun di dalam <li> induk, bukan di dalam div baru.
                renderTree(node.children, li, level + 1);
            }
        });
        container.appendChild(ul);
    }

    function populateParentDropdown(selectedId = null) {
        const parentSelect = document.getElementById('parent_id');
        parentSelect.innerHTML = '<option value="">-- Akun Induk (Root) --</option>';
        flatAccounts.forEach(acc => {
            const option = new Option(`${acc.kode_akun} - ${acc.nama_akun}`, acc.id);
            if (acc.id == selectedId) option.selected = true;
            parentSelect.add(option);
        });
    }

    async function loadCoaData() {
        treeContainer.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        try {
            const response = await fetch(`${basePath}/api/coa`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            flatAccounts = result.data;
            const tree = buildTree(flatAccounts);
            treeContainer.innerHTML = '';
            if (tree) {
                renderTree(tree, treeContainer);
            } else {
                treeContainer.innerHTML = '<div class="alert alert-info">Bagan Akun masih kosong.</div>';
            }
            populateParentDropdown();
        } catch (error) {
            treeContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat data: ${error.message}</div>`;
        }
    }

    saveBtn.addEventListener('click', async () => {
        const formData = new FormData(form);
        const originalBtnHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

        try {
            const response = await fetch(`${basePath}/api/coa`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                modal.hide();
                loadCoaData();
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    });

    treeContainer.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            const id = editBtn.dataset.id;
            const formData = new FormData();
            formData.append('action', 'get_single');
            formData.append('id', id);
            const response = await fetch(`${basePath}/api/coa`, { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                document.getElementById('coaModalLabel').textContent = 'Edit Akun';
                form.reset();
                const acc = result.data;
                document.getElementById('coa-id').value = acc.id;
                document.getElementById('coa-action').value = 'update';
                populateParentDropdown(acc.parent_id);
                document.getElementById('kode_akun').value = acc.kode_akun;
                document.getElementById('nama_akun').value = acc.nama_akun;
                document.getElementById('tipe_akun').value = acc.tipe_akun;
                document.getElementById('is_kas').checked = (acc.is_kas == 1);
                modal.show();
            }
        }

        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            const { id, nama } = deleteBtn.dataset;
            if (confirm(`Yakin ingin menghapus akun "${nama}"?`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                const response = await fetch(`${basePath}/api/coa`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') loadCoaData();
            }
        }
    });

    modalEl.addEventListener('show.bs.modal', (e) => {
        const button = e.relatedTarget;
        if (button && button.dataset.action === 'add') {
            document.getElementById('coaModalLabel').textContent = 'Tambah Akun Baru';
            form.reset();
            document.getElementById('coa-id').value = '';
            document.getElementById('coa-action').value = 'add';
            populateParentDropdown();
        }
    });

    loadCoaData();
}

function initKategoriPage() {
    console.log("Halaman Kategori diinisialisasi. (Belum diimplementasikan)");
}
function initLaporanPage() {
    const neracaTanggalInput = document.getElementById('neraca-tanggal');
    const neracaContent = document.getElementById('neraca-content');
    const labaRugiTab = document.getElementById('laba-rugi-tab');
    const labaRugiContent = document.getElementById('laba-rugi-content');
    const labaRugiTglMulai = document.getElementById('laba-rugi-tanggal-mulai');
    const labaRugiTglAkhir = document.getElementById('laba-rugi-tanggal-akhir');
    const arusKasTab = document.getElementById('arus-kas-tab');
    const arusKasContent = document.getElementById('arus-kas-content');
    const arusKasTglMulai = document.getElementById('arus-kas-tanggal-mulai');
    const arusKasTglAkhir = document.getElementById('arus-kas-tanggal-akhir');

    const exportNeracaPdfBtn = document.getElementById('export-neraca-pdf');
    const exportLrPdfBtn = document.getElementById('export-lr-pdf');
    const exportAkPdfBtn = document.getElementById('export-ak-pdf');
    const exportNeracaCsvBtn = document.getElementById('export-neraca-csv');
    const exportLrCsvBtn = document.getElementById('export-lr-csv');
    const exportAkCsvBtn = document.getElementById('export-ak-csv');


    const storageKey = 'laporan_filters';

    if (!neracaTanggalInput || !neracaContent) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    function saveFilters() {
        const filtersToSave = {
            neraca_tanggal: neracaTanggalInput.value,
            lr_start: labaRugiTglMulai.value,
            lr_end: labaRugiTglAkhir.value,
            ak_start: arusKasTglMulai.value,
            ak_end: arusKasTglAkhir.value,
        };
        localStorage.setItem(storageKey, JSON.stringify(filtersToSave));
    }

    function loadAndSetFilters() {
        const savedFilters = JSON.parse(localStorage.getItem(storageKey)) || {};
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];

        neracaTanggalInput.value = savedFilters.neraca_tanggal || today;

        labaRugiTglMulai.value = savedFilters.lr_start || firstDay;
        labaRugiTglAkhir.value = savedFilters.lr_end || lastDay;

        arusKasTglMulai.value = savedFilters.ak_start || firstDay;
        arusKasTglAkhir.value = savedFilters.ak_end || lastDay;
    }

    function renderNeraca(data) {
        neracaContent.innerHTML = '';

        const renderRows = (items, level = 0) => {
            let html = '';
            items.forEach(item => {
                const isParent = item.children && item.children.length > 0;
                const padding = level * 20;
                const fw = isParent ? 'fw-bold' : '';
                
                // Saldo yang akan ditampilkan. Untuk akun induk, ini adalah jumlah dari saldo anak-anaknya.
                // Untuk akun anak (tanpa turunan), ini adalah saldo akhirnya sendiri.
                let saldoToShow;
                if (isParent) {
                    // Fungsi rekursif untuk menjumlahkan semua saldo akhir dari daun (leaf nodes)
                    const sumLeafNodes = (node) => {
                        if (!node.children || node.children.length === 0) return parseFloat(node.saldo_akhir);
                        return node.children.reduce((acc, child) => acc + sumLeafNodes(child), 0);
                    };
                    saldoToShow = sumLeafNodes(item);
                } else {
                    saldoToShow = parseFloat(item.saldo_akhir);
                }

                html += `
                    <tr>
                        <td style="padding-left: ${padding}px;" class="${fw}">${item.nama_akun}</td>
                        <td class="text-end ${fw}">${formatCurrencyAccounting(saldoToShow)}</td>
                    </tr>
                `;
                if (isParent) {
                    html += renderRows(item.children, level + 1);
                }
            });
            return html;
        };

        const buildHierarchy = (list, parentId = null) => list
            .filter(item => item.parent_id == parentId)
            .map(item => ({ ...item, children: buildHierarchy(list, item.id) }));

        // Perbaiki fungsi calculateTotal untuk menjumlahkan semua item dalam data, bukan hanya root.
        const calculateTotal = (data) => data.reduce((acc, item) => acc + parseFloat(item.saldo_akhir), 0);

        const asetData = data.filter(d => d.tipe_akun === 'Aset');
        const liabilitasData = data.filter(d => d.tipe_akun === 'Liabilitas');
        const ekuitasData = data.filter(d => d.tipe_akun === 'Ekuitas');

        const aset = buildHierarchy(asetData);
        const liabilitas = buildHierarchy(liabilitasData);
        const ekuitas = buildHierarchy(ekuitasData);

        const totalAset = calculateTotal(asetData);
        const totalLiabilitas = calculateTotal(liabilitasData);
        const totalEkuitas = calculateTotal(ekuitasData);
        const totalLiabilitasEkuitas = totalLiabilitas + totalEkuitas;

        const isBalanced = Math.abs(totalAset - totalLiabilitasEkuitas) < 0.01;
        const balanceStatusClass = isBalanced ? 'table-success' : 'table-danger';
        const balanceStatusText = isBalanced ? 'BALANCE' : 'TIDAK BALANCE';
        const balanceBadge = document.getElementById('neraca-balance-status-badge');
        if (balanceBadge) {
            balanceBadge.innerHTML = `<span class="badge ${isBalanced ? 'bg-success' : 'bg-danger'}">${balanceStatusText}</span>`;
        }

        const neracaHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h5>Aset</h5>
                    <table class="table table-sm"><tbody>${renderRows(asetData)}</tbody></table><br>
                </div>
                <div class="col-md-6">
                    <h5>Liabilitas</h5>
                    <table class="table table-sm"><tbody>${renderRows(liabilitasData)}</tbody></table>
                    <table class="table"><tbody><tr class="table-light"><td class="fw-bold">TOTAL LIABILITAS</td><td class="text-end fw-bold">${formatCurrencyAccounting(totalLiabilitas)}</td></tr></tbody></table><br>

                    <h5 class="mt-4">Ekuitas</h5>
                    <table class="table table-sm"><tbody>${renderRows(ekuitasData)}</tbody></table>
                    <table class="table"><tbody><tr class="table-light"><td class="fw-bold">TOTAL EKUITAS</td><td class="text-end fw-bold">${formatCurrencyAccounting(totalEkuitas)}</td></tr></tbody></table><br>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-6">
                    <table class="table"><tbody><tr class="${balanceStatusClass}"><td class="fw-bold">TOTAL ASET</td><td class="text-end fw-bold">${formatCurrencyAccounting(totalAset)}</td></tr></tbody></table>
                </div>
                <div class="col-md-6">
                    <table class="table"><tbody><tr class="${balanceStatusClass}"><td class="fw-bold">TOTAL LIABILITAS + EKUITAS</td><td class="text-end fw-bold">${formatCurrencyAccounting(totalLiabilitasEkuitas)}</td></tr></tbody></table>
                </div>
            </div>
        `;
        neracaContent.innerHTML = neracaHtml;
    }

    async function loadNeraca() {
        const tanggal = neracaTanggalInput.value;
        neracaContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        try {
            const response = await fetch(`${basePath}/api/laporan/neraca?tanggal=${tanggal}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            renderNeraca(result.data);
        } catch (error) {
            neracaContent.innerHTML = `<div class="alert alert-danger">Gagal memuat laporan: ${error.message}</div>`;
        }
    }

    function renderLabaRugi(data) {
        labaRugiContent.innerHTML = '';
        const { pendapatan, beban, summary } = data;

        const renderRows = (items) => {
            let html = '';
            if (!items || items.length === 0) return '<tr><td colspan="2" class="text-muted">Tidak ada data.</td></tr>';
            items.forEach(item => {
                html += `<tr><td>${item.nama_akun}</td><td class="text-end">${formatCurrencyAccounting(item.total)}</td></tr>`;
            });
            return html;
        };

        const labaRugiHtml = `
            <h5 class="mt-3">Pendapatan</h5>
            <table class="table table-sm"><tbody>${renderRows(pendapatan)}</tbody></table>
            <table class="table"><tbody><tr class="table-light"><td class="fw-bold">TOTAL PENDAPATAN</td><td class="text-end fw-bold">${formatCurrencyAccounting(summary.total_pendapatan)}</td></tr></tbody></table>

            <h5 class="mt-4">Beban</h5>
            <table class="table table-sm"><tbody>${renderRows(beban)}</tbody></table>
            <table class="table"><tbody><tr class="table-light"><td class="fw-bold">TOTAL BEBAN</td><td class="text-end fw-bold">${formatCurrencyAccounting(summary.total_beban)}</td></tr></tbody></table>

            <table class="table mt-4">
                <tbody>
                    <tr class="${summary.laba_bersih >= 0 ? 'table-success' : 'table-danger'}">
                        <td class="fw-bold fs-5">LABA (RUGI) BERSIH</td>
                        <td class="text-end fw-bold fs-5">${formatCurrencyAccounting(summary.laba_bersih)}</td>
                    </tr>
                </tbody>
            </table>
        `;
        labaRugiContent.innerHTML = labaRugiHtml;
    }

    async function loadLabaRugi() {
        const startDate = labaRugiTglMulai.value;
        const endDate = labaRugiTglAkhir.value;
        labaRugiContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        try {
            const response = await fetch(`${basePath}/api/laporan/laba-rugi?start=${startDate}&end=${endDate}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            renderLabaRugi(result.data);
        } catch (error) {
            labaRugiContent.innerHTML = `<div class="alert alert-danger">Gagal memuat laporan: ${error.message}</div>`;
        }
    }

    function renderArusKas(data) {
        arusKasContent.innerHTML = '';
        const { arus_kas_operasi, arus_kas_investasi, arus_kas_pendanaan, kenaikan_penurunan_kas, saldo_kas_awal, saldo_kas_akhir_terhitung } = data;

        const renderSection = (title, amount) => `
            <tr>
                <td>${title}</td>
                <td class="text-end">${formatCurrencyAccounting(amount)}</td>
            </tr>
        `;
        
        const createTooltipContent = (details) => {
            // 'details' adalah objek, bukan array. Kita cek dengan Object.keys.
            if (!details || Object.keys(details).length === 0) return 'Tidak ada rincian.';
            let content = '<ul class="list-unstyled mb-0">';
            // Gunakan Object.entries untuk iterasi pada objek
            for (const [akun, jumlah] of Object.entries(details)) {
                content += `<li class="d-flex justify-content-between"><span>${akun}</span> <span class="fw-bold">${formatCurrencyAccounting(jumlah)}</span></li>`;
            }
            content += '</ul>';
            return content;
        };

        const arusKasHtml = `
            <table class="table table-sm">
                <tbody>
                    <tr class="table-light"><td colspan="2" class="fw-bold">Arus Kas dari Aktivitas Operasi
                        <i class="bi bi-info-circle-fill ms-2 text-primary" data-bs-toggle="tooltip" data-bs-html="true" data-details='${JSON.stringify(arus_kas_operasi.details)}'></i>
                    </td></tr>
                    ${renderSection('Total Arus Kas Operasi', arus_kas_operasi.total)}
                    
                    <tr class="table-light"><td colspan="2" class="fw-bold mt-3">Arus Kas dari Aktivitas Investasi
                        <i class="bi bi-info-circle-fill ms-2 text-primary" data-bs-toggle="tooltip" data-bs-html="true" data-details='${JSON.stringify(arus_kas_investasi.details)}'></i>
                    </td></tr>
                    ${renderSection('Total Arus Kas Investasi', arus_kas_investasi.total)}

                    <tr class="table-light"><td colspan="2" class="fw-bold mt-3">Arus Kas dari Aktivitas Pendanaan
                        <i class="bi bi-info-circle-fill ms-2 text-primary" data-bs-toggle="tooltip" data-bs-html="true" data-details='${JSON.stringify(arus_kas_pendanaan.details)}'></i>
                    </td></tr>
                    ${renderSection('Total Arus Kas Pendanaan', arus_kas_pendanaan.total)}
                </tbody>
                <tfoot class="table-group-divider">
                    <tr class="fw-bold">
                        <td>Kenaikan (Penurunan) Bersih Kas</td>
                        <td class="text-end">${formatCurrencyAccounting(kenaikan_penurunan_kas)}</td>
                    </tr>
                    <tr>
                        <td>Saldo Kas pada Awal Periode</td>
                        <td class="text-end">${formatCurrencyAccounting(saldo_kas_awal)}</td>
                    </tr>
                    <tr class="fw-bold table-success">
                        <td>Saldo Kas pada Akhir Periode</td>
                        <td class="text-end">${formatCurrencyAccounting(saldo_kas_akhir_terhitung)}</td>
                    </tr>
                </tfoot>
            </table>
        `;
        arusKasContent.innerHTML = arusKasHtml;

        // Initialize tooltips
        const tooltipTriggerList = arusKasContent.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            const tooltip = new bootstrap.Tooltip(tooltipTriggerEl, {
                title: 'Memuat rincian...' // Placeholder title
            });
            tooltipTriggerEl.addEventListener('show.bs.tooltip', function () {
                const details = JSON.parse(this.dataset.details || '{}');
                tooltip.setContent({ '.tooltip-inner': createTooltipContent(details) });
            });
        });
    }

    async function loadArusKas() {
        const startDate = arusKasTglMulai.value;
        const endDate = arusKasTglAkhir.value;
        arusKasContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        try {
            const response = await fetch(`${basePath}/api/laporan/arus-kas?start=${startDate}&end=${endDate}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            renderArusKas(result.data);
        } catch (error) {
            arusKasContent.innerHTML = `<div class="alert alert-danger">Gagal memuat laporan: ${error.message}</div>`;
        }
    }

    // Fungsi untuk memanggil load dan save
    const handleNeracaChange = () => { saveFilters(); loadNeraca(); };
    const handleLabaRugiChange = () => { saveFilters(); loadLabaRugi(); };
    const handleArusKasChange = () => { saveFilters(); loadArusKas(); };

    neracaTanggalInput.addEventListener('change', handleNeracaChange);

    // Event listener untuk tab Laba Rugi
    if (labaRugiTab) {
        labaRugiTab.addEventListener('shown.bs.tab', () => {
            // Hanya load data jika tab aktif, jangan save filter di sini
            if (document.getElementById('laba-rugi-pane').classList.contains('active')) {
                loadLabaRugi();
            }
        });
    }
    labaRugiTglMulai.addEventListener('change', handleLabaRugiChange);
    labaRugiTglAkhir.addEventListener('change', handleLabaRugiChange);

    // Event listener untuk tab Arus Kas
    if (arusKasTab) {
        arusKasTab.addEventListener('shown.bs.tab', loadArusKas);
    }
    arusKasTglMulai.addEventListener('change', handleArusKasChange);
    arusKasTglAkhir.addEventListener('change', handleArusKasChange);

    // --- Event Listeners untuk Export ---

    // Fungsi helper untuk cetak via browser
    const printReport = (elementToPrint, titleText) => {
        // Cari elemen .card terdekat yang membungkus konten laporan
        const reportCard = elementToPrint.closest('.card');
        if (!reportCard) {
            showToast('Area laporan tidak ditemukan untuk dicetak.', 'error');
            return;
        }
 
        // 1. Buat header laporan dan sisipkan di bagian atas card
        const printHeader = document.createElement('div');
        printHeader.className = 'print-only-header';
        printHeader.innerHTML = `<h3>${titleText}</h3>`;
        reportCard.prepend(printHeader);
 
        // 2. Tambahkan class 'is-printing' ke body dan 'print-area' ke card
        document.body.classList.add('is-printing');
        reportCard.classList.add('print-area');
 
        // 3. Panggil dialog cetak browser
        window.print();
 
        // 4. Setelah cetak (atau dibatalkan), bersihkan semuanya
        // Event 'afterprint' memastikan ini berjalan setelah dialog cetak ditutup
        const cleanup = () => {
            document.body.classList.remove('is-printing');
            reportCard.classList.remove('print-area');
            printHeader.remove();
            window.removeEventListener('afterprint', cleanup);
        };
        window.addEventListener('afterprint', cleanup);
    };

    // Event listener untuk tombol PDF (sekarang menggunakan print browser)
    exportNeracaPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const tanggal = new Date(neracaTanggalInput.value).toLocaleDateString('id-ID', { dateStyle: 'long' });
        printReport(neracaContent, `Laporan Posisi Keuangan (Neraca) per ${tanggal}`);
    });

    exportLrPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const periode = `Periode ${new Date(labaRugiTglMulai.value).toLocaleDateString('id-ID')} s/d ${new Date(labaRugiTglAkhir.value).toLocaleDateString('id-ID')}`;
        printReport(labaRugiContent, `Laporan Laba Rugi ${periode}`);
    });

    exportAkPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const periode = `Periode ${new Date(arusKasTglMulai.value).toLocaleDateString('id-ID')} s/d ${new Date(arusKasTglAkhir.value).toLocaleDateString('id-ID')}`;
        printReport(arusKasContent, `Laporan Arus Kas ${periode}`);
    });

    // Event listener untuk tombol CSV (tetap sama)
    exportNeracaCsvBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            window.open(`${basePath}/api/laporan/cetak?report=neraca&format=csv&tanggal=${neracaTanggalInput.value}`, '_blank');
    });
    exportLrCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
            window.open(`${basePath}/api/laporan/cetak?report=laba-rugi&format=csv&start=${labaRugiTglMulai.value}&end=${labaRugiTglAkhir.value}`, '_blank');
    });
    exportAkCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
            window.open(`${basePath}/api/laporan/cetak?report=arus-kas&format=csv&start=${arusKasTglMulai.value}&end=${arusKasTglAkhir.value}`, '_blank');
    });

    // Initial Load
    loadAndSetFilters();
    loadNeraca();
}

function initLaporanHarianPage() {
    const tanggalInput = document.getElementById('lh-tanggal');
    const tampilkanBtn = document.getElementById('lh-tampilkan-btn');
    const reportContent = document.getElementById('lh-report-content');
    const reportHeader = document.getElementById('lh-report-header');
    const exportPdfBtn = document.getElementById('export-lh-pdf');
    const exportCsvBtn = document.getElementById('export-lh-csv');
    const summaryContent = document.getElementById('lh-summary-content');
    const chartCanvas = document.getElementById('lh-chart');

    if (!tanggalInput) return;

    tanggalInput.valueAsDate = new Date(); // Set default to today

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    let dailyChart = null; // Variable to hold chart instance
    async function loadReport() {
        const tanggal = tanggalInput.value;
        if (!tanggal) {
            showToast('Harap pilih tanggal terlebih dahulu.', 'error');
            return;
        }

        const originalBtnHtml = tampilkanBtn.innerHTML;
        tampilkanBtn.disabled = true;
        tampilkanBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Memuat...`;
        reportContent.innerHTML = `<div class="text-center p-5"><div class="spinner-border"></div></div>`;
        summaryContent.innerHTML = `<div class="text-center p-5"><div class="spinner-border"></div></div>`;
        reportHeader.textContent = `Detail Transaksi Harian untuk ${new Date(tanggal).toLocaleDateString('id-ID', { dateStyle: 'full' })}`;

        try {
            const response = await fetch(`${basePath}/api/laporan-harian?tanggal=${tanggal}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const { saldo_awal, transaksi, total_pemasukan, total_pengeluaran, saldo_akhir } = result.data;

            // Render Summary Card
            summaryContent.innerHTML = `
                <dl class="row">
                    <dt class="col-sm-5">Saldo Awal Hari</dt>
                    <dd class="col-sm-7 text-end">${currencyFormatter.format(saldo_awal)}</dd>

                    <dt class="col-sm-5 text-success">Total Pemasukan</dt>
                    <dd class="col-sm-7 text-end text-success">${currencyFormatter.format(total_pemasukan)}</dd>

                    <dt class="col-sm-5 text-danger">Total Pengeluaran</dt>
                    <dd class="col-sm-7 text-end text-danger">${currencyFormatter.format(total_pengeluaran)}</dd>

                    <hr class="my-2">

                    <dt class="col-sm-5 fw-bold">Saldo Akhir Hari</dt>
                    <dd class="col-sm-7 text-end fw-bold">${currencyFormatter.format(saldo_akhir)}</dd>
                </dl>
            `;

            // Render Chart
            if (dailyChart) {
                dailyChart.destroy();
            }
            dailyChart = new Chart(chartCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Pemasukan', 'Pengeluaran'],
                    datasets: [{
                        label: 'Jumlah',
                        data: [total_pemasukan, total_pengeluaran],
                        backgroundColor: [
                            'rgba(25, 135, 84, 0.7)', // Success
                            'rgba(220, 53, 69, 0.7)'  // Danger
                        ],
                        borderColor: [
                            'rgba(25, 135, 84, 1)',
                            'rgba(220, 53, 69, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });

            let tableHtml = `
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Keterangan</th>
                            <th>Akun Terkait</th>
                            <th class="text-end">Pemasukan</th>
                            <th class="text-end">Pengeluaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" class="fw-bold">Saldo Awal</td>
                            <td class="text-end fw-bold" colspan="2">${currencyFormatter.format(saldo_awal)}</td>
                        </tr>
            `;

            if (transaksi.length > 0) {
                transaksi.forEach(tx => {
                    let pemasukan = 0, pengeluaran = 0;
                    let akunTerkait = '';
                    let idHtml = '';

                    if (tx.source === 'transaksi') {
                        const idDisplay = tx.nomor_referensi || `TRX-${tx.id}`;
                        idHtml = `<a href="#" class="view-detail-btn" data-type="transaksi" data-id="${tx.id}">${idDisplay}</a>`;
                        if (tx.jenis === 'pemasukan') { pemasukan = tx.jumlah; akunTerkait = tx.akun_utama; }
                        if (tx.jenis === 'pengeluaran') { pengeluaran = tx.jumlah; akunTerkait = tx.akun_utama; }
                        if (tx.jenis === 'transfer') { akunTerkait = `Dari: ${tx.akun_kas}<br>Ke: ${tx.akun_tujuan}`; }
                    } else { // Jurnal
                        const idDisplay = `JRN-${String(tx.id).padStart(5, '0')}`;
                        idHtml = `<a href="#" class="view-detail-btn" data-type="jurnal" data-id="${tx.id}">${idDisplay}</a>`;
                        akunTerkait = '<i>Jurnal Umum</i>';
                        // Untuk jurnal, kita hanya tampilkan totalnya di summary, bukan per baris
                    }

                    tableHtml += `
                        <tr>
                            <td><small>${idHtml}</small></td>
                            <td>${tx.keterangan}</td>
                            <td><small>${akunTerkait}</small></td>
                            <td class="text-end text-success">${pemasukan > 0 ? currencyFormatter.format(pemasukan) : '-'}</td>
                            <td class="text-end text-danger">${pengeluaran > 0 ? currencyFormatter.format(pengeluaran) : '-'}</td>
                        </tr>
                    `;
                });
            } else {
                tableHtml += `<tr><td colspan="5" class="text-center text-muted">Tidak ada transaksi pada tanggal ini.</td></tr>`;
            }

            tableHtml += `
                    </tbody>
                    <tfoot class="table-group-divider">
                        <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end text-success">${currencyFormatter.format(total_pemasukan)}</td><td class="text-end text-danger">${currencyFormatter.format(total_pengeluaran)}</td></tr>
                        <tr class="fw-bold table-primary"><td colspan="3" class="text-end">Saldo Akhir</td><td class="text-end" colspan="2">${currencyFormatter.format(saldo_akhir)}</td></tr>
                    </tfoot>
                </table>`;
            reportContent.innerHTML = tableHtml;

        } catch (error) {
            reportContent.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            summaryContent.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        } finally {
            tampilkanBtn.disabled = false;
            tampilkanBtn.innerHTML = originalBtnHtml;
        }
    }

    tampilkanBtn.addEventListener('click', loadReport);

    reportContent.addEventListener('click', async (e) => {
        const viewBtn = e.target.closest('.view-detail-btn');
        if (!viewBtn) return;

        e.preventDefault();
        const { type, id } = viewBtn.dataset;

        const detailModalEl = document.getElementById('detailModal');
        const detailModal = new bootstrap.Modal(detailModalEl);
        const modalBody = document.getElementById('detailModalBody');
        const modalLabel = document.getElementById('detailModalLabel');

        modalLabel.textContent = `Detail ${type === 'transaksi' ? 'Transaksi' : 'Jurnal'}`;
        modalBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        detailModal.show();

        try {
            const endpoint = type === 'transaksi' 
                ? `${basePath}/api/transaksi?action=get_journal_entry&id=${id}`
                : `${basePath}/api/entri-jurnal?action=get_single&id=${id}`;

            const response = await fetch(endpoint);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const header = type === 'transaksi' ? result.data.transaksi : result.data.header;
            const details = type === 'transaksi' ? result.data.jurnal : result.data.details;

            let tableHtml = `
                <p><strong>Tanggal:</strong> ${new Date(header.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'})}</p>
                ${header.nomor_referensi ? `<p><strong>No. Referensi:</strong> ${header.nomor_referensi}</p>` : ''}
                <p><strong>Keterangan:</strong> ${header.keterangan}</p>
                <table class="table table-sm table-bordered">
                    <thead class="table-light"><tr><th>Akun</th><th class="text-end">Debit</th><th class="text-end">Kredit</th></tr></thead>
                    <tbody>
            `;

            details.forEach(line => {
                const akunText = line.kode_akun ? `${line.kode_akun} - ${line.nama_akun}` : line.akun;
                tableHtml += `
                    <tr>
                        <td>${akunText}</td>
                        <td class="text-end">${line.debit > 0 ? currencyFormatter.format(line.debit) : '-'}</td>
                        <td class="text-end">${line.kredit > 0 ? currencyFormatter.format(line.kredit) : '-'}</td>
                    </tr>
                `;
            });
            tableHtml += `</tbody></table>`;
            modalBody.innerHTML = tableHtml;

        } catch (error) {
            modalBody.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        }
    });

    // Export buttons can be implemented similarly to other reports, pointing to a new handler or an updated one.

    // Initial load for today's report
    loadReport();
}

function initSaldoAwalNeracaPage() {
    const gridBody = document.getElementById('jurnal-grid-body');
    const saveBtn = document.getElementById('save-jurnal-btn');
    const form = document.getElementById('jurnal-form');

    if (!gridBody || !saveBtn || !form) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    function calculateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        gridBody.querySelectorAll('tr').forEach(row => {
            totalDebit += parseFloat(row.querySelector('.debit-input').value) || 0;
            totalCredit += parseFloat(row.querySelector('.credit-input').value) || 0;
        });

        document.getElementById('total-debit').textContent = currencyFormatter.format(totalDebit);
        document.getElementById('total-kredit').textContent = currencyFormatter.format(totalCredit);

        const totalDebitEl = document.getElementById('total-debit');
        const totalKreditEl = document.getElementById('total-kredit');

        if (Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0) {
            totalDebitEl.classList.add('text-success');
            totalKreditEl.classList.add('text-success');
            totalDebitEl.classList.remove('text-danger');
            totalKreditEl.classList.remove('text-danger');
        } else {
            totalDebitEl.classList.remove('text-success');
            totalKreditEl.classList.remove('text-success');
            if (totalDebit !== totalCredit) {
                totalDebitEl.classList.add('text-danger');
                totalKreditEl.classList.add('text-danger');
            } else {
                totalDebitEl.classList.remove('text-danger');
                totalKreditEl.classList.remove('text-danger');
            }
        }
    }

    async function renderGrid() {
        gridBody.innerHTML = '<tr><td colspan="4" class="text-center p-5"><div class="spinner-border"></div></td></tr>';
        try {
            const response = await fetch(`${basePath}/api/saldo-awal-neraca`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            
            gridBody.innerHTML = '';
            result.data.forEach((acc, index) => {
                const saldo = parseFloat(acc.saldo_awal);
                const debitValue = acc.saldo_normal === 'Debit' && saldo > 0 ? saldo : 0;
                const creditValue = acc.saldo_normal === 'Kredit' && saldo > 0 ? saldo : 0;

                const row = `
                    <tr>
                        <td>
                            <input type="hidden" name="entries[${index}][account_id]" value="${acc.id}">
                            ${acc.kode_akun}
                        </td>
                        <td>${acc.nama_akun}</td>
                        <td><input type="number" class="form-control form-control-sm text-end debit-input" name="entries[${index}][debit]" value="${debitValue}" step="any"></td>
                        <td><input type="number" class="form-control form-control-sm text-end credit-input" name="entries[${index}][credit]" value="${creditValue}" step="any"></td>
                    </tr>
                `;
                gridBody.insertAdjacentHTML('beforeend', row);
            });
            calculateTotals();
        } catch (error) {
            gridBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    gridBody.addEventListener('input', (e) => {
        if (e.target.matches('.debit-input, .credit-input')) {
            calculateTotals();
        }
    });

    saveBtn.addEventListener('click', async () => {
        const formData = new FormData(form);
        const originalBtnHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

        try {
            const response = await fetch(`${basePath}/api/saldo-awal-neraca`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                renderGrid(); // Reload grid to confirm changes
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    });

    renderGrid();
}

function initSaldoAwalLRPage() {
    const gridBody = document.getElementById('saldo-lr-grid-body');
    const saveBtn = document.getElementById('save-saldo-lr-btn');
    const form = document.getElementById('saldo-lr-form');

    if (!gridBody || !saveBtn || !form) return;

    async function renderGrid() {
        gridBody.innerHTML = '<tr><td colspan="4" class="text-center p-5"><div class="spinner-border"></div></td></tr>';
        try {
            const response = await fetch(`${basePath}/api/saldo-awal-lr`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            
            gridBody.innerHTML = '';
            result.data.forEach((acc, index) => {
                const saldo = parseFloat(acc.saldo_awal) || 0;

                const row = `
                    <tr>
                        <td>
                            <input type="hidden" name="entries[${index}][account_id]" value="${acc.id}">
                            ${acc.kode_akun}
                        </td>
                        <td>${acc.nama_akun}</td>
                        <td><span class="badge bg-${acc.tipe_akun === 'Pendapatan' ? 'success' : 'danger'}">${acc.tipe_akun}</span></td>
                        <td><input type="number" class="form-control form-control-sm text-end" name="entries[${index}][saldo]" value="${saldo}" step="any"></td>
                    </tr>
                `;
                gridBody.insertAdjacentHTML('beforeend', row);
            });
        } catch (error) {
            gridBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    saveBtn.addEventListener('click', async () => {
        const formData = new FormData(form);
        const originalBtnHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

        try {
            const response = await fetch(`${basePath}/api/saldo-awal-lr`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                renderGrid(); // Reload grid to confirm changes
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    });

    renderGrid();
}

function initBukuBesarPage() {
    const akunFilter = document.getElementById('bb-akun-filter');
    const tglMulai = document.getElementById('bb-tanggal-mulai');
    const tglAkhir = document.getElementById('bb-tanggal-akhir');
    const tampilkanBtn = document.getElementById('bb-tampilkan-btn');
    const reportContent = document.getElementById('bb-report-content');
    const reportHeader = document.getElementById('bb-report-header');

    if (!akunFilter) return;

    // Set default dates to today
    const today = new Date().toISOString().split('T')[0];
    tglMulai.value = today;
    tglAkhir.value = today;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 });

    async function loadAccounts() {
        try {
            const response = await fetch(`${basePath}/api/buku-besar?action=get_accounts`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            akunFilter.innerHTML = '<option value="">-- Pilih Akun --</option>';
            result.data.forEach(acc => {
                akunFilter.add(new Option(`${acc.kode_akun} - ${acc.nama_akun}`, acc.id));
            });
        } catch (error) {
            akunFilter.innerHTML = `<option value="">Gagal memuat akun</option>`;
            showToast(error.message, 'error');
        }
    }

    async function loadReport() {
        const accountId = akunFilter.value;
        const startDate = tglMulai.value;
        const endDate = tglAkhir.value;

        if (!accountId || !startDate || !endDate) {
            showToast('Harap pilih akun dan rentang tanggal.', 'error');
            return;
        }

        const originalBtnHtml = tampilkanBtn.innerHTML;
        tampilkanBtn.disabled = true;
        tampilkanBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Memuat...`;
        reportContent.innerHTML = `<div class="text-center p-5"><div class="spinner-border"></div></div>`;

        try {
            const params = new URLSearchParams({ account_id: accountId, start_date: startDate, end_date: endDate });
            const response = await fetch(`${basePath}/api/buku-besar?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const { account_info, saldo_awal, transactions } = result.data;
            reportHeader.textContent = `Buku Besar: ${account_info.nama_akun}`;

            let tableHtml = `
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr><th>Tanggal</th><th>Keterangan</th><th class="text-end">Debit</th><th class="text-end">Kredit</th><th class="text-end">Saldo</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4"><strong>Saldo Awal</strong></td>
                            <td class="text-end"><strong>${currencyFormatter.format(saldo_awal)}</strong></td>
                        </tr>
            `;

            let saldoBerjalan = parseFloat(saldo_awal);
            transactions.forEach(tx => {
                const debit = parseFloat(tx.debit);
                const kredit = parseFloat(tx.kredit);
                saldoBerjalan += debit - kredit;
                tableHtml += `
                    <tr>
                        <td>${new Date(tx.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'})}</td>
                        <td>${tx.keterangan}</td>
                        <td class="text-end">${debit > 0 ? currencyFormatter.format(debit) : '-'}</td>
                        <td class="text-end">${kredit > 0 ? currencyFormatter.format(kredit) : '-'}</td>
                        <td class="text-end">${currencyFormatter.format(saldoBerjalan)}</td>
                    </tr>
                `;
            });

            tableHtml += `</tbody><tfoot><tr class="table-light"><td colspan="4" class="text-end fw-bold">Saldo Akhir</td><td class="text-end fw-bold">${currencyFormatter.format(saldoBerjalan)}</td></tr></tfoot></table>`;
            reportContent.innerHTML = tableHtml;

        } catch (error) {
            reportContent.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        } finally {
            tampilkanBtn.disabled = false;
            tampilkanBtn.innerHTML = originalBtnHtml;
        }
    }

    tampilkanBtn.addEventListener('click', loadReport);
    loadAccounts();
}

function initEntriJurnalPage() {
    const form = document.getElementById('entri-jurnal-form');
    const linesBody = document.getElementById('jurnal-lines-body');
    const addLineBtn = document.getElementById('add-jurnal-line-btn');


    if (!form) return;

    let allAccounts = [];
    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    async function fetchAccounts() {
        try {
            const response = await fetch(`${basePath}/api/coa`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            allAccounts = result.data;
        } catch (error) {
            showToast(`Gagal memuat akun: ${error.message}`, 'error');
        }
    }

    function createAccountSelect(selectedValue = '') {
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.innerHTML = '<option value="">-- Pilih Akun --</option>';
        allAccounts.forEach(acc => {
            const option = new Option(`${acc.kode_akun} - ${acc.nama_akun}`, acc.id);
            if (acc.id == selectedValue) option.selected = true;
            select.add(option);
        });
        return select;
    }

    function addJurnalLine() {
        const index = linesBody.children.length;
        const tr = document.createElement('tr');
        const select = createAccountSelect(); // No selected value for new line
        select.name = `lines[${index}][account_id]`;

        tr.innerHTML = `
            <td></td>
            <td><input type="number" name="lines[${index}][debit]" class="form-control form-control-sm text-end debit-input" value="0" step="any"></td>
            <td><input type="number" name="lines[${index}][kredit]" class="form-control form-control-sm text-end kredit-input" value="0" step="any"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-line-btn"><i class="bi bi-trash-fill"></i></button></td>
        `;
        tr.querySelector('td').appendChild(select);
        linesBody.appendChild(tr);
    }

    function calculateTotals() {
        let totalDebit = 0;
        let totalKredit = 0;
        linesBody.querySelectorAll('tr').forEach(row => {
            totalDebit += parseFloat(row.querySelector('.debit-input').value) || 0;
            totalKredit += parseFloat(row.querySelector('.kredit-input').value) || 0;
        });

        const totalDebitEl = document.getElementById('total-jurnal-debit');
        const totalKreditEl = document.getElementById('total-jurnal-kredit');
        totalDebitEl.textContent = currencyFormatter.format(totalDebit);
        totalKreditEl.textContent = currencyFormatter.format(totalKredit);

        if (Math.abs(totalDebit - totalKredit) < 0.01 && totalDebit > 0) {
            totalDebitEl.classList.add('text-success');
            totalKreditEl.classList.add('text-success');
        } else {
            totalDebitEl.classList.remove('text-success');
            totalKreditEl.classList.remove('text-success');
        }
    }

    addLineBtn.addEventListener('click', addJurnalLine);

    linesBody.addEventListener('click', e => {
        if (e.target.closest('.remove-line-btn')) {
            e.target.closest('tr').remove();
            calculateTotals();
        }
    });

    linesBody.addEventListener('input', e => {
        if (e.target.matches('.debit-input, .kredit-input')) {
            calculateTotals();
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const action = document.getElementById('jurnal-action').value || 'add';
        const saveBtn = document.getElementById('save-jurnal-entry-btn');
        const formData = new FormData(form); // The action is now correctly set from the hidden input
        const originalBtnHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;
        
        try {
            const response = await fetch(`${basePath}/api/entri-jurnal`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                form.reset();
                linesBody.innerHTML = '';
                addJurnalLine(); addJurnalLine(); // Start with two new lines
                calculateTotals();
                if (action === 'update') {
                    navigate(`${basePath}/daftar-jurnal`); // Redirect to list after update
                }
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    });

    async function loadJournalForEdit(id) {
        try {
            const response = await fetch(`${basePath}/api/entri-jurnal?action=get_single&id=${id}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const { header, details } = result.data;
            document.querySelector('.h2').innerHTML = `<i class="bi bi-pencil-square"></i> Edit Entri Jurnal (ID: JRN-${String(id).padStart(5, '0')})`;
            document.getElementById('jurnal-id').value = header.id;
            document.getElementById('jurnal-action').value = 'update';
            document.getElementById('jurnal-tanggal').value = header.tanggal;
            document.getElementById('jurnal-keterangan').value = header.keterangan;

            linesBody.innerHTML = '';
            details.forEach((line, index) => {
                const tr = document.createElement('tr');
                const select = createAccountSelect(line.account_id);
                select.name = `lines[${index}][account_id]`;

                tr.innerHTML = `
                    <td></td>
                    <td><input type="number" name="lines[${index}][debit]" class="form-control form-control-sm text-end debit-input" value="${line.debit}" step="any"></td>
                    <td><input type="number" name="lines[${index}][kredit]" class="form-control form-control-sm text-end kredit-input" value="${line.kredit}" step="any"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-line-btn"><i class="bi bi-trash-fill"></i></button></td>
                `;
                tr.querySelector('td').appendChild(select);
                linesBody.appendChild(tr);
            });
            calculateTotals();
        } catch (error) {
            showToast(`Gagal memuat data jurnal untuk diedit: ${error.message}`, 'error');
            linesBody.innerHTML = `<tr><td colspan="4" class="alert alert-danger">${error.message}</td></tr>`;
        }
    }

    // Initial setup
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit_id');

    fetchAccounts().then(() => {
        if (editId) {
            loadJournalForEdit(editId);
        } else {
            document.getElementById('jurnal-tanggal').valueAsDate = new Date();
            addJurnalLine(); addJurnalLine();
        }
    });
}

function initDaftarJurnalPage() {
    const tableBody = document.getElementById('daftar-jurnal-table-body');
    const searchInput = document.getElementById('search-jurnal');
    const startDateFilter = document.getElementById('filter-jurnal-mulai');
    const endDateFilter = document.getElementById('filter-jurnal-akhir');
    const limitSelect = document.getElementById('filter-jurnal-limit');
    const paginationContainer = document.getElementById('daftar-jurnal-pagination');
    const viewModalEl = document.getElementById('viewJurnalModal');

    if (!tableBody) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    async function loadJurnal(page = 1) {
        const params = new URLSearchParams({
            page,
            limit: limitSelect.value,
            search: searchInput.value,
            start_date: startDateFilter.value,
            end_date: endDateFilter.value,
        });

        tableBody.innerHTML = `<tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;
        try {
            const response = await fetch(`${basePath}/api/entri-jurnal?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(je => {
                    const row = `
                        <tr>
                            <td>JRN-${String(je.id).padStart(5, '0')}</td>
                            <td>${new Date(je.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'})}</td>
                            <td>${je.keterangan}</td>
                            <td class="text-end">${currencyFormatter.format(je.total)}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-info view-jurnal-btn" data-id="${je.id}" title="Lihat Detail"><i class="bi bi-eye-fill"></i></button>
                                <a href="${basePath}/entri-jurnal?edit_id=${je.id}" class="btn btn-sm btn-warning edit-jurnal-btn" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                <button class="btn btn-sm btn-danger delete-jurnal-btn" data-id="${je.id}" data-keterangan="${je.keterangan}" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Tidak ada entri jurnal ditemukan.</td></tr>';
            }
            renderPagination(paginationContainer, result.pagination, loadJurnal);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    tableBody.addEventListener('click', async (e) => {
        const viewBtn = e.target.closest('.view-jurnal-btn');
        if (viewBtn) {
            const id = viewBtn.dataset.id;
            const viewModal = new bootstrap.Modal(viewModalEl);
            const modalBody = document.getElementById('view-jurnal-body');
            modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border"></div></div>';
            viewModal.show();

            try {
                const response = await fetch(`${basePath}/api/entri-jurnal?action=get_single&id=${id}`);
                const result = await response.json();
                if (result.status !== 'success') throw new Error(result.message);

                const { header, details } = result.data;
                let tableHtml = `
                    <p><strong>Tanggal:</strong> ${new Date(header.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'})}</p>
                    <p><strong>Keterangan:</strong> ${header.keterangan}</p>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light"><tr><th>Akun</th><th class="text-end">Debit</th><th class="text-end">Kredit</th></tr></thead>
                        <tbody>
                `;
                details.forEach(line => {
                    tableHtml += `<tr><td>${line.kode_akun} - ${line.nama_akun}</td><td class="text-end">${line.debit > 0 ? currencyFormatter.format(line.debit) : '-'}</td><td class="text-end">${line.kredit > 0 ? currencyFormatter.format(line.kredit) : '-'}</td></tr>`;
                });
                tableHtml += `</tbody></table>`;
                modalBody.innerHTML = tableHtml;
            } catch (error) {
                modalBody.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            }
        }

        const deleteBtn = e.target.closest('.delete-jurnal-btn');
        if (deleteBtn) {
            const { id, keterangan } = deleteBtn.dataset;
            if (confirm(`Yakin ingin menghapus entri jurnal "${keterangan}" (ID: JRN-${String(id).padStart(5, '0')})? Aksi ini tidak dapat dibatalkan.`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                const response = await fetch(`${basePath}/api/entri-jurnal`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') loadJurnal(1);
            }
        }
    });

    let debounceTimer;
    const combinedFilterHandler = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadJurnal(1), 300);
        localStorage.setItem('jurnal_limit', limitSelect.value); // Save limit on change
    };
    [searchInput, startDateFilter, endDateFilter, limitSelect].forEach(el => el.addEventListener('change', combinedFilterHandler));
    searchInput.addEventListener('input', combinedFilterHandler);

    // Load saved limit from localStorage before the initial load
    const savedJurnalLimit = localStorage.getItem('jurnal_limit');
    if (savedJurnalLimit) {
        limitSelect.value = savedJurnalLimit;
    }

    // Set default dates to the current month on initial load
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
    startDateFilter.value = firstDay;
    endDateFilter.value = lastDay;
    // Initial load
    loadJurnal();
}

function initSettingsPage() {
    const generalSettingsContainer = document.getElementById('settings-container');
    const saveGeneralSettingsBtn = document.getElementById('save-settings-btn');
    const generalSettingsForm = document.getElementById('settings-form');
    const trxSettingsContainer = document.getElementById('transaksi-settings-container');
    const saveTrxSettingsBtn = document.getElementById('save-transaksi-settings-btn');
    const trxSettingsForm = document.getElementById('transaksi-settings-form');
    const cfMappingContainer = document.getElementById('arus-kas-mapping-container');
    const saveCfSettingsBtn = document.getElementById('save-arus-kas-settings-btn');
    const cfSettingsForm = document.getElementById('arus-kas-settings-form');
    if (!generalSettingsContainer) return;

    async function loadSettings() {
        try {
            const response = await fetch(`${basePath}/api/settings`);
            const result = await response.json();

            if (result.status === 'success') {
                const settings = result.data;
                generalSettingsContainer.innerHTML = `
                    <div class="mb-3">
                        <label for="app_name" class="form-label">Nama Aplikasi</label>
                        <input type="text" class="form-control" id="app_name" name="app_name" value="${settings.app_name || ''}">
                    </div>
                    <div class="mb-3">
                        <label for="notification_interval" class="form-label">Interval Refresh Notifikasi (ms)</label>
                        <input type="number" class="form-control" id="notification_interval" name="notification_interval" value="${settings.notification_interval || ''}">
                    </div>
                `;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            generalSettingsContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat pengaturan: ${error.message}</div>`;
        }
    }

    async function loadTransaksiSettings() {
        if (!trxSettingsContainer) return;
        try {
            const [settingsRes, cashAccRes] = await Promise.all([
                fetch(`${basePath}/api/settings`),
                fetch(`${basePath}/api/settings?action=get_cash_accounts`)
            ]);
            const settingsResult = await settingsRes.json();
            const cashAccResult = await cashAccRes.json();

            if (settingsResult.status !== 'success' || cashAccResult.status !== 'success') {
                throw new Error(settingsResult.message || cashAccResult.message);
            }

            const settings = settingsResult.data;
            const cashAccounts = cashAccResult.data;

            let cashOptions = cashAccounts.map(acc => `<option value="${acc.id}">${acc.nama_akun}</option>`).join('');

            trxSettingsContainer.innerHTML = `
                <h5 class="mb-3">Nomor Referensi Otomatis</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="ref_pemasukan_prefix" class="form-label">Prefix Pemasukan</label>
                        <input type="text" class="form-control" id="ref_pemasukan_prefix" name="ref_pemasukan_prefix" value="${settings.ref_pemasukan_prefix || 'INV'}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="ref_pengeluaran_prefix" class="form-label">Prefix Pengeluaran</label>
                        <input type="text" class="form-control" id="ref_pengeluaran_prefix" name="ref_pengeluaran_prefix" value="${settings.ref_pengeluaran_prefix || 'EXP'}">
                    </div>
                </div>
                <hr>
                <h5 class="mb-3">Akun Kas Default</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="default_cash_in" class="form-label">Akun Kas Default untuk Pemasukan</label>
                        <select class="form-select" id="default_cash_in" name="default_cash_in">${cashOptions}</select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="default_cash_out" class="form-label">Akun Kas Default untuk Pengeluaran</label>
                        <select class="form-select" id="default_cash_out" name="default_cash_out">${cashOptions}</select>
                    </div>
                </div>
            `;
            // Set selected values
            if (settings.default_cash_in) document.getElementById('default_cash_in').value = settings.default_cash_in;
            if (settings.default_cash_out) document.getElementById('default_cash_out').value = settings.default_cash_out;

        } catch (error) {
            trxSettingsContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat pengaturan transaksi: ${error.message}</div>`;
        }
    }

    async function loadArusKasSettings() {
        if (!cfMappingContainer) return;
        try {
            const response = await fetch(`${basePath}/api/settings?action=get_cf_accounts`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            let formHtml = '<div class="row">';
            result.data.forEach(acc => {
                formHtml += `
                    <div class="col-md-6 mb-3">
                        <label for="cf_mapping_${acc.id}" class="form-label small">${acc.kode_akun} - ${acc.nama_akun}</label>
                        <select class="form-select form-select-sm" id="cf_mapping_${acc.id}" name="cf_mapping[${acc.id}]">
                            <option value="">-- Tidak Diklasifikasikan (Operasi) --</option>
                            <option value="Operasi" ${acc.cash_flow_category === 'Operasi' ? 'selected' : ''}>Operasi</option>
                            <option value="Investasi" ${acc.cash_flow_category === 'Investasi' ? 'selected' : ''}>Investasi</option>
                            <option value="Pendanaan" ${acc.cash_flow_category === 'Pendanaan' ? 'selected' : ''}>Pendanaan</option>
                        </select>
                    </div>
                `;
            });
            formHtml += '</div>';
            cfMappingContainer.innerHTML = formHtml;

        } catch (error) {
            cfMappingContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat pemetaan akun: ${error.message}</div>`;
        }
    }

    saveGeneralSettingsBtn.addEventListener('click', async () => {
        const formData = new FormData(generalSettingsForm);
        const originalBtnHtml = saveGeneralSettingsBtn.innerHTML;
        saveGeneralSettingsBtn.disabled = true;
        saveGeneralSettingsBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...`;

        try {
            const minDelay = new Promise(resolve => setTimeout(resolve, 500));
            const fetchPromise = fetch(`${basePath}/api/settings`, { method: 'POST', body: formData });

            const [response] = await Promise.all([fetchPromise, minDelay]);

            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                loadSettings(); // Reload settings
                showToast('Beberapa perubahan mungkin memerlukan refresh halaman untuk diterapkan.', 'info', 'Informasi');
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveGeneralSettingsBtn.disabled = false;
            saveGeneralSettingsBtn.innerHTML = originalBtnHtml;
        }
    });

    if (saveTrxSettingsBtn) {
        saveTrxSettingsBtn.addEventListener('click', async () => {
            const formData = new FormData(trxSettingsForm);
            const originalBtnHtml = saveTrxSettingsBtn.innerHTML;
            saveTrxSettingsBtn.disabled = true;
            saveTrxSettingsBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

            try {
                const minDelay = new Promise(resolve => setTimeout(resolve, 500));
                const fetchPromise = fetch(`${basePath}/api/settings`, { method: 'POST', body: formData });
                const [response] = await Promise.all([fetchPromise, minDelay]);
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    loadTransaksiSettings();
                }
            } catch (error) {
                showToast('Terjadi kesalahan jaringan.', 'error');
            } finally {
                saveTrxSettingsBtn.disabled = false;
                saveTrxSettingsBtn.innerHTML = originalBtnHtml;
            }
        });
    }

    if (saveCfSettingsBtn) {
        saveCfSettingsBtn.addEventListener('click', async () => {
            const formData = new FormData(cfSettingsForm);
            const originalBtnHtml = saveCfSettingsBtn.innerHTML;
            saveCfSettingsBtn.disabled = true;
            saveCfSettingsBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

            try {
                const minDelay = new Promise(resolve => setTimeout(resolve, 500));
                const fetchPromise = fetch(`${basePath}/api/settings`, { method: 'POST', body: formData });
                const [response] = await Promise.all([fetchPromise, minDelay]);
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    loadArusKasSettings();
                }
            } catch (error) {
                showToast('Terjadi kesalahan jaringan.', 'error');
            } finally {
                saveCfSettingsBtn.disabled = false;
                saveCfSettingsBtn.innerHTML = originalBtnHtml;
            }
        });
    }

    loadSettings();
    loadTransaksiSettings();
    loadArusKasSettings();
}

function initMyProfilePage() {
    const form = document.getElementById('change-password-form');
    const saveBtn = document.getElementById('save-password-btn');

    if (!form || !saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const formData = new FormData(form);
        const originalBtnHtml = saveBtn.innerHTML;

        // Client-side validation
        if (formData.get('new_password') !== formData.get('confirm_password')) {
            showToast('Password baru dan konfirmasi tidak cocok.', 'error');
            return;
        }
        if (formData.get('new_password').length < 6) {
            showToast('Password baru minimal harus 6 karakter.', 'error');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';

        try {
            const minDelay = new Promise(resolve => setTimeout(resolve, 500));
            const fetchPromise = fetch(`${basePath}/api/my-profile/change-password`, {
                method: 'POST',
                body: formData
            });

            const [response] = await Promise.all([fetchPromise, minDelay]);

            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                form.reset();
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    });
}


function initAnggaranPage() {
    const yearFilter = document.getElementById('anggaran-tahun-filter');
    const reportTableBody = document.getElementById('anggaran-report-table-body');
    const modalEl = document.getElementById('anggaranModal');
    const modal = new bootstrap.Modal(modalEl);
    const modalTahunLabel = document.getElementById('modal-tahun-label');
    const managementContainer = document.getElementById('anggaran-management-container');
    const addAnggaranForm = document.getElementById('add-anggaran-form');

    if (!yearFilter || !reportTableBody) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    });

    // Populate year filter
    const currentYear = new Date().getFullYear();
    for (let i = 0; i < 5; i++) {
        const year = currentYear - i;
        yearFilter.add(new Option(year, year));
    }

    async function loadReport() {
        const selectedYear = yearFilter.value;
        reportTableBody.innerHTML = '<tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>';
        try {
            const response = await fetch(`${basePath}/api/anggaran?action=get_report&tahun=${selectedYear}`);
            const result = await response.json();
            reportTableBody.innerHTML = '';
            if (result.status === 'success' && result.data.length > 0) {
                result.data.forEach(item => {
                    const percentage = item.persentase;
                    let progressBarColor = 'bg-success';
                    if (percentage > 75) progressBarColor = 'bg-warning';
                    if (percentage > 95) progressBarColor = 'bg-danger';

                    const row = `
                        <tr>
                            <td>${item.kategori}</td>
                            <td class="text-end">${currencyFormatter.format(item.jumlah_anggaran)}</td>
                            <td class="text-end">${currencyFormatter.format(item.realisasi_belanja)}</td>
                            <td class="text-end fw-bold ${item.sisa_anggaran < 0 ? 'text-danger' : ''}">${currencyFormatter.format(item.sisa_anggaran)}</td>
                            <td>
                                <div class="progress" role="progressbar" style="height: 20px;">
                                    <div class="progress-bar ${progressBarColor}" style="width: ${Math.min(percentage, 100)}%">${percentage.toFixed(1)}%</div>
                                </div>
                            </td>
                        </tr>
                    `;
                    reportTableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                reportTableBody.innerHTML = '<tr><td colspan="5" class="text-center">Belum ada data anggaran untuk tahun ini.</td></tr>';
            }
        } catch (error) {
            reportTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Gagal memuat laporan.</td></tr>';
        }
    }

    async function loadBudgetManagement() {
        const selectedYear = yearFilter.value;
        modalTahunLabel.textContent = selectedYear;
        managementContainer.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        try {
            const response = await fetch(`${basePath}/api/anggaran?action=list_budget&tahun=${selectedYear}`);
            const result = await response.json();
            managementContainer.innerHTML = '';
            if (result.status === 'success' && result.data.length > 0) {
                result.data.forEach(item => {
                    const itemHtml = `
                        <div class="input-group mb-2">
                            <span class="input-group-text" style="width: 150px;">${item.kategori}</span>
                            <input type="number" class="form-control budget-amount-input" data-id="${item.id}" value="${item.jumlah_anggaran}">
                            <button class="btn btn-outline-danger delete-budget-btn" data-id="${item.id}" title="Hapus"><i class="bi bi-trash"></i></button>
                        </div>
                    `;
                    managementContainer.insertAdjacentHTML('beforeend', itemHtml);
                });
            } else {
                managementContainer.innerHTML = '<p class="text-muted text-center">Belum ada anggaran yang ditetapkan untuk tahun ini.</p>';
            }
        } catch (error) {
            managementContainer.innerHTML = '<div class="alert alert-danger">Gagal memuat data anggaran.</div>';
        }
    }

    async function loadExpenseCategoriesForSelect() {
        const kategoriSelect = document.getElementById('new-kategori');
        if (!kategoriSelect) return;
        kategoriSelect.innerHTML = '<option value="">Memuat...</option>';
        try {
            const response = await fetch(`${basePath}/api/kategori-kas`);
            const result = await response.json();
            kategoriSelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
            if (result.status === 'success' && result.data.keluar) {
                result.data.keluar.forEach(cat => kategoriSelect.add(new Option(cat.nama_kategori, cat.nama_kategori)));
            }
        } catch (error) {
            kategoriSelect.innerHTML = '<option value="">Gagal memuat</option>';
        }
    }

    yearFilter.addEventListener('change', loadReport);

    modalEl.addEventListener('show.bs.modal', loadBudgetManagement);
    modalEl.addEventListener('hidden.bs.modal', loadReport); // Refresh report after closing modal

    addAnggaranForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const kategori = document.getElementById('new-kategori').value.trim();
        const jumlah = document.getElementById('new-jumlah').value;
        const tahun = yearFilter.value;

        const formData = new FormData();
        formData.append('action', 'add_budget');
        formData.append('tahun', tahun);
        formData.append('kategori', kategori);
        formData.append('jumlah', jumlah);

        const response = await fetch(`${basePath}/api/anggaran`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') {
            addAnggaranForm.reset();
            document.getElementById('new-kategori').selectedIndex = 0; // Reset dropdown
            loadBudgetManagement();
        }
    });

    managementContainer.addEventListener('change', async (e) => {
        if (e.target.classList.contains('budget-amount-input')) {
            const id = e.target.dataset.id;
            const jumlah = e.target.value;

            const formData = new FormData();
            formData.append('action', 'save_budget');
            formData.append('id', id);
            formData.append('jumlah', jumlah);

            const response = await fetch(`${basePath}/api/anggaran`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
        }
    });

    managementContainer.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-budget-btn');
        if (deleteBtn) {
            if (confirm('Yakin ingin menghapus kategori anggaran ini?')) {
                const id = deleteBtn.dataset.id;
                const formData = new FormData();
                formData.append('action', 'delete_budget');
                formData.append('id', id);
                const response = await fetch(`${basePath}/api/anggaran`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') loadBudgetManagement();
            }
        }
    });

    loadReport();
    loadExpenseCategoriesForSelect(); // Panggil fungsi ini saat halaman anggaran diinisialisasi
}


/**
 * Calculates time since a given date.
 * @param {Date} date The date to compare against.
 * @returns {string} A human-readable string like "5 menit lalu".
 */
function timeSince(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    let interval = seconds / 31536000;
    if (interval > 1) return Math.floor(interval) + " tahun lalu";
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + " bulan lalu";
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + " hari lalu";
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + " jam lalu";
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + " menit lalu";
    return "Baru saja";
}

// =================================================================================
// GLOBAL INITIALIZATION
// =================================================================================

document.addEventListener('DOMContentLoaded', function () {
    // --- Sidebar Toggle Logic ---
    const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');

    const toggleSidebar = () => {
        document.body.classList.toggle('sidebar-collapsed');
        // Save the state to localStorage
        const isCollapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebar-collapsed', isCollapsed);
    };

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', toggleSidebar);
    }

    if (sidebarOverlay) {
        // Di layar kecil, klik pada overlay akan menutup sidebar
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }

    // --- Theme Switcher ---
    const themeSwitcher = document.getElementById('theme-switcher');
    if (themeSwitcher) {
        const themeIcon = themeSwitcher.querySelector('i');
        const themeText = document.getElementById('theme-switcher-text');

        // Function to set the switcher state
        const setSwitcherState = (theme) => {
            if (theme === 'dark') {
                themeIcon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
                themeText.textContent = 'Mode Terang';
            } else {
                themeIcon.classList.replace('bi-sun-fill', 'bi-moon-stars-fill');
                themeText.textContent = 'Mode Gelap';
            }
        };

        // Set initial state based on what's already applied to the body
        const currentTheme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
        setSwitcherState(currentTheme);

        themeSwitcher.addEventListener('click', (e) => {
            e.preventDefault();
            const newTheme = document.body.classList.toggle('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', newTheme);
            setSwitcherState(newTheme);
        });
    }

    // --- Panic Button Logic ---
    const panicButton = document.getElementById('panic-button');
    if (panicButton) {
        let holdTimeout;
        const originalButtonHtml = panicButton.innerHTML;

        const startHold = (e) => {
            e.preventDefault();
            // Prevent action if button is already processing
            if (panicButton.disabled) return;

            panicButton.classList.add('is-holding');

            holdTimeout = setTimeout(async () => {
                panicButton.disabled = true;
                panicButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Mengirim...`;

                try {
                    const response = await fetch(`${basePath}/api/panic`, { method: 'POST' });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Server error');
                    }

                    showToast(result.message, 'success');
                    panicButton.classList.remove('btn-danger');
                    panicButton.classList.add('btn-success');
                    panicButton.innerHTML = `<i class="bi bi-check-circle-fill"></i> Terkirim`;

                } catch (error) {
                    // Use error.message if available from the thrown error
                    showToast(error.message || 'Gagal mengirim sinyal darurat.', 'error');
                    panicButton.innerHTML = `<i class="bi bi-x-circle-fill"></i> Gagal`;
                } finally {
                    // Reset button to original state after a few seconds
                    setTimeout(() => {
                        panicButton.classList.remove('is-holding', 'btn-success');
                        panicButton.classList.add('btn-danger');
                        panicButton.innerHTML = originalButtonHtml;
                        panicButton.disabled = false;
                    }, 5000); // Reset after 5 seconds
                }
            }, 3000); // 3 seconds
        };

        const cancelHold = () => {
            if (panicButton.disabled) return;
            clearTimeout(holdTimeout);
            panicButton.classList.remove('is-holding');
        };

        panicButton.addEventListener('mousedown', startHold);
        panicButton.addEventListener('touchstart', startHold, { passive: false });
        panicButton.addEventListener('mouseup', cancelHold);
        panicButton.addEventListener('mouseleave', cancelHold);
        panicButton.addEventListener('touchend', cancelHold);
    }

    // --- Live Clock in Header ---
    const clockElement = document.getElementById('live-clock');
    if (clockElement) {
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        function updateLiveClock() {
            const now = new Date();
            const dayName = days[now.getDay()];
            const day = now.getDate().toString().padStart(2, '0');
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');

            clockElement.textContent = `${dayName}, ${day} ${monthName} ${year} ${hours}:${minutes}:${seconds}`;
        }

        updateLiveClock(); // Initial call
        setInterval(updateLiveClock, 1000); // Update every second
    }

    // --- SPA Navigation Listeners ---
    // Intercept clicks on internal links
    document.body.addEventListener('click', e => {
        const link = e.target.closest('a');
        // Check if it's an internal, navigable link that doesn't open a new tab, trigger a modal/dropdown, or has the 'data-spa-ignore' attribute
        if (link && link.href && link.target !== '_blank' && new URL(link.href).origin === window.location.origin && !link.getAttribute('data-bs-toggle') && link.getAttribute('data-spa-ignore') === null) {
            e.preventDefault();
            if (new URL(link.href).pathname !== window.location.pathname) {
                navigate(link.href);
            }
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', e => {
        if (e.state && e.state.path) {
            navigate(e.state.path, false); // false = don't push a new state
        }
    });

    // --- Initial Page Load ---
    updateActiveSidebarLink(window.location.pathname);
    runPageScripts(window.location.pathname);
});

/**
 * Initializes the global search functionality.
 */
function initGlobalSearch() {
    const searchModalEl = document.getElementById('globalSearchModal');
    if (!searchModalEl) return;

    const searchInput = document.getElementById('global-search-input');
    const resultsContainer = document.getElementById('global-search-results');
    const spinner = document.getElementById('global-search-spinner');
    const searchModal = new bootstrap.Modal(searchModalEl);

    let debounceTimer;

    const performSearch = async () => {
        const term = searchInput.value.trim();

        if (term.length < 3) {
            resultsContainer.innerHTML = '<p class="text-muted text-center">Masukkan minimal 3 karakter untuk mencari.</p>';
            spinner.style.display = 'none';
            return;
        }

        spinner.style.display = 'block';

        try {
            const response = await fetch(`${basePath}/api/global-search?term=${encodeURIComponent(term)}`);
            const result = await response.json();

            resultsContainer.innerHTML = '';
            if (result.status === 'success' && result.data.length > 0) {
                result.data.forEach(item => {
                    const resultItem = `
                        <a href="${basePath}${item.link}" class="search-result-item" data-bs-dismiss="modal">
                            <div class="d-flex align-items-center">
                                <i class="bi ${item.icon} fs-4 me-3 text-primary"></i>
                                <div>
                                    <div class="fw-bold">${item.title}</div>
                                    <small class="text-muted">${item.subtitle}</small>
                                </div>
                                <span class="badge bg-secondary ms-auto">${item.type}</span>
                            </div>
                        </a>
                    `;
                    resultsContainer.insertAdjacentHTML('beforeend', resultItem);
                });
            } else if (result.status === 'success') {
                resultsContainer.innerHTML = `<p class="text-muted text-center">Tidak ada hasil ditemukan untuk "<strong>${term}</strong>".</p>`;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            resultsContainer.innerHTML = `<p class="text-danger text-center">Terjadi kesalahan: ${error.message}</p>`;
        } finally {
            spinner.style.display = 'none';
        }
    };

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        spinner.style.display = 'block';
        debounceTimer = setTimeout(performSearch, 500); // Debounce for 500ms
    });

    searchModalEl.addEventListener('shown.bs.modal', () => {
        searchInput.focus();
    });

    searchModalEl.addEventListener('hidden.bs.modal', () => {
        searchInput.value = '';
        resultsContainer.innerHTML = '<p class="text-muted text-center">Masukkan kata kunci untuk memulai pencarian.</p>';
    });

    // Add keyboard shortcut (Ctrl+K or Cmd+K)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault(); // Prevent default browser action (e.g., search)
            searchModal.show();
        }
    });
}
/**
 * Renders pagination controls.
 * @param {HTMLElement} container The container element for the pagination.
 * @param {object|null} pagination The pagination object from the API.
 * @param {function} onPageClick The callback function to execute when a page link is clicked.
 */
function renderPagination(container, pagination, onPageClick) {
    if (!container) return;
    container.innerHTML = '';
    if (!pagination || pagination.total_pages <= 1) return;

    const { current_page, total_pages } = pagination;

    const createPageItem = (page, text, isDisabled = false, isActive = false) => {
        const li = document.createElement('li');
        li.className = `page-item ${isDisabled ? 'disabled' : ''} ${isActive ? 'active' : ''}`;
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.dataset.page = page;
        a.innerHTML = text;
        li.appendChild(a);
        return li;
    };

    container.appendChild(createPageItem(current_page - 1, 'Previous', current_page === 1));

    const maxPagesToShow = 5;
    let startPage, endPage;
    if (total_pages <= maxPagesToShow) {
        startPage = 1; endPage = total_pages;
    } else {
        const maxPagesBeforeCurrent = Math.floor(maxPagesToShow / 2);
        const maxPagesAfterCurrent = Math.ceil(maxPagesToShow / 2) - 1;
        if (current_page <= maxPagesBeforeCurrent) { startPage = 1; endPage = maxPagesToShow; } 
        else if (current_page + maxPagesAfterCurrent >= total_pages) { startPage = total_pages - maxPagesToShow + 1; endPage = total_pages; } 
        else { startPage = current_page - maxPagesBeforeCurrent; endPage = current_page + maxPagesAfterCurrent; }
    }

    if (startPage > 1) {
        container.appendChild(createPageItem(1, '1'));
        if (startPage > 2) container.appendChild(createPageItem(0, '...', true));
    }

    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createPageItem(i, i, false, i === current_page));
    }

    if (endPage < total_pages) {
        if (endPage < total_pages - 1) container.appendChild(createPageItem(0, '...', true));
        container.appendChild(createPageItem(total_pages, total_pages));
    }

    container.appendChild(createPageItem(current_page + 1, 'Next', current_page === total_pages));

    container.addEventListener('click', (e) => {
        e.preventDefault();
        const pageLink = e.target.closest('.page-link');
        if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
            const page = parseInt(pageLink.dataset.page, 10);
            if (page !== current_page) {
                onPageClick(page);
            }
        }
    });
}

// Initialize global search on every page load
initGlobalSearch();