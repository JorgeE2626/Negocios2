<?php

function paypalConfiguration(): array
{
    $clientId = trim((string) getenv('PAYPAL_CLIENT_ID'));
    $clientSecret = trim((string) getenv('PAYPAL_CLIENT_SECRET'));
    $mode = strtolower(trim((string) getenv('PAYPAL_MODE')));
    if ($mode === '') {
        $mode = 'sandbox';
    }

    if ($clientId === '' || $clientSecret === '') {
        throw new RuntimeException('PayPal no está configurado. Define PAYPAL_CLIENT_ID y PAYPAL_CLIENT_SECRET.');
    }
    if (!in_array($mode, ['sandbox', 'live'], true)) {
        throw new RuntimeException('PAYPAL_MODE debe ser sandbox o live.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL de PHP es necesaria para procesar pagos.');
    }

    return [
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'apiUrl' => $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com'
    ];
}

function paypalAccessToken(): string
{
    $configuration = paypalConfiguration();
    $curl = curl_init($configuration['apiUrl'] . '/v1/oauth2/token');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
        CURLOPT_USERPWD => $configuration['clientId'] . ':' . $configuration['clientSecret'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $data = is_string($response) ? json_decode($response, true) : null;
    if ($response === false || $status < 200 || $status >= 300
        || !is_array($data) || empty($data['access_token'])) {
        error_log('PayPal OAuth failed with HTTP ' . $status . ': ' . $curlError);
        throw new RuntimeException('No se pudo autenticar con PayPal.');
    }

    return (string) $data['access_token'];
}

function paypalApiRequest(string $accessToken, string $method, string $path, ?array $body = null, ?string $requestId = null): array
{
    $configuration = paypalConfiguration();
    $curl = curl_init($configuration['apiUrl'] . $path);
    $headers = ['Accept: application/json', 'Authorization: Bearer ' . $accessToken];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($requestId !== null) {
        $headers[] = 'PayPal-Request-Id: ' . $requestId;
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        error_log('PayPal API connection failed: ' . $curlError);
        throw new RuntimeException('No se pudo conectar con PayPal.');
    }

    $data = json_decode($response, true);
    if (!is_array($data) || $status < 200 || $status >= 300) {
        error_log('PayPal API returned HTTP ' . $status . ': ' . substr($response, 0, 1000));
        throw new RuntimeException('PayPal no pudo procesar la solicitud.');
    }

    return $data;
}
