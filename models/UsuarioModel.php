<?php
// models/UsuarioModel.php
// Usa la misma conexión que el resto de tu app
include_once '../includes/db.php';

class UsuarioModel
{
    /** @var PDO */
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Listado paginado (por si luego lo necesitas en un ABM)
     */
    public function listar(
        int $pagina = 1,
        int $limite = 10,
        string $q = '',
        ?int $idRol = null,
        ?int $activo = 1
    ): array {
        $offset = max(0, ($pagina - 1) * $limite);

        $sql = "SELECT u.id_usuario, u.nombre, u.usuario, u.correo, u.telefono,
                       u.id_rol, u.activo, u.fecha_creacion
                FROM usuarios u
                WHERE 1=1";
        $params = [];

        if ($activo !== null) {
            $sql .= " AND u.activo = :activo";
            $params[':activo'] = (int)$activo;
        }
        if ($q !== '') {
            $sql .= " AND (u.nombre LIKE :q OR u.usuario LIKE :q OR u.correo LIKE :q OR u.telefono LIKE :q)";
            $params[':q'] = '%'.trim($q).'%';
        }
        if (!empty($idRol)) {
            $sql .= " AND u.id_rol = :idrol";
            $params[':idrol'] = (int)$idRol;
        }

        $sql .= " ORDER BY u.nombre ASC
                  LIMIT :lim OFFSET :off";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':lim',  $limite, PDO::PARAM_INT);
        $st->bindValue(':off',  $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(string $q = '', ?int $idRol = null, ?int $activo = 1): int
    {
        $sql = "SELECT COUNT(*) AS total FROM usuarios u WHERE 1=1";
        $params = [];

        if ($activo !== null) {
            $sql .= " AND u.activo = :activo";
            $params[':activo'] = (int)$activo;
        }
        if ($q !== '') {
            $sql .= " AND (u.nombre LIKE :q OR u.usuario LIKE :q OR u.correo LIKE :q OR u.telefono LIKE :q)";
            $params[':q'] = '%'.trim($q).'%';
        }
        if (!empty($idRol)) {
            $sql .= " AND u.id_rol = :idrol";
            $params[':idrol'] = (int)$idRol;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->conn->prepare("SELECT u.* FROM usuarios u WHERE u.id_usuario = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Para selects / typeahead
     * Devuelve: id_usuario, nombre, usuario, nombre_mostrar
     */
    public function listarMin(string $q = '', int $limite = 200): array
    {
        $lim = max(1, min((int)$limite, 1000));

        $sql = "SELECT u.id_usuario, u.nombre, u.usuario
                FROM usuarios u
                WHERE u.activo = 1";
        $useQ = ($q !== '');
        if ($useQ) {
            $sql .= " AND (u.nombre LIKE :q OR u.usuario LIKE :q OR u.correo LIKE :q)";
        }
        $sql .= " ORDER BY u.nombre ASC
                  LIMIT {$lim}";

        $st = $this->conn->prepare($sql);
        if ($useQ) {
            $like = '%'.trim($q).'%';
            $st->bindValue(':q', $like, PDO::PARAM_STR);
        }
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // Campo combinado útil para mostrar en el <option>
        foreach ($rows as &$r) {
            $r['nombre_mostrar'] = trim(($r['nombre'] ?? '').' ('.($r['usuario'] ?? '').')');
        }
        return $rows;
    }
}
