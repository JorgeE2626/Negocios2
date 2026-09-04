<?php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Se requieren permisos de administrador.']);
    exit();
}

function adminInput(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return is_array($_POST) ? $_POST : [];
}

function adminResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function readStringField(array $input, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $input)) {
            return trim((string) $input[$key]);
        }
    }

    return $default;
}

function normalizeInteractionType($value): ?string
{
    $normalized = strtolower(trim((string) $value));
    $map = [
        'llamada' => 'llamada',
        'llamar' => 'llamada',
        'correo' => 'correo',
        'correo electronico' => 'correo',
        'email' => 'correo',
        'mensaje' => 'mensaje',
        'mensaje whatsapp' => 'mensaje',
        'whatsapp' => 'mensaje',
        'reunion' => 'reunion',
        'reunión' => 'reunion',
        'videollamada' => 'reunion',
        'video llamada' => 'reunion',
    ];

    return $map[$normalized] ?? null;
}

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = strtolower((string) ($_GET['action'] ?? ''));
    $input = adminInput();

    if ($method === 'GET') {
        $productos = $pdo->query(
            'SELECT id, name AS nombre, description, price AS precio, category, features, image_icon, active
             FROM productos ORDER BY id'
        )->fetchAll();
        foreach ($productos as &$producto) {
            $producto['features'] = json_decode($producto['features'] ?? '[]', true) ?: [];
            $producto['precio'] = (float) $producto['precio'];
        }
        unset($producto);

        $clientes = $pdo->query(
            "SELECT id, username, nombre, email, telefono, empresa, etapa_crm AS etapa,
                   DATE_FORMAT(fecha_registro, '%Y-%m-%d') AS ingreso,
                   CASE WHEN estado = 'activo' THEN 'Activo' ELSE 'Inactivo' END AS estado
             FROM usuarios WHERE role = 'cliente' ORDER BY id"
        )->fetchAll();

        $usuarios = $pdo->query(
            "SELECT id, COALESCE(nombre, username) AS nombre, email AS correo, telefono,
                   CASE WHEN role = 'admin' THEN 'Administrador' ELSE 'Usuario Normal' END AS tipo
             FROM usuarios ORDER BY id"
        )->fetchAll();

        $interacciones = $pdo->query(
            "SELECT
                   i.id,
                   i.cliente_id,
                   i.cliente_id AS clienteId,
                   COALESCE(u.nombre, u.username) AS cliente_nombre,
                   COALESCE(u.nombre, u.username) AS clienteNombre,
                   CASE
                       WHEN i.tipo = 'llamada' THEN 'Llamada'
                       WHEN i.tipo = 'correo' THEN 'Correo Electrónico'
                       WHEN i.tipo = 'reunion' THEN 'Reunión'
                       WHEN i.tipo = 'mensaje' THEN 'Mensaje WhatsApp'
                       ELSE i.tipo
                   END AS tipo,
                   i.descripcion AS detalle,
                   DATE_FORMAT(i.fecha, '%Y-%m-%d %H:%i') AS fecha_hora,
                   DATE_FORMAT(i.fecha, '%Y-%m-%d %H:%i') AS fechaHora
             FROM interacciones i
             INNER JOIN usuarios u ON u.id = i.cliente_id
             ORDER BY i.fecha DESC, i.id DESC"
        )->fetchAll();

        $ventas = $pdo->query(
            "SELECT v.folio, COALESCE(u.nombre, u.username, 'Cliente no disponible') AS cliente,
                   COALESCE(p.name, 'Producto no disponible') AS producto, v.total
             FROM ventas v LEFT JOIN usuarios u ON u.id = v.cliente_id
             LEFT JOIN productos p ON p.id = v.producto_id ORDER BY v.fecha DESC, v.id DESC"
        )->fetchAll();

        adminResponse(compact('productos', 'clientes', 'usuarios', 'interacciones', 'ventas'));
    }

    if ($method === 'POST' && $action === 'cliente') {
        $nombre = readStringField($input, ['nombre', 'name']);
        $correo = readStringField($input, ['correo', 'email']);
        $telefono = readStringField($input, ['telefono', 'phone']);
        $estado = strtolower(readStringField($input, ['estado', 'status'], 'activo'));

        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            adminResponse(['error' => 'Nombre y correo electrónico válidos son obligatorios.'], 400);
        }

        $username = 'cliente_' . bin2hex(random_bytes(5));
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (username, nombre, email, password_hash, telefono, role, estado, etapa_crm)
             VALUES (?, ?, ?, ?, ?, 'cliente', ?, 'Prospecto')"
        );
        $stmt->execute([
            $username,
            cleanInput($nombre),
            cleanInput($correo),
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            cleanInput($telefono),
            strtolower($estado) === 'inactivo' ? 'inactivo' : 'activo'
        ]);
        adminResponse(['success' => true, 'id' => $pdo->lastInsertId()], 201);
    }

    if ($method === 'POST' && $action === 'interaccion') {
        $clienteId = (int) (readStringField($input, ['clienteId', 'cliente_id', 'cliente']) !== '' ? (int) readStringField($input, ['clienteId', 'cliente_id', 'cliente']) : ($input['clienteId'] ?? $input['cliente_id'] ?? 0));
        $usuarioId = (int) (readStringField($input, ['usuarioId', 'usuario_id'], '0') !== '' ? (int) readStringField($input, ['usuarioId', 'usuario_id'], '0') : ($_SESSION['user_id'] ?? 0));
        $detalle = readStringField($input, ['detalle', 'descripcion', 'nota']);
        $tipo = normalizeInteractionType(readStringField($input, ['tipo', 'type']));

        if (!$tipo || $clienteId <= 0 || $detalle === '') {
            adminResponse(['error' => 'Cliente, tipo y detalle son obligatorios.'], 400);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO interacciones (cliente_id, usuario_id, tipo, descripcion) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $clienteId,
            $usuarioId > 0 ? $usuarioId : (int) ($_SESSION['user_id'] ?? 0),
            $tipo,
            cleanInput($detalle)
        ]);
        adminResponse(['success' => true, 'id' => $pdo->lastInsertId()], 201);
    }

    if ($method === 'POST' && $action === 'usuario') {
        $nombre = readStringField($input, ['nombre', 'name']);
        $correo = readStringField($input, ['correo', 'email']);
        $password = (string) ($input['password'] ?? '');
        $telefono = readStringField($input, ['telefono', 'phone']);
        $role = strtolower(readStringField($input, ['tipo', 'role'], 'cliente'));

        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            adminResponse(['error' => 'Nombre, correo y contraseña válida son obligatorios.'], 400);
        }

        if (!in_array($role, ['admin', 'cliente'], true)) {
            $role = 'cliente';
        }

        $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre)) . '_' . bin2hex(random_bytes(3));
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (username, nombre, email, password_hash, telefono, role, estado, etapa_crm)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $username,
            cleanInput($nombre),
            cleanInput($correo),
            password_hash($password, PASSWORD_DEFAULT),
            cleanInput($telefono),
            $role,
            'activo',
            $role === 'admin' ? 'Activo' : 'Prospecto'
        ]);
        adminResponse(['success' => true, 'id' => $pdo->lastInsertId()], 201);
    }

    if ($method === 'DELETE' && $action === 'usuario') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            adminResponse(['error' => 'ID de usuario inválido.'], 400);
        }
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            adminResponse(['error' => 'No puedes eliminar tu propia cuenta.'], 400);
        }

        $exists = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            adminResponse(['error' => 'Usuario no encontrado.'], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        adminResponse(['success' => true]);
    }

    adminResponse(['error' => 'Operación no permitida.'], 405);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos. Verifica el esquema importado.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.'], JSON_UNESCAPED_UNICODE);
}
