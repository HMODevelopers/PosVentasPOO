<?php
include_once __DIR__ . '/../includes/db.php';
class CatSatUsoCfdiModel {
    private $conn;
    public function __construct(){ global $pdo; $this->conn=$pdo; }
    public function listar(int $pagina=1,int $limite=10,array $f=[]){
        $offset=(max(1,$pagina)-1)*max(1,$limite);
        $sql="SELECT * FROM cat_sat_uso_cfdi WHERE activo=1"; $p=[];
        if (($q=trim($f['q']??''))!==''){ $sql.=" AND (clave_uso_cfdi LIKE :q1 OR descripcion LIKE :q2)"; $p[':q1']="%$q%"; $p[':q2']="%$q%"; }
        if (($c=trim($f['clave_uso_cfdi']??''))!==''){ $sql.=" AND clave_uso_cfdi LIKE :c"; $p[':c']="%".strtoupper($c)."%"; }
        if (($d=trim($f['descripcion']??''))!==''){ $sql.=" AND descripcion LIKE :d"; $p[':d']="%$d%"; }
        $sql.=" ORDER BY clave_uso_cfdi ASC LIMIT $limite OFFSET $offset";
        $st=$this->conn->prepare($sql); foreach($p as $k=>$v){$st->bindValue($k,$v);} $st->execute(); return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function contar(array $f=[]){
        $sql="SELECT COUNT(*) total FROM cat_sat_uso_cfdi WHERE activo=1"; $p=[];
        if (($q=trim($f['q']??''))!==''){ $sql.=" AND (clave_uso_cfdi LIKE :q1 OR descripcion LIKE :q2)"; $p[':q1']="%$q%"; $p[':q2']="%$q%"; }
        if (($c=trim($f['clave_uso_cfdi']??''))!==''){ $sql.=" AND clave_uso_cfdi LIKE :c"; $p[':c']="%".strtoupper($c)."%"; }
        if (($d=trim($f['descripcion']??''))!==''){ $sql.=" AND descripcion LIKE :d"; $p[':d']="%$d%"; }
        $st=$this->conn->prepare($sql); foreach($p as $k=>$v){$st->bindValue($k,$v);} $st->execute(); return (int)($st->fetch(PDO::FETCH_ASSOC)['total']??0);
    }
    public function obtenerPorId(int $id){ $st=$this->conn->prepare("SELECT * FROM cat_sat_uso_cfdi WHERE id_uso_cfdi=:id LIMIT 1"); $st->execute([':id'=>$id]); return $st->fetch(PDO::FETCH_ASSOC); }
    public function crear(array $d){ $st=$this->conn->prepare("INSERT INTO cat_sat_uso_cfdi(clave_uso_cfdi,descripcion,activo,fecha_creacion) VALUES(:c,:d,1,NOW())"); $ok=$st->execute([':c'=>strtoupper(trim($d['clave_uso_cfdi']??'')),':d'=>trim($d['descripcion']??'')]); return $ok?(int)$this->conn->lastInsertId():0; }
    public function actualizar(int $id,array $d){ $st=$this->conn->prepare("UPDATE cat_sat_uso_cfdi SET clave_uso_cfdi=:c, descripcion=:d WHERE id_uso_cfdi=:id"); return $st->execute([':c'=>strtoupper(trim($d['clave_uso_cfdi']??'')),':d'=>trim($d['descripcion']??''),':id'=>$id]); }
    public function eliminar(int $id){ $st=$this->conn->prepare("UPDATE cat_sat_uso_cfdi SET activo=0 WHERE id_uso_cfdi=:id"); return $st->execute([':id'=>$id]); }
    public function listarMin(string $q='',int $lim=50){ $sql="SELECT id_uso_cfdi, clave_uso_cfdi, descripcion FROM cat_sat_uso_cfdi WHERE activo=1"; $p=[]; if($q!==''){ $sql.=" AND (clave_uso_cfdi LIKE :q1 OR descripcion LIKE :q2)"; $p[':q1']="%$q%"; $p[':q2']="%$q%"; } $sql.=" ORDER BY clave_uso_cfdi ASC LIMIT :l"; $st=$this->conn->prepare($sql); foreach($p as $k=>$v){$st->bindValue($k,$v);} $st->bindValue(':l',$lim,PDO::PARAM_INT); $st->execute(); return $st->fetchAll(PDO::FETCH_ASSOC);} }
