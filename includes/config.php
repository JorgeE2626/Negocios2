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

