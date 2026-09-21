<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Verificar que el usuario esté logueado y sea administrador
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Se requieren permisos de administrador.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

try {
    switch ($method) {
        case 'GET':
            // Obtener todos los productos (incluyendo inactivos para admin)
            $stmt = $pdo->prepare("SELECT * FROM productos ORDER BY id");
            $stmt->execute();
            $productos = $stmt->fetchAll();
            
            // Procesar features como arrays
            foreach ($productos as &$producto) {
                $producto['features'] = json_decode($producto['features'], true) ?: [];
            }
            
            echo json_encode([
                'success' => true,
                'productos' => $productos
            ]);
            break;
            
        case 'POST':
            // Crear nuevo producto
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            // Validar datos requeridos
            if (!isset($input['name'], $input['description'], $input['price'], $input['category'])
                || trim((string) $input['name']) === ''
                || trim((string) $input['description']) === ''
                || trim((string) $input['category']) === ''
                || !is_numeric($input['price'])
                || (float) $input['price'] < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan datos requeridos (name, description, price, category)']);
                exit();
            }
            
            $name = cleanInput($input['name']);
            $description = cleanInput($input['description'] ?? '');
            $price = floatval($input['price']);
            $category = cleanInput($input['category']);
            $stock_actual = filter_var($input['stock_actual'] ?? 0, FILTER_VALIDATE_INT);
            $stock_minimo = filter_var($input['stock_minimo'] ?? 0, FILTER_VALIDATE_INT);
            $estrategia_logistica = cleanInput($input['estrategia_logistica'] ?? '');
            if ($stock_actual === false || $stock_minimo === false || $stock_actual < 0 || $stock_minimo < 0 || $estrategia_logistica === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Stock y estrategia logística deben ser válidos']);
                exit();
            }
            $features = isset($input['features']) ? json_encode($input['features']) : '[]';
            $image_icon = cleanInput($input['image_icon'] ?? '');
            $active = isset($input['active']) ? (bool)$input['active'] : true;
            
            $stmt = $pdo->prepare("INSERT INTO productos (name, description, price, category, stock_actual, stock_minimo, estrategia_logistica, features, image_icon, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $category, $stock_actual, $stock_minimo, $estrategia_logistica, $features, $image_icon, $active]);
            
            $product_id = $pdo->lastInsertId();
            
            // Obtener el producto creado
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
            $stmt->execute([$product_id]);
            $producto = $stmt->fetch();
            $producto['features'] = json_decode($producto['features'], true) ?: [];
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Producto creado exitosamente',
                'producto' => $producto
            ]);
            break;
            
        case 'PUT':
            // Actualizar producto existente
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID del producto requerido']);
                exit();
            }
            
            $id = intval($input['id']);
            
            // Verificar que el producto existe
            $stmt = $pdo->prepare("SELECT id FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Producto no encontrado']);
                exit();
            }
            
            // Construir query de actualización dinámicamente
            $updates = [];
            $params = [];
            
            if (isset($input['name'])) {
                $updates[] = "name = ?";
                $params[] = cleanInput($input['name']);
            }
            if (isset($input['description'])) {
                $updates[] = "description = ?";
                $params[] = cleanInput($input['description']);
            }
            if (isset($input['price'])) {
                $updates[] = "price = ?";
                $params[] = floatval($input['price']);
            }
            if (isset($input['category'])) {
                $updates[] = "category = ?";
                $params[] = cleanInput($input['category']);
            }
            if (isset($input['stock_actual'])) {
                $stockActual = filter_var($input['stock_actual'], FILTER_VALIDATE_INT);
                if ($stockActual === false || $stockActual < 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'El stock actual debe ser un entero no negativo']);
                    exit();
                }
                $updates[] = "stock_actual = ?";
                $params[] = $stockActual;
            }
            if (isset($input['stock_minimo'])) {
                $stockMinimo = filter_var($input['stock_minimo'], FILTER_VALIDATE_INT);
                if ($stockMinimo === false || $stockMinimo < 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'El stock mínimo debe ser un entero no negativo']);
                    exit();
                }
                $updates[] = "stock_minimo = ?";
                $params[] = $stockMinimo;
            }
            if (isset($input['estrategia_logistica'])) {
                $estrategia = cleanInput($input['estrategia_logistica']);
                if ($estrategia === '') {
                    http_response_code(400);
                    echo json_encode(['error' => 'La estrategia logística es obligatoria']);
                    exit();
                }
                $updates[] = "estrategia_logistica = ?";
                $params[] = $estrategia;
            }
            if (isset($input['features'])) {
                $updates[] = "features = ?";
                $params[] = json_encode($input['features']);
            }
            if (isset($input['image_icon'])) {
                $updates[] = "image_icon = ?";
                $params[] = cleanInput($input['image_icon']);
            }
            if (isset($input['active'])) {
                $updates[] = "active = ?";
                $params[] = (bool)$input['active'];
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No hay datos para actualizar']);
                exit();
            }
            
            $params[] = $id;
            $sql = "UPDATE productos SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Obtener el producto actualizado
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            $producto = $stmt->fetch();
            $producto['features'] = json_decode($producto['features'], true) ?: [];
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'producto' => $producto
            ]);
            break;
            
        case 'DELETE':
            // Eliminar producto (soft delete - marcar como inactivo)
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID del producto requerido']);
                exit();
            }
            
            $id = intval($input['id']);
            
            // Verificar que el producto existe
            $stmt = $pdo->prepare("SELECT id FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Producto no encontrado']);
                exit();
            }
            
            // Marcar como inactivo en lugar de eliminar
            $stmt = $pdo->prepare("UPDATE productos SET active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto eliminado exitosamente'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            break;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
