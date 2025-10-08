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
    } else if (cleanPath === '/daftar-jurnal') {
        initDaftarJurnalPage();
    } else if (cleanPath === '/konsinyasi') {
        initKonsinyasiPage();
    } else if (cleanPath === '/transaksi-berulang') {
        initTransaksiBerulangPage();
    } else if (cleanPath === '/laporan-laba-ditahan') {
        initLaporanLabaDitahanPage();
    } else if (cleanPath === '/tutup-buku') {
        initTutupBukuPage();
    } else if (cleanPath === '/analisis-rasio') {
        initAnalisisRasioPage();
    } else if (cleanPath === '/activity-log') {
        initActivityLogPage();
    } else if (cleanPath === '/anggaran') {
        initAnggaranPage();
    } else if (cleanPath === '/users') {
        initUsersPage();
    } else if (cleanPath === '/laporan-pertumbuhan-laba') {
        initLaporanPertumbuhanLabaPage();
    } else if (cleanPath === '/histori-rekonsiliasi') {
        initHistoriRekonsiliasiPage();
    } else if (cleanPath === '/rekonsiliasi-bank') {
        initRekonsiliasiBankPage();
    } else if (cleanPath === '/aset-tetap') {
        initAsetTetapPage();
    } else if (cleanPath === '/buku-panduan') {
        // Halaman ini statis dan tidak memerlukan inisialisasi JavaScript.
        // Cukup daftarkan agar tidak error dan hentikan eksekusi.
        return; 
    }
}

// =================================================================================
// PAGE-SPECIFIC INITIALIZATION FUNCTIONS
// =================================================================================

