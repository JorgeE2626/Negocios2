<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/paypal.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function paymentResponse(array $body, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($body);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentResponse(['error' => 'Método no permitido.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = trim((string) ($input['orderID'] ?? ''));
if (!is_array($input) || !preg_match('/^[A-Z0-9-]{8,64}$/i', $orderId)
    || !hash_equals((string) ($_SESSION['paypal_csrf'] ?? ''), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    paymentResponse(['error' => 'La confirmación de pago no es válida.'], 400);
}

$pendingOrder = $_SESSION['paypal_orders'][$orderId] ?? null;
if (!is_array($pendingOrder) || !isset($pendingOrder['items'], $pendingOrder['totalCents'])) {
    paymentResponse(['error' => 'No se encontró esta compra en la sesión. Vuelve al carrito e inténtalo de nuevo.'], 404);
}

$pdo = null;
try {
    $accessToken = paypalAccessToken();
    $order = paypalApiRequest($accessToken, 'GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    if (($order['id'] ?? '') !== $orderId || !isset($order['purchase_units'][0]['amount'])) {
        throw new RuntimeException('PayPal no devolvió una orden válida.', 502);
    }

    $orderAmount = $order['purchase_units'][0]['amount'];
    $expectedAmount = number_format(((int) $pendingOrder['totalCents']) / 100, 2, '.', '');
    if (($orderAmount['currency_code'] ?? '') !== 'MXN'
        || (string) ($orderAmount['value'] ?? '') !== $expectedAmount) {
        throw new RuntimeException('El importe confirmado por PayPal no coincide con la compra.', 409);
    }

    $pdo = getDBConnection();
    ensureProductSchema($pdo);
    ensureInventoryMovementSchema($pdo);
    ensurePurchaseOrderSchema($pdo);
    ensureGlobalLogisticsSettingsSchema($pdo);
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pagos_paypal (
            paypal_order_id VARCHAR(64) NOT NULL PRIMARY KEY,
            importe DECIMAL(10,2) NOT NULL,
            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->beginTransaction();
    $paymentExists = $pdo->prepare('SELECT paypal_order_id FROM pagos_paypal WHERE paypal_order_id = ? FOR UPDATE');
    $paymentExists->execute([$orderId]);
    if ($paymentExists->fetchColumn()) {
        $pdo->commit();
        unset($_SESSION['paypal_orders'][$orderId]);
        paymentResponse(['success' => true, 'alreadyProcessed' => true]);
    }

    $quantitiesByProduct = [];
    foreach ($pendingOrder['items'] as $item) {
        if (!empty($item['productId'])) {
            $productId = (int) $item['productId'];
            $quantitiesByProduct[$productId] = ($quantitiesByProduct[$productId] ?? 0) + (int) $item['quantity'];
        }
    }

    $products = [];
    $selectProduct = $pdo->prepare(
        'SELECT id, name, proveedor, stock_actual, stock_maximo, estrategia_logistica
         FROM productos WHERE id = ? AND active = 1 FOR UPDATE'
    );
    foreach ($quantitiesByProduct as $productId => $quantity) {
        $selectProduct->execute([$productId]);
        $product = $selectProduct->fetch();
        if (!$product) {
            throw new RuntimeException('Un producto de la compra ya no está disponible.', 409);
        }
        if ((int) $product['stock_actual'] < $quantity) {
            throw new RuntimeException(
                'No hay existencias suficientes de ' . $product['name'] . '. Disponible: '
                    . (int) $product['stock_actual'] . '.',
                409
            );
        }
        $products[$productId] = ['product' => $product, 'quantity' => $quantity];
    }

    $orderStatus = (string) ($order['status'] ?? '');
    if ($orderStatus === 'APPROVED') {
        $capture = paypalApiRequest(
            $accessToken,
            'POST',
            '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
            null,
            'cap-' . substr(hash('sha256', $orderId), 0, 32)
        );
        $orderStatus = (string) ($capture['status'] ?? '');
    }
    if ($orderStatus !== 'COMPLETED') {
        throw new RuntimeException('PayPal no confirmó el pago. El carrito y el inventario se conservaron.', 409);
    }

    $insertPayment = $pdo->prepare('INSERT INTO pagos_paypal (paypal_order_id, importe) VALUES (?, ?)');
    $insertPayment->execute([$orderId, $expectedAmount]);
    $updateStock = $pdo->prepare('UPDATE productos SET stock_actual = ? WHERE id = ?');
    $insertMovement = $pdo->prepare(
        "INSERT INTO movimientos_inventario (producto_id, usuario_id, tipo, cantidad, motivo)
         VALUES (?, ?, 'salida', ?, ?)"
    );
    foreach ($products as $productId => $sale) {
        $product = $sale['product'];
        $quantity = $sale['quantity'];
        $newStock = (int) $product['stock_actual'] - $quantity;
        $updateStock->execute([$newStock, $productId]);
        $insertMovement->execute([
            $productId,
            (int) ($_SESSION['user_id'] ?? 0) ?: null,
            $quantity,
            'Venta PayPal ' . $orderId
        ]);
        createAutomaticRestockOrder(
            $pdo,
            $product,
            $newStock,
            (int) ($_SESSION['user_id'] ?? 0) ?: null
        );
    }

    $pdo->commit();
    unset($_SESSION['paypal_orders'][$orderId]);
    paymentResponse(['success' => true, 'inventoryUpdated' => count($products)]);
} catch (RuntimeException $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $error->getCode() >= 400 && $error->getCode() <= 599 ? $error->getCode() : 502;
    paymentResponse(['error' => $error->getMessage()], $status);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('PayPal confirmation failed: ' . $error->getMessage());
    paymentResponse(['error' => 'No se pudo confirmar la compra. El carrito se conservó. Inténtalo de nuevo.'], 500);
}
