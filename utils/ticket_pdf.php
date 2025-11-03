<?php
// utils/ticket_pdf.php
// Ticket térmico 80mm — Layout “bueno” + nitidez (overprint, micro-offset, sin compresión)
// Con espacio extra bajo las últimas leyendas y patrón punteado para corte.

require __DIR__ . '/../assets/libs/fpdf/fpdf.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../models/VentaModel.php';

/* ===================== Helpers ===================== */
function mxn($n){ return '$'.number_format((float)$n, 2, '.', ','); }
function fechaMx($s){ $d = $s ? new DateTime($s) : new DateTime(); return $d->format('d/m/Y h:i a'); }

// 203 dpi ⇒ 1 dot ≈ 25.4/203 ≈ 0.125 mm
function mmPorDot(int $dpi = 203): float { return 25.4 / $dpi; }
// Alinea medidas al grid de dots (evita reescalado/antialias)
function snapMM(float $mm, int $dpi = 203): float { $step = mmPorDot($dpi); return round($mm / $step) * $step; }

// Estimar líneas con fuente actual (para medir alto)
function contarLineasFPDF(FPDF $pdf, string $texto, float $ancho_mm): int {
  $texto = trim($texto);
  if ($texto === '') return 0;
  $texto = preg_replace('/\s+/', ' ', $texto);
  $pal = explode(' ', $texto);
  $lineas = 1; $w = 0;
  foreach ($pal as $p) {
    $pw = $pdf->GetStringWidth(utf8_decode($p.' '));
    if ($w + $pw > $ancho_mm) { $lineas++; $w = $pw; } else { $w += $pw; }
  }
  return $lineas;
}

/* ===================== Entrada ===================== */
$idVenta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
if ($idVenta <= 0) { http_response_code(400); exit('Falta id_venta'); }

$ventaModel = new VentaModel();
$venta    = $ventaModel->obtenerVentaPorId($idVenta);
if (!$venta) { http_response_code(404); exit('Venta no encontrada'); }
$detalles = $ventaModel->obtenerDetalleVenta($idVenta);

/* ===================== Parámetros de look ===================== */
$DPI          = 203;
$PAGE_W       = snapMM(80, $DPI);           // ancho físico
$MARGIN       = snapMM(3,  $DPI);           // márgenes
$PAGE_WU      = $PAGE_W - 2*$MARGIN;        // ancho útil

// Columnas (acomodo “bueno”)
$W_CANT       = snapMM(12, $DPI);
$W_PREC       = snapMM(15, $DPI);
$W_TOT        = snapMM(17, $DPI);
$W_ART        = $PAGE_WU - $W_CANT - $W_PREC - $W_TOT;

$LH           = snapMM(5,  $DPI);           // alto de fila base
$LH_DESC      = snapMM(4,  $DPI);           // alto de multilínea
$LINE_W       = snapMM(0.25, $DPI);         // grosor de separadores
$GAP          = snapMM(1,  $DPI);           // respiro vertical

// Tipografías
$FS_HDR       = 12.0;                       // título
$FS_BODY      = 9.0;                        // cuerpo
$FS_SM        = 6.0;                        // pequeño
$BODY_STYLE   = 'B';                        // bold para mayor densidad
$OVERPRINT    = 3;                          // pasadas para oscurecer (2–3 recomendado)

// Cola / corte (ajuste)
$TAIL_ROWS    = 3;                           // ↓ un poco menos de espacio al final (antes 5)
$TAIL_DOTS    = 2;                           // líneas punteadas al final

/* ===================== Paso 1: Medir alto requerido ===================== */
$probe = new FPDF('P','mm',[ $PAGE_W, 600 ]);
$probe->SetMargins($MARGIN, $MARGIN, $MARGIN);
$probe->AddPage();
$probe->SetFont('Courier', $BODY_STYLE, $FS_BODY);

$alto = 0;
// Encabezado (título + 6 líneas + separador)
$alto += snapMM(5, $DPI);                   // título
$alto += 6 * snapMM(4, $DPI);               // 6 líneas de datos fiscales
$alto += $GAP + $LINE_W + $GAP;             // separador

// Meta (2 líneas + separador)
$alto += 2 * snapMM(4, $DPI);
$alto += $GAP + $LINE_W + $GAP;

// Cabecera de columnas
$alto += $LH;

// Detalle
foreach ($detalles as $d) {
  $art = trim((string)($d['producto'] ?? ''));
  $desc = trim((string)($d['descripcion'] ?? ''));
  if ($desc !== '' && $desc !== $art) $art .= "\n".$desc;

  $lineas = 0;
  foreach (explode("\n", $art) as $bloque) $lineas += contarLineasFPDF($probe, $bloque, $W_ART);
  $alto += max($LH, $lineas * $LH_DESC);
}

