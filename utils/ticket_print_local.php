<?php
/** utils/ticket_print_local.php (v2: binario + JSON robusto) */
declare(strict_types=1);
ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../models/VentaModel.php';

/* Autoloader Mike42 */
$baseNamespace = __DIR__ . '/../assets/libs/mike42';
spl_autoload_register(function ($class) use ($baseNamespace) {
  $prefix = 'Mike42\\Escpos\\'; $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) return;
  $relative = substr($class, $len);
  $file = $baseNamespace . '/Escpos/' . str_replace('\\', '/', $relative) . '.php';
  if (file_exists($file)) require_once $file;
});

use Mike42\Escpos\Printer;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

/* ---------- CONFIG ---------- */
const SHARE_NAME   = 'EPSON TM-T88V';   // nombre del recurso compartido
const PRINTER_NAME = 'EPSON TM-T88V';   // nombre local (cola)

/* ---------- helpers ---------- */
function outJson(array $data, int $status=200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  // si hubo basura previa, la mandamos como debug
  $debugBuf = trim((string)ob_get_contents());
  if ($debugBuf !== '') $data['debug_buffer'] = $debugBuf;
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function mxn($n){ return '$'.number_format((float)$n, 2, '.', ','); }
function fechaMx($s){ $d=$s?new DateTime($s):new DateTime(); return $d->format('d/m/Y h:i a'); }

const COLS=42; const CANT_W=6; const PREC_W=10; const IMP_W=10; const GAP=2;
const DESC_W = COLS - (CANT_W + PREC_W + IMP_W + GAP);
function padCenter($t,$w){ $l=mb_strwidth($t,'UTF-8'); if($l>=$w) return $t; $L=intdiv($w-$l,2); $R=$w-$l-$L; return str_repeat(' ',$L).$t.str_repeat(' ',$R); }
function line2Cols($L,$R,$W=COLS){ $sp=$W-mb_strwidth($L,'UTF-8')-mb_strwidth($R,'UTF-8'); if($sp<0)$sp=0; return $L.str_repeat(' ',$sp).$R; }
function wrapLines($text,$width=DESC_W){
  $text=trim(preg_replace('/[ \t]+/',' ',$text??'')); if($text==='')return[];
  $w=preg_split('/\s/u',$text); $out=[];$line='';
  foreach($w as $x){ $try=$line===''?$x:$line.' '.$x;
    if(mb_strwidth($try,'UTF-8')<=$width){ $line=$try; }
    else{
      if($line!=='') $out[]=$line;
      while(mb_strwidth($x,'UTF-8')>$width){
        $cut=mb_strimwidth($x,0,$width,'','UTF-8');
        $out[]=$cut; $x=mb_substr($x,mb_strlen($cut,'UTF-8'), null,'UTF-8');
      }
      $line=$x;
    }
  }
  if($line!=='') $out[]=$line; return $out;
}
function itemRow($cantidad,$codigo,$descripcion,$precio,$importe){
  $cantW=6;$impW=10;$precW=10;$descW=COLS-$cantW-$precW-$impW-2;
  $cantTxt=rtrim(rtrim(number_format((float)$cantidad,2,'.',''),'0'),'.');
  $cantCell=padCenter($cantTxt,$cantW);
  if(!empty($codigo)) $descripcion="[$codigo] ".$descripcion;
  $precTxt=mxn($precio); $impTxt=mxn($importe);
  $descLines=wrapLines($descripcion,$descW)?:[''];
  $rows=[];
  foreach($descLines as $i=>$dl){
    if($i===0){
      $left=$cantCell.' '.str_pad($dl,$descW);
      $right=str_pad($precTxt,$precW,' ',STR_PAD_LEFT).' '.str_pad($impTxt,$impW,' ',STR_PAD_LEFT);
      $rows[]=line2Cols($left,$right,COLS);
    }else{
      $left=str_repeat(' ',$cantW).' '.str_pad($dl,$descW);
      $rows[]=line2Cols($left,str_repeat(' ', $precW+1+$impW),COLS);
    }
  }
  return $rows;
}

/* ---------- auth + params ---------- */
if (!isset($_SESSION['usuario'])) outJson(['ok'=>false,'error'=>'No autorizado'], 401);
$idVenta = (int)($_GET['id_venta'] ?? 0);
if ($idVenta <= 0) outJson(['ok'=>false,'error'=>'Falta id_venta'], 400);

/* ---------- datos ---------- */
try{
  $vm = new VentaModel();
  $venta    = $vm->obtenerVentaPorId($idVenta);
  if(!$venta) outJson(['ok'=>false,'error'=>'Venta no encontrada'], 404);
  $detalles = $vm->obtenerDetalleVenta($idVenta);
}catch(Throwable $e){
  outJson(['ok'=>false,'error'=>'Error al consultar la venta','ex'=>$e->getMessage()], 500);
}

/* ---------- generar ESC/POS en binario ---------- */
$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR."ticket_{$idVenta}_".bin2hex(random_bytes(4)).".escpos";
$connector = new FilePrintConnector($tmp);                 // escribe BINARIO
$profile   = CapabilityProfile::load('default');
$printer   = new Printer($connector, $profile);

/* Encabezado */
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->setEmphasis(true);  $printer->text("REFACCIONARIA RIVERA\n"); $printer->setEmphasis(false);
$printer->text("KARINA VALENTINA RIVERA LEON\nRFC: RILK830214NI9\nRegimen Fiscal: 612\n");
$printer->text("Blvd. Solidaridad 601, Col. Choyal\nC.P. 83130 Hermosillo, Sonora\nTel: (662) 262-1129\n");
$printer->text(str_repeat('-',COLS)."\n");

/* Meta */
$printer->setJustification(Printer::JUSTIFY_LEFT);
$folio = ($venta['folio'] ?? '') !== '' ? $venta['folio'] : ('VTA-'.$idVenta);
$printer->text("FECHA: ".fechaMx($venta['fecha'] ?? null)."\n");
$printer->text("FOLIO: ".$folio."\n");
$printer->text(str_repeat('-',COLS)."\n");

/* Cabecera detalle */
$printer->setEmphasis(true);
$leftHeader  = padCenter('CANT', CANT_W) . ' ' . str_pad('DESCRIPCION', DESC_W);
$rightHeader = str_pad('PRECIO', PREC_W, ' ', STR_PAD_LEFT) . ' ' . str_pad('IMPORTE', IMP_W, ' ', STR_PAD_LEFT);
$printer->text(line2Cols($leftHeader,$rightHeader)."\n");
$printer->setEmphasis(false);

/* Detalle */
$totalCalc = 0.0;
foreach ($detalles as $d) {
  $cant   = (float)($d['cantidad'] ?? 0);
  $precio = (float)($d['precio_unitario'] ?? 0);
  $imp    = isset($d['subtotal']) ? (float)$d['subtotal'] : $cant * $precio;
  $totalCalc += $imp;

  $nombre = trim((string)($d['producto'] ?? ''));
  $desc   = trim((string)($d['descripcion'] ?? ''));
  $codigo = '';
  foreach (['codigo','codigo_producto','sku','clave','clave_sat'] as $k) {
    if (!empty($d[$k])) { $codigo = (string)$d[$k]; break; }
  }
  $texto = $nombre;
  if ($desc !== '' && $desc !== $nombre) $texto .= ' ' . $desc;

  foreach (itemRow($cant, $codigo, $texto, $precio, $imp) as $r) $printer->text($r."\n");
}

/* Totales + pie */
$printer->text(str_repeat('-', COLS) . "\n");
$printer->setEmphasis(true);
$printer->text(line2Cols('TOTAL', mxn(($venta['total'] ?? 0) ?: $totalCalc)) . "\n");
$printer->setEmphasis(false);
$printer->text(str_repeat('-', COLS) . "\n");
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->text("GRACIAS POR TU COMPRA\n");
$printer->text("EN PARTES ELECTRICAS NO HAY GARANTIA\n");
$printer->feed(4);
$printer->cut();
$printer->close();

/* ---------- envío BINARIO ---------- */
function runCmd(string $cmd): array {
  $out = []; exec('cmd /S /C '.$cmd.' 2>&1', $out, $code);
  return [$code, implode("\n", $out)];
}

$debug = [];
$ok=false; $method='';

/* 1) copy /B al recurso compartido local */
if (SHARE_NAME !== '') {
  $share = '\\\\localhost\\'.SHARE_NAME;
  $cmd1 = 'copy /B '.escapeshellarg($tmp).' "'.$share.'" >NUL';
  [$c1,$o1] = runCmd($cmd1);
  $debug[] = ['cmd'=>$cmd1,'code'=>$c1,'out'=>$o1];
  if ($c1===0) { $ok=true; $method='copy-share'; }
}

/* 2) fallback: copy /B a la cola por nombre (suele funcionar también) */
if(!$ok && PRINTER_NAME !== ''){
  $cmd2 = 'copy /B '.escapeshellarg($tmp).' "\\\\localhost\\'.PRINTER_NAME.'" >NUL';
  [$c2,$o2] = runCmd($cmd2);
  $debug[] = ['cmd'=>$cmd2,'code'=>$c2,'out'=>$o2];
  if ($c2===0) { $ok=true; $method='copy-local'; }
}

/* 3) último intento (NO recomendado): print /D (texto) */
if(!$ok && PRINTER_NAME !== ''){
  $cmd3 = 'print /D:"'.PRINTER_NAME.'" '.escapeshellarg($tmp);
  [$c3,$o3] = runCmd($cmd3);
  $debug[] = ['cmd'=>$cmd3,'code'=>$c3,'out'=>$o3,'note'=>'text-mode (puede causar ???)'];
  if ($c3===0) { $ok=true; $method='print-text'; }
}

@unlink($tmp);

if ($ok) outJson(['ok'=>true,'id_venta'=>$idVenta,'method'=>$method,'debug'=>$debug]);
outJson(['ok'=>false,'error'=>'No se pudo enviar a la impresora','debug'=>$debug], 500);
