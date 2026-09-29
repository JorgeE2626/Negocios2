<?php
// Configuración de la base de datos para XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Por defecto XAMPP no tiene contraseña para root
define('DB_NAME', 'nikenza_store');

// Configuración de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 si usas HTTPS

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Función para conectar a la base de datos
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }

}

function ensureProductSchema(PDO $pdo): void {
    $column = $pdo->query("SHOW COLUMNS FROM productos LIKE 'proveedor'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN proveedor VARCHAR(150) NOT NULL DEFAULT '' AFTER category");
    }
    $locationColumn = $pdo->query("SHOW COLUMNS FROM productos LIKE 'ubicacion'")->fetch();
    if (!$locationColumn) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN ubicacion VARCHAR(150) NOT NULL DEFAULT '' AFTER proveedor");
    }
    $maximumStockColumn = $pdo->query("SHOW COLUMNS FROM productos LIKE 'stock_maximo'")->fetch();
    if (!$maximumStockColumn) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN stock_maximo INT UNSIGNED NOT NULL DEFAULT 0 AFTER stock_minimo");
    }
}

function ensureProviderSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS proveedores (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            contacto VARCHAR(150) NOT NULL DEFAULT '',
            email VARCHAR(254) NOT NULL DEFAULT '',
            telefono VARCHAR(30) NOT NULL DEFAULT '',
            productos TEXT NOT NULL,
            UNIQUE KEY uq_proveedores_nombre (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensureInventoryMovementSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS movimientos_inventario (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            producto_id INT NOT NULL,
            usuario_id INT NULL,
            tipo VARCHAR(10) NOT NULL,
            cantidad INT UNSIGNED NOT NULL,
            motivo VARCHAR(255) NOT NULL,
            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_movimientos_fecha (fecha),
            INDEX idx_movimientos_producto (producto_id),
            INDEX idx_movimientos_tipo (tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensurePurchaseOrderSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pedidos_reposicion (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            folio VARCHAR(30) NOT NULL UNIQUE,
            producto_id INT NOT NULL,
            proveedor VARCHAR(150) NOT NULL DEFAULT '',
            cantidad INT UNSIGNED NOT NULL,
            tipo VARCHAR(20) NOT NULL DEFAULT 'reposicion',
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            fecha_pedido DATE NULL,
            notas TEXT NULL,
            usuario_id INT NULL,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pedidos_estado (estado),
            INDEX idx_pedidos_tipo (tipo),
            INDEX idx_pedidos_producto (producto_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function createAutomaticRestockOrder(PDO $pdo, array $product, int $stockActualizado, ?int $usuarioId): ?array
{
    $stockMaximo = (int) ($product['stock_maximo'] ?? 0);
    if ($stockMaximo <= 0) {
        return null;
    }

    if ($stockActualizado >= $stockMaximo) {
        return null;
    }

    $pedidosPendientes = $pdo->prepare(
        "SELECT COALESCE(SUM(cantidad), 0)
         FROM pedidos_reposicion
         WHERE producto_id = ? AND tipo = 'reposicion'
           AND estado IN ('pendiente', 'en_proceso')"
    );
    $pedidosPendientes->execute([$product['id']]);
    $cantidadPendiente = (int) $pedidosPendientes->fetchColumn();
    $cantidad = $stockMaximo - $stockActualizado - $cantidadPendiente;
    if ($cantidad <= 0) {
        return null;
    }

    $insertar = $pdo->prepare(
        "INSERT INTO pedidos_reposicion
            (folio, producto_id, proveedor, cantidad, tipo, estado, fecha_pedido, notas, usuario_id)
         VALUES (?, ?, ?, ?, 'reposicion', 'pendiente', CURDATE(), ?, ?)"
    );
    $insertar->execute([
        'TMP-' . bin2hex(random_bytes(12)),
        $product['id'],
        (string) ($product['proveedor'] ?? ''),
        $cantidad,
        'Pedido automático por nivel de stock',
        $usuarioId
    ]);
    $pedidoId = (int) $pdo->lastInsertId();
    $folio = 'PA-' . str_pad((string) $pedidoId, 3, '0', STR_PAD_LEFT);
    $actualizarFolio = $pdo->prepare('UPDATE pedidos_reposicion SET folio = ? WHERE id = ?');
    $actualizarFolio->execute([$folio, $pedidoId]);

    return [
        'id' => $pedidoId,
        'folio' => $folio,
        'producto' => (string) ($product['name'] ?? 'Producto'),
        'cantidad' => $cantidad
    ];
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar si el usuario es administrador
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Función para obtener información del usuario actual
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Función para redirigir
function redirect($url) {
    header("Location: $url");
    exit();
}

// Función para limpiar datos de entrada
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para generar token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
