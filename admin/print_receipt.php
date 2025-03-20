<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if payment ID is provided
if (!isset($_GET['payment_id'])) {
    echo "Payment ID is required";
    exit();
}

$payment_id = $_GET['payment_id'];

// Get payment details
try {
    // First get the order_id from the payment
    $payment_query = "SELECT order_id FROM payments WHERE payment_id = ?";
    $payment_stmt = $db->prepare($payment_query);
    $payment_stmt->execute([$payment_id]);
    $order_id = $payment_stmt->fetchColumn();
    
    if (!$order_id) {
        echo "Payment not found";
        exit();
    }
    
    // Get table number from this order
    $table_query = "SELECT t.table_number 
                    FROM orders o 
                    JOIN tables t ON o.table_id = t.id 
                    WHERE o.id = ?";
    $table_stmt = $db->prepare($table_query);
    $table_stmt->execute([$order_id]);
    $table_number = $table_stmt->fetchColumn();
    
    // Get all orders from this table that were paid in the same transaction
    $orders_query = "SELECT o.id 
                     FROM orders o 
                     JOIN tables t ON o.table_id = t.id 
                     JOIN payments p ON o.id = p.order_id 
                     WHERE t.table_number = ? 
                     AND p.payment_date = (SELECT payment_date FROM payments WHERE payment_id = ?)";
    $orders_stmt = $db->prepare($orders_query);
    $orders_stmt->execute([$table_number, $payment_id]);
    $order_ids = $orders_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get payment details for the first payment (they should all have the same cash_received and change)
    $payment_details_query = "SELECT cash_received, change_amount, payment_date 
                              FROM payments 
                              WHERE payment_id = ?";
    $payment_details_stmt = $db->prepare($payment_details_query);
    $payment_details_stmt->execute([$payment_id]);
    $payment_details = $payment_details_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment_details) {
        echo "Payment details not found";
        exit();
    }
    
    // Get all items from all orders - Fix the query to properly retrieve all items
    $items_query = "SELECT m.name, oi.quantity, m.price, oi.special_instructions
                    FROM order_items oi
                    JOIN menu_items m ON oi.menu_item_id = m.id
                    WHERE oi.order_id IN (" . implode(',', array_fill(0, count($order_ids), '?')) . ")
                    ORDER BY m.name";  // Add ordering to group similar items together
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute($order_ids);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process items and calculate totals
    $items_array = [];
    $subtotal = 0;
    
    // Debug: Print the raw items data to check what's being retrieved
    error_log("Items retrieved: " . print_r($items, true));

    foreach ($items as $item) {
        $item_total = $item['quantity'] * $item['price'];
        $subtotal += $item_total;
        
        $items_array[] = [
            'name' => $item['name'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total' => $item_total,
            'note' => $item['special_instructions']
        ];
    }
    
    // Debug: Print the processed items array
    error_log("Processed items: " . print_r($items_array, true));
    
    // Check if we have items
    if (empty($items_array)) {
        echo "No items found for this receipt";
        exit();
    }
    
    // Calculate SST and total
    $sst_amount = $subtotal * 0.06;  // Calculate 6% SST
    $total_with_sst = $subtotal + $sst_amount; // Final total with SST
    
    // Store receipt data
    $receipt_data = [
        'payment_id' => $payment_id,
        'order_ids' => $order_ids,
        'table_number' => $table_number,
        'items' => $items_array,
        'subtotal' => $subtotal,
        'sst_amount' => $sst_amount,
        'total_amount' => $total_with_sst,
        'cash_received' => $payment_details['cash_received'],
        'change_amount' => $payment_details['change_amount'],
        'payment_date' => $payment_details['payment_date']
    ];
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo str_pad($payment_id, 4, '0', STR_PAD_LEFT); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        
        .receipt-container {
            width: 80mm;
            margin: 20px auto;
            padding: 10px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-header h1 {
            font-size: 18px;
            margin: 0;
            font-weight: bold;
        }
        
        .receipt-header p {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .receipt-details {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-details p {
            margin: 3px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .receipt-items {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .item-name {
            width: 50%;
        }
        
        .item-quantity {
            width: 10%;
            text-align: center;
        }
        
        .item-price {
            width: 20%;
            text-align: right;
        }
        
        .item-total {
            width: 20%;
            text-align: right;
        }
        
        .receipt-totals {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-totals p {
            margin: 3px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .receipt-totals .total {
            font-weight: bold;
        }
        
        .receipt-payment {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-payment p {
            margin: 3px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 10px;
        }
        
        .receipt-footer p {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .print-button {
            text-align: center;
            margin: 20px 0;
        }
        
        @media print {
            .print-button {
                display: none;
            }
            
            body {
                background-color: white;
            }
            
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }

            @page {
                margin: 0;
                size: 80mm 297mm;  /* Standard thermal receipt size */
            }

            @page :first {
                margin-bottom: 0;
            }
            
            html {
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>FOOD ORDERING SYSTEM</h1>
            <p>123 Restaurant Street</p>
            <p>City, State 12345</p>
            <p>Tel: (123) 456-7890</p>
        </div>
        
        <div class="receipt-details">
            <p>
                <span>Receipt #:</span>
                <span><?php echo str_pad($payment_id, 4, '0', STR_PAD_LEFT); ?></span>
            </p>
            <p>
                <span>Order #:</span>
                <span>
                    <?php 
                    $formatted_order_ids = array_map(function($id) {
                        return str_pad($id, 4, '0', STR_PAD_LEFT);
                    }, $receipt_data['order_ids']);
                    echo implode(', ', $formatted_order_ids); 
                    ?>
                </span>
            </p>
            <p>
                <span>Table #:</span>
                <span><?php echo $receipt_data['table_number']; ?></span>
            </p>
            <p>
                <span>Date:</span>
                <span><?php echo date('d/m/Y', strtotime($receipt_data['payment_date'])); ?></span>
            </p>
            <p>
                <span>Time:</span>
                <span><?php echo date('h:i A', strtotime($receipt_data['payment_date'])); ?></span>
            </p>
        </div>
        
        <div class="receipt-items">
            <div class="receipt-item" style="font-weight: bold;">
                <div class="item-name">Item</div>
                <div class="item-quantity">Qty</div>
                <div class="item-price">Price</div>
                <div class="item-total">Total</div>
            </div>
            
            <?php 
            // Debug: Print the count of items
            echo "<!-- Items count: " . count($receipt_data['items']) . " -->";
            
            foreach ($receipt_data['items'] as $item): 
            ?>
            <div class="receipt-item">
                <div class="item-name">
                    <?php echo htmlspecialchars($item['name']); ?>
                    <?php if (!empty($item['note'])): ?>
                        <br><small class="text-muted">Note: <?php echo htmlspecialchars($item['note']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="item-quantity"><?php echo $item['quantity']; ?></div>
                <div class="item-price">RM <?php echo number_format($item['price'], 2); ?></div>
                <div class="item-total">RM <?php echo number_format($item['total'], 2); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="receipt-totals">
            <p>
                <span>Subtotal:</span>
                <span>RM <?php echo number_format($receipt_data['subtotal'], 2); ?></span>
            </p>
            <p>
                <span>SST (6%):</span>
                <span>RM <?php echo number_format($receipt_data['sst_amount'], 2); ?></span>
            </p>
            <p class="total">
                <span>Total:</span>
                <span>RM <?php echo number_format($receipt_data['total_amount'], 2); ?></span>
            </p>
        </div>
        
        <div class="receipt-payment">
            <p>
                <span>Cash:</span>
                <span>RM <?php echo number_format($receipt_data['cash_received'], 2); ?></span>
            </p>
            <p>
                <span>Change:</span>
                <span>RM <?php echo number_format($receipt_data['change_amount'], 2); ?></span>
            </p>
        </div>
        
        <div class="receipt-footer">
            <p>Thank you for your order!</p>
            <p>Please come again</p>
        </div>
    </div>
    
    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
        <a href="payment_counter.php" class="btn btn-secondary">Back to Payment Counter</a>
    </div>
    
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html> 