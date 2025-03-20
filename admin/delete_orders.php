<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if it's a POST request and has order IDs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_ids'])) {
    try {
        $order_ids = $_POST['order_ids'];
        
        // Start transaction
        $db->beginTransaction();
        
        // Delete related records first (due to foreign key constraints)
        // Delete from order_items
        $delete_items = $db->prepare("DELETE FROM order_items WHERE order_id IN (" . str_repeat('?,', count($order_ids) - 1) . "?)");
        $delete_items->execute($order_ids);
        
        // Delete from payments
        $delete_payments = $db->prepare("DELETE FROM payments WHERE order_id IN (" . str_repeat('?,', count($order_ids) - 1) . "?)");
        $delete_payments->execute($order_ids);
        
        // Finally delete the orders
        $delete_orders = $db->prepare("DELETE FROM orders WHERE id IN (" . str_repeat('?,', count($order_ids) - 1) . "?)");
        $delete_orders->execute($order_ids);
        
        // Commit transaction
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => 'Orders deleted successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 