<?php

require_once __DIR__ . '/../services/ProductCodeMatcher.php';

$matcher = new ProductCodeMatcher();
$pruebas = 0;

function producto(int $id, string $codigo): array
{
    return ['id_producto' => $id, 'codigo' => $codigo, 'descripcion' => "Producto {$codigo}"];
}

function verificar($condicion, string $mensaje): void
{
    global $pruebas;
    $pruebas++;
    if (!$condicion) throw new RuntimeException($mensaje);
}

// 1. La coincidencia exacta siempre tiene prioridad.
$r = $matcher->resolver('ABC-123', [producto(1, 'ABC-123')], [], [producto(2, 'ABC-1234')], [], 20);
verificar($r['match']['tipo'] === 'exacta', 'No clasificó la coincidencia exacta.');
verificar($r['match']['auto_resolver'] === true && $r['match']['id_producto'] === 1, 'No resolvió el exacto único.');

// 2. Sustituciones conocidas del lector se pueden resolver si son únicas.
$r = $matcher->resolver("PCM'M69'8313", [], [producto(3, 'PCM-M69-8313')], [], [], 20);
verificar($r['match']['tipo'] === 'equivalencia_lector', 'No reconoció la sustitución apóstrofe/guion.');
verificar($r['match']['consulta_normalizada'] === 'PCM-M69-8313', 'Normalización incorrecta.');
verificar($r['match']['auto_resolver'] === true, 'La equivalencia única debería ser resoluble.');
verificar(
    ProductCodeMatcher::normalizarSustitucionesConocidas('A“B”C´D`E') === 'A-B-C-D-E',
    'No normalizó todas las variantes conocidas de comillas.'
);

// 3. Las aproximaciones se ordenan por distancia, longitud y posición.
$r = $matcher->resolver('PCM-M69-8314', [], [], [], [
    producto(4, 'PCM-M69-8313'),
    producto(5, 'PCM-M60-8300'),
], 20);
verificar($r['data'][0]['id_producto'] === 4, 'El candidato más cercano no quedó primero.');
verificar($r['match']['requiere_confirmacion'] === true, 'Una aproximación nunca debe autoagregarse.');

// 4. Una diferencia fuera del umbral no genera resultados.
$r = $matcher->resolver('ABC-123', [], [], [], [producto(6, 'XYZ-999')], 20);
verificar($r['match']['tipo'] === 'sin_coincidencias' && $r['data'] === [], 'Aceptó un código sin similitud suficiente.');

// 5. Dos códigos igual de cercanos se consideran ambiguos.
$r = $matcher->resolver('ABC-125', [], [], [], [
    producto(7, 'ABC-123'),
    producto(8, 'ABC-124'),
], 20);
verificar($r['match']['tipo'] === 'ambigua', 'No detectó candidatos aproximados ambiguos.');
verificar($r['match']['auto_resolver'] === false, 'Una ambigüedad no debe resolverse automáticamente.');

// 6. Una equivalencia conocida duplicada tampoco se auto-resuelve.
$r = $matcher->resolver("ABC'123", [], [producto(9, 'ABC-123'), producto(10, 'ABC-123')], [], [], 20);
verificar($r['match']['tipo'] === 'ambigua' && $r['match']['requiere_confirmacion'] === true, 'No protegió una equivalencia conocida duplicada.');

// 7. La búsqueda regular por descripción conserva el comportamiento manual.
$r = $matcher->resolver('aceite', [], [], [producto(11, 'ACE-20W50')], [], 20);
verificar($r['match']['tipo'] === 'regular', 'Alteró la clasificación de una búsqueda manual.');
verificar($r['match']['requiere_confirmacion'] === false, 'Una búsqueda manual normal no debe marcarse como ambigua.');

echo "OK: {$pruebas} verificaciones\n";
