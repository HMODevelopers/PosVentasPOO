<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class ProveedorModel
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

        $sql = "SELECT p.*
                FROM proveedores p
                WHERE p.activo = 1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (p.nombre LIKE :q OR p.rfc LIKE :q OR p.correo LIKE :q OR p.telefono LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $sql .= " ORDER BY p.id_proveedor DESC
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
        $sql = "SELECT COUNT(*) AS total FROM proveedores p WHERE p.activo = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (p.nombre LIKE :q OR p.rfc LIKE :q OR p.correo LIKE :q OR p.telefono LIKE :q)";
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
        $st = $this->conn->prepare("SELECT * FROM proveedores WHERE id_proveedor = :id LIMIT 1");
        $st->bindValue(':id',$id,PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $data)
    {
        $sql = "INSERT INTO proveedores
                (nombre, rfc, correo, telefono, direccion, activo, fecha_creacion)
                VALUES (:nom, :rfc, :cor, :tel, :dir, 1, NOW())";
        $st = $this->conn->prepare($sql);
        $ok = $st->execute([
            ':nom' => trim($data['nombre'] ?? ''),
            ':rfc' => $data['rfc'] ?? null,
            ':cor' => $data['correo'] ?? null,
            ':tel' => $data['telefono'] ?? null,
            ':dir' => $data['direccion'] ?? null,
        ]);
        return $ok ? (int)$this->conn->lastInsertId() : 0;
    }

    public function actualizar(int $id, array $data)
    {
        $sql = "UPDATE proveedores
                SET nombre = :nom, rfc = :rfc, correo = :cor, telefono = :tel, direccion = :dir
                WHERE id_proveedor = :id";
        $st = $this->conn->prepare($sql);
        return $st->execute([
            ':nom' => trim($data['nombre'] ?? ''),
            ':rfc' => $data['rfc'] ?? null,
            ':cor' => $data['correo'] ?? null,
            ':tel' => $data['telefono'] ?? null,
            ':dir' => $data['direccion'] ?? null,
            ':id'  => $id
        ]);
    }

    // Borrado lógico
    public function eliminar(int $id)
    {
        $st = $this->conn->prepare("UPDATE proveedores SET activo = 0 WHERE id_proveedor = :id");
        return $st->execute([':id' => $id]);
    }

    // ========== PARA SELECTS / AUTOCOMPLETE ==========
    // Devuelve {id_proveedor, nombre}
    public function listarMin(string $q = '', int $limite = 50)
    {
        $sql = "SELECT id_proveedor, nombre
                FROM proveedores
                WHERE activo = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (nombre LIKE :q OR rfc LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        $sql .= " ORDER BY nombre ASC LIMIT :lim";
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':lim',$limite,PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
