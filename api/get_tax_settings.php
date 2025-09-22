<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    $systemSettings = new SystemSettings($db);
    
    $response = [
        'success' => true,
        'data' => [
            'tax_rate' => $systemSettings->getTaxRatePercent(),
            'tax_name' => $systemSettings->getTaxName(),
            'currency_symbol' => $systemSettings->getCurrencySymbol(),
            'currency_code' => $systemSettings->getCurrencyCode(),
            'restaurant_name' => $systemSettings->getRestaurantName()
        ]
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get tax settings',
        'message' => $e->getMessage()
    ]);
}
?>
