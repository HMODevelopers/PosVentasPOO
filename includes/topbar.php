 <div class="navbar-custom">
                <div class="container-fluid">
                    <ul class="list-unstyled topnav-menu float-right mb-0">

                        <li class="dropdown notification-list">
                            <!-- Mobile menu toggle-->
                            <a class="navbar-toggle nav-link">
                                <div class="lines">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </a>
                            <!-- End mobile menu toggle-->
                        </li>


                        <li class="d-none d-sm-block d-flex align-items-center" style="margin-top:18px; margin-right:15px;">
                            <a href="<?= BASE_URL ?>/views/private/caja/index.php"
                                id="btnTopbarPOS"
                                class="btn btn-success waves-effect my-0 py-1"
                                style="display:flex; align-items:center; gap:6px;"
                                title="Ir al Punto de Venta">
                                <i class="mdi mdi-desktop-classic"></i> POS
                            </a>
                        </li>


                        <!--NOTIFICACIONES-->
                        

                        <!--USUARIO-->
                        <li class="dropdown notification-list">
                            <a class="nav-link dropdown-toggle nav-user mr-0 waves-effect" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                <img src="<?= BASE_URL ?>/assets/images/users/user-1.jpg" alt="user-image" class="rounded-circle">
                                <span class="pro-user-name ml-1">
                                    <?= htmlspecialchars($_SESSION['usuario']['nombre'] ?? 'Invitado') ?> <i class="mdi mdi-chevron-down"></i> 
                                </span>

                            </a>
                            <div class="dropdown-menu dropdown-menu-right profile-dropdown ">
                                <!-- item-->
                                <div class="dropdown-item noti-title">
                                    <h5 class="m-0">
                                        Bienvenido
                                    </h5>
                                </div>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item notify-item">
                                    <i class="fe-user"></i>
                                    <span>Mi Cuenta</span>
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item notify-item">
                                    <i class="fe-settings"></i>
                                    <span>Configuración</span>
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item notify-item">
                                    <i class="fe-lock"></i>
                                    <span>Bloquear Pantalla</span>
                                </a>

                                <div class="dropdown-divider"></div>

                                <!-- item-->
                               <a href="<?= BASE_URL ?>/controllers/LogoutController.php" class="dropdown-item notify-item">
                                 <i class="fe-log-out"></i>
                                <span>Cerrar Sesión</span>
                                </a>

                            </div>
                        </li>

                      

                    </ul>

                    <!-- LOGO -->
                    <div class="logo-box">
                        <a href="" class="logo text-center">
                            <span class="logo-lg">
                                <img src="<?= BASE_URL ?>/assets/images/logo-dark.png" alt="" height="16">
                                <!-- <span class="logo-lg-text-dark">Xeria</span> -->
                            </span>
                            <span class="logo-sm">
                                <!-- <span class="logo-sm-text-dark">X</span> -->
                                <img src="<?= BASE_URL ?>/assets/images/logo-sm.png" alt="" height="18">
                            </span>
                        </a>
                    </div>

                    <ul class="list-unstyled topnav-menu topnav-menu-left m-0">
            
                        <li class="dropdown d-none d-lg-block">
                            <a class="nav-link dropdown-toggle waves-effect" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                Reports
                                <i class="mdi mdi-chevron-down"></i> 
                            </a>
                            <div class="dropdown-menu">
                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item">
                                    Finance Report
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item">
                                    Monthly Report
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item">
                                    Revenue Report
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item">
                                    Settings
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item">
                                    Help & Support
                                </a>

                            </div>
                        </li>

                        
                    </ul>

                    <div class="clearfix"></div>
                </div>
            </div>