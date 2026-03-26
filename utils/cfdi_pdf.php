<?php
// utils/cfdi_pdf.php
// Representación impresa CFDI 4.0 usando FPDF (fuente: ventas_cfdi.xml_timbrado)

require __DIR__ . '/../assets/libs/fpdf/fpdf.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../services/facturacion/FacturacionSchemaHelper.php';
require __DIR__ . '/../models/FacturacionModel.php';

function failResponse(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $msg;
    exit;
}

function pdfTxt($text): string
{
    $text = trim((string)$text);
    if ($text === '') return '';
    $encoded = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    return $encoded !== false ? $encoded : utf8_decode($text);
}

function mxn($n): string { return '$' . number_format((float)$n, 2, '.', ','); }

function xmlAttr($node, string $name, string $default = ''): string
{
    if (!$node) return $default;
    $attrs = $node->attributes();
    return isset($attrs[$name]) ? trim((string)$attrs[$name]) : $default;
}

function cadenaOriginalTfd(array $tfd): string
{
    if (empty($tfd['UUID']) || empty($tfd['FechaTimbrado']) || empty($tfd['SelloCFD']) || empty($tfd['NoCertificadoSAT'])) {
        return '';
    }
    return '||1.1|' . $tfd['UUID'] . '|' . $tfd['FechaTimbrado'] . '|' . ($tfd['RfcProvCertif'] ?? '') . '|' . $tfd['SelloCFD'] . '|' . $tfd['NoCertificadoSAT'] . '||';
}

class CfdiPdf extends FPDF
{
    public function sectionTitle(string $label): void
    {
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 7, pdfTxt($label), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }

    public function kv(string $label, string $value, float $labelW = 46): void
    {
        $this->SetFont('Arial', 'B', 8.5);
        $this->Cell($labelW, 5.5, pdfTxt($label), 0, 0, 'L');
        $this->SetFont('Arial', '', 8.5);
        $this->MultiCell(0, 5.5, pdfTxt($value !== '' ? $value : 'N/D'), 0, 'L');
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, pdfTxt('Este documento es una representación impresa de un CFDI'), 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 4, pdfTxt('Página ') . $this->PageNo(), 0, 0, 'R');
    }
}

$idVenta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
if ($idVenta <= 0) {
    failResponse(400, 'Falta id_venta para generar el PDF del CFDI.');
}

$schema = new FacturacionSchemaHelper($pdo);
$model = new FacturacionModel($pdo, $schema);
$cfdi = $model->getCfdiByVenta($idVenta);
if (!$cfdi) {
    failResponse(404, 'No existe CFDI relacionado para la venta solicitada.');
}

$xmlTimbrado = trim((string)($cfdi['xml_timbrado'] ?? ''));
if ($xmlTimbrado === '') {
    failResponse(404, 'El CFDI no cuenta con XML timbrado para generar su representación impresa.');
}

libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlTimbrado);
if (!$xml) {
    failResponse(422, 'No fue posible leer el XML timbrado del CFDI.');
}

