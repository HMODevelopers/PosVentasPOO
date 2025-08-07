<?php
$titulo = "Inicio";
$modulo = "Panel Principal";
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
        <title>Inicio | REFASOFT-V4</title>
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

    </head>

    <body>

        <!-- Navigation Bar-->


            <?php include_once __DIR__ . '/../../../includes/header.php'; ?>


        <!-- End Navigation Bar-->

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->

        <div class="wrapper">
            <div class="container-fluid">

                <!-- start page title -->
                <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>    
                    <!-- end page title --> 

                    <div class="row">
                        <div class="col-xl-3">
                            <div class="card-box">
                                <i class="fa fa-info-circle text-muted float-right" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="More Info"></i>
                                <h4 class="mt-0 font-16">Wallet Balance</h4>
                                <h2 class="text-primary my-4 text-center">$<span data-plugin="counterup">31,570</span></h2>
                                <div class="row mb-4">
                                    <div class="col-6">
                                        <p class="text-muted mb-1">This Month</p>
                                        <h3 class="mt-0 font-20 text-truncate">$120,254 <small class="badge badge-light-success font-13">+15%</small></h3>
                                    </div>

                                    <div class="col-6">
                                        <p class="text-muted mb-1">Last Month</p>
                                        <h3 class="mt-0 font-20 text-truncate">$98,741 <small class="badge badge-light-danger font-13">-5%</small></h3>
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <span data-plugin="peity-line" data-fill="#56c2d6" data-stroke="#4297a6" data-width="100%" data-height="50">3,5,2,9,7,2,5,3,9,6,5,9,7</span>
                                </div>

                            </div> <!-- end card-box-->
                        </div>
                        
                        <div class="col-xl-6">
                            <div class="card-box">
                                <div class="float-right d-none d-md-inline-block">
                                    <div class="btn-group mb-2">
                                        <button type="button" class="btn btn-xs btn-secondary">Today</button>
                                        <button type="button" class="btn btn-xs btn-light">Weekly</button>
                                        <button type="button" class="btn btn-xs btn-light">Monthly</button>
                                    </div>
                                </div>
                                <h4 class="header-title mb-1">Transaction History</h4>
                                <div id="rotate-labels-column" class="apex-charts"></div>
                            </div> <!-- end card-box-->
                        </div> <!-- end col -->

                        <div class="col-xl-3">
                            <div class="card-box">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="avatar-sm bg-light rounded">
                                            <i class="fe-shopping-cart avatar-title font-22 text-success"></i>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-right">
                                            <h3 class="text-dark my-1"><span data-plugin="counterup">1576</span></h3>
                                            <p class="text-muted mb-1 text-truncate">January's Sales</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h6 class="text-uppercase">Target <span class="float-right">49%</span></h6>
                                    <div class="progress progress-sm m-0">
                                        <div class="progress-bar bg-success" role="progressbar" aria-valuenow="49" aria-valuemin="0" aria-valuemax="100" style="width: 49%">
                                            <span class="sr-only">49% Complete</span>
                                        </div>
                                    </div>
                                </div>
                            </div> <!-- end card-box-->

                            <div class="card-box">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="avatar-sm bg-light rounded">
                                            <i class="fe-aperture avatar-title font-22 text-purple"></i>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-right">
                                            <h3 class="text-dark my-1">$<span data-plugin="counterup">12,145</span></h3>
                                            <p class="text-muted mb-1 text-truncate">Income status</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h6 class="text-uppercase">Target <span class="float-right">60%</span></h6>
                                    <div class="progress progress-sm m-0">
                                        <div class="progress-bar bg-purple" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 60%">
                                            <span class="sr-only">60% Complete</span>
                                        </div>
                                    </div>
                                </div>
                            </div> <!-- end card-box-->
                        </div>
                    </div>
                    <!-- end row -->


                
            </div> <!-- end container -->
        </div>
        <!-- end wrapper -->

        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

        <!-- Footer Start -->
        <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
        <!-- end Footer -->

        

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- Vendor js -->
        <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>

        <!-- Third Party js-->
        <script src="<?= BASE_URL ?>/assets/libs/peity/jquery.peity.min.js"></script>
        <script src="<?= BASE_URL ?>/assets/libs/apexcharts/apexcharts.min.js"></script>
        <script src="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.min.js"></script>
        <script src="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-us-merc-en.js"></script>

        <!-- Dashboard init -->
        <script src="<?= BASE_URL ?>/assets/js/pages/dashboard-1.init.js"></script>

        <!-- App js-->
        <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
        
    </body>
</html>