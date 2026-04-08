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

function satLabelValue(string $value, ?string $description): string
{
    $value = trim($value);
    $description = trim((string)$description);

    if ($value === '') {
        return '';
    }

    if ($description === '') {
        return $value;
    }

    if (strpos($value, ' - ') !== false) {
        return $value;
    }

    return $value . ' - ' . $description;
}

function satSplitLabel(string $value, ?string $label): array
{
    $clave = trim($value);
    $descripcion = trim((string)$label);

    if ($descripcion === '') {
        return [$clave, ''];
    }

    if ($clave !== '' && strpos($descripcion, $clave . ' - ') === 0) {
        $descripcion = trim(substr($descripcion, strlen($clave . ' - ')));
    } elseif (preg_match('/^([A-Za-z0-9]+)\s*-\s*(.+)$/u', $descripcion, $m)) {
        if ($clave === '') {
            $clave = trim($m[1]);
        }
        $descripcion = trim($m[2]);
    }

    return [$clave, $descripcion];
}

function satCatalogDescription(PDO $conn, FacturacionSchemaHelper $schema, string $table, string $value, array $valueColumns, array $descriptionColumns): ?string
{
    $value = trim($value);
    if ($value === '' || !$schema->tableExists($table)) {
        return null;
    }

    $colValue = $schema->pickColumn($table, $valueColumns);
    $colDescription = $schema->pickColumn($table, $descriptionColumns);
    if (!$colValue || !$colDescription) {
        return null;
    }

    $hasActivo = $schema->hasColumn($table, 'Activo') || $schema->hasColumn($table, 'activo');
    $colActivo = $schema->hasColumn($table, 'Activo') ? 'Activo' : ($schema->hasColumn($table, 'activo') ? 'activo' : null);
    $sql = sprintf(
        'SELECT %s AS descripcion FROM %s WHERE %s = :clave%s LIMIT 1',
        $colDescription,
        $table,
        $colValue,
        ($hasActivo && $colActivo) ? (' AND ' . $colActivo . ' = 1') : ''
    );
    $st = $conn->prepare($sql);
    $st->execute([':clave' => $value]);

    $descripcion = $st->fetchColumn();
    if (!is_string($descripcion)) {
        return null;
    }

    $descripcion = trim($descripcion);
    return $descripcion !== '' ? $descripcion : null;
}