$ns = $xml->getNamespaces(true);
$cfdiNs = $ns['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4';
$tfdNs = $ns['tfd'] ?? 'http://www.sat.gob.mx/TimbreFiscalDigital';
$xml->registerXPathNamespace('cfdi', $cfdiNs);
$xml->registerXPathNamespace('tfd', $tfdNs);

$comprobanteNode = ($xml->xpath('/cfdi:Comprobante')[0] ?? $xml);
$emisorNode = $xml->xpath('/cfdi:Comprobante/cfdi:Emisor')[0] ?? null;
$receptorNode = $xml->xpath('/cfdi:Comprobante/cfdi:Receptor')[0] ?? null;
$impuestosNode = $xml->xpath('/cfdi:Comprobante/cfdi:Impuestos')[0] ?? null;
$tfdNode = $xml->xpath('/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital')[0] ?? null;

$conceptoNodes = $xml->xpath('/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto') ?: [];
$trasladosGlobal = $xml->xpath('/cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado') ?: [];

$comprobante = [
    'Serie' => xmlAttr($comprobanteNode, 'Serie'),
    'Folio' => xmlAttr($comprobanteNode, 'Folio'),
    'Fecha' => xmlAttr($comprobanteNode, 'Fecha'),
    'Moneda' => xmlAttr($comprobanteNode, 'Moneda'),
    'FormaPago' => xmlAttr($comprobanteNode, 'FormaPago'),
    'MetodoPago' => xmlAttr($comprobanteNode, 'MetodoPago'),
    'TipoDeComprobante' => xmlAttr($comprobanteNode, 'TipoDeComprobante'),
    'Exportacion' => xmlAttr($comprobanteNode, 'Exportacion'),
    'LugarExpedicion' => xmlAttr($comprobanteNode, 'LugarExpedicion'),
    'SubTotal' => xmlAttr($comprobanteNode, 'SubTotal', '0'),
    'Total' => xmlAttr($comprobanteNode, 'Total', '0'),
    'NoCertificado' => xmlAttr($comprobanteNode, 'NoCertificado'),
    'SelloCFD' => xmlAttr($comprobanteNode, 'Sello'),
];

$emisor = [
    'Rfc' => xmlAttr($emisorNode, 'Rfc'),
    'Nombre' => xmlAttr($emisorNode, 'Nombre'),
    'RegimenFiscal' => xmlAttr($emisorNode, 'RegimenFiscal'),
];

$receptor = [
    'Rfc' => xmlAttr($receptorNode, 'Rfc'),
    'Nombre' => xmlAttr($receptorNode, 'Nombre'),
    'DomicilioFiscalReceptor' => xmlAttr($receptorNode, 'DomicilioFiscalReceptor'),
    'RegimenFiscalReceptor' => xmlAttr($receptorNode, 'RegimenFiscalReceptor'),
    'UsoCFDI' => xmlAttr($receptorNode, 'UsoCFDI'),
];

$tfd = [
    'UUID' => xmlAttr($tfdNode, 'UUID', trim((string)($cfdi['uuid'] ?? ''))),
    'FechaTimbrado' => xmlAttr($tfdNode, 'FechaTimbrado', trim((string)($cfdi['fecha_timbrado'] ?? ''))),
    'SelloCFD' => xmlAttr($tfdNode, 'SelloCFD', $comprobante['SelloCFD']),
    'SelloSAT' => xmlAttr($tfdNode, 'SelloSAT'),
    'NoCertificadoSAT' => xmlAttr($tfdNode, 'NoCertificadoSAT'),
    'RfcProvCertif' => xmlAttr($tfdNode, 'RfcProvCertif'),
];

$impuestos = [
    'TotalImpuestosTrasladados' => xmlAttr($impuestosNode, 'TotalImpuestosTrasladados', '0'),
    'TotalImpuestosRetenidos' => xmlAttr($impuestosNode, 'TotalImpuestosRetenidos', '0'),
    'traslados' => [],
];
foreach ($trasladosGlobal as $t) {
    $impuestos['traslados'][] = [
        'Impuesto' => xmlAttr($t, 'Impuesto'),
        'TipoFactor' => xmlAttr($t, 'TipoFactor'),
        'TasaOCuota' => xmlAttr($t, 'TasaOCuota'),
        'Base' => xmlAttr($t, 'Base', '0'),
        'Importe' => xmlAttr($t, 'Importe', '0'),
    ];
}

$conceptos = [];
foreach ($conceptoNodes as $c) {
    $conceptos[] = [
        'Cantidad' => xmlAttr($c, 'Cantidad', '0'),
        'ClaveUnidad' => xmlAttr($c, 'ClaveUnidad'),
        'NoIdentificacion' => xmlAttr($c, 'NoIdentificacion'),
        'Descripcion' => xmlAttr($c, 'Descripcion'),
        'ValorUnitario' => xmlAttr($c, 'ValorUnitario', '0'),
        'ObjetoImp' => xmlAttr($c, 'ObjetoImp'),
        'Importe' => xmlAttr($c, 'Importe', '0'),
    ];
}

$cadenaOriginal = cadenaOriginalTfd($tfd);
$uuid = $tfd['UUID'] !== '' ? $tfd['UUID'] : ((string)($cfdi['uuid'] ?? 'sin_uuid'));

$pdf = new CfdiPdf('P', 'mm', 'Letter');
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(130, 7, pdfTxt($emisor['Nombre'] !== '' ? $emisor['Nombre'] : 'EMISOR'), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, pdfTxt('Factura ' . trim(($comprobante['Serie'] !== '' ? $comprobante['Serie'] . '-' : '') . $comprobante['Folio'])), 0, 1, 'R');

$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(130, 5, pdfTxt('RFC: ' . ($emisor['Rfc'] ?: 'N/D')), 0, 0, 'L');
$pdf->Cell(0, 5, pdfTxt('UUID: ' . ($tfd['UUID'] ?: 'N/D')), 0, 1, 'R');
$pdf->Cell(130, 5, pdfTxt('Régimen fiscal: ' . ($emisor['RegimenFiscal'] ?: 'N/D')), 0, 0, 'L');
$pdf->Cell(0, 5, pdfTxt('Fecha timbrado: ' . ($tfd['FechaTimbrado'] ?: 'N/D')), 0, 1, 'R');
$pdf->Cell(130, 5, pdfTxt('Lugar expedición: ' . ($comprobante['LugarExpedicion'] ?: 'N/D')), 0, 0, 'L');
$pdf->Cell(0, 5, pdfTxt('Estatus: ' . strtoupper((string)($cfdi['estatus'] ?? 'N/D'))), 0, 1, 'R');

$pdf->Ln(2);
$pdf->sectionTitle('CLIENTE / RECEPTOR');
$pdf->kv('Nombre:', $receptor['Nombre']);
$pdf->kv('RFC:', $receptor['Rfc']);
$pdf->kv('Uso CFDI:', $receptor['UsoCFDI']);
$pdf->kv('Régimen fiscal receptor:', $receptor['RegimenFiscalReceptor']);
$pdf->kv('CP domicilio fiscal:', $receptor['DomicilioFiscalReceptor']);

$pdf->Ln(1);
$pdf->sectionTitle('DATOS FISCALES DEL CFDI');
$pdf->kv('Referencia:', (string)($cfdi['referencia'] ?? ''));
$pdf->kv('Serie / Folio:', trim(($comprobante['Serie'] !== '' ? $comprobante['Serie'] . '-' : '') . $comprobante['Folio']));
$pdf->kv('Fecha emisión:', $comprobante['Fecha']);
$pdf->kv('Tipo comprobante:', $comprobante['TipoDeComprobante']);
$pdf->kv('Moneda:', $comprobante['Moneda']);
$pdf->kv('Forma de pago:', $comprobante['FormaPago']);
$pdf->kv('Método de pago:', $comprobante['MetodoPago']);
$pdf->kv('Exportación:', $comprobante['Exportacion']);
$pdf->kv('No. certificado emisor:', $comprobante['NoCertificado']);
$pdf->kv('No. certificado SAT:', $tfd['NoCertificadoSAT']);
$pdf->kv('RFC prov. certificación:', $tfd['RfcProvCertif']);

$pdf->Ln(1);
$pdf->sectionTitle('CONCEPTOS');

$col = ['cant' => 18, 'unidad' => 20, 'id' => 27, 'desc' => 76, 'pu' => 22, 'obj' => 18, 'imp' => 22];
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($col['cant'], 6, 'Cantidad', 1, 0, 'C', true);
$pdf->Cell($col['unidad'], 6, 'Unidad', 1, 0, 'C', true);
$pdf->Cell($col['id'], 6, 'No. Ident.', 1, 0, 'C', true);
$pdf->Cell($col['desc'], 6, pdfTxt('Descripción'), 1, 0, 'C', true);
$pdf->Cell($col['pu'], 6, 'P. Unitario', 1, 0, 'C', true);
$pdf->Cell($col['obj'], 6, 'Obj.Imp.', 1, 0, 'C', true);
$pdf->Cell($col['imp'], 6, 'Importe', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7.8);
foreach ($conceptos as $c) {
    $desc = pdfTxt($c['Descripcion']);
    $y = $pdf->GetY();
    $x = $pdf->GetX();
    $hDesc = max(6, 4 * ceil(max(1, $pdf->GetStringWidth($desc) / max(1, $col['desc'] - 2))));

    if ($pdf->GetY() + $hDesc > 250) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($col['cant'], 6, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell($col['unidad'], 6, 'Unidad', 1, 0, 'C', true);
        $pdf->Cell($col['id'], 6, 'No. Ident.', 1, 0, 'C', true);
        $pdf->Cell($col['desc'], 6, pdfTxt('Descripción'), 1, 0, 'C', true);
        $pdf->Cell($col['pu'], 6, 'P. Unitario', 1, 0, 'C', true);
        $pdf->Cell($col['obj'], 6, 'Obj.Imp.', 1, 0, 'C', true);
        $pdf->Cell($col['imp'], 6, 'Importe', 1, 1, 'C', true);
        $pdf->SetFont('Arial', '', 7.8);
        $y = $pdf->GetY();
        $x = $pdf->GetX();
    }

    $pdf->Cell($col['cant'], $hDesc, pdfTxt($c['Cantidad']), 1, 0, 'C');
    $pdf->Cell($col['unidad'], $hDesc, pdfTxt($c['ClaveUnidad']), 1, 0, 'C');
    $pdf->Cell($col['id'], $hDesc, pdfTxt($c['NoIdentificacion']), 1, 0, 'C');

    $xDesc = $pdf->GetX();
    $yDesc = $pdf->GetY();
    $pdf->MultiCell($col['desc'], 4, $desc, 1, 'L');
    $pdf->SetXY($xDesc + $col['desc'], $yDesc);

    $pdf->Cell($col['pu'], $hDesc, mxn($c['ValorUnitario']), 1, 0, 'R');
    $pdf->Cell($col['obj'], $hDesc, pdfTxt($c['ObjetoImp']), 1, 0, 'C');
    $pdf->Cell($col['imp'], $hDesc, mxn($c['Importe']), 1, 1, 'R');
}

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(130, 6, pdfTxt('IMPORTE CON LETRA'), 0, 0, 'L');
$pdf->Cell(30, 6, 'SUBTOTAL', 0, 0, 'R');
$pdf->Cell(30, 6, mxn($comprobante['SubTotal']), 0, 1, 'R');

$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(130, 5, pdfTxt('CFDI 4.0 - Moneda: ' . ($comprobante['Moneda'] ?: 'N/D')), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 5, 'IMPUESTOS', 0, 0, 'R');
$pdf->Cell(30, 5, mxn($impuestos['TotalImpuestosTrasladados']), 0, 1, 'R');
$pdf->Cell(130, 5, '', 0, 0, 'L');
$pdf->Cell(30, 5, 'TOTAL', 0, 0, 'R');
$pdf->Cell(30, 5, mxn($comprobante['Total']), 0, 1, 'R');

if (!empty($impuestos['traslados'])) {
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell(0, 5, 'Desglose de impuestos trasladados', 0, 1);
    $pdf->SetFont('Arial', '', 8);
    foreach ($impuestos['traslados'] as $t) {
        $linea = sprintf(
            'Impuesto: %s | Tipo: %s | Tasa: %s | Base: %s | Importe: %s',
            $t['Impuesto'] ?: 'N/D',
            $t['TipoFactor'] ?: 'N/D',
            $t['TasaOCuota'] ?: 'N/D',
            mxn($t['Base']),
            mxn($t['Importe'])
        );
        $pdf->MultiCell(0, 4.5, pdfTxt($linea), 0, 'L');
    }
}

$pdf->Ln(2);
$pdf->sectionTitle('SELLOS Y CADENA ORIGINAL');
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(0, 5, 'Sello digital del CFDI', 0, 1);
$pdf->SetFont('Arial', '', 7.2);
$pdf->MultiCell(0, 3.8, pdfTxt($tfd['SelloCFD']), 0, 'L');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(0, 5, 'Sello digital del SAT', 0, 1);
$pdf->SetFont('Arial', '', 7.2);
$pdf->MultiCell(0, 3.8, pdfTxt($tfd['SelloSAT']), 0, 'L');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(0, 5, 'Cadena original del complemento de certificación digital del SAT', 0, 1);
$pdf->SetFont('Arial', '', 7.2);
$pdf->MultiCell(0, 3.8, pdfTxt($cadenaOriginal), 0, 'L');

$filename = 'cfdi_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $uuid) . '.pdf';
$pdf->Output('I', $filename);
