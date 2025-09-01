<?php
// models/CatGrupoModel.php
include_once __DIR__ . '/../includes/db.php';

class CatGrupoModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    // ===== LISTADO PAGINADO =====
    public function listar(int $pagina = 1, int $limite = 10, string $q = '')
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT g.*
                FROM cat_grupos g
                WHERE g.activo = 1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND g.nombre_grupo LIKE :q";
            $params[':q'] = "%{$q}%";
        }

        $sql .= " ORDER BY g.id_grupo DESC
                  LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(string $q = '')
    {
        $sql = "SELECT COUNT(*) AS total
                FROM cat_grupos g
                WHERE g.activo = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND g.nombre_grupo LIKE :q";
            $params[':q'] = "%{$q}%";
        }
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ===== DETALLE =====
    public function obtenerPorId(int $id)
    {
        $st = $this->conn->prepare("SELECT * FROM cat_grupos WHERE id_grupo = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    // ===== CREAR =====
    public function crear(array $data)
    {
        $sql = "INSERT INTO cat_grupos (nombre_grupo, activo, fecha_creacion)
                VALUES (:nom, 1, NOW())";
        $st  = $this->conn->prepare($sql);
        $ok  = $st->execute([
            ':nom' => trim($data['nombre_grupo'] ?? '')
        ]);
        return $ok ? (int)$this->conn->lastInsertId() : 0;
    }

    // ===== ACTUALIZAR =====
    public function actualizar(int $id, array $data)
    {
        $act = isset($data['activo']) ? (int)!!$data['activo'] : null;

        if ($act === null) {
            $sql = "UPDATE cat_grupos SET nombre_grupo = :nom WHERE id_grupo = :id";
            $st  = $this->conn->prepare($sql);
            return $st->execute([
                ':nom' => trim($data['nombre_grupo'] ?? ''),
                ':id'  => $id
            ]);
        } else {
            $sql = "UPDATE cat_grupos SET nombre_grupo = :nom, activo = :act WHERE id_grupo = :id";
            $st  = $this->conn->prepare($sql);
            return $st->execute([
                ':nom' => trim($data['nombre_grupo'] ?? ''),
                ':act' => $act,
                ':id'  => $id
            ]);
        }
    }

    // ===== ELIMINAR (lógico) =====
    public function eliminar(int $id)
    {
        $st = $this->conn->prepare("UPDATE cat_grupos SET activo = 0 WHERE id_grupo = :id");
        return $st->execute([':id' => $id]);
    }

    // ===== LISTA CORTA PARA SELECTS =====
    // devuelve [{id_grupo, nombre_grupo}]
    public function listarMin(string $q = '', int $limite = 100)
    {
        $sql = "SELECT id_grupo, nombre_grupo
                FROM cat_grupos
                WHERE activo = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND nombre_grupo LIKE :q";
            $params[':q'] = "%{$q}%";
        }
        $sql .= " ORDER BY nombre_grupo ASC LIMIT :lim";
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
