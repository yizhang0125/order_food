<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Custom rounding function for payment counter
if (!function_exists('customRound')) {
    function customRound($amount) {
        // Get the decimal part (last 2 digits)
        $decimal_part = fmod($amount * 100, 100);
        
        // Handle rounding rules based on decimal part
        if ($decimal_part >= 11 && $decimal_part <= 12) {
            // Round to .10 (e.g., 69.11, 69.12 -> 69.10)
            return floor($amount) + 0.10;
        } elseif ($decimal_part >= 13 && $decimal_part <= 14) {
            // Round to .15 (e.g., 69.13, 69.14 -> 69.15)
            return floor($amount) + 0.15;
        } elseif ($decimal_part >= 16 && $decimal_part <= 17) {
            // Round to .15 (e.g., 69.16, 69.17 -> 69.15)
            return floor($amount) + 0.15;
        } elseif ($decimal_part >= 18 && $decimal_part <= 19) {
            // Round to .20 (e.g., 69.18, 69.19 -> 69.20)
            return floor($amount) + 0.20;
        } else {
            // Standard rounding for other cases
            return round($amount, 2);
        }
    }
}

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get date range from query parameters or default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get payments with order details
try {
    // First, get all payments within the date range
    $sql = "SELECT p.payment_id, p.order_id, p.amount, p.payment_status, 
            p.payment_date, p.cash_received, p.change_amount, p.processed_by_name,
            p.payment_method, p.tng_reference,
            o.id as order_id, t.table_number, o.created_at as order_date,
            GROUP_CONCAT(CONCAT(m.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items_list,
            SUM(oi.quantity) as item_count
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN menu_items m ON oi.menu_item_id = m.id
            WHERE p.payment_date BETWEEN ? AND ?
            GROUP BY p.payment_id
            ORDER BY p.payment_date DESC";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group payments by date, time (to the minute) and table
    $grouped_payments = [];
    foreach ($all_payments as $payment) {
        // Create a key using date, time (minutes only), and table number
        $timestamp = strtotime($payment['payment_date']);
        $timeKey = date('Y-m-d H:i', $timestamp); // Format to minutes only
        $key = $timeKey . '_' . $payment['table_number'];
        
        if (!isset($grouped_payments[$key])) {
            $grouped_payments[$key] = [
                'payment_day' => date('Y-m-d', $timestamp),
                'payment_time' => date('H:i:s', $timestamp),
                'table_number' => $payment['table_number'],
                'payment_count' => 0,
                'payment_ids' => [],
                'order_ids' => [],
                'payment_amounts' => [],
                'total_amount' => 0,
                'cash_received' => $payment['cash_received'],
                'change_amount' => $payment['change_amount'],
                'payment_statuses' => [],
                'payment_methods' => [],
                'tng_references' => [],
                'latest_payment_date' => $payment['payment_date'],
                'items_list' => [],
                'item_count' => 0,
                'processed_by_name' => $payment['processed_by_name']
            ];
        }
        
        // Add payment details
        $grouped_payments[$key]['payment_count']++;
        $grouped_payments[$key]['payment_ids'][] = $payment['payment_id'];
        $grouped_payments[$key]['order_ids'][] = $payment['order_id'];
        $grouped_payments[$key]['payment_amounts'][] = $payment['amount'];
        $grouped_payments[$key]['payment_statuses'][] = $payment['payment_status'];
        $grouped_payments[$key]['payment_methods'][] = $payment['payment_method'];
        $grouped_payments[$key]['tng_references'][] = $payment['tng_reference'];
        $grouped_payments[$key]['total_amount'] += floatval($payment['amount']);
        
        // Add items to the items list without duplicates
        $items = explode(', ', $payment['items_list']);
        foreach ($items as $item) {
            if (!in_array($item, $grouped_payments[$key]['items_list'])) {
                $grouped_payments[$key]['items_list'][] = $item;
            }
        }
        $grouped_payments[$key]['item_count'] += $payment['item_count'];
        
        // Update latest payment date if this one is newer
        if (strtotime($payment['payment_date']) > strtotime($grouped_payments[$key]['latest_payment_date'])) {
            $grouped_payments[$key]['latest_payment_date'] = $payment['payment_date'];
            $grouped_payments[$key]['cash_received'] = $payment['cash_received'];
            $grouped_payments[$key]['change_amount'] = $payment['change_amount'];
        }
    }
    
    // Convert to indexed array and prepare for display
    $payments = [];
    foreach ($grouped_payments as $group) {
        // Convert arrays to strings for display
        $group['payment_ids'] = implode(',', $group['payment_ids']);
        $group['order_ids'] = implode(',', $group['order_ids']);
        $group['payment_amounts_array'] = $group['payment_amounts'];
        $group['items_list'] = implode(', ', $group['items_list']);
        
        // Ensure change amount is calculated correctly for grouped payments
        if (count($group['payment_amounts_array']) > 1) {
            $group['change_amount'] = floatval($group['cash_received']) - $group['total_amount'];
        }
        
        $payments[] = $group;
    }
    
    // Sort by latest payment date (newest first)
    usort($payments, function($a, $b) {
        return strtotime($b['latest_payment_date']) - strtotime($a['latest_payment_date']);
    });
    
    // Calculate overall totals
    $total_payments = 0;
    $total_amount = 0;
    foreach ($payments as $payment) {
        $total_payments += $payment['payment_count'];
        $total_amount += $payment['total_amount'];
    }
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $payments = [];
    $total_payments = 0;
    $total_amount = 0;
}

// Set page title
$page_title = "Payment Details (Newest to Oldest)";

// Start output buffering
ob_start();
?>

<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-money-bill-wave"></i>
            Payment Details (Newest to Oldest)
        </h1>
    </div>

    <div class="date-filter">
        <form class="date-inputs">
            <input type="date" class="date-input" id="start_date" name="start_date" 
                   value="<?php echo $start_date; ?>">
            <input type="date" class="date-input" id="end_date" name="end_date" 
                   value="<?php echo $end_date; ?>">
            <button type="submit" class="filter-btn">
                <i class="fas fa-filter"></i>
                Filter Payments
            </button>
        </form>
    </div>

    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="summary-info">
                <h3>Total Payments</h3>
                <p><?php echo $total_payments; ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="summary-info">
                <h3>Total Amount</h3>
                <p>RM <?php echo number_format(customRound($total_amount), 2); ?></p>
            </div>
        </div>
    </div>

    <div class="orders-container">
        <div class="table-responsive">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Order ID</th>
                        <th>Table</th>
                        <th>Items</th>
                        <th>Payment Amount</th>
                        <th>Payment Method</th>
                        <th>Cash Received</th>
                        <th>Change</th>
                        <th>Payment Status</th>
                        <th>Payment By</th>
                        <th>Payment Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x mb-3 text-muted d-block"></i>
                            No payments found for the selected date range
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <?php 
                                $payment_ids = explode(',', $payment['payment_ids']);
                                if (count($payment_ids) > 1): ?>
                                    <span class="payment-id"><?php echo count($payment_ids); ?> payments</span>
                                    <div class="small text-muted">
                                        <?php 
                                        $formatted_ids = array_map(function($id) {
                                            return '#' . str_pad($id, 4, '0', STR_PAD_LEFT);
                                        }, $payment_ids);
                                        echo implode(', ', array_slice($formatted_ids, 0, 3));
                                        if (count($formatted_ids) > 3) echo ', ...';
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <span class="payment-id">#<?php echo str_pad($payment_ids[0], 4, '0', STR_PAD_LEFT); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $order_ids = explode(',', $payment['order_ids']);
                                if (count($order_ids) > 1): ?>
                                    <span><?php echo count($order_ids); ?> orders</span>
                                    <div class="small text-muted">
                                        <?php 
                                        $formatted_order_ids = array_map(function($id) {
                                            return '<a href="view_order.php?id=' . $id . '" class="order-link">#' . 
                                                   str_pad($id, 4, '0', STR_PAD_LEFT) . '</a>';
                                        }, $order_ids);
                                        echo implode(', ', array_slice($formatted_order_ids, 0, 3));
                                        if (count($formatted_order_ids) > 3) echo ', ...';
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <a href="view_order.php?id=<?php echo $order_ids[0]; ?>" class="order-link">
                                        #<?php echo str_pad($order_ids[0], 4, '0', STR_PAD_LEFT); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>Table <?php echo htmlspecialchars($payment['table_number']); ?></td>
                            <td>
                                <div class="order-items">
                                    <span class="item-count"><?php echo intval($payment['item_count']); ?> items</span>
                                    <div class="item-details small text-muted">
                                        <?php echo !empty($payment['items_list']) ? htmlspecialchars($payment['items_list']) : 'No items'; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="payment-amount">RM <?php echo number_format(customRound($payment['total_amount']), 2); ?></span>
                                <?php if (count($payment_ids) > 1): ?>
                                <div class="small text-muted">
                                    <?php 
                                    $amount_details = array_map(function($amt) {
                                        return 'RM ' . number_format(customRound(floatval($amt)), 2);
                                    }, $payment['payment_amounts_array']);
                                    echo implode(', ', array_slice($amount_details, 0, 3));
                                    if (count($amount_details) > 3) echo ', ...';
                                    ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                // Get unique payment methods for this group
                                $unique_methods = array_unique($payment['payment_methods']);
                                if (count($unique_methods) == 1): 
                                    $method = $unique_methods[0];
                                    $icon = '';
                                    switch($method) {
                                        case 'cash':
                                            $icon = 'fas fa-money-bill-wave';
                                            break;
                                        case 'card':
                                            $icon = 'fas fa-credit-card';
                                            break;
                                        case 'tng':
                                            $icon = 'fas fa-mobile-alt';
                                            break;
                                        default:
                                            $icon = 'fas fa-question-circle';
                                    }
                                ?>
                                <span class="payment-method <?php echo $method; ?>">
                                    <i class="<?php echo $icon; ?>"></i>
                                    <?php echo strtoupper($method); ?>
                                </span>
                                <?php if ($method == 'tng' && !empty($payment['tng_references'][0])): ?>
                                <div class="small text-muted">
                                    Ref: <?php echo htmlspecialchars($payment['tng_references'][0]); ?>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="payment-method mixed">
                                    <i class="fas fa-layer-group"></i>
                                    Mixed
                                </span>
                                <div class="small text-muted">
                                    <?php echo implode(', ', array_map('strtoupper', array_slice($unique_methods, 0, 2))); ?>
                                    <?php if (count($unique_methods) > 2) echo ', ...'; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="cash-received">RM <?php echo number_format($payment['cash_received'], 2); ?></span>
                            </td>
                            <td>
                                <span class="change-amount">RM <?php echo number_format($payment['change_amount'], 2); ?></span>
                            </td>
                            <td>
                                <?php 
                                // Check if all payments in the group have the same status
                                $all_completed = true;
                                foreach ($payment['payment_statuses'] as $status) {
                                    if ($status !== 'completed') {
                                        $all_completed = false;
                                        break;
                                    }
                                }
                                
                                if ($all_completed): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i>
                                    Completed
                                </span>
                                <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-clock"></i>
                                    Mixed Status
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                echo isset($payment['processed_by_name']) && $payment['processed_by_name'] !== ''
                                    ? htmlspecialchars($payment['processed_by_name'])
                                    : '-';
                                ?>
                            </td>
                            <td>
                                <span class="payment-date">
                                    <?php echo date('d M Y, h:i A', strtotime($payment['latest_payment_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary print-receipt" 
                                        data-payment-id="<?php echo explode(',', $payment['payment_ids'])[0]; ?>">
                                    <i class="fas fa-print"></i> Print Receipt
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="total-payments">
                Showing <?php echo count($payments); ?> grouped entries (<?php echo $total_payments; ?> total payments)
            </div>
            <a href="export_payments.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="export-btn"
               target="_blank">
                <i class="fas fa-download"></i>
                Export to Excel
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Include external CSS file
$extra_css = '<link rel="stylesheet" href="css/payment_details.css">';

// Add custom JavaScript
$extra_js = '
<script>
    // Date filter functionality
    document.querySelector(".date-inputs").addEventListener("submit", function(e) {
        e.preventDefault();
        const startDate = document.getElementById("start_date").value;
        const endDate = document.getElementById("end_date").value;
        
        // Show loading state
        const filterBtn = document.querySelector(".filter-btn");
        const originalBtnText = filterBtn.innerHTML;
        filterBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Loading...`;
        filterBtn.disabled = true;

        // Redirect to the same page with new date parameters
        window.location.href = `payment_details.php?start_date=${startDate}&end_date=${endDate}`;
    });
    
    // Print receipt functionality
    document.addEventListener("click", function(e) {
        if (e.target.classList.contains("print-receipt") || e.target.closest(".print-receipt")) {
            const button = e.target.classList.contains("print-receipt") ? e.target : e.target.closest(".print-receipt");
            const paymentId = button.getAttribute("data-payment-id");
            
            // Show loading state
            const originalBtnText = button.innerHTML;
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Printing...`;
            button.disabled = true;
            
            // Call the print function
            printPaymentReceipt(paymentId);
            
            // Reset button state after a delay
            setTimeout(function() {
                button.innerHTML = originalBtnText;
                button.disabled = false;
            }, 1500);
        }
    });
    
    function printPaymentReceipt(paymentId) {
        const iframe = document.createElement("iframe");
        iframe.style.display = "none";
        
        iframe.onload = function() {
            try {
                setTimeout(function() {
                    iframe.contentWindow.print();
                    setTimeout(function() {
                        document.body.removeChild(iframe);
                    }, 3000);
                }, 500);
            } catch (error) {
                console.error("Error printing receipt:", error);
                alert("There was an error printing the receipt. Please try again.");
                document.body.removeChild(iframe);
            }
        };
        
        iframe.src = "print_receipt.php?payment_id=" + paymentId;
        document.body.appendChild(iframe);
    }
</script>';

// Include the layout
include 'includes/layout.php';
?> 