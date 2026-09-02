<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

try {
    // Obtener datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST; // Fallback para formularios normales
    }
    
    // Validar datos requeridos
    if (empty($input['username']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos requeridos']);
        exit();
    }
    
    $username = cleanInput($input['username']);
    $password = $input['password'];
    
    $pdo = getDBConnection();
    
    // Buscar usuario por username o email
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM usuarios WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales inválidas']);
        exit();
    }
    
    // Iniciar sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
    
} // catch (PDOException $e) {
 //   http_response_code(500);
  //  echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
} //catch (Exception $e) {
   // http_response_code(500);
   // echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>

