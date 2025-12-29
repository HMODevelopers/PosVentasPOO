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


