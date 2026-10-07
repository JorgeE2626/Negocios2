<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/paypal.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit();
}

try {
    $configuration = paypalConfiguration();
    if (empty($_SESSION['paypal_csrf'])) {
        $_SESSION['paypal_csrf'] = bin2hex(random_bytes(32));
    }

    echo json_encode([
        'success' => true,
        'clientId' => $configuration['clientId'],
        'csrfToken' => $_SESSION['paypal_csrf']
    ]);
} catch (RuntimeException $error) {
    http_response_code(503);
    echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('PayPal configuration error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo preparar la configuración de pago.']);
}
