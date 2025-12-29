<?php
/* ============ DEPENDENCIAS DEL PROYECTO ============ */
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../models/VentaModel.php';

/* ============ HELPERS ============ */
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

/* ============ VALIDAR PARAMS ============ */
$idVenta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
if ($idVenta <= 0) { http_response_code(400); exit('Falta id_venta'); }

/* ============ CARGAR DATOS ============ */
$ventaModel = new VentaModel();
$venta    = $ventaModel->obtenerVentaPorId($idVenta);
if (!$venta) { http_response_code(404); exit('Venta no encontrada'); }
$detalles = $ventaModel->obtenerDetalleVenta($idVenta);

/* ============ AUTOLOADER MIKE42 (TU CONFIG QUE SÍ FUNCIONA) ============ */
$baseNamespace = __DIR__ . '/../assets/libs/mike42';
spl_autoload_register(function ($class) use ($baseNamespace) {
    $prefix = 'Mike42\\Escpos\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $baseNamespace . '/Escpos/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require_once $file;
});

use Mike42\Escpos\Printer;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
// Si un día la pones por red, usa esto en lugar de WindowsPrintConnector:
// use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

/* ============ FORMATO TICKET (80mm ≈ 42 columnas) ============ */
const COLS = 42;
// Anchos de columnas (ajústalos si quieres)
const CANT_W = 6;     // ancho fijo de CANT (centrada)
const PREC_W = 10;    // PRECIO (derecha)
const IMP_W  = 10;    // IMPORTE (derecha)
const GAP    = 2;     // espacios entre bloques
const DESC_W = COLS - (CANT_W + PREC_W + IMP_W + GAP);

function padCenter($txt, $w){
    $len = mb_strwidth($txt, 'UTF-8');
    if ($len >= $w) return $txt;
    $left = intdiv($w - $len, 2);
    $right = $w - $len - $left;
    return str_repeat(' ', $left) . $txt . str_repeat(' ', $right);
}

function line2Cols($left, $right, $width = COLS) {
    $left = (string)$left; $right = (string)$right;
    $sp = $width - mb_strwidth($left, 'UTF-8') - mb_strwidth($right, 'UTF-8');
    if ($sp < 0) $sp = 0; // no forzar espacio si ya llena
    return $left . str_repeat(' ', $sp) . $right;
}
function wrapLines($text, $width = DESC_W) {
    $text = trim(preg_replace('/[ \t]+/', ' ', (string)$text));
    if ($text === '') return [];
    $words = preg_split('/\s/u', $text);
    $lines = []; $line = '';
    foreach ($words as $w) {
        $try = $line === '' ? $w : $line . ' ' . $w;
        if (mb_strwidth($try, 'UTF-8') <= $width) {
            $line = $try;
        } else {
            if ($line !== '') $lines[] = $line;
            while (mb_strwidth($w, 'UTF-8') > $width) {
                $cut = mb_strimwidth($w, 0, $width, '', 'UTF-8');
                $lines[] = $cut;
                $w = mb_substr($w, mb_strlen($cut, 'UTF-8'), null, 'UTF-8');
            }
            $line = $w;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
}

/** Fila de detalle: CANT x DESC  PREC  IMPORTE */
function itemRow($cantidad, $codigo, $descripcion, $precio, $importe) {
    $cantW = 6; $impW = 10; $precW = 10;
    $descW = COLS - $cantW - $precW - $impW - 2;

    // Cantidad centrada
    $cantTxt = fmtCantidad($cantidad);
    $cantCell = padCenter($cantTxt, $cantW);

    // Concatenar código al final de la descripción
    if (!empty($codigo)) {
       $descripcion = "[$codigo] " . $descripcion;

    }

    $precTxt = mxn($precio);
    $impTxt  = mxn($importe);

    $descLines = wrapLines($descripcion, $descW);
    if (!$descLines) $descLines = [''];

    $rows = [];
    foreach ($descLines as $i => $dl) {
        if ($i === 0) {
            $left = $cantCell . ' ' . str_pad($dl, $descW);
            $right = str_pad($precTxt, $precW, ' ', STR_PAD_LEFT) . ' ' .
                     str_pad($impTxt,  $impW,  ' ', STR_PAD_LEFT);
            $rows[] = line2Cols($left, $right, COLS);
        } else {
            $left = str_repeat(' ', $cantW) . ' ' . str_pad($dl, $descW);
            $right = str_repeat(' ', $precW + 1 + $impW);
            $rows[] = line2Cols($left, $right, COLS);
        }
    }
    return $rows;
}

/* ============ IMPRESIÓN ============ */
$printer = null;
try {
    // Nombre EXACTO como en Dispositivos e impresoras (el que ya te funcionó)
    $nombreImpresora = "EPSON TM-T88V";

    // Conexión por Windows (USB)
    $connector = new WindowsPrintConnector($nombreImpresora);

    // Perfil (si ves acentos raros, cambia a 'simple')
    $profile  = CapabilityProfile::load('default');
    $printer  = new Printer($connector, $profile);

    /* --- Encabezado --- */
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setEmphasis(true);
    $printer->text("REFACCIONARIA RIVERA\n");
    $printer->setEmphasis(false);
    $printer->text("KARINA VALENTINA RIVERA LEON\n");
    $printer->text("RFC: RILK830214NI9\n");
    $printer->text("Regimen Fiscal: 612\n"); // (quita acento si ves raro con tu codepage)
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

    // --- Cabecera detalle ---
    $printer->setEmphasis(true);

    // Left: "CANT  DESCRIPCION"
    $leftHeader = padCenter('CANT', CANT_W) . ' ' . str_pad('DESCRIPCION', DESC_W);
    // Right: "PRECIO   IMPORTE"
    $rightHeader = str_pad('PRECIO', PREC_W, ' ', STR_PAD_LEFT) . ' ' .
                str_pad('IMPORTE', IMP_W, ' ', STR_PAD_LEFT);

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

        // intenta sacar el código del producto de las llaves más comunes
        $codigo = '';
        foreach (['codigo', 'codigo_producto', 'sku', 'clave', 'clave_sat'] as $k) {
            if (!empty($d[$k])) { $codigo = (string)$d[$k]; break; }
        }

        // Texto base (nombre + desc si es distinta)
        $texto = $nombre;
        if ($desc !== '' && $desc !== $nombre) {
            $texto .= ' ' . $desc;
        }

        // Imprime filas (cantidad centrada y descripción con código)
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

    /* --- Avance + Corte --- */
    $printer->feed(4);
    $printer->cut();
    // $printer->pulse(); // abre gaveta si aplica

    echo "ok: Ticket #$idVenta enviado a la impresora.";
} catch (Throwable $e) {
    http_response_code(500);
    echo "error: " . $e->getMessage();
} finally {
    if ($printer instanceof Printer) {
        try { $printer->close(); } catch (\Throwable $t) {}
    }
}
