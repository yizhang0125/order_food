<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);
$systemSettings = new SystemSettings($db); // Fix: Pass database connection

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

// Check if user has permission to view orders
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('view_orders', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Order Details') . '&type=warning');
    exit();
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$order_id) {
    header('Location: completed_orders.php?message=' . urlencode('Invalid order ID') . '&type=error');
    exit();
}

// Get order details
$order = $orderModel->getOrder($order_id);

if (!$order) {
    header('Location: completed_orders.php?message=' . urlencode('Order not found') . '&type=error');
    exit();
}

// Get payment details for this order
$payment_sql = "SELECT * FROM payments WHERE order_id = ? AND payment_status = 'completed'";
$payment_stmt = $db->prepare($payment_sql);
$payment_stmt->execute([$order_id]);
$payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);

// Set page title
$page_title = "Order Details - #" . str_pad($order_id, 4, '0', STR_PAD_LEFT);

// Start output buffering
ob_start();
?>

<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Order Details
            </h1>
            <div class="header-actions">
                <a href="completed_orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Orders
                </a>
                <button class="btn btn-primary" onclick="printOrder()">
                    <i class="fas fa-print"></i>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Order Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i>
                        Order Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Order ID:</label>
                                <span class="order-id">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Table Number:</label>
                                <span>Table <?php echo htmlspecialchars($order['table_number']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Order Status:</label>
                                <span class="status-badge <?php echo strtolower($order['status']); ?>">
                                    <i class="fas fa-<?php echo $order['status'] == 'completed' ? 'check-circle' : ($order['status'] == 'processing' ? 'clock' : 'times-circle'); ?>"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Order Date:</label>
                                <span><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
                            </div>
                            <?php if (isset($order['updated_at']) && $order['updated_at'] && $order['updated_at'] != $order['created_at']): ?>
                            <div class="info-item">
                                <label>Last Updated:</label>
                                <span><?php echo date('d M Y, h:i A', strtotime($order['updated_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($payment): ?>
                            <div class="info-item">
                                <label>Payment Method:</label>
                                <span class="payment-method <?php echo strtolower($payment['payment_method']); ?>">
                                    <i class="fas fa-<?php echo $payment['payment_method'] == 'cash' ? 'money-bill' : ($payment['payment_method'] == 'card' ? 'credit-card' : 'mobile'); ?>"></i>
                                    <?php echo ucfirst($payment['payment_method']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i>
                        Order Items
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Special Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subtotal = 0;
                                foreach ($order['items'] as $item): 
                                    $item_total = $item['price'] * $item['quantity'];
                                    $subtotal += $item_total;
                                ?>
                                <tr>
                                    <td>
                                        <div class="item-info">
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="quantity-badge">
                                            <?php echo $item['quantity']; ?>
                                        </span>
                                    </td>
                                    <td>RM <?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <span class="item-total">
                                            RM <?php echo number_format($item_total, 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        // Order items may store special instructions under 'special_instructions'
                                        // or (in other parts of the app) under 'instructions'. Support both.
                                        $instr = '';
                                        if (!empty($item['special_instructions'])) {
                                            $instr = $item['special_instructions'];
                                        } elseif (!empty($item['instructions'])) {
                                            $instr = $item['instructions'];
                                        }
                                        ?>
                                        <?php if (!empty($instr)): ?>
                                            <div class="special-instructions">
                                                <i class="fas fa-comment-alt"></i>
                                                <?php echo htmlspecialchars($instr); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No special instructions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Order Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calculator"></i>
                        Order Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="summary-item">
                        <span>Subtotal:</span>
                        <span>RM <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <?php 
                    // Calculate service tax and SST
                    $service_tax_rate = $systemSettings->getServiceTaxRate();
                    $service_tax_amount = $subtotal * $service_tax_rate;
                    $service_tax_name = $systemSettings->getServiceTaxName();
                    $service_tax_percent = $systemSettings->getServiceTaxRatePercent();

                    $sst_rate = $systemSettings->getTaxRate();
                    $sst_amount = $subtotal * $sst_rate;
                    $sst_name = $systemSettings->getTaxName();
                    $sst_percent = $systemSettings->getTaxRatePercent();
                    $currency_symbol = $systemSettings->getCurrencySymbol();
                    
                    // Calculate total with both taxes
                    $total_with_taxes = customRound($subtotal + $service_tax_amount + $sst_amount);
                    ?>
                    <div class="summary-item">
                        <span><?php echo $service_tax_name; ?> (<?php echo $service_tax_percent; ?>%):</span>
                        <span><?php echo $currency_symbol; ?> <?php echo number_format($service_tax_amount, 2); ?></span>
                    </div>
                    <div class="summary-item">
                        <span><?php echo $sst_name; ?> (<?php echo $sst_percent; ?>%):</span>
                        <span><?php echo $currency_symbol; ?> <?php echo number_format($sst_amount, 2); ?></span>
                    </div>
                    <div class="summary-total">
                        <span>Total:</span>
                        <span><?php echo $currency_symbol; ?> <?php echo number_format($total_with_taxes, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <?php if ($payment): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-credit-card"></i>
                        Payment Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <label>Payment Status:</label>
                        <span class="payment-status <?php echo strtolower($payment['payment_status']); ?>">
                            <i class="fas fa-<?php echo $payment['payment_status'] == 'completed' ? 'check-circle' : 'clock'; ?>"></i>
                            <?php echo ucfirst($payment['payment_status']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Payment Method:</label>
                        <span class="payment-method <?php echo strtolower($payment['payment_method']); ?>">
                            <i class="fas fa-<?php echo $payment['payment_method'] == 'cash' ? 'money-bill' : ($payment['payment_method'] == 'card' ? 'credit-card' : 'mobile'); ?>"></i>
                            <?php echo ucfirst($payment['payment_method']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Amount Paid:</label>
                        <span class="payment-amount">
                            <?php echo $currency_symbol; ?> <?php echo number_format(customRound($payment['amount']), 2); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Payment Date:</label>
                        <span><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></span>
                    </div>
                    <?php if (!empty($payment['notes'])): ?>
                    <div class="info-item">
                        <label>Payment Notes:</label>
                        <span><?php echo htmlspecialchars($payment['notes']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cogs"></i>
                        Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="printOrder()">
                            <i class="fas fa-print"></i>
                            Print Receipt
                        </button>
                        <a href="completed_orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Orders
                        </a>
                        <!-- View Order History removed per request -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add custom CSS
$extra_css = '
<style>
    :root {
        --primary: #4f46e5;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
    }

    .page-header {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-title i {
        color: var(--primary);
    }

    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        margin-bottom: 1.5rem;
    }

    .card-header {
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        border-radius: 16px 16px 0 0 !important;
        padding: 1.25rem;
    }

    .card-title {
        font-weight: 600;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-title i {
        color: var(--primary);
    }

    .card-body {
        padding: 1.5rem;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-item label {
        font-weight: 500;
        color: var(--gray-600);
        margin: 0;
    }

    .info-item span {
        font-weight: 600;
        color: var(--gray-800);
    }

    .order-id {
        color: var(--primary);
        font-size: 1.1rem;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .status-badge.completed {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .status-badge.processing {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .status-badge.cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .payment-method {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .payment-method.cash {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .payment-method.card {
        background: rgba(79, 70, 229, 0.1);
        color: var(--primary);
    }

    .payment-method.tng {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .payment-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .payment-status.completed {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .payment-status.pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .quantity-badge {
        background: var(--primary);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .item-total {
        font-weight: 600;
        color: var(--gray-800);
    }

    .special-instructions {
        background: rgba(79, 70, 229, 0.1);
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-size: 0.875rem;
        color: var(--gray-700);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .special-instructions i {
        color: var(--primary);
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-top: 2px solid var(--gray-200);
        margin-top: 0.5rem;
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--gray-800);
    }

    .payment-amount {
        font-weight: 700;
        color: var(--success);
        font-size: 1.1rem;
    }

    .table th {
        background: var(--gray-50);
        border-top: none;
        font-weight: 600;
        color: var(--gray-600);
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.05em;
    }

    .table td {
        border-top: 1px solid var(--gray-100);
        vertical-align: middle;
    }

    .item-info strong {
        color: var(--gray-800);
    }

    @media (max-width: 768px) {
        .page-header {
            padding: 1.5rem;
        }

        .header-actions {
            flex-direction: column;
            width: 100%;
            margin-top: 1rem;
        }

        .header-actions .btn {
            width: 100%;
        }

        .info-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
        }
    }
</style>';

// Add custom JavaScript
$extra_js = '
<script>
    function printOrder() {
        // Create a new window for printing
        const printWindow = window.open("", "_blank", "width=800,height=600");
        
        const orderData = {
            id: "' . $order['id'] . '",
            table: "' . $order['table_number'] . '",
            date: "' . date('d M Y, h:i A', strtotime($order['created_at'])) . '",
            status: "' . $order['status'] . '",
            items: ' . json_encode($order['items']) . ',
            subtotal: ' . $subtotal . ',
            service_tax: ' . $service_tax_amount . ',
            service_tax_percent: ' . $service_tax_percent . ',
            sst: ' . $sst_amount . ',
            sst_percent: ' . $sst_percent . ',
            total: ' . $total_with_taxes . ',
            payment: ' . json_encode($payment) . '
        };
        
        const htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Order Receipt - #${orderData.id}</title>
                <style>
                    body { 
                        font-family: "Arial", sans-serif; 
                        font-size: 14px; 
                        margin: 0; 
                        padding: 20px; 
                        background: white;
                    }
                    .receipt-header { 
                        text-align: center; 
                        margin-bottom: 20px; 
                        border-bottom: 2px solid #000; 
                        padding-bottom: 15px; 
                    }
                    .receipt-header h1 { 
                        font-size: 24px; 
                        margin: 0 0 10px 0; 
                        font-weight: bold; 
                        text-transform: uppercase;
                    }
                    .receipt-info { 
                        margin-bottom: 20px; 
                        display: flex;
                        justify-content: space-between;
                    }
                    .receipt-info div { 
                        flex: 1;
                    }
                    .receipt-info p { 
                        margin: 5px 0; 
                        font-weight: bold;
                    }
                    .items-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                    }
                    .items-table th,
                    .items-table td {
                        border: 1px solid #000;
                        padding: 8px;
                        text-align: left;
                    }
                    .items-table th {
                        background: #f0f0f0;
                        font-weight: bold;
                    }
                    .receipt-summary {
                        border-top: 2px solid #000;
                        padding-top: 15px;
                        margin-top: 20px;
                    }
                    .summary-row {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 5px;
                    }
                    .summary-total {
                        font-weight: bold;
                        font-size: 16px;
                        border-top: 1px solid #000;
                        padding-top: 10px;
                        margin-top: 10px;
                    }
                    .receipt-footer { 
                        margin-top: 30px; 
                        border-top: 2px solid #000; 
                        padding-top: 15px; 
                        text-align: center; 
                        font-weight: bold;
                    }
                    @media print {
                        body { margin: 0; padding: 10px; }
                        @page { margin: 0.5in; }
                    }
                </style>
            </head>
            <body>
                <div class="receipt-header">
                    <h1>ORDER RECEIPT</h1>
                    <p>Gourmet Delights Restaurant</p>
                </div>
                
                <div class="receipt-info">
                    <div>
                        <p>Order ID: #${orderData.id.toString().padStart(4, "0")}</p>
                        <p>Table: ${orderData.table}</p>
                        <p>Date: ${orderData.date}</p>
                    </div>
                    <div>
                        <p>Status: ${orderData.status.toUpperCase()}</p>
                        ${orderData.payment ? `<p>Payment: ${orderData.payment.payment_method.toUpperCase()}</p>` : ""}
                    </div>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${orderData.items.map(item => `
                            <tr>
                                <td>${item.name}</td>
                                <td>${item.quantity}</td>
                                <td>RM ${parseFloat(item.price).toFixed(2)}</td>
                                <td>RM ${(parseFloat(item.price) * item.quantity).toFixed(2)}</td>
                            </tr>
                        `).join("")}
                    </tbody>
                </table>
                
                <div class="receipt-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>RM ${orderData.subtotal.toFixed(2)}</span>
                    </div>
                    <div class="summary-row">
                        <span>Service Tax (${orderData.service_tax_percent}%):</span>
                        <span>RM ${orderData.service_tax.toFixed(2)}</span>
                    </div>
                    <div class="summary-row">
                        <span>SST (${orderData.sst_percent}%):</span>
                        <span>RM ${orderData.sst.toFixed(2)}</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total:</span>
                        <span>RM ${orderData.total.toFixed(2)}</span>
                    </div>
                </div>
                
                <div class="receipt-footer">
                    <p>Thank you for your order!</p>
                    <p>Generated on ${new Date().toLocaleDateString()}</p>
                </div>
            </body>
            </html>
        `;
        
        printWindow.document.write(htmlContent);
        printWindow.document.close();
        
        // Wait for content to load, then print
        printWindow.onload = function() {
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 500);
        };
    }
    
    // Order history feature removed.
</script>';

// Include the layout
include 'includes/layout.php';
?>
