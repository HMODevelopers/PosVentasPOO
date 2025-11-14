<?php
// No ponemos header JSON global para no estorbar el Excel.

include_once '../models/InventarioMovimientoModel.php';

$movModel = new InventarioMovimientoModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

  // ===== LISTAR PAGINADO =====
  case 'listar':
    header('Content-Type: application/json; charset=UTF-8');

    $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
    $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

    // Filtros
    $q           = trim($_REQUEST['q']           ?? '');
    $codigo      = trim($_REQUEST['codigo']      ?? '');
    $descripcion = trim($_REQUEST['descripcion'] ?? '');
    $idUsuario   = (isset($_REQUEST['id_usuario']) && $_REQUEST['id_usuario']!=='') ? (int)$_REQUEST['id_usuario'] : null;
    $desde       = trim($_REQUEST['desde'] ?? '');
    $hasta       = trim($_REQUEST['hasta'] ?? '');

    $data  = $movModel->listar($pagina, $limite, $q, $codigo, $descripcion, $idUsuario, $desde, $hasta);
    $total = $movModel->contar($q, $codigo, $descripcion, $idUsuario, $desde, $hasta);

    echo json_encode(['data'=>$data, 'total'=>$total], JSON_UNESCAPED_UNICODE);
  break;

  // ===== DETALLE =====
  case 'detalle':
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_GET['id_movimiento'] ?? $_POST['id_movimiento'] ?? 0);
    if ($id <= 0) {
      echo json_encode(['error'=>'id_movimiento inválido'], JSON_UNESCAPED_UNICODE);
      break;
    }
    $row = $movModel->obtenerPorId($id);
    echo json_encode(['data'=>$row], JSON_UNESCAPED_UNICODE);
  break;

  // ===== EXPORTAR EXCEL =====
  case 'exportar-excel':

    // Autoload de PhpSpreadsheet (igual que en ProductosController)
    $autoload1 = __DIR__ . '/../assets/libs/phpSpreadsheet/vendor/autoload.php';
    $autoload2 = __DIR__ . '/../assets/libs/phpSpreadsheet/autoload.php';
    if (file_exists($autoload1)) {
        require_once $autoload1;
    } elseif (file_exists($autoload2)) {
        require_once $autoload2;
    } else {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok'=>false,'msg'=>'No se encontró PhpSpreadsheet en assets/libs/phpSpreadsheet'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Filtros (mismos que en listar, pero desde GET y sin paginar)
    $q           = trim($_GET['q']           ?? '');
    $codigo      = trim($_GET['codigo']      ?? '');
    $descripcion = trim($_GET['descripcion'] ?? '');
    $idUsuario   = (isset($_GET['id_usuario']) && $_GET['id_usuario']!=='') ? (int)$_GET['id_usuario'] : null;
    $desde       = trim($_GET['desde'] ?? '');
    $hasta       = trim($_GET['hasta'] ?? '');

    $rows = $movModel->listarParaExportar($q, $codigo, $descripcion, $idUsuario, $desde, $hasta);

    // Construir Excel
    $xlsxName = 'movimientos_inventario_' . date('Ymd_His') . '.xlsx';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Movimientos');

    // Encabezados
    $headers = [
        'A1' => 'ID Movimiento',
        'B1' => 'Fecha',
        'C1' => 'Tipo',
        'D1' => 'Código',
        'E1' => 'Producto',
        'F1' => 'Cantidad',
        'G1' => 'Sucursal',
        'H1' => 'Usuario',
        'I1' => 'Referencia',
        'J1' => 'Motivo',
        'K1' => 'Estatus Venta',
        'L1' => 'Cliente (si crédito)'
    ];
    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    // Estilo encabezado
    $sheet->getStyle('A1:L1')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EDEDED']
        ],
        'borders' => [
            'bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]
        ]
    ]);

    // Datos
    $rowNum = 2;
    foreach ($rows as $r) {
        $fecha  = $r['fecha'] ?? $r['fecha_creacion'] ?? '';
        $tipo   = $r['tipo'] ?? '';
        $codigo = $r['codigo'] ?? ('#'.($r['id_producto'] ?? ''));
        $prod   = $r['descripcion'] ?? '';
        $suc    = $r['sucursal'] ?? ($r['id_sucursal'] ? '#'.$r['id_sucursal'] : '');
        $usr    = $r['usuario']  ?? ($r['id_usuario']  ? '#'.$r['id_usuario']  : '');
        $ref    = $r['referencia'] ?? '';
        $mot    = $r['motivo'] ?? '';
        $estV   = $r['estatus_venta'] ?? '';
        $cliCr  = ($estV === 'Credito' || $estV === 'Crédito') ? ($r['cliente'] ?? '') : '';

        $cant  = (float)($r['cantidad'] ?? 0);
        $signo = (int)($r['signo'] ?? 0);
        if ($signo > 0)      { $cantFinal = $cant; }
        elseif ($signo < 0)  { $cantFinal = -$cant; }
        else                 { $cantFinal = $cant; }

        $sheet->setCellValue('A'.$rowNum, (int)($r['id_movimiento'] ?? 0));
        $sheet->setCellValue('B'.$rowNum, $fecha);
        $sheet->setCellValue('C'.$rowNum, $tipo);
        $sheet->setCellValue('D'.$rowNum, $codigo);
        $sheet->setCellValue('E'.$rowNum, $prod);
        $sheet->setCellValue('F'.$rowNum, $cantFinal);
        $sheet->setCellValue('G'.$rowNum, $suc);
        $sheet->setCellValue('H'.$rowNum, $usr);
        $sheet->setCellValue('I'.$rowNum, $ref);
        $sheet->setCellValue('J'.$rowNum, $mot);
        $sheet->setCellValue('K'.$rowNum, $estV);
        $sheet->setCellValue('L'.$rowNum, $cliCr);
        $rowNum++;
    }

    // Autosize columnas
    foreach (range('A','L') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Filtro y freeze
    $sheet->setAutoFilter('A1:L'.($rowNum-1));
    $sheet->freezePane('A2');

    // Descargar
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$xlsxName.'"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    exit;
  break;

  default:
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error'=>'Acción no válida'], JSON_UNESCAPED_UNICODE);
  break;
}
