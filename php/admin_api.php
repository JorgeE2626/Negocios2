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

function normalizeProviderProducts($value): array
{
    if (is_string($value)) {
        $value = preg_split('/\r\n|\r|\n/', $value);
    }
    if (!is_array($value)) {
        throw new InvalidArgumentException('La lista de productos no es válida.');
    }

    $products = [];
    foreach ($value as $product) {
        if (!is_string($product)) {
            throw new InvalidArgumentException('Cada producto debe ser texto.');
        }
        $product = trim($product);
        if ($product !== '' && !in_array($product, $products, true)) {
            $products[] = $product;
        }
    }

    return $products;
}

function syncProviderProducts(PDO $pdo, string $provider, array $products): void
{
    $findProduct = $pdo->prepare(
        'SELECT id FROM productos WHERE name = ? AND proveedor = ? LIMIT 1'
    );
    $createProduct = $pdo->prepare(
        "INSERT INTO productos
            (name, description, price, category, proveedor, stock_actual, stock_minimo,
             estrategia_logistica, features, image_icon, active)
         VALUES (?, 'Pendiente de completar', 0, 'Pendiente', ?, 0, 0, 'Push', '[]', '', 0)"
    );

    foreach ($products as $product) {
        if (mb_strlen($product, 'UTF-8') > 100) {
            throw new InvalidArgumentException('El nombre de cada producto no puede superar 100 caracteres.');
        }
        $findProduct->execute([$product, $provider]);
        if (!$findProduct->fetch()) {
            $createProduct->execute([$product, $provider]);
        }
    }
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
    ensureProductSchema($pdo);
    ensureProviderSchema($pdo);
    ensureInventoryMovementSchema($pdo);
    ensurePurchaseOrderSchema($pdo);
    $method = $_SERVER['REQUEST_METHOD'];
    $action = strtolower((string) ($_GET['action'] ?? ''));
    $input = adminInput();

    if ($method === 'GET') {
        $demandaPorProducto = [];
        $demandaQuery = $pdo->query(
            "SELECT producto_id, COUNT(*) AS ventas_recientes
             FROM ventas
             WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND producto_id IS NOT NULL
             GROUP BY producto_id"
        )->fetchAll();
        foreach ($demandaQuery as $demanda) {
            $demandaPorProducto[$demanda['producto_id']] = (int) $demanda['ventas_recientes'];
        }

        $productos = $pdo->query(
            'SELECT id, name AS nombre, description, price AS precio, category, proveedor, ubicacion,
                    stock_actual, stock_minimo, stock_maximo, estrategia_logistica,
                    features, image_icon, active
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
            "SELECT id, username, nombre, email AS correo, telefono, role,
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

        $movimientos = $pdo->query(
            "SELECT m.id, m.producto_id, p.name AS producto, m.tipo, m.cantidad, m.motivo,
                    DATE_FORMAT(m.fecha, '%d/%m/%Y %H:%i') AS fecha,
                    COALESCE(u.nombre, u.username, 'Sistema') AS usuario
             FROM movimientos_inventario m
             LEFT JOIN productos p ON p.id = m.producto_id
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             ORDER BY m.fecha DESC, m.id DESC"
        )->fetchAll();

        $pedidos = $pdo->query(
            "SELECT o.id, o.folio, o.producto_id, p.name AS producto, o.proveedor, o.cantidad,
                    o.tipo, o.estado, DATE_FORMAT(o.fecha_pedido, '%d/%m/%Y') AS fecha,
                    o.notas, COALESCE(u.nombre, u.username, 'Sistema') AS usuario
             FROM pedidos_reposicion o
             LEFT JOIN productos p ON p.id = o.producto_id
             LEFT JOIN usuarios u ON u.id = o.usuario_id
             ORDER BY o.fecha_creacion DESC, o.id DESC"
        )->fetchAll();

        $proveedores = $pdo->query(
            'SELECT id, nombre, contacto, email, telefono, productos FROM proveedores ORDER BY nombre'
        )->fetchAll();
        $proveedoresPorNombre = [];
        foreach ($proveedores as &$proveedor) {
            $proveedor['productos'] = normalizeProviderProducts(
                json_decode($proveedor['productos'] ?? '[]', true) ?? []
            );
            $proveedoresPorNombre[mb_strtolower(trim($proveedor['nombre']), 'UTF-8')] = true;
        }
        unset($proveedor);

        $productosPorProveedor = [];
        $productosConProveedor = $pdo->query(
            "SELECT name, proveedor FROM productos
             WHERE TRIM(COALESCE(proveedor, '')) <> ''
             ORDER BY proveedor, name"
        )->fetchAll();
        foreach ($productosConProveedor as $producto) {
            $nombreProveedor = trim($producto['proveedor']);
            $clave = mb_strtolower($nombreProveedor, 'UTF-8');
            if (isset($proveedoresPorNombre[$clave])) {
                continue;
            }
            if (!isset($productosPorProveedor[$clave])) {
                $productosPorProveedor[$clave] = [
                    'id' => null,
                    'nombre' => $nombreProveedor,
                    'contacto' => '',
                    'email' => '',
                    'telefono' => '',
                    'productos' => []
                ];
            }
            $productosPorProveedor[$clave]['productos'][] = $producto['name'];
        }
        $proveedores = array_merge($proveedores, array_values($productosPorProveedor));

        adminResponse(compact('productos', 'proveedores', 'clientes', 'usuarios', 'interacciones', 'ventas', 'movimientos', 'pedidos', 'demandaPorProducto'));
    }

    if ($method === 'POST' && $action === 'pedido') {
        $productoId = (int) ($input['producto_id'] ?? 0);
        $proveedor = readStringField($input, ['proveedor']);
        $cantidad = filter_var($input['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $tipo = strtolower(readStringField($input, ['tipo'], 'reposicion'));
        $fecha = readStringField($input, ['fecha']);
        $notas = readStringField($input, ['notas']);

        if ($productoId <= 0 || $cantidad === false || $cantidad === null || $cantidad <= 0
            || !in_array($tipo, ['reposicion', 'venta'], true)
            || mb_strlen($proveedor, 'UTF-8') > 150 || mb_strlen($notas, 'UTF-8') > 5000
            || ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))) {
            adminResponse(['error' => 'Completa un producto, cantidad válida, tipo y fecha válidos.'], 400);
        }

        $productoExiste = $pdo->prepare('SELECT id FROM productos WHERE id = ?');
        $productoExiste->execute([$productoId]);
        if (!$productoExiste->fetch()) {
            adminResponse(['error' => 'El producto seleccionado no existe.'], 404);
        }

        $pdo->beginTransaction();
        try {
            $insertar = $pdo->prepare(
                "INSERT INTO pedidos_reposicion
                    (folio, producto_id, proveedor, cantidad, tipo, estado, fecha_pedido, notas, usuario_id)
                 VALUES (?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)"
            );
            $insertar->execute([
                'TMP-' . bin2hex(random_bytes(12)),
                $productoId,
                $proveedor,
                $cantidad,
                $tipo,
                $fecha !== '' ? $fecha : null,
                $notas,
                (int) ($_SESSION['user_id'] ?? 0) ?: null
            ]);
            $pedidoId = (int) $pdo->lastInsertId();
            $folio = 'PC-' . str_pad((string) $pedidoId, 3, '0', STR_PAD_LEFT);
            $actualizarFolio = $pdo->prepare('UPDATE pedidos_reposicion SET folio = ? WHERE id = ?');
            $actualizarFolio->execute([$folio, $pedidoId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        adminResponse(['success' => true, 'id' => $pedidoId, 'folio' => $folio], 201);
    }

    if (($method === 'PATCH' || $method === 'PUT') && $action === 'pedido') {
        $pedidoId = (int) ($input['id'] ?? 0);
        $nuevoEstado = strtolower(readStringField($input, ['estado']));
        if ($pedidoId <= 0 || !in_array($nuevoEstado, ['en_proceso', 'surtido', 'cancelado'], true)) {
            adminResponse(['error' => 'Pedido o estado no válido.'], 400);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, folio, producto_id, proveedor, cantidad, tipo, estado
                 FROM pedidos_reposicion WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch();
            if (!$pedido) {
                $pdo->rollBack();
                adminResponse(['error' => 'Pedido no encontrado.'], 404);
            }
            if ($pedido['estado'] === 'surtido' || $pedido['estado'] === 'cancelado') {
                $pdo->rollBack();
                adminResponse(['error' => 'El pedido ya está cerrado y no se puede cambiar.'], 409);
            }
            if ($nuevoEstado === 'en_proceso' && $pedido['estado'] !== 'pendiente') {
                $pdo->rollBack();
                adminResponse(['error' => 'Solo se pueden procesar pedidos pendientes.'], 409);
            }

            if ($nuevoEstado === 'surtido') {
                $productoStmt = $pdo->prepare(
                    'SELECT id, name, proveedor, stock_actual, stock_minimo, stock_maximo, estrategia_logistica
                     FROM productos WHERE id = ? FOR UPDATE'
                );
                $productoStmt->execute([$pedido['producto_id']]);
                $producto = $productoStmt->fetch();
                if (!$producto) {
                    $pdo->rollBack();
                    adminResponse(['error' => 'El producto del pedido ya no existe.'], 404);
                }

                $esReposicion = $pedido['tipo'] === 'reposicion';
                $nuevoStock = (int) $producto['stock_actual']
                    + ($esReposicion ? (int) $pedido['cantidad'] : -(int) $pedido['cantidad']);
                if ($nuevoStock < 0) {
                    $pdo->rollBack();
                    adminResponse(['error' => 'El pedido de venta supera el stock disponible.'], 400);
                }
                if ($esReposicion && $nuevoStock > (int) $producto['stock_maximo']) {
                    $pdo->rollBack();
                    adminResponse([
                        'error' => 'El pedido excede el inventario máximo. Stock máximo: '
                            . (int) $producto['stock_maximo'] . '.'
                    ], 400);
                }

                $actualizarStock = $pdo->prepare('UPDATE productos SET stock_actual = ? WHERE id = ?');
                $actualizarStock->execute([$nuevoStock, $producto['id']]);
                $insertarMovimiento = $pdo->prepare(
                    'INSERT INTO movimientos_inventario (producto_id, usuario_id, tipo, cantidad, motivo)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insertarMovimiento->execute([
                    $producto['id'],
                    (int) ($_SESSION['user_id'] ?? 0) ?: null,
                    $esReposicion ? 'entrada' : 'salida',
                    $pedido['cantidad'],
                    'Pedido ' . $pedido['folio']
                ]);
                if (!$esReposicion) {
                    $pedidoAutomatico = createAutomaticRestockOrder(
                        $pdo,
                        $producto,
                        $nuevoStock,
                        (int) ($_SESSION['user_id'] ?? 0) ?: null
                    );
                }
            }

            $actualizarPedido = $pdo->prepare('UPDATE pedidos_reposicion SET estado = ? WHERE id = ?');
            $actualizarPedido->execute([$nuevoEstado, $pedidoId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        adminResponse([
            'success' => true,
            'id' => $pedidoId,
            'estado' => $nuevoEstado,
            'pedidoAutomatico' => $pedidoAutomatico ?? null
        ]);
    }

    if ($method === 'POST' && $action === 'movimiento') {
        $productoId = (int) ($input['producto_id'] ?? 0);
        $tipo = strtolower(readStringField($input, ['tipo']));
        $cantidad = filter_var($input['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $motivo = readStringField($input, ['motivo']);

        if ($productoId <= 0 || !in_array($tipo, ['entrada', 'salida'], true)
            || $cantidad === false || $cantidad === null || $cantidad <= 0
            || $motivo === '' || mb_strlen($motivo, 'UTF-8') > 255) {
            adminResponse(['error' => 'Producto, tipo, cantidad positiva y motivo son obligatorios.'], 400);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, proveedor, stock_actual, stock_minimo, stock_maximo, estrategia_logistica
                 FROM productos WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$productoId]);
            $producto = $stmt->fetch();
            if (!$producto) {
                $pdo->rollBack();
                adminResponse(['error' => 'Producto no encontrado.'], 404);
            }

            $stockActualizado = (int) $producto['stock_actual']
                + ($tipo === 'entrada' ? $cantidad : -$cantidad);
            if ($stockActualizado < 0) {
                $pdo->rollBack();
                adminResponse(['error' => 'La salida supera el stock disponible.'], 400);
            }
            if ($tipo === 'entrada' && $stockActualizado > (int) $producto['stock_maximo']) {
                $pdo->rollBack();
                adminResponse([
                    'error' => 'La entrada excede el inventario máximo. Stock máximo: '
                        . (int) $producto['stock_maximo'] . '.'
                ], 400);
            }

            $actualizar = $pdo->prepare('UPDATE productos SET stock_actual = ? WHERE id = ?');
            $actualizar->execute([$stockActualizado, $productoId]);

            $insertar = $pdo->prepare(
                'INSERT INTO movimientos_inventario (producto_id, usuario_id, tipo, cantidad, motivo)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insertar->execute([
                $productoId,
                (int) ($_SESSION['user_id'] ?? 0) ?: null,
                $tipo,
                $cantidad,
                $motivo
            ]);
            $movimientoId = $pdo->lastInsertId();
            $pedidoAutomatico = $tipo === 'salida'
                ? createAutomaticRestockOrder(
                    $pdo,
                    $producto,
                    $stockActualizado,
                    (int) ($_SESSION['user_id'] ?? 0) ?: null
                )
                : null;
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        adminResponse([
            'success' => true,
            'id' => $movimientoId,
            'stock_actual' => $stockActualizado,
            'pedidoAutomatico' => $pedidoAutomatico
        ], 201);
    }

    if ($method === 'POST' && $action === 'proveedor') {
        $nombre = readStringField($input, ['nombre']);
        $nombreAnterior = readStringField($input, ['nombreAnterior']);
        $contacto = readStringField($input, ['contacto']);
        $email = readStringField($input, ['email']);
        $telefono = readStringField($input, ['telefono']);
        $productos = normalizeProviderProducts($input['productos'] ?? []);

        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 150
            || mb_strlen($nombreAnterior, 'UTF-8') > 150
            || mb_strlen($contacto, 'UTF-8') > 150
            || mb_strlen($email, 'UTF-8') > 254
            || mb_strlen($telefono, 'UTF-8') > 30
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || ($telefono !== '' && !preg_match('/^[0-9]+$/', $telefono))) {
            adminResponse(['error' => 'Revisa el nombre, correo y teléfono del proveedor.'], 400);
        }

        $duplicate = $pdo->prepare('SELECT id FROM proveedores WHERE nombre = ?');
        $duplicate->execute([$nombre]);
        if ($duplicate->fetch()) {
            adminResponse(['error' => 'Ya existe un proveedor con ese nombre.'], 409);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO proveedores (nombre, contacto, email, telefono, productos) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nombre, $contacto, $email, $telefono, json_encode($productos, JSON_UNESCAPED_UNICODE)]);
            if ($nombreAnterior !== '' && $nombreAnterior !== $nombre) {
                $actualizarProductos = $pdo->prepare('UPDATE productos SET proveedor = ? WHERE proveedor = ?');
                $actualizarProductos->execute([$nombre, $nombreAnterior]);
            }
            syncProviderProducts($pdo, $nombre, $productos);
            $id = $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        adminResponse(['success' => true, 'id' => $id], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $action === 'proveedor') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            adminResponse(['error' => 'ID del proveedor inválido.'], 400);
        }

        $stmt = $pdo->prepare('SELECT * FROM proveedores WHERE id = ?');
        $stmt->execute([$id]);
        $actual = $stmt->fetch();
        if (!$actual) {
            adminResponse(['error' => 'Proveedor no encontrado.'], 404);
        }

        $nombre = array_key_exists('nombre', $input) ? trim((string) $input['nombre']) : $actual['nombre'];
        $contacto = array_key_exists('contacto', $input) ? trim((string) $input['contacto']) : $actual['contacto'];
        $email = array_key_exists('email', $input) ? trim((string) $input['email']) : $actual['email'];
        $telefono = array_key_exists('telefono', $input) ? trim((string) $input['telefono']) : $actual['telefono'];
        $productos = array_key_exists('productos', $input)
            ? normalizeProviderProducts($input['productos'])
            : normalizeProviderProducts(json_decode($actual['productos'] ?? '[]', true) ?? []);

        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 150
            || mb_strlen($contacto, 'UTF-8') > 150
            || mb_strlen($email, 'UTF-8') > 254
            || mb_strlen($telefono, 'UTF-8') > 30
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || ($telefono !== '' && !preg_match('/^[0-9]+$/', $telefono))) {
            adminResponse(['error' => 'Revisa el nombre, correo y teléfono del proveedor.'], 400);
        }

        $duplicate = $pdo->prepare('SELECT id FROM proveedores WHERE nombre = ? AND id <> ?');
        $duplicate->execute([$nombre, $id]);
        if ($duplicate->fetch()) {
            adminResponse(['error' => 'Ya existe un proveedor con ese nombre.'], 409);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE proveedores SET nombre = ?, contacto = ?, email = ?, telefono = ?, productos = ? WHERE id = ?'
            );
            $stmt->execute([
                $nombre,
                $contacto,
                $email,
                $telefono,
                json_encode($productos, JSON_UNESCAPED_UNICODE),
                $id
            ]);

            if ($nombre !== $actual['nombre']) {
                $actualizarProductos = $pdo->prepare('UPDATE productos SET proveedor = ? WHERE proveedor = ?');
                $actualizarProductos->execute([$nombre, $actual['nombre']]);
            }
            syncProviderProducts($pdo, $nombre, $productos);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        adminResponse(['success' => true, 'id' => $id]);
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

    if (($method === 'PUT' || $method === 'PATCH') && $action === 'cliente') {
        $id = (int) ($input['id'] ?? 0);
        $estado = strtolower(readStringField($input, ['estado', 'status']));

        if ($id <= 0 || !in_array($estado, ['activo', 'inactivo'], true)) {
            adminResponse(['error' => 'Cliente o estado inválido.'], 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET estado = ?
             WHERE id = ? AND role = 'cliente'"
        );
        $stmt->execute([$estado, $id]);

        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND role = 'cliente'");
            $exists->execute([$id]);
            if (!$exists->fetch()) {
                adminResponse(['error' => 'Cliente no encontrado.'], 404);
            }
        }

        adminResponse(['success' => true, 'id' => $id, 'estado' => $estado]);
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

    if (($method === 'PUT' || $method === 'PATCH') && $action === 'usuario') {
        $id = (int) ($input['id'] ?? 0);
        $nombre = readStringField($input, ['nombre', 'name']);
        $correo = readStringField($input, ['correo', 'email']);
        $password = (string) ($input['password'] ?? '');
        $telefono = readStringField($input, ['telefono', 'phone']);
        $role = strtolower(readStringField($input, ['tipo', 'role'], 'cliente'));

        if ($id <= 0 || $nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            adminResponse(['error' => 'Usuario, nombre y correo electrónico válidos son obligatorios.'], 400);
        }
        if ($password !== '' && strlen($password) < 6) {
            adminResponse(['error' => 'La contraseña debe tener al menos 6 caracteres.'], 400);
        }
        if (!in_array($role, ['admin', 'cliente'], true)) {
            $role = 'cliente';
        }

        $exists = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            adminResponse(['error' => 'Usuario no encontrado.'], 404);
        }

        $duplicate = $pdo->prepare('SELECT id FROM usuarios WHERE (email = ?) AND id <> ?');
        $duplicate->execute([$correo, $id]);
        if ($duplicate->fetch()) {
            adminResponse(['error' => 'El correo electrónico ya está registrado.'], 400);
        }

        if ($password !== '') {
            $stmt = $pdo->prepare(
                'UPDATE usuarios
                 SET nombre = ?, email = ?, password_hash = ?, telefono = ?, role = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                cleanInput($nombre),
                cleanInput($correo),
                password_hash($password, PASSWORD_DEFAULT),
                cleanInput($telefono),
                $role,
                $id
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE usuarios
                 SET nombre = ?, email = ?, telefono = ?, role = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                cleanInput($nombre),
                cleanInput($correo),
                cleanInput($telefono),
                $role,
                $id
            ]);
        }

        adminResponse(['success' => true, 'id' => $id]);
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
} catch (InvalidArgumentException $e) {
    adminResponse(['error' => $e->getMessage()], 400);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos. Verifica el esquema importado.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.'], JSON_UNESCAPED_UNICODE);
}