function satResolveLabel(PDO $conn, FacturacionSchemaHelper $schema, string $catalogName, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $config = [
        'tipo_comprobante' => [
            'table' => 'cat_sat_tipo_comprobante',
            'value_cols' => ['ClaveTipoComprobante', 'ClaveTipoDeComprobante', 'Clave'],
            'description_cols' => ['Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [
                'I' => 'Ingreso',
                'E' => 'Egreso',
                'T' => 'Traslado',
                'N' => 'Nómina',
                'P' => 'Pago',
            ],
        ],
        'forma_pago' => [
            'table' => 'formas_pago',
            'value_cols' => ['clave_sat', 'ClaveFormaPago', 'Clave'],
            'description_cols' => ['descripcion', 'Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [],
        ],
        'metodo_pago' => [
            'table' => 'cat_sat_metodo_pago',
            'value_cols' => ['ClaveMetodoPago', 'Clave'],
            'description_cols' => ['Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [
                'PUE' => 'Pago en una sola exhibición',
                'PPD' => 'Pago en parcialidades o diferido',
            ],
        ],
        'moneda' => [
            'table' => 'cat_sat_moneda',
            'value_cols' => ['ClaveMoneda', 'Clave'],
            'description_cols' => ['Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [],
        ],
        'exportacion' => [
            'table' => 'cat_sat_exportacion',
            'value_cols' => ['ClaveExportacion', 'ClaveExportación', 'Clave'],
            'description_cols' => ['Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [],
        ],
        'uso_cfdi' => [
            'table' => 'cat_sat_uso_cfdi',
            'value_cols' => ['ClaveUsoCFDI', 'Clave'],
            'description_cols' => ['Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [],
        ],
        'regimen_fiscal' => [
            'table' => 'cat_sat_regimen_fiscal',
            'value_cols' => ['ClaveRegimenFiscal', 'Clave'],
            'description_cols' => ['Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [],
        ],
        'objeto_imp' => [
            'table' => 'cat_sat_objeto_imp',
            'value_cols' => ['ClaveObjetoImp', 'ClaveObjetoImpuesto', 'Clave'],
            'description_cols' => ['Descripcion', 'Descripción', 'Nombre'],
            'fallback' => [
                '01' => 'No objeto de impuesto',
                '02' => 'Sí objeto de impuesto',
                '03' => 'Sí objeto del impuesto y no obligado al desglose',
                '04' => 'Sí objeto del impuesto y no causa impuesto',
            ],
        ],
    ];

    if (!isset($config[$catalogName])) {
        return $value;
    }

    $def = $config[$catalogName];
    $description = satCatalogDescription(
        $conn,
        $schema,
        $def['table'],
        $value,
        $def['value_cols'],
        $def['description_cols']
    );

    if ($description === null) {
        $description = $def['fallback'][$value] ?? null;
    }

    return satLabelValue($value, $description);
}

function cadenaOriginalTfd(array $tfd): string
{
    if (empty($tfd['UUID']) || empty($tfd['FechaTimbrado']) || empty($tfd['SelloCFD']) || empty($tfd['NoCertificadoSAT'])) {
        return '';
    }
    return '||1.1|' . $tfd['UUID'] . '|' . $tfd['FechaTimbrado'] . '|' . ($tfd['RfcProvCertif'] ?? '') . '|' . $tfd['SelloCFD'] . '|' . $tfd['NoCertificadoSAT'] . '||';
}

function satTotalForQr(string $total): string
{
    $tt = number_format((float)$total, 6, '.', '');
    $tt = rtrim(rtrim($tt, '0'), '.');
    return $tt === '' ? '0' : $tt;
}

function buildCfdiQrUrl(array $tfd, array $comprobante, array $emisor, array $receptor): string
{
    $uuid  = trim((string)($tfd['UUID'] ?? ''));
    $re    = trim((string)($emisor['Rfc'] ?? ''));
    $rr    = trim((string)($receptor['Rfc'] ?? ''));
    $tt    = satTotalForQr((string)($comprobante['Total'] ?? '0'));
    $sello = trim((string)($comprobante['SelloCFD'] ?? ''));
    $fe    = strtoupper(substr($sello, -8));

    return sprintf(
        'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?&id=%s&re=%s&rr=%s&tt=%s&fe=%s',
        strtoupper($uuid),
        strtoupper($re),
        strtoupper($rr),
        $tt,
        $fe
    );
}

function findLogoPath(): ?string
{
    $candidates = [
        __DIR__ . '/../assets/images/rr1_black.png',
        __DIR__ . '/../assets/images/rr1_black.png',
        __DIR__ . '/../assets/images/rr1_black.png',
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) return $p;
    }
    return null;
}

function downloadQrTemp(string $content): ?string
{
    $endpoint = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=' . rawurlencode($content);
    $img = @file_get_contents($endpoint);
    if (!$img || strlen($img) < 100) return null;

    $tmp = tempnam(sys_get_temp_dir(), 'cfdi_qr_');
    if ($tmp === false) return null;
    $png = $tmp . '.png';
    @rename($tmp, $png);
    if (@file_put_contents($png, $img) === false) return null;
    return $png;
}

class CfdiPdf extends FPDF
{
    public function sectionTitle(string $label): void
    {
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6.2, pdfTxt($label), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }

    public function kvInline(string $label, string $value, float $x, float $y, float $wLabel = 44, float $h = 4.6): void
    {
        $this->SetXY($x, $y);
        $this->SetFont('Arial', 'B', 8.4);
        $this->Cell($wLabel, $h, pdfTxt($label), 0, 0, 'L');
        $this->SetFont('Arial', '', 8.4);
        $this->Cell(0, $h, pdfTxt($value !== '' ? $value : 'N/D'), 0, 1, 'L');
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 7.2);
        $this->SetTextColor(70, 70, 70);
        $this->Cell(0, 3.8, pdfTxt('Este documento es una representación impresa de un CFDI.'), 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 3.8, pdfTxt(''), 0, 0, 'L');
        $this->Cell(0, 3.8, pdfTxt('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }
}

$idVenta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
if ($idVenta <= 0) {
    failResponse(400, 'Falta id_venta para generar el PDF del CFDI.');
}

$schema = new FacturacionSchemaHelper($pdo);
$model = new FacturacionModel($pdo, $schema);
$cfdi = $model->getCfdiEmitidoByVenta($idVenta);
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
    'Version' => xmlAttr($comprobanteNode, 'Version'),
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

$comprobanteLabels = [
    'TipoDeComprobante' => satResolveLabel($pdo, $schema, 'tipo_comprobante', $comprobante['TipoDeComprobante']),
    'FormaPago' => satResolveLabel($pdo, $schema, 'forma_pago', $comprobante['FormaPago']),
    'MetodoPago' => satResolveLabel($pdo, $schema, 'metodo_pago', $comprobante['MetodoPago']),
    'Moneda' => satResolveLabel($pdo, $schema, 'moneda', $comprobante['Moneda']),
    'Exportacion' => satResolveLabel($pdo, $schema, 'exportacion', $comprobante['Exportacion']),
];

$emisorLabels = [
    'RegimenFiscal' => satResolveLabel($pdo, $schema, 'regimen_fiscal', $emisor['RegimenFiscal']),
];

$receptor = [
    'Rfc' => xmlAttr($receptorNode, 'Rfc'),
    'Nombre' => xmlAttr($receptorNode, 'Nombre'),
    'DomicilioFiscalReceptor' => xmlAttr($receptorNode, 'DomicilioFiscalReceptor'),
    'RegimenFiscalReceptor' => xmlAttr($receptorNode, 'RegimenFiscalReceptor'),
    'UsoCFDI' => xmlAttr($receptorNode, 'UsoCFDI'),
];

$receptorLabels = [
    'UsoCFDI' => satResolveLabel($pdo, $schema, 'uso_cfdi', $receptor['UsoCFDI']),
    'RegimenFiscalReceptor' => satResolveLabel($pdo, $schema, 'regimen_fiscal', $receptor['RegimenFiscalReceptor']),
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
    $trasladosConcepto = [];
    $tras = $c->xpath('cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado') ?: [];
    foreach ($tras as $tc) {
        $trasladosConcepto[] = [
            'Impuesto' => xmlAttr($tc, 'Impuesto'),
            'TipoFactor' => xmlAttr($tc, 'TipoFactor'),
            'TasaOCuota' => xmlAttr($tc, 'TasaOCuota'),
            'Base' => xmlAttr($tc, 'Base', '0'),
            'Importe' => xmlAttr($tc, 'Importe', '0'),
        ];
    }

    $conceptos[] = [
        'Cantidad' => xmlAttr($c, 'Cantidad', '0'),
        'ClaveUnidad' => xmlAttr($c, 'ClaveUnidad'),
        'ClaveProdServ' => xmlAttr($c, 'ClaveProdServ'),
        'NoIdentificacion' => xmlAttr($c, 'NoIdentificacion'),
        'Descripcion' => xmlAttr($c, 'Descripcion'),
        'ValorUnitario' => xmlAttr($c, 'ValorUnitario', '0'),
        'ObjetoImp' => xmlAttr($c, 'ObjetoImp'),
        'ObjetoImpLabel' => satResolveLabel($pdo, $schema, 'objeto_imp', xmlAttr($c, 'ObjetoImp')),
        'Importe' => xmlAttr($c, 'Importe', '0'),
        'traslados' => $trasladosConcepto,
    ];
}

$cadenaOriginal = cadenaOriginalTfd($tfd);
$uuid = $tfd['UUID'] !== '' ? $tfd['UUID'] : ((string)($cfdi['uuid'] ?? 'sin_uuid'));
$folioText = trim(($comprobante['Serie'] !== '' ? $comprobante['Serie'] . '-' : '') . $comprobante['Folio']);
$importeLetra = trim((string)($cfdi['total_letra'] ?? ''));
if ($importeLetra === '') $importeLetra = 'IMPORTE CON LETRA NO DISPONIBLE';

$pdf = new CfdiPdf('P', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetMargins(8, 8, 8);
$pdf->AddPage();

$logoPath = findLogoPath();
$headerTop = $pdf->GetY();

// Encabezado de 3 zonas
$leftX = 8;
$centerX = 46;
$rightX = 136;
$centerW = 88;

$pdf->SetDrawColor(200, 200, 200);
//$pdf->Rect($leftX, $headerTop, 35, 33);
if ($logoPath) {
    $pdf->Image($logoPath, $leftX + 2, $headerTop + 4, 45);
} else {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY($leftX, $headerTop + 13);
    $pdf->Cell(35, 6, 'LOGO', 10, 10, 'C');
}

$pdf->SetXY($centerX + 10, $headerTop);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell($centerW, 5.8, pdfTxt($emisor['Nombre'] ?: 'EMISOR'), 0, 1, 'C');
$pdf->SetX($centerX + 10);
$pdf->SetFont('Arial', 'B', 8.7);
$pdf->Cell($centerW, 4.5, pdfTxt('RFC: ' . ($emisor['Rfc'] ?: 'N/D')), 0, 1, 'C');
$pdf->SetX($centerX + 10);
$pdf->SetFont('Arial', '', 8);
[$regimenClave, $regimenDescripcion] = satSplitLabel((string)($emisor['RegimenFiscal'] ?? ''), (string)($emisorLabels['RegimenFiscal'] ?? ''));
$pdf->MultiCell($centerW, 3.9, pdfTxt('RÉGIMEN FISCAL: ' . ($regimenClave !== '' ? $regimenClave : 'N/D')), 0, 'C');
if ($regimenDescripcion !== '') {
    $pdf->SetX($centerX + 10);
    $pdf->MultiCell($centerW, 3.9, pdfTxt($regimenDescripcion), 0, 'C');
}
$pdf->SetX($centerX + 10);
$pdf->MultiCell($centerW, 3.9, pdfTxt('LUGAR EXPEDICIÓN: ' . ($comprobante['LugarExpedicion'] ?: 'N/D')), 0, 'C');
if (!empty($cfdi['sucursal_nombre'])) {
    $pdf->SetX($centerX + 10);
    $pdf->MultiCell($centerW, 3.9, pdfTxt((string)$cfdi['sucursal_nombre']), 0, 'C');
}

$pdf->SetFillColor(245, 245, 245);
$pdf->SetXY($rightX + 9, $headerTop + 1.4);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(64, 5, pdfTxt('Factura ' . ($folioText ?: 'N/D')), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 7.5);
$rightInfo = [
    'UUID' => $tfd['UUID'],
    'Folio fiscal' => $tfd['UUID'],
    'No. serie certificado SAT' => $tfd['NoCertificadoSAT'],
    'No. serie certificado emisor' => $comprobante['NoCertificado'],
    'Fecha y hora certificación' => $tfd['FechaTimbrado'],
    'RFC proveedor de certificación' => $tfd['RfcProvCertif'],
    'Fecha y hora emisión' => $comprobante['Fecha'],
    'Lugar de expedición' => $comprobante['LugarExpedicion'],
];
foreach ($rightInfo as $k => $v) {
    $pdf->SetX($rightX + -1);
    $pdf->Cell(31, 3.5, pdfTxt($k), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 7.3);
    $pdf->Cell(43, 3.5, pdfTxt($v ?: 'N/D'), 0, 1, 'R');
    $pdf->SetFont('Arial', 'B', 7.5);
}

$headerBottomY = max($headerTop + 38, $pdf->GetY() + 1);
$pdf->SetY($headerBottomY);

// Cliente
$pdf->sectionTitle('CLIENTE');
$yCliente = $pdf->GetY() + 0.5;
$pdf->kvInline('Nombre:', $receptor['Nombre'], 10, $yCliente, 36);
$pdf->kvInline('RFC:', $receptor['Rfc'], 10, $yCliente + 5, 36);
$pdf->kvInline('Uso CFDI:', $receptorLabels['UsoCFDI'], 10, $yCliente + 10, 36);
$pdf->kvInline('Domicilio fiscal:', $receptor['DomicilioFiscalReceptor'], 10, $yCliente + 15, 36);
$pdf->kvInline('Régimen fiscal receptor:', $receptorLabels['RegimenFiscalReceptor'], 10, $yCliente + 20, 36);
if (!empty($cfdi['cliente_direccion'])) {
    $pdf->kvInline('Dirección:', (string)$cfdi['cliente_direccion'], 10, $yCliente + 25, 36);
    $pdf->SetY($yCliente + 31);
} else {
    $pdf->SetY($yCliente + 26);
}

// Conceptos
$pdf->sectionTitle('CONCEPTOS');

$col = [
    'cant'   => 17,
    'unidad' => 17,
    'id'     => 24,
    'desc'   => 74,
    'pu'     => 22,
    'obj'    => 24,
    'imp'    => 22,
];

$renderConceptHeader = function($pdf, $col) {
    $pdf->SetFillColor(0, 0, 0);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 7.6);

    $pdf->Cell($col['cant'], 6, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell($col['unidad'], 6, 'Unidad', 1, 0, 'C', true);
    $pdf->Cell($col['id'], 6, 'No. Ident.', 1, 0, 'C', true);
    $pdf->Cell($col['desc'], 6, pdfTxt('Descripción'), 1, 0, 'C', true);
    $pdf->Cell($col['pu'], 6, 'Precio Unitario', 1, 0, 'C', true);
    $pdf->Cell($col['obj'], 6, 'Objeto Imp.', 1, 0, 'C', true);
    $pdf->Cell($col['imp'], 6, 'Importe', 1, 1, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 7.2);
};

$getWrappedLines = function($pdf, string $text, float $width) {
    $text = str_replace("\r", '', $text);
    $paragraphs = explode("\n", $text);
    $lines = [];

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);

        if ($paragraph === '') {
            $lines[] = '';
            continue;
        }

        $words = preg_split('/\s+/', $paragraph);
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if ($pdf->GetStringWidth(pdfTxt($test)) <= ($width - 2)) {
                $current = $test;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }
    }

    return $lines ?: [''];
};

$renderConceptHeader($pdf, $col);

foreach ($conceptos as $c) {
    $detalle = [];
    $detalle[] = pdfTxt($c['Descripcion'] ?? '');
    $detalle[] = pdfTxt('Clave Prod. Serv.: ' . (($c['ClaveProdServ'] ?? '') ?: 'N/D'));
    $detalle[] = pdfTxt('No. identificación: ' . (($c['NoIdentificacion'] ?? '') ?: 'N/D'));

    if (!empty($c['traslados'])) {
        foreach ($c['traslados'] as $t) {
            $detalle[] = pdfTxt(sprintf(
                'Impuestos trasladados: %s %s Tasa %s Importe %s',
                ($t['Impuesto'] ?? '') ?: 'N/D',
                ($t['TipoFactor'] ?? '') ?: 'N/D',
                ($t['TasaOCuota'] ?? '') ?: 'N/D',
                mxn($t['Importe'] ?? 0)
            ));
        }
    }

    $descText = implode("\n", $detalle);
    $descLines = $getWrappedLines($pdf, $descText, $col['desc']);
    [$objClave, $objDescripcion] = satSplitLabel((string)($c['ObjetoImp'] ?? ''), (string)($c['ObjetoImpLabel'] ?? ''));
    $objText = $objClave !== '' ? $objClave : 'N/D';
    if ($objDescripcion !== '') {
        $objText .= "\n" . $objDescripcion;
    }
    $objLines = $getWrappedLines($pdf, $objText, $col['obj']);
    $lineHeight = 3.4;
    $h = max(9, count($descLines) * $lineHeight + 2, count($objLines) * $lineHeight + 2);

    if ($pdf->GetY() + $h > 220) {
        $pdf->AddPage();
        $pdf->sectionTitle('CONCEPTOS');
        $renderConceptHeader($pdf, $col);
    }

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    // 1. Dibujar primero la retícula completa de la fila
    $pdf->Rect($x, $y, $col['cant'], $h);
    $pdf->Rect($x + $col['cant'], $y, $col['unidad'], $h);
    $pdf->Rect($x + $col['cant'] + $col['unidad'], $y, $col['id'], $h);
    $pdf->Rect($x + $col['cant'] + $col['unidad'] + $col['id'], $y, $col['desc'], $h);
    $pdf->Rect($x + $col['cant'] + $col['unidad'] + $col['id'] + $col['desc'], $y, $col['pu'], $h);
    $pdf->Rect($x + $col['cant'] + $col['unidad'] + $col['id'] + $col['desc'] + $col['pu'], $y, $col['obj'], $h);
    $pdf->Rect($x + $col['cant'] + $col['unidad'] + $col['id'] + $col['desc'] + $col['pu'] + $col['obj'], $y, $col['imp'], $h);

    // 2. Escribir contenido centrado/alineado dentro de la misma fila uniforme
    $pdf->SetXY($x, $y);
    $pdf->Cell($col['cant'], $h, pdfTxt($c['Cantidad'] ?? ''), 0, 0, 'C');

    $pdf->SetXY($x + $col['cant'], $y);
    $pdf->Cell($col['unidad'], $h, pdfTxt($c['ClaveUnidad'] ?? ''), 0, 0, 'C');

    $pdf->SetXY($x + $col['cant'] + $col['unidad'], $y);
    $pdf->Cell($col['id'], $h, pdfTxt($c['NoIdentificacion'] ?? ''), 0, 0, 'C');

    $pdf->SetXY($x + $col['cant'] + $col['unidad'] + $col['id'], $y + 1);
    $pdf->MultiCell($col['desc'], $lineHeight, $descText, 0, 'L');

    $pdf->SetXY($x + $col['cant'] + $col['unidad'] + $col['id'] + $col['desc'], $y);
    $pdf->Cell($col['pu'], $h, mxn($c['ValorUnitario'] ?? 0), 0, 0, 'R');

    $pdf->SetXY($x + $col['cant'] + $col['unidad'] + $col['id'] + $col['desc'] + $col['pu'], $y + 1);
    $pdf->SetFont('Arial', '', 6.1);
    $pdf->MultiCell($col['obj'], $lineHeight, pdfTxt($objText), 0, 'C');
    $pdf->SetFont('Arial', '', 7.2);

    $pdf->SetXY($x + $col['cant'] + $col['unidad'] + $col['id'] + $col['desc'] + $col['pu'] + $col['obj'], $y);
    $pdf->Cell($col['imp'], $h, mxn($c['Importe'] ?? 0), 0, 0, 'R');

    // 3. Avanzar al final de la fila
    $pdf->SetY($y + $h);
}

$pdf->Ln(2);

// Bloque importes (izquierda + derecha)
$xLeft = 10;
$yImp = $pdf->GetY();

$pdf->SetFont('Arial', 'B', 9.2);
$pdf->SetXY($xLeft, $yImp);
$pdf->Cell(118, 5, pdfTxt('IMPORTE CON LETRA'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8.2);
$pdf->SetX($xLeft);
$pdf->MultiCell(118, 4.1, pdfTxt($importeLetra), 0, 'L');

$metaLeft = [
    'Tipo de comprobante' => $comprobanteLabels['TipoDeComprobante'],
    'Forma de pago' => $comprobanteLabels['FormaPago'],
    'Método de pago' => $comprobanteLabels['MetodoPago'],
    'Moneda' => $comprobanteLabels['Moneda'],
    'Versión' => $comprobante['Version'],
    'Exportación' => $comprobanteLabels['Exportacion'],
];
foreach ($metaLeft as $k => $v) {
    $pdf->SetX($xLeft);
    $pdf->SetFont('Arial', 'B', 8.2);
    $pdf->Cell(40, 4.4, pdfTxt($k), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 8.2);
    $pdf->Cell(78, 4.4, pdfTxt($v ?: 'N/D'), 0, 1, 'L');
}

$xRight = 137;
$yRight = $yImp;
$pdf->SetXY($xRight, $yRight);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(36, 5.5, 'SUBTOTAL', 0, 0, 'R');
$pdf->Cell(35, 5.5, mxn($comprobante['SubTotal']), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 9);
if (!empty($impuestos['traslados'])) {
    foreach ($impuestos['traslados'] as $t) {
        $etq = sprintf('TRASLADO IVA TASA %s', $t['TasaOCuota'] ?: 'N/D');
        $pdf->SetX($xRight);
        $pdf->Cell(36, 4.8, pdfTxt($etq), 0, 0, 'R');
        $pdf->Cell(35, 4.8, mxn($t['Importe']), 0, 1, 'R');
    }
} else {
    $pdf->SetX($xRight);
    $pdf->Cell(36, 4.8, 'IMPUESTOS', 0, 0, 'R');
    $pdf->Cell(35, 4.8, mxn($impuestos['TotalImpuestosTrasladados']), 0, 1, 'R');
}
$pdf->SetX($xRight);
$pdf->SetFont('Arial', 'B', 10.5);
$pdf->Cell(36, 6, 'TOTAL', 0, 0, 'R');
$pdf->Cell(35, 6, mxn($comprobante['Total']), 0, 1, 'R');

$pdf->SetY(max($pdf->GetY(), $yImp + 38));

// QR y sellos
$qrUrl = buildCfdiQrUrl($tfd, $comprobante, $emisor, $receptor);
$qrTemp = downloadQrTemp($qrUrl);
$yQr = $pdf->GetY() + 1.5;
if ($qrTemp && is_file($qrTemp)) {
    $pdf->Image($qrTemp, 10, $yQr, 34, 34);
} else {
    $pdf->Rect(10, $yQr, 34, 34);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(11, $yQr + 15);
    $pdf->Cell(32, 4, 'QR CFDI', 0, 0, 'C');
}

$pdf->SetXY(48, $yQr);
$pdf->sectionTitle('SELLOS Y CADENA ORIGINAL');
$pdf->SetFont('Arial', 'B', 8.3);
$pdf->SetX(48);
$pdf->Cell(0, 4.5, 'Sello digital del CFDI', 0, 1, 'L');
$pdf->SetFont('Arial', '', 6.5);
$pdf->SetX(48);
$pdf->MultiCell(154, 3.2, pdfTxt($tfd['SelloCFD']), 0, 'L');

$pdf->SetFont('Arial', 'B', 8.3);
$pdf->SetX(48);
$pdf->Cell(0, 4.5, 'Sello digital del SAT', 0, 1, 'L');
$pdf->SetFont('Arial', '', 6.5);
$pdf->SetX(48);
$pdf->MultiCell(154, 3.2, pdfTxt($tfd['SelloSAT']), 0, 'L');

$pdf->SetFont('Arial', 'B', 8.3);
$pdf->SetX(48);
$pdf->Cell(0, 4.5, 'Cadena original del complemento de certificación digital del SAT', 0, 1, 'L');
$pdf->SetFont('Arial', '', 6.5);
$pdf->SetX(48);
$pdf->MultiCell(154, 3.2, pdfTxt($cadenaOriginal), 0, 'L');

$filename = 'cfdi_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $uuid) . '.pdf';
$pdf->Output('I', $filename);

if ($qrTemp && is_file($qrTemp)) {
    @unlink($qrTemp);
}
