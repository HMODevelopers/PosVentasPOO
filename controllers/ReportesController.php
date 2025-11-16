<?php
// controllers/ReportesController.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../models/ReporteModel.php';
$model = new ReporteModel();

/* ======================= Helpers ======================= */

/**
 * Responder JSON (sin interferir con descargas)
 */
function send_json($payload, $code = 200) {
  if (!headers_sent()) header('Content-Type: application/json; charset=UTF-8');
  http_response_code($code);
  echo json_encode($payload);
}

/**
 * Limpia buffers / desactiva compresión antes de descargar
 * para evitar que se corrompa el ZIP del XLSX.
 */
function clean_output_before_download() {
  while (ob_get_level() > 0) { ob_end_clean(); }
  if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
  @ini_set('zlib.output_compression', 'Off');
  @ini_set('output_buffering', '0');
}

/**
 * Carga SimpleXLSXGen o sale con error JSON
 */
function require_simplexlsxgen_or_fail() {
  $lib = __DIR__ . '/../assets/libs/simplexlsxgen/SimpleXLSXGen.php';
  if (!is_file($lib)) {
    send_json(['ok'=>false,'msg'=>'Falta SimpleXLSXGen.php','ruta'=>$lib], 500);
    exit;
  }
  require_once $lib; // \Shuchkin\SimpleXLSXGen
}

/**
 * Helper genérico para descargar XLSX con SimpleXLSXGen
 * @param string $filename
 * @param array<string> $headers
 * @param array<array> $rows
 * @param array|null $footer
 */
function xlsx_download($filename, array $headers, array $rows, array $footer = null) {
  require_simplexlsxgen_or_fail();

  $data = [];
  $data[] = $headers;
  foreach ($rows as $r) { $data[] = $r; }
  if ($footer) { $data[] = $footer; }

  clean_output_before_download();
  \Shuchkin\SimpleXLSXGen::fromArray($data)->downloadAs($filename);
  exit;
}

