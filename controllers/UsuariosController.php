<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once '../models/UsuarioModel.php';
$usuarioModel = new UsuarioModel();

// Usuario que opera (para bitácora). Si no hay sesión, 0.
$idUsuarioSesion = (int)($_SESSION['usuario']['id_usuario'] ?? 0);

// Lee JSON si viene y normaliza acción (guion_bajo -> guion)
$raw    = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $_REQUEST['accion'] ?? ($raw['accion'] ?? '');
$accion = str_replace('_', '-', $accion);

/** Mensaje claro para duplicados */
function msgDuplicados(array $dup, ?string $usuario, ?string $correo): string {
    $usuario = trim((string)$usuario);
    $correo  = ($correo === null) ? null : trim($correo);

    if (!empty($dup['usuario']) && !empty($dup['correo']) && $correo !== null && $correo !== '') {
        return "El usuario \"{$usuario}\" y el correo \"{$correo}\" ya están registrados.";
    }
    if (!empty($dup['usuario'])) {
        return "El usuario \"{$usuario}\" ya está registrado.";
    }
    if (!empty($dup['correo']) && $correo !== null && $correo !== '') {
        return "El correo \"{$correo}\" ya está registrado.";
    }
    return "Usuario/correo ya existe (violación de clave única).";
}

/** Helper de respuesta de error */
function out_error(string $msg, array $extra = []) {
    echo json_encode(array_merge(['ok'=>false,'msg'=>$msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {

        // ===== LISTA CORTA PARA SELECTS =====
        case 'listar-min': {
            $q      = trim($_GET['q'] ?? $_POST['q'] ?? ($raw['q'] ?? ''));
            $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? ($raw['limite'] ?? 200));
            $limite = max(1, min($limite, 1000));
            $data   = $usuarioModel->listarMin($q, $limite);
            echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
        } break;

        // ===== LISTAR PAGINADO =====
        case 'listar': {
            $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? ($raw['pagina'] ?? 1)));
            $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? ($raw['limite'] ?? 10)));

            $filtros = [
                'q'        => trim($_GET['q'] ?? $_POST['q'] ?? ($raw['q'] ?? '')),
                'nombre'   => trim($_GET['nombre'] ?? $_POST['nombre'] ?? ($raw['nombre'] ?? '')),
                'usuario'  => trim($_GET['usuario'] ?? $_POST['usuario'] ?? ($raw['usuario'] ?? '')),
                'correo'   => trim($_GET['correo'] ?? $_POST['correo'] ?? ($raw['correo'] ?? '')),
                'telefono' => trim($_GET['telefono'] ?? $_POST['telefono'] ?? ($raw['telefono'] ?? '')),
                'id_rol'   => (($_GET['id_rol'] ?? $_POST['id_rol'] ?? $raw['id_rol'] ?? '') !== '')
                              ? (int)($_GET['id_rol'] ?? $_POST['id_rol'] ?? $raw['id_rol'])
                              : null,
                // FIX del typo aquí:
                'activo'   => (isset($_GET['activo']) || isset($_POST['activo']) || isset($raw['activo']))
                              ? (($_GET['activo'] ?? $_POST['activo'] ?? $raw['activo']) === '' ? '' : (int)($_GET['activo'] ?? $_POST['activo'] ?? $raw['activo']))
                              : 1,
            ];

            $data  = $usuarioModel->listar($pagina, $limite, $filtros);
            $total = $usuarioModel->contar($filtros);
            echo json_encode(['data' => $data, 'total' => $total], JSON_UNESCAPED_UNICODE);
        } break;

        // ===== DETALLE =====
        case 'detalle': {
            $id = (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? ($raw['id_usuario'] ?? 0));
            if ($id <= 0) out_error('id_usuario inválido');
            $row = $usuarioModel->obtenerPorId($id);
            echo json_encode(['data' => $row], JSON_UNESCAPED_UNICODE);
        } break;

        // ===== CREAR =====
        case 'crear': {
            $payload = $raw ?: $_POST;

            // Reglas mínimas
            $nombre  = trim($payload['nombre']  ?? '');
            $usuario = trim($payload['usuario'] ?? '');
            $correo  = trim((string)($payload['correo'] ?? ''));
            $correo  = ($correo === '') ? null : $correo;

            if ($nombre === '' || $usuario === '') {
                out_error('Los campos nombre y usuario son obligatorios.');
            }

            // Chequeo fino de duplicados ANTES de insertar
            $dup = $usuarioModel->existeUsuarioCorreo($usuario, $correo, null);
            if ($dup['usuario'] || $dup['correo']) {
                out_error(msgDuplicados($dup, $usuario, $correo));
            }

            // Crear
            $id = $usuarioModel->crear($payload, $idUsuarioSesion);

            // Carrera/índice: si el modelo devolvió -1, recalculamos el mensaje exacto
            if ($id === -1) {
                $dup2 = $usuarioModel->existeUsuarioCorreo($usuario, $correo, null);
                out_error(msgDuplicados($dup2, $usuario, $correo));
            }

            echo json_encode(['ok' => $id > 0, 'id_usuario' => $id], JSON_UNESCAPED_UNICODE);
        } break;

        // ===== ACTUALIZAR =====
        case 'actualizar': {
            $payload = $raw ?: $_POST;

            $id = (int)($payload['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
            if ($id <= 0) out_error('id_usuario requerido');

            $nombre  = trim($payload['nombre']  ?? '');
            $usuario = trim($payload['usuario'] ?? '');
            $correo  = trim((string)($payload['correo'] ?? ''));
            $correo  = ($correo === '') ? null : $correo;

            if ($nombre === '' || $usuario === '') {
                out_error('Los campos nombre y usuario son obligatorios.');
            }

            // Chequeo de duplicados EXCLUYENDO el propio id
            $dup = $usuarioModel->existeUsuarioCorreo($usuario, $correo, $id);
            if ($dup['usuario'] || $dup['correo']) {
                out_error(msgDuplicados($dup, $usuario, $correo));
            }

            $ok = $usuarioModel->actualizar($id, $payload, $idUsuarioSesion);

            if ($ok === -1) {
                $dup2 = $usuarioModel->existeUsuarioCorreo($usuario, $correo, $id);
                out_error(msgDuplicados($dup2, $usuario, $correo));
            }

            echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
        } break;

        // ===== ELIMINAR (lógico) =====
        case 'eliminar': {
            $id = (int)($_POST['id_usuario'] ?? ($raw['id_usuario'] ?? 0));
            if ($id <= 0) out_error('id_usuario requerido');
            $ok = $usuarioModel->eliminar($id, $idUsuarioSesion);
            echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
        } break;

        default:
            echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
        break;
    }
} catch (\Throwable $e) {
    // Catch-all por si algo se escapa
    out_error('Error inesperado en el controlador.', [
        'error' => [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]
    ]);
}
