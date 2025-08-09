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
<html lang="en">
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
    </head>

    <body>

        <!-- Navigation Bar-->


            <?php include_once __DIR__ . '/../../../includes/header.php'; ?>


        <!-- End Navigation Bar-->

        <!-- ============================================================== -->
        <!-- ================== Start Page Content here =================== -->
        <!-- ============================================================== -->

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

                    <!-- start page title -->
                    <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>    
                    <!-- end page title --> 

                   

                
                </div> <!-- end container -->
        </div>
        <!-- end wrapper -->

        <!-- ============================================================== -->
        <!-- ===================== End Page content ======================= -->
        <!-- ============================================================== -->

        <!-- Footer Start -->
        <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
        <!-- End Footer -->

        

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- Vendor js -->
        <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
        <!-- App js-->
        <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>