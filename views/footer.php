    </main> <!-- end main-content -->

    <footer class="footer-fixed">
        <p class="mb-0 text-center text-muted">&copy; <?= date('Y') ?> Aplikasi Keuangan</p>
    </footer>

</div> <!-- end content-wrapper -->

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" style="z-index: 1100">
    <!-- Toasts will be appended here by JavaScript -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<!-- Ganti main.js dengan rt_main.js untuk aplikasi RT -->
<script src="<?= base_url('assets/js/main.js') ?>"></script>
</body>
</html> 