<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../models/ClientesSatModel.php';

$model = new ClientesSatModel();
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = str_replace('_', '-', ($_REQUEST['accion'] ?? ($raw['accion'] ?? '')));

switch ($accion) {
    case 'listar':
        $pagina = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
        $limite = (int)($_POST['limite'] ?? $_GET['limite'] ?? 10);
        $filtros = [
            'rfc' => trim($_POST['rfc'] ?? $_GET['rfc'] ?? ''),
            'razon_social' => trim($_POST['razon_social'] ?? $_GET['razon_social'] ?? ''),
            'dom_fiscal_cp' => trim($_POST['codigo_postal'] ?? $_GET['codigo_postal'] ?? $_POST['dom_fiscal_cp'] ?? $_GET['dom_fiscal_cp'] ?? ''),
        ];
        echo json_encode(['data' => $model->listar($pagina, $limite, $filtros), 'total' => $model->contar($filtros)]);
        break;

    case 'detalle':
        $id = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
        $rfc = trim((string)($_GET['rfc'] ?? $_POST['rfc'] ?? ''));
        $rowKey = (string)($_GET['row_key'] ?? $_POST['row_key'] ?? '');
        if ($id !== '') {
            $rowKey = 'ID:' . (int)$id;
        } elseif ($rowKey === '' && $rfc !== '') {
            $rowKey = 'RFC:' . strtoupper($rfc);
        }
        echo json_encode(['data' => $rowKey !== '' ? $model->obtenerPorRowKey($rowKey) : null]);
        break;


    case 'catalogos-form':
        echo json_encode([
            'regimenes' => $model->listarRegimenes(),
            'usos_cfdi' => $model->listarUsosCfdi(),
            'entidades' => $model->listarEntidades(),
        ]);
        break;

    case 'municipios-por-entidad':
        $cveEnt = trim((string)($_GET['cve_ent'] ?? $_POST['cve_ent'] ?? ''));
        echo json_encode(['data' => $cveEnt !== '' ? $model->listarMunicipios($cveEnt) : []]);
        break;

    case 'localidades-por-municipio':
        $cveEnt = trim((string)($_GET['cve_ent'] ?? $_POST['cve_ent'] ?? ''));
        $cveMun = trim((string)($_GET['cve_mun'] ?? $_POST['cve_mun'] ?? ''));
        echo json_encode(['data' => ($cveEnt !== '' && $cveMun !== '') ? $model->listarLocalidades($cveEnt, $cveMun) : []]);
        break;

    case 'crear':
        echo json_encode(['ok' => $model->crear($raw)]);
        break;

    case 'actualizar':
        $rowKey = trim($raw['row_key'] ?? '');
        if ($rowKey === '' && trim((string)($raw['id'] ?? '')) !== '') {
            $rowKey = 'ID:' . (int)$raw['id'];
        }
        if ($rowKey === '' && trim((string)($raw['rfc'] ?? '')) !== '') {
            $rowKey = 'RFC:' . strtoupper(trim((string)$raw['rfc']));
        }
        echo json_encode(['ok' => $rowKey !== '' ? $model->actualizar($rowKey, $raw) : false]);
        break;

    case 'eliminar':
        echo json_encode(['ok' => false, 'msg' => 'clientes_sat no tiene campo Activo para baja lógica.']);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
