<?php
/**
 * Constantes de negocio compartidas.
 */
if (!defined('ID_GRUPO_ACUMULADOR')) {
    // Necesitamos conexión para buscar el grupo "acumulador" si no se configuró.
    $idGrupoAcumulador = null;
    try {
        // $pdo proviene de db.php
        if (!isset($pdo)) {
            require_once __DIR__ . '/db.php';
        }
        if (isset($pdo)) {
            $stmt = $pdo->prepare(
                "SELECT id_grupo
                   FROM cat_grupos
                  WHERE LOWER(nombre_grupo) LIKE :nombre
                  ORDER BY id_grupo ASC
                  LIMIT 1"
            );
            $stmt->execute([':nombre' => '%acumulador%']);
            $id = $stmt->fetchColumn();
            if ($id !== false && $id !== null) {
                $idGrupoAcumulador = (int)$id;
            }
        }
    } catch (\Throwable $th) {
        // Silencioso: si no existe la tabla o hay error, deja null
        $idGrupoAcumulador = null;
    }

    define('ID_GRUPO_ACUMULADOR', $idGrupoAcumulador);
}
