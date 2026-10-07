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
if (!is_array($input) || !isset($input['items']) || !is_array($input['items'])
    || count($input['items']) === 0 || count($input['items']) > 100
    || !hash_equals((string) ($_SESSION['paypal_csrf'] ?? ''), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    paymentResponse(['error' => 'El carrito de compra no es válido. Actualiza la página e inténtalo de nuevo.'], 400);
}

try {
    $pdo = getDBConnection();
    ensureProductSchema($pdo);
    $normalizedItems = [];
    $paypalItems = [];
    $quantitiesByProduct = [];
    $subtotalCents = 0;

    foreach ($input['items'] as $item) {
        if (!is_array($item)) {
            paymentResponse(['error' => 'Hay un producto inválido en el carrito.'], 400);
        }

        $quantity = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $hasProductId = array_key_exists('productId', $item);
        $productId = $hasProductId ? filter_var($item['productId'], FILTER_VALIDATE_INT) : null;
        if ($quantity === false || $quantity < 1 || $quantity > 100) {
            paymentResponse(['error' => 'La cantidad de un producto no es válida.'], 400);
        }

        $name = trim((string) ($item['producto'] ?? 'Producto'));
        $description = trim((string) ($item['descripcion'] ?? $name));
        $price = filter_var($item['precio'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 127
            || mb_strlen($description, 'UTF-8') > 127
            || $price === false || $price <= 0) {
            paymentResponse(['error' => 'El nombre, descripción o precio de un producto no es válido.'], 400);
        }

        $managedProductId = null;
        if ($hasProductId) {
            if ($productId === false || $productId === null || $productId <= 0) {
                paymentResponse(['error' => 'El identificador de un producto no es válido.'], 400);
            }
        }
        $productStmt = $pdo->prepare(
            $hasProductId
                ? 'SELECT id, name, description, price, active FROM productos WHERE id = ?'
                : 'SELECT id, name, description, price, active FROM productos WHERE name = ? AND active = 1 LIMIT 1'
        );
        $productStmt->execute([$hasProductId ? $productId : $name]);
        $product = $productStmt->fetch();
        if ($product && (int) $product['active'] === 1) {
            $databasePriceCents = (int) round((float) $product['price'] * 100);
            if ((int) round($price * 100) !== $databasePriceCents) {
                paymentResponse(['error' => 'El precio de un producto cambió. Actualiza el carrito antes de pagar.'], 409);
            }
            $managedProductId = (int) $product['id'];
            $name = (string) $product['name'];
            $description = mb_substr((string) ($product['description'] ?? $name), 0, 127, 'UTF-8');
            $quantitiesByProduct[$managedProductId] = ($quantitiesByProduct[$managedProductId] ?? 0) + $quantity;
            $price = (float) $product['price'];
        } elseif ($hasProductId) {
            paymentResponse(['error' => 'Un producto del carrito ya no está disponible.'], 409);
        }

        $priceCents = (int) round($price * 100);
        $subtotalCents += $priceCents * $quantity;
        $paypalItem = [
            'name' => $name,
            'description' => $description,
            'quantity' => (string) $quantity,
            'unit_amount' => ['currency_code' => 'MXN', 'value' => number_format($priceCents / 100, 2, '.', '')]
        ];
        if ($managedProductId !== null) {
            $paypalItem['sku'] = (string) $managedProductId;
        }
        $paypalItems[] = $paypalItem;
        $normalizedItems[] = [
            'productId' => $managedProductId,
            'quantity' => $quantity,
            'priceCents' => $priceCents
        ];
    }

    foreach ($quantitiesByProduct as $productId => $quantity) {
        $stockStmt = $pdo->prepare('SELECT stock_actual, name FROM productos WHERE id = ? AND active = 1');
        $stockStmt->execute([$productId]);
        $product = $stockStmt->fetch();
        if (!$product || (int) $product['stock_actual'] < $quantity) {
            paymentResponse([
                'error' => 'No hay suficientes existencias de ' . ($product['name'] ?? 'un producto')
                    . '. Disponible: ' . (int) ($product['stock_actual'] ?? 0) . '.'
            ], 409);
        }
    }

    $shippingCents = $subtotalCents >= 50000 ? 0 : 8000;
    $totalCents = $subtotalCents + $shippingCents;
    $orderBody = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => 'MXN',
                'value' => number_format($totalCents / 100, 2, '.', ''),
                'breakdown' => [
                    'item_total' => ['currency_code' => 'MXN', 'value' => number_format($subtotalCents / 100, 2, '.', '')],
                    'shipping' => ['currency_code' => 'MXN', 'value' => number_format($shippingCents / 100, 2, '.', '')]
                ]
            ],
            'items' => $paypalItems
        ]]
    ];
    $accessToken = paypalAccessToken();
    $order = paypalApiRequest($accessToken, 'POST', '/v2/checkout/orders', $orderBody);
    if (empty($order['id']) || ($order['status'] ?? '') !== 'CREATED') {
        throw new RuntimeException('PayPal no devolvió una orden válida.');
    }

    if (!isset($_SESSION['paypal_orders']) || !is_array($_SESSION['paypal_orders'])) {
        $_SESSION['paypal_orders'] = [];
    }
    foreach ($_SESSION['paypal_orders'] as $id => $storedOrder) {
        if ((int) ($storedOrder['createdAt'] ?? 0) < time() - 3600) {
            unset($_SESSION['paypal_orders'][$id]);
        }
    }
    $_SESSION['paypal_orders'][$order['id']] = [
        'items' => $normalizedItems,
        'totalCents' => $totalCents,
        'createdAt' => time()
    ];

    paymentResponse(['success' => true, 'orderID' => $order['id']]);
} catch (RuntimeException $error) {
    paymentResponse(['error' => $error->getMessage()], 502);
} catch (Throwable $error) {
    error_log('PayPal order creation failed: ' . $error->getMessage());
    paymentResponse(['error' => 'No se pudo crear la orden de pago. Inténtalo de nuevo.'], 500);
}
