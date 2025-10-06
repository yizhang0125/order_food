<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/Order.php');
require_once(__DIR__ . '/classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$orderModel = new Order($db);
$systemSettings = new SystemSettings($db);

// Cash rounding function - rounds to nearest 0.05 (5 cents)
if (!function_exists('customRound')) {
    function customRound($amount) {
        // Round to nearest 0.05 (5 cents) for cash transactions
        // Multiply by 20, round to nearest integer, then divide by 20
        return round($amount * 20) / 20;
    }
}

// Get table number and token from URL
$table_number = isset($_GET['table']) ? htmlspecialchars($_GET['table']) : null;
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : null;

// Validate token and get table orders
$table_orders = [];
$error_message = '';

if ($table_number && $token) {
    try {
        // Validate token
        $validate_query = "SELECT t.id, t.table_number, qc.token 
                          FROM tables t 
                          JOIN qr_codes qc ON t.id = qc.table_id 
                          WHERE t.table_number = ? 
                          AND qc.token = ? 
                          AND qc.is_active = 1 
                          AND (qc.expires_at IS NULL OR qc.expires_at > NOW())";
        $stmt = $db->prepare($validate_query);
        $stmt->execute([$table_number, $token]);
        $table_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($table_data) {
            // Get current orders for this table - pending, processing, and completed
            // Only show orders from the current day to exclude old orders
            // Exclude orders that have a completed payment
            $orders_query = "SELECT o.*, 
                           (SELECT JSON_ARRAYAGG(
                               JSON_OBJECT(
                                   'id', oi.id,
                                   'name', m.name,
                                   'price', m.price,
                                   'quantity', oi.quantity,
                                   'instructions', oi.special_instructions
                               )
                           ) 
                           FROM order_items oi 
                           JOIN menu_items m ON oi.menu_item_id = m.id 
                           WHERE oi.order_id = o.id) as items
                           FROM orders o
                           JOIN tables t ON o.table_id = t.id
                           LEFT JOIN payments p ON o.id = p.order_id
                           WHERE t.table_number = ?
                           AND o.status IN ('pending', 'processing', 'completed')
                           AND (p.payment_status IS NULL OR p.payment_status != 'completed')
                           AND DATE(o.created_at) = CURDATE()
                           ORDER BY o.status, o.created_at DESC";
            $orders_stmt = $db->prepare($orders_query);
            $orders_stmt->execute([$table_number]);
            $all_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group orders by status
            $grouped_orders = [];
            $status_groups = [
                'pending' => [
                    'total' => 0,
                    'ids' => [],
                    'items' => [],
                    'earliest_time' => null
                ],
                'processing' => [
                    'total' => 0,
                    'ids' => [],
                    'items' => [],
                    'earliest_time' => null
                ],
                'completed' => [
                    'total' => 0,
                    'ids' => [],
                    'items' => [],
                    'earliest_time' => null
                ]
            ];
            
            foreach ($all_orders as $order) {
                if ($order['status'] == 'pending' || $order['status'] == 'processing' || $order['status'] == 'completed') {
                    $status = $order['status'];
                    
                    // Add to appropriate status group
                    $status_groups[$status]['total'] += floatval($order['total_amount']);
                    $status_groups[$status]['ids'][] = $order['id'];
                    
                    // Track earliest order time
                    if ($status_groups[$status]['earliest_time'] === null || 
                        strtotime($order['created_at']) < strtotime($status_groups[$status]['earliest_time'])) {
                        $status_groups[$status]['earliest_time'] = $order['created_at'];
                    }
                    
                    // Merge items and combine same food items
                    $items = json_decode($order['items'], true);
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (is_array($item)) {
                                // Check if item already exists in the group
                                $found = false;
                                foreach ($status_groups[$status]['items'] as &$existing_item) {
                                    if ($existing_item['name'] === $item['name'] && 
                                        $existing_item['price'] == $item['price'] &&
                                        $existing_item['instructions'] === $item['instructions']) {
                                        // Combine quantities for same food
                                        $existing_item['quantity'] += $item['quantity'];
                                        $found = true;
                                        break;
                                    }
                                }
                                
                                // If not found, add as new item
                                if (!$found) {
                                    $status_groups[$status]['items'][] = $item;
                                }
                            }
                        }
                    }
                }
            }
            
            // Create combined orders for each status group that has orders
            foreach ($status_groups as $status => $group) {
                if (!empty($group['ids'])) {
                    $combined_order = [
                        'id' => implode(',', $group['ids']),
                        'status' => $status,
                        'total_amount' => $group['total'],
                        'created_at' => $group['earliest_time'],
                        'items' => json_encode($group['items']),
                        'is_combined' => true,
                        'order_count' => count($group['ids'])
                    ];
                    
                    // Add to grouped orders
                    $grouped_orders[] = $combined_order;
                }
            }
            
            // Sort orders: pending first, then processing, then completed
            usort($grouped_orders, function($a, $b) {
                $status_order = ['pending' => 1, 'processing' => 2, 'completed' => 3];
                $status_a = $a['status'];
                $status_b = $b['status'];
                
                if ($status_order[$status_a] !== $status_order[$status_b]) {
                    return $status_order[$status_a] - $status_order[$status_b];
                }
                
                // If same status, sort by created_at (newest first)
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            $table_orders = $grouped_orders;
        } else {
            $error_message = "Invalid or expired QR code. Please scan again.";
            $table_number = null;
            $token = null;
        }
    } catch (Exception $e) {
        error_log("Error in view_orders.php: " . $e->getMessage());
        $error_message = "Error retrieving orders. Please try again.";
    }
} else {
    $error_message = "Please scan your table's QR code to view orders.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders - Table <?php echo $table_number; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    
    <style>
        :root {
            --color-bg: #f8faff;
            --color-surface: #ffffff;
            --color-primary: #2563eb;
            --color-secondary: #3b82f6;
            --color-pending: #f97316;
            --color-processing: #8b5cf6;
            --color-completed: #10b981;
            --color-cancelled: #ef4444;
            --color-text: #1e293b;
            --color-text-light: #64748b;
            --box-shadow: 0 2px 4px rgba(148, 163, 184, 0.1);
            --box-shadow-lg: 0 8px 16px rgba(148, 163, 184, 0.1);
            --border-radius: 12px;
        }

        body {
            background: var(--color-bg);
            color: var(--color-text);
            font-family: 'DM Sans', sans-serif;
            padding-top: 80px;
            line-height: 1.6;
        }

        /* Navbar styles are now in navbar.css */

        .table-info {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
            text-align: center;
        }

        .table-info h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--color-primary);
            margin-bottom: 0.5rem;
        }

        .table-info p {
            color: var(--color-text-light);
            margin: 0;
        }

        .order-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: visible;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-lg);
        }

        .order-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-id {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--color-primary);
        }

        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-processing {
            background: #f5f3ff;
            color: #5b21b6;
        }

        .status-completed {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fef2f2;
            color: #991b1b;
        }

        .order-body {
            padding: 1.25rem;
        }

        .order-items {
            margin-bottom: 1.25rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--color-border);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-details {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .item-quantity {
            background: var(--color-primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .item-name {
            font-weight: 500;
        }

        .item-price {
            color: var(--color-text-light);
            font-weight: 500;
        }

        .special-instructions {
            margin-top: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #f8fafc;
            border-left: 3px solid var(--color-primary);
            border-radius: 4px;
            font-size: 0.875rem;
            color: var(--color-text);
        }

        .special-instructions i {
            color: var(--color-primary);
            margin-right: 0.5rem;
        }

        .order-footer {
            padding: 1.25rem;
            background: var(--color-surface);
            border-top: 1px solid var(--color-border);
        }

        .order-subtotal, .order-sst {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
            color: var(--color-text-light);
            margin-bottom: 0.5rem;
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.125rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px dashed rgba(0,0,0,0.1);
        }

        .order-time {
            color: var(--color-text-light);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .no-orders {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .no-orders i {
            font-size: 3rem;
            color: var(--color-text-light);
            margin-bottom: 1rem;
        }

        .no-orders h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }

        .no-orders p {
            color: var(--color-text-light);
            margin: 0;
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }

            .table-info {
                padding: 1.5rem;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .order-status {
                align-self: flex-start;
            }
        }

        /* Status Timeline Styles */
        .status-timeline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.5rem 0;
            position: relative;
            padding: 0 1rem;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--color-border);
            transform: translateY(-50%);
            z-index: 1;
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 1rem;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-surface);
            border: 2px solid var(--color-border);
            color: var(--color-text-light);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .step-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-text-light);
            white-space: nowrap;
        }

        /* Status-specific styles */
        .timeline-step.active .step-icon {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .timeline-step.active .step-label {
            color: var(--color-primary);
            font-weight: 600;
        }

        .timeline-step.completed .step-icon {
            background: var(--color-completed);
            border-color: var(--color-completed);
            color: white;
        }

        .timeline-step.completed .step-label {
            color: var(--color-completed);
        }

        /* Progress line styles */
        .progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            height: 2px;
            background: var(--color-primary);
            transform: translateY(-50%);
            transition: width 0.3s ease;
            z-index: 1;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="table-info">
            <h1>Table <?php echo $table_number; ?></h1>
            <p>View your orders for today</p>
        </div>

        <?php if (empty($table_orders)): ?>
        <div class="no-orders">
            <i class="fas fa-receipt"></i>
            <h2>No Orders Found</h2>
            <p>You don't have any orders for today.</p>
        </div>
        <?php else: ?>
            <?php foreach ($table_orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <?php if (isset($order['is_combined']) && $order['is_combined']): ?>
                    <div class="order-id">
                        <?php echo ucfirst($order['status']); ?> Orders (<?php echo $order['order_count']; ?> orders)
                        <div class="small text-muted">
                            <?php 
                            $order_ids = explode(',', $order['id']);
                            $formatted_ids = array_map(function($id) {
                                return '#' . str_pad($id, 4, '0', STR_PAD_LEFT);
                            }, $order_ids);
                            echo implode(', ', $formatted_ids);
                            ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="order-id">Order #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></div>
                    <?php endif; ?>
                    <div class="order-status status-<?php echo strtolower($order['status']); ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </div>
                </div>

                <!-- Add Status Timeline -->
                <div class="status-timeline">
                    <?php
                    $statuses = ['pending', 'processing', 'completed'];
                    $currentStatus = strtolower($order['status']);
                    $progress = 0;
                    
                    switch($currentStatus) {
                        case 'completed':
                            $progress = 100;
                            break;
                        case 'processing':
                            $progress = 50;
                            break;
                        default:
                            $progress = 0;
                    }
                    ?>
                    <div class="progress-line" style="width: <?php echo $progress; ?>%"></div>
                    
                    <?php foreach ($statuses as $index => $status): 
                        $isActive = $currentStatus === $status;
                        $isCompleted = array_search($currentStatus, $statuses) > array_search($status, $statuses);
                        $stepClass = $isActive ? 'active' : ($isCompleted ? 'completed' : '');
                    ?>
                    <div class="timeline-step <?php echo $stepClass; ?>">
                        <div class="step-icon">
                            <?php if ($isCompleted): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <?php
                                $icon = 'clock';
                                switch($status) {
                                    case 'processing':
                                        $icon = 'fire';
                                        break;
                                    case 'completed':
                                        $icon = 'check';
                                        break;
                                }
                                ?>
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-label"><?php echo ucfirst($status); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-body">
                    <div class="order-items">
                        <?php 
                        $items = json_decode($order['items'], true);
                        if (is_array($items)) {
                            foreach ($items as $item): 
                                if (is_array($item)):
                        ?>
                        <div class="order-item">
                            <div class="item-details">
                                <span class="item-quantity"><?php echo htmlspecialchars($item['quantity']); ?>×</span>
                                <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            </div>
                            <span class="item-price"><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                        <?php if (!empty($item['instructions'])): ?>
                        <div class="special-instructions">
                            <i class="fas fa-comment-alt"></i>
                            <?php echo htmlspecialchars($item['instructions']); ?>
                        </div>
                        <?php 
                                endif;
                            endif;
                            endforeach; 
                        }
                        ?>
                    </div>
                </div>
                <div class="order-footer">
                    <?php
                    // Calculate tax and service tax using dynamic rates
                    $tax_rate = $systemSettings->getTaxRate();
                    $service_tax_rate = $systemSettings->getServiceTaxRate();
                    $total_tax_rate = $tax_rate + $service_tax_rate;
                    $subtotal = $order['total_amount'] / (1 + $total_tax_rate);
                    $tax_amount = $subtotal * $tax_rate;
                    $service_tax_amount = $subtotal * $service_tax_rate;
                    $total_with_tax = $subtotal + $tax_amount + $service_tax_amount;
                    
                    // Apply cash rounding to the final total
                    $total_with_tax = customRound($total_with_tax);
                    
                    $tax_name = $systemSettings->getTaxName();
                    $tax_percent = $systemSettings->getTaxRatePercent();
                    $service_tax_name = $systemSettings->getServiceTaxName();
                    $service_tax_percent = $systemSettings->getServiceTaxRatePercent();
                    $currency_symbol = $systemSettings->getCurrencySymbol();
                    ?>
                    <div class="order-subtotal">
                        <span>Subtotal</span>
                        <span><?php echo $currency_symbol; ?> <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="order-sst">
                        <span><?php echo $tax_name; ?> (<?php echo $tax_percent; ?>%)</span>
                        <span><?php echo $currency_symbol; ?> <?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    <div class="order-sst">
                        <span><?php echo $service_tax_name; ?> (<?php echo $service_tax_percent; ?>%)</span>
                        <span><?php echo $currency_symbol; ?> <?php echo number_format($service_tax_amount, 2); ?></span>
                    </div>
                    <div class="order-total">
                        <span>Total</span>
                        <span><?php echo $currency_symbol; ?> <?php echo number_format($total_with_tax, 2); ?></span>
                    </div>
                    <div class="order-time">
                        Ordered: <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 