<?php
$titulo = "Utilidades";
$modulo = "Comparador";
$subtitulo = "";
session_start();

require_once __DIR__ . '/../../../includes/config.php';

if (!isset($_SESSION['usuario'])) {
  header('Location: ' . BASE_URL . '/views/public/index.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Comparador | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

  <!-- plugin css -->
  <link href="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />

  <!-- App css -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <style>
    .table-responsive { overflow-y: visible !important; }
    .best-pb { outline: 2px solid #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.25); }
    .best-pt { outline: 2px solid #28a745; box-shadow: 0 0 0 3px rgba(40,167,69,.25); }
    .cell-ppv { min-width: 150px; }
  </style>
</head>

<body>
  <!-- Navigation Bar-->
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>
  <!-- End Navigation Bar-->

  <div class="wrapper">
    <!-- Loader -->
    <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
      <div class="loader"><div class="loader__figure"></div><p class="loader__label">Cargando...</p></div>
    </div>

    <div class="container-fluid">
      <!-- breadcrumb -->
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- =================== Parámetros =================== -->
      <div class="card-header" style="border-color:darkgray; border-style:dotted;">
        <h5>Parámetros</h5>
        <div class="row">
          <div class="col-lg-12">
            <div class="row">
              <!-- PPV -->
              <div class="col-md-4">
                <div class="form-group">
                  <label for="ppvGeneral" class="control-label">Precio proveedor (PPV) general</label>
                  <div class="input-group">
                    <input type="number" step="0.01" min="0" id="ppvGeneral" class="form-control" placeholder="0.00">
                    <div class="input-group-append">
                      <span class="input-group-text" style="cursor:pointer" onclick="clearField('ppvGeneral')">
                        <i class="mdi mdi-close-circle text-danger"></i>
                      </span>
                    </div>
                  </div>
                  <small class="text-muted">Se copiará a las filas que no hayas editado manualmente.</small>
                </div>
              </div>

              <!-- Botones -->
              <div class="col-md-8 d-flex align-items-end justify-content-end">
                <div class="btn-group">
                  <button id="btnAdd" class="btn btn-primary"><i class="mdi mdi-plus"></i> Agregar proveedor</button>
                  <button id="btnClear" class="btn btn-light"><i class="mdi mdi-eraser"></i> Limpiar</button>
                </div>
              </div>
            </div><!--/row-->
          </div>
        </div>
      </div>
      <!-- =================== /Parámetros =================== -->

      <!-- =================== Tabla comparador =================== -->
      <div class="row mt-3">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title"><i class="mdi mdi-compare"></i> Comparador de proveedores</h4>
            </div>

            <p class="text-muted mb-2">
              Se calcula por proveedor: <strong>CN</strong>, <strong>PB</strong>, <strong>PT</strong> y sus
              utilidades/márgenes:
              <code>Utilidad PB = PB − CN</code>, <code>Margen PB = Utilidad PB / PB</code>;
              <code>Utilidad PT = PT − CN</code>, <code>Margen PT = Utilidad PT / PT</code>.
            </p>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-striped mb-1">
                <thead>
                  <tr>
                    <th width="32%">Proveedor</th>
                    <th class="text-right cell-ppv">PPV (proveedor)</th>
                    <th class="text-right">Costo Neto</th>
                    <th class="text-right">Precio Publico</th>
                    <th class="text-right">Precio Taller</th>
                    <th class="text-right">Utilidad PB</th>
                    <th class="text-right">Margen PB</th>
                    <th class="text-right">Utilidad PT</th>
                    <th class="text-right">Margen PT</th>
                    <th class="text-center">—</th>
                  </tr>
                </thead>
                <tbody id="tbodyComp"></tbody>
                <tfoot>
                  <tr>
                    <td colspan="10" class="text-muted">
                      <div id="resumenPB" class="mb-1"></div>
                      <div id="resumenPT"></div>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

          </div>
        </div>
      </div>
      <!-- =================== /Tabla comparador =================== -->

    </div><!-- /.container-fluid -->
  </div><!-- /.wrapper -->

  <!-- Footer -->
  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <div class="rightbar-overlay"></div>

  <!-- Vendor js -->
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <!-- App js-->
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <!-- Toastr -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(function(){
      const URL_PROV = '<?= BASE_URL ?>/controllers/ProveedoresController.php';
      const $tbody   = $('#tbodyComp');
      const $ppvGen  = $('#ppvGeneral');
      const $resPB   = $('#resumenPB');
      const $resPT   = $('#resumenPT');

      let PROVEEDORES = [];
      let lastPPVGeneral = 0; // Para propagar solo a filas no editadas

      /* ========= Reglas JS (réplica exacta de tu PHP) ========= */
      function calcularPreciosPorProveedor(ppv, provNombre){
        ppv = Math.max(0, Number(ppv||0));
        const nom = String(provNombre||'').trim().toLowerCase();
        const IVA = 1.16;

        // Defaults
        let CN = ppv * IVA;
        let PB = (ppv * 1.8) * IVA;
        let PT = PB * 0.8;

        switch(nom){
          case 'permor':
            CN = ppv * 0.64 * IVA * 0.89 * 0.95;
            PB = ppv * 1.024;
            PT = PB / 1.25;
            break;

          case 'apymsa':
            CN = ppv * 1.044;
            PB = ppv * 1.70694;
            PT = ppv * 1.365552; // (= PB / 1.25)
            break;

          case 'bdh':
            CN = ppv;
            PB = ppv * IVA;
            PT = ppv;
            break;

          case 'switchero':
            CN = ppv;
            PB = ppv * 1.8125;
            PT = ppv * 1.45;
            break;

          case 'serva':
          case 'dirco':
          case 'ciosa':
          case 'diriego':
          case 'delatsa':
          case 'calderon':
          case 'visa':
            CN = ppv * IVA;
            PB = (ppv * 1.8) * IVA;
            PT = PB * 0.8;
            break;
        }
        const r2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
        return [r2(CN), r2(PB), r2(PT)];
      }

      /* ========= Utils ========= */
      const num = v => { const n = parseFloat(v); return isNaN(n)?0:n; };
      const fmt = n => '$' + Number(n||0).toFixed(2);

      function cargarProveedores(){
        return $.ajax({
          url: URL_PROV, method:'GET', dataType:'json',
          data:{accion:'listar-min', limite:500}
        })
        .done(resp => { PROVEEDORES = resp?.data || []; })
        .fail(() => {
          PROVEEDORES = [];
          toastr.warning('No se pudieron cargar proveedores. Se usará una lista mínima.');
        });
      }

      function makeSelectProveedor(selectedText){
        const $sel = $('<select class="form-control form-control-sm"></select>');
        if (PROVEEDORES.length){
          PROVEEDORES.forEach(p=>{
            const nombre = p.nombre ?? p.razon_social ?? 'Proveedor';
            $sel.append($('<option>').val(nombre).text(nombre));
          });
        } else {
          ['Apymsa','Permor','BDH','Switchero','Serva','Dirco','CIOSA','Diriego','Delatsa','Calderon','Visa']
            .forEach(n => $sel.append($('<option>').val(n).text(n)));
        }
        if (selectedText) $sel.val(selectedText);
        return $sel;
      }

      function addRow(prefill={}){
        const $tr = $('<tr>');

        // === Proveedor (select) ===
        const $tdProv = $('<td>');
        const $selProv = makeSelectProveedor(prefill.proveedor);
        $tdProv.append($selProv);

        // === PPV por proveedor (editable) ===
        const $tdPPV = $('<td class="text-right cell-ppv">');
        const $ppvInput = $('<input type="number" step="0.01" min="0" class="form-control form-control-sm ppv-row" placeholder="0.00">');

        // Si hay PPV general, se inicializa con ese valor
        const ppvInicial = String($ppvGen.val() ?? '');
        if (ppvInicial !== '') $ppvInput.val(ppvInicial);

        // Flag "dirty": false al crear, true al editar manualmente
        $tr.data('ppvDirty', false);

        // Marcar dirty al teclear y recalcular tabla
        $ppvInput.on('input', function(){
          $tr.data('ppvDirty', true);
          recalc();
        });

        $tdPPV.append($ppvInput);

        // === Celdas calculadas ===
        const $tdCN   = $('<td class="text-right">').text('$0.00');
        const $tdPB   = $('<td class="text-right">').text('$0.00');
        const $tdPT   = $('<td class="text-right">').text('$0.00');
        const $tdUPB  = $('<td class="text-right">').text('$0.00');
        const $tdMPB  = $('<td class="text-right">').text('0.00%');
        const $tdUPT  = $('<td class="text-right">').text('$0.00');
        const $tdMPT  = $('<td class="text-right">').text('0.00%');

        // === Acciones ===
        const $tdAcc  = $('<td class="text-center">');
        const $btnDel = $('<button type="button" class="btn btn-sm btn-outline-danger"><i class="mdi mdi-delete"></i></button>');
        $btnDel.on('click', ()=>{ $tr.remove(); recalc(); });
        $tdAcc.append($btnDel);

        $tr.append($tdProv,$tdPPV,$tdCN,$tdPB,$tdPT,$tdUPB,$tdMPB,$tdUPT,$tdMPT,$tdAcc);
        $tbody.append($tr);

        // Recalcular al cambiar proveedor
        $selProv.on('change', recalc);

        recalc();
      }

      // Obtiene PPV efectivo para una fila: usa el input de la fila, si no hay usa el general
      function getRowPPV($tr){
        const $inp = $tr.find('input.ppv-row');
        const val = $inp.length ? $inp.val() : '';
        if (val === '' || isNaN(parseFloat(val))) {
          return num($ppvGen.val());
        }
        return num(val);
      }

      function recalc(){
        let bestPB = {uti:-Infinity, tr:null, prov:'', cn:0, pb:0, margen:0};
        let bestPT = {uti:-Infinity, tr:null, prov:'', cn:0, pt:0, margen:0};

        $tbody.find('tr').removeClass('best-pb best-pt');

        $tbody.find('tr').each(function(){
          const $tr  = $(this);
          const prov = $tr.find('td:eq(0) select').val() || '';
          const ppvR = getRowPPV($tr);

          const [CN, PB, PT] = calcularPreciosPorProveedor(ppvR, prov);

          // Utilidades y márgenes
          const utilPB = PB - CN;
          const margPB = PB > 0 ? (utilPB / PB) * 100 : 0;

          const utilPT = PT - CN;
          const margPT = PT > 0 ? (utilPT / PT) * 100 : 0;

          // Pintar (ojo con los índices por la nueva columna PPV)
          $tr.find('td:eq(2)').text(fmt(CN));                                  // CN
          $tr.find('td:eq(3)').text(fmt(PB));                                  // PB
          $tr.find('td:eq(4)').text(fmt(PT));                                  // PT
          $tr.find('td:eq(5)').text(fmt(utilPB));                              // U PB
          $tr.find('td:eq(6)').text(isFinite(margPB)? margPB.toFixed(2)+'%' : '0.00%'); // M PB
          $tr.find('td:eq(7)').text(fmt(utilPT));                              // U PT
          $tr.find('td:eq(8)').text(isFinite(margPT)? margPT.toFixed(2)+'%' : '0.00%'); // M PT

          // Mejores
          if (utilPB > bestPB.uti){
            bestPB = {uti:utilPB, tr:$tr, prov, cn:CN, pb:PB, margen:margPB};
          }
          if (utilPT > bestPT.uti){
            bestPT = {uti:utilPT, tr:$tr, prov, cn:CN, pt:PT, margen:margPT};
          }
        });

        // Resaltados y resumen
        if (bestPB.tr){
          bestPB.tr.addClass('best-pb');
          $resPB.html(`<strong>Mejor por PB:</strong> ${bestPB.prov} • CN ${fmt(bestPB.cn)} • PB ${fmt(bestPB.pb)} • Utilidad ${fmt(bestPB.uti)} • Margen ${isFinite(bestPB.margen)?bestPB.margen.toFixed(2):'0.00'}%`);
        } else { $resPB.empty(); }

        if (bestPT.tr){
          bestPT.tr.addClass('best-pt');
          $resPT.html(`<strong>Mejor por PT:</strong> ${bestPT.prov} • CN ${fmt(bestPT.cn)} • PT ${fmt(bestPT.pt)} • Utilidad ${fmt(bestPT.uti)} • Margen ${isFinite(bestPT.margen)?bestPT.margen.toFixed(2):'0.00'}%`);
        } else { $resPT.empty(); }
      }

      // Botones
      $('#btnAdd').on('click', ()=> addRow());
      $('#btnClear').on('click', ()=>{
        $tbody.empty();
        $resPB.empty(); $resPT.empty();
        $ppvGen.val('');
        lastPPVGeneral = 0;
      });

      // Al cambiar PPV general:
      //  - Se actualiza en TODAS las filas que NO estén "dirty" (no editadas manualmente)
      //  - Las filas dirty conservan su PPV propio
      $ppvGen.on('input', function(){
        const nuevo = num($(this).val());
        $tbody.find('tr').each(function(){
          const $tr = $(this);
          const isDirty = !!$tr.data('ppvDirty');
          if (!isDirty) {
            const $inp = $tr.find('input.ppv-row');
            $inp.val(nuevo === 0 ? '0' : String(nuevo));
          }
        });
        lastPPVGeneral = nuevo;
        recalc();
      });

      // Carga inicial
      $.when(cargarProveedores()).always(function(){
        addRow(); addRow(); addRow(); // 3 filas de inicio
      });
    });

    // util: limpiar input + disparar evento
    function clearField(id){
      const el = document.getElementById(id);
      if (!el) return;
      el.value = '';
      el.dispatchEvent(new Event('input'));
    }
  </script>
</body>
</html>
