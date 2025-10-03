<?php
$is_spa_request = isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true';
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/header.php';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-pie-chart-fill"></i> Analisis Rasio Keuangan</h1>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="ra-tanggal-akhir" class="form-label">Analisis per Tanggal</label>
                <input type="date" id="ra-tanggal-akhir" class="form-control">
            </div>
            <div class="col-md-5">
                <label for="ra-tanggal-pembanding" class="form-label">Bandingkan dengan Tanggal (Opsional)</label>
                <input type="date" id="ra-tanggal-pembanding" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" id="ra-tampilkan-btn">
                    <i class="bi bi-search"></i> Analisis
                </button>
            </div>
        </div>
    </div>
</div>

<div id="ratio-analysis-content">
    <div class="alert alert-info text-center">
        Silakan pilih tanggal analisis, lalu klik "Analisis".
    </div>
</div>

<!-- Template untuk card rasio -->
<template id="ratio-card-template">
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title d-flex justify-content-between">
                    <span class="ratio-name"></span>
                    <i class="bi bi-info-circle-fill text-muted" data-bs-toggle="tooltip" title=""></i>
                </h5>
                <h2 class="card-text ratio-value fw-bold"></h2>
                <p class="card-text ratio-comparison text-muted small"></p>
                <p class="card-text ratio-formula text-muted fst-italic small"></p>
            </div>
            <div class="card-footer ratio-interpretation small">
            </div>
        </div>
    </div>
</template>


<?php
if (!$is_spa_request) {
    require_once PROJECT_ROOT . '/views/footer.php';
}
?>