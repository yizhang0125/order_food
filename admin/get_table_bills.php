<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');
require_once(__DIR__ . '/classes/PaymentController.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$systemSettings = new SystemSettings($db);
$paymentController = new PaymentController($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo '<div class="alert alert-danger">Access denied. Please login.</div>';
    exit();
}

// Get table number from URL
$table_number = isset($_GET['table']) ? (int)$_GET['table'] : null;

if (!$table_number) {
    echo '<div class="alert alert-danger">Invalid table number.</div>';
    exit();
}

try {
    // Get all tables with status
    $all_tables = $paymentController->getAllTablesWithStatus();
    
    if (!isset($all_tables[$table_number])) {
        echo '<div class="alert alert-warning">Table ' . $table_number . ' not found.</div>';
        exit();
    }
    
    $table_data = $all_tables[$table_number];
    
    if ($table_data['status'] === 'empty') {
        echo '<div class="text-center py-4">';
        echo '<i class="fas fa-table fa-3x text-muted mb-3"></i>';
        echo '<h5 class="text-muted">No Bills</h5>';
        echo '<p class="text-muted">This table has no active orders or bills.</p>';
        echo '</div>';
        exit();
    }
    
    // Display bills
    echo '<div class="bills-container">';
    
    // Show pending orders if any
    if (!empty($table_data['pending_orders'])) {
        echo '<div class="bill-section mb-4">';
        echo '<h6 class="bill-section-title"><i class="fas fa-clock text-warning"></i> Orders In Progress</h6>';
        
        foreach ($table_data['pending_orders'] as $order) {
            echo '<div class="bill-card pending">';
            echo '<div class="bill-header">';
            echo '<div class="bill-info">';
            echo '<span class="bill-id">Order #' . str_pad($order['id'], 4, '0', STR_PAD_LEFT) . '</span>';
            echo '<span class="bill-status badge bg-warning">' . ucfirst($order['status']) . '</span>';
            echo '</div>';
            echo '<div class="bill-time">' . date('h:i A', strtotime($order['created_at'])) . '</div>';
            echo '</div>';
            
            // Show items
            $items = json_decode($order['items'], true);
            if (is_array($items)) {
                echo '<div class="bill-items">';
                foreach ($items as $item) {
                    echo '<div class="bill-item">';
                    echo '<div class="item-info">';
                    echo '<span class="item-name">' . htmlspecialchars($item['name']) . '</span>';
                    if (!empty($item['instructions'])) {
                        echo '<small class="item-note">Note: ' . htmlspecialchars($item['instructions']) . '</small>';
                    }
                    echo '</div>';
                    echo '<div class="item-details">';
                    echo '<span class="item-qty">Qty: ' . $item['quantity'] . '</span>';
                    echo '<span class="item-price">' . $systemSettings->getCurrencySymbol() . ' ' . number_format($item['price'], 2) . '</span>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            
            echo '<div class="bill-total">';
            echo '<strong>Total: ' . $systemSettings->getCurrencySymbol() . ' ' . number_format($order['total_amount'], 2) . '</strong>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Show completed orders if any
    if (!empty($table_data['completed_orders'])) {
        echo '<div class="bill-section">';
        echo '<h6 class="bill-section-title"><i class="fas fa-check-circle text-success"></i> Completed Orders (Ready for Payment)</h6>';
        
        $subtotal = 0;
        foreach ($table_data['completed_orders'] as $order) {
            echo '<div class="bill-card completed">';
            echo '<div class="bill-header">';
            echo '<div class="bill-info">';
            echo '<span class="bill-id">Order #' . str_pad($order['id'], 4, '0', STR_PAD_LEFT) . '</span>';
            echo '<span class="bill-status badge bg-success">Completed</span>';
            echo '</div>';
            echo '<div class="bill-time">' . date('h:i A', strtotime($order['created_at'])) . '</div>';
            echo '</div>';
            
            // Show items
            $items = json_decode($order['items'], true);
            if (is_array($items)) {
                echo '<div class="bill-items">';
                foreach ($items as $item) {
                    $item_total = $item['quantity'] * $item['price'];
                    $subtotal += $item_total;
                    
                    echo '<div class="bill-item">';
                    echo '<div class="item-info">';
                    echo '<span class="item-name">' . htmlspecialchars($item['name']) . '</span>';
                    if (!empty($item['instructions'])) {
                        echo '<small class="item-note">Note: ' . htmlspecialchars($item['instructions']) . '</small>';
                    }
                    echo '</div>';
                    echo '<div class="item-details">';
                    echo '<span class="item-qty">Qty: ' . $item['quantity'] . '</span>';
                    echo '<span class="item-price">' . $systemSettings->getCurrencySymbol() . ' ' . number_format($item['price'], 2) . '</span>';
                    echo '<span class="item-total">' . $systemSettings->getCurrencySymbol() . ' ' . number_format($item_total, 2) . '</span>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            
            echo '<div class="bill-total">';
            echo '<strong>Total: ' . $systemSettings->getCurrencySymbol() . ' ' . number_format($order['total_amount'], 2) . '</strong>';
            echo '</div>';
            echo '</div>';
        }
        
        // Show payment summary
        if (!empty($table_data['completed_orders'])) {
            $tax_rate = $systemSettings->getTaxRate();
            $tax_amount = $subtotal * $tax_rate;
            $total_with_tax = $subtotal + $tax_amount;
            $total_with_tax = $paymentController->customRound($total_with_tax);
            
            echo '<div class="payment-summary">';
            echo '<h6 class="payment-title"><i class="fas fa-credit-card"></i> Payment Summary</h6>';
            echo '<div class="payment-breakdown">';
            echo '<div class="payment-row">';
            echo '<span>Subtotal:</span>';
            echo '<span>' . $systemSettings->getCurrencySymbol() . ' ' . number_format(round($subtotal, 2), 2) . '</span>';
            echo '</div>';
            echo '<div class="payment-row">';
            echo '<span>' . $systemSettings->getTaxName() . ' (' . $systemSettings->getTaxRatePercent() . '%):</span>';
            echo '<span>' . $systemSettings->getCurrencySymbol() . ' ' . number_format(round($tax_amount, 2), 2) . '</span>';
            echo '</div>';
            echo '<div class="payment-row total">';
            echo '<span><strong>Total Amount:</strong></span>';
            echo '<span><strong>' . $systemSettings->getCurrencySymbol() . ' ' . number_format($total_with_tax, 2) . '</strong></span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading bills: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<style>
.bills-container {
    max-height: 500px;
    overflow-y: auto;
}

.bill-section {
    margin-bottom: 2rem;
}

.bill-section-title {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--gray-200);
}

.bill-card {
    background: var(--light);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--gray-300);
}

.bill-card.pending {
    border-left-color: var(--warning);
}

.bill-card.completed {
    border-left-color: var(--success);
}

.bill-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1rem;
}

.bill-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bill-id {
    font-weight: 600;
    color: var(--primary);
}

.bill-time {
    color: var(--gray-500);
    font-size: 0.9rem;
}

.bill-items {
    margin-bottom: 1rem;
}

.bill-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--gray-100);
}

.bill-item:last-child {
    border-bottom: none;
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: 500;
    color: var(--dark);
}

.item-note {
    display: block;
    color: var(--gray-500);
    font-style: italic;
    margin-top: 0.25rem;
}

.item-details {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.9rem;
}

.item-qty {
    color: var(--gray-600);
}

.item-price {
    color: var(--gray-600);
}

.item-total {
    font-weight: 600;
    color: var(--primary);
}

.bill-total {
    text-align: right;
    padding-top: 0.5rem;
    border-top: 2px solid var(--gray-200);
    color: var(--primary);
}

.payment-summary {
    background: var(--gray-50);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.payment-title {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 1rem;
}

.payment-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.payment-row.total {
    border-top: 2px solid var(--primary);
    margin-top: 0.5rem;
    padding-top: 1rem;
    font-size: 1.1rem;
}

.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
</style>
