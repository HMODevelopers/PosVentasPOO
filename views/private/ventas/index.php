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
        
        <!-- Custom box css -->
        <link href="<?= BASE_URL ?>/assets/libs/custombox/custombox.min.css" rel="stylesheet">
        <!-- App css -->
        <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
        <link href="<?= BASE_URL ?>/assets/css/ticket.css" rel="stylesheet" />

        <!-- Toastr -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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

                    <!-- Modal para ver detalle de venta -->
                    <?php include_once __DIR__ . '/../ventas/modales/detalle.php'; ?>  

                    <!-- Modal para ticket -->
                    <?php include_once __DIR__ . '/../ventas/modales/ticket.php'; ?>

                    <!-- Modal para eliminar venta -->
                    <?php include_once __DIR__ . '/../ventas/modales/eliminar.php'; ?>

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

        <!-- 1) Librerías base (jQuery 3.3.1 + Bootstrap 4.3.1, etc.) -->
        <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>

        <!-- 2) JS del template (inicializa tooltips, menú, etc.) -->
        <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>

        <!-- 3) Tus scripts propios -->
        <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
        <!-- 4) Toastr Js-->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        <script>

            $(document).ready(function () {

                let paginaActual = 1;
                const limitePorPagina = 10;

                cargarVentas(paginaActual);

                function getBadge(estatus) {
                    
                    switch (estatus) {
                        case 'Activa':
                            return '<span class="badge badge-light-success badge-pill">Activa</span>';
                        case 'Cancelada':
                            return '<span class="badge badge-light-danger badge-pill">Cancelada</span>';
                        case 'Devuelta':
                            return '<span class="badge badge-light-warning badge-pill">Devuelta</span>';
                        case 'Guardada':
                            return '<span class="badge badge-light-primary badge-pill">Guardada</span>';
                        default:
                            return `<span class="badge badge-light-secondary badge-pill">${estatus}</span>`;
                    }
                }

                function getAcciones(v) {
                    let acciones = `
                        <a class="dropdown-item accion-ver-detalle" href="#" data-toggle="modal" data-target="#modalDetalle" data-id="${v.id_venta}">
                            <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle
                        </a>
                    `;

                    // Ticket solo si el estatus es Activa o Guardada
                    if (v.estatus === 'Activa' || v.estatus === 'Guardada') {
                        acciones += `
                            <a class="dropdown-item" href="javascript:void(0);" onclick="abrirTicket(${v.id_venta});">
                                <i class="mdi mdi-printer mr-2 text-muted font-18 vertical-middle"></i>Ticket / Imprimir
                            </a>
                        `;
                    }

                    // Cancelar solo si es Activa
                    if (v.estatus === 'Activa') {
                        acciones += `
                            <a class="dropdown-item accion-eliminar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
                                <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Cancelar
                            </a>
                        `;
                    }

                    return acciones;
                }


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
                                    <td><center>${getBadge(v.estatus)}</center></td>
                                    <td><center>${v.cliente ? v.cliente : 'Público en general'}</center></td>
                                    <td><center>${new Date(v.fecha).toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',  hour12: true })}</center></td>
                                    <td>
                                        <center>
                                            <div class="btn-group dropdown">
                                                <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                </a>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                     ${getAcciones(v)}
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
            
                // Utilidades
                function mxn(n) {
                const v = Number(n || 0);
                return v.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
                }
                function fechaMx(dt) {
                try {
                    const d = new Date(dt);
                    return d.toLocaleString('es-MX', {
                    day:'2-digit', month:'2-digit', year:'numeric',
                    hour:'2-digit', minute:'2-digit', hour12:true
                    });
                } catch { return dt || '—'; }
                }

                // Delegación: click en Ver Detalle
                $(document).on('click', 'a.accion-ver-detalle', function (e) {
                    
                    e.preventDefault();

                    const id = $(this).data('id');
                    if (!id) return;

                    // estados UI
                    $('#det-error').hide();
                    $('#det-contenido').hide();
                    $('#det-loader').show();

                    // abrir modal
                    $('#modalDetalle').modal('show');

                    // pedir detalle
                    $.ajax({
                        url: '<?= BASE_URL ?>/controllers/VentasController.php',
                        method: 'GET',
                        dataType: 'json',
                        data: { accion: 'detalle', id_venta: id }
                    })
                    .done(function (resp) {
                        // Validar respuesta
                        if (!resp || !resp.venta) {
                        $('#det-loader').hide();
                        $('#det-error').show().text('No se encontró la venta.');
                        return;
                        }

                        const v = resp.venta;
                        const dets = Array.isArray(resp.detalles) ? resp.detalles : [];

                        // Encabezado
                        $('#det-folio').text(v.folio || '—');
                        $('#det-fecha').text(fechaMx(v.fecha));
                        $('#det-estatus').html(getBadge(v.estatus || '—'));
                        $('#det-cliente').text(v.cliente || 'Público en general');
                        $('#det-usuario').text(v.usuario || '—');
                        $('#det-caja').text(v.caja || '—');
                        $('#det-forma').text(v.forma_pago || '—');
                        $('#det-tipo').text(v.tipo_precio || '—');

                        // Items
                        let tbody = '';
                        let total = 0;
                        if (dets.length === 0) {
                        tbody = `<tr><td colspan="5" class="text-center text-muted">Sin productos</td></tr>`;
                        total = Number(v.total || 0);
                        } else {
                        dets.forEach(d => {
                            const cant = Number(d.cantidad || 0);
                            const precio = Number(d.precio_unitario || 0);
                            const subt = Number(d.subtotal || (cant * precio));
                            total += subt;

                            tbody += `
                            <tr>
                                <td>${d.codigo || ('#' + (d.id_producto || ''))}</td>
                                <td>${d.producto || ('#' + (d.id_producto || ''))}</td>
                                <td class="text-center">${cant}</td>
                                <td class="text-right">${mxn(precio)}</td>
                                <td class="text-right">${mxn(subt)}</td>
                            </tr>
                            `;
                        });
                        }
                        $('#det-tbody').html(tbody);
                        $('#det-total').text(mxn(total || v.total || 0));

                        // Mostrar contenido
                        $('#det-loader').hide();
                        $('#det-contenido').show();
                    })
                    .fail(function () {
                        $('#det-loader').hide();
                        $('#det-error').show().text('Error al cargar el detalle.');
                    });
                });
                
                //------------------------------------------- Tiket ----------------------------------------------------------//

                // --- Renglón de ticket con layout en grid ---
                function renderItem({ cantidad, articulo, precio_unitario, subtotal, descripcion }) {
                    const cant = (Number(cantidad || 0)).toFixed(2);
                    const art  = (articulo || '').toString(); // ya no cortamos, dejamos que haga word-wrap
                    const precio = mxn(precio_unitario);
                    const total  = mxn(subtotal);

                    // Fila principal
                    const row1 = `
                        <div class="tk-item">
                            <div class="c-cant">${cant}</div>
                            <div class="c-art">${art}</div>
                            <div class="c-precio">${precio}</div>
                            <div class="c-total">${total}</div>
                        </div>
                    `;

                    // Si hay descripción extra, la agregamos en otra fila (sin columnas)
                    const row2 = descripcion 
                        ? `<div style="margin-left:50px; font-size:11px; white-space:normal; overflow-wrap:anywhere;">${descripcion}</div>`
                        : '';

                    return row1 + row2;
                }

                // --- Hacer global para el onclick del menú ---
                window.abrirTicket = function(idVenta) {
                // limpia
                $('#tk-items').empty();
                $('#tk-folio').text('—');
                $('#tk-fecha').text('—');
                $('#tk-total').text('$0.00');
                $('#tk-idventa').val(idVenta);

                $.ajax({
                    url: '<?= BASE_URL ?>/controllers/VentasController.php',
                    method: 'GET',
                    dataType: 'json',
                    data: { accion: 'detalle', id_venta: idVenta }
                })
                .done(function (resp) {
                    if (!resp || !resp.venta) {
                    alert('No se encontró la venta.');
                    return;
                    }
                    const v   = resp.venta || {};
                    const det = Array.isArray(resp.detalles) ? resp.detalles : [];

                    $('#tk-folio').text(v.folio || '—');
                    $('#tk-fecha').text(fechaMx(v.fecha));

                    let html = '';
                    let total = 0;
                    det.forEach(d => {
                    const cantidad = Number(d.cantidad || 0);
                    const precio   = Number(d.precio_unitario || 0);
                    const importe  = (d.subtotal != null) ? Number(d.subtotal) : (cantidad * precio);
                    total += importe;

                    html += renderItem({
                        cantidad,
                        articulo: d.producto || d.clave || d.codigo || '',
                        precio_unitario: precio,
                        subtotal: importe,
                        descripcion: d.descripcion || d.nombre || ''
                    });
                    });

                    $('#tk-items').html(html);
                    $('#tk-total').text(mxn(v.total != null ? v.total : total));
                    $('#modalTicket').modal('show');
                })
                .fail(function(){
                    alert('Error al cargar el ticket.');
                });
                };

                // --- Imprimir (solo el área del ticket gracias al @media print) ---
               $(document).on('click', '#btnImprimirTicket', function () {
                    const id = $('#tk-idventa').val();
                    if(!id){ alert('No hay venta seleccionada'); return; }

                    $.get('<?= BASE_URL ?>/utils/ticket_mike42.php', { id_venta: id })
                        .done(function(resp){
                            console.log("Impresión:", resp);
                        })
                        .fail(function(xhr){
                            console.error("Error al imprimir:", xhr.responseText || 'Error al imprimir');
                        });
               });

                //------------------------------------Eliminar Ventas ------------------------------------------------------//

                // Abrir modal de eliminar (desde el dropdown)
                $(document).on('click', 'a.accion-eliminar', function (e) {
                e.preventDefault();
                const id    = $(this).data('id');
                const folio = $(this).data('folio');

                if (!id) return;

                $('#el-id-venta').val(id);
                $('#el-folio').text(folio);
                $('#modalEliminar').modal('show');
                });

                // Confirmar eliminación (bind único)
                $(document).off('click', '#btnConfirmarEliminar').on('click', '#btnConfirmarEliminar', function () {
                    const id = $('#el-id-venta').val();
                    if (!id) return;

                    const $btn = $(this);
                    const originalHtml = $btn.html();

                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Eliminando...');

                    $.ajax({
                        url: '<?= BASE_URL ?>/controllers/VentasController.php',
                        method: 'POST',
                        dataType: 'json',
                        data: { accion: 'eliminar', id_venta: id }
                    })
                    .done(function (resp) {
                        // resp esperado: { ok: true|false, msg: "texto" }
                        if (resp && (resp.ok === true || resp.resultado === 'ok')) {
                        toastr.success(resp?.msg || 'Venta eliminada con éxito');
                        cargarVentas(paginaActual);
                        } else {
                        const msg = resp?.msg || resp?.resultado || 'Error al eliminar.';
                        toastr.error(msg);
                        }
                    })
                    .fail(function (xhr) {
                        toastr.error('No se pudo conectar con el servidor.');
                        // Por si el backend está enviando texto no-JSON, ayuda ver qué llegó:
                        console.warn('Respuesta fallo:', xhr.responseText);
                    })
                    .always(function () {
                        $('#modalEliminar').modal('hide');
                        $btn.prop('disabled', false).html(originalHtml);
                    });
                });
        });
    </script>



    </body>
</html>