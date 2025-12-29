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
   cd PosVentasPOO
   ```

2. **Configura las variables de entorno:**
   - Copia el archivo de ejemplo y completa tus credenciales reales:
     ```bash
     cp .env.example .env
     ```
   - Edita `.env` con los datos de conexión (host, puerto, usuario, contraseña, base de datos). El archivo real `.env` está en `.gitignore`, por lo que puedes versionar el proyecto con seguridad.

3. **Importa la base de datos:**
   - Crea una base de datos en tu servidor (por ejemplo: `pos_db`).
   - Importa el archivo `db/pos.sql` usando phpMyAdmin, MySQL Workbench o la CLI.

4. **Configura el servidor web:**
   - Asegúrate de que `.htaccess` esté activo y que tu servidor tenga habilitado `mod_rewrite`.
   - Coloca el proyecto en el directorio público de tu servidor o configura tu host virtual apuntando a la carpeta del proyecto.

5. **Listo para usar:**
   - Accede a la URL configurada y utiliza las credenciales iniciales definidas en los datos de prueba.
