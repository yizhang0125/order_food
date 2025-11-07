<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$systemSettings = new SystemSettings($db);

// Cash rounding function - rounds to nearest 0.05 (5 cents)
if (!function_exists('customRound')) {
    function customRound($amount) {
        // Round to nearest 0.05 (5 cents) for cash transactions
        // Multiply by 20, round to nearest integer, then divide by 20
        return round($amount * 20) / 20;
    }
}

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

    // Get all orders that were paid in the same transaction (handles merged bills)
    $orders_query = "SELECT o.id, t.table_number
                     FROM orders o 
                     JOIN tables t ON o.table_id = t.id 
                     JOIN payments p ON o.id = p.order_id 
                     WHERE p.payment_date = (SELECT payment_date FROM payments WHERE payment_id = ?)
                     ORDER BY t.table_number, o.id";
    $orders_stmt = $db->prepare($orders_query);
    $orders_stmt->execute([$payment_id]);
    $order_data = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract order IDs and table numbers
    $order_ids = array_column($order_data, 'id');
    $table_numbers = array_unique(array_column($order_data, 'table_number'));
    $is_merged = count($table_numbers) > 1;
    
    // Get payment details for the first payment (they should all have the same cash_received and change)
    // Try to get payment method columns, fallback to basic columns if they don't exist
    try {
        $payment_details_query = "SELECT cash_received, change_amount, payment_date, processed_by_name, payment_method, tng_reference, discount_amount, discount_type, discount_reason
                                  FROM payments 
                                  WHERE payment_id = ?";
        $payment_details_stmt = $db->prepare($payment_details_query);
        $payment_details_stmt->execute([$payment_id]);
        $payment_details = $payment_details_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback for when payment_method column doesn't exist yet
        $payment_details_query = "SELECT cash_received, change_amount, payment_date, processed_by_name 
                                  FROM payments 
                                  WHERE payment_id = ?";
        $payment_details_stmt = $db->prepare($payment_details_query);
        $payment_details_stmt->execute([$payment_id]);
        $payment_details = $payment_details_stmt->fetch(PDO::FETCH_ASSOC);
        $payment_details['payment_method'] = 'cash'; // Default to cash
        $payment_details['tng_reference'] = null;
        $payment_details['discount_amount'] = 0;
        $payment_details['discount_type'] = null;
        $payment_details['discount_reason'] = null;
    }
    
    // Get discount information from session if available
    $discount_info = null;
    if (isset($_SESSION['discount_info'])) {
        $discount_info = $_SESSION['discount_info'];
        // Clear the session data after retrieving
        unset($_SESSION['discount_info']);
    }
    
    if (!$payment_details) {
        echo "Payment details not found";
        exit();
    }

    // Get all items from all orders - Fix the query to properly retrieve all items
    $items_query = "SELECT m.name, oi.quantity, m.price, oi.special_instructions, t.table_number
                    FROM order_items oi
                    JOIN menu_items m ON oi.menu_item_id = m.id
                    JOIN orders o ON oi.order_id = o.id
                    JOIN tables t ON o.table_id = t.id
                    WHERE oi.order_id IN (" . implode(',', array_fill(0, count($order_ids), '?')) . ")
                    ORDER BY t.table_number, m.name";  // Add ordering to group by table and item
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute($order_ids);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process items and calculate totals
    $items_array = [];
    $subtotal = 0;
    
    // Group items by table for merged bills
    $items_by_table = [];
    
    foreach ($items as $item) {
        $item_total = $item['quantity'] * $item['price'];
        $subtotal += $item_total;
        
        $item_data = [
            'name' => $item['name'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total' => $item_total,
            'note' => $item['special_instructions']
        ];
        
        if ($is_merged) {
            $table_num = $item['table_number'];
            if (!isset($items_by_table[$table_num])) {
                $items_by_table[$table_num] = [];
            }
            $items_by_table[$table_num][] = $item_data;
        }
        
        $items_array[] = $item_data;
    }
    
    // Check if we have items
    if (empty($items_array)) {
        echo "No items found for this receipt";
        exit();
    }
    
    // Calculate tax and service tax using dynamic settings
    $tax_rate = $systemSettings->getTaxRate();
    $service_tax_rate = $systemSettings->getServiceTaxRate();
    $tax_amount = $subtotal * $tax_rate;
    $service_tax_amount = $subtotal * $service_tax_rate;
    $total_with_tax = $subtotal + $tax_amount + $service_tax_amount;
    
    // Apply custom rounding to total
    $total_with_tax = customRound($total_with_tax);
    
    // Calculate discount information
    $discount_amount = 0;
    $discount_type = null;
    $discount_reason = null;
    
    // Get discount from payment details or session
    if (isset($payment_details['discount_amount']) && $payment_details['discount_amount'] > 0) {
        $discount_amount = $payment_details['discount_amount'];
        $discount_type = $payment_details['discount_type'] ?? null;
        $discount_reason = $payment_details['discount_reason'] ?? null;
    } elseif ($discount_info) {
        $discount_amount = $discount_info['amount'] ?? 0;
        $discount_type = $discount_info['type'] ?? null;
        $discount_reason = $discount_info['reason'] ?? null;
    }
    
    // Calculate original total (before discount)
    $original_total = $total_with_tax;
    
    // Apply discount to total
    if ($discount_amount > 0) {
        $total_with_tax = $total_with_tax - $discount_amount;
        // Apply custom rounding to discounted total
        $total_with_tax = customRound($total_with_tax);
    }
    
    // Recalculate cash received and change based on discounted total
    $cash_received = customRound($payment_details['cash_received']);
    $change_amount = customRound($payment_details['change_amount']);
    
    // If discount was applied, recalculate change based on discounted total
    if ($discount_amount > 0) {
        $change_amount = customRound($cash_received - $total_with_tax);
    }
    
    // Store receipt data
    $receipt_data = [
        'payment_id' => $payment_id,
        'order_ids' => $order_ids,
        'table_number' => $table_numbers[0], // First table for backward compatibility
        'table_numbers' => $table_numbers,
        'is_merged' => $is_merged,
        'items' => $items_array,
        'items_by_table' => $items_by_table,
        'subtotal' => round($subtotal, 2),
        'tax_amount' => round($tax_amount, 2),
        'service_tax_amount' => round($service_tax_amount, 2),
        'total_amount' => round($total_with_tax, 2),
        'original_total' => round($original_total, 2),
        'discount_amount' => round($discount_amount, 2),
        'discount_type' => $discount_type,
        'discount_reason' => $discount_reason,
        'cash_received' => $cash_received,
        'change_amount' => $change_amount,
        'payment_date' => $payment_details['payment_date'],
        'processed_by_name' => $payment_details['processed_by_name'],
        'payment_method' => $payment_details['payment_method'] ?? 'cash',
        'tng_reference' => $payment_details['tng_reference'] ?? null
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 0;
            padding: 0 0 100px 0; /* Add bottom padding for fixed buttons */
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
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        
        .receipt-header p {
            margin: 5px 0;
            font-size: 16px;
        }
        
        .receipt-details {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-details p {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            line-height: 1.4;
        }
        
        .receipt-items {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
            line-height: 1.4;
        }
        
        .item-name {
            width: 50%;
            font-weight: 600;
        }
        
        .item-quantity {
            width: 10%;
            text-align: center;
            font-weight: 600;
        }
        
        .item-price {
            width: 20%;
            text-align: right;
            font-weight: 600;
        }
        
        .item-total {
            width: 20%;
            text-align: right;
            font-weight: 600;
        }
        
        
        .receipt-totals {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-totals p {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            line-height: 1.4;
        }
        
        .receipt-totals .total {
            font-weight: bold;
            font-size: 22px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }
        
        .receipt-payment {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #000;
        }
        
        .receipt-payment p {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            line-height: 1.4;
            font-weight: 600;
        }
        
        .payment-method-info {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin: 5px 0;
        }
        
        .payment-method-info p {
            margin: 4px 0;
            font-size: 16px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
        }
        
        .receipt-footer p {
            margin: 8px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .page-controls {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #f8f9fa;
            border-top: 2px solid #dee2e6;
            z-index: 1000;
        }
        
        .page-controls .btn {
            margin: 0 10px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .page-controls .btn-primary {
            background-color: #007bff;
            border: 2px solid #007bff;
            color: white;
        }
        
        .page-controls .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }
        
        .page-controls .btn-secondary {
            background-color: #6c757d;
            border: 2px solid #6c757d;
            color: white;
        }
        
        .page-controls .btn-secondary:hover {
            background-color: #545b62;
            border-color: #545b62;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        
        .page-controls .btn-danger {
            background-color: #dc3545;
            border: 2px solid #dc3545;
            color: white;
        }
        
        .page-controls .btn-danger:hover {
            background-color: #c82333;
            border-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }
        
        .auto-print-mode .page-controls {
            display: none;
        }
        
        .auto-print-mode .receipt-container {
            margin: 0 auto;
        }
        
        /* Floating Action Buttons */
        .floating-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .floating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }
        
        .floating-btn.print {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        
        .floating-btn.close {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .floating-btn:hover.print {
            background: linear-gradient(135deg, #0056b3, #004085);
        }
        
        .floating-btn:hover.close {
            background: linear-gradient(135deg, #c82333, #a71e2a);
        }
        
        .printing-indicator {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            font-size: 18px;
            z-index: 9999;
            display: none;
        }
        
        .auto-print-mode .printing-indicator {
            display: block;
        }
        
        .printing-indicator .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media print {
            .page-controls {
                display: none !important;
            }
            
            .floating-actions {
                display: none !important;
            }
            
            body {
                background-color: white;
                padding: 0; /* Remove bottom padding when printing */
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
        
        /* Mobile responsiveness for floating buttons */
        @media (max-width: 768px) {
            .floating-actions {
                top: 10px;
                right: 10px;
                gap: 8px;
            }
            
            .floating-btn {
                width: 50px;
                height: 50px;
                font-size: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .floating-actions {
                top: 5px;
                right: 5px;
                gap: 6px;
            }
            
            .floating-btn {
                width: 45px;
                height: 45px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Action Buttons -->
    <div class="floating-actions">
        <button class="floating-btn print" onclick="quickPrint()" title="Print Receipt">
            <i class="fas fa-print"></i>
        </button>
        <button class="floating-btn close" onclick="quickClose()" title="Close Window">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="printing-indicator">
        <div class="spinner"></div>
        Preparing receipt for printing...
    </div>
    
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
                <span><?php echo $receipt_data['is_merged'] ? 'Tables #:' : 'Table #:'; ?></span>
                <span><?php echo $receipt_data['is_merged'] ? implode(', ', $receipt_data['table_numbers']) : $receipt_data['table_number']; ?></span>
            </p>
            <p>
                <span>Date:</span>
                <span><?php echo date('d/m/Y', strtotime($receipt_data['payment_date'])); ?></span>
            </p>
            <p>
                <span>Time:</span>
                <span><?php echo date('h:i A', strtotime($receipt_data['payment_date'])); ?></span>
            </p>
            <p>
                <span>Payment By:</span>
                <span><?php echo htmlspecialchars($receipt_data['processed_by_name']); ?></span>
            </p>
            <p>
                <span>Payment Method:</span>
                <span>
                    <?php 
                    $payment_method = $receipt_data['payment_method'];
                    if ($payment_method === 'tng_pay') {
                        echo 'TNG Pay';
                    } else {
                        echo 'Cash';
                    }
                    ?>
                </span>
            </p>
            <?php if ($receipt_data['payment_method'] === 'tng_pay' && !empty($receipt_data['tng_reference'])): ?>
            <p>
                <span>TNG Reference:</span>
                <span><?php echo htmlspecialchars($receipt_data['tng_reference']); ?></span>
            </p>
            <?php endif; ?>
        </div>
        
        <div class="receipt-items">
            <div class="receipt-item" style="font-weight: bold;">
                <div class="item-name">Item</div>
                <div class="item-quantity">Qty</div>
                <div class="item-price">Price</div>
                <div class="item-total">Total</div>
            </div>
            
            <?php 
            // Combine all items from all tables into one unified list with table info
            $all_items = [];
            
            if ($receipt_data['is_merged']) {
                // For merged bills, combine all items from all tables with table number
                foreach ($receipt_data['items_by_table'] as $table_num => $table_items) {
                    foreach ($table_items as $item) {
                        $item['table_number'] = $table_num;
                        $all_items[] = $item;
                    }
                }
            } else {
                // For single table, use existing items with table number
                foreach ($receipt_data['items'] as $item) {
                    $item['table_number'] = $receipt_data['table_number'];
                    $all_items[] = $item;
                }
            }
            
            // Group items by table number
            $items_by_table = [];
            foreach ($all_items as $item) {
                $table_num = $item['table_number'];
                if (!isset($items_by_table[$table_num])) {
                    $items_by_table[$table_num] = [];
                }
                $items_by_table[$table_num][] = $item;
            }
            
            // Sort tables by number (1, 2, 3, etc.)
            ksort($items_by_table);
            
            // Display all items grouped by table
            foreach ($items_by_table as $table_num => $table_items): 
                // Show table number header
            ?>
            <div class="receipt-item" style="background-color: #f8f9fa; padding: 8px; margin: 10px 0 5px 0; border-radius: 4px;">
                <div class="item-name" style="font-weight: bold; color: #007bff;">
                    Table <?php echo $table_num; ?>
                        </div>
                <div class="item-quantity"></div>
                <div class="item-price"></div>
                <div class="item-total"></div>
                    </div>
            <?php
                // Show all items for this table
                foreach ($table_items as $item): 
            ?>
                <div class="receipt-item">
                    <div class="item-name">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </div>
                    <div class="item-quantity"><?php echo $item['quantity']; ?></div>
                    <div class="item-price"><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($item['price'], 2); ?></div>
                    <div class="item-total"><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($item['total'], 2); ?></div>
                </div>
            <?php 
                endforeach; 
            endforeach; 
            ?>
        </div>
        
        <div class="receipt-totals">
            <p>
                <span>Subtotal:</span>
                <span><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($receipt_data['subtotal'], 2); ?></span>
            </p>
            <p>
                <span><?php echo $systemSettings->getTaxName(); ?> (<?php echo $systemSettings->getTaxRatePercent(); ?>%):</span>
                <span><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($receipt_data['tax_amount'], 2); ?></span>
            </p>
            <p>
                <span><?php echo $systemSettings->getServiceTaxName(); ?> (<?php echo $systemSettings->getServiceTaxRatePercent(); ?>%):</span>
                <span><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($receipt_data['service_tax_amount'], 2); ?></span>
            </p>
            <?php if ($receipt_data['discount_amount'] > 0): ?>
            <p style="color: #dc3545; font-weight: 600;">
                <span>
                    <?php 
                    // Get discount type display name
                    $discount_types = [
                        'birthday' => 'Birthday Discount',
                        'staff' => 'Staff Discount', 
                        'review' => 'Review Discount',
                        'complaint' => 'Complaint Discount'
                    ];
                    $discount_display = $discount_types[$receipt_data['discount_type']] ?? 'Discount';
                    
                    // Calculate percentage
                    $percentage = $receipt_data['original_total'] > 0 ? 
                        round(($receipt_data['discount_amount'] / $receipt_data['original_total']) * 100, 1) : 0;
                    
                    // Show discount type and percentage
                    echo $discount_display . ' (' . $percentage . '%):';
                    
                    // Add discount reason if available
                    if (!empty($receipt_data['discount_reason'])) {
                        echo '<br><small style="font-size: 0.8em; color: #666;">Reason: ' . htmlspecialchars($receipt_data['discount_reason']) . '</small>';
                    }
                    ?>
                </span>
                <span>-<?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($receipt_data['discount_amount'], 2); ?></span>
            </p>
            <?php endif; ?>
            <p class="total">
                <span>Total:</span>
                <span><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($receipt_data['total_amount'], 2); ?></span>
            </p>
        </div>
        
        <div class="receipt-payment">
            <?php if ($receipt_data['payment_method'] === 'cash'): ?>
            <div class="payment-method-info">
                <p>
                    <span>Payment Method:</span>
                    <span>Cash</span>
                </p>
            </div>
            <p>
                <span>Cash Received:</span>
                <span><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($receipt_data['cash_received'], 2); ?></span>
            </p>
            <p>
                <span>Change:</span>
                <span><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($receipt_data['change_amount'], 2); ?></span>
            </p>
            <?php else: ?>
            <div class="payment-method-info">
                <p>
                    <span>Payment Method:</span>
                    <span>TNG Pay</span>
                </p>
                <?php if (!empty($receipt_data['tng_reference'])): ?>
                <p>
                    <span>Reference:</span>
                    <span><?php echo htmlspecialchars($receipt_data['tng_reference']); ?></span>
                </p>
                <?php endif; ?>
                <p>
                    <span>Status:</span>
                    <span>PAID</span>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="receipt-footer">
            <p>Thank you for your order!</p>
            <p>Please come again</p>
        </div>
    </div>
    
    <!-- Page Controls - Outside of receipt container -->
    <div class="page-controls">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="payment_counter.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Payment Counter
        </a>
        <button class="btn btn-danger" onclick="closeWindow()">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <script>
        // Auto print functionality
        function autoPrint() {
            window.printExecuted = true;
            window.print();
        }

        // Enhanced print handling
        function handlePrint() {
            // For browsers that support the beforeprint event
            window.addEventListener('beforeprint', function() {
                console.log('Print dialog opened');
                // Hide printing indicator when print dialog opens
                const indicator = document.querySelector('.printing-indicator');
                if (indicator) {
                    indicator.style.display = 'none';
                }
            });
            
            // For browsers that support the afterprint event
            window.addEventListener('afterprint', function() {
                console.log('Print completed or cancelled');
                // Hide printing indicator
                const indicator = document.querySelector('.printing-indicator');
                if (indicator) {
                    indicator.style.display = 'none';
                }
                // If this was opened as a popup, close it
                if (window.opener) {
                    setTimeout(function() {
                        window.close();
                    }, 1000);
                }
            });
            
            // Trigger print
            autoPrint();
        }

        // Auto print when page loads
        window.onload = function() {
            // Add auto-print class to body for styling
            document.body.classList.add('auto-print-mode');
            
            // Multiple attempts to ensure printing works
            setTimeout(handlePrint, 300);
            setTimeout(handlePrint, 800);
            setTimeout(handlePrint, 1500);
            
            // Hide printing indicator after 4 seconds regardless
            setTimeout(function() {
                const indicator = document.querySelector('.printing-indicator');
                if (indicator) {
                    indicator.style.display = 'none';
                }
            }, 4000);
        };

        // Fallback: Auto print after 3 seconds if not already printed
        setTimeout(function() {
            if (!window.printExecuted) {
                window.printExecuted = true;
                console.log('Fallback print triggered');
                window.print();
            }
        }, 3000);

        // Additional fallback for stubborn browsers
        setTimeout(function() {
            if (!window.printExecuted) {
                window.printExecuted = true;
                console.log('Final fallback print triggered');
                window.print();
            }
        }, 5000);

        // Close window function
        function closeWindow() {
            // Check if this window was opened by another window
            if (window.opener) {
                // If opened as popup, close it
                window.close();
            } else {
                // If opened directly, redirect to payment counter
                window.location.href = 'payment_counter.php';
            }
        }
        
        // Quick print function for floating button
        function quickPrint() {
            // Add visual feedback
            const printBtn = document.querySelector('.floating-btn.print');
            printBtn.style.transform = 'scale(0.9)';
            printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            setTimeout(function() {
                window.print();
                printBtn.style.transform = 'scale(1)';
                printBtn.innerHTML = '<i class="fas fa-print"></i>';
            }, 300);
        }
        
        // Quick close function for floating button
        function quickClose() {
            // Add visual feedback
            const closeBtn = document.querySelector('.floating-btn.close');
            closeBtn.style.transform = 'scale(0.9)';
            closeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            setTimeout(function() {
                closeWindow();
            }, 300);
        }
        
        // Handle print button click
        document.addEventListener('DOMContentLoaded', function() {
            const printButton = document.querySelector('.btn-primary');
            if (printButton) {
                printButton.addEventListener('click', function() {
                    window.printExecuted = true;
                    window.print();
                });
            }
            
            // Handle close button click
            const closeButton = document.querySelector('.btn-danger');
            if (closeButton) {
                closeButton.addEventListener('click', function() {
                    closeWindow();
                });
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close
            if (e.key === 'Escape') {
                closeWindow();
            }
            // Ctrl+W or Cmd+W to close
            if ((e.ctrlKey || e.metaKey) && e.key === 'w') {
                e.preventDefault();
                closeWindow();
            }
        });
    </script>
</body>
</html>




