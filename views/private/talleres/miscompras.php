<?php
$titulo = "Inventarios";
$modulo = "Productos";
$subtitulo = ""; // puedes dejarlo vacío si no se necesita
session_start();

// Incluye la configuración con BASE_URL
require_once __DIR__ . '/../../../includes/config.php';

// Verifica si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/views/public/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Productos | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
    <meta content="Coderthemes" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

    <!-- plugin css -->
    <link href="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  </head>

  <body>
    <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

    <div class="wrapper">
      <!-- Loader -->
      <div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
        <div class="loader">
          <div class="loader__figure"></div>
          <p class="loader__label">Cargando...</p>
        </div>
      </div>
      <!-- Fin Loader -->

      <div class="container-fluid">
       <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>
        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
                        <h5>Filtros</h5>

                        <div class="row">
                            <div class="col-lg-12">
                                <div class="row">

                                    <!-- Filtro por Código -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="Codigo" class="control-label">Código</label>
                                            <div class="input-group">
                                                <input type="text" id="Codigo" name="Codigo" class="form-control filtrar">
                                                <div class="input-group-append clean-filter">
                                                    <span class="input-group-text">
                                                        <i class="mdi mdi-close-circle text-danger" onclick="clearField('Codigo')"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Filtro por Descripción -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="Descripcion" class="control-label">Descripción</label>
                                            <div class="input-group">
                                                <input type="text" id="Descripcion" name="Descripcion" class="form-control filtrar">
                                                <div class="input-group-append clean-filter">
                                                    <span class="input-group-text">
                                                        <i class="mdi mdi-close-circle text-danger" onclick="clearField('Descripcion')"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Filtro por Fecha -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="FechaVenta" class="control-label">Fecha de Venta</label>
                                            <div class="input-group">
                                                <input type="date" id="FechaVenta" name="FechaVenta" class="form-control filtrar" value="<?php echo date('Y-m-d'); ?>">
                                                <div class="input-group-append clean-filter">
                                                    <span class="input-group-text">
                                                        <i class="mdi mdi-close-circle text-danger" onclick="clearField('FechaVenta')"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    
                                    
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card-box">
                                
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="ventas-table">
                                        <thead>
                                            <tr>
                                                <th>No Tiket</th>
                                                <th>Código</th>
                                                <th>Descripción</th>
                                                <th>Cantidad</th>
                                                <th>Precio</th>
                                                <th>Total</th>
                                                <th>Fecha Venta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Los datos se llenarán aquí con jQuery -->
                                        </tbody>
                                    </table>
                                </div> <!-- end .table-responsive -->
                                
                                <div class="row">
                                    <div class="col col-md-4">
                                          <!-- Total de ventas -->    
                                        <h4>Total compras del día:</h4> <h5><span id="total-venta"><strong> $ 0.00</strong></span></h5>
                                    </div>
                                    <div class="col col-md-8">
                                        <nav aria-label="Page navigation example">
                                            <ul id="pagination" class="pagination justify-content-end"></ul>
                                        </nav>
                                    </div>
                                </div>

                            </div> <!-- end card-box -->
                        </div> <!-- end col -->
                    </div>
       
        </div><!-- container-fluid -->
    </div><!-- wrapper -->

    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

    <div class="rightbar-overlay"></div>

    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
   
    </script>

  </body>
</html>
