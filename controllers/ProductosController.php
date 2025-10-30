<?php
// NOTA: evitamos fijar aquí el header JSON global para no estorbar al Excel.
// Ponemos el header JSON en cada case que devuelve JSON.

include_once '../models/ProductoModel.php';
$productoModel = new ProductoModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        header('Content-Type: application/json; charset=UTF-8');

        $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

        // Filtros
        $codigo      = trim($_GET['codigo']      ?? $_POST['codigo']      ?? '');
        $descripcion = trim($_GET['descripcion'] ?? $_POST['descripcion'] ?? '');
        $idProveedor = (isset($_REQUEST['id_proveedor']) && $_REQUEST['id_proveedor'] !== '')
                        ? (int)$_REQUEST['id_proveedor']
                        : null;
        $idGrupo     = (isset($_REQUEST['id_grupo']) && $_REQUEST['id_grupo'] !== '')
                        ? (int)$_REQUEST['id_grupo']
                        : null;

        $data  = $productoModel->listar($pagina, $limite, $codigo, $descripcion, $idProveedor, $idGrupo);
        $total = $productoModel->contar($codigo, $descripcion, $idProveedor, $idGrupo);

        echo json_encode(['data' => $data, 'total' => $total], JSON_UNESCAPED_UNICODE);
    break;

    // ===== DETALLE =====
    case 'detalle':
        header('Content-Type: application/json; charset=UTF-8');

        $id = (int)($_GET['id_producto'] ?? $_POST['id_producto'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['error'=>'id_producto inválido'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $row = $productoModel->obtenerPorId($id);
        echo json_encode(['data' => $row], JSON_UNESCAPED_UNICODE);
    break;

    // ===== CREAR =====
    case 'crear':
        header('Content-Type: application/json; charset=UTF-8');

        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? null;
        }

        if (!isset($payload['id_sucursal'])) {
            $payload['id_sucursal'] = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? null);
        }

        if (empty($payload['id_usuario'])) {
            echo json_encode(['ok' => false, 'msg' => 'Falta id_usuario (sesión).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        if (!array_key_exists('id_grupo', $payload) || $payload['id_grupo'] === '' || $payload['id_grupo'] === null) {
            $payload['id_grupo'] = null;
        } else {
            $payload['id_grupo'] = (int)$payload['id_grupo'];
        }

        $resp = $productoModel->crear($payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        header('Content-Type: application/json; charset=UTF-8');

        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        $id = (int)($payload['id_producto'] ?? $_POST['id_producto'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok'=>false,'msg'=>'id_producto requerido'], JSON_UNESCAPED_UNICODE);
            break;
        }

        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? null;
        }

        if (!isset($payload['id_sucursal'])) {
            $payload['id_sucursal'] = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? null);
        }

        if (empty($payload['id_usuario'])) {
            echo json_encode(['ok' => false, 'msg' => 'Falta id_usuario (sesión).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        if (!array_key_exists('id_grupo', $payload) || $payload['id_grupo'] === '' || $payload['id_grupo'] === null) {
            $payload['id_grupo'] = null;
        } else {
            $payload['id_grupo'] = (int)$payload['id_grupo'];
        }

        $resp = $productoModel->actualizar($id, $payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        header('Content-Type: application/json; charset=UTF-8');

        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        $id = (int)($_POST['id_producto'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? 'Desactivación de producto');

        $idSucursal = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? 1);
        $idUsuario  = $_SESSION['usuario']['id_usuario'] ?? ($_SESSION['usuario']['id'] ?? $_SESSION['id_usuario'] ?? null);

        if ($id <= 0 || empty($idUsuario) || empty($idSucursal)) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan datos: id_producto / sesión (usuario/sucursal).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resp = $productoModel->eliminar((int)$id, (int)$idSucursal, (int)$idUsuario, $motivo);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== BUSCAR-MIN (para selects y typeahead) =====
    case 'buscar-min':
        header('Content-Type: application/json; charset=UTF-8');

        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $lim = max(1, min($lim, 500));
        $data = $productoModel->buscarMin($q, $lim);
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    break;

    // ===== SIMULAR PRECIOS =====
    case 'simular-precios':
        header('Content-Type: application/json; charset=UTF-8');

        $idProv = (int)($_REQUEST['id_proveedor'] ?? 0);
        $ppv    = (float)($_REQUEST['precio_proveedor'] ?? 0);
        $data   = $productoModel->simularPrecios($idProv, $ppv);
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    break;

    // ===== EXPORTAR EXCEL =====
    case 'exportar-excel':
        // Cargar autoload de PhpSpreadsheet (ajusta la ruta si tu estructura difiere)
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

        // Filtros (mismos que en listar)
        $codigo      = trim($_GET['codigo']      ?? '');
        $descripcion = trim($_GET['descripcion'] ?? '');
        $idProveedor = (isset($_GET['id_proveedor']) && $_GET['id_proveedor'] !== '') ? (int)$_GET['id_proveedor'] : null;
        $idGrupo     = (isset($_GET['id_grupo'])     && $_GET['id_grupo']     !== '') ? (int)$_GET['id_grupo']     : null;

        // Data sin paginar
        $rows = $productoModel->listarParaExportar($codigo, $descripcion, $idProveedor, $idGrupo);

        // Construir el Excel
        $xlsxName = 'productos_' . date('Ymd_His') . '.xlsx';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        // Encabezados
        $headers = [
            'A1' => 'Código',
            'B1' => 'Descripción',
            'C1' => 'Proveedor',
            'D1' => 'Grupo',
            'E1' => 'Stock',
            'F1' => 'Precio Público',
            'G1' => 'Precio Taller'
        ];
        foreach ($headers as $cell => $text) { $sheet->setCellValue($cell, $text); }

        // Estilo encabezado
        $sheet->getStyle('A1:G1')->applyFromArray([
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
            $c  = $r['codigo'] ?? ('#' . ($r['id_producto'] ?? ''));
            $d  = $r['descripcion'] ?? '';
            $pr = $r['proveedor'] ?? '';
            $gr = $r['grupo'] ?? ($r['nombre_grupo'] ?? '');
            $st = (float)($r['stock_actual'] ?? 0);
            $pb = (float)($r['precio_publico'] ?? 0);
            $pt = (float)($r['precio_taller'] ?? 0);

            $sheet->setCellValueExplicit('A'.$rowNum, $c, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$rowNum, $d);
            $sheet->setCellValue('C'.$rowNum, $pr);
            $sheet->setCellValue('D'.$rowNum, $gr);
            $sheet->setCellValue('E'.$rowNum, $st);
            $sheet->setCellValue('F'.$rowNum, $pb);
            $sheet->setCellValue('G'.$rowNum, $pt);
            $rowNum++;
        }

        // Formatos y autosize
        $sheet->getStyle('E2:E'.$rowNum)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F2:G'.$rowNum)->getNumberFormat()->setFormatCode('$ #,##0.00;[Red]$ -#,##0.00');
        foreach (range('A','G') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        // Filtro y freeze
        $sheet->setAutoFilter('A1:G'.($rowNum-1));
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
        echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    break;
}