// Totales + mensajes + cola (con extra)
$alto += $GAP + $LINE_W + $GAP;             // sep antes de total
$alto += snapMM(5, $DPI);                   // línea de TOTAL
$alto += $GAP + $LINE_W + $GAP;             // sep después de total
$alto += 2 * snapMM(4, $DPI);               // 2 leyendas
$alto += $TAIL_ROWS * snapMM(4, $DPI);      // cola para cutter (ajustada)
$alto += $TAIL_DOTS * snapMM(4, $DPI);      // patrón punteado (alto reservado)

/* ===================== Paso 2: PDF definitivo ===================== */
$pdf = new FPDF('P','mm',[ $PAGE_W, $alto ]);
$pdf->SetMargins($MARGIN, $MARGIN, $MARGIN);
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);
$pdf->SetCompression(false);                // menos suavizado en algunos drivers
$pdf->SetTextColor(0,0,0);
$pdf->SetDrawColor(0);
$pdf->SetFillColor(255,255,255);
$pdf->SetLineWidth($LINE_W);

$X0 = $MARGIN;
$X1 = $PAGE_W - $MARGIN;

/* —— Herramientas de nitidez (overprint + micro-offset) —— */
$darkWrite = function(int $passes, callable $writer){
  $p = max(1, $passes);
  for ($i=0; $i<$p; $i++) $writer($i);
};

$cellDark = function(FPDF $pdf, float $w, float $h, string $txt, string $align='L', bool $ln=true) use ($darkWrite, $OVERPRINT){
  $txt = utf8_decode($txt);
  $x = $pdf->GetX(); $y = $pdf->GetY();
  $darkWrite($OVERPRINT, function($i) use($pdf,$x,$y,$w,$h,$txt,$align){
    // micro-offset ~0.05mm ≈ 0.4 dot
    $pdf->SetXY($x + ($i ? 0.05 : 0), $y);
    $pdf->Cell($w, $h, $txt, 0, 0, $align);
  });
  if ($ln || $w <= 0) $pdf->Ln($h); else $pdf->SetXY($x + $w, $y);
};

$fitCellDark = function(FPDF $pdf, float $w, float $h, string $text, string $align='R', float $minSize=7.0) use ($FS_BODY, $darkWrite, $OVERPRINT){
  $text = utf8_decode($text);
  $orig = $FS_BODY; $size = $orig;
  while ($pdf->GetStringWidth($text) > ($w - 0.5) && $size > $minSize) {
    $size -= 0.5; $pdf->SetFont('Courier','B', $size);
  }
  $x = $pdf->GetX(); $y = $pdf->GetY();
  $darkWrite($OVERPRINT, function($i) use($pdf,$x,$y,$w,$h,$text,$align){
    $pdf->SetXY($x + ($i ? 0.05 : 0), $y);
    $pdf->Cell($w, $h, $text, 0, 0, $align);
  });
  $pdf->SetFont('Courier','B', $orig);
};

/* ---------- Encabezado ---------- */
$pdf->SetFont('Courier','B', $FS_HDR);
$cellDark($pdf, 0, snapMM(5,$DPI), 'REFACCIONARIA RIVERA', 'C', true);

$pdf->SetFont('Courier', $BODY_STYLE, $FS_BODY);
$pdf->Cell(0, snapMM(4,$DPI), 'KARINA VALENTINA RIVERA LEON',      0, 1, 'C');
$pdf->Cell(0, snapMM(4,$DPI), 'RFC: RILK830214NI9',                 0, 1, 'C');
$pdf->Cell(0, snapMM(4,$DPI), utf8_decode('Régimen Fiscal: 612'),   0, 1, 'C');
$pdf->Cell(0, snapMM(4,$DPI), 'Blvd. Solidaridad 601, Col. Choyal', 0, 1, 'C');
$pdf->Cell(0, snapMM(4,$DPI), 'C.P. 83130 Hermosillo, Sonora',      0, 1, 'C');
$pdf->Cell(0, snapMM(4,$DPI), 'Tel: (662) 262-1129',                0, 1, 'C');

$pdf->Ln($GAP); $y=$pdf->GetY(); $pdf->Line($X0, $y, $X1, $y); $pdf->Ln($GAP);

/* ---------- Meta ---------- */
$pdf->SetFont('Courier', $BODY_STYLE, $FS_BODY);
$pdf->Cell(0, snapMM(4,$DPI), 'FECHA: '.fechaMx($venta['fecha'] ?? null), 0, 1, 'L');
$pdf->Cell(0, snapMM(4,$DPI), 'FOLIO: '.(($venta['folio'] ?? '') !== '' ? $venta['folio'] : 'VTA-'.$idVenta), 0, 1, 'L');

$pdf->Ln($GAP); $y=$pdf->GetY(); $pdf->Line($X0, $y, $X1, $y); $pdf->Ln($GAP);

/* ---------- Cabecera detalle ---------- */
$pdf->SetFont('Courier','B', $FS_BODY);
$pdf->Cell($W_CANT, $LH, 'CANT',     0, 0, 'L');
$pdf->Cell($W_ART,  $LH, 'ARTICULO', 0, 0, 'L');
$pdf->Cell($W_PREC, $LH, 'PRECIO',   0, 0, 'R');
$pdf->Cell($W_TOT,  $LH, 'TOTAL',    0, 1, 'R');
$pdf->SetFont('Courier', $BODY_STYLE, $FS_BODY);

/* ---------- Detalle ---------- */
$totalCalc = 0.0;

foreach ($detalles as $d) {
  $cant   = (float)($d['cantidad'] ?? 0);
  $precio = (float)($d['precio_unitario'] ?? 0);
  $imp    = isset($d['subtotal']) ? (float)$d['subtotal'] : $cant * $precio;
  $totalCalc += $imp;

  $art  = trim((string)($d['producto'] ?? ''));
  $desc = trim((string)($d['descripcion'] ?? ''));
  if ($desc !== '' && $desc !== $art) $art .= "\n".$desc;

  $y0 = $pdf->GetY();

  // CANT (oscurecida) — sin salto
  $pdf->SetFont('Courier','B', $FS_BODY);
  $cellDark($pdf, $W_CANT, $LH, rtrim(rtrim(number_format($cant,2,'.',''),'0'),'.'), 'L', false);
  $pdf->SetFont('Courier', $BODY_STYLE, $FS_BODY);

  // ART (multilínea)
  $xArt = $pdf->GetX();
  $pdf->MultiCell($W_ART, $LH_DESC, utf8_decode($art), 0, 'L');

  $y1 = $pdf->GetY();
  $h  = max($LH, $y1 - $y0);

  // PRECIO (oscurecido con ajuste)
  $pdf->SetXY($xArt + $W_ART, $y0);
  $fitCellDark($pdf, $W_PREC, $h, mxn($precio), 'R');

  // TOTAL (oscurecido con ajuste)
  $pdf->SetXY($xArt + $W_ART + $W_PREC, $y0);
  $fitCellDark($pdf, $W_TOT,  $h, mxn($imp), 'R');

  // Avanza al final de la fila real
  $pdf->SetY($y0 + $h);
}

/* ---------- Totales ---------- */
$pdf->Ln($GAP); $y=$pdf->GetY(); $pdf->Line($X0, $y, $X1, $y); $pdf->Ln($GAP);

$pdf->SetFont('Courier','B', $FS_BODY + 1.5);
$cellDark($pdf, $W_CANT + $W_ART, snapMM(5,$DPI), 'TOTAL:', 'R', false);
$cellDark($pdf, $W_PREC + $W_TOT, snapMM(5,$DPI), mxn(($venta['total'] ?? 0) ?: $totalCalc), 'R', true);

$pdf->Ln($GAP); $y=$pdf->GetY(); $pdf->Line($X0, $y, $X1, $y); $pdf->Ln($GAP);

/* ---------- Leyendas ---------- */
$pdf->SetFont('Courier', $BODY_STYLE, $FS_BODY);
$pdf->Cell(0, snapMM(4,$DPI), 'GRACIAS POR TU COMPRA', 0, 1, 'C');
$pdf->Cell(0, snapMM(4,$DPI), utf8_decode('EN PARTES ELÉCTRICAS NO HAY GARANTÍA'), 0, 1, 'C');

/* ---------- Cola extra bajo leyendas + patrón punteado ---------- */
// Cola (ajustada)
$pdf->SetFont('Courier','', $FS_SM);
for ($i=0; $i<$TAIL_ROWS; $i++) {
  $pdf->Cell(0, snapMM(4,$DPI), ' ', 0, 1, 'L');
}

// Patrón punteado (2 líneas)
$patron = str_repeat('.', 40);
for ($i=0; $i<$TAIL_DOTS; $i++) {
  $pdf->Cell(0, snapMM(4,$DPI), $patron, 0, 1, 'C');
}

/* ---------- Salida ---------- */
$pdf->Output('I', 'ticket_'.$idVenta.'.pdf');
