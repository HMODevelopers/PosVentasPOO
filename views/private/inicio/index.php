<?php
$titulo = "Inicio";
$modulo = "Panel Principal";
$subtitulo = "";
session_start();

require_once __DIR__ . '/../../../includes/config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/views/public/index.php');
    exit();
}
$hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Inicio | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Dashboard principal" name="description" />
    <meta content="Coderthemes" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

    <!-- App css -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <!-- ApexCharts -->
    <script src="<?= BASE_URL ?>/assets/libs/apexcharts/apexcharts.min.js"></script>

    <style>
        .kpi-card .card-body { padding: 1.25rem 1.25rem; }
        .kpi-titulo { font-size: .85rem; letter-spacing: .06em; font-weight: 600; color:#9aa0ac; text-transform: uppercase; margin-bottom:.25rem; }
        .kpi-valor  { font-size: 2rem; font-weight: 700; line-height: 1.1; }
        .kpi-sub    { font-size: .8rem; color:#9aa0ac; margin-top:.25rem; }
        @media (min-width: 1400px) { .kpi-valor { font-size: 2.2rem; } }
        .filter-label{ font-weight:600; margin-bottom:.25rem; }
        /* colores sutiles por tipo */
        .bg-soft-primary   { background: rgba(64,153,255,.08); }
        .bg-soft-success   { background: rgba(16,183,89,.08); }
        .bg-soft-warning   { background: rgba(247,184,75,.12); }
        .bg-soft-info      { background: rgba(29,233,182,.10); }
        .bg-soft-danger    { background: rgba(250,92,124,.10); }
        .bg-soft-secondary { background: rgba(108,117,125,.10); }
        .kpi-card .icon { font-size: 20px; opacity:.9; }

        /* Charts */
        #chart-tendencia, #chart-top-prod, #chart-top-prov { min-height: 320px; }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

    <div class="wrapper">
        <div class="container-fluid">

            <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

            <!-- Filtro de fecha (100% ancho) -->
            <div class="card mb-3">
                <div class="card-body">
                    <label for="FiltroFecha" class="filter-label">Fecha:</label>
                    <input type="date" id="FiltroFecha" class="form-control" value="<?= htmlspecialchars($hoy) ?>">
                </div>
            </div>

            <!-- KPIs -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Venta del día</div>
                                    <div id="ventaDia" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Efectivo + Tarjeta</div>
                                </div>
                                <i class="icon mdi mdi-cash-multiple text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Venta Efectivo</div>
                                    <div id="ventaEfectivo" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Total</div>
                                </div>
                                <i class="icon mdi mdi-cash-register text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Venta Tarjeta</div>
                                    <div id="ventaTarjeta" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Transferencia / Tarjeta</div>
                                </div>
                                <i class="icon mdi mdi-credit-card-outline text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-secondary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Checadas</div>
                                    <div id="checadas" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Importe producto CHKDA</div>
                                </div>
                                <i class="icon mdi mdi-counter text-secondary"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Préstamos del día</div>
                                    <div id="prestamosDia" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Préstamos + Disposiciones</div>
                                </div>
                                <i class="icon mdi mdi-handshake-outline text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Pagos o Abonos</div>
                                    <div id="abonosDia" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Abonos a préstamos</div>
                                </div>
                                <i class="icon mdi mdi-cash-refund text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Efectivo en caja</div>
                                    <div id="efectivoEnCaja" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Efectivo + Abonos − Préstamos</div>
                                </div>
                                <i class="icon mdi mdi-safe-square-outline text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card kpi-card bg-soft-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="kpi-titulo">Venta Total</div>
                                    <div id="ventaTotal" class="kpi-valor">$0.00</div>
                                    <div class="kpi-sub">Efectivo + Tarjeta − Checadas</div>
                                </div>
                                <i class="icon mdi mdi-chart-areaspline text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficas -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Tendencia de ventas (últimos 30 días)</h5>
                            <div id="chart-tendencia"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Top 10 productos del mes con mas ventas</h5>
                            <div id="chart-top-prod"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Top compras a proveedores del mes</h5>
                            <div id="chart-top-prov"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- container -->
    </div> <!-- wrapper -->

    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

    <div class="rightbar-overlay"></div>

    <!-- Vendor js -->
    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <!-- App js-->
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>

    <script>
    (function(){
        const $fecha = document.getElementById('FiltroFecha');

        $fecha.addEventListener('change', cargarResumen);

        // ===== Paleta + asignación determinística por etiqueta =====
        const PALETTE = [
            '#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc949',
            '#af7aa1','#ff9da7','#9c755f','#bab0ab','#2f4b7c','#a05195',
            '#d45087','#f95d6a','#ff7c43','#ffa600'
        ];
        function hashStr(s){
            let h = 0; for (let i=0;i<s.length;i++){ h = ((h<<5)-h) + s.charCodeAt(i); h |= 0; }
            return Math.abs(h);
        }
        function colorForLabel(label){
            return PALETTE[ hashStr(String(label)) % PALETTE.length ];
        }
        function colorsForLabels(labels){
            return labels.map(colorForLabel);
        }

        function mxn(v){ return Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'}); }
        function setText(id, val){ const el = document.getElementById(id); if (el) el.textContent = mxn(val); }

        function cargarResumen(){
            const fecha = $fecha.value || new Date().toISOString().slice(0,10);

            $.post('<?= BASE_URL ?>/controllers/DashboardController.php', {
                accion: 'resumen',
                fecha: fecha
            })
            .done(function(resp){
                if(!resp || resp.ok !== true){
                    console.error(resp);
                    alert((resp && resp.msg) ? resp.msg : 'No se pudo cargar el resumen');
                    return;
                }
                const d = resp.data || {};
                setText('ventaDia',        d.venta_dia);
                setText('ventaEfectivo',   d.venta_efectivo);
                setText('ventaTarjeta',    d.venta_tarjeta);
                setText('checadas',        d.importe_chkda);
                setText('prestamosDia',    d.prestamos_dia);
                setText('abonosDia',       d.abonos_dia);
                setText('efectivoEnCaja',  d.efectivo_en_caja);
                setText('ventaTotal',      d.venta_total);
            })
            .fail(function(err){
                console.error(err);
                alert('Error de red al cargar el resumen');
            });
        }

        function renderLine(el, data){
            const options = {
                chart: { type:'line', height:320, toolbar:{show:false} },
                series: data.series,
                xaxis: { categories: data.labels },
                yaxis: { labels:{ formatter: v => v.toLocaleString('es-MX',{style:'currency',currency:'MXN'}) } },
                tooltip: { y:{ formatter: v => v.toLocaleString('es-MX',{style:'currency',currency:'MXN'}) } },
                dataLabels: { enabled:false },
                stroke: { width:3, curve:'smooth' }
            };
            if (el.__chart__) el.__chart__.destroy();
            el.__chart__ = new ApexCharts(el, options);
            el.__chart__.render();
        }

        // Barras horizontales con color por etiqueta (producto/proveedor)
        function renderBarHorizontal(el, labels, series){
            const colors = colorsForLabels(labels);
            const options = {
                chart: { type:'bar', height: 360, toolbar:{show:false} },
                series: [ { name: series.name, data: series.data } ],
                xaxis: {
                    categories: labels,
                    labels: { formatter: v => v.toLocaleString('es-MX',{style:'currency',currency:'MXN'}) }
                },
                plotOptions: { bar: { horizontal: true, barHeight:'70%', distributed: true } },
                colors: colors, // <- distinto por cada barra
                tooltip: {
                    x: { formatter: (val, {dataPointIndex}) => labels[dataPointIndex] },
                    y: { formatter: v => v.toLocaleString('es-MX',{style:'currency',currency:'MXN'}) }
                },
                dataLabels: { enabled:false },
                legend: { show:false }
            };
            if (el.__chart__) el.__chart__.destroy();
            el.__chart__ = new ApexCharts(el, options);
            el.__chart__.render();
        }

        function cargarTendencia(){
            $.post('<?= BASE_URL ?>/controllers/DashboardController.php', {accion:'tendencia_30d'})
             .done(function(resp){
                if(!resp || resp.ok!==true){ console.error(resp); return; }
                renderLine(document.querySelector('#chart-tendencia'), resp.data);
             });
        }

        function cargarTopProductosMes(){
            $.post('<?= BASE_URL ?>/controllers/DashboardController.php', {accion:'top_prod_mes'})
             .done(function(resp){
                if(!resp || resp.ok!==true){ console.error(resp); return; }
                const d = resp.data;
                renderBarHorizontal(
                    document.querySelector('#chart-top-prod'),
                    d.labels,
                    { name:'Importe', data: d.series[0]?.data || [] }
                );
             });
        }

        function cargarTopProveedoresMes(){
            $.post('<?= BASE_URL ?>/controllers/DashboardController.php', {accion:'top_prov_mes'})
             .done(function(resp){
                if(!resp || resp.ok!==true){ console.error(resp); return; }
                const d = resp.data;
                renderBarHorizontal(
                    document.querySelector('#chart-top-prov'),
                    d.labels,
                    { name:'Importe', data: d.series[0]?.data || [] }
                );
             });
        }

        // Inicial
        cargarResumen();
        cargarTendencia();
        cargarTopProductosMes();
        cargarTopProveedoresMes();
    })();
    </script>
</body>
</html>
