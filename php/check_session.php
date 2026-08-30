<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

try {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        
        if ($user) {
            echo json_encode([
                'authenticated' => true,
                'user' => $user
            ]);
        } else {
            // Sesión corrupta, limpiar
            session_destroy();
            echo json_encode(['authenticated' => false]);
        }
    } else {
        echo json_encode(['authenticated' => false]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>

