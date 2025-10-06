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

echo "<h1>Debug: Cancelled Orders Investigation</h1>";

// Test 1: Check cancelled orders
echo "<h2>Test 1: Cancelled Orders</h2>";
try {
    $query = "SELECT o.*, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE o.status = 'cancelled' ORDER BY o.created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($orders) . " cancelled orders<br><br>";
    
    if (!empty($orders)) {
        foreach ($orders as $order) {
            echo "<strong>Order ID: " . $order['id'] . "</strong><br>";
            echo "Table: " . $order['table_number'] . "<br>";
            echo "Status: " . $order['status'] . "<br>";
            echo "Total: " . $order['total_amount'] . "<br>";
            echo "Created: " . $order['created_at'] . "<br>";
            
            // Check order items for this order
            $items_query = "SELECT oi.*, mi.name as item_name FROM order_items oi LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?";
            $items_stmt = $db->prepare($items_query);
            $items_stmt->execute([$order['id']]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Order Items: " . count($items) . "<br>";
            if (!empty($items)) {
                echo "<ul>";
                foreach ($items as $item) {
                    echo "<li>" . $item['item_name'] . " (Qty: " . $item['quantity'] . ", Price: " . $item['price'] . ")";
                    if (!empty($item['special_instructions'])) {
                        echo " - <strong>Instructions:</strong> " . $item['special_instructions'];
                    }
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<span style='color: red;'>NO ITEMS FOUND FOR THIS ORDER!</span><br>";
            }
            echo "<hr>";
        }
    } else {
        echo "<span style='color: red;'>NO CANCELLED ORDERS FOUND!</span><br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 2: Check all orders with their status
echo "<h2>Test 2: All Orders by Status</h2>";
try {
    $query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    foreach ($statuses as $status) {
        echo "<tr><td>" . $status['status'] . "</td><td>" . $status['count'] . "</td></tr>";
    }
    echo "</table><br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 3: Check order_items table
echo "<h2>Test 3: Order Items Table</h2>";
try {
    $query = "SELECT COUNT(*) as count FROM order_items";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total order items in database: " . $result['count'] . "<br>";
    
    if ($result['count'] > 0) {
        // Get sample order items
        $query = "SELECT oi.*, mi.name as item_name, o.status as order_status 
                  FROM order_items oi 
                  LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id 
                  LEFT JOIN orders o ON oi.order_id = o.id 
                  ORDER BY oi.id DESC LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Order Items:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Order ID</th><th>Item Name</th><th>Quantity</th><th>Price</th><th>Order Status</th></tr>";
        foreach ($items as $item) {
            echo "<tr>";
            echo "<td>" . $item['order_id'] . "</td>";
            echo "<td>" . $item['item_name'] . "</td>";
            echo "<td>" . $item['quantity'] . "</td>";
            echo "<td>" . $item['price'] . "</td>";
            echo "<td>" . $item['order_status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 4: Test the getCancelledOrders method
echo "<h2>Test 4: getCancelledOrders Method</h2>";
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

echo "<h1>Debug: Cancelled Orders Investigation</h1>";

// Test 1: Check cancelled orders
echo "<h2>Test 1: Cancelled Orders</h2>";
try {
    $query = "SELECT o.*, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE o.status = 'cancelled' ORDER BY o.created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($orders) . " cancelled orders<br><br>";
    
    if (!empty($orders)) {
        foreach ($orders as $order) {
            echo "<strong>Order ID: " . $order['id'] . "</strong><br>";
            echo "Table: " . $order['table_number'] . "<br>";
            echo "Status: " . $order['status'] . "<br>";
            echo "Total: " . $order['total_amount'] . "<br>";
            echo "Created: " . $order['created_at'] . "<br>";
            
            // Check order items for this order
            $items_query = "SELECT oi.*, mi.name as item_name FROM order_items oi LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?";
            $items_stmt = $db->prepare($items_query);
            $items_stmt->execute([$order['id']]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Order Items: " . count($items) . "<br>";
            if (!empty($items)) {
                echo "<ul>";
                foreach ($items as $item) {
                    echo "<li>" . $item['item_name'] . " (Qty: " . $item['quantity'] . ", Price: " . $item['price'] . ")";
                    if (!empty($item['special_instructions'])) {
                        echo " - <strong>Instructions:</strong> " . $item['special_instructions'];
                    }
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<span style='color: red;'>NO ITEMS FOUND FOR THIS ORDER!</span><br>";
            }
            echo "<hr>";
        }
    } else {
        echo "<span style='color: red;'>NO CANCELLED ORDERS FOUND!</span><br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 2: Check all orders with their status
echo "<h2>Test 2: All Orders by Status</h2>";
try {
    $query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    foreach ($statuses as $status) {
        echo "<tr><td>" . $status['status'] . "</td><td>" . $status['count'] . "</td></tr>";
    }
    echo "</table><br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 3: Check order_items table
echo "<h2>Test 3: Order Items Table</h2>";
try {
    $query = "SELECT COUNT(*) as count FROM order_items";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total order items in database: " . $result['count'] . "<br>";
    
    if ($result['count'] > 0) {
        // Get sample order items
        $query = "SELECT oi.*, mi.name as item_name, o.status as order_status 
                  FROM order_items oi 
                  LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id 
                  LEFT JOIN orders o ON oi.order_id = o.id 
                  ORDER BY oi.id DESC LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Order Items:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Order ID</th><th>Item Name</th><th>Quantity</th><th>Price</th><th>Order Status</th></tr>";
        foreach ($items as $item) {
            echo "<tr>";
            echo "<td>" . $item['order_id'] . "</td>";
            echo "<td>" . $item['item_name'] . "</td>";
            echo "<td>" . $item['quantity'] . "</td>";
            echo "<td>" . $item['price'] . "</td>";
            echo "<td>" . $item['order_status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 4: Test the getCancelledOrders method
echo "<h2>Test 4: getCancelledOrders Method</h2>";
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

echo "<br><a href='cancelled_orders.php'>Back to Cancelled Orders Page</a>";
?>