function initDashboardPage() {
    const bulanFilter = document.getElementById('dashboard-bulan-filter');
    const tahunFilter = document.getElementById('dashboard-tahun-filter');
    const customizeModalEl = document.getElementById('customizeDashboardModal');
    const widgetsForm = document.getElementById('dashboard-widgets-form');
    const saveWidgetsBtn = document.getElementById('save-dashboard-widgets-btn');

    // Definisi semua widget yang tersedia
    const allWidgets = {
        summary_cards: { name: 'Kartu Ringkasan (Saldo, Pemasukan, dll.)', default: true },
        balance_status: { name: 'Status Keseimbangan Neraca', default: true },
        profit_loss_trend: { name: 'Grafik Tren Laba/Rugi', default: true },
        expense_category: { name: 'Grafik Kategori Pengeluaran', default: true },
        recent_transactions: { name: 'Tabel Transaksi Terbaru', default: true },
    };

    // Fungsi untuk mendapatkan preferensi widget dari localStorage
    function getWidgetPreferences() {
        const saved = localStorage.getItem('dashboard_widgets');
        if (saved) {
            return JSON.parse(saved);
        }
        // Jika tidak ada, buat dari default
        const defaults = {};
        for (const key in allWidgets) {
            defaults[key] = allWidgets[key].default;
        }
        return defaults;
    }

    // Fungsi untuk mengisi form di modal kustomisasi
    function populateCustomizeModal() {
        const prefs = getWidgetPreferences();
        widgetsForm.innerHTML = '';
        for (const key in allWidgets) {
            const widget = allWidgets[key];
            const isChecked = prefs[key] !== false; // Default to true if undefined
            widgetsForm.innerHTML += `
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="widget-toggle-${key}" data-widget-key="${key}" ${isChecked ? 'checked' : ''}>
                    <label class="form-check-label" for="widget-toggle-${key}">${widget.name}</label>
                </div>
            `;
        }
    }

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

    async function fetchDashboardData(bulan, tahun, preferences) {
        // Hapus container dashboard lama jika ada, untuk mencegah duplikasi
        const oldDashboardContent = document.getElementById('dashboard-content-wrapper');
        if (oldDashboardContent) {
            oldDashboardContent.remove();
        }

        // Buat HTML untuk widget berdasarkan preferensi
        let widgetsHtml = '';
        if (preferences.summary_cards) {
            widgetsHtml += `
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
                </div>`;
        }
        if (preferences.balance_status || preferences.profit_loss_trend) {
            widgetsHtml += `<div class="row g-3">`;
            if (preferences.balance_status) {
                widgetsHtml += `
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card h-100" id="balance-status-card">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                 <div id="balance-status-icon" class="fs-1"><div class="spinner-border"></div></div>
                                 <h5 class="card-title mt-2" id="balance-status-text">Memeriksa Status...</h5>
                                 <small class="text-muted">Keseimbangan Neraca</small>
                            </div>
                        </div>
                    </div>`;
            }
            if (preferences.profit_loss_trend) {
                widgetsHtml += `
                    <div class="col-lg-9 col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header"><h5 class="card-title mb-0">Tren Laba/Rugi (30 Hari Terakhir)</h5></div>
                            <div class="card-body"><canvas id="profit-loss-trend-chart"></canvas></div>
                        </div>
                    </div>`;
            }
            widgetsHtml += `</div>`;
        }
        if (preferences.expense_category || preferences.recent_transactions) {
            widgetsHtml += `<div class="row g-3">`;
            if (preferences.expense_category) {
                widgetsHtml += `
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
                </div>`;
            }
            if (preferences.recent_transactions) {
                widgetsHtml += `
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
                </div>`;
            }
            widgetsHtml += `</div>`;
        }

        const newDashboardHtml = `<div id="dashboard-content-wrapper" class="mt-4">${widgetsHtml}</div>`;
        // Sisipkan setelah elemen h1 dan filter
        const borderBottom = document.querySelector('.main-content .row.g-3.mb-4');
        if (borderBottom) {
            borderBottom.insertAdjacentHTML('afterend', newDashboardHtml);
        }

        // Ambil data dari API
        try {
            const response = await fetch(`${basePath}/api/dashboard?bulan=${bulan}&tahun=${tahun}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const data = result.data;
            const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

            // Isi data hanya jika widgetnya ada
            if (preferences.summary_cards) {
                document.getElementById('total-saldo-widget').textContent = currencyFormatter.format(data.total_saldo);
                document.getElementById('pemasukan-widget').textContent = currencyFormatter.format(data.pemasukan_bulan_ini);
                document.getElementById('pengeluaran-widget').textContent = currencyFormatter.format(data.pengeluaran_bulan_ini);
            }

            // Render Balance Status
            if (preferences.balance_status) {
                const balanceCard = document.getElementById('balance-status-card');
                const balanceIcon = document.getElementById('balance-status-icon');
                const balanceText = document.getElementById('balance-status-text');
                const balanceStatus = data.balance_status;

                balanceCard.style.cursor = 'default';
                balanceCard.onclick = null;

                if (balanceStatus.is_balanced) {
                    balanceCard.classList.add('bg-success-subtle');
                    balanceIcon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                    balanceText.textContent = 'Balance';
                } else {
                    balanceCard.classList.add('bg-danger-subtle');
                    balanceCard.style.cursor = 'pointer';
                    balanceIcon.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                    balanceText.textContent = 'Tidak Balance';

                    balanceCard.onclick = () => {
                        const detailModalEl = document.getElementById('detailModal');
                        const detailModal = bootstrap.Modal.getInstance(detailModalEl) || new bootstrap.Modal(detailModalEl);
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
                                                <th>ID Jurnal</th><th>Tanggal</th><th>Keterangan</th><th class="text-end">Total Debit</th><th class="text-end">Total Kredit</th>
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
            }
            
            if (preferences.summary_cards) {
                const labaRugiWidget = document.getElementById('laba-rugi-widget');
                const labaRugiSubtitle = document.getElementById('laba-rugi-subtitle');
                labaRugiWidget.textContent = currencyFormatter.format(data.laba_rugi_bulan_ini);
                if (data.laba_rugi_bulan_ini < 0) {
                    labaRugiWidget.parentElement.parentElement.classList.replace('bg-info', 'bg-warning');
                    labaRugiSubtitle.textContent = 'Rugi';
                } else {
                    labaRugiSubtitle.textContent = 'Laba';
                }
            }

            // Render recent transactions
            if (preferences.recent_transactions) {
                const txWidget = document.getElementById('recent-transactions-widget');
                txWidget.innerHTML = '';
                if (data.transaksi_terbaru.length > 0) {
                    data.transaksi_terbaru.forEach(tx => {
                        const row = `
                            <tr>
                                <td>${new Date(tx.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short'})}</td>
                                <td>${tx.keterangan}</td>
                                <td class="text-end">${currencyFormatter.format(tx.jumlah)}</td>
                            </tr>
                        `;
                        txWidget.insertAdjacentHTML('beforeend', row);
                    });
                } else {
                    txWidget.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Tidak ada transaksi.</td></tr>';
                }
            }

            // Render chart
            if (preferences.expense_category && document.getElementById('expense-category-chart')) {
                const chartCtx = document.getElementById('expense-category-chart').getContext('2d');
                if (window.dashboardExpenseChart) window.dashboardExpenseChart.destroy();
                window.dashboardExpenseChart = new Chart(chartCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.pengeluaran_per_kategori.labels,
                        datasets: [{ data: data.pengeluaran_per_kategori.data }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
                });
            }

            // Render profit loss trend chart
            if (preferences.profit_loss_trend && document.getElementById('profit-loss-trend-chart')) {
                const trendChartCtx = document.getElementById('profit-loss-trend-chart').getContext('2d');
                if (window.dashboardTrendChart) window.dashboardTrendChart.destroy();
                window.dashboardTrendChart = new Chart(trendChartCtx, {
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
                        scales: { y: { ticks: { callback: value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value) } } }
                    }
                });
            }
        } catch (error) {
            showToast(`Gagal memuat data dashboard: ${error.message}`, 'error');
        }
    }

    // --- Event Listeners ---
    const filterHandler = () => {
        const prefs = getWidgetPreferences();
        fetchDashboardData(bulanFilter.value, tahunFilter.value, prefs);
    };
    bulanFilter.addEventListener('change', filterHandler);
    tahunFilter.addEventListener('change', filterHandler);

    customizeModalEl.addEventListener('show.bs.modal', populateCustomizeModal);

    saveWidgetsBtn.addEventListener('click', () => {
        const newPrefs = {};
        widgetsForm.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            newPrefs[checkbox.dataset.widgetKey] = checkbox.checked;
        });
        localStorage.setItem('dashboard_widgets', JSON.stringify(newPrefs));
        showToast('Preferensi dashboard berhasil disimpan.', 'success');
        bootstrap.Modal.getInstance(customizeModalEl).hide();
        // Muat ulang dashboard dengan preferensi baru
        filterHandler();
    });

    setupFilters();
    filterHandler(); // Panggil handler untuk memuat data awal
}

function initTransaksiPage() {
    const tableBody = document.getElementById('transaksi-table-body');
    const modalEl = document.getElementById('transaksiModal');
    const jurnalDetailModalEl = document.getElementById('jurnalDetailModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
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
    let periodLockDate = null;

    // Cek jika URL memiliki hash untuk memfilter transaksi tertentu
    if (window.location.hash && window.location.hash.startsWith('#tx-')) {
        const txId = window.location.hash.substring(4); // Hapus '#tx-'
        searchInput.value = txId;
        // Hapus hash dari URL agar tidak mengganggu navigasi selanjutnya
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }

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
            const [transaksiRes, settingsRes] = await Promise.all([
                fetch(`${basePath}/api/transaksi?${params.toString()}`),
                fetch(`${basePath}/api/settings`) // Ambil juga data settings
            ]);
            const result = await transaksiRes.json();
            const settingsResult = await settingsRes.json();

            if (result.status !== 'success') throw new Error(result.message);
            if (settingsResult.status === 'success' && settingsResult.data.period_lock_date) {
                periodLockDate = new Date(settingsResult.data.period_lock_date);
            }

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

                    // Info Audit (Created/Updated)
                    const createdAt = new Date(tx.created_at);
                    const updatedAt = new Date(tx.updated_at);
                    const createdBy = tx.created_by_name || 'sistem';
                    const updatedBy = tx.updated_by_name || 'sistem';
                    
                    let auditInfo = `Dibuat: ${createdBy} pada ${createdAt.toLocaleString('id-ID')}`;
                    let auditIcon = '<i class="bi bi-info-circle"></i>';

                    if (updatedBy && updatedAt.getTime() > createdAt.getTime() + 1000) { // Cek jika ada update signifikan
                        auditInfo += `\nDiperbarui: ${updatedBy} pada ${updatedAt.toLocaleString('id-ID')}`;
                        auditIcon = '<i class="bi bi-info-circle-fill text-primary"></i>';
                    }

                    // Cek apakah transaksi terkunci
                    const isLocked = periodLockDate && new Date(tx.tanggal) <= periodLockDate;
                    const disabledAttr = isLocked ? 'disabled title="Periode terkunci"' : '';
                    const deleteBtnHtml = `<button class="btn btn-sm btn-danger delete-btn" data-id="${tx.id}" data-keterangan="${tx.keterangan}" title="Hapus" ${disabledAttr}><i class="bi bi-trash-fill"></i></button>`;
                    const editBtnHtml = `<button class="btn btn-sm btn-warning edit-btn" data-id="${tx.id}" title="Edit" ${disabledAttr}><i class="bi bi-pencil-fill"></i></button>`;

                    const row = `
                        <tr id="tx-${tx.id}">
                            <td>${new Date(tx.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'})}</td>
                            <td>${akunUtama}</td>
                            <td><small class="text-muted">${tx.nomor_referensi || '-'}</small></td>
                            <td>${tx.keterangan.replace(/\n/g, '<br>')}</td>
                            <td class="text-end">${jumlahDisplay}</td>
                            <td><span data-bs-toggle="tooltip" data-bs-placement="top" title="${auditInfo}">${auditIcon}</span></td>
                            <td><small>${akunKas}</small></td>
                            <td class="text-end">
                                ${deleteBtnHtml}
                                ${editBtnHtml}
                                <button class="btn btn-sm btn-secondary view-journal-btn" data-id="${tx.id}" title="Lihat Jurnal"><i class="bi bi-journal-text"></i></button>                                
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center">Tidak ada transaksi ditemukan.</td></tr>';
            }
            renderPagination(paginationContainer, result.pagination, loadTransaksi);
            // Inisialisasi ulang tooltip setelah data baru dimuat
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
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

    // Menambahkan fungsionalitas 'Enter' untuk pindah field
    if (modalEl) {
        modalEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault(); // Mencegah form tersubmit

                // Dapatkan semua elemen yang bisa difokuskan dan terlihat di dalam form
                const focusableElements = Array.from(
                    form.querySelectorAll(
                        'input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)'
                    )
                ).filter(el => el.offsetParent !== null); // Filter hanya yang terlihat

                const currentIndex = focusableElements.indexOf(document.activeElement);
                const nextIndex = currentIndex + 1;

                if (nextIndex < focusableElements.length) {
                    // Pindah ke elemen berikutnya
                    focusableElements[nextIndex].focus();
                } else {
                    // Jika sudah di elemen terakhir, klik tombol simpan
                    const saveBtn = document.getElementById('save-transaksi-btn');
                    if (saveBtn) {
                        saveBtn.click();
                    }
                }
            }
        });
    }

    // Menambahkan fungsionalitas 'Enter' pada textarea keterangan untuk menyimpan
    const keteranganTextarea = document.getElementById('keterangan');
    if (keteranganTextarea) {
        keteranganTextarea.addEventListener('keydown', (e) => {
            // Jika 'Enter' ditekan tanpa 'Shift', tampilkan konfirmasi
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); // Mencegah membuat baris baru
                if (confirm('Simpan transaksi?')) {
                    const saveBtn = document.getElementById('save-transaksi-btn');
                    if (saveBtn) saveBtn.click();
                } else {
                    // Jika tidak, fokus kembali ke field jumlah
                    const jumlahInput = document.getElementById('jumlah');
                    if (jumlahInput) jumlahInput.focus();
                }
            }
            // Jika 'Shift + Enter' ditekan, akan tetap membuat baris baru (perilaku default)
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
                const action = formData.get('action');
                loadTransaksi(1); // Selalu refresh tabel di latar belakang

                if (action === 'add') {
                    // Untuk 'add', jangan tutup modal, tapi reset form untuk entri baru
                    form.reset();
                    form.classList.remove('was-validated');
                    document.getElementById('tanggal').valueAsDate = new Date();
                    // Kembalikan ke jenis transaksi default (misal: pengeluaran)
                    jenisBtnGroup.querySelector('button[data-value="pengeluaran"]').click();
                } else {
                    // Untuk 'update', tutup modal seperti biasa
                    modal.hide();
                }
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
                // Gunakan POST untuk get_single sesuai dengan handler
                const formData = new FormData();
                formData.append('action', 'get_single');
                formData.append('id', id);
                const response = await fetch(`${basePath}/api/transaksi`, { method: 'POST', body: formData });
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
            const jurnalModal = bootstrap.Modal.getInstance(jurnalDetailModalEl) || new bootstrap.Modal(jurnalDetailModalEl);
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

    // Fokus ke field jumlah saat modal selesai ditampilkan
    modalEl.addEventListener('shown.bs.modal', () => {
        const jumlahInput = document.getElementById('jumlah');
        if (jumlahInput) jumlahInput.focus();
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
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
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
    const lrCompareModeSelect = document.getElementById('lr-compare-mode');
    const lrPeriod2Container = document.getElementById('lr-period-2');
    const labaRugiTglMulai2 = document.getElementById('laba-rugi-tanggal-mulai-2');
    const labaRugiTglAkhir2 = document.getElementById('laba-rugi-tanggal-akhir-2');
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
        
        // Set default comparison period to previous month
        const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const firstDayPrevMonth = new Date(prevMonthDate.getFullYear(), prevMonthDate.getMonth(), 1).toISOString().split('T')[0];
        const lastDayPrevMonth = new Date(prevMonthDate.getFullYear(), prevMonthDate.getMonth() + 1, 0).toISOString().split('T')[0];
        labaRugiTglMulai2.value = savedFilters.lr_start2 || firstDayPrevMonth;
        labaRugiTglAkhir2.value = savedFilters.lr_end2 || lastDayPrevMonth;

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
        const { current, previous } = data;
        const isComparison = !!previous;

        // Gabungkan semua akun dari kedua periode untuk membuat template tabel
        const allAccounts = new Map();
        [...(current.pendapatan || []), ...(current.beban || []), ...(previous?.pendapatan || []), ...(previous?.beban || [])].forEach(acc => {
            if (!allAccounts.has(acc.id)) {
                allAccounts.set(acc.id, { id: acc.id, nama_akun: acc.nama_akun, tipe_akun: acc.tipe_akun });
            }
        });

        const findAccountTotal = (periodData, accountId) => {
            const acc = [...(periodData.pendapatan || []), ...(periodData.beban || [])].find(a => a.id === accountId);
            return acc ? acc.total : 0;
        };

        const calculateChange = (currentVal, prevVal) => {
            if (prevVal === 0) return currentVal > 0 ? '<span class="text-success">Baru</span>' : '-';
            const change = ((currentVal - prevVal) / Math.abs(prevVal)) * 100;
            const color = change >= 0 ? 'text-success' : 'text-danger';
            const icon = change >= 0 ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
            return `<span class="${color}">${icon} ${Math.abs(change).toFixed(1)}%</span>`;
        };

        const renderRows = (tipe) => {
            let html = '';
            const accountsOfType = Array.from(allAccounts.values()).filter(acc => acc.tipe_akun === tipe);
            if (accountsOfType.length === 0) return `<tr><td colspan="${isComparison ? 4 : 2}" class="text-muted">Tidak ada data.</td></tr>`;

            accountsOfType.forEach(acc => {
                const currentTotal = findAccountTotal(current, acc.id);
                html += `<tr><td>${acc.nama_akun}</td><td class="text-end">${formatCurrencyAccounting(currentTotal)}</td>`;
                if (isComparison) {
                    const prevTotal = findAccountTotal(previous, acc.id);
                    html += `<td class="text-end">${formatCurrencyAccounting(prevTotal)}</td>`;
                    html += `<td class="text-end small">${calculateChange(currentTotal, prevTotal)}</td>`;
                }
                html += `</tr>`;
            });
            return html;
        };

        const labaRugiHtml = `
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Keterangan</th>
                        <th class="text-end">Periode Saat Ini</th>
                        ${isComparison ? '<th class="text-end">Periode Pembanding</th><th class="text-end">Perubahan</th>' : ''}
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light"><td colspan="${isComparison ? 4 : 2}" class="fw-bold">Pendapatan</td></tr>
                    ${renderRows('Pendapatan')}
                    <tr class="table-light">
                        <td class="fw-bold">TOTAL PENDAPATAN</td>
                        <td class="text-end fw-bold">${formatCurrencyAccounting(current.summary.total_pendapatan)}</td>
                        ${isComparison ? `<td class="text-end fw-bold">${formatCurrencyAccounting(previous.summary.total_pendapatan)}</td><td class="text-end small">${calculateChange(current.summary.total_pendapatan, previous.summary.total_pendapatan)}</td>` : ''}
                    </tr>
                    
                    <tr class="table-light"><td colspan="${isComparison ? 4 : 2}" class="fw-bold pt-4">Beban</td></tr>
                    ${renderRows('Beban')}
                    <tr class="table-light">
                        <td class="fw-bold">TOTAL BEBAN</td>
                        <td class="text-end fw-bold">${formatCurrencyAccounting(current.summary.total_beban)}</td>
                        ${isComparison ? `<td class="text-end fw-bold">${formatCurrencyAccounting(previous.summary.total_beban)}</td><td class="text-end small">${calculateChange(current.summary.total_beban, previous.summary.total_beban)}</td>` : ''}
                    </tr>
                </tbody>
                <tfoot class="table-group-divider">
                    <tr class="${current.summary.laba_bersih >= 0 ? 'table-success' : 'table-danger'}">
                        <td class="fw-bold fs-5">LABA (RUGI) BERSIH</td>
                        <td class="text-end fw-bold fs-5">${formatCurrencyAccounting(current.summary.laba_bersih)}</td>
                        ${isComparison ? `<td class="text-end fw-bold fs-5">${formatCurrencyAccounting(previous.summary.laba_bersih)}</td><td class="text-end small">${calculateChange(current.summary.laba_bersih, previous.summary.laba_bersih)}</td>` : ''}
                    </tr>
                </tfoot>
            </table>
        `;
        labaRugiContent.innerHTML = labaRugiHtml;
    }

    async function loadLabaRugi() {
        const params = new URLSearchParams({
            start: labaRugiTglMulai.value,
            end: labaRugiTglAkhir.value
        });

        const compareMode = lrCompareModeSelect.value;
        if (compareMode !== 'none') {
            params.append('compare', 'true');
            let start2, end2;

            if (compareMode === 'custom') {
                start2 = labaRugiTglMulai2.value;
                end2 = labaRugiTglAkhir2.value;
            } else {
                const mainStartDate = new Date(labaRugiTglMulai.value);
                const mainEndDate = new Date(labaRugiTglAkhir.value);

                if (compareMode === 'previous_period') {
                    const duration = mainEndDate.getTime() - mainStartDate.getTime();
                    const prevEndDate = new Date(mainStartDate.getTime() - (24 * 60 * 60 * 1000)); // One day before main start
                    const prevStartDate = new Date(prevEndDate.getTime() - duration);
                    start2 = prevStartDate.toISOString().split('T')[0];
                    end2 = prevEndDate.toISOString().split('T')[0];
                } else if (compareMode === 'previous_year_month') {
                    const prevStart = new Date(mainStartDate);
                    prevStart.setFullYear(prevStart.getFullYear() - 1);
                    const prevEnd = new Date(mainEndDate);
                    prevEnd.setFullYear(prevEnd.getFullYear() - 1);
                    start2 = prevStart.toISOString().split('T')[0];
                    end2 = prevEnd.toISOString().split('T')[0];
                }
            }
            params.append('start2', start2);
            params.append('end2', end2);
        }

        labaRugiContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        try {
            const response = await fetch(`${basePath}/api/laporan/laba-rugi?${params.toString()}`);
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
                </tbody>
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
    labaRugiTab?.addEventListener('shown.bs.tab', loadLabaRugi);
    labaRugiTglMulai.addEventListener('change', handleLabaRugiChange);
    labaRugiTglAkhir.addEventListener('change', handleLabaRugiChange);
    labaRugiTglMulai2.addEventListener('change', handleLabaRugiChange);
    labaRugiTglAkhir2.addEventListener('change', handleLabaRugiChange);
    lrCompareModeSelect.addEventListener('change', () => {
        lrPeriod2Container.classList.toggle('d-none', lrCompareModeSelect.value !== 'custom');
        handleLabaRugiChange();
    });
    arusKasTab?.addEventListener('shown.bs.tab', loadArusKas);
    arusKasTglMulai.addEventListener('change', handleArusKasChange);
    arusKasTglAkhir.addEventListener('change', handleArusKasChange);

    // --- Event Listeners untuk Export ---

    // Event listener untuk tombol PDF (sekarang menggunakan FPDF handler)
    exportNeracaPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'neraca', tanggal: neracaTanggalInput.value };
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportLrPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'laba-rugi', start: labaRugiTglMulai.value, end: labaRugiTglAkhir.value, compare_mode: lrCompareModeSelect.value };
        if (lrCompareModeSelect.value !== 'none') {
            params.compare = 'true';
            params.start2 = labaRugiTglMulai2.value;
            params.end2 = labaRugiTglAkhir2.value;
        }
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportAkPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'arus-kas', start: arusKasTglMulai.value, end: arusKasTglAkhir.value };
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // Event listener untuk tombol CSV (tetap sama)
    exportNeracaCsvBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            window.open(`${basePath}/api/csv?report=neraca&format=csv&tanggal=${neracaTanggalInput.value}`, '_blank');
    });
    exportLrCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
            const params = new URLSearchParams({ report: 'laba-rugi', format: 'csv', start: labaRugiTglMulai.value, end: labaRugiTglAkhir.value });
            if (lrCompareModeSelect.value !== 'none') {
                params.append('compare', 'true');
                params.append('start2', labaRugiTglMulai2.value);
                params.append('end2', labaRugiTglAkhir2.value);
            }
            window.open(`${basePath}/api/csv?${params.toString()}`, '_blank');
    });
    exportAkCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
            window.open(`${basePath}/api/csv?report=arus-kas&format=csv&start=${arusKasTglMulai.value}&end=${arusKasTglAkhir.value}`, '_blank');
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
            if (window.dailyChart) {
                window.dailyChart.destroy();
            }
            window.dailyChart = new Chart(chartCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Pemasukan', 'Pengeluaran'],
                    datasets: [{
                        label: 'Jumlah',
                        data: [total_pemasukan, total_pengeluaran],
                        backgroundColor: ['rgba(25, 135, 84, 0.7)', 'rgba(220, 53, 69, 0.7)'],
                        borderColor: ['rgba(25, 135, 84, 1)', 'rgba(220, 53, 69, 1)'],
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
                    const idDisplay = tx.ref || `${tx.source.toUpperCase()}-${tx.id}`; // Gunakan ref jika ada
                    const idHtml = `<a href="#" class="view-detail-btn" data-type="${tx.source}" data-id="${tx.id}">${idDisplay}</a>`;

                    tableHtml += `
                        <tr>
                            <td><small>${idHtml}</small></td>
                            <td>${tx.keterangan}</td>
                            <td><small>${tx.akun_terkait || '<i>N/A</i>'}</small></td>
                            <td class="text-end text-success">${tx.pemasukan > 0 ? currencyFormatter.format(tx.pemasukan) : '-'}</td>
                            <td class="text-end text-danger">${tx.pengeluaran > 0 ? currencyFormatter.format(tx.pengeluaran) : '-'}</td>
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

    exportPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'laporan-harian', tanggal: tanggalInput.value };
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        window.open(`${basePath}/api/csv?report=laporan-harian&format=csv&tanggal=${tanggalInput.value}`, '_blank');
    });

    reportContent.addEventListener('click', async (e) => {
        const viewBtn = e.target.closest('.view-detail-btn');
        if (!viewBtn) return;

        e.preventDefault();
        const { type, id } = viewBtn.dataset;

        const detailModalEl = document.getElementById('detailModal');
        const detailModal = bootstrap.Modal.getInstance(detailModalEl) || new bootstrap.Modal(detailModalEl);
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

function initTutupBukuPage() {
    const closingDateInput = document.getElementById('closing-date');
    const processBtn = document.getElementById('process-closing-btn');
    const historyContainer = document.getElementById('closing-history-container');

    if (!processBtn) return;

    // Set default date to end of last year
    const lastYear = new Date().getFullYear() - 1;
    closingDateInput.value = `${lastYear}-12-31`;

    async function loadHistory() {
        historyContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></div>';
        try {
            const response = await fetch(`${basePath}/api/tutup-buku?action=list_history`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            historyContainer.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(item => {
                    // Tombol batal hanya muncul untuk item paling atas (paling baru)
                    const isLatest = historyContainer.children.length === 0;
                    const batalBtn = isLatest ? `<button class="btn btn-sm btn-outline-warning ms-2 reverse-closing-btn" data-id="${item.id}" title="Batalkan Jurnal Penutup ini"><i class="bi bi-unlock-fill"></i></button>` : '';
                    const historyItem = `
                        <a href="${basePath}/daftar-jurnal#JRN-${item.id}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-journal-id="${item.id}">
                            <span>${item.keterangan} <span class="badge bg-secondary rounded-pill">${new Date(item.tanggal).toLocaleDateString('id-ID')}</span></span>
                            <span>${batalBtn}</span>
                        </a>
                    `;
                    historyContainer.insertAdjacentHTML('beforeend', historyItem);
                });
            } else {
                historyContainer.innerHTML = '<p class="text-center text-muted">Belum ada histori tutup buku.</p>';
            }
        } catch (error) {
            historyContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        }
    }

    processBtn.addEventListener('click', async () => {
        const closingDate = closingDateInput.value;
        if (!closingDate) {
            showToast('Harap pilih tanggal tutup buku.', 'error');
            return;
        }

        if (confirm(`ANDA YAKIN? Proses ini akan membuat Jurnal Penutup untuk periode yang berakhir pada ${closingDate}. Aksi ini tidak dapat dibatalkan dengan mudah.`)) {
            const formData = new FormData();
            formData.append('action', 'process_closing');
            formData.append('closing_date', closingDate);

            const response = await fetch(`${basePath}/api/tutup-buku`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') loadHistory();
        }
    });

    historyContainer.addEventListener('click', async (e) => {
        const reverseBtn = e.target.closest('.reverse-closing-btn');
        if (reverseBtn) {
            e.preventDefault(); // Mencegah navigasi dari link <a>
            e.stopPropagation(); // Mencegah event bubble up ke link <a>

            const id = reverseBtn.dataset.id;
            if (confirm(`ANDA YAKIN? \n\nMembatalkan Jurnal Penutup ini akan membuat Jurnal Pembalik dan membuka kembali periode yang terkunci. \n\nAksi ini hanya bisa dilakukan pada Jurnal Penutup yang paling baru.`)) {
                const formData = new FormData();
                formData.append('action', 'reverse_closing');
                formData.append('id', id);

                const response = await fetch(`${basePath}/api/tutup-buku`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') loadHistory();
            }
        }
    });

    loadHistory();
}

function initLaporanLabaDitahanPage() {
    const tglMulai = document.getElementById('re-tanggal-mulai');
    const tglAkhir = document.getElementById('re-tanggal-akhir');
    const tampilkanBtn = document.getElementById('re-tampilkan-btn');
    const reportContent = document.getElementById('re-report-content');
    const reportHeader = document.getElementById('re-report-header');
    const exportPdfBtn = document.getElementById('export-re-pdf');
    const exportCsvBtn = document.getElementById('export-re-csv');

    if (!tampilkanBtn) return;

    // Set default dates to current year
    const now = new Date();
    tglMulai.value = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
    tglAkhir.value = new Date(now.getFullYear(), 11, 31).toISOString().split('T')[0];

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 });

    async function loadReport() {
        const startDate = tglMulai.value;
        const endDate = tglAkhir.value;

        if (!startDate || !endDate) {
            showToast('Harap pilih rentang tanggal.', 'error');
            return;
        }

        const originalBtnHtml = tampilkanBtn.innerHTML;
        tampilkanBtn.disabled = true;
        tampilkanBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Memuat...`;
        reportContent.innerHTML = `<div class="text-center p-5"><div class="spinner-border"></div></div>`;

        try {
            const params = new URLSearchParams({ start_date: startDate, end_date: endDate });
            const response = await fetch(`${basePath}/api/laporan-laba-ditahan?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const { account_info, saldo_awal, transactions } = result.data;
            reportHeader.textContent = `Laporan Perubahan Laba Ditahan: ${account_info.nama_akun}`;

            let tableHtml = `
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr><th>Tanggal</th><th>Keterangan</th><th class="text-end">Debit</th><th class="text-end">Kredit</th><th class="text-end">Saldo</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4"><strong>Saldo Awal per ${new Date(startDate).toLocaleDateString('id-ID')}</strong></td>
                            <td class="text-end"><strong>${currencyFormatter.format(saldo_awal)}</strong></td>
                        </tr>
            `;

            let saldoBerjalan = parseFloat(saldo_awal);
            transactions.forEach(tx => {
                const debit = parseFloat(tx.debit);
                const kredit = parseFloat(tx.kredit);
                saldoBerjalan += kredit - debit; // Saldo normal Ekuitas adalah Kredit
                
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

            tableHtml += `</tbody><tfoot><tr class="table-light"><td colspan="4" class="text-end fw-bold">Saldo Akhir per ${new Date(endDate).toLocaleDateString('id-ID')}</td><td class="text-end fw-bold">${currencyFormatter.format(saldoBerjalan)}</td></tr></tfoot></table>`;
            reportContent.innerHTML = tableHtml;

        } catch (error) {
            reportContent.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        } finally {
            tampilkanBtn.disabled = false;
            tampilkanBtn.innerHTML = originalBtnHtml;
        }
    }

    tampilkanBtn.addEventListener('click', loadReport);

    exportPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'laporan-laba-ditahan', start_date: tglMulai.value, end_date: tglAkhir.value };
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const url = `${basePath}/api/csv?report=laporan-laba-ditahan&format=csv&start_date=${tglMulai.value}&end_date=${tglAkhir.value}`;
        window.open(url, '_blank');
    });

    loadReport(); // Initial load
}

function initAnalisisRasioPage() {
    const dateInput = document.getElementById('ra-tanggal-akhir');
    const compareDateInput = document.getElementById('ra-tanggal-pembanding');
    const analyzeBtn = document.getElementById('ra-tampilkan-btn');
    const contentContainer = document.getElementById('ratio-analysis-content');
    const cardTemplate = document.getElementById('ratio-card-template');
    const exportPdfBtn = document.getElementById('export-ra-pdf');

    if (!analyzeBtn) return;

    // Set default dates
    const today = new Date();
    dateInput.value = today.toISOString().split('T')[0];
    const lastMonth = new Date(today.setMonth(today.getMonth() - 1));
    compareDateInput.value = lastMonth.toISOString().split('T')[0];

    const ratioDefinitions = {
        profit_margin: {
            name: 'Profit Margin',
            formula: '(Laba Bersih / Total Pendapatan) * 100%',
            description: 'Mengukur seberapa besar laba bersih yang dihasilkan dari setiap rupiah pendapatan. Semakin tinggi, semakin baik.',
            format: (val) => val.toFixed(2),
            interpret: (val) => val < 0.5 ? 'Sehat' : (val < 0.8 ? 'Waspada' : 'Berisiko Tinggi'),
            color: (val) => val < 0.5 ? 'text-success' : (val < 0.8 ? 'text-warning' : 'text-danger'),
        },
        debt_to_equity: {
            name: 'Debt to Equity Ratio',
            formula: 'Total Liabilitas / Total Ekuitas',
            description: 'Mengukur proporsi pembiayaan perusahaan antara utang dan modal sendiri. Semakin rendah, semakin aman posisi keuangan perusahaan.',
            format: (val) => val.toFixed(2),
            interpret: (val) => val < 1 ? 'Sehat' : (val < 2 ? 'Waspada' : 'Berisiko Tinggi'),
            color: (val) => val < 1 ? 'text-success' : (val < 2 ? 'text-warning' : 'text-danger'),
        },
        debt_to_asset: {
            name: 'Debt to Asset Ratio',
            formula: 'Total Liabilitas / Total Aset',
            description: 'Mengukur seberapa besar aset perusahaan yang dibiayai oleh utang. Semakin rendah, semakin baik.',
            format: (val) => val.toFixed(2),
            interpret: (val) => val < 0.4 ? 'Sangat Sehat' : (val < 0.6 ? 'Sehat' : 'Berisiko'),
            color: (val) => val < 0.4 ? 'text-success' : (val < 0.6 ? 'text-primary' : 'text-danger'),
        },
        return_on_equity: {
            name: 'Return on Equity (ROE)',
            formula: '(Laba Bersih / Total Ekuitas) * 100%',
            description: 'Mengukur kemampuan perusahaan menghasilkan laba dari modal yang diinvestasikan oleh pemilik/anggota. Semakin tinggi, semakin efisien penggunaan modal.',
            format: (val) => `${(val * 100).toFixed(2)}%`,
            interpret: (val) => val > 0.15 ? 'Sangat Baik' : (val > 0.05 ? 'Baik' : 'Kurang Efisien'),
            color: (val) => val > 0.15 ? 'text-success' : (val > 0.05 ? 'text-warning' : 'text-danger'),
        },
        return_on_assets: {
            name: 'Return on Assets (ROA)',
            formula: '(Laba Bersih / Total Aset) * 100%',
            description: 'Mengukur efisiensi perusahaan dalam menggunakan asetnya untuk menghasilkan laba. Semakin tinggi, semakin baik.',
            format: (val) => `${(val * 100).toFixed(2)}%`,
            interpret: (val) => val > 0.1 ? 'Sangat Efisien' : (val > 0.05 ? 'Efisien' : 'Kurang Efisien'),
            color: (val) => val > 0.1 ? 'text-success' : (val > 0.05 ? 'text-primary' : 'text-warning'),
        },
        asset_turnover: {
            name: 'Asset Turnover Ratio',
            formula: 'Total Pendapatan / Total Aset',
            description: 'Mengukur efisiensi penggunaan aset untuk menghasilkan pendapatan. Semakin tinggi, semakin efisien.',
            format: (val) => val.toFixed(2) + 'x',
            interpret: (val) => val > 1.5 ? 'Sangat Efisien' : (val > 1 ? 'Efisien' : 'Kurang Efisien'),
            color: (val) => val > 1.5 ? 'text-success' : (val > 1 ? 'text-primary' : 'text-warning'),
        }
    };

    async function runAnalysis() {
        const date = dateInput.value;
        const compareDate = compareDateInput.value;

        if (!date) {
            showToast('Tanggal analisis wajib diisi.', 'error');
            return;
        }

        contentContainer.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';

        try {
            const params = new URLSearchParams({ date });
            if (compareDate) {
                params.append('compare_date', compareDate);
            }
            const response = await fetch(`${basePath}/api/analisis-rasio?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const { current, previous } = result.data;
            contentContainer.innerHTML = '<div class="row"></div>';
            const rowContainer = contentContainer.querySelector('.row');

            for (const key in current) {
                if (ratioDefinitions[key]) {
                    const def = ratioDefinitions[key];
                    const card = cardTemplate.content.cloneNode(true);
                    
                    card.querySelector('.ratio-name').textContent = def.name;
                    card.querySelector('[data-bs-toggle="tooltip"]').setAttribute('title', def.description);
                    card.querySelector('.ratio-value').textContent = def.format(current[key]);
                    card.querySelector('.ratio-formula').textContent = `Rumus: ${def.formula}`;
                    
                    const interpretationEl = card.querySelector('.ratio-interpretation');
                    interpretationEl.textContent = `Interpretasi: ${def.interpret(current[key])}`;
                    interpretationEl.classList.add(def.color(current[key]));

                    if (previous && previous[key] !== null) {
                        const change = current[key] - previous[key];
                        const changeIcon = change >= 0 ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
                        const changeColor = change >= 0 ? 'text-success' : 'text-danger';
                        card.querySelector('.ratio-comparison').innerHTML = `${changeIcon} <span class="${changeColor}">${Math.abs(change * (def.name.includes('%') ? 100 : 1)).toFixed(2)}</span> vs periode sebelumnya`;
                    } else {
                        card.querySelector('.ratio-comparison').textContent = 'Tidak ada data pembanding.';
                    }

                    rowContainer.appendChild(card);
                }
            }

            // Re-initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

        } catch (error) {
            contentContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        }
    }

    analyzeBtn.addEventListener('click', runAnalysis);

    exportPdfBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = {
            report: 'analisis-rasio',
            date: dateInput.value,
            compare_date: compareDateInput.value
        };
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    runAnalysis(); // Initial load
}

// Deklarasikan variabel modal di luar fungsi untuk mencegah duplikasi listener
let anggaranModalInstance = null;

function initAnggaranPage() {
    const yearFilter = document.getElementById('anggaran-tahun-filter');
    const monthFilter = document.getElementById('anggaran-bulan-filter');
    const tampilkanBtn = document.getElementById('anggaran-tampilkan-btn');
    const reportTableBody = document.getElementById('anggaran-report-table-body');
    const chartCanvas = document.getElementById('anggaran-chart');
    const modalEl = document.getElementById('anggaranModal');
    const modalTahunLabel = document.getElementById('modal-tahun-label');
    const managementContainer = document.getElementById('anggaran-management-container');
    const saveAnggaranBtn = document.getElementById('save-anggaran-btn');
    const exportPdfBtn = document.getElementById('export-anggaran-pdf');
    const exportCsvBtn = document.getElementById('export-anggaran-csv');
    const compareSwitch = document.getElementById('anggaran-compare-switch');
    const trendChartCanvas = document.getElementById('anggaran-trend-chart');

    let budgetChart = null;
    let trendChart = null;
    
    if (!yearFilter || !reportTableBody) return;

    // Inisialisasi instance modal jika belum ada
    if (!anggaranModalInstance) {
        anggaranModalInstance = new bootstrap.Modal(modalEl);
    }

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    function setupFilters() {
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;

        for (let i = 0; i < 5; i++) {
            yearFilter.add(new Option(currentYear - i, currentYear - i));
        }
        const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        months.forEach((month, index) => {
            monthFilter.add(new Option(month, index + 1));
        });

        yearFilter.value = currentYear;
        monthFilter.value = currentMonth;
    }

    async function loadTrendChart() {
        const selectedYear = yearFilter.value;
        if (!trendChartCanvas) return;

        try {
            const response = await fetch(`${basePath}/api/anggaran?action=get_trend_data&tahun=${selectedYear}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            if (window.anggaranTrendChart) {
                window.anggaranTrendChart.destroy();
            }

            const labels = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            window.anggaranTrendChart = new Chart(trendChartCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Anggaran Bulanan',
                            data: result.data.anggaran_bulanan,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            fill: false,
                            tension: 0.1
                        },
                        {
                            label: 'Realisasi Bulanan',
                            data: result.data.realisasi_bulanan,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            fill: true,
                            tension: 0.1
                        }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } } }
            });
        } catch (error) {
            console.error("Gagal memuat data tren:", error);
        }
    }

    async function loadReport() {
        const selectedYear = yearFilter.value;
        const selectedMonth = monthFilter.value;
        const isComparing = compareSwitch.checked;

        reportTableBody.innerHTML = '<tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>';
        try {
            const params = new URLSearchParams({
                action: 'get_report',
                tahun: selectedYear,
                bulan: selectedMonth,
                compare: isComparing
            });
            const response = await fetch(`${basePath}/api/anggaran?${params.toString()}`);
            const result = await response.json();

            // Update Summary Cards
            if (result.status === 'success' && result.summary) {
                document.getElementById('summary-total-anggaran').textContent = currencyFormatter.format(result.summary.total_anggaran);
                document.getElementById('summary-total-realisasi').textContent = currencyFormatter.format(result.summary.total_realisasi);
                document.getElementById('summary-sisa-anggaran').textContent = currencyFormatter.format(result.summary.total_sisa);
            }

            // Update Table Header
            const tableHeader = document.getElementById('anggaran-report-table-header');
            if (isComparing) {
                tableHeader.innerHTML = `
                    <th>Akun Beban</th>
                    <th class="text-end">Anggaran (${selectedYear})</th>
                    <th class="text-end">Realisasi (${selectedYear})</th>
                    <th class="text-end">Realisasi (${selectedYear - 1})</th>
                    <th style="width: 20%;">Penggunaan</th>
                `;
            } else {
                tableHeader.innerHTML = `
                    <th>Akun Beban</th>
                    <th class="text-end">Anggaran Bulanan</th>
                    <th class="text-end">Realisasi Belanja</th>
                    <th class="text-end">Sisa Anggaran</th>
                    <th style="width: 25%;">Penggunaan</th>
                `;
            }

            reportTableBody.innerHTML = '';

            // Update Chart
            if (window.anggaranBudgetChart) {
                window.anggaranBudgetChart.destroy();
            }
            if (result.status === 'success' && result.data.length > 0) {
                const labels = result.data.map(item => item.nama_akun);
                const budgetData = result.data.map(item => item.anggaran_bulanan);
                const realizationData = result.data.map(item => item.realisasi_belanja);                
                const realizationPrevYearData = result.data.map(item => item.realisasi_belanja_lalu);

                const chartConfig = {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Anggaran',
                                data: budgetData,
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Realisasi',
                                data: realizationData,
                                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true } } }
                };

                if (isComparing) {
                    chartConfig.data.datasets.push({
                        label: `Realisasi ${selectedYear - 1}`,
                        data: realizationPrevYearData,
                        backgroundColor: 'rgba(255, 206, 86, 0.5)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    });
                }

                window.anggaranBudgetChart = new Chart(chartCanvas, chartConfig);
            }

            if (result.status === 'success' && result.data.length > 0) {
                result.data.forEach(item => {
                    const percentage = parseFloat(item.persentase);
                    let progressBarColor = 'bg-success';
                    if (percentage > 75) progressBarColor = 'bg-warning';
                    if (percentage >= 100) progressBarColor = 'bg-danger';

                    let row;
                    if (isComparing) {
                        row = `
                            <tr>
                                <td>${item.nama_akun}</td>
                                <td class="text-end">${currencyFormatter.format(item.anggaran_bulanan)}</td>
                                <td class="text-end">${currencyFormatter.format(item.realisasi_belanja)}</td>
                                <td class="text-end text-muted">${currencyFormatter.format(item.realisasi_belanja_lalu)}</td>
                                <td>
                                    <div class="progress" role="progressbar" style="height: 20px;">
                                        <div class="progress-bar ${progressBarColor}" style="width: ${Math.min(percentage, 100)}%">${percentage.toFixed(1)}%</div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    } else {
                        row = `
                            <tr>
                                <td>${item.nama_akun}</td>
                                <td class="text-end">${currencyFormatter.format(item.anggaran_bulanan)}</td>
                                <td class="text-end">${currencyFormatter.format(item.realisasi_belanja)}</td>
                                <td class="text-end fw-bold ${item.sisa_anggaran < 0 ? 'text-danger' : ''}">${currencyFormatter.format(item.sisa_anggaran)}</td>
                                <td>
                                    <div class="progress" role="progressbar" style="height: 20px;">
                                        <div class="progress-bar ${progressBarColor}" style="width: ${Math.min(percentage, 100)}%">${percentage.toFixed(1)}%</div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }
                    reportTableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                reportTableBody.innerHTML = '<tr><td colspan="5" class="text-center">Belum ada data anggaran untuk periode ini.</td></tr>';
            }
        } catch (error) {
            reportTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal memuat laporan: ${error.message}</td></tr>`;
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
                            <span class="input-group-text" style="width: 250px; font-size: 0.9rem;">${item.nama_akun}</span>
                            <input type="number" class="form-control budget-amount-input" name="budgets[${item.account_id}]" value="${item.jumlah_anggaran}" placeholder="Anggaran Tahunan">
                        </div>
                    `;
                    managementContainer.insertAdjacentHTML('beforeend', itemHtml);
                });
            } else {
                managementContainer.innerHTML = '<p class="text-muted text-center">Tidak ada akun beban yang dapat dianggarkan.</p>';
            }
        } catch (error) {
            managementContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat data anggaran.</div>`;
        }
    }

    saveAnggaranBtn.addEventListener('click', async () => {
        const form = document.getElementById('anggaran-management-form');
        const formData = new FormData(form);
        formData.append('action', 'save_budgets');
        formData.append('tahun', yearFilter.value);

        const response = await fetch(`${basePath}/api/anggaran`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status === 'success' ? 'success' : 'error');
        if (result.status === 'success') anggaranModalInstance.hide();
    });

    exportPdfBtn.addEventListener('click', (e) => {
        e.preventDefault();

        // 1. Ambil gambar dari kedua chart sebagai base64
        const trendChartImage = window.anggaranTrendChart ? window.anggaranTrendChart.toBase64Image() : '';
        const budgetChartImage = window.anggaranBudgetChart ? window.anggaranBudgetChart.toBase64Image() : '';

        // 2. Buat form sementara untuk mengirim data via POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank'; // Buka di tab baru

        const params = {
            report: 'anggaran',
            tahun: yearFilter.value,
            bulan: monthFilter.value,
            compare: compareSwitch.checked,
            trend_chart_image: trendChartImage,
            budget_chart_image: budgetChartImage
        };

        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportCsvBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const tahun = yearFilter.value;
        const bulan = monthFilter.value;
        const isComparing = compareSwitch.checked;
        const params = new URLSearchParams({ report: 'anggaran', format: 'csv', tahun, bulan, compare: isComparing });
        const url = `${basePath}/api/csv?${params.toString()}`;
        window.open(url, '_blank');
    });

    // Gabungkan listener untuk tombol dan switch
    tampilkanBtn.addEventListener('click', () => {
        loadReport();
        loadTrendChart(); // Perbarui juga grafik tren saat filter tahun berubah
    });
    compareSwitch.addEventListener('change', loadReport);

    // Inisialisasi instance modal jika belum ada
    if (!anggaranModalInstance) {
        anggaranModalInstance = new bootstrap.Modal(modalEl);
    }
    // Cek apakah listener sudah ada sebelum menambahkannya
    // Listener untuk modal ini perlu dicek karena modal ada di luar area konten utama SPA
    if (!modalEl.dataset.listenerAttached) {
        modalEl.addEventListener('show.bs.modal', loadBudgetManagement);
        modalEl.addEventListener('hidden.bs.modal', () => {
            loadReport(); // Muat ulang laporan setelah modal ditutup
        });
        modalEl.dataset.listenerAttached = 'true';
    }

    setupFilters();
    loadReport(); // Muat laporan detail
    loadTrendChart(); // Muat grafik tren
}

function initLaporanPertumbuhanLabaPage() {
    const yearFilter = document.getElementById('lpl-tahun-filter');
    const tampilkanBtn = document.getElementById('lpl-tampilkan-btn');
    const chartCanvas = document.getElementById('lpl-chart');
    const tableBody = document.getElementById('lpl-report-table-body');
    const compareSwitch = document.getElementById('lpl-compare-switch');
    const viewModeGroup = document.getElementById('lpl-view-mode');
    const exportPdfBtn = document.getElementById('export-lpl-pdf');
    const exportCsvBtn = document.getElementById('export-lpl-csv');

    if (!yearFilter) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
    const quarters = ["Triwulan 1 (Jan-Mar)", "Triwulan 2 (Apr-Jun)", "Triwulan 3 (Jul-Sep)", "Triwulan 4 (Okt-Des)"];
    const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

    function setupFilters() {
        const currentYear = new Date().getFullYear();
        for (let i = 0; i < 5; i++) {
            yearFilter.add(new Option(currentYear - i, currentYear - i));
        }
        yearFilter.value = currentYear;
    }

    async function loadReport() {
        const selectedYear = yearFilter.value;
        const viewMode = document.querySelector('input[name="view_mode"]:checked').value;
        const isComparing = compareSwitch.checked;
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;

        try {
            const params = new URLSearchParams({
                view_mode: viewMode,
                tahun: selectedYear,
                compare: isComparing
            });
            const response = await fetch(`${basePath}/api/laporan-pertumbuhan-laba?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const data = result.data;

            // Render Table
            tableBody.innerHTML = '';
            const tableHeader = document.getElementById('lpl-report-table-header');
            const isCumulative = viewMode === 'cumulative';
            const isYearly = viewMode === 'yearly';
            const periodLabel = isCumulative ? 'Bulan (YTD)' : (isYearly ? 'Tahun' : (viewMode === 'monthly' ? 'Bulan' : 'Triwulan'));
            const growthLabel = isYearly ? 'YoY' : (isCumulative ? 'MoM' : (viewMode === 'monthly' ? 'MoM' : 'QoQ'));


            if (isComparing) {
                tableHeader.innerHTML = `
                    <th>${periodLabel}</th>
                    <th class="text-end">Laba Bersih (${selectedYear})</th>
                    <th class="text-end">Laba Bersih (${selectedYear - 1})</th>
                    <th class="text-end">Pertumbuhan ${growthLabel}</th>
                    <th class="text-end">Pertumbuhan YoY</th>
                `;
            } else {
                tableHeader.innerHTML = `
                    <th>${periodLabel}</th>
                    <th class="text-end">Total Pendapatan</th>
                    <th class="text-end">Total Beban</th>
                    <th class="text-end">Laba (Rugi) Bersih</th>
                    <th class="text-end">Pertumbuhan ${growthLabel}</th>
                `;
            }
            data.forEach(row => {
                let growthHtml;
                if (row.pertumbuhan > 0) {
                    growthHtml = `<span class="text-success"><i class="bi bi-arrow-up"></i> ${row.pertumbuhan.toFixed(2)}%</span>`;
                } else if (row.pertumbuhan < 0) {
                    growthHtml = `<span class="text-danger"><i class="bi bi-arrow-down"></i> ${Math.abs(row.pertumbuhan).toFixed(2)}%</span>`;
                } else {
                    growthHtml = `<span>-</span>`;
                }

                let tableRow;
                let periodName;
                if (viewMode === 'quarterly') {
                    periodName = quarters[row.triwulan - 1];
                } else if (viewMode === 'yearly') {
                    periodName = row.tahun;
                } else { // monthly or cumulative
                    periodName = months[row.bulan - 1];
                }
                if (isComparing) {
                    let yoyGrowthHtml;
                    if (row.pertumbuhan_yoy > 0) {
                        yoyGrowthHtml = `<span class="text-success"><i class="bi bi-arrow-up"></i> ${row.pertumbuhan_yoy.toFixed(2)}%</span>`;
                    } else if (row.pertumbuhan_yoy < 0) {
                        yoyGrowthHtml = `<span class="text-danger"><i class="bi bi-arrow-down"></i> ${Math.abs(row.pertumbuhan_yoy).toFixed(2)}%</span>`;
                    } else {
                        yoyGrowthHtml = `<span>-</span>`;
                    }
                    tableRow = `
                        <tr>
                            <td>${periodName}</td>
                            <td class="text-end fw-bold ${row.laba_bersih < 0 ? 'text-danger' : ''}">${currencyFormatter.format(row.laba_bersih)}</td>
                            <td class="text-end text-muted">${currencyFormatter.format(row.laba_bersih_lalu)}</td>
                            <td class="text-end">${growthHtml}</td>
                            <td class="text-end">${yoyGrowthHtml}</td>
                        </tr>
                    `;
                } else {
                    tableRow = `
                        <tr>
                            <td>${periodName}</td>
                            <td class="text-end">${currencyFormatter.format(row.total_pendapatan)}</td>
                            <td class="text-end">${currencyFormatter.format(row.total_beban)}</td>
                            <td class="text-end fw-bold ${row.laba_bersih < 0 ? 'text-danger' : ''}">${currencyFormatter.format(row.laba_bersih)}</td>
                            <td class="text-end">${growthHtml}</td>
                        </tr>
                    `;
                }
                tableBody.insertAdjacentHTML('beforeend', tableRow);
            });

            // Render Chart
            if (window.lplProfitChart) {
                window.lplProfitChart.destroy();
            }

            let chartLabels;
            if (viewMode === 'quarterly') {
                chartLabels = ["Q1", "Q2", "Q3", "Q4"];
            } else if (viewMode === 'yearly') {
                chartLabels = data.map(d => d.tahun);
            } else { // monthly or cumulative
                chartLabels = months.map(m => m.substring(0, 3));
            }

            const isCumulativeView = viewMode === 'cumulative';

            const chartConfig = {
                type: isCumulativeView ? 'line' : 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: `Laba Bersih ${selectedYear}`,
                        data: data.map(d => d.laba_bersih),
                        // Atur warna berdasarkan tipe chart
                        backgroundColor: isCumulativeView 
                            ? 'rgba(0, 122, 255, 0.1)' 
                            : data.map(d => d.laba_bersih >= 0 ? 'rgba(25, 135, 84, 0.6)' : 'rgba(220, 53, 69, 0.6)'),
                        borderColor: isCumulativeView 
                            ? 'rgba(0, 122, 255, 1)' 
                            : data.map(d => d.laba_bersih >= 0 ? 'rgba(25, 135, 84, 1)' : 'rgba(220, 53, 69, 1)'),
                        borderWidth: isCumulativeView ? 2 : 1,
                        fill: isCumulativeView, // Aktifkan fill hanya untuk line chart
                        tension: 0.3 // Buat garis lebih halus
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += currencyFormatter.format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => currencyFormatter.format(value) }
                        }
                    }
                }
            };

            if (isComparing) {
                chartConfig.data.datasets.push({
                    label: `Laba Bersih ${selectedYear - 1}`,
                    data: data.map(d => d.laba_bersih_lalu),
                    backgroundColor: 'rgba(108, 117, 125, 0.5)',
                    borderColor: 'rgba(108, 117, 125, 1)',
                    borderWidth: 1,
                    type: 'line', // Tampilkan sebagai garis untuk perbandingan
                    tension: 0.1
                });
            }
            window.lplProfitChart = new Chart(chartCanvas, chartConfig);

        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal memuat laporan: ${error.message}</td></tr>`;
        }
    }

    // Cukup tambahkan listener secara langsung. SPA akan menghapusnya saat navigasi.
    tampilkanBtn.addEventListener('click', loadReport);
    viewModeGroup.addEventListener('change', loadReport);
    compareSwitch.addEventListener('change', loadReport);

    exportPdfBtn.addEventListener('click', (e) => {
        e.preventDefault();

        // 1. Ambil gambar chart sebagai base64
        const chartImage = window.lplProfitChart ? window.lplProfitChart.toBase64Image() : '';

        // 2. Buat form sementara untuk mengirim data via POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank'; // Buka di tab baru

        const params = {
            report: 'laporan-pertumbuhan-laba',
            tahun: yearFilter.value,
            view_mode: document.querySelector('input[name="view_mode"]:checked').value,
            compare: compareSwitch.checked,
            chart_image: chartImage // Kirim data gambar
        };

        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportCsvBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const params = new URLSearchParams({
            report: 'laporan-pertumbuhan-laba',
            format: 'csv',
            tahun: yearFilter.value,
            view_mode: document.querySelector('input[name="view_mode"]:checked').value,
            compare: compareSwitch.checked
        });
        window.open(`${basePath}/api/csv?${params.toString()}`, '_blank');
    });

    setupFilters();
    loadReport(); // Initial load
}

function initTransaksiBerulangPage() {
    const tableBody = document.getElementById('recurring-table-body');
    if (!tableBody) return;

    async function loadTemplates() {
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;
        try {
            const response = await fetch(`${basePath}/api/recurring?action=list_templates`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(t => {
                    const statusBadge = t.is_active == 1 ? `<span class="badge bg-success">Aktif</span>` : `<span class="badge bg-secondary">Non-Aktif</span>`;
                    const toggleText = t.is_active == 1 ? 'Non-aktifkan' : 'Aktifkan';
                    const row = `
                        <tr>
                            <td>${t.name}</td>
                            <td>Setiap ${t.frequency_interval} ${t.frequency_unit}</td>
                            <td>${new Date(t.next_run_date).toLocaleDateString('id-ID', {dateStyle: 'long'})}</td>
                            <td>${statusBadge}</td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-info edit-recurring-btn" data-id="${t.id}"><i class="bi bi-pencil-fill"></i></button>
                                    <button class="btn btn-sm btn-secondary toggle-status-btn" data-id="${t.id}" data-active="${t.is_active}" title="${toggleText}"><i class="bi bi-power"></i></button>
                                    <button class="btn btn-sm btn-danger delete-recurring-btn" data-id="${t.id}"><i class="bi bi-trash-fill"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Belum ada template yang dibuat.</td></tr>`;
            }
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    tableBody.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-recurring-btn');
        if (deleteBtn) {
            if (confirm('Yakin ingin menghapus template ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', deleteBtn.dataset.id);
                const response = await fetch(`${basePath}/api/recurring`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status);
                if (result.status === 'success') loadTemplates();
            }
        }

        const toggleBtn = e.target.closest('.toggle-status-btn');
        if (toggleBtn) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id', toggleBtn.dataset.id);
            formData.append('is_active', toggleBtn.dataset.active == 1 ? 0 : 1);
            const response = await fetch(`${basePath}/api/recurring`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status);
            if (result.status === 'success') loadTemplates();
        }

        const editBtn = e.target.closest('.edit-recurring-btn');
        if (editBtn) {
            const response = await fetch(`${basePath}/api/recurring?action=get_single&id=${editBtn.dataset.id}`);
            const result = await response.json();
            if (result.status === 'success') {
                openRecurringModal(result.data.template_type, JSON.parse(result.data.template_data), result.data);
            } else {
                showToast(result.message, 'error');
            }
        }
    });

    document.getElementById('add-recurring-btn').addEventListener('click', (e) => {
        e.preventDefault();
        // Arahkan pengguna untuk membuat jurnal dulu
        showToast('Silakan buat draf jurnal di halaman "Entri Jurnal", lalu klik "Jadikan Berulang".', 'info');
        navigate(`${basePath}/entri-jurnal`);
    });

    loadTemplates();
}

function initActivityLogPage() {
    const tableBody = document.getElementById('activity-log-table-body');
    const searchInput = document.getElementById('search-log');
    const startDateFilter = document.getElementById('filter-log-mulai');
    const endDateFilter = document.getElementById('filter-log-akhir');
    const limitSelect = document.getElementById('filter-log-limit');
    const paginationContainer = document.getElementById('activity-log-pagination');

    if (!tableBody) return;

    // Set default dates
    endDateFilter.valueAsDate = new Date();
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    startDateFilter.valueAsDate = sevenDaysAgo;

    async function loadLogs(page = 1) {
        const params = new URLSearchParams({
            page,
            limit: limitSelect.value,
            search: searchInput.value,
            start_date: startDateFilter.value,
            end_date: endDateFilter.value,
        });

        tableBody.innerHTML = `<tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;
        try {
            const response = await fetch(`${basePath}/api/activity-log?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(log => {
                    const row = `
                        <tr>
                            <td><small>${new Date(log.timestamp).toLocaleString('id-ID')}</small></td>
                            <td>${log.username}</td>
                            <td><span class="badge bg-info text-dark">${log.action}</span></td>
                            <td>${log.details}</td>
                            <td>${log.ip_address}</td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Tidak ada log aktivitas ditemukan.</td></tr>';
            }
            renderPagination(paginationContainer, result.pagination, loadLogs);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    let debounceTimer;
    const filterHandler = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadLogs(1), 300); };
    [searchInput, startDateFilter, endDateFilter, limitSelect].forEach(el => el.addEventListener('change', filterHandler));
    searchInput.addEventListener('input', filterHandler);

    loadLogs();
}


function initKonsinyasiPage() {
    // --- Element Selectors ---
    const supplierTableBody = document.getElementById('suppliers-table-body');
    const itemTableBody = document.getElementById('items-table-body');
    const supplierModalEl = document.getElementById('supplierModal');
    const itemModalEl = document.getElementById('itemModal');
    const saleForm = document.getElementById('consignment-sale-form');
    const reportLink = document.getElementById('view-consignment-report-link');
    const reportModalEl = document.getElementById('consignmentReportModal');
    const debtSummaryReportLink = document.getElementById('view-debt-summary-report-link');
    const printDebtSummaryBtn = document.getElementById('print-debt-summary-btn');
    const debtSummaryModalEl = document.getElementById('debtSummaryReportModal');
    const filterSisaUtangBtn = document.getElementById('filter-sisa-utang-btn');

    if (!supplierTableBody || !itemTableBody || !reportModalEl) return;

    const supplierModal = new bootstrap.Modal(supplierModalEl);
    const itemModal = new bootstrap.Modal(itemModalEl);
    const reportModal = new bootstrap.Modal(reportModalEl);

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    // --- Report Modal Logic ---
    const reportStartDateEl = document.getElementById('report-start-date');
    const reportEndDateEl = document.getElementById('report-end-date');
    const filterReportBtn = document.getElementById('filter-report-btn');
    const printReportBtn = document.getElementById('print-report-btn');

    async function loadConsignmentReport() {
        const startDate = reportStartDateEl.value;
        const endDate = reportEndDateEl.value;
        if (!startDate || !endDate) {
            showToast('Harap pilih tanggal mulai dan akhir.', 'error');
            return;
        }

        const reportBody = document.getElementById('consignment-report-body');
        reportBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        
        const params = new URLSearchParams({ action: 'get_sales_report', start_date: startDate, end_date: endDate });
        const response = await fetch(`${basePath}/api/konsinyasi?${params.toString()}`);
        const result = await response.json();

        if (result.status === 'success') {
            let html = '<table class="table table-sm table-hover"><thead><tr><th>Pemasok</th><th>Barang</th><th class="text-end">Terjual</th><th class="text-end">Harga Beli</th><th class="text-end">Total Utang</th></tr></thead><tbody>';
            let totalUtangKeseluruhan = 0;
            if (result.data.length > 0) {
                result.data.forEach(row => {
                    totalUtangKeseluruhan += parseFloat(row.total_utang);
                    html += `<tr><td>${row.nama_pemasok}</td><td>${row.nama_barang}</td><td class="text-end">${row.total_terjual}</td><td class="text-end">${currencyFormatter.format(row.harga_beli)}</td><td class="text-end">${currencyFormatter.format(row.total_utang)}</td></tr>`;
                });
            } else {
                html += '<tr><td colspan="5" class="text-center text-muted">Tidak ada penjualan pada periode ini.</td></tr>';
            }
            html += `</tbody><tfoot><tr class="table-light fw-bold"><td colspan="4" class="text-end">Total Utang Konsinyasi</td><td class="text-end">${currencyFormatter.format(totalUtangKeseluruhan)}</td></tr></tfoot></table>`;
            reportBody.innerHTML = html;
        } else {
            reportBody.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
        }
    }

    // --- Load Functions ---
    async function loadSuppliers() {
        supplierTableBody.innerHTML = '<tr><td colspan="3" class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        const response = await fetch(`${basePath}/api/konsinyasi?action=list_suppliers`);
        const result = await response.json();
        supplierTableBody.innerHTML = '';
        if (result.status === 'success' && result.data.length > 0) {
            result.data.forEach(s => {
                supplierTableBody.innerHTML += `<tr><td>${s.nama_pemasok}</td><td>${s.kontak || '-'}</td><td class="text-end"><button class="btn btn-sm btn-info edit-supplier-btn" data-id="${s.id}" data-nama="${s.nama_pemasok}" data-kontak="${s.kontak}"><i class="bi bi-pencil-fill"></i></button> <button class="btn btn-sm btn-danger delete-supplier-btn" data-id="${s.id}"><i class="bi bi-trash-fill"></i></button></td></tr>`;
            });
        } else {
            supplierTableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Belum ada pemasok.</td></tr>';
        }
    }

    async function loadItems() {
        itemTableBody.innerHTML = '<tr><td colspan="6" class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        const response = await fetch(`${basePath}/api/konsinyasi?action=list_items`);
        const result = await response.json();
        itemTableBody.innerHTML = '';
        if (result.status === 'success' && result.data.length > 0) {
            result.data.forEach(i => {
                itemTableBody.innerHTML += `<tr><td>${i.nama_barang}</td><td>${i.nama_pemasok}</td><td class="text-end">${currencyFormatter.format(i.harga_jual)}</td><td class="text-end">${currencyFormatter.format(i.harga_beli)}</td><td class="text-end">${i.stok_saat_ini} / ${i.stok_awal}</td><td class="text-end"><button class="btn btn-sm btn-info edit-item-btn" data-id="${i.id}"><i class="bi bi-pencil-fill"></i></button> <button class="btn btn-sm btn-danger delete-item-btn" data-id="${i.id}"><i class="bi bi-trash-fill"></i></button></td></tr>`;
            });
        } else {
            itemTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada barang konsinyasi.</td></tr>';
        }
    }

    async function loadItemsForSale() {
        const select = document.getElementById('cs-item-id');
        select.innerHTML = '<option>Memuat...</option>';
        const response = await fetch(`${basePath}/api/konsinyasi?action=list_items`);
        const result = await response.json();
        select.innerHTML = '<option value="">-- Pilih Barang --</option>';
        if (result.status === 'success') {
            result.data.forEach(i => {
                if (i.stok_saat_ini > 0) {
                    select.add(new Option(`${i.nama_barang} (Stok: ${i.stok_saat_ini})`, i.id));
                }
            });
        }
    }

    async function loadSuppliersForPayment() {
        const select = document.getElementById('cp-supplier-id');
        select.innerHTML = '<option>Memuat...</option>';
        const response = await fetch(`${basePath}/api/konsinyasi?action=list_suppliers`);
        const result = await response.json();
        select.innerHTML = '<option value="">-- Pilih Pemasok --</option>';
        if (result.status === 'success') {
            result.data.forEach(s => select.add(new Option(s.nama_pemasok, s.id)));
        }
    }

    async function loadCashAccountsForPayment() {
        const select = document.getElementById('cp-kas-account-id');
        select.innerHTML = '<option>Memuat...</option>';
        const response = await fetch(`${basePath}/api/settings?action=get_cash_accounts`);
        const result = await response.json();
        select.innerHTML = '<option value="">-- Pilih Akun Kas/Bank --</option>';
        if (result.status === 'success') {
            result.data.forEach(acc => select.add(new Option(acc.nama_akun, acc.id)));
        }
    }

    async function loadPaymentHistory() {
        const tableBody = document.getElementById('payment-history-table-body');
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center p-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        const response = await fetch(`${basePath}/api/konsinyasi?action=list_payments`);
        const result = await response.json();
        tableBody.innerHTML = '';
        if (result.status === 'success' && result.data.length > 0) {
            result.data.forEach(p => {
                tableBody.innerHTML += `
                    <tr>
                        <td>${new Date(p.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'})}</td>
                        <td>${p.nama_pemasok || '<i>Tidak terdeteksi</i>'}</td>
                        <td><small>${p.keterangan}</small></td>
                        <td class="text-end">${currencyFormatter.format(p.jumlah)}</td>
                    </tr>
                `;
            });
        } else {
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Belum ada riwayat pembayaran.</td></tr>';
        }
    }

    document.getElementById('consignment-payment-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('action', 'pay_debt');
        formData.append('tanggal', document.getElementById('cp-tanggal').value);
        formData.append('supplier_id', document.getElementById('cp-supplier-id').value);
        formData.append('jumlah', document.getElementById('cp-jumlah').value);
        formData.append('kas_account_id', document.getElementById('cp-kas-account-id').value);
        formData.append('keterangan', document.getElementById('cp-keterangan').value);
        const response = await fetch(`${basePath}/api/konsinyasi`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status);
        if (result.status === 'success') { e.target.reset(); document.getElementById('cp-tanggal').valueAsDate = new Date(); loadPaymentHistory(); }
    });

    // --- Event Listeners ---
    document.getElementById('save-supplier-btn').addEventListener('click', async () => {
        const form = document.getElementById('supplier-form');
        const formData = new FormData(form);
        formData.set('action', document.getElementById('supplier-action').value);
        const response = await fetch(`${basePath}/api/konsinyasi`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status);
        if (result.status === 'success') { supplierModal.hide(); loadSuppliers(); }
    });

    document.getElementById('save-item-btn').addEventListener('click', async () => {
        const form = document.getElementById('item-form');
        const formData = new FormData(form);
        formData.set('action', document.getElementById('item-action').value);
        const response = await fetch(`${basePath}/api/konsinyasi`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status);
        if (result.status === 'success') { itemModal.hide(); loadItems(); loadItemsForSale(); }
    });

    saleForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Ambil detail untuk pesan konfirmasi
        const itemSelect = document.getElementById('cs-item-id');
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const itemName = selectedOption ? selectedOption.text.split(' (Stok:')[0] : 'barang';
        const qty = document.getElementById('cs-qty').value;

        // Tampilkan dialog konfirmasi
        if (!confirm(`Anda yakin ingin menjual ${qty} x ${itemName}?`)) {
            return; // Hentikan proses jika pengguna menekan "Batal"
        }

        const formData = new FormData();
        formData.append('action', 'sell_item');
        formData.append('item_id', document.getElementById('cs-item-id').value);
        formData.append('qty', document.getElementById('cs-qty').value);
        formData.append('tanggal', document.getElementById('cs-tanggal').value);
        
        const response = await fetch(`${basePath}/api/konsinyasi`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status);
        if (result.status === 'success') {
            saleForm.reset();
            document.getElementById('cs-tanggal').valueAsDate = new Date();
            loadItemsForSale();
        }
    });

    reportLink.addEventListener('click', async (e) => {
        e.preventDefault();
        const reportBody = document.getElementById('consignment-report-body');
        reportBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        reportModal.show();
        const response = await fetch(`${basePath}/api/konsinyasi?action=get_sales_report`);
        const result = await response.json();
        if (result.status === 'success') {
            let html = '<table class="table table-sm"><thead><tr><th>Pemasok</th><th>Barang</th><th class="text-end">Terjual</th><th class="text-end">Harga Beli</th><th class="text-end">Total Utang</th></tr></thead><tbody>';
            let totalUtangKeseluruhan = 0;
            result.data.forEach(row => {
                totalUtangKeseluruhan += parseFloat(row.total_utang);
                html += `<tr><td>${row.nama_pemasok}</td><td>${row.nama_barang}</td><td class="text-end">${row.total_terjual}</td><td class="text-end">${currencyFormatter.format(row.harga_beli)}</td><td class="text-end">${currencyFormatter.format(row.total_utang)}</td></tr>`;
            });
            html += `</tbody><tfoot><tr class="table-light fw-bold"><td colspan="4" class="text-end">Total Utang Konsinyasi</td><td class="text-end">${currencyFormatter.format(totalUtangKeseluruhan)}</td></tr></tfoot></table>`;
            reportBody.innerHTML = html;
        } else {
            reportBody.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
        }
        const now = new Date();
    });

    filterReportBtn.addEventListener('click', loadConsignmentReport);

    printReportBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'konsinyasi', start_date: reportStartDateEl.value, end_date: reportEndDateEl.value };
        for (const key in params) {
            const hiddenField = document.createElement('input'); hiddenField.type = 'hidden'; hiddenField.name = key; hiddenField.value = params[key]; form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    document.getElementById('barang-tab').addEventListener('shown.bs.tab', () => {
        loadItems();
    });

    document.getElementById('pembayaran-tab').addEventListener('shown.bs.tab', () => {
        loadSuppliersForPayment();
        loadCashAccountsForPayment();
        loadPaymentHistory();
        document.getElementById('cp-tanggal').valueAsDate = new Date();
    });

    async function loadDebtSummaryReport() {
        const startDate = document.getElementById('sisa-utang-start-date').value;
        const endDate = document.getElementById('sisa-utang-end-date').value;
        const reportBody = document.getElementById('debt-summary-report-body');

        if (!startDate || !endDate) {
            showToast('Harap pilih tanggal mulai dan akhir.', 'error');
            return;
        }

        reportBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';

        try {
            const params = new URLSearchParams({ action: 'get_debt_summary_report', start_date: startDate, end_date: endDate });
            const response = await fetch(`${basePath}/api/konsinyasi?${params.toString()}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            let html = '<table class="table table-sm table-hover"><thead><tr><th>Pemasok</th><th class="text-end">Total Utang</th><th class="text-end">Total Bayar</th><th class="text-end">Sisa Utang</th></tr></thead><tbody>';
            let grandTotalSisa = 0;
            result.data.forEach(row => {
                grandTotalSisa += parseFloat(row.sisa_utang);
                html += `<tr><td>${row.nama_pemasok}</td><td class="text-end">${currencyFormatter.format(row.total_utang)}</td><td class="text-end">${currencyFormatter.format(row.total_bayar)}</td><td class="text-end fw-bold">${currencyFormatter.format(row.sisa_utang)}</td></tr>`;
            });
            html += `</tbody><tfoot><tr class="table-light fw-bold"><td colspan="3" class="text-end">Total Sisa Utang Keseluruhan</td><td class="text-end">${currencyFormatter.format(grandTotalSisa)}</td></tr></tfoot></table>`;
            reportBody.innerHTML = html;

        } catch (error) {
            reportBody.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        }
    }

    debtSummaryModalEl.addEventListener('show.bs.modal', () => {
        const startDateEl = document.getElementById('sisa-utang-start-date');
        const endDateEl = document.getElementById('sisa-utang-end-date');
        const now = new Date();
        startDateEl.value = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0]; // Awal tahun
        endDateEl.value = new Date(now.getFullYear(), 11, 31).toISOString().split('T')[0]; // Akhir tahun
    });

    filterSisaUtangBtn.addEventListener('click', loadDebtSummaryReport);

    printDebtSummaryBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { 
            report: 'konsinyasi-sisa-utang',
            start_date: document.getElementById('sisa-utang-start-date').value,
            end_date: document.getElementById('sisa-utang-end-date').value
        };
        for (const key in params) {
            const hiddenField = document.createElement('input'); hiddenField.type = 'hidden'; hiddenField.name = key; hiddenField.value = params[key]; form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // --- Modal & Table Delegation ---
    supplierModalEl.addEventListener('show.bs.modal', (e) => {
        const button = e.relatedTarget;
        const form = document.getElementById('supplier-form');
        form.reset();
        if (button.dataset.action === 'add') {
            document.getElementById('supplierModalLabel').textContent = 'Tambah Pemasok';
            document.getElementById('supplier-action').value = 'save_supplier';
        }
    });

    itemModalEl.addEventListener('show.bs.modal', async (e) => {
        const button = e.relatedTarget;
        const form = document.getElementById('item-form');
        // form.reset(); // Reset is handled in the specific 'add' or 'edit' logic
        // Populate supplier dropdown
        const supplierSelect = document.getElementById('supplier_id');
        supplierSelect.innerHTML = '<option>Memuat...</option>';
        const response = await fetch(`${basePath}/api/konsinyasi?action=list_suppliers`);
        const result = await response.json();
        supplierSelect.innerHTML = '<option value="">-- Pilih Pemasok --</option>';
        if (result.status === 'success') {
            result.data.forEach(s => supplierSelect.add(new Option(s.nama_pemasok, s.id)));
        }

        if (button && button.dataset.action === 'add') {
            form.reset();
            document.getElementById('itemModalLabel').textContent = 'Tambah Barang Konsinyasi';
            document.getElementById('item-action').value = 'save_item';
            document.getElementById('tanggal_terima').valueAsDate = new Date();
        }
    });

    document.getElementById('pemasok-pane').addEventListener('click', e => {
        const editBtn = e.target.closest('.edit-supplier-btn');
        if (editBtn) {
            document.getElementById('supplierModalLabel').textContent = 'Edit Pemasok';
            document.getElementById('supplier-action').value = 'save_supplier';
            document.getElementById('supplier-id').value = editBtn.dataset.id;
            document.getElementById('nama_pemasok').value = editBtn.dataset.nama;
            document.getElementById('kontak').value = editBtn.dataset.kontak;
            supplierModal.show();
        }
        const deleteBtn = e.target.closest('.delete-supplier-btn');
        if (deleteBtn) {
            if (confirm('Yakin ingin menghapus pemasok ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete_supplier');
                formData.append('id', deleteBtn.dataset.id);
                fetch(`${basePath}/api/konsinyasi`, { method: 'POST', body: formData }).then(res => res.json()).then(result => {
                    showToast(result.message, result.status);
                    if (result.status === 'success') loadSuppliers();
                });
            }
        }
    });

    document.getElementById('barang-pane').addEventListener('click', async e => {
        const editBtn = e.target.closest('.edit-item-btn');
        if (editBtn) {
            const id = editBtn.dataset.id;
            try {
                const response = await fetch(`${basePath}/api/konsinyasi?action=get_single_item&id=${id}`);
                const result = await response.json();
                if (result.status !== 'success') throw new Error(result.message);
                
                const item = result.data;
                await itemModalEl.querySelector('#supplier_id').dispatchEvent(new Event('show.bs.modal')); // Trigger supplier load
                document.getElementById('itemModalLabel').textContent = 'Edit Barang Konsinyasi';
                document.getElementById('item-action').value = 'save_item';
                document.getElementById('item-id').value = item.id;
                document.getElementById('supplier_id').value = item.supplier_id;
                document.getElementById('nama_barang').value = item.nama_barang;
                document.getElementById('harga_jual').value = item.harga_jual;
                document.getElementById('harga_beli').value = item.harga_beli;
                document.getElementById('stok_awal').value = item.stok_awal;
                document.getElementById('tanggal_terima').value = item.tanggal_terima;
                itemModal.show();
            } catch (error) { showToast(`Gagal memuat data barang: ${error.message}`, 'error'); }
        }
    });

    // --- Initial Load ---
    loadSuppliers();
    loadItems();
    loadItemsForSale();
    document.getElementById('cs-tanggal').valueAsDate = new Date();
}

function initAsetTetapPage() {
    const tableBody = document.getElementById('assets-table-body');
    const modalEl = document.getElementById('assetModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('asset-form');
    const saveBtn = document.getElementById('save-asset-btn');
    const disposalModalEl = document.getElementById('disposalModal');
    const disposalModal = new bootstrap.Modal(disposalModalEl);
    const postDepreciationBtn = document.getElementById('post-depreciation-btn');
    const printReportBtn = document.getElementById('print-asset-report-btn');

    if (!tableBody) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    function setupDepreciationFilters() {
        const monthSelect = document.getElementById('depreciation-month');
        const yearSelect = document.getElementById('depreciation-year');
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth(); // 0-11

        // Populate years
        for (let i = 0; i < 5; i++) {
            yearSelect.add(new Option(currentYear - i, currentYear - i));
        }

        // Populate months
        const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        months.forEach((month, index) => {
            monthSelect.add(new Option(month, index + 1));
        });

        // Set default to current month and year
        monthSelect.value = currentMonth + 1;
        yearSelect.value = currentYear;
    }

    async function loadAssets() {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;
        try {
            const response = await fetch(`${basePath}/api/aset_tetap?action=list`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(asset => {
                    const isDisposed = asset.status === 'Dilepas';
                    const statusBadge = isDisposed ? `<span class="badge bg-secondary">Dilepas</span>` : `<span class="badge bg-success">Aktif</span>`;
                    const actionButtons = isDisposed ? `<button class="btn btn-sm btn-secondary" disabled title="Aset sudah dilepas"><i class="bi bi-check-circle-fill"></i></button>` : `
                        <button class="btn btn-sm btn-info edit-asset-btn" data-id="${asset.id}"><i class="bi bi-pencil-fill"></i></button>
                        <button class="btn btn-sm btn-warning dispose-asset-btn" data-id="${asset.id}" data-nama="${asset.nama_aset}"><i class="bi bi-box-arrow-right"></i></button>
                        <button class="btn btn-sm btn-danger delete-asset-btn" data-id="${asset.id}"><i class="bi bi-trash-fill"></i></button>`;

                    const row = `
                        <tr class="${isDisposed ? 'table-light text-muted' : ''}">
                            <td>${asset.nama_aset} ${statusBadge}</td>
                            <td>${new Date(asset.tanggal_akuisisi).toLocaleDateString('id-ID')}</td>
                            <td class="text-end">${currencyFormatter.format(asset.harga_perolehan)}</td>
                            <td class="text-end">${currencyFormatter.format(asset.akumulasi_penyusutan)}</td>
                            <td class="text-end fw-bold">${currencyFormatter.format(asset.nilai_buku)}</td>
                            <td class="text-end">
                                ${actionButtons}
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada aset tetap yang dicatat.</td></tr>';
            }
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    async function loadAccountsForModal() {
        try {
            const response = await fetch(`${basePath}/api/aset_tetap?action=get_accounts`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            const { aset, beban, pendapatan, kas } = result.data;
            const createOptions = (accounts) => accounts.map(acc => `<option value="${acc.id}">${acc.kode_akun} - ${acc.nama_akun}</option>`).join('');

            document.getElementById('akun_aset_id').innerHTML = createOptions(aset);
            document.getElementById('akun_akumulasi_penyusutan_id').innerHTML = createOptions(aset);
            document.getElementById('akun_beban_penyusutan_id').innerHTML = createOptions(beban);
        } catch (error) {
            showToast(`Gagal memuat daftar akun: ${error.message}`, 'error');
        }
    }

    saveBtn.addEventListener('click', async () => {
        const formData = new FormData(form);
        const response = await fetch(`${basePath}/api/aset_tetap`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status);
        if (result.status === 'success') {
            modal.hide();
            loadAssets();
        }
    });

    postDepreciationBtn.addEventListener('click', async () => {
        const month = document.getElementById('depreciation-month').value;
        const year = document.getElementById('depreciation-year').value;
        if (confirm(`Anda yakin ingin memposting jurnal penyusutan untuk periode ${month}/${year}?`)) {
            const formData = new FormData();
            formData.append('action', 'post_depreciation');
            formData.append('month', month);
            formData.append('year', year);
            const response = await fetch(`${basePath}/api/aset_tetap`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status);
            if (result.status === 'success') {
                loadAssets();
            }
        }
    });

    printReportBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'aset-tetap', per_tanggal: new Date().toISOString().split('T')[0] };
        for (const key in params) {
            const hiddenField = document.createElement('input'); hiddenField.type = 'hidden'; hiddenField.name = key; hiddenField.value = params[key]; form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    document.getElementById('save-disposal-btn').addEventListener('click', async () => {
        const form = document.getElementById('disposal-form');
        if (!form.checkValidity()) {
            showToast('Harap isi semua field yang wajib.', 'error');
            return;
        }
        if (confirm('Anda yakin ingin memproses pelepasan aset ini? Aksi ini akan membuat jurnal permanen dan tidak dapat dibatalkan.')) {
            const formData = new FormData(form);
            const response = await fetch(`${basePath}/api/aset_tetap`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status);
            if (result.status === 'success') {
                disposalModal.hide();
                loadAssets();
            }
        }
    });

    document.getElementById('harga_jual').addEventListener('input', (e) => {
        const kasContainer = document.getElementById('disposal-kas-account-container');
        if (parseFloat(e.target.value) > 0) {
            kasContainer.style.display = 'block';
            document.getElementById('kas_account_id').required = true;
        } else {
            kasContainer.style.display = 'none';
            document.getElementById('kas_account_id').required = false;
        }
    });

    disposalModalEl.addEventListener('show.bs.modal', async (e) => {
        const kasSelect = document.getElementById('kas_account_id');
        const response = await fetch(`${basePath}/api/settings?action=get_cash_accounts`);
        const result = await response.json();
        kasSelect.innerHTML = result.data.map(acc => `<option value="${acc.id}">${acc.nama_akun}</option>`).join('');
    });

    tableBody.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-asset-btn');
        if (editBtn) {
            const response = await fetch(`${basePath}/api/aset_tetap?action=get_single&id=${editBtn.dataset.id}`);
            const result = await response.json();
            if (result.status === 'success') {
                const asset = result.data;
                form.reset();
                document.getElementById('assetModalLabel').textContent = 'Edit Aset Tetap';
                Object.keys(asset).forEach(key => {
                    const el = document.getElementById(key);
                    if (el) el.value = asset[key];
                });
                document.getElementById('asset-id').value = asset.id;
                modal.show();
            }
        }

        const disposeBtn = e.target.closest('.dispose-asset-btn');
        if (disposeBtn) {
            const form = document.getElementById('disposal-form');
            form.reset();
            document.getElementById('disposal-asset-id').value = disposeBtn.dataset.id;
            document.getElementById('disposal-asset-name').textContent = disposeBtn.dataset.nama;
            document.getElementById('tanggal_pelepasan').valueAsDate = new Date();
            // Sembunyikan field kas/bank secara default
            document.getElementById('disposal-kas-account-container').style.display = 'none';
            document.getElementById('kas_account_id').required = false;
            disposalModal.show();
        }
    });

    setupDepreciationFilters();
    loadAssets();
    loadAccountsForModal();
}

function initUsersPage() {
    const tableBody = document.getElementById('users-table-body');
    const modalEl = document.getElementById('userModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    const form = document.getElementById('user-form');
    const saveBtn = document.getElementById('save-user-btn');

    if (!tableBody) return;

    async function loadUsers() {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center p-5"><div class="spinner-border"></div></td></tr>';
        try {
            const response = await fetch(`${basePath}/api/users`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach((user, index) => {
                    const row = `
                        <tr>
                            <td>${user.username}</td>
                            <td>${user.nama_lengkap || '-'}</td>
                            <td><span class="badge bg-primary">${user.role}</span></td>
                            <td>${new Date(user.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' })}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-info edit-btn" data-id="${user.id}" title="Edit"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="${user.id}" data-username="${user.username}" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Tidak ada pengguna ditemukan.</td></tr>';
            }
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    saveBtn.addEventListener('click', async () => {
        const formData = new FormData(form);
        const originalBtnHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

        try {
            const response = await fetch(`${basePath}/api/users`, { method: 'POST', body: formData });
            const result = await response.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                modal.hide();
                loadUsers();
            }
        } catch (error) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
    });

    tableBody.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            const id = editBtn.dataset.id;
            const formData = new FormData();
            formData.append('action', 'get_single');
            formData.append('id', id);
            const response = await fetch(`${basePath}/api/users`, { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                document.getElementById('userModalLabel').textContent = 'Edit Pengguna';
                form.reset();
                const user = result.data;
                document.getElementById('user-id').value = user.id;
                document.getElementById('user-action').value = 'update';
                document.getElementById('username').value = user.username;
                document.getElementById('nama_lengkap').value = user.nama_lengkap;
                document.getElementById('role').value = user.role;
                document.getElementById('password').setAttribute('placeholder', 'Kosongkan jika tidak diubah');
                modal.show();
            }
        }

        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            const { id, username } = deleteBtn.dataset;
            if (confirm(`Yakin ingin menghapus pengguna "${username}"?`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                const response = await fetch(`${basePath}/api/users`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') loadUsers();
            }
        }
    });

    modalEl.addEventListener('show.bs.modal', (e) => {
        const button = e.relatedTarget;
        if (button && button.dataset.action === 'add') {
            document.getElementById('userModalLabel').textContent = 'Tambah Pengguna Baru';
            form.reset();
            document.getElementById('user-id').value = '';
            document.getElementById('user-action').value = 'add';
            document.getElementById('password').setAttribute('placeholder', '');
        }
    });

    loadUsers();
}

function initRekonsiliasiBankPage() {
    const akunFilter = document.getElementById('recon-akun-filter');
    const tanggalAkhirInput = document.getElementById('recon-tanggal-akhir');
    const saldoRekeningInput = document.getElementById('recon-saldo-rekening');
    const tampilkanBtn = document.getElementById('recon-tampilkan-btn');
    const reconciliationContent = document.getElementById('reconciliation-content');
    const appTransactionsBody = document.getElementById('app-transactions-body');
    const checkAllApp = document.getElementById('check-all-app');
    const saveBtn = document.getElementById('save-reconciliation-btn');

    if (!akunFilter) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 });
    let saldoBukuAwal = 0;

    async function loadCashAccounts() {
        try {
            const response = await fetch(`${basePath}/api/rekonsiliasi-bank?action=get_cash_accounts`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);
            akunFilter.innerHTML = '<option value="">-- Pilih Akun --</option>';
            result.data.forEach(acc => akunFilter.add(new Option(acc.nama_akun, acc.id)));
        } catch (error) {
            showToast(`Gagal memuat akun kas: ${error.message}`, 'error');
        }
    }

    function updateSummary() {
        const saldoBank = parseFloat(saldoRekeningInput.value) || 0;
        let clearedDebit = 0;
        let clearedKredit = 0;
        let unclearedDebit = 0;
        let unclearedKredit = 0;

        appTransactionsBody.querySelectorAll('tr').forEach(row => {
            const debit = parseFloat(row.dataset.debit) || 0;
            const kredit = parseFloat(row.dataset.kredit) || 0;
            const checkbox = row.querySelector('.recon-check');

            if (checkbox && checkbox.checked) {
                clearedDebit += debit;
                clearedKredit += kredit;
            } else {
                unclearedDebit += debit;
                unclearedKredit += kredit;
            }
        });

        const saldoBukuAkhir = saldoBukuAwal + (clearedDebit - clearedKredit) + (unclearedDebit - unclearedKredit);
        const saldoBukuDisesuaikan = saldoBukuAwal + (clearedDebit - clearedKredit);
        const selisih = saldoBukuDisesuaikan - saldoBank;

        document.getElementById('summary-saldo-buku').textContent = currencyFormatter.format(saldoBukuAkhir);
        document.getElementById('summary-saldo-bank').textContent = currencyFormatter.format(saldoBank);
        document.getElementById('summary-cleared').textContent = currencyFormatter.format(clearedDebit - clearedKredit);
        document.getElementById('summary-selisih').textContent = currencyFormatter.format(selisih);

        // Aktifkan tombol simpan jika selisihnya nol (atau sangat kecil)
        saveBtn.disabled = Math.abs(selisih) > 0.01;
    }

    async function startReconciliation() {
        const accountId = akunFilter.value;
        const endDate = tanggalAkhirInput.value;

        if (!accountId || !endDate || !saldoRekeningInput.value) {
            showToast('Harap pilih akun, tanggal akhir, dan isi saldo rekening koran.', 'error');
            return;
        }

        reconciliationContent.classList.remove('d-none');
        appTransactionsBody.innerHTML = `<tr><td colspan="5" class="text-center p-4"><div class="spinner-border"></div></td></tr>`;

        try {
            const response = await fetch(`${basePath}/api/rekonsiliasi-bank?action=get_transactions&account_id=${accountId}&end_date=${endDate}`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            saldoBukuAwal = result.saldo_buku_awal;
            appTransactionsBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(tx => {
                    const row = `
                        <tr data-debit="${tx.debit}" data-kredit="${tx.kredit}">
                            <td><input class="form-check-input recon-check" type="checkbox" value="${tx.id}"></td>
                            <td>${new Date(tx.tanggal).toLocaleDateString('id-ID')}</td>
                            <td>${tx.keterangan}</td>
                            <td class="text-end">${tx.debit > 0 ? currencyFormatter.format(tx.debit) : '-'}</td>
                            <td class="text-end">${tx.kredit > 0 ? currencyFormatter.format(tx.kredit) : '-'}</td>
                        </tr>
                    `;
                    appTransactionsBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                appTransactionsBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Tidak ada transaksi untuk direkonsiliasi.</td></tr>`;
            }
            updateSummary();

        } catch (error) {
            showToast(`Gagal memuat transaksi: ${error.message}`, 'error');
            appTransactionsBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${error.message}</td></tr>`;
        }
    }

    tampilkanBtn.addEventListener('click', startReconciliation);
    saldoRekeningInput.addEventListener('input', updateSummary);

    appTransactionsBody.addEventListener('change', e => {
        if (e.target.classList.contains('recon-check')) {
            updateSummary();
        }
    });

    checkAllApp.addEventListener('change', () => {
        appTransactionsBody.querySelectorAll('.recon-check').forEach(chk => {
            chk.checked = checkAllApp.checked;
        });
        updateSummary();
    });

    saveBtn.addEventListener('click', async () => {
        const clearedIds = Array.from(appTransactionsBody.querySelectorAll('.recon-check:checked')).map(chk => chk.value);
        if (clearedIds.length === 0) {
            showToast('Tidak ada transaksi yang dipilih untuk direkonsiliasi.', 'info');
            return;
        }

        if (!confirm(`Anda yakin ingin menyimpan rekonsiliasi untuk ${clearedIds.length} transaksi?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('account_id', akunFilter.value);
        formData.append('reconciliation_date', tanggalAkhirInput.value);
        clearedIds.forEach(id => formData.append('cleared_ids[]', id));

        const response = await fetch(`${basePath}/api/rekonsiliasi-bank`, { method: 'POST', body: formData });
        const result = await response.json();
        showToast(result.message, result.status);
        if (result.status === 'success') {
            reconciliationContent.classList.add('d-none');
            appTransactionsBody.innerHTML = '';
        }
    });

    // Initial Load
    tanggalAkhirInput.valueAsDate = new Date();
    loadCashAccounts();
}

function initHistoriRekonsiliasiPage() {
    const tableBody = document.getElementById('history-recon-table-body');
    if (!tableBody) return;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    async function loadHistory() {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;
        try {
            const response = await fetch(`${basePath}/api/histori-rekonsiliasi`);
            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                result.data.forEach(item => {
                    const row = `
                        <tr>
                            <td>RECON-${String(item.id).padStart(5, '0')}</td>
                            <td>${item.nama_akun}</td>
                            <td>${new Date(item.statement_date).toLocaleDateString('id-ID', { dateStyle: 'long' })}</td>
                            <td class="text-end">${currencyFormatter.format(item.statement_balance)}</td>
                            <td>${new Date(item.created_at).toLocaleString('id-ID')}</td>
                            <td class="text-end">
                                <a href="#" class="btn btn-sm btn-danger print-recon-btn" data-id="${item.id}" title="Cetak PDF">
                                    <i class="bi bi-file-earmark-pdf-fill"></i>                                </a>
                                <a href="#" class="btn btn-sm btn-warning reverse-recon-btn" data-id="${item.id}" title="Batalkan Rekonsiliasi">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada histori rekonsiliasi.</td></tr>';
            }
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Gagal memuat histori: ${error.message}</td></tr>`;
        }
    }

    tableBody.addEventListener('click', async (e) => {
        const printBtn = e.target.closest('.print-recon-btn');
        if (printBtn) {
            e.preventDefault();
            const reconId = printBtn.dataset.id;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${basePath}/api/pdf`;
            form.target = '_blank';

            const params = {
                report: 'rekonsiliasi',
                id: reconId
            };

            for (const key in params) {
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = key;
                hiddenField.value = params[key];
                form.appendChild(hiddenField);
            }
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        const reverseBtn = e.target.closest('.reverse-recon-btn');
        if (reverseBtn) {
            e.preventDefault();
            const reconId = reverseBtn.dataset.id;
            if (confirm(`Anda yakin ingin membatalkan rekonsiliasi RECON-${String(reconId).padStart(5, '0')}? \n\nTransaksi yang terkait akan dikembalikan ke status "belum direkonsiliasi".`)) {
                const formData = new FormData();
                formData.append('action', 'reverse');
                formData.append('id', reconId);

                try {
                    const response = await fetch(`${basePath}/api/histori-rekonsiliasi`, { method: 'POST', body: formData });
                    const result = await response.json();
                    showToast(result.message, result.status);
                    if (result.status === 'success') {
                        loadHistory(); // Muat ulang daftar histori
                    }
                } catch (error) {
                    showToast(`Terjadi kesalahan: ${error.message}`, 'error');
                }
            }
        }
    });
    loadHistory();
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
    const exportPdfBtn = document.getElementById('export-bb-pdf');
    const exportCsvBtn = document.getElementById('export-bb-csv');

    if (!akunFilter) return;

    // Set default dates to today
    const today = new Date().toISOString().split('T')[0];
    tglMulai.value = today;
    tglAkhir.value = today;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 });

    async function loadAccounts() {
        try {
            const response = await fetch(`${basePath}/api/coa`); // Use the existing coa handler
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
            const response = await fetch(`${basePath}/api/buku-besar-data?${params.toString()}`);
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
            const saldoNormal = account_info.saldo_normal;

            transactions.forEach(tx => {
                const debit = parseFloat(tx.debit);
                const kredit = parseFloat(tx.kredit);
                if (saldoNormal === 'Debit') {
                    saldoBerjalan += debit - kredit;
                } else { // Kredit
                    saldoBerjalan += kredit - debit;
                }
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

    exportPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        if (!akunFilter.value) { showToast('Pilih akun terlebih dahulu.', 'error'); return; }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'buku-besar', account_id: akunFilter.value, start_date: tglMulai.value, end_date: tglAkhir.value };
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        if (!akunFilter.value) { showToast('Pilih akun terlebih dahulu.', 'error'); return; }
        const url = `${basePath}/api/csv?report=buku-besar&account_id=${akunFilter.value}&start_date=${tglMulai.value}&end_date=${tglAkhir.value}`;
        window.open(url, '_blank');
    });

    loadAccounts();
}

function initEntriJurnalPage() {
    const form = document.getElementById('entri-jurnal-form');
    const linesBody = document.getElementById('jurnal-lines-body');
    const addLineBtn = document.getElementById('add-jurnal-line-btn');
    const saveAsRecurringBtn = document.getElementById('save-as-recurring-btn');


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

    saveAsRecurringBtn.addEventListener('click', () => {
        // Validasi form jurnal dulu
        const keterangan = document.getElementById('jurnal-keterangan').value;
        if (!keterangan) {
            showToast('Keterangan jurnal wajib diisi sebelum membuat template.', 'error');
            return;
        }

        // Kumpulkan data baris jurnal
        const lines = [];
        linesBody.querySelectorAll('tr').forEach(row => {
            const account_id = row.querySelector('select').value;
            const debit = parseFloat(row.querySelector('.debit-input').value) || 0;
            const kredit = parseFloat(row.querySelector('.kredit-input').value) || 0;
            if (account_id && (debit > 0 || kredit > 0)) {
                lines.push({ account_id, debit, kredit });
            }
        });

        if (lines.length < 2) {
            showToast('Template harus memiliki minimal 2 baris jurnal.', 'error');
            return;
        }

        const templateData = { keterangan, lines };

        // Buka modal recurring
        openRecurringModal('jurnal', templateData);
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
        // Pastikan elemen ada sebelum diakses
        if (document.getElementById('jurnal-tanggal')) {
            if (editId) {
                loadJournalForEdit(editId);
            } else {
                document.getElementById('jurnal-tanggal').valueAsDate = new Date();
                addJurnalLine(); addJurnalLine();
            }
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
    const exportPdfBtn = document.getElementById('export-dj-pdf');
    const exportCsvBtn = document.getElementById('export-dj-csv');
    const viewModalEl = document.getElementById('viewJurnalModal');

    if (!tableBody) return;
    let periodLockDate = null;

    const currencyFormatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    async function loadJurnal(page = 1) {
        const params = new URLSearchParams({
            page,
            limit: limitSelect.value,
            search: searchInput.value,
            start_date: startDateFilter.value,
            end_date: endDateFilter.value,
        });

        tableBody.innerHTML = `<tr><td colspan="8" class="text-center p-5"><div class="spinner-border"></div></td></tr>`;
        try {
            const [jurnalRes, settingsRes] = await Promise.all([
                fetch(`${basePath}/api/entri-jurnal?${params.toString()}`),
                fetch(`${basePath}/api/settings`)
            ]);
            const result = await jurnalRes.json();
            const settingsResult = await settingsRes.json();

            if (settingsResult.status === 'success' && settingsResult.data.period_lock_date) {
                periodLockDate = new Date(settingsResult.data.period_lock_date);
            }
            if (result.status !== 'success') throw new Error(result.message);

            tableBody.innerHTML = '';
            if (result.data.length > 0) {
                let lastRef = null;
                result.data.forEach((line, index) => {
                    const isFirstRowOfGroup = line.ref !== lastRef;
                    const borderTopClass = isFirstRowOfGroup && index > 0 ? 'border-top-heavy' : '';

                    // Info Audit (Created/Updated)
                    const createdAt = new Date(line.created_at);
                    const updatedAt = new Date(line.updated_at);
                    const createdBy = line.created_by_name || 'sistem';
                    const updatedBy = line.updated_by_name || 'sistem';
                    
                    let auditInfo = `Dibuat: ${createdBy} pada ${createdAt.toLocaleString('id-ID')}`;
                    let auditIcon = '<i class="bi bi-info-circle"></i>';

                    if (updatedBy && updatedAt.getTime() > createdAt.getTime() + 1000) { // Cek jika ada update signifikan
                        auditInfo += `\nDiperbarui: ${updatedBy} pada ${updatedAt.toLocaleString('id-ID')}`;
                        auditIcon = '<i class="bi bi-info-circle-fill text-primary"></i>';
                    }

                    let editBtn, deleteBtn;
                    if (line.source === 'jurnal') {
                        editBtn = `<a href="${basePath}/entri-jurnal?edit_id=${line.entry_id}" class="btn btn-sm btn-warning edit-jurnal-btn" title="Edit"><i class="bi bi-pencil-fill"></i></a>`;
                        deleteBtn = `<button class="btn btn-sm btn-danger delete-jurnal-btn" data-id="${line.entry_id}" data-keterangan="${line.keterangan}" title="Hapus"><i class="bi bi-trash-fill"></i></button>`;
                    } else { // transaksi, hanya bisa dihapus dari sini, edit di halaman transaksi
                        editBtn = `<a href="${basePath}/transaksi#tx-${line.entry_id}" class="btn btn-sm btn-secondary" title="Lihat & Edit di Halaman Transaksi"><i class="bi bi-box-arrow-up-right"></i></a>`;
                        deleteBtn = `<button class="btn btn-sm btn-danger delete-transaksi-btn" data-id="${line.entry_id}" data-keterangan="${line.keterangan}" title="Hapus Transaksi"><i class="bi bi-trash-fill"></i></button>`;
                    }

                    const row = `
                        <tr class="${borderTopClass}">
                            <td>${isFirstRowOfGroup ? line.ref : ''}</td>
                            <td>${isFirstRowOfGroup ? new Date(line.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : ''}</td>
                            <td>${isFirstRowOfGroup ? line.keterangan : ''}</td>
                            <td class="${line.debit > 0 ? '' : 'ps-4'}">${line.nama_akun || '-'}</td>
                            <td class="text-end">${line.debit > 0 ? currencyFormatter.format(line.debit) : ''}</td>
                            <td class="text-end">${line.kredit > 0 ? currencyFormatter.format(line.kredit) : ''}</td>
                            <td>${isFirstRowOfGroup ? `<span data-bs-toggle="tooltip" data-bs-placement="top" title="${auditInfo}">${auditIcon}</span>` : ''}</td>
                            <td class="text-end align-middle">
                                ${isFirstRowOfGroup ? `
                                    <div class="btn-group">
                                        ${editBtn}
                                        ${deleteBtn}
                                    </div>
                                ` : ''}
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                    lastRef = line.ref;
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center">Tidak ada entri jurnal ditemukan.</td></tr>';
            }
            renderPagination(paginationContainer, result.pagination, loadJurnal);
            // Inisialisasi ulang tooltip setelah data baru dimuat
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Gagal memuat data: ${error.message}</td></tr>`;
        }
    }

    tableBody.addEventListener('click', async (e) => {
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

        const editTransaksiBtn = e.target.closest('.edit-transaksi-btn');
        if (editTransaksiBtn) {
            const id = editTransaksiBtn.dataset.id;
            // Navigasi ke halaman transaksi dan buka modal edit
            navigate(`${basePath}/transaksi#edit-${id}`);
        }

        const deleteTransaksiBtn = e.target.closest('.delete-transaksi-btn');
        if (deleteTransaksiBtn) {
            const { id, keterangan } = deleteTransaksiBtn.dataset;
            if (confirm(`Yakin ingin menghapus transaksi "${keterangan}"? Aksi ini juga akan menghapus entri jurnal terkait.`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                try {
                    const response = await fetch(`${basePath}/api/transaksi`, { method: 'POST', body: formData });
                    const result = await response.json();
                    showToast(result.message, result.status === 'success' ? 'success' : 'error');
                    if (result.status === 'success') loadJurnal(1);
                } catch (error) {
                    showToast('Gagal menghapus transaksi.', 'error');
                }
            }
        }
    });

    // --- Export Listeners ---
    exportPdfBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/api/pdf`;
        form.target = '_blank';
        const params = { report: 'daftar-jurnal', search: searchInput.value, start_date: startDateFilter.value, end_date: endDateFilter.value };
        for (const key in params) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = params[key];
            form.appendChild(hiddenField);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    exportCsvBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const params = new URLSearchParams({ report: 'daftar-jurnal', format: 'csv', search: searchInput.value, start_date: startDateFilter.value, end_date: endDateFilter.value });
        const url = `${basePath}/api/csv?${params.toString()}`;
        window.open(url, '_blank');
    });

    let debounceTimer;
    const combinedFilterHandler = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadJurnal(1), 300);
        // Simpan semua filter ke localStorage
        localStorage.setItem('daftar_jurnal_limit', limitSelect.value);
        localStorage.setItem('daftar_jurnal_start_date', startDateFilter.value);
        localStorage.setItem('daftar_jurnal_end_date', endDateFilter.value);
    };
    [searchInput, startDateFilter, endDateFilter, limitSelect].forEach(el => el.addEventListener('change', combinedFilterHandler));
    searchInput.addEventListener('input', combinedFilterHandler);

    // Muat filter yang tersimpan dari localStorage sebelum memuat data awal
    const savedLimit = localStorage.getItem('daftar_jurnal_limit');
    if (savedLimit) {
        limitSelect.value = savedLimit;
    }

    const savedStartDate = localStorage.getItem('daftar_jurnal_start_date');
    const savedEndDate = localStorage.getItem('daftar_jurnal_end_date');

    if (savedStartDate && savedEndDate) {
        startDateFilter.value = savedStartDate;
        endDateFilter.value = savedEndDate;
    } else {
        // Atur tanggal default ke bulan ini jika tidak ada yang tersimpan
        const now = new Date();
        startDateFilter.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        endDateFilter.value = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
    }

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
    const konsinyasiSettingsContainer = document.getElementById('konsinyasi-settings-container');
    const saveKonsinyasiSettingsBtn = document.getElementById('save-konsinyasi-settings-btn');
    const accountingSettingsContainer = document.getElementById('accounting-settings-container');
    const saveAccountingSettingsBtn = document.getElementById('save-accounting-settings-btn');
    if (!generalSettingsContainer) return;

    async function loadSettings() {
        try {
            const response = await fetch(`${basePath}/api/settings`);
            const result = await response.json();

            if (result.status === 'success') {
                const settings = result.data;
                generalSettingsContainer.innerHTML = `
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="app_name" class="form-label">Nama Aplikasi</label>
                                <input type="text" class="form-control" id="app_name" name="app_name" value="${settings.app_name || ''}">
                            </div>
                            <div class="mb-3">
                                <label for="app_logo" class="form-label">Logo Aplikasi (PNG/JPG, maks 1MB)</label>
                                <input class="form-control" type="file" id="app_logo" name="app_logo" accept="image/png, image/jpeg">
                            </div>
                            <hr>
                            <h5 class="mb-3">Pengaturan Warna Halaman Login</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="login_bg_color" class="form-label">Warna Latar Samping</label>
                                    <input type="color" class="form-control form-control-color" id="login_bg_color" name="login_bg_color" value="${settings.login_bg_color || '#075E54'}" title="Pilih warna">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="login_btn_color" class="form-label">Warna Tombol Login</label>
                                    <input type="color" class="form-control form-control-color" id="login_btn_color" name="login_btn_color" value="${settings.login_btn_color || '#25D366'}" title="Pilih warna">
                                </div>
                            </div>

                            <hr>
                            <h5 class="mb-3">Pengaturan Header Laporan PDF</h5>
                            <div class="mb-3">
                                <label for="pdf_header_line1" class="form-label">Header Baris 1</label>
                                <input type="text" class="form-control" id="pdf_header_line1" name="pdf_header_line1" value="${settings.pdf_header_line1 || ''}" placeholder="cth: NAMA PENGURUS">
                            </div>
                            <div class="mb-3">
                                <label for="pdf_header_line2" class="form-label">Header Baris 2 (Nama Perusahaan)</label>
                                <input type="text" class="form-control" id="pdf_header_line2" name="pdf_header_line2" value="${settings.pdf_header_line2 || ''}" placeholder="cth: NAMA PERUSAHAAN ANDA">
                            </div>
                            <div class="mb-3">
                                <label for="pdf_header_line3" class="form-label">Header Baris 3 (Alamat)</label>
                                <input type="text" class="form-control" id="pdf_header_line3" name="pdf_header_line3" value="${settings.pdf_header_line3 || ''}" placeholder="cth: Alamat Sekretariat RT Anda">
                            </div>
                            <hr>
                            <h5 class="mb-3">Pengaturan Tanda Tangan Laporan</h5>
                             <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="signature_ketua_name" class="form-label">Nama Penanda Tangan 1 (Kanan)</label>
                                    <input type="text" class="form-control" id="signature_ketua_name" name="signature_ketua_name" value="${settings.signature_ketua_name || ''}" placeholder="cth: John Doe">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="signature_bendahara_name" class="form-label">Nama Penanda Tangan 2 (Kiri)</label>
                                    <input type="text" class="form-control" id="signature_bendahara_name" name="signature_bendahara_name" value="${settings.signature_bendahara_name || ''}" placeholder="cth: Jane Doe">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="app_city" class="form-label">Kota Laporan</label>
                                    <input type="text" class="form-control" id="app_city" name="app_city" value="${settings.app_city || ''}" placeholder="cth: Jakarta">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="stamp_image" class="form-label">Gambar Stempel (PNG Transparan)</label>
                                    <input class="form-control" type="file" id="stamp_image" name="stamp_image" accept="image/png">
                                    ${settings.stamp_image_exists ? `<div class="form-text">Stempel saat ini: <a href="${basePath}/${settings.stamp_image}" target="_blank">Lihat</a></div>` : ''}
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="signature_image" class="form-label">Gambar Tanda Tangan (PNG Transparan)</label>
                                    <input class="form-control" type="file" id="signature_image" name="signature_image" accept="image/png">
                                    ${settings.signature_image_exists ? `<div class="form-text">Tanda tangan saat ini: <a href="${basePath}/${settings.signature_image}" target="_blank">Lihat</a></div>` : ''}
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="letterhead_image" class="form-label">Gambar Kop Surat (PNG/JPG)</label>
                                    <input class="form-control" type="file" id="letterhead_image" name="letterhead_image" accept="image/png, image/jpeg">
                                    ${settings.letterhead_image_exists ? `<div class="form-text">Kop surat saat ini: <a href="${basePath}/${settings.letterhead_image}" target="_blank">Lihat</a></div>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <label class="form-label">Preview Logo</label>
                            <img id="logo-preview" src="${settings.app_logo ? basePath + '/' + settings.app_logo + '?t=' + new Date().getTime() : 'https://via.placeholder.com/150x50?text=Logo'}" class="img-thumbnail" alt="Logo Preview" style="max-height: 80px;">
                        </div>
                    </div>
                `;

                // Event listener untuk preview logo
                const logoInput = document.getElementById('app_logo');
                const logoPreview = document.getElementById('logo-preview');
                if (logoInput && logoPreview) {
                    logoInput.addEventListener('change', function() {
                        const file = this.files[0];
                        if (file) logoPreview.src = URL.createObjectURL(file);
                    });
                }
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

    async function loadKonsinyasiSettings() {
        if (!konsinyasiSettingsContainer) return;
        try {
            const [settingsRes, accountsRes] = await Promise.all([
                fetch(`${basePath}/api/settings`),
                fetch(`${basePath}/api/settings?action=get_accounts_for_consignment`)
            ]);
            const settingsResult = await settingsRes.json();
            const accountsResult = await accountsRes.json(); // This contains {kas, pendapatan, beban, liabilitas, persediaan}

            if (settingsResult.status !== 'success' || accountsResult.status !== 'success') {
                throw new Error(settingsResult.message || accountsResult.message);
            }

            const settings = settingsResult.data;
            const { kas = [], pendapatan = [], beban = [], liabilitas = [], persediaan = [] } = accountsResult.data;

            const createOptions = (accounts) => (accounts || []).map(acc => `<option value="${acc.id}">${acc.nama_akun}</option>`).join('');

            konsinyasiSettingsContainer.innerHTML = `
                <div class="mb-3">
                    <label for="consignment_cash_account" class="form-label">Akun Kas (Penerimaan Penjualan)</label>
                    <select class="form-select" id="consignment_cash_account" name="consignment_cash_account">${createOptions(kas)}</select>
                </div>
                <div class="mb-3">
                    <label for="consignment_revenue_account" class="form-label">Akun Pendapatan Konsinyasi</label>
                    <select class="form-select" id="consignment_revenue_account" name="consignment_revenue_account">${createOptions(pendapatan)}</select>
                </div>
                <div class="mb-3">
                    <label for="consignment_cogs_account" class="form-label">Akun HPP Konsinyasi</label>
                    <select class="form-select" id="consignment_cogs_account" name="consignment_cogs_account">${createOptions(beban)}</select>
                </div>
                <div class="mb-3">
                    <label for="consignment_payable_account" class="form-label">Akun Utang Konsinyasi</label>
                    <select class="form-select" id="consignment_payable_account" name="consignment_payable_account">${createOptions(liabilitas)}</select>
                </div>
                <div class="mb-3">
                    <label for="consignment_inventory_account" class="form-label">Akun Persediaan Konsinyasi (Aset)</label>
                    <select class="form-select" id="consignment_inventory_account" name="consignment_inventory_account">${createOptions(persediaan)}</select>
                </div>
            `;

            // Set selected values
            if (settings.consignment_cash_account) document.getElementById('consignment_cash_account').value = settings.consignment_cash_account;
            if (settings.consignment_revenue_account) document.getElementById('consignment_revenue_account').value = settings.consignment_revenue_account;
            if (settings.consignment_cogs_account) document.getElementById('consignment_cogs_account').value = settings.consignment_cogs_account;
            if (settings.consignment_payable_account) document.getElementById('consignment_payable_account').value = settings.consignment_payable_account;
            if (settings.consignment_inventory_account) document.getElementById('consignment_inventory_account').value = settings.consignment_inventory_account;
        } catch (error) {
            konsinyasiSettingsContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat pengaturan konsinyasi: ${error.message}</div>`;
        }
    }

    async function loadAccountingSettings() {
        if (!accountingSettingsContainer) return;
        try {
            const [settingsRes, equityAccRes] = await Promise.all([
                fetch(`${basePath}/api/settings`),
                fetch(`${basePath}/api/settings?action=get_equity_accounts`)
            ]);
            const settingsResult = await settingsRes.json();
            const equityAccResult = await equityAccRes.json();

            if (settingsResult.status !== 'success' || equityAccResult.status !== 'success') {
                throw new Error(settingsResult.message || equityAccResult.message);
            }

            const settings = settingsResult.data;
            const equityAccounts = equityAccResult.data;

            let equityOptions = equityAccounts.map(acc => `<option value="${acc.id}">${acc.kode_akun} - ${acc.nama_akun}</option>`).join('');

            accountingSettingsContainer.innerHTML = `
                <h5 class="mb-3">Pengaturan Jurnal Penutup</h5>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="retained_earnings_account_id" class="form-label">Akun Laba Ditahan (Retained Earnings)</label>
                        <select class="form-select" id="retained_earnings_account_id" name="retained_earnings_account_id">
                            <option value="">-- Pilih Akun Ekuitas --</option>
                            ${equityOptions}
                        </select>
                        <div class="form-text">Akun ini akan digunakan untuk menampung laba/rugi bersih saat proses tutup buku.</div>
                    </div>
                </div>
            `;
            // Set selected value
            if (settings.retained_earnings_account_id) {
                document.getElementById('retained_earnings_account_id').value = settings.retained_earnings_account_id;
            }

        } catch (error) {
            accountingSettingsContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat pengaturan akuntansi: ${error.message}</div>`;
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

    if (saveKonsinyasiSettingsBtn) {
        saveKonsinyasiSettingsBtn.addEventListener('click', async () => {
            const form = document.getElementById('konsinyasi-settings-form');
            const formData = new FormData(form);
            const originalBtnHtml = saveKonsinyasiSettingsBtn.innerHTML;
            saveKonsinyasiSettingsBtn.disabled = true;
            saveKonsinyasiSettingsBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

            try {
                const response = await fetch(`${basePath}/api/settings`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
            } catch (error) {
                showToast('Terjadi kesalahan jaringan.', 'error');
            } finally {
                saveKonsinyasiSettingsBtn.disabled = false;
                saveKonsinyasiSettingsBtn.innerHTML = originalBtnHtml;
            }
        });
    }

    if (saveAccountingSettingsBtn) {
        saveAccountingSettingsBtn.addEventListener('click', async () => {
            const form = document.getElementById('accounting-settings-form');
            const formData = new FormData(form);
            const originalBtnHtml = saveAccountingSettingsBtn.innerHTML;
            saveAccountingSettingsBtn.disabled = true;
            saveAccountingSettingsBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Menyimpan...`;

            try {
                const response = await fetch(`${basePath}/api/settings`, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.status === 'success' ? 'success' : 'error');
                if (result.status === 'success') {
                    loadAccountingSettings();
                }
            } catch (error) {
                showToast('Terjadi kesalahan jaringan.', 'error');
            } finally {
                saveAccountingSettingsBtn.disabled = false;
                saveAccountingSettingsBtn.innerHTML = originalBtnHtml;
            }
        });
    }

    loadSettings();
    loadTransaksiSettings();
    loadArusKasSettings();
    loadKonsinyasiSettings();
    loadAccountingSettings();
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

// --- Global Theme Color Picker Logic ---
function applyThemeColor(color) {
    if (!color) return;
    // Update CSS variables for the entire document
    document.documentElement.style.setProperty('--cf-blue', color);
    // Also update the Bootstrap variable for primary button background
    document.documentElement.style.setProperty('--bs-btn-bg', color);
    document.documentElement.style.setProperty('--bs-btn-border-color', color);
}

document.addEventListener('DOMContentLoaded', function() {
    const savedColor = localStorage.getItem('theme_color');
    const colorPicker = document.getElementById('theme-color-picker');

    if (savedColor) {
        applyThemeColor(savedColor);
        if (colorPicker) {
            colorPicker.value = savedColor;
        }
    }

    if (colorPicker) {
        colorPicker.addEventListener('input', (e) => {
            applyThemeColor(e.target.value);
            localStorage.setItem('theme_color', e.target.value);
        });
    }
});
// --- Recurring Modal Logic (Global) ---
const recurringModalEl = document.getElementById('recurringModal');
const recurringModal = recurringModalEl ? new bootstrap.Modal(recurringModalEl) : null;
const recurringForm = document.getElementById('recurring-form');

function openRecurringModal(type, data, existingTemplate = null) {
    if (!recurringModal || !recurringForm) return;

    recurringForm.reset();
    document.getElementById('recurring-template-type').value = type;
    document.getElementById('recurring-template-data').value = JSON.stringify(data);

    if (existingTemplate) {
        document.getElementById('recurringModalLabel').textContent = 'Edit Jadwal Berulang';
        document.getElementById('recurring-id').value = existingTemplate.id;
        document.getElementById('recurring-name').value = existingTemplate.name;
        document.getElementById('recurring-frequency-interval').value = existingTemplate.frequency_interval;
        document.getElementById('recurring-frequency-unit').value = existingTemplate.frequency_unit;
        document.getElementById('recurring-start-date').value = existingTemplate.start_date;
        document.getElementById('recurring-end-date').value = existingTemplate.end_date || '';
    } else {
        document.getElementById('recurringModalLabel').textContent = 'Atur Jadwal Berulang';
        document.getElementById('recurring-id').value = '';
        document.getElementById('recurring-start-date').valueAsDate = new Date();
    }

    recurringModal.show();
}

document.getElementById('save-recurring-template-btn')?.addEventListener('click', async () => {
    const response = await fetch(`${basePath}/api/recurring`, { method: 'POST', body: new FormData(recurringForm) });
    const result = await response.json();
    showToast(result.message, result.status);
    if (result.status === 'success') recurringModal.hide();
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
    const searchModal = bootstrap.Modal.getInstance(searchModalEl) || new bootstrap.Modal(searchModalEl);

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

    resultsContainer.addEventListener('click', (e) => {
        const link = e.target.closest('.search-result-item');
        if (link) {
            e.preventDefault();
            const url = link.href;
            // Tutup modal secara manual
            searchModal.hide();
            // Gunakan fungsi navigate SPA untuk pindah halaman dan menangani hash
            navigate(url);
        }
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