/* ======================= Router ======================= */
$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

  /* ============================================================
   *  CRÉDITOS POR CLIENTE - LISTADO
   * ============================================================ */
  case 'creditos-cliente-listar':
    $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
    $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 20);

    $desde = trim($_REQUEST['desde'] ?? $_REQUEST['del'] ?? $_REQUEST['fecha'] ?? '');
    $hasta = trim($_REQUEST['hasta'] ?? $_REQUEST['al']  ?? $_REQUEST['fecha'] ?? '');

    $filtros = [
      'id_cliente' => (int)($_REQUEST['id_cliente'] ?? 0),
      'desde'      => $desde,
      'hasta'      => $hasta,
      'q'          => trim($_REQUEST['q'] ?? '')
    ];

    if ($filtros['id_cliente'] <= 0) { send_json(['ok'=>false,'msg'=>'id_cliente requerido'], 400); break; }

    $data  = $model->listarCreditoClienteItems($pagina, $limite, $filtros);
    $total = $model->contarCreditoClienteItems($filtros);

    send_json(['ok'=>true, 'data'=>$data, 'total'=>$total]);
  break;

  /* ============================================================
   *  CRÉDITOS POR CLIENTE - TOTALES
   * ============================================================ */
  case 'creditos-cliente-totales':
    $desde = trim($_REQUEST['desde'] ?? $_REQUEST['del'] ?? $_REQUEST['fecha'] ?? '');
    $hasta = trim($_REQUEST['hasta'] ?? $_REQUEST['al']  ?? $_REQUEST['fecha'] ?? '');

    $filtros = [
      'id_cliente' => (int)($_REQUEST['id_cliente'] ?? 0),
      'desde'      => $desde,
      'hasta'      => $hasta,
      'q'          => trim($_REQUEST['q'] ?? '')
    ];
    if ($filtros['id_cliente'] <= 0) { send_json(['ok'=>false,'msg'=>'id_cliente requerido'], 400); break; }

    $tot = $model->totalesCreditoClienteDia($filtros);
    send_json(['ok'=>true,'totales'=>$tot]);
  break;

  /* ============================================================
   *  CRÉDITOS POR CLIENTE - CSV
   * ============================================================ */
  case 'creditos-cliente-csv':
    $desde = trim($_REQUEST['desde'] ?? $_REQUEST['del'] ?? $_REQUEST['fecha'] ?? '');
    $hasta = trim($_REQUEST['hasta'] ?? $_REQUEST['al']  ?? $_REQUEST['fecha'] ?? '');

    $filtros = [
      'id_cliente' => (int)($_GET['id_cliente'] ?? 0),
      'desde'      => $desde,
      'hasta'      => $hasta,
      'q'          => trim($_GET['q'] ?? '')
    ];
    if ($filtros['id_cliente'] <= 0) { send_json(['ok'=>false,'msg'=>'id_cliente requerido'], 400); break; }

    $rows  = $model->listarCreditoClienteItemsTodo($filtros);
    $csv   = $model->csvCreditoClienteItems($rows);
    $fname = 'ventas_credito_items_cliente_'.$filtros['id_cliente'].'_'.date('Ymd_His').'.csv';

    clean_output_before_download();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo $csv;
    exit;
  break;

  /* ============================================================
   *  CRÉDITOS POR CLIENTE - XLSX (SimpleXLSXGen)
   * ============================================================ */
  case 'creditos-cliente-xls':
    $desde = trim($_REQUEST['desde'] ?? $_REQUEST['del'] ?? '');
    $hasta = trim($_REQUEST['hasta'] ?? $_REQUEST['al']  ?? '');
    $filtros = [
      'id_cliente' => (int)($_GET['id_cliente'] ?? 0),
      'desde'      => $desde,
      'hasta'      => $hasta,
      'q'          => trim($_GET['q'] ?? '')
    ];
    if ($filtros['id_cliente'] <= 0) { send_json(['ok'=>false,'msg'=>'id_cliente requerido'], 400); break; }

    $rows = $model->listarCreditoClienteItemsTodo($filtros);
    $tot  = $model->totalesCreditoClienteDia($filtros); // ['total','abonos','saldo']

    $headers = ['Folio','Estatus Crédito','Cantidad','Código','Unidad','Descripción','Precio','Total','Fecha venta'];
    $outRows = [];
    foreach ($rows as $r) {
      $fecha = $r['fecha_venta'] ?? $r['fecha'] ?? '';
      $outRows[] = [
        (string)($r['folio'] ?? ''),
        (string)($r['estatus_credito'] ?? ''),
        (float)($r['cantidad'] ?? 0),
        (string)($r['codigo'] ?? ''),
        (string)($r['unidad'] ?? ''),
        (string)($r['descripcion'] ?? ''),
        (float)($r['precio_unitario'] ?? $r['precio'] ?? 0),
        (float)($r['importe'] ?? $r['total'] ?? 0),
        (string)$fecha,
      ];
    }
    $footer = ['', '', '', '', '', 'TOTAL:', '', (float)($tot['total'] ?? 0), ''];

    $fname = 'ventas_credito_items_cliente_'.$filtros['id_cliente'].'_'.date('Ymd_His').'.xlsx';
    xlsx_download($fname, $headers, $outRows, $footer);
  break;

  /* ============================================================
   *  UTILIDADES - LISTADO
   * ============================================================ */
  case 'utilidades-listar':
    $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
    $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 20);
    $modo   = strtolower(trim($_REQUEST['modo'] ?? 'resumen')); // 'resumen' | 'detalle'

    $filtros = [
      'desde'                  => trim($_REQUEST['desde'] ?? ''),
      'hasta'                  => trim($_REQUEST['hasta'] ?? ''),
      'q'                      => trim($_REQUEST['q'] ?? ''),
      'group_by'               => trim($_REQUEST['group_by'] ?? 'dia'),
      'inc_credito'            => (int)($_REQUEST['inc_credito'] ?? 0),
      'solo_credito_liquidado' => (int)($_REQUEST['solo_credito_liquidado'] ?? 1),
      'inc_guardadas'          => (int)($_REQUEST['inc_guardadas'] ?? 0),
    ];

    if ($modo === 'detalle') {
      $data  = $model->listarUtilidadesDetalle($pagina, $limite, $filtros);
      $total = $model->contarUtilidadesDetalle($filtros);
    } else {
      $data  = $model->listarUtilidadesDetalle($pagina, $limite, $filtros);
      $total = $model->contarUtilidadesDetalle($filtros);
    }

    send_json(['ok'=>true,'data'=>$data,'total'=>$total]);
  break;

  /* ============================================================
   *  UTILIDADES - TOTALES
   * ============================================================ */
  case 'utilidades-totales':
    $filtros = [
      'desde'                  => trim($_REQUEST['desde'] ?? ''),
      'hasta'                  => trim($_REQUEST['hasta'] ?? ''),
      'q'                      => trim($_REQUEST['q'] ?? ''),
      'group_by'               => trim($_REQUEST['group_by'] ?? 'dia'),
      'inc_credito'            => (int)($_REQUEST['inc_credito'] ?? 0),
      'solo_credito_liquidado' => (int)($_REQUEST['solo_credito_liquidado'] ?? 1),
      'inc_guardadas'          => (int)($_REQUEST['inc_guardadas'] ?? 0),
    ];
    $tot = $model->totalesUtilidades($filtros);
    send_json(['ok'=>true,'totales'=>$tot]);
  break;

  /* ============================================================
   *  UTILIDADES - XLSX (SimpleXLSXGen)
   * ============================================================ */
  case 'utilidades-xls':
    $modo = strtolower(trim($_REQUEST['modo'] ?? 'resumen'));
    $filtros = [
      'desde'                  => trim($_REQUEST['desde'] ?? ''),
      'hasta'                  => trim($_REQUEST['hasta'] ?? ''),
      'q'                      => trim($_REQUEST['q'] ?? ''),
      'group_by'               => trim($_REQUEST['group_by'] ?? 'dia'),
      'inc_credito'            => (int)($_REQUEST['inc_credito'] ?? 0),
      'solo_credito_liquidado' => (int)($_REQUEST['solo_credito_liquidado'] ?? 1),
      'inc_guardadas'          => (int)($_REQUEST['inc_guardadas'] ?? 0),
    ];

    // Usamos el mismo origen que la vista (detalle por renglón)
    $rows = $model->listarUtilidadesDetalle(1, 100000, $filtros);
    $tot  = $model->totalesUtilidades($filtros);

    $outRows = [];
    if ($modo === 'detalle') {
      $headers = ['Fecha','Folio','Código','Descripción','Unidad','Cantidad','Precio Unit.','Costo Unit.','Ingreso','Costo','Utilidad','Margen'];
      $sumIng = 0; $sumCos = 0; $sumUti = 0;

      foreach ($rows as $r) {
        $ing = (float)($r['ingreso'] ?? 0);
        $cos = (float)($r['costo'] ?? 0);
        $uti = (float)($r['utilidad'] ?? ($ing - $cos));
        $mar = (float)($r['margen'] ?? ($ing > 0 ? $uti / $ing : 0));

        $outRows[] = [
          (string)($r['fecha'] ?? ''),(string)($r['folio'] ?? ''),(string)($r['codigo'] ?? ''),
          (string)($r['descripcion'] ?? ''),(string)($r['unidad'] ?? ''),(float)($r['cantidad'] ?? 0),
          (float)($r['precio_unitario'] ?? 0),(float)($r['costo_unitario'] ?? 0),
          $ing,$cos,$uti,$mar
        ];
        $sumIng += $ing; $sumCos += $cos; $sumUti += $uti;
      }

      $footer = ['','','','','','', 'TOTALES:', '', $sumIng, $sumCos, $sumUti, ($sumIng>0 ? $sumUti/$sumIng : 0)];
    } else {
      // RESUMEN (si lo activas en el futuro)
      $headers = ['Periodo','Código','Descripción','Unidad','Cantidad','Ingreso','Costo','Utilidad','Margen'];
      $sumIng = 0; $sumCos = 0; $sumUti = 0;

      foreach ($rows as $r) {
        $ing = (float)($r['ingreso'] ?? 0);
        $cos = (float)($r['costo'] ?? 0);
        $uti = (float)($r['utilidad'] ?? ($ing - $cos));
        $mar = (float)($r['margen'] ?? ($ing > 0 ? $uti / $ing : 0));

        $outRows[] = [
          (string)($r['periodo'] ?? ''),(string)($r['codigo'] ?? ''),(string)($r['descripcion'] ?? ''),
          (string)($r['unidad'] ?? ''),(float)($r['cantidad'] ?? 0),$ing,$cos,$uti,$mar
        ];
        $sumIng += $ing; $sumCos += $cos; $sumUti += $uti;
      }

      $footer = ['','','','','TOTALES:', $sumIng, $sumCos, $sumUti, ($sumIng>0 ? $sumUti/$sumIng : 0)];
    }

    $fname = 'reporte_utilidades_'.($modo==='detalle'?'detalle_':'resumen_').date('Ymd_His').'.xlsx';
    xlsx_download($fname, $headers, $outRows, $footer);
  break;

  /* ============================================================
   *  DEFAULT
   * ============================================================ */
  default:
    send_json(['ok'=>false,'msg'=>'Acción no válida'], 400);
  break;
}
