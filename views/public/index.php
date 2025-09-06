<?php 
session_start(); 
include_once '../../includes/config.php';

// Si ya hay sesión activa, redirige al panel
if (isset($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/views/private/inicio/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Iniciar Sesión | Sistema REFASOFTV4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

    <!-- Estilos del template -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
</head>

<body class="authentication-bg authentication-bg-pattern">
<div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
    <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
    </div>
</div>

    <div class="account-pages mt-5 mb-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card">

                        <div class="card-body p-4">

                            <div class="text-center w-75 m-auto">
                                <a href="#">
                                    <img src="<?= BASE_URL ?>/assets/images/rr1_black.png" alt="Logo" height="100">
                                </a>
                                <p class="text-muted mb-4 mt-3">Introduce tu usuario y contraseña para acceder.</p>
                            </div>

                            <h5 class="auth-title">Iniciar sesión</h5>

                            <!-- Mensaje de error dinámico -->
                            <div id="mensaje-error" class="alert alert-danger text-center" style="display: none;"></div>

                            <!-- Formulario AJAX -->
                            <form id="formLogin">

                                <div class="form-group mb-3">
                                    <label for="usuario">Usuario</label>
                                    <input type="text" name="usuario" id="usuario" class="form-control" required placeholder="Ingresa tu usuario">
                                </div>

                                <div class="form-group mb-3">
                                    <label for="contrasena">Contraseña</label>
                                    <input type="password" name="contrasena" id="contrasena" class="form-control" required placeholder="Ingresa tu contraseña">
                                </div>

                                <div class="form-group mb-3">
                                    <div class="custom-control custom-checkbox checkbox-info">
                                        <input type="checkbox" class="custom-control-input" id="checkbox-signin">
                                        <label class="custom-control-label" for="checkbox-signin">Recordarme</label>
                                    </div>
                                </div>

                                <div class="form-group mb-0 text-center">
                                    <button class="btn btn-danger btn-block" type="submit"> Iniciar sesión </button>
                                </div>

                            </form>

                            <div class="text-center mt-3">
                                <small class="text-muted">¿Olvidaste tu contraseña?</small>
                            </div>

                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <p class="text-muted">¿No tienes una cuenta? <a href="#" class="text-muted ml-1"><b>Regístrate</b></a></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <footer class="footer footer-alt">
        2025 &copy; REFASOFTV4
    </footer>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/pages/login.js"></script>


   

</body>
</html>
