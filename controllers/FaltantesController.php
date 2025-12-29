<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);
require_once __DIR__ . '/../models/FaltantesModel.php';
$model = new FaltantesModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

  case 'listar':
    header('Content-Type: application/json; charset=UTF-8');

    $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
    $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

    $modo  = $_REQUEST['Modo']  ?? 'rango';
    $desde = $_REQUEST['Desde'] ?? '';
    $hasta = $_REQUEST['Hasta'] ?? '';

    if ($modo !== 'rango') { $desde = ''; $hasta = ''; }

    try {
      if ($modo === 'negativos') {
        $resp = $model->negativosPaginado([], $pagina, $limite);
      } else {
        $resp = $model->faltantesPaginado([
          'modo'  => $modo,
          'desde' => $desde,
          'hasta' => $hasta,
        ], $pagina, $limite);
      }
      echo json_encode(['data' => $resp['data'], 'total' => $resp['total']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['data'=>[], 'total'=>0, 'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
  break;

  case 'exportar-excel':
    // Autoload PhpSpreadsheet (ajusta ruta si difiere)
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

    $modo  = $_REQUEST['Modo']  ?? 'rango';
    $desde = $_REQUEST['Desde'] ?? '';
    $hasta = $_REQUEST['Hasta'] ?? '';

    if ($modo === 'rango' && (!$desde || !$hasta)) {
      header('Content-Type: text/plain; charset=UTF-8', true, 400);
      echo "Faltan 'Desde' y/o 'Hasta' para exportar en modo 'rango'.";
      exit;
    }
    if ($modo !== 'rango') { $desde = ''; $hasta = ''; }

    try {
      if ($modo === 'negativos') {
        $rows = $model->negativosAll();
      } else {
        $rows = $model->faltantesAll([
          'modo'  => $modo,
          'desde' => $desde,
          'hasta' => $hasta,
        ]);
      }

      $xlsxName = 'faltantes_' . date('Ymd_His') . '.xlsx';
      $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      $sheet->setTitle('Faltantes');

      // Encabezados (ahora incluye FOLIO y FECHA VENTA por renglón)
      $headers = [
        'A1' => 'CÓDIGO',
        'B1' => 'UNIDAD',
        'C1' => 'DESCRIPCIÓN',
        'D1' => 'VENDIDO',
        'E1' => 'FOLIO',
        'F1' => 'FECHA VENTA',
        'G1' => 'COMPRÓ (si crédito)',
        'H1' => 'PROVEEDOR',
        'I1' => 'INVENTARIO',
        'J1' => 'FALTANTE vs VENTAS',
        'K1' => 'FALTANTE vs MÍNIMO',
      ];
      foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
      }

      $sheet->getStyle('A1:K1')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => [
          'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        ],
        'fill' => [
          'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
          'startColor' => ['rgb' => 'EDEDED']
        ],
        'borders' => [
          'bottom' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
          ]
        ]
      ]);

      $rowNum = 2;
      foreach ($rows as $r) {
        $vendido    = (float)($r['cantidad'] ?? 0);
        $inventario = (float)($r['inventario'] ?? 0);
        $faltVtas   = (float)($r['faltante_sobre_ventas'] ?? 0);
        $faltMin    = (float)($r['faltante_vs_minimo'] ?? 0);
        $folio      = (string)($r['folio'] ?? '');

        $fecha = $r['fecha_venta'] ?? null;
        if (!empty($fecha)) {
          $dt = date_create($fecha);
          $fecha = $dt ? $dt->format('Y-m-d H:i') : $r['fecha_venta'];
        } else {
          $fecha = '';
        }

        $sheet->setCellValueExplicit(
          'A'.$rowNum,
          (string)($r['codigo'] ?? ''),
          \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        $sheet->setCellValue('B'.$rowNum, (string)($r['unidad'] ?? ''));
        $sheet->setCellValue('C'.$rowNum, (string)($r['descripcion'] ?? ''));
        $sheet->setCellValue('D'.$rowNum, $vendido);
        $sheet->setCellValue('E'.$rowNum, $folio);
        $sheet->setCellValue('F'.$rowNum, $fecha);
        $sheet->setCellValue('G'.$rowNum, (string)($r['compro_credito'] ?? ''));
        $sheet->setCellValue('H'.$rowNum, (string)($r['proveedor'] ?? ''));
        $sheet->setCellValue('I'.$rowNum, $inventario);
        $sheet->setCellValue('J'.$rowNum, $faltVtas);
        $sheet->setCellValue('K'.$rowNum, $faltMin);
        $rowNum++;
      }

      $last = $rowNum - 1;
      if ($last >= 2) {
        // VENDIDO
        $sheet->getStyle('D2:D'.$last)
              ->getNumberFormat()
              ->setFormatCode('#,##0.00');
        // INVENTARIO, FALTANTE vs VENTAS, FALTANTE vs MÍNIMO
        $sheet->getStyle('I2:K'.$last)
              ->getNumberFormat()
              ->setFormatCode('#,##0.00;[Red]-#,##0.00');
      }

      foreach (range('A','K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
      }

      $sheet->setAutoFilter('A1:K'.$last);
      $sheet->freezePane('A2');

      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment; filename="'.$xlsxName.'"');
      header('Cache-Control: max-age=0');

      $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
      $writer->save('php://output');
      $spreadsheet->disconnectWorksheets();
      unset($spreadsheet);
      exit;

    } catch (Throwable $e) {
      header('Content-Type: text/plain; charset=UTF-8', true, 500);
      echo "Error al exportar: " . $e->getMessage();
      exit;
    }
  break;

  default:
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
  break;
}
