# Sistema de Autenticación Nikenza Store

## Descripción
Sistema web completo con registro y login de dos tipos de usuario (cliente y administrador) para la tienda Nikenza. Los clientes pueden ver y comprar productos, mientras que los administradores pueden gestionar el inventario.

## Requisitos
- XAMPP (Apache + MySQL + PHP)
- Navegador web moderno

## Instalación

### 1. Configurar XAMPP
1. Instala XAMPP desde https://www.apachefriends.org/
2. Inicia Apache y MySQL desde el panel de control de XAMPP

### 2. Configurar la Base de Datos
1. Abre phpMyAdmin en tu navegador: `http://localhost/phpmyadmin`
2. Ejecuta el archivo `database.sql` para crear la base de datos y tablas:
   - Crea una nueva base de datos llamada `nikenza_store`
   - Importa el archivo `database.sql` o copia y pega su contenido en la pestaña SQL

### 3. Instalar los Archivos
1. Copia toda la carpeta del proyecto a `C:\xampp\htdocs\` (Windows) o `/opt/lampp/htdocs/` (Linux)
2. La estructura debe quedar así:
   ```
   htdocs/
   └── nikenza_store/
       ├── index.html
       ├── admin.html
       ├── Proyecto.css
       ├── database.sql
       ├── INSTRUCCIONES.md
       ├── includes/
       │   └── config.php
       └── php/
           ├── register.php
           ├── login.php
           ├── logout.php
           ├── check_session.php
           ├── get_products.php
           ├── admin_products.php
           └── test_connection.php
   ```

### 4. Verificar la Instalación
1. Abre tu navegador y ve a: `http://localhost/nikenza_store/php/test_connection.php`
2. Deberías ver un mensaje de conexión exitosa y el estado de las tablas

## Uso del Sistema

### Acceso Principal
- URL: `http://localhost/nikenza_store/`
- La página principal muestra todos los productos y paquetes disponibles

### Usuarios por Defecto
- **Administrador:**
  - Usuario: `admin`
  - Contraseña: `admin123`
  - Acceso al panel de administración para gestionar productos

### Funcionalidades

#### Para Clientes:
- Registro de nueva cuenta
- Inicio de sesión
- Visualización de productos y paquetes
- Contacto via WhatsApp para cotizaciones

#### Para Administradores:
- Todas las funcionalidades de cliente
- Acceso al panel de administración (`admin.html`)
- Gestión completa de productos (CRUD):
  - Crear nuevos productos
  - Editar productos existentes
  - Eliminar productos (soft delete)
  - Ver todos los productos (activos e inactivos)

### Estructura de la Base de Datos

#### Tabla `usuarios`
- `id`: ID único del usuario
- `username`: Nombre de usuario (único)
- `email`: Correo electrónico (único)
- `password_hash`: Contraseña encriptada
- `role`: Rol del usuario ('cliente' o 'admin')
- `created_at`, `updated_at`: Timestamps

#### Tabla `productos`
- `id`: ID único del producto
- `name`: Nombre del producto
- `description`: Descripción del producto
- `price`: Precio del producto
- `category`: Categoría del producto
- `features`: Características (JSON)
- `image_icon`: Clase de icono FontAwesome
- `active`: Estado del producto (activo/inactivo)
- `created_at`, `updated_at`: Timestamps

#### Tabla `paquetes`
- `id`: ID único del paquete
- `name`: Nombre del paquete
- `description`: Descripción del paquete
- `price`: Precio del paquete
- `items`: Items incluidos (JSON)
- `featured`: Si es paquete destacado
- `active`: Estado del paquete (activo/inactivo)
- `created_at`, `updated_at`: Timestamps

## Seguridad
- Contraseñas encriptadas con `password_hash()`
- Protección contra inyección SQL con prepared statements
- Validación de sesiones y roles
- Sanitización de datos de entrada
- Protección CSRF (básica)

## Personalización
- Modifica `includes/config.php` para cambiar configuraciones de base de datos
- Edita `Proyecto.css` para personalizar estilos
- Agrega nuevas categorías de productos en `admin.html`

## Solución de Problemas

### Error de Conexión a la Base de Datos
1. Verifica que MySQL esté ejecutándose en XAMPP
2. Confirma que la base de datos `nikenza_store` existe
3. Revisa las credenciales en `includes/config.php`

### Problemas de Permisos
1. Asegúrate de que Apache tenga permisos de lectura en la carpeta del proyecto
2. En Linux, puedes usar: `sudo chmod -R 755 /opt/lampp/htdocs/nikenza_store`

### Sesiones No Funcionan
1. Verifica que `session.save_path` esté configurado correctamente en PHP
2. Revisa los logs de error de Apache

## Desarrollo Futuro
- Sistema de carrito de compras
- Procesamiento de pagos
- Gestión de inventario avanzada
- Notificaciones por email
- API REST completa
- Panel de estadísticas para administradores

## Soporte
Para soporte técnico o preguntas sobre el sistema, contacta al desarrollador.

