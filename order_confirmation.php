<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/Order.php');
require_once(__DIR__ . '/classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$orderModel = new Order($db);
$systemSettings = new SystemSettings($db);

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
$order = $order_id ? $orderModel->getOrder($order_id) : null;

// Get table number from URL
$table_number = isset($_GET['table']) ? htmlspecialchars($_GET['table']) : null;

// Get token from URL
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="admin/css/order_confirmation.css" rel="stylesheet">
    
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php<?php echo $table_number && $token ? '?table=' . $table_number . '&token=' . $token : ''; ?>">
                <i class="fas fa-utensils"></i>
                Gourmet Delights
            </a>
            <div class="d-flex align-items-center gap-3">
                <?php if ($table_number): ?>
                <div class="table-info-banner">
                    <span class="table-number">Table <?php echo $table_number; ?></span>
                </div>
                <?php if ($token): ?>
                <a href="view_orders.php?table=<?php echo $table_number; ?>&token=<?php echo $token; ?>" class="view-orders-btn">
                    <i class="fas fa-list-ul"></i>
                    View Orders
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Confirmation Content -->
    <div class="container">
        <div class="confirmation-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="confirmation-title">Order Confirmed!</h1>
            <p class="mb-4">Thank you for your order. Your food will be prepared shortly.</p>
            
            <?php if ($order): ?>
            <div class="order-details">
                <div class="order-number">
                    Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="detail-row">
                    <span>Table Number:</span>
                    <span>Table <?php echo htmlspecialchars($order['table_number']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Order Time:</span>
                    <span><?php echo date('h:i A', strtotime($order['created_at'])); ?></span>
                </div>
                
                <div class="order-items">
                    <?php 
                    $subtotal = 0;
                    foreach ($order['items'] as $item): 
                        $item_total = $item['price'] * $item['quantity'];
                        $subtotal += $item_total;
                    ?>
                    <div class="order-item">
                        <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?></span>
                        <span>RM <?php echo number_format($item_total, 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="order-summary">
                        <div class="detail-row">
                            <span>Subtotal:</span>
                            <span>RM <?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <?php 
                        // Calculate tax using dynamic tax rate
                        $tax_rate = $systemSettings->getTaxRate();
                        $tax_amount = $subtotal * $tax_rate;
                        $tax_name = $systemSettings->getTaxName();
                        $tax_percent = $systemSettings->getTaxRatePercent();
                        $currency_symbol = $systemSettings->getCurrencySymbol();
                        ?>
                        <div class="detail-row">
                            <span><?php echo $tax_name; ?> (<?php echo $tax_percent; ?>%):</span>
                            <span><?php echo $currency_symbol; ?> <?php echo number_format($tax_amount, 2); ?></span>
                        </div>
                        <div class="order-total">
                            <div class="detail-row">
                                <span>Total (Incl. <?php echo $tax_name; ?>):</span>
                                <span><?php echo $currency_symbol; ?> <?php echo number_format($subtotal + $tax_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons mt-4">
                <a href="view_orders.php<?php echo ($table_number && $token) ? '?table=' . $table_number . '&token=' . $token : ''; ?>" class="view-orders-btn">
                    <i class="fas fa-list-ul"></i>
                    View All Orders
                </a>
                
                <a href="index.php<?php echo ($table_number && $token) ? '?table=' . $table_number . '&token=' . $token : ''; ?>" class="back-to-menu">
                    <i class="fas fa-arrow-left"></i>
                    Back to Menu
                </a>
            </div>
        </div>
    </div>

    <script>
        // Get table and token from URL to create unique cart key
        const urlParams = new URLSearchParams(window.location.search);
        const tableNumber = urlParams.get('table');
        const token = urlParams.get('token');
        
        // Create unique cart key for this table and token
        const cartKey = tableNumber && token ? `cart_${tableNumber}_${token}` : 'cart_default';
        
        // Clear cart after successful order
        localStorage.removeItem(cartKey);
    </script>
</body>
</html> 