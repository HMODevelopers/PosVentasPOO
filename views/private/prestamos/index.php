<?php
$titulo = "Ventas";
$modulo = "Préstamos / Disposiciones";
$subtitulo = "";
session_start();

// ================================
    // Duración lógica de la sesión
    // ================================
    $SESSION_LIFETIME = 10 * 60 * 60; // 10 horas en segundos

    // Iniciar sesión solo si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/../../../includes/config.php';

    // ================================
    // Validar que haya usuario logueado
    // ================================
    if (!isset($_SESSION['usuario'])) {
        header('Location: ' . BASE_URL . '/views/public/index.php');
        exit();
    }

    // ================================
    // Control de tiempo de sesión (10h)
    // ================================
    $sessionStart = $_SESSION['SESSION_START'] ?? 0;
    $sessionTTL   = $_SESSION['SESSION_TTL']   ?? $SESSION_LIFETIME;

    // Si no hay marca de inicio o ya se pasó el tiempo, forzamos re-login
    if ($sessionStart === 0 || (time() - $sessionStart) > $sessionTTL) {
        session_unset();
        session_destroy();
        // Mandamos al index público con flag de expirado
        header('Location: ' . BASE_URL . '/views/public/index.php?expired=1');
        exit();
    }

    // Si la sesión sigue vigente, actualizamos banderas
    $_SESSION['SESION_VIGENTE'] = true;
    $_SESSION['LAST_ACTIVITY']  = time();


