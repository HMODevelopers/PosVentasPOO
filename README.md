# PosVentasPOO

**Sistema de punto de venta en PHP 8 con estructura MVC y POO, utilizando MySQL, jQuery y AJAX. Desarrollado en PHP puro, ideal para servidores con cPanel. Incluye gestión de usuarios, productos, clientes, ventas, abonos, reportes y control de caja. Ligero, modular y listo para producción.**

## 📂 Estructura del proyecto


```text
PosVentasPOO/
│
├── Controllers/             # Controladores (lógica del sistema)
│   ├── ClienteController.php
│   ├── ProductoController.php
│   ├── VentaController.php
│   └── ...
│
├── Models/                  # Modelos conectados a la base de datos
│   ├── Cliente.php
│   ├── Producto.php
│   ├── Venta.php
│   └── ...
│
├── Views/                   # Vistas HTML divididas por sección
│   ├── Public/              # Acceso público (login)
│   └── Private/             # Panel principal del sistema
│
├── Includes/                # Configuraciones y utilidades
│   ├── db.php               # Conexión a la base de datos
│   ├── session.php          # Manejo de sesión
│   └── funciones.php
│
├── Assets/                  # Recursos estáticos
│   ├── css/
│   ├── js/
│   └── img/
│
├── index.php                # Archivo de entrada principal
├── .htaccess                # Configuración para URLs amigables
└── README.md



## ⚙️ Requisitos

- PHP **8.0** o superior  
- MySQL **5.7** o superior  
- Servidor Apache con **mod_rewrite** activado  
- Compatible con **cPanel**, **XAMPP**, **LAMP**, **WAMP**

## 🚀 Instalación

1. **Sube o clona el proyecto en tu servidor:**

   ```bash
   git clone https://github.com/tuusuario/PosVentasPOO.git
Importa la base de datos:

Crea una base de datos en tu servidor (por ejemplo: pos_db)

Importa el archivo db/pos.sql usando phpMyAdmin u otra herramienta

Edita la conexión a la base de datos:

En Includes/db.php, coloca tus datos reales:

php
Copiar
Editar
define('DB_HOST', 'localhost');
define('DB_USER', 'TU_USUARIO');
define('DB_PASS', 'TU_CONTRASEÑA');
define('DB_NAME', 'pos_db');
Activa URLs amigables:

Asegúrate de que .htaccess esté activo y que tu servidor tenga habilitado mod_rewrite.

🔐 Funcionalidades
Inicio de sesión y control de acceso

Registro y gestión de productos

Control de clientes

Ventas con detalle de ticket

Registro de abonos/pagos

Reportes de ventas

Control de caja

🔧 Tecnologías utilizadas
PHP 8 (POO sin frameworks)

MySQL

AJAX + jQuery

HTML5 + CSS3

Bootstrap (opcional)

Patrón MVC personalizado

📄 Licencia
Este sistema es de uso libre para fines educativos y comerciales.
Desarrollado por Carlos Lafarga.
