<?php
$titulo = "Ventas";
$modulo = "Gesionar Ventas";
$subtitulo = ""; // puedes dejarlo vacío si no se necesita
session_start();

// Incluye la configuración con BASE_URL
require_once __DIR__ . '/../../../includes/config.php';

// Verifica si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/views/public/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Ventas | REFASOFT-V4</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
        <meta content="Coderthemes" name="author" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <!-- App favicon -->
        <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

        <!-- plugin css -->
        <link href="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />

        <!-- App css -->
        <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />

    </head>

    <body>

        <!-- Navigation Bar-->


            <?php include_once __DIR__ . '/../../../includes/header.php'; ?>


        <!-- End Navigation Bar-->

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->

        <div class="wrapper">
            <div class="container-fluid">

                <!-- start page title -->
                <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>    
                    <!-- end page title --> 

                    
                <!--Contenido-->
                <div class="row">
                    <div class="col-12">
                        <div class="card-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h4 class="header-title">Listado de Ventas</h4>
                            </div>

                            <!-- Tabla -->
                            <div class="table-responsive">
                                <table id="tablaVentas" class="table table-bordered table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Fecha</th>
                                            <th>Cajero</th>
                                            <th>Caja</th>
                                            <th class="text-end">Total</th>
                                            <th>Estatus</th>
                                            <th>Cliente</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <!-- Info y paginador (fuera del .table-responsive) -->
                            <div class="row align-items-center justify-content-between mt-2">
                                <div class="col-md-6">
                                    <div id="infoVentas" class="dataTables_info" role="status" aria-live="polite"></div>
                                </div>
                                <div class="col-md-6 d-flex justify-content-end">
                                    <div id="paginadorVentas" class="dataTables_paginate paging_simple_numbers">
                                        <ul class="pagination pagination-rounded mb-0"></ul>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!--Fin Contenido-->

                
            </div> <!-- end container -->
        </div>
        <!-- end wrapper -->

        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

        <!-- Footer Start -->
        <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
        <!-- end Footer -->

        

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- Vendor js -->
        <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
        <!-- App js-->
        <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
        <script>
            $(document).ready(function () {
                let paginaActual = 1;
                const limitePorPagina = 10;

                cargarVentas(paginaActual);

                function cargarVentas(pagina) {
                    $.ajax({
                        url: '<?= BASE_URL ?>/controllers/VentasController.php',
                        method: 'POST',
                        data: {
                            accion: 'listar',
                            pagina: pagina,
                            limite: limitePorPagina
                        },
                        dataType: 'json',
                        success: function (response) {
                            let ventas = response.data;
                            let total = parseInt(response.total || 0);
                            let totalPaginas = Math.ceil(total / limitePorPagina);

                            renderizarTabla(ventas);

                            let desde = (pagina - 1) * limitePorPagina + 1;
                            let hasta = Math.min(pagina * limitePorPagina, total);
                            $('#infoVentas').text(`Mostrando ${desde} a ${hasta} de ${total} ventas`);

                            renderizarPaginador(pagina, totalPaginas);
                        },
                        error: function () {
                            alert('Error al cargar las ventas.');
                        }
                    });
                }

              function renderizarTabla(ventas) {
                    let tbody = '';
                    if (ventas.length === 0) {
                        tbody = '<tr><td colspan="8" class="text-center">No hay ventas disponibles</td></tr>';
                    } else {
                        ventas.forEach(v => {
                            tbody += `
                                <tr>
                                    <td><center><b>${v.folio}</b></center></td>
                                    <td>${new Date(v.fecha).toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',  hour12: true })}</td>
                                    <td>${v.usuario}</td>
                                    <td>${v.caja}</td>
                                    <td>${parseFloat(v.total).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}</td>
                                    <td>${v.estatus}</td>
                                    <td>${v.cliente ? v.cliente : 'Público en general'}</td>
                                    <td>
                                        <center>
                                            <div class="btn-group dropdown">
                                                <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                </a>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="#"><i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle</a>
                                                    <a class="dropdown-item" href="#"><i class="mdi mdi-content-copy mr-2 text-muted font-18 vertical-middle"></i>Reimprimir</a>
                                                    <a class="dropdown-item text-danger" href="#"><i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Eliminar</a>
                                                </div>
                                            </div>
                                        </center>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    $('#tablaVentas tbody').html(tbody);
                }


                function renderizarPaginador(pagina, totalPaginas) {
                    const paginador = $('#paginadorVentas ul');
                    paginador.empty();

                    // Botón anterior
                    const prevClass = pagina === 1 ? 'disabled' : '';
                    paginador.append(`
                        <li class="paginate_button page-item previous ${prevClass}">
                            <a href="#" class="page-link" data-pagina="${pagina - 1}">
                                <i class="mdi mdi-chevron-left"></i>
                            </a>
                        </li>
                    `);

                    // Botones de número
                    for (let i = 1; i <= totalPaginas; i++) {
                        const activeClass = i === pagina ? 'active' : '';
                        paginador.append(`
                            <li class="paginate_button page-item ${activeClass}">
                                <a href="#" class="page-link" data-pagina="${i}">${i}</a>
                            </li>
                        `);
                    }

                    // Botón siguiente
                    const nextClass = pagina === totalPaginas ? 'disabled' : '';
                    paginador.append(`
                        <li class="paginate_button page-item next ${nextClass}">
                            <a href="#" class="page-link" data-pagina="${pagina + 1}">
                                <i class="mdi mdi-chevron-right"></i>
                            </a>
                        </li>
                    `);

                    // Mostrar u ocultar según necesidad
                    if (totalPaginas <= 1) {
                        $('#paginadorVentas').hide();
                    } else {
                        $('#paginadorVentas').show();
                    }
                }

                // Evento para cambiar página
                $(document).on('click', '#paginadorVentas .page-link', function (e) {
                    e.preventDefault();
                    const nuevaPagina = parseInt($(this).data('pagina'));
                    if (!isNaN(nuevaPagina) && nuevaPagina > 0) {
                        paginaActual = nuevaPagina;
                        cargarVentas(paginaActual);
                    }
                });
            });
        </script>



    </body>
</html>