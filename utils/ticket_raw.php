<?php
/* ============ DEPENDENCIAS DEL PROYECTO ============ */
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../models/VentaModel.php';

/* ============ AUTOLOADER MIKE42 ============ */
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

/* ============ HELPERS Y CONSTANTES ============ */
function mxn($n){ return '$'.number_format((float)$n, 2, '.', ','); }
function fechaMx($s){ $d = $s ? new DateTime($s) : new DateTime(); return $d->format('d/m/Y h:i a'); }
function fmtCantidad($cantidad): string {
  $raw = trim((string)$cantidad);
  if ($raw === '') return '0';
  $norm = str_replace(',', '.', $raw);
  if (!is_numeric($norm)) {
    $norm = (string)(float)$cantidad;
  }
  if (strpos($norm, '.') !== false) {
    $norm = rtrim(rtrim($norm, '0'), '.');
  }
  return $norm === '' ? '0' : $norm;
}

const COLS=42; const CANT_W=6; const PREC_W=10; const IMP_W=10; const GAP=2;
const DESC_W = COLS - (CANT_W + PREC_W + IMP_W + GAP);

function padCenter($txt, $w){
  $len = mb_strwidth($txt, 'UTF-8');
  if ($len >= $w) return $txt;
  $left = intdiv($w - $len, 2); $right = $w - $len - $left;
  return str_repeat(' ', $left) . $txt . str_repeat(' ', $right);
}
function line2Cols($left, $right, $width = COLS) {
  $left=(string)$left; $right=(string)$right;
  $sp = $width - mb_strwidth($left, 'UTF-8') - mb_strwidth($right, 'UTF-8');
  if ($sp < 0) $sp = 0; return $left . str_repeat(' ', $sp) . $right;
}
function wrapLines($text, $width = DESC_W) {
  $text = trim(preg_replace('/[ \t]+/', ' ', (string)$text));
  if ($text === '') return [];
  $words = preg_split('/\s/u', $text);
  $lines = []; $line = '';
  foreach ($words as $w) {
    $try = $line === '' ? $w : $line . ' ' . $w;
    if (mb_strwidth($try,'UTF-8') <= $width) { $line = $try; }
    else {
      if ($line !== '') $lines[] = $line;
      while (mb_strwidth($w,'UTF-8') > $width) {
        $cut = mb_strimwidth($w, 0, $width, '', 'UTF-8');
        $lines[] = $cut;
        $w = mb_substr($w, mb_strlen($cut,'UTF-8'), null, 'UTF-8');
      }
      $line = $w;
    }
  }
  if ($line !== '') $lines[] = $line;
  return $lines;
}
function itemRow($cantidad, $codigo, $descripcion, $precio, $importe) {
  $cantW=6; $impW=10; $precW=10; $descW = COLS-$cantW-$precW-$impW-2;
  $cantTxt = fmtCantidad($cantidad);
  $cantCell = padCenter($cantTxt, $cantW);
  if (!empty($codigo)) { $descripcion = "[$codigo] " . $descripcion; }
  $precTxt = mxn($precio); $impTxt = mxn($importe);
  $descLines = wrapLines($descripcion, $descW) ?: [''];
  $rows=[]; foreach ($descLines as $i=>$dl){
    if ($i===0){
      $left = $cantCell.' '.str_pad($dl,$descW);
      $right = str_pad($precTxt,$precW,' ',STR_PAD_LEFT).' '.str_pad($impTxt,$impW,' ',STR_PAD_LEFT);
      $rows[] = line2Cols($left,$right,COLS);
    } else {
      $left = str_repeat(' ', $cantW).' '.str_pad($dl,$descW);
      $right = str_repeat(' ', $precW + 1 + $impW);
      $rows[] = line2Cols($left,$right,COLS);
    }
  } return $rows;
}

/* ============ VALIDAR PARAM ============
   GET id_venta obligatorio */
$idVenta = (int)($_GET['id_venta'] ?? 0);
if ($idVenta <= 0) {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Falta id_venta']); exit;
}

/* ============ CARGAR DATOS ============ */
$ventaModel = new VentaModel();
$venta    = $ventaModel->obtenerVentaPorId($idVenta);
if (!$venta) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Venta no encontrada']); exit; }
$detalles = $ventaModel->obtenerDetalleVenta($idVenta);

/* ============ GENERAR ESC/POS EN TEMP ============ */
$tmp = sys_get_temp_dir()."/ticket_{$idVenta}_".bin2hex(random_bytes(4)).".escpos";
$connector = new FilePrintConnector($tmp);
$profile   = CapabilityProfile::load('default');
$printer   = new Printer($connector, $profile);

/* --- Encabezado --- */
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->setEmphasis(true);  $printer->text("REFACCIONARIA RIVERA\n"); $printer->setEmphasis(false);
$printer->text("KARINA VALENTINA RIVERA LEON\n");
$printer->text("RFC: RILK830214NI9\n");
$printer->text("Regimen Fiscal: 612\n");
$printer->text("Blvd. Solidaridad 601, Col. Choyal\n");
$printer->text("C.P. 83130 Hermosillo, Sonora\n");
$printer->text("Tel: (662) 262-1129\n");
$printer->text(str_repeat('-', COLS) . "\n");

/* --- Meta --- */
$printer->setJustification(Printer::JUSTIFY_LEFT);
$folio = ($venta['folio'] ?? '') !== '' ? $venta['folio'] : ('VTA-' . $idVenta);
$printer->text("FECHA: " . fechaMx($venta['fecha'] ?? null) . "\n");
$printer->text("FOLIO: " . $folio . "\n");
$printer->text(str_repeat('-', COLS) . "\n");

/* --- Cabeceras detalle --- */
$printer->setEmphasis(true);
$leftHeader  = padCenter('CANT', CANT_W) . ' ' . str_pad('DESCRIPCION', DESC_W);
$rightHeader = str_pad('PRECIO', PREC_W, ' ', STR_PAD_LEFT) . ' ' . str_pad('IMPORTE', IMP_W, ' ', STR_PAD_LEFT);
$printer->text(line2Cols($leftHeader, $rightHeader) . "\n");
$printer->setEmphasis(false);

/* --- Detalle --- */
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
  if ($desc !== '' && $desc !== $nombre) { $texto .= ' ' . $desc; }

  foreach (itemRow($cant, $codigo, $texto, $precio, $imp) as $r) {
    $printer->text($r . "\n");
  }
}

/* --- Totales --- */
$printer->text(str_repeat('-', COLS) . "\n");
$printer->setEmphasis(true);
$printer->text(line2Cols('TOTAL', mxn(($venta['total'] ?? 0) ?: $totalCalc)) . "\n");
$printer->setEmphasis(false);
$printer->text(str_repeat('-', COLS) . "\n");

/* --- Mensajes --- */
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->text("GRACIAS POR TU COMPRA\n");
$printer->text("EN PARTES ELECTRICAS NO HAY GARANTIA\n");
$printer->feed(4);
$printer->cut();

$printer->close();

/* Respuesta JSON con ESC/POS base64 */
$raw = @file_get_contents($tmp);
@unlink($tmp);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'       => true,
  'type'     => 'escpos',
  'id_venta' => $idVenta,
  'b64'      => base64_encode($raw ?: '')
]);
