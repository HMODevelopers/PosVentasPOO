<?php

/**
 * Clasifica resultados de búsqueda de productos sin modificar los códigos
 * almacenados. La obtención de candidatos se mantiene en ProductoModel.
 */
final class ProductCodeMatcher
{
    private const SUSTITUCIONES_LECTOR = [
        "'" => '-',
        '"' => '-',
        '‘' => '-',
        '’' => '-',
        '“' => '-',
        '”' => '-',
        '`' => '-',
        '´' => '-',
    ];

    public static function normalizarSustitucionesConocidas(string $codigo): string
    {
        return strtr($codigo, self::SUSTITUCIONES_LECTOR);
    }

    public static function distanciaMaxima(string $codigo): int
    {
        $longitud = strlen(self::comparable(self::normalizarSustitucionesConocidas($codigo)));

        if ($longitud <= 8) return 1;
        if ($longitud <= 14) return 2;
        return 3;
    }

    /**
     * Resuelve las etapas ya consultadas por el modelo y conserva un contrato
     * uniforme para el controlador.
     */
    public function resolver(
        string $consulta,
        array $exactos,
        array $equivalentes,
        array $regulares,
        array $candidatos,
        int $limite
    ): array {
        $consulta = trim($consulta);
        $normalizada = self::normalizarSustitucionesConocidas($consulta);

        if ($exactos) {
            $unico = count($exactos) === 1;
            return $this->respuesta(
                $this->combinar($exactos, $regulares, $limite),
                $unico ? 'exacta' : 'ambigua',
                $unico,
                !$unico,
                $consulta,
                $normalizada,
                $unico ? (int)$exactos[0]['id_producto'] : null
            );
        }

        if ($normalizada !== $consulta && $equivalentes) {
            $unico = count($equivalentes) === 1;
            return $this->respuesta(
                $this->combinar($equivalentes, $regulares, $limite),
                $unico ? 'equivalencia_lector' : 'ambigua',
                $unico,
                !$unico,
                $consulta,
                $normalizada,
                $unico ? (int)$equivalentes[0]['id_producto'] : null
            );
        }

        if ($regulares) {
            return $this->respuesta(
                array_slice($regulares, 0, $limite),
                'regular',
                false,
                false,
                $consulta,
                $normalizada
            );
        }

        return $this->ordenarAproximados($consulta, $candidatos, $limite);
    }

    private function ordenarAproximados(string $consulta, array $candidatos, int $limite): array
    {
        $normalizada = self::normalizarSustitucionesConocidas($consulta);
        $buscado = self::comparable($normalizada);
        $maxDistancia = self::distanciaMaxima($consulta);
        $evaluados = [];

        foreach ($candidatos as $producto) {
            $codigo = trim((string)($producto['codigo'] ?? ''));
            if ($codigo === '') continue;

            $comparable = self::comparable($codigo);
            $diferenciaLongitud = abs(strlen($buscado) - strlen($comparable));
            if ($diferenciaLongitud > $maxDistancia) continue;

            $distancia = levenshtein($buscado, $comparable);
            if ($distancia > $maxDistancia) continue;

            $longitud = max(strlen($buscado), strlen($comparable), 1);
            $similitudLevenshtein = 1 - ($distancia / $longitud);
            $coincidenciasPosicionales = 0;
            $longitudComun = min(strlen($buscado), strlen($comparable));
            for ($i = 0; $i < $longitudComun; $i++) {
                if ($buscado[$i] === $comparable[$i]) $coincidenciasPosicionales++;
            }
            $similitudPosicional = $coincidenciasPosicionales / $longitud;
            $prefijo = $this->longitudPrefijoComun($buscado, $comparable) / $longitud;
            $puntaje = round(100 * (
                (0.70 * $similitudLevenshtein)
                + (0.20 * $similitudPosicional)
                + (0.10 * $prefijo)
            ), 2);

            $producto['coincidencia_tipo'] = 'aproximada';
            $producto['coincidencia_distancia'] = $distancia;
            $producto['coincidencia_similitud'] = $puntaje;
            $producto['_diferencia_longitud'] = $diferenciaLongitud;
            $evaluados[] = $producto;
        }

        usort($evaluados, static function (array $a, array $b): int {
            return ($a['coincidencia_distancia'] <=> $b['coincidencia_distancia'])
                ?: ($a['_diferencia_longitud'] <=> $b['_diferencia_longitud'])
                ?: ($b['coincidencia_similitud'] <=> $a['coincidencia_similitud'])
                ?: strcmp((string)$a['codigo'], (string)$b['codigo']);
        });

        $evaluados = array_slice($evaluados, 0, $limite);
        foreach ($evaluados as &$producto) unset($producto['_diferencia_longitud']);
        unset($producto);

        if (!$evaluados) {
            return $this->respuesta([], 'sin_coincidencias', false, false, $consulta, $normalizada);
        }

        $ambigua = false;
        if (count($evaluados) > 1) {
            $primero = $evaluados[0];
            $segundo = $evaluados[1];
            $ambigua = $primero['coincidencia_distancia'] === $segundo['coincidencia_distancia']
                && abs($primero['coincidencia_similitud'] - $segundo['coincidencia_similitud']) < 5;
        }

        return $this->respuesta(
            $evaluados,
            $ambigua ? 'ambigua' : 'aproximada',
            false,
            true,
            $consulta,
            $normalizada
        );
    }

    private function respuesta(
        array $data,
        string $tipo,
        bool $autoResolver,
        bool $requiereConfirmacion,
        string $consulta,
        string $normalizada,
        ?int $idProducto = null
    ): array {
        return [
            'data' => $data,
            'match' => [
                'tipo' => $tipo,
                'auto_resolver' => $autoResolver,
                'requiere_confirmacion' => $requiereConfirmacion,
                'id_producto' => $idProducto,
                'consulta_original' => $consulta,
                'consulta_normalizada' => $normalizada,
            ],
        ];
    }

    private function combinar(array $prioritarios, array $otros, int $limite): array
    {
        $resultado = [];
        $vistos = [];
        foreach (array_merge($prioritarios, $otros) as $producto) {
            $id = (int)($producto['id_producto'] ?? 0);
            if ($id > 0 && isset($vistos[$id])) continue;
            if ($id > 0) $vistos[$id] = true;
            $resultado[] = $producto;
            if (count($resultado) >= $limite) break;
        }
        return $resultado;
    }

    private function longitudPrefijoComun(string $a, string $b): int
    {
        $max = min(strlen($a), strlen($b));
        for ($i = 0; $i < $max; $i++) {
            if ($a[$i] !== $b[$i]) return $i;
        }
        return $max;
    }

    private static function comparable(string $codigo): string
    {
        $codigo = trim($codigo);
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($codigo, 'UTF-8')
            : strtoupper($codigo);
    }
}
