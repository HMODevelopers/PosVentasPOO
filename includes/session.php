<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: /views/Public/index.php');
    exit();
}