// Nota: NO fijamos $hoy en el input "Fecha" para no filtrar la carga inicial
$hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Préstamos / Disposiciones | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core CSS -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

    <style>
      .table td, .table th { vertical-align: middle; }
      .badge-soft { padding:.35rem .55rem; border-radius: .5rem; font-weight:600; }
      .badge-prestamo { background: rgba(64,153,255,.12); color:#4099ff; }
      .badge-dispo    { background: rgba(250,92,124,.12); color:#fa5c7c; }
      .badge-pago     { background: rgba(16,183,89,.12); color:#10b759; }
      .text-center{ text-align:center!important; }
      .text-right{ text-align:right!important; }
      .clean-filter{ display:none; }

      /* Evitar scroll horizontal por textos largos */
      #tablaPrestamos th, #tablaPrestamos td { white-space: normal; }
      #tablaPrestamos td:nth-child(3) { word-break: break-word; }

      /* Menú de acciones compacto en responsive */
      .table-responsive-lg .dropdown-menu,
      .table-responsive .dropdown-menu { min-width: unset; }
    </style>
  </head>

  <body>
    <!-- Topbar -->
    <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

    <div class="wrapper">
      <!-- Loader -->
      <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
        <div class="loader">
          <div class="loader__figure"></div>
          <p class="loader__label">Cargando...</p>
        </div>
      </div>

      <div class="container-fluid">
        <!-- Breadcrumb -->
        <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>
        <!-- /Breadcrumb -->

        <!-- Filtros -->
        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
          <h5>Filtros</h5>
          <div class="row">
            <div class="col-lg-12">
              <div class="row">
                <!-- Folio / Búsqueda -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="Folio" class="control-label">Folio / Concepto</label>
                    <div class="input-group">
                      <input type="text" id="Folio" class="form-control filtrar" autocomplete="off" placeholder="Ej. 102 o anticipo">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Folio')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Fecha -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="Fecha" class="control-label">Fecha</label>
                    <div class="input-group">
                      <!-- IMPORTANTE: quitar value="<?= htmlspecialchars($hoy) ?>" para NO filtrar la carga inicial -->
                      <input type="date" id="Fecha" class="form-control filtrar" value="">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Fecha')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Tipo operación -->
                <div class="col-md-2">
                  <div class="form-group">
                    <label for="FTipoOp" class="control-label">Tipo</label>
                    <select id="FTipoOp" class="form-control filtrar">
                      <option value="">Todos</option>
                      <option value="Prestamo">Préstamo</option>
                      <option value="Disposicion">Disposición</option>
                      <option value="Pago">Pago</option>
                    </select>
                  </div>
                </div>
                <!-- Estatus -->
                <div class="col-md-2">
                  <div class="form-group">
                    <label for="FEstatus" class="control-label">Estatus</label>
                    <select id="FEstatus" class="form-control filtrar">
                      <option value="">Todos</option>
                      <option value="Pendiente">Pendiente</option>
                      <option value="Pagado">Pagado</option>
                      <option value="Aplicado">Aplicado</option>
                      <option value="Cancelado">Cancelado</option>
                      <option value="SinRetorno">Sin Retorno</option>
                    </select>
                  </div>
                </div>
              </div><!-- /row -->
            </div>
          </div>
        </div>
        <!-- /Filtros -->

        <!-- Tabla -->
        <div class="row">
          <div class="col-12">
            <div class="card-box">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="header-title mb-0">Listado de Préstamos / Disposiciones / Pagos</h4>
                <button id="btnNuevo" class="btn btn-primary btn-sm">
                  <i class="mdi mdi-plus"></i> Nuevo
                </button>
              </div>

              <!-- Solo responsive en pantallas chicas -->
              <div class="table-responsive-lg">
                <table id="tablaPrestamos" class="table table-bordered table-hover table-striped">
                  <thead>
                    <tr>
                      <th class="text-center">Folio</th>
                      <th class="text-center">Tipo</th>
                      <th>Concepto</th>
                      <th class="text-center">Beneficiario</th>
                      <th class="text-right">Monto</th>
                      <th class="text-right">Saldo</th>
                      <th class="text-center">Estatus</th>
                      <th class="text-center">Fecha</th>
                      <th class="text-center">Acciones</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="row align-items-center justify-content-between mt-2">
                <div class="col-md-6"><div id="infoPrestamos" class="dataTables_info"></div></div>
                <div class="col-md-6 d-flex justify-content-end">
                  <nav aria-label="Page navigation"><ul id="pagination" class="pagination justify-content-end mb-0"></ul></nav>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /Tabla -->

        <!-- ========== MODALES ========== -->

        <!-- Nuevo -->
        <?php include_once __DIR__ . '/../prestamos/modales/agregar.php'; ?>

        <!-- Abonar -->
        <?php include_once __DIR__ . '/../prestamos/modales/abonar.php'; ?>

        <!-- Detalle -->
        <?php include_once __DIR__ . '/../prestamos/modales/detalle.php'; ?>

      </div> <!-- /container-fluid -->
    </div> <!-- /wrapper -->

    <!-- Footer -->
    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
    <div class="rightbar-overlay"></div>

    <!-- Core JS -->
    <script>const BASE_URL='<?= BASE_URL ?>';</script>
    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // helper para íconos "limpiar"
    function clearField(id){ try { $('#'+id).val('').trigger('change'); } catch(e){} }

    $(function(){
      const BASE = BASE_URL;
      const PRESTAMOS_URL   = `${BASE}/controllers/PrestamosController.php`;
      const CLIENTES_URL    = `${BASE}/controllers/ClientesController.php`;
      const USUARIOS_URL    = `${BASE}/controllers/UsuariosController.php`; // para Empleados
      const EMPLEADOS_URL   = USUARIOS_URL;
      const FORMAS_PAGO_URL = `${BASE}/controllers/FormasPagoController.php`;

      const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
      const fechaMx = dt => { try{ const d=new Date(String(dt).replace(' ','T')); return d.toLocaleDateString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric'}); } catch { return dt||'—'; } };

      // =================== LISTADO ===================
      let paginaActual=1; const limitePorPagina=10;

      function badgeTipo(op){
        if (op==='Prestamo') return '<span class="badge-soft badge-prestamo">Préstamo</span>';
        if (op==='Disposicion') return '<span class="badge-soft badge-dispo">Disposición</span>';
        if (op==='Pago') return '<span class="badge-soft badge-pago">Pago</span>';
        return `<span class="badge badge-light">${op||'—'}</span>`;
      }

      function badgeEstatus(s){
        const m = {
          'Pendiente':'badge-light-warning',
          'Pagado':'badge-light-success',
          'Aplicado':'badge-light-primary',
          'Cancelado':'badge-light-danger',
          'SinRetorno':'badge-light-secondary'
        };
        return `<span class="badge ${m[s]||'badge-light-secondary'} badge-pill">${s||'—'}</span>`;
      }

      function parseBeneficiarioDesdeConcepto(concepto){
        if (!concepto) return null;
        const m = concepto.match(/\[Beneficiario:\s*(.+?)\]/i);
        return m ? m[1] : null;
      }

      function beneficiarioLabel(r){
        const nombre = (r.beneficiario_nombre || '').trim();
        if (nombre) return nombre;

        if (r.tipo==='Otro'){
          const extra = parseBeneficiarioDesdeConcepto(r.concepto);
          return extra ? extra : 'Otro';
        }

        if (r.tipo==='Cliente') return 'Cliente sin nombre';
        if (r.tipo==='Empleado') return 'Empleado sin nombre';

        return r.tipo || '—';
      }

      // ========= Acciones por fila =========
      function accionesFila(r){
        let out = `
          <a class="dropdown-item accion-detalle" href="#" data-id="${r.id_prestamo}">
            <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver detalle
          </a>`;

        const frozen = (String(r.estatus) === 'Pagado' || String(r.estatus) === 'Cancelado');
        if (frozen) return out;

        if (r.tipo_operacion==='Prestamo' && Number(r.saldo)>0){
          out += `
          <a class="dropdown-item accion-abonar" href="#" data-id="${r.id_prestamo}">
            <i class="mdi mdi-cash mr-2 text-muted font-18 vertical-middle"></i>Abonar
          </a>`;
        }

        out += `
          <a class="dropdown-item accion-cancelar" href="#" data-id="${r.id_prestamo}">
            <i class="mdi mdi-cancel mr-2 text-muted font-18 vertical-middle"></i>Cancelar
          </a>`;

        return out;
      }

      // Carga del listado (si no hay filtros -> trae TODO)
      function cargarPrestamos(pagina){
        const q      = $('#Folio').val() || '';
        const fecha  = $('#Fecha').val() || '';   // OJO: en la carga inicial estará vacío
        const desde  = fecha || '';
        const hasta  = fecha || '';
        const tipoop = $('#FTipoOp').val() || '';
        const est    = $('#FEstatus').val() || '';

        $("#LoadingImage").fadeIn(150);
        $.post(PRESTAMOS_URL,{
          accion:'listar', pagina, limite:limitePorPagina,
          q, desde, hasta, tipo_operacion:tipoop, estatus:est
        }, function(resp){
          const rows = resp?.data || [];
          const total= parseInt(resp?.total||0,10);
          let tbody = '';

          if (!rows.length){
            tbody = '<tr><td colspan="9" class="text-center">Sin registros</td></tr>';
          } else {
            rows.forEach(r=>{
              const benef = beneficiarioLabel(r);
              tbody += `
                <tr>
                  <td class="text-center"><b>${r.id_prestamo}</b></td>
                  <td class="text-center">${badgeTipo(r.tipo_operacion)}</td>
                  <td>${r.concepto || ''}</td>
                  <td class="text-center">${benef}</td>
                  <td class="text-right"><b>${mxn(r.monto_total)}</b></td>
                  <td class="text-right">${mxn(r.saldo)}</td>
                  <td class="text-center">${badgeEstatus(r.estatus)}</td>
                  <td class="text-center">${fechaMx(r.fecha_prestamo)}</td>
                  <td class="text-center">
                    <div class="btn-group dropdown dropleft">
                      <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                        <i class="mdi mdi-dots-horizontal"></i>
                      </a>
                      <div class="dropdown-menu">
                        ${accionesFila(r)}
                      </div>
                    </div>
                  </td>
                </tr>`;
            });
          }
          $('#tablaPrestamos tbody').html(tbody);

          configurarPaginacion(pagina, total, limitePorPagina);
          const desdeIdx=(pagina-1)*limitePorPagina+1, hastaIdx=Math.min(pagina*limitePorPagina,total);
          $('#infoPrestamos').text(`Mostrando ${total===0?0:desdeIdx} a ${hastaIdx} de ${total} registros`);
        },'json').fail(()=> toastr.error('No se pudo cargar el listado.'))
        .always(()=> $("#LoadingImage").fadeOut(150));
      }

      function configurarPaginacion(currentPage,totalItems,itemsPerPage){
        const totalPages=Math.max(1,Math.ceil(totalItems/itemsPerPage));
        const $ul=$('#pagination').empty();
        const maxVisible=5;
        if (totalPages<=1){ $ul.closest('nav').hide(); return; } else { $ul.closest('nav').show(); }
        let start=Math.max(1,currentPage-Math.floor(maxVisible/2));
        let end=Math.min(totalPages,start+maxVisible-1);
        if (end-start+1<maxVisible) start=Math.max(1,end-maxVisible+1);
        if (currentPage>1){
          $ul.append(`<li class="page-item"><a class="page-link" data-page="1">Primera</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
        }
        for(let i=start;i<=end;i++){
          $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`);
        }
        if (currentPage<totalPages){
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${totalPages}">Última</a></li>`);
        }
      }
      $('#pagination').on('click','a.page-link',function(e){
        e.preventDefault(); const p=Number($(this).data('page'));
        if (Number.isFinite(p)){ paginaActual=p; cargarPrestamos(paginaActual); }
      });

      // Filtros
      $(".filtrar")
        .on('change keyup', function(){
          const $el=$(this);
          if ($el.val().length>0) $el.siblings(".clean-filter").css({display:"flex"});
          else $el.siblings(".clean-filter").css({display:"none"});
        })
        .on('change', ()=> setTimeout(()=>cargarPrestamos(1),200))
        .on('keypress', e=>{ if(e.charCode===13) cargarPrestamos(1); });

      $(".clean-filter").click(function(){
        const $el=$(this).parent().find(".filtrar"); $el.val("").trigger("change");
        cargarPrestamos(1);
      });

      // ========= Selects dinámicos =========
      function setSelect($sel, arr, valueKey, labelKey){
        $sel.empty().append(`<option value="">-- Seleccionar Opción --</option>`);
        (arr||[]).forEach(x=>{
          const id = x[valueKey];
          const label = x[labelKey] || `(id ${id})`;
          if (id != null) $sel.append(`<option value="${id}">${label}</option>`);
        });
      }

      function cargarClientes(selected){
        const LIM=200;
        $.post(CLIENTES_URL,{accion:'listar-min', limite:LIM})
          .done(r=>{
            const data = r?.data || [];
            const mapped = data.map(c=>({ id: c.id_cliente ?? c.id, nombre: c.nombre ?? c.razon_social ?? c.nombre_comercial }));
            setSelect($('#selCliente'), mapped, 'id','nombre');
            if (selected!=null) $('#selCliente').val(String(selected));
          })
          .fail(()=> setSelect($('#selCliente'), [], 'id','nombre'));
      }

      // Empleados desde UsuariosController (listar-min)
      function cargarEmpleados(selected){
        $.post(EMPLEADOS_URL, {accion:'listar-min', limite:200})
          .done(r=>{
            const data = r?.data || [];
            const mapped = data.map(u=>{
              const id = u.id_usuario ?? u.id;
              const nom = u.nombre ?? u.nombre_completo ?? u.usuario ?? `Usuario ${id}`;
              return { id, nombre: nom };
            });
            setSelect($('#selEmpleado'), mapped, 'id','nombre');
            if (selected!=null) $('#selEmpleado').val(String(selected));
          })
          .fail(()=> setSelect($('#selEmpleado'), [], 'id','nombre'));
      }

      // Helper: referencia segura al select de forma de pago del ABONO (acepta dos ids)
      function $selAbonoFP(){
        return $('#selFormaPagoAbono').length ? $('#selFormaPagoAbono') : $('#abono_forma_pago');
      }

      // Formas de pago para el modal de abono
      function cargarFormasPagoAbono(selected){
        const $sel = $selAbonoFP();
        if(!$sel.length) return;

        const EXCLUDE_FP_IDS = []; // si conoces IDs de "Crédito", colócalos aquí

        const norm = (t)=> String(t||'')
          .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
          .toLowerCase().trim();

        const esCreditoPuro = (desc)=>{
          const txt = norm(desc);
          return /^credito\b/.test(txt) && !/tarjeta/.test(txt);
        };

        $sel.prop('disabled', true).empty().append('<option value="">Cargando…</option>');

        $.get(FORMAS_PAGO_URL, {accion:'listar_select'})
          .done(r=>{
            const arr = r?.data || (Array.isArray(r)?r:[]);
            const filtradas = arr.filter(fp => {
              const id   = Number(fp.id_forma_pago);
              const desc = fp.descripcion ?? '';
              return !EXCLUDE_FP_IDS.includes(id) && !esCreditoPuro(desc);
            });

            $sel.empty();

            if(!filtradas.length){
              $sel.append('<option value="">(sin formas de pago)</option>');
            } else {
              filtradas.forEach(fp=>{
                $sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
              });

              if (selected != null && $sel.find(`option[value="${String(selected)}"]`).length){
                $sel.val(String(selected));
              }

              if(!$sel.val()){
                const opEfe = $sel.find('option').filter(function(){
                  return norm($(this).text()) === 'efectivo';
                }).first().val();
                if(opEfe) $sel.val(opEfe);
              }
            }
          })
          .fail(()=>{
            $sel.empty()
                .append('<option value="1">Efectivo</option>')
                .append('<option value="2">Tarjeta de crédito</option>')
                .append('<option value="3">Tarjeta de débito</option>')
                .append('<option value="4">Transferencia electrónica de fondos</option>');
          })
          .always(()=> $sel.prop('disabled', false));
      }

      function tipoOperacionLabel(op){
        if (op==='Prestamo') return 'Préstamo';
        if (op==='Disposicion') return 'Disposición';
        if (op==='Pago') return 'Pago';
        return op || '—';
      }

      function cargarFormasPagoNuevo(selected){
        const $sel = $('#selFormaPagoNuevo');
        if (!$sel.length) return;

        $sel.prop('disabled', true).empty().append('<option value="">Cargando…</option>');
        $.get(FORMAS_PAGO_URL, {accion:'listar_select'})
          .done(r=>{
            const arr = r?.data || (Array.isArray(r)?r:[]);
            $sel.empty().append('<option value="">-- Seleccionar forma de pago --</option>');
            arr.forEach(fp=>{
              $sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
            });
            if (selected!=null) $sel.val(String(selected));
          })
          .fail(()=>{
            $sel.empty().append('<option value="">(sin formas de pago)</option>');
          })
          .always(()=> $sel.prop('disabled', false));
      }

      // Mostrar/ocultar inputs según tipo de beneficiario
      function toggleBenefWrappers(){
        const t = $('#tipo').val();
        const $selCli = $('#selCliente');
        const $selEmp = $('#selEmpleado');

        $('#wrapCliente').toggleClass('d-none', t!=='Cliente');
        $('#wrapEmpleado').toggleClass('d-none', t!=='Empleado');
        $('#wrapOtro').toggleClass('d-none', t!=='Otro');

        $selCli.prop('required', t==='Cliente');
        $selEmp.prop('required', t==='Empleado');

        if (t==='Cliente') {
          cargarClientes();
          $selEmp.val('');
          $('#txtOtro').val('');
        }
        if (t==='Empleado'){
          cargarEmpleados();
          $selCli.val('');
          $('#txtOtro').val('');
        }
        if (t==='Otro') {
          $selCli.val('');
          $selEmp.val('');
        }
      }

      // ========= Nuevo =========
      function toggleTipoOperacionNuevo(){
        const op = $('#tipo_operacion').val();
        const esPago = (op === 'Pago');
        $('#wrapFormaPagoNuevo').toggleClass('d-none', !esPago);
        $('#selFormaPagoNuevo').prop('required', esPago);
        if (esPago) {
          cargarFormasPagoNuevo();
        } else {
          $('#selFormaPagoNuevo').val('');
        }
      }

      $('#btnNuevo').on('click', ()=> {
        $('#formNuevo')[0].reset();
        $('#tipo').val('Cliente'); // default
        toggleBenefWrappers();
        toggleTipoOperacionNuevo();
        $('#modalNuevo').modal('show');
      });
      $('#tipo').on('change', toggleBenefWrappers);
      $('#tipo_operacion').on('change', toggleTipoOperacionNuevo);

      $('#formNuevo').on('submit', function(e){
        e.preventDefault();

        const arr = $(this).serializeArray();
        const data = {};
        arr.forEach(x=> data[x.name]=x.value);

        if (!data.id_cliente)  delete data.id_cliente;
        if (!data.id_empleado) delete data.id_empleado;

        const opSel = data.tipo_operacion || 'Prestamo';
        if (opSel !== 'Pago') {
          delete data.id_forma_pago;
        } else if (!data.id_forma_pago) {
          toastr.warning('Selecciona la forma de pago para registrar Pago.');
          return;
        }

        const otro = ($('#tipo').val()==='Otro') ? ($('#txtOtro').val()||'').trim() : '';
        if (otro){
          const conceptoBase = (data.concepto||'').trim();
          const yaIncluye = /\[Beneficiario:\s*.+?\]/i.test(conceptoBase);
          data.concepto = yaIncluye ? conceptoBase : `${conceptoBase} [Beneficiario: ${otro}]`.trim();
        }

        data.accion = 'crear';

        const $btn = $('#formNuevo button[type=submit]'), html=$btn.html();
        $btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');
        $.post(PRESTAMOS_URL, data, function(r){
          if(r && r.ok){ toastr.success('Registro creado.'); $('#modalNuevo').modal('hide'); cargarPrestamos(paginaActual); }
          else { toastr.error(r?.msg || 'No se pudo crear.'); }
        },'json').fail(()=> toastr.error('Error de comunicación.'))
        .always(()=> $btn.prop('disabled',false).html(html));
      });

      // ========= Abonar =========
      $(document).on('click', 'a.accion-abonar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if(!id) return;

        $('#formAbono')[0].reset();
        $('#abono_id_prestamo').val(id);
        $('#abono_hint_saldo').text('');

        cargarFormasPagoAbono();

        $.post(PRESTAMOS_URL, {accion:'detalle', id_prestamo:id}, function(resp){
          const p = resp?.data?.prestamo || null;
          const saldo = Number(p?.saldo || 0);

          const $inpMonto = $('#formAbono [name=monto]');
          $inpMonto.attr('max', saldo > 0 ? saldo : 0);
          $inpMonto.data('saldo', saldo);
          $inpMonto.attr('placeholder', saldo > 0 ? `Máximo: ${mxn(saldo)}` : 'Sin saldo');

          if ($('#abono_hint_saldo').length) $('#abono_hint_saldo').text(saldo>0 ? `Saldo disponible: ${mxn(saldo)}` : 'Saldo agotado');

          $('#modalAbonar').modal('show');
        }, 'json').fail(()=>{
          toastr.error('No se pudo obtener el saldo del préstamo.');
        });
      });

      // Validación y envío del abono
      $('#formAbono').on('submit', function(e){
        e.preventDefault();

        const $inpMonto = $('#formAbono [name=monto]');
        const monto = Number($inpMonto.val() || 0);
        const saldo = Number($inpMonto.data('saldo') || 0);

        if (saldo <= 0) {
          Swal.fire({icon:'error', title:'Sin saldo', text:'Este préstamo no admite más abonos.'});
          return;
        }
        if (!monto || monto <= 0) {
          Swal.fire({icon:'warning', title:'Monto inválido', text:'Ingresa un monto mayor a 0.'});
          return;
        }
        if (monto > saldo) {
          Swal.fire({
            icon:'error',
            title:'Monto excede el saldo',
            html:`El abono no puede ser mayor al saldo restante.<br>Saldo: <b>${mxn(saldo)}</b>`
          });
          return;
        }

        const form = $(this).serializeArray();
        form.push({name:'accion', value:'abonar'});

        const $btn = $('#formAbono button[type=submit]'), html=$btn.html();
        $btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');
        $.post(PRESTAMOS_URL, form, function(r){
          if(r && r.ok){
            Swal.fire({icon:'success', title:'Abono registrado', timer:1400, showConfirmButton:false});
            $('#modalAbonar').modal('hide');
            cargarPrestamos(paginaActual);
          } else {
            Swal.fire({icon:'error', title:'No se pudo registrar el abono', text: r?.msg || 'Error desconocido'});
          }
        },'json').fail(()=>{
          Swal.fire({icon:'error', title:'Error de comunicación'});
        }).always(()=>{
          $btn.prop('disabled',false).html(html);
        });
      });

      // ========= Detalle =========
      $(document).on('click','a.accion-detalle',function(e){
        e.preventDefault();
        const id=$(this).data('id'); if(!id) return;

        $('#det-error').hide().empty();
        $('#det-contenido').hide(); $('#det-loader').show();
        $('#modalDetalle').modal('show');

        $.post(PRESTAMOS_URL,{accion:'detalle', id_prestamo:id}, function(resp){
          const p = resp?.data?.prestamo || null;
          const abs = resp?.data?.abonos || [];
          if(!p){
            $('#det-loader').hide();
            $('#det-error').show().text('No se encontró el registro.');
            return;
          }

          $('#det-folio').text(p.id_prestamo);
          $('#det-tipo').text(tipoOperacionLabel(p.tipo_operacion));
          $('#det-estatus').text(p.estatus);

          const benefDet = (function(){
            const nombre = (p.beneficiario_nombre || '').trim();
            if (nombre) return nombre;

            if (p.tipo==='Otro'){
              const extra = parseBeneficiarioDesdeConcepto(p.concepto);
              return extra ? extra : 'Otro';
            }

            if (p.tipo==='Cliente') return 'Cliente sin nombre';
            if (p.tipo==='Empleado') return 'Empleado sin nombre';

            return p.tipo || '—';
          })();

          $('#det-beneficiario').text(benefDet);
          $('#det-monto').text(mxn(p.monto_total));
          $('#det-saldo').text(mxn(p.saldo));
          $('#det-fecha').text(fechaMx(p.fecha_prestamo));
          $('#det-usuario').text(p.usuario_nombre || p.usuario || (p.id_usuario ? `ID ${p.id_usuario}` : '—'));
          $('#det-sucursal').text(p.sucursal_nombre || p.sucursal || '—');

          let tb = '';
          if(!abs.length){
            tb = '<tr><td colspan="6" class="text-center text-muted">Sin abonos</td></tr>';
          } else {
            tb = abs.map(a => `
              <tr>
                <td>${a.id_abono}</td>
                <td>${fechaMx(a.fecha_abono)}</td>
                <td class="text-right">${mxn(a.monto)}</td>
                <td>${a.forma_pago_desc || '—'}</td>
                <td>${a.referencia_pago || ''}</td>
                <td>${a.usuario_nombre || '—'}</td>
              </tr>
            `).join('');
          }
          $('#det-tbody').html(tb);

          $('#det-loader').hide(); $('#det-contenido').show();
        },'json').fail(()=>{
          $('#det-loader').hide();
          $('#det-error').show().text('Error al cargar el detalle.');
        });
      });

      // ========= Cancelar =========
      $(document).on('click','a.accion-cancelar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if(!id) return;

        Swal.fire({
          title: '¿Cancelar este registro?',
          text: 'Esta acción marcará el préstamo/disposición como cancelado.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, cancelar',
          cancelButtonText: 'No, cerrar'
        }).then((res)=>{
          if(res.isConfirmed){
            $.post(PRESTAMOS_URL,{accion:'cancelar', id_prestamo:id}, r=>{
              if(r && r.ok){
                Swal.fire({icon:'success', title:'Registro cancelado', timer:1200, showConfirmButton:false});
                cargarPrestamos(paginaActual);
              } else {
                Swal.fire({icon:'error', title:'No se pudo cancelar', text:r?.msg||'Error desconocido'});
              }
            },'json').fail(()=> Swal.fire({icon:'error', title:'Error de comunicación'}));
          }
        });
      });

      // ========= Eliminar (lógico) =========
      $(document).on('click','a.accion-eliminar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if(!id) return;

        Swal.fire({
          title: '¿Eliminar este registro?',
          text: 'Se realizará un borrado lógico del registro.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'No, cerrar'
        }).then((res)=>{
          if(res.isConfirmed){
            $.post(PRESTAMOS_URL,{accion:'eliminar', id_prestamo:id}, r=>{
              if(r && r.ok){
                Swal.fire({icon:'success', title:'Registro eliminado', timer:1200, showConfirmButton:false});
                cargarPrestamos(paginaActual);
              } else {
                Swal.fire({icon:'error', title:'No se pudo eliminar', text:r?.msg||'Error desconocido'});
              }
            },'json').fail(()=> Swal.fire({icon:'error', title:'Error de comunicación'}));
          }
        });
      });

      // Evitar scroll horizontal al abrir el dropdown de acciones
      $('#tablaPrestamos')
        .on('show.bs.dropdown', '.dropdown', function(){
          $(this).closest('.table-responsive-lg, .table-responsive').css('overflow-x','visible');
        })
        .on('hide.bs.dropdown', '.dropdown', function(){
          $(this).closest('.table-responsive-lg, .table-responsive').css('overflow-x','auto');
        });

      // ============== INICIO ==============
      // Por si el navegador autocompleta la fecha, la limpiamos:
      $('#Fecha').val('');
      cargarPrestamos(paginaActual);
    });
    </script>
  </body>
</html>
