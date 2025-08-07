<?php
// Valores por defecto si no se definen antes de incluir este archivo
$modulo = $modulo ?? 'Inicio';
$titulo = $titulo ?? 'Dashboard';
$subtitulo = $subtitulo ?? '';
?>

<div class="col-12">
    <div class="page-title-box">
        <div class="page-title-right">
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Refasoft</a></li>
                <li class="breadcrumb-item"><?= htmlspecialchars($modulo) ?></li>
                <?php if ($subtitulo): ?>
                    <li class="breadcrumb-item"><?= htmlspecialchars($subtitulo) ?></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($titulo) ?></li>
            </ol>
        </div>
        <h4 class="page-title"><?= htmlspecialchars($titulo) ?></h4>
    </div>
</div>
