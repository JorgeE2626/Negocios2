<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function demoPaymentResponse(array $body, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    demoPaymentResponse([
        'success' => true,
        'csrfToken' => generateCSRFToken(),
        'demo' => true
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    demoPaymentResponse(['error' => 'Método no permitido.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$operationId = is_array($input) ? strtolower(trim((string) ($input['operationId'] ?? ''))) : '';
if (!is_array($input) || !preg_match('/^[a-f0-9]{32}$/', $operationId)
    || !isset($input['items']) || !is_array($input['items'])
    || count($input['items']) === 0 || count($input['items']) > 100
    || !verifyCSRFToken((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    demoPaymentResponse(['error' => 'La solicitud de pago de demostración no es válida.'], 400);
}

$pdo = null;
try {
    $pdo = getDBConnection();
    ensureProductSchema($pdo);
    ensureInventoryMovementSchema($pdo);
    ensureSalesSchema($pdo);
    ensurePurchaseOrderSchema($pdo);
    ensureGlobalLogisticsSettingsSchema($pdo);
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pagos_demo (
            operation_id CHAR(32) NOT NULL PRIMARY KEY,
            folio VARCHAR(30) NOT NULL UNIQUE,
            total DECIMAL(10,2) NOT NULL,
            items_json TEXT NOT NULL,
            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->beginTransaction();
    $existingPayment = $pdo->prepare(
        'SELECT folio, total, items_json FROM pagos_demo WHERE operation_id = ? FOR UPDATE'
    );
    $existingPayment->execute([$operationId]);
    $existing = $existingPayment->fetch();
    if ($existing) {
        $pdo->commit();
        demoPaymentResponse([
            'success' => true,
            'alreadyProcessed' => true,
            'folio' => $existing['folio'],
            'total' => (float) $existing['total'],
            'items' => json_decode($existing['items_json'], true) ?: []
        ]);
    }

    $itemsByProduct = [];
    $subtotalCents = 0;
    foreach ($input['items'] as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Hay un producto inválido en el carrito.', 400);
        }
        $quantity = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($item['producto'] ?? ''));
        $price = filter_var($item['precio'] ?? null, FILTER_VALIDATE_FLOAT);
        $hasProductId = array_key_exists('productId', $item);
        $productId = $hasProductId
            ? filter_var($item['productId'], FILTER_VALIDATE_INT)
            : null;
        if ($quantity === false || $quantity < 1 || $quantity > 100
            || $name === '' || mb_strlen($name, 'UTF-8') > 127
            || $price === false || $price <= 0
            || ($hasProductId && ($productId === false || $productId === null || $productId <= 0))) {
            throw new RuntimeException('El producto, precio o cantidad no es válido.', 400);
        }

        $byId = $hasProductId;
        $productQuery = $pdo->prepare(
            $byId
                ? 'SELECT id, name, description, price, proveedor, stock_actual, stock_maximo, estrategia_logistica
                   FROM productos WHERE id = ? AND active = 1 FOR UPDATE'
                : 'SELECT id, name, description, price, proveedor, stock_actual, stock_maximo, estrategia_logistica
                   FROM productos WHERE name = ? AND active = 1 LIMIT 1 FOR UPDATE'
        );
        $productQuery->execute([$byId ? $productId : $name]);
        $product = $productQuery->fetch();
        if (!$product) {
            throw new RuntimeException('El producto "' . $name . '" no está vinculado a un producto activo del inventario.', 409);
        }

        $databasePriceCents = (int) round((float) $product['price'] * 100);
        $priceCents = $byId ? $databasePriceCents : (int) round($price * 100);
        if ($byId && $priceCents !== (int) round($price * 100)) {
            throw new RuntimeException('El precio de "' . $product['name'] . '" cambió. Actualiza el carrito.', 409);
        }

        $id = (int) $product['id'];
        if (!isset($itemsByProduct[$id])) {
            $itemsByProduct[$id] = [
                'product' => $product,
                'quantity' => 0,
                'items' => []
            ];
        }
        $itemsByProduct[$id]['quantity'] += $quantity;
        if ($itemsByProduct[$id]['quantity'] > 100) {
            throw new RuntimeException('La cantidad total de un producto no puede superar 100.', 400);
        }
        $itemsByProduct[$id]['items'][] = [
            'producto' => (string) $product['name'],
            'descripcion' => trim((string) ($item['descripcion'] ?? $product['description'] ?? $name)),
            'precio' => $priceCents / 100,
            'cantidad' => $quantity,
            'productId' => $id
        ];
        $subtotalCents += $priceCents * $quantity;
    }

    foreach ($itemsByProduct as $sale) {
        if ((int) $sale['product']['stock_actual'] < $sale['quantity']) {
            throw new RuntimeException(
                'No hay suficientes existencias de ' . $sale['product']['name']
                    . '. Disponible: ' . (int) $sale['product']['stock_actual'] . '.',
                409
            );
        }
    }

    $shippingCents = $subtotalCents >= 50000 ? 0 : 8000;
    $totalCents = $subtotalCents + $shippingCents;
    $folio = 'DEMO-' . strtoupper(substr(hash('sha256', $operationId), 0, 12));
    $clienteId = ($_SESSION['user_role'] ?? '') === 'cliente'
        ? ((int) ($_SESSION['user_id'] ?? 0) ?: null)
        : null;
    $updateStock = $pdo->prepare('UPDATE productos SET stock_actual = ? WHERE id = ?');
    $insertMovement = $pdo->prepare(
        "INSERT INTO movimientos_inventario (producto_id, usuario_id, tipo, cantidad, motivo)
         VALUES (?, ?, 'salida', ?, ?)"
    );
    $insertSale = $pdo->prepare(
        'INSERT INTO ventas (folio, cliente_id, producto_id, total) VALUES (?, ?, ?, ?)'
    );
    $paidItems = [];
    $lineNumber = 0;
    foreach ($itemsByProduct as $productId => $sale) {
        $product = $sale['product'];
        $newStock = (int) $product['stock_actual'] - $sale['quantity'];
        $updateStock->execute([$newStock, $productId]);
        $insertMovement->execute([
            $productId,
            $clienteId,
            $sale['quantity'],
            'Venta de demostración ' . $folio
        ]);
        foreach ($sale['items'] as $item) {
            $lineNumber++;
            $lineFolio = $folio . '-' . str_pad((string) $lineNumber, 2, '0', STR_PAD_LEFT);
            $lineTotalCents = (int) round($item['precio'] * 100) * $item['cantidad'];
            $insertSale->execute([
                $lineFolio,
                $clienteId,
                $productId,
                number_format($lineTotalCents / 100, 2, '.', '')
            ]);
            $paidItems[] = $item;
        }
        createAutomaticRestockOrder(
            $pdo,
            $product,
            $newStock,
            $clienteId
        );
    }

    $total = number_format($totalCents / 100, 2, '.', '');
    $savePayment = $pdo->prepare(
        'INSERT INTO pagos_demo (operation_id, folio, total, items_json) VALUES (?, ?, ?, ?)'
    );
    $savePayment->execute([
        $operationId,
        $folio,
        $total,
        json_encode($paidItems, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    ]);
    $pdo->commit();

    demoPaymentResponse([
        'success' => true,
        'folio' => $folio,
        'total' => (float) $total,
        'items' => $paidItems
    ]);
} catch (RuntimeException $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $error->getCode() >= 400 && $error->getCode() <= 599 ? $error->getCode() : 500;
    demoPaymentResponse(['error' => $error->getMessage()], $status);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Demo payment failed: ' . $error->getMessage());
    demoPaymentResponse(['error' => 'No se pudo completar la compra. El carrito y el inventario se conservaron.'], 500);
}
