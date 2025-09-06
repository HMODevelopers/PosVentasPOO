<?php
$titulo = "Ventas";
$modulo = "Préstamos / Disposiciones";
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
      .text-center{ text-align:center!important; }
      .text-right{ text-align:right!important; }
      .clean-filter{ display:none; } /* se muestra cuando hay valor */
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
                      <input type="date" id="Fecha" class="form-control filtrar" value="<?= htmlspecialchars($hoy) ?>">
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
                      <option value="Cancelado">Cancelado</option>
                      <option value="SinRetorno">SinRetorno</option>
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
                <h4 class="header-title mb-0">Listado de Préstamos / Disposiciones</h4>
                <button id="btnNuevo" class="btn btn-primary btn-sm">
                  <i class="mdi mdi-plus"></i> Nuevo
                </button>
              </div>

              <div class="table-responsive">
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

    <script>
    // helper para íconos "limpiar"
    function clearField(id){ try { $('#'+id).val('').trigger('change'); } catch(e){} }

    $(function(){
      const BASE = BASE_URL;
      const PRESTAMOS_URL = `${BASE}/controllers/PrestamosController.php`;
      const CLIENTES_URL  = `${BASE}/controllers/ClientesController.php`;
      const EMPLEADOS_URL = `${BASE}/controllers/EmpleadosController.php`; // opcional si lo tienes

      const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
      const fechaMx = dt => { try{ const d=new Date(String(dt).replace(' ','T')); return d.toLocaleDateString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric'}); } catch { return dt||'—'; } };

      // =================== LISTADO ===================
      let paginaActual=1; const limitePorPagina=10;

      function badgeTipo(op){
        return (op==='Prestamo')
          ? '<span class="badge-soft badge-prestamo">Préstamo</span>'
          : '<span class="badge-soft badge-dispo">Disposición</span>';
      }
      function badgeEstatus(s){
        const m = {
          'Pendiente':'badge-light-warning',
          'Pagado':'badge-light-success',
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
        if (r.tipo==='Cliente' && r.id_cliente)   return `Cliente #${r.id_cliente}`;
        if (r.tipo==='Empleado' && r.id_empleado) return `Empleado #${r.id_empleado}`;
        if (r.tipo==='Otro'){
          const extra = parseBeneficiarioDesdeConcepto(r.concepto);
          return extra ? `Otro: ${extra}` : 'Otro';
        }
        return r.tipo || '—';
      }

      function accionesFila(r){
        let out = `
          <a class="dropdown-item accion-detalle" href="#" data-id="${r.id_prestamo}">
            <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver detalle
          </a>`;
        if (r.tipo_operacion==='Prestamo' && r.estatus!=='Pagado' && Number(r.saldo)>0){
          out += `
          <a class="dropdown-item accion-abonar" href="#" data-id="${r.id_prestamo}">
            <i class="mdi mdi-cash-plus mr-2 text-muted font-18 vertical-middle"></i>Abonar
          </a>`;
        }
        out += `
          <a class="dropdown-item accion-cancelar" href="#" data-id="${r.id_prestamo}">
            <i class="mdi mdi-cancel mr-2 text-muted font-18 vertical-middle"></i>Cancelar
          </a>
          <a class="dropdown-item accion-eliminar" href="#" data-id="${r.id_prestamo}">
            <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Eliminar
          </a>`;
        return out;
      }

      function cargarPrestamos(pagina){
        const q      = $('#Folio').val() || '';
        const fecha  = $('#Fecha').val() || '';
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
                    <div class="btn-group dropdown">
                      <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown">
                        <i class="mdi mdi-dots-horizontal"></i>
                      </a>
                      <div class="dropdown-menu dropdown-menu-right">
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

      // Filtros (mismo comportamiento que Ventas)
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
      function cargarEmpleados(selected){
        $.post(EMPLEADOS_URL, {accion:'listar-min', limite:200})
          .done(r=>{
            const data = r?.data || [];
            const mapped = data.map(e=>({ id: e.id_empleado ?? e.id, nombre: e.nombre ?? e.fullname ?? e.nombre_completo }));
            setSelect($('#selEmpleado'), mapped, 'id','nombre');
            if (selected!=null) $('#selEmpleado').val(String(selected));
          })
          .fail(()=> setSelect($('#selEmpleado'), [], 'id','nombre'));
      }

      // Mostrar/ocultar inputs según tipo de beneficiario
      function toggleBenefWrappers(){
        const t = $('#tipo').val();
        $('#wrapCliente').toggleClass('d-none', t!=='Cliente');
        $('#wrapEmpleado').toggleClass('d-none', t!=='Empleado');
        $('#wrapOtro').toggleClass('d-none', t!=='Otro');

        if (t==='Cliente') { cargarClientes(); $('#selEmpleado').val(''); $('#txtOtro').val(''); }
        if (t==='Empleado'){ cargarEmpleados(); $('#selCliente').val(''); $('#txtOtro').val(''); }
        if (t==='Otro')    { $('#selCliente').val(''); $('#selEmpleado').val(''); }
      }

      // ========= Nuevo =========
      $('#btnNuevo').on('click', ()=> {
        $('#formNuevo')[0].reset();
        $('#tipo').val('Cliente'); // default
        toggleBenefWrappers();
        $('#modalNuevo').modal('show');
      });
      $('#tipo').on('change', toggleBenefWrappers);

      // Envío de "Nuevo": cliente/empleado opcional, y "Otro" inyecta en concepto
      $('#formNuevo').on('submit', function(e){
        e.preventDefault();

        const arr = $(this).serializeArray();
        const data = {};
        arr.forEach(x=> data[x.name]=x.value);

        // Quitar ids vacíos para no ensuciar payload
        if (!data.id_cliente)  delete data.id_cliente;
        if (!data.id_empleado) delete data.id_empleado;

        // Si es "Otro", anexar nombre al concepto sin tocar backend/modelo
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
        const id=$(this).data('id'); if(!id) return;
        $('#formAbono')[0].reset();
        $('#abono_id_prestamo').val(id);
        $('#modalAbonar').modal('show');
      });

      $('#formAbono').on('submit', function(e){
        e.preventDefault();
        const form = $(this).serializeArray();
        form.push({name:'accion', value:'abonar'});
        const $btn = $('#formAbono button[type=submit]'), html=$btn.html();
        $btn.prop('disabled',true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');
        $.post(PRESTAMOS_URL, form, function(r){
          if(r && r.ok){ toastr.success('Abono registrado.'); $('#modalAbonar').modal('hide'); cargarPrestamos(paginaActual); }
          else { toastr.error(r?.msg || 'No se pudo registrar el abono.'); }
        },'json').fail(()=> toastr.error('Error de comunicación.'))
        .always(()=> $btn.prop('disabled',false).html(html));
      });

      // ========= Detalle =========
      $(document).on('click','a.accion-detalle',function(e){
        e.preventDefault();
        const id=$(this).data('id'); if(!id) return;
        $('#det-error').addClass('d-none').empty();
        $('#det-contenido').hide(); $('#det-loader').show();
        $('#modalDetalle').modal('show');

        $.post(PRESTAMOS_URL,{accion:'detalle', id_prestamo:id}, function(resp){
          const p = resp?.data?.prestamo || null;
          const abs = resp?.data?.abonos || [];
          if(!p){ $('#det-loader').hide(); $('#det-error').removeClass('d-none').text('No se encontró el registro.'); return; }

          $('#det-folio').text(p.id_prestamo);
          $('#det-tipo').html(p.tipo_operacion==='Prestamo' ? 'Préstamo' : 'Disposición');
          $('#det-estatus').text(p.estatus);

          const benefDet = (function(){
            if (p.tipo==='Cliente' && p.id_cliente) return `Cliente #${p.id_cliente}`;
            if (p.tipo==='Empleado' && p.id_empleado) return `Empleado #${p.id_empleado}`;
            if (p.tipo==='Otro'){
              const extra = parseBeneficiarioDesdeConcepto(p.concepto);
              return extra ? `Otro: ${extra}` : 'Otro';
            }
            return p.tipo || '—';
          })();

          $('#det-benef').text(benefDet);
          $('#det-monto').text(mxn(p.monto_total));
          $('#det-saldo').text(mxn(p.saldo));
          $('#det-fecha').text(fechaMx(p.fecha_prestamo));

          let tb='';
          if(!abs.length){ tb='<tr><td colspan="4" class="text-center text-muted">Sin abonos</td></tr>'; }
          else {
            tb = abs.map(a=> `<tr><td>${a.id_abono}</td><td>${fechaMx(a.fecha_abono)}</td><td>${mxn(a.monto)}</td><td>${a.referencia_pago||''}</td></tr>`).join('');
          }
          $('#det-tbody').html(tb);

          $('#det-loader').hide(); $('#det-contenido').show();
        },'json').fail(()=> { $('#det-loader').hide(); $('#det-error').removeClass('d-none').text('Error al cargar el detalle.'); });
      });

      // ========= Cancelar / Eliminar =========
      $(document).on('click','a.accion-cancelar', function(e){
        e.preventDefault();
        if(!confirm('¿Cancelar este registro?')) return;
        const id=$(this).data('id');
        $.post(PRESTAMOS_URL,{accion:'cancelar', id_prestamo:id}, r=>{
          if(r && r.ok){ toastr.success('Registro cancelado.'); cargarPrestamos(paginaActual); }
          else { toastr.error(r?.msg||'No se pudo cancelar.'); }
        },'json').fail(()=> toastr.error('Error de comunicación.'));
      });

      $(document).on('click','a.accion-eliminar', function(e){
        e.preventDefault();
        if(!confirm('¿Eliminar (lógico) este registro?')) return;
        const id=$(this).data('id');
        $.post(PRESTAMOS_URL,{accion:'eliminar', id_prestamo:id}, r=>{
          if(r && r.ok){ toastr.success('Registro eliminado.'); cargarPrestamos(paginaActual); }
          else { toastr.error(r?.msg||'No se pudo eliminar.'); }
        },'json').fail(()=> toastr.error('Error de comunicación.'));
      });

      // ============== INICIO ==============
      cargarPrestamos(paginaActual);
    });
    </script>
  </body>
</html>
