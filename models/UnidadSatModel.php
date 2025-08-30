<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class UnidadSatModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    // ========== LISTADO PAGINADO ==========
    public function listar(int $pagina = 1, int $limite = 10, string $q = '')
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT us.*
                FROM unidades_sat us
                WHERE us.activo = 1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (us.clave_unidad_sat LIKE :q OR us.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $sql .= " ORDER BY us.clave_unidad_sat ASC
                  LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':limite',(int)$limite,PDO::PARAM_INT);
        $st->bindValue(':offset',(int)$offset,PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(string $q = '')
    {
        $sql = "SELECT COUNT(*) AS total
                FROM unidades_sat us
                WHERE us.activo = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (us.clave_unidad_sat LIKE :q OR us.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ========== CRUD ==========
    public function obtenerPorId(int $id)
    {
        $st = $this->conn->prepare("SELECT * FROM unidades_sat WHERE id_unidad_sat = :id LIMIT 1");
        $st->bindValue(':id',$id,PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $data)
    {
        $clave = strtoupper(trim($data['clave_unidad_sat'] ?? ''));
        $desc  = trim($data['descripcion'] ?? '');

        $sql = "INSERT INTO unidades_sat
                (clave_unidad_sat, descripcion, activo, fecha_creacion)
                VALUES (:cla, :des, 1, NOW())";
        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':cla' => $clave,
                ':des' => $desc,
            ]);
            return $ok ? (int)$this->conn->lastInsertId() : 0;
        } catch (PDOException $e) {
            // Duplicado de clave
            if ($e->getCode() === '23000') return -1;
            throw $e;
        }
    }

    public function actualizar(int $id, array $data)
    {
        $clave = strtoupper(trim($data['clave_unidad_sat'] ?? ''));
        $desc  = trim($data['descripcion'] ?? '');

        $sql = "UPDATE unidades_sat
                SET clave_unidad_sat = :cla,
                    descripcion      = :des
                WHERE id_unidad_sat = :id";
        try {
            $st = $this->conn->prepare($sql);
            return $st->execute([
                ':cla' => $clave,
                ':des' => $desc,
                ':id'  => $id
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return -1;
            throw $e;
        }
    }

    // Borrado lógico
    public function eliminar(int $id)
    {
        $st = $this->conn->prepare("UPDATE unidades_sat SET activo = 0 WHERE id_unidad_sat = :id");
        return $st->execute([':id' => $id]);
    }

    // ========== PARA SELECTS / AUTOCOMPLETE ==========
    // Devuelve {id_unidad_sat, clave, descripcion}
    public function listarMin(string $q = '', int $limite = 50)
    {
        $sql = "SELECT id_unidad_sat,
                       clave_unidad_sat AS clave,
                       descripcion
                FROM unidades_sat
                WHERE activo = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (clave_unidad_sat LIKE :q OR descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        $sql .= " ORDER BY clave_unidad_sat ASC LIMIT :lim";
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':lim',$limite,PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
