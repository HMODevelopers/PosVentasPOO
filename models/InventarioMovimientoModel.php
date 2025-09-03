<?php
include_once '../includes/db.php';

class InventarioMovimientoModel
{
    private $conn;
    public function __construct(){ global $pdo; $this->conn = $pdo; $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

    private function baseSelect(): string
    {
        return "
            SELECT
                m.id_movimiento, m.id_producto, m.tipo, m.cantidad,
                m.id_usuario, m.id_sucursal, m.referencia, m.motivo,
                m.fecha, m.activo, m.fecha_creacion,
                p.codigo, p.descripcion,
                /* Si tu tabla sucursales tiene 'nombre' o 'descripcion', descomenta UNA:
                s.nombre AS sucursal,
                s.descripcion AS sucursal,
                De lo contrario, dejamos NULL y en el front ya tienes fallback al id_sucursal. */
                s.nombre AS sucursal,
                u.nombre AS usuario,
                CASE
                    WHEN m.tipo IN ('Entrada','Devolucion Compra') THEN  1
                    WHEN m.tipo IN ('Salida','Devolucion Venta')   THEN -1
                    ELSE 0
                END AS signo
            FROM inventario_movimientos m
            INNER JOIN productos  p ON p.id_producto = m.id_producto
            LEFT  JOIN sucursales s ON s.id_sucursal = m.id_sucursal
            LEFT  JOIN usuarios   u ON u.id_usuario   = m.id_usuario
        ";
    }

    private function buildWhere(array $f, array &$params): string {
        $w = ['1=1','m.activo = 1']; // por defecto solo activos

        if ($f['q'] !== '') {
            $w[] = '(p.codigo LIKE :q OR p.descripcion LIKE :q OR m.referencia LIKE :q OR m.motivo LIKE :q)';
            $params[':q'] = '%'.trim($f['q']).'%';
        }
        if ($f['codigo'] !== '') {
            $w[] = 'p.codigo LIKE :codigo';
            $params[':codigo'] = '%'.trim($f['codigo']).'%';
        }
        if ($f['descripcion'] !== '') {
            $w[] = 'p.descripcion LIKE :descripcion';
            $params[':descripcion'] = '%'.trim($f['descripcion']).'%';
        }
        if (!empty($f['id_usuario'])) {
            $w[] = 'm.id_usuario = :idu';
            $params[':idu'] = (int)$f['id_usuario'];
        }
        if ($f['desde'] !== '') {
            $w[] = 'm.fecha >= :desde';
            $params[':desde'] = $f['desde'].' 00:00:00';
        }
        if ($f['hasta'] !== '') {
            $w[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $f['hasta'].' 23:59:59';
        }
        return implode(' AND ', $w);
    }

    // ===== LISTAR =====
    public function listar(
        int $pagina = 1,
        int $limite = 10,
        string $q = '',
        string $codigo = '',
        string $descripcion = '',
        ?int $idUsuario = null,
        string $desde = '',
        string $hasta = ''
    ): array {
        $offset = max(0, ($pagina-1)*$limite);

        $f = ['q'=>$q,'codigo'=>$codigo,'descripcion'=>$descripcion,'id_usuario'=>$idUsuario,'desde'=>$desde,'hasta'=>$hasta];
        $params = [];
        $where  = $this->buildWhere($f, $params);

        $sql = $this->baseSelect()."
          WHERE $where
          ORDER BY m.fecha DESC, m.id_movimiento DESC
          LIMIT :lim OFFSET :off
        ";
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ===== CONTAR =====
    public function contar(
        string $q = '',
        string $codigo = '',
        string $descripcion = '',
        ?int $idUsuario = null,
        string $desde = '',
        string $hasta = ''
    ): int {
        $f = ['q'=>$q,'codigo'=>$codigo,'descripcion'=>$descripcion,'id_usuario'=>$idUsuario,'desde'=>$desde,'hasta'=>$hasta];
        $params = [];
        $where  = $this->buildWhere($f, $params);

        $sql = "
          SELECT COUNT(*) AS total
          FROM inventario_movimientos m
          INNER JOIN productos  p ON p.id_producto = m.id_producto
          LEFT  JOIN sucursales s ON s.id_sucursal = m.id_sucursal
          LEFT  JOIN usuarios   u ON u.id_usuario   = m.id_usuario
          WHERE $where
        ";
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ===== DETALLE =====
    public function obtenerPorId(int $idMovimiento){
        $sql = $this->baseSelect()." WHERE m.id_movimiento = :id LIMIT 1";
        $st  = $this->conn->prepare($sql);
        $st->bindValue(':id', $idMovimiento, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
