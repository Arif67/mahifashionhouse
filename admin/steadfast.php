<?php
// ============================================
// Steadfast Courier API Integration
// ============================================

define('STEADFAST_API_KEY',    'vro8fvefldpcntyz7jlx7ndo1pmbls9y');
define('STEADFAST_SECRET_KEY', 'j7zmhzntohpfn2jstlxayw8e');
define('STEADFAST_BASE_URL',   'https://portal.steadfast.com.bd/api/v1');

/**
 * Steadfast API Call Helper
 */
function steadfastApiCall($endpoint, $method = 'GET', $data = []) {
    $url = STEADFAST_BASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = [
        'Api-Key: ' . STEADFAST_API_KEY,
        'Secret-Key: ' . STEADFAST_SECRET_KEY,
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['error' => true, 'message' => 'CURL Error: ' . $curlError];
    }
    
    $decoded = json_decode($response, true);
    
    if ($httpCode !== 200 || !isset($decoded['status']) || $decoded['status'] !== 200) {
        return [
            'error' => true,
            'http_code' => $httpCode,
            'message' => $decoded['message'] ?? 'API request failed',
            'raw_response' => $response
        ];
    }
    
    return ['error' => false, 'data' => $decoded];
}

/**
 * Create new consignment in Steadfast
 */
function createSteadfastConsignment($orderData) {
    $payload = [
        'invoice'           => $orderData['order_number'],
        'recipient_name'    => $orderData['customer_name'],
        'recipient_phone'   => $orderData['customer_phone'],
        'recipient_address' => $orderData['customer_address'] . ', ' . ($orderData['customer_district'] ?? ''),
        'cod_amount'        => floatval($orderData['total_amount']),
        'note'              => !empty($orderData['customer_note']) ? $orderData['customer_note'] : 'Order from Mahi Fashion House'
    ];
    
    return steadfastApiCall('/create_order', 'POST', $payload);
}

/**
 * Check consignment status by ID
 */
function getSteadfastStatus($consignmentId) {
    return steadfastApiCall('/status_by_cid?consignment_id=' . urlencode($consignmentId));
}

/**
 * Check consignment status by Invoice
 */
function getSteadfastStatusByInvoice($invoice) {
    return steadfastApiCall('/status_by_invoice?invoice=' . urlencode($invoice));
}