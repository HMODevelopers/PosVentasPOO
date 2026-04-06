<?php
$titulo = "Facturación múltiple";
$modulo = "Facturar varios tickets";
$subtitulo = "";
session_start();
$SESSION_LIFETIME = 10 * 60 * 60;
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../includes/config.php';
if (!isset($_SESSION['usuario'])) {
  header('Location: ' . BASE_URL . '/views/public/index.php');
  exit();
}
$sessionStart = $_SESSION['SESSION_START'] ?? 0;
$sessionTTL   = $_SESSION['SESSION_TTL']   ?? $SESSION_LIFETIME;
if ($sessionStart === 0 || (time() - $sessionStart) > $sessionTTL) {
  session_unset(); session_destroy();
  header('Location: ' . BASE_URL . '/views/public/index.php?expired=1');
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Facturación múltiple | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/libs/select2/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
</head>
<body>
<?php include_once __DIR__ . '/../../../includes/header.php'; ?>
<div class="wrapper">
  <div class="container-fluid">
    <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>
    <?php include_once __DIR__ . '/partials/facturacion_multiple_form.php'; ?>
  </div>
</div>

<div class="modal fade" id="modalTicketsFacturacion" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Agregar tickets</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group mb-2">
          <input type="text" id="multi-ticket-search" class="form-control" placeholder="Buscar por folio, id o cliente">
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>Folio</th><th>Fecha</th><th>Cliente</th><th class="text-right">Total</th><th></th></tr></thead>
            <tbody id="multi-ticket-body"><tr><td colspan="5" class="text-center text-muted">Sin tickets</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
<div class="rightbar-overlay"></div>
<script>const BASE_URL='<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
<script src="<?= BASE_URL ?>/assets/libs/select2/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/pages/facturacion_multiple.js"></script>
</body>
</html>
