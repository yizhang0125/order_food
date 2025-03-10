<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['table_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Table ID required']);
    exit();
}

try {
    $tableId = $_GET['table_id'];
    $newToken = bin2hex(random_bytes(16));
    
    // Update or insert new token
    $query = "INSERT INTO table_tokens (table_id, token) 
              VALUES (:table_id, :token) 
              ON DUPLICATE KEY UPDATE token = :token";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':table_id', $tableId);
    $stmt->bindParam(':token', $newToken);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'token' => $newToken]);
    } else {
        throw new Exception('Failed to update token');
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 