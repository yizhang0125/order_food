<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    die('Please log in first');
}

echo "<h1>Debug: Cancelled Orders Test</h1>";

// Test 1: Check if there are any cancelled orders in the database
echo "<h2>Test 1: Direct Database Query</h2>";
try {
    $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'cancelled'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total cancelled orders in database: " . $result['count'] . "<br>";
    
    if ($result['count'] > 0) {
        // Get some sample cancelled orders
        $query = "SELECT o.*, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE o.status = 'cancelled' ORDER BY o.created_at DESC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Cancelled Orders:</h3>";
        echo "<pre>";
        foreach ($orders as $order) {
            echo "Order ID: " . $order['id'] . "\n";
            echo "Table: " . $order['table_number'] . "\n";
            echo "Status: " . $order['status'] . "\n";
            echo "Total: " . $order['total_amount'] . "\n";
            echo "Created: " . $order['created_at'] . "\n";
            echo "Cancelled At: " . (isset($order['cancelled_at']) ? $order['cancelled_at'] : 'NULL (using created_at)') . "\n";
            echo "Cancel Reason: " . (isset($order['cancel_reason']) ? $order['cancel_reason'] : 'NULL (using default)') . "\n";
            echo "---\n";
        }
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 2: Check if there are order items for cancelled orders
echo "<h2>Test 2: Order Items for Cancelled Orders</h2>";
try {
    $query = "SELECT oi.*, mi.name as item_name, o.id as order_id, o.status 
              FROM order_items oi 
              JOIN menu_items mi ON oi.menu_item_id = mi.id 
              JOIN orders o ON oi.order_id = o.id 
              WHERE o.status = 'cancelled' 
              ORDER BY o.created_at DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Order items for cancelled orders: " . count($items) . "<br>";
    
    if (!empty($items)) {
        echo "<h3>Sample Order Items:</h3>";
        echo "<pre>";
        foreach ($items as $item) {
            echo "Order ID: " . $item['order_id'] . "\n";
            echo "Item: " . $item['item_name'] . "\n";
            echo "Quantity: " . $item['quantity'] . "\n";
            echo "Price: " . $item['price'] . "\n";
            echo "Instructions: " . ($item['special_instructions'] ?? 'None') . "\n";
            echo "---\n";
        }
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 3: Test the getCancelledOrders method
echo "<h2>Test 3: getCancelledOrders Method</h2>";
try {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
    
    echo "Testing with date range: $start_date to $end_date<br>";
    
    $cancelled_orders = $orderModel->getCancelledOrders($start_date, $end_date);
    
    echo "Method returned: " . count($cancelled_orders) . " orders<br>";
    
    if (!empty($cancelled_orders)) {
        echo "<h3>First Order Details:</h3>";
        echo "<pre>";
        print_r($cancelled_orders[0]);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 4: Check database schema
echo "<h2>Test 4: Database Schema Check</h2>";
try {
    $query = "DESCRIBE orders";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Orders Table Structure:</h3>";
    echo "<pre>";
    foreach ($columns as $column) {
        echo $column['Field'] . " - " . $column['Type'] . " - " . $column['Null'] . " - " . $column['Default'] . "\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='cancelled_orders.php'>Back to Cancelled Orders Page</a>";
?>

require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    die('Please log in first');
}

echo "<h1>Debug: Cancelled Orders Test</h1>";

// Test 1: Check if there are any cancelled orders in the database
echo "<h2>Test 1: Direct Database Query</h2>";
try {
    $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'cancelled'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total cancelled orders in database: " . $result['count'] . "<br>";
    
    if ($result['count'] > 0) {
        // Get some sample cancelled orders
        $query = "SELECT o.*, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE o.status = 'cancelled' ORDER BY o.created_at DESC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Cancelled Orders:</h3>";
        echo "<pre>";
        foreach ($orders as $order) {
            echo "Order ID: " . $order['id'] . "\n";
            echo "Table: " . $order['table_number'] . "\n";
            echo "Status: " . $order['status'] . "\n";
            echo "Total: " . $order['total_amount'] . "\n";
            echo "Created: " . $order['created_at'] . "\n";
            echo "Cancelled At: " . (isset($order['cancelled_at']) ? $order['cancelled_at'] : 'NULL (using created_at)') . "\n";
            echo "Cancel Reason: " . (isset($order['cancel_reason']) ? $order['cancel_reason'] : 'NULL (using default)') . "\n";
            echo "---\n";
        }
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 2: Check if there are order items for cancelled orders
echo "<h2>Test 2: Order Items for Cancelled Orders</h2>";
try {
    $query = "SELECT oi.*, mi.name as item_name, o.id as order_id, o.status 
              FROM order_items oi 
              JOIN menu_items mi ON oi.menu_item_id = mi.id 
              JOIN orders o ON oi.order_id = o.id 
              WHERE o.status = 'cancelled' 
              ORDER BY o.created_at DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Order items for cancelled orders: " . count($items) . "<br>";
    
    if (!empty($items)) {
        echo "<h3>Sample Order Items:</h3>";
        echo "<pre>";
        foreach ($items as $item) {
            echo "Order ID: " . $item['order_id'] . "\n";
            echo "Item: " . $item['item_name'] . "\n";
            echo "Quantity: " . $item['quantity'] . "\n";
            echo "Price: " . $item['price'] . "\n";
            echo "Instructions: " . ($item['special_instructions'] ?? 'None') . "\n";
            echo "---\n";
        }
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 3: Test the getCancelledOrders method
echo "<h2>Test 3: getCancelledOrders Method</h2>";
try {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
    
    echo "Testing with date range: $start_date to $end_date<br>";
    
    $cancelled_orders = $orderModel->getCancelledOrders($start_date, $end_date);
    
    echo "Method returned: " . count($cancelled_orders) . " orders<br>";
    
    if (!empty($cancelled_orders)) {
        echo "<h3>First Order Details:</h3>";
        echo "<pre>";
        print_r($cancelled_orders[0]);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 4: Check database schema
echo "<h2>Test 4: Database Schema Check</h2>";
try {
    $query = "DESCRIBE orders";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Orders Table Structure:</h3>";
    echo "<pre>";
    foreach ($columns as $column) {
        echo $column['Field'] . " - " . $column['Type'] . " - " . $column['Null'] . " - " . $column['Default'] . "\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='cancelled_orders.php'>Back to Cancelled Orders Page</a>";
?>
