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

// Get date range from query parameters or default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get payments with order details
try {
    // First, get all payments within the date range
    $sql = "SELECT p.payment_id, p.order_id, p.amount, p.payment_status, 
            p.payment_date, p.cash_received, p.change_amount,
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
                'latest_payment_date' => $payment['payment_date'],
                'items_list' => [],
                'item_count' => 0
            ];
        }
        
        // Add payment details
        $grouped_payments[$key]['payment_count']++;
        $grouped_payments[$key]['payment_ids'][] = $payment['payment_id'];
        $grouped_payments[$key]['order_ids'][] = $payment['order_id'];
        $grouped_payments[$key]['payment_amounts'][] = $payment['amount'];
        $grouped_payments[$key]['payment_statuses'][] = $payment['payment_status'];
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
                <p>RM <?php echo number_format($total_amount, 2); ?></p>
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
                        <th>Cash Received</th>
                        <th>Change</th>
                        <th>Payment Status</th>
                        <th>Payment Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4">
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
                                <span class="payment-amount">RM <?php echo number_format($payment['total_amount'], 2); ?></span>
                                <?php if (count($payment_ids) > 1): ?>
                                <div class="small text-muted">
                                    <?php 
                                    $amount_details = array_map(function($amt) {
                                        return 'RM ' . number_format(floatval($amt), 2);
                                    }, $payment['payment_amounts_array']);
                                    echo implode(', ', array_slice($amount_details, 0, 3));
                                    if (count($amount_details) > 3) echo ', ...';
                                    ?>
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

    .date-filter {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .date-inputs {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .date-input {
        padding: 0.75rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        font-size: 0.95rem;
    }

    .filter-btn {
        padding: 0.75rem 1.5rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .summary-icon {
        width: 60px;
        height: 60px;
        background: var(--primary);
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .summary-info h3 {
        font-size: 1rem;
        color: var(--gray-600);
        margin: 0 0 0.5rem 0;
    }

    .summary-info p {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
    }

    .orders-container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .orders-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .orders-table th {
        background: var(--gray-50);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--gray-600);
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--gray-200);
    }

    .orders-table td {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        color: var(--gray-700);
        font-size: 0.95rem;
    }

    .payment-id {
        font-weight: 600;
        color: var(--primary);
    }

    .order-link {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .order-link:hover {
        text-decoration: underline;
    }

    .payment-amount, .cash-received, .change-amount {
        font-weight: 600;
        color: var(--gray-800);
    }

    .payment-date {
        color: var(--gray-500);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .badge.bg-success {
        background-color: var(--success) !important;
    }

    .order-items {
        max-width: 300px;
    }

    .item-count {
        font-weight: 600;
        color: var(--gray-700);
        display: block;
        margin-bottom: 0.25rem;
    }

    .item-details {
        font-size: 0.85rem;
        color: var(--gray-500);
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .table-footer {
        padding: 1rem 1.5rem;
        background: var(--gray-50);
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .total-payments {
        font-weight: 600;
        color: var(--gray-700);
    }

    .export-btn {
        padding: 0.75rem 1.5rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .export-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        color: white;
    }

    .btn-outline-primary {
        color: var(--primary);
        border: 1px solid var(--primary);
        background: transparent;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-outline-primary:hover {
        background: var(--primary);
        color: white;
    }

    @media (max-width: 768px) {
        .date-inputs {
            flex-direction: column;
        }
        
        .date-input {
            width: 100%;
        }
        
        .orders-table {
            display: block;
            overflow-x: auto;
        }
    }
</style>';

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