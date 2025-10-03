    </main> <!-- end main-content -->

    <footer class="footer-fixed">
        <p class="mb-0 text-center text-muted">&copy; <?= date('Y') ?> Aplikasi Keuangan</p>
    </footer>

</div> <!-- end content-wrapper -->

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" style="z-index: 1100">
    <!-- Toasts will be appended here by JavaScript -->
</div>

<!-- Generic Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Detail Transaksi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="detailModalBody">
        <!-- Content will be injected here by JavaScript -->
        <div class="text-center p-5"><div class="spinner-border"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal untuk Jadwal Berulang -->
<div class="modal fade" id="recurringModal" tabindex="-1" aria-labelledby="recurringModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="recurringModalLabel">Atur Jadwal Berulang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="recurring-form">
            <input type="hidden" id="recurring-id" name="id">
            <input type="hidden" id="recurring-template-type" name="template_type">
            <input type="hidden" id="recurring-template-data" name="template_data">
            <input type="hidden" name="action" value="save_template">

            <div class="mb-3">
                <label for="recurring-name" class="form-label">Nama Template</label>
                <input type="text" class="form-control" id="recurring-name" name="name" placeholder="cth: Bayar Sewa Bulanan" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Jadwalkan Setiap</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="recurring-frequency-interval" name="frequency_interval" value="1" min="1">
                    <select class="form-select" id="recurring-frequency-unit" name="frequency_unit">
                        <option value="day">Hari</option>
                        <option value="week">Minggu</option>
                        <option value="month" selected>Bulan</option>
                        <option value="year">Tahun</option>
                    </select>
                </div>
            </div>
            <div class="mb-3"><label for="recurring-start-date" class="form-label">Mulai Tanggal</label><input type="date" class="form-control" id="recurring-start-date" name="start_date" required></div>
            <div class="mb-3"><label for="recurring-end-date" class="form-label">Berakhir Tanggal (Opsional)</label><input type="date" class="form-control" id="recurring-end-date" name="end_date"></div>
        </form>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="save-recurring-template-btn">Simpan Template</button></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<!-- Ganti main.js dengan rt_main.js untuk aplikasi RT -->
<script src="<?= base_url('assets/js/main.js') ?>"></script>
</body>
</html> 