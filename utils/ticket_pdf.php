<?php
require __DIR__ . '/../assets/libs/fpdf/fpdf.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../models/VentaModel.php';

/* ---------- Helpers ---------- */
function mxn($n){ return '$'.number_format((float)$n, 2, '.', ','); }
function fechaMx($s){ $d = $s ? new DateTime($s) : new DateTime(); return $d->format('d/m/Y h:i a'); }

/** Cuenta líneas que ocupará un texto en un ancho dado con la fuente actual */
function contarLineasFPDF(FPDF $pdf, string $texto, float $ancho_mm): int {
    if ($texto === '') return 0;
    $texto = preg_replace('/\s+/', ' ', trim($texto));
    $pal = explode(' ', $texto);
    $lineas = 1; $w = 0;
    foreach ($pal as $p) {
        $pw = $pdf->GetStringWidth(utf8_decode($p.' '));
        if ($w + $pw > $ancho_mm) { $lineas++; $w = $pw; }
        else { $w += $pw; }
    }
    return $lineas;
}

/* ---------- Param ---------- */
$idVenta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
if ($idVenta <= 0) { http_response_code(400); exit('Falta id_venta'); }

/* ---------- Datos ---------- */
$ventaModel = new VentaModel();
$venta    = $ventaModel->obtenerVentaPorId($idVenta);
if (!$venta) { http_response_code(404); exit('Venta no encontrada'); }
$detalles = $ventaModel->obtenerDetalleVenta($idVenta);

/* ---------- Layout (80mm ancho; área útil ~72mm) ---------- */
$W_CANT = 12;   // mm
$W_PREC = 15;
$W_TOT  = 17;
$W_ART  = 72 - $W_CANT - $W_PREC - $W_TOT; // 28mm
$LH     = 5;    // alto base de fila
$LH_DESC= 4;    // alto de línea de MultiCell

// Tamaños “amigables” a 203 dpi (mejor nitidez)
$FS_HDR  = 12.06; // ~34 dots
$FS_BODY =  8.51; // ~24 dots
$FS_SM   =  6.03; // ~17 dots (texto chico)

/* ---------- Paso 1: medir alto necesario ---------- */
$probe = new FPDF('P','mm',[80, 600]); // alto temporal
$probe->SetMargins(3,3,3);
$probe->AddPage();
$probe->SetTextColor(0,0,0);
$probe->SetDrawColor(0);

// Mediremos con fuente de cuerpo
$probe->SetFont('Courier','', $FS_BODY);

// Alto base (encabezado+separadores+meta+cabecera+totales+mensajes)
$alto = 55 /*encabezado*/ + 10 /*meta*/ + 6 /*cabecera*/ + 22 /*totales+msgs*/;

foreach ($detalles as $d) {
    $artCorto = trim((string)($d['producto'] ?? ''));
    $desc     = trim((string)($d['descripcion'] ?? ''));
    $art = $artCorto;
    if ($desc !== '' && $desc !== $artCorto) $art .= "\n".$desc;

    $lineas = 0;
    foreach (explode("\n", $art) as $bloque) {
        $lineas += contarLineasFPDF($probe, $bloque, $W_ART);
    }
    $alto += max($LH, $lineas * $LH_DESC);
}

// Colchón para corte
$alto += 20;

/* ---------- Paso 2: PDF definitivo ---------- */
$pdf = new FPDF('P','mm',[80, $alto]);
$pdf->SetMargins(3,3,3);
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

// Colores sólidos (mejor en térmica)
$pdf->SetTextColor(0,0,0);
$pdf->SetDrawColor(0);
$pdf->SetFillColor(255,255,255);

/* ---------- Encabezado ---------- */
$pdf->SetFont('Courier','B', $FS_HDR);
$pdf->Cell(0,5,'REFACCIONARIA RIVERA',0,1,'C');
$pdf->SetFont('Courier','', $FS_BODY);
$pdf->Cell(0,4,'KARINA VALENTINA RIVERA LEON',0,1,'C');
$pdf->Cell(0,4,'RFC: RILK830214NI9',0,1,'C');
$pdf->Cell(0,4,utf8_decode('Régimen Fiscal: 612'),0,1,'C');
$pdf->Cell(0,4,'Blvd. Solidaridad 601, Col. Choyal',0,1,'C');
$pdf->Cell(0,4,'C.P. 83130 Hermosillo, Sonora',0,1,'C');
$pdf->Cell(0,4,'Tel: (662) 262-1129',0,1,'C');
$pdf->Ln(1); $pdf->Cell(0,0,'','T',1); $pdf->Ln(1);

/* ---------- Meta ---------- */
$pdf->SetFont('Courier','', $FS_BODY);
$pdf->Cell(0,4,'FECHA: '.fechaMx($venta['fecha'] ?? null),0,1,'L');
$pdf->Cell(0,4,'FOLIO: '.(($venta['folio'] ?? '') !== '' ? $venta['folio'] : 'VTA-'.$idVenta),0,1,'L');
$pdf->Ln(1); $pdf->Cell(0,0,'','T',1); $pdf->Ln(1);

/* ---------- Cabecera detalle ---------- */
$pdf->SetFont('Courier','B', $FS_BODY);
$pdf->Cell($W_CANT,$LH,'CANT',0,0,'L');
$pdf->Cell($W_ART, $LH,'ARTICULO',0,0,'L');
$pdf->Cell($W_PREC,$LH,'PRECIO',0,0,'R');
$pdf->Cell($W_TOT, $LH,'TOTAL', 0,1,'R');
$pdf->SetFont('Courier','', $FS_BODY);

/* Ajusta texto si se pasa del ancho (reduce fuente hasta 7 pt) */
$fitCell = function(FPDF $pdf, float $w, float $h, string $text, string $align = 'R') use ($FS_BODY) {
    $text = utf8_decode($text);
    $origSize = $FS_BODY; $size = $origSize;
    while ($pdf->GetStringWidth($text) > ($w - 0.5) && $size > 7.0) {
        $size -= 0.5;
        $pdf->SetFont('Courier', '', $size);
    }
    $pdf->Cell($w, $h, $text, 0, 0, $align);
    $pdf->SetFont('Courier', '', $origSize);
};

$totalCalc = 0.0;

/* ---------- Filas de detalle ---------- */
foreach ($detalles as $d) {
    $cant   = (float)($d['cantidad'] ?? 0);
    $precio = (float)($d['precio_unitario'] ?? 0);
    $imp    = isset($d['subtotal']) ? (float)$d['subtotal'] : $cant * $precio;
    $totalCalc += $imp;

    $artCorto = trim((string)($d['producto'] ?? ''));
    $desc     = trim((string)($d['descripcion'] ?? ''));
    $art = $artCorto;
    if ($desc !== '' && $desc !== $artCorto) $art .= "\n".$desc;

    $y0 = $pdf->GetY();

    // CANT
    $pdf->Cell($W_CANT, $LH, rtrim(rtrim(number_format($cant,2,'.',''),'0'),'.'), 0, 0, 'L');

    // ART (envuelve)
    $xArt = $pdf->GetX();
    // Para descripciones muy pequeñas, podemos usar $FS_SM (opcional)
    $pdf->SetFont('Courier','', $FS_BODY);
    $pdf->MultiCell($W_ART, $LH_DESC, utf8_decode($art), 0, 'L');

    $y1 = $pdf->GetY();
    $h  = max($LH, $y1 - $y0);

    // PRECIO / TOTAL con misma altura
    $pdf->SetXY($xArt + $W_ART, $y0);
    $fitCell($pdf, $W_PREC, $h, mxn($precio), 'R');

    $pdf->SetXY($xArt + $W_ART + $W_PREC, $y0);
    $fitCell($pdf, $W_TOT,  $h, mxn($imp), 'R');

    // Avanza al final real de la fila
    $pdf->SetY($y0 + $h);

    // (opcional) línea divisoria
    // $pdf->SetDrawColor(220); $pdf->Line(3, $pdf->GetY(), 77, $pdf->GetY()); $pdf->SetDrawColor(0);
}

/* ---------- Totales ---------- */
$pdf->Ln(1); $pdf->Cell(0,0,'','T',1); $pdf->Ln(1);
$pdf->SetFont('Courier','B', $FS_BODY + 1.5);
$pdf->Cell($W_CANT+$W_ART,5,'TOTAL:',0,0,'R');
$pdf->Cell($W_PREC+$W_TOT,5,mxn(($venta['total'] ?? 0) ?: $totalCalc),0,1,'R');
$pdf->Ln(1); $pdf->Cell(0,0,'','T',1); $pdf->Ln(1);

/* ---------- Mensajes ---------- */
$pdf->SetFont('Courier','', $FS_BODY);
$pdf->Cell(0,4,'GRACIAS POR TU COMPRA',0,1,'C');
$pdf->Cell(0,4,utf8_decode('EN PARTES ELÉCTRICAS NO HAY GARANTÍA'),0,1,'C');

/* ---------- Relleno físico antes del corte ---------- */
/* Estas “líneas” con espacios fuerzan papel real; ajusta cantidad/alto si quieres más */
$pdf->SetFont('Courier','', $FS_SM);
for ($i = 0; $i < 5; $i++) { // ~5 x 4mm = 20mm aprox
    $pdf->Cell(0, 4, ' ', 0, 1, 'L');
}

/* (Opcional) marca de corte tipo patrón limpio */
$patron = str_repeat('.', 40);
for ($i = 0; $i < 2; $i++) {
    $pdf->Cell(0, 4, $patron, 0, 1, 'C');
}

/* ---------- Salida ---------- */
$pdf->Output('I', 'ticket_'.$idVenta.'.pdf');
