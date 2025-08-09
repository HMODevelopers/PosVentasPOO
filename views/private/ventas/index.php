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
        <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
    </head>

    <body>

        <!-- Navigation Bar-->


            <?php include_once __DIR__ . '/../../../includes/header.php'; ?>


        <!-- End Navigation Bar-->

        <!-- ============================================================== -->
        <!-- ================== Start Page Content here =================== -->
        <!-- ============================================================== -->

        <div class="wrapper">
            
                <!-- Loader -->
                <div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
                    <div class="loader">
                        <div class="loader__figure"></div>
                        <p class="loader__label">Cargando...</p>
                    </div>
                </div>
                <!-- Fin Loader -->

                <div class="container-fluid">

                    <!-- start page title -->
                    <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>    
                    <!-- end page title --> 

                    <!-- start filters -->
                    <div class="card-header" style="border-color:darkgray; border-style:dotted;">
                            <h5>Filtros</h5>

                            <div class="row">
                                <div class="col-lg-12">
                                        <div class="row">
                                            <!-- Filtro por Folio -->
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="Folio" class="control-label">Folio</label>
                                                    <div class="input-group">
                                                        <input type="text" id="Folio" name="Folio" class="form-control filtrar">
                                                        <div class="input-group-append clean-filter">
                                                            <span class="input-group-text">
                                                                <i class="mdi mdi-close-circle text-danger" onclick="clearField('Folio')"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Filtro por Fecha -->
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="Fecha" class="control-label">Fecha</label>
                                                    <div class="input-group">
                                                        <input type="date" id="Fecha" name="Fecha" class="form-control filtrar" value="<?php echo date('Y-m-d'); ?>">
                                                        <div class="input-group-append clean-filter">
                                                            <span class="input-group-text">
                                                                <i class="mdi mdi-close-circle text-danger" onclick="clearField('Fecha')"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                </div>
                            </div>
                    </div>   
                    <!--End Filters-->

                    <!--Tabla Ventas-->
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
                                                <th>Cajero</th>
                                                <th>Caja</th>
                                                <th>Forma de Pago</th>
                                                <th>Tipo de Precio</th>
                                                <th class="text-end">Total</th>
                                                <th>Estatus</th>
                                                <th>Cliente</th>
                                                <th>Fecha</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                                <!-- Paginador -->
                                <div class="row align-items-center justify-content-between mt-2">
                                        <div class="col-md-6">
                                            <div id="infoVentas" class="dataTables_info" role="status" aria-live="polite"></div>
                                        </div>
                                        <div class="col-md-6 d-flex justify-content-end">
                                            <nav aria-label="Page navigation">
                                            <ul id="pagination" class="pagination justify-content-end mb-0"></ul>
                                            </nav>
                                        </div>
                                </div>
                                <!-- Fin Paginador -->
                                 
                            </div>
                        </div>
                    </div>
                    <!--Fin Tabla Ventas-->

                
                </div> <!-- end container -->
        </div>
        <!-- end wrapper -->

        <!-- ============================================================== -->
        <!-- ===================== End Page content ======================= -->
        <!-- ============================================================== -->

        <!-- Footer Start -->
        <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
        <!-- End Footer -->

        

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- Vendor js -->
        <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
        <!-- App js-->
        <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
        <script>

            $(document).ready(function () {

                let paginaActual = 1;
                const limitePorPagina = 10;

                cargarVentas(paginaActual);

                function cargarVentas(pagina) {
                    
                    const folio = $('#Folio').val();// obtiene el valor actual del input
                    const fecha = $('#Fecha').val() || new Date().toISOString().split('T')[0]; // si está vacío, usa la fecha actual

                    $.ajax({
                        url: '<?= BASE_URL ?>/controllers/VentasController.php',
                        method: 'POST',
                        data: {
                            accion: 'listar',
                            pagina: pagina,
                            limite: limitePorPagina,
                            folio: folio,
                            fecha: fecha
                        },
                        dataType: 'json',
                        success: function (response) {
                            let ventas = response.data;
                            let total = parseInt(response.total || 0);

                            renderizarTabla(ventas);

                            // Info "Mostrando X a Y de Z"
                            let desde = (pagina - 1) * limitePorPagina + 1;
                            let hasta = Math.min(pagina * limitePorPagina, total);
                            $('#infoVentas').text(`Mostrando ${total === 0 ? 0 : desde} a ${hasta} de ${total} ventas`);

                            // Paginación
                            configurarPaginacion(pagina, total, limitePorPagina);
                        },
                        error: function () {
                            alert('Error al cargar las ventas.');
                        }
                    });
                }

                function renderizarTabla(ventas) {
                    let tbody = '';
                    if (ventas.length === 0) {
                        tbody = '<tr><td colspan="10" class="text-center">No hay ventas disponibles</td></tr>';
                    } else {
                        ventas.forEach(v => {
                            tbody += `
                                <tr>
                                    <td><center><b>${v.folio}</b></center></td>
                                    <td><center>${v.usuario}</center></td>
                                    <td><center>${v.caja}</center></td>
                                    <td><center>${v.forma_pago}</center></td>
                                    <td><center>${v.tipo_precio}</center></td>
                                    <td><center><b>${parseFloat(v.total).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}</b></center></td>
                                    <td><center>${v.estatus}</center></td>
                                    <td><center>${v.cliente ? v.cliente : 'Público en general'}</center></td>
                                     <td><center>${new Date(v.fecha).toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',  hour12: true })}</center></td>
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

                function configurarPaginacion(currentPage, totalItems, itemsPerPage = 10) {
                    var totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
                    var $ul = $('#pagination');
                    var maxVisiblePages = 5; // páginas visibles
                    $ul.empty();

                    // Ocultar si no hay más de 1 página
                    if (totalPages <= 1) {
                        $ul.closest('nav').hide();
                        return;
                    } else {
                        $ul.closest('nav').show();
                    }

                    // Rango mostrado
                    var startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                    var endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                    if (endPage - startPage + 1 < maxVisiblePages) {
                        startPage = Math.max(1, endPage - maxVisiblePages + 1);
                    }

                    // Primera / Anterior
                    if (currentPage > 1) {
                        $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="1">Primera</a></li>`);
                        $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage - 1}">&laquo; Anterior</a></li>`);
                    }

                    // Números
                    for (var i = startPage; i <= endPage; i++) {
                        var activeClass = (i === currentPage) ? 'active' : '';
                        $ul.append(`<li class="page-item ${activeClass}"><a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a></li>`);
                    }

                    // Siguiente / Última
                    if (currentPage < totalPages) {
                        $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage + 1}">Siguiente &raquo;</a></li>`);
                        $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${totalPages}">Última</a></li>`);
                    }

                    // Delegación de eventos SOLO dentro de #pagination
                    $ul.off('click', 'a.page-link').on('click', 'a.page-link', function (event) {
                        event.preventDefault();
                        var page = Number($(this).data('page'));
                        if (Number.isFinite(page)) {
                            paginaActual = page;        // usa tu variable global
                            cargarVentas(paginaActual); // reusa tu función existente
                        }
                    });
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

                //Funcion para filtrar resultados
                $(".filtrar")
                    .change(function () {

                        var vElement = $(this);
                        if ($(vElement).val().length > 0) {
                            $(vElement).siblings(".clean-filter").css({ display: "flex" });
                        } else {
                            $(vElement).siblings(".clean-filter").css({ display: "none" });
                        }

                        $(vElement).blur();

                        setTimeout(function () {
                            cargarVentas(1); // Cambiado aquí
                        }, 200);
                    })
                    .keypress(function (event) {
                        if (event.charCode == 13) {
                            cargarVentas(1); // Cambiado aquí
                        }
                    })
                    .keyup(function () {
                        if ($(this).val().length > 0) {
                            $(this).siblings(".clean-filter").css({ display: "flex" });
                        } else {
                            $(this).siblings(".clean-filter").css({ display: "none" });
                        }
                    })
                    .click(function () {
                        if ($(this).is(":button")) {
                            cargarVentas(1); // Cambiado aquí
                        }
                    });

                $(".clean-filter").click(function () {
                    var $vElement = $(this).parent().find(".filtrar");
                    $vElement.val("").trigger("change");

                    if ($vElement.hasClass("select2")) {
                        $vElement.select2("val", 0);
                    }

                    cargarVentas(1); // Cambiado aquí
                });

                
            });
        </script>



    </body>
</html>