<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

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

// Sample data for testing the design with cash rounding examples
$sample_payments = [
    [
        'payment_ids' => '1001',
        'order_ids' => '2001',
        'table_number' => '5',
        'item_count' => 3,
        'items_list' => 'Chicken Rice (2), Coke (1)',
        'total_amount' => 12.43, // Will round to 12.45
        'payment_amounts_array' => [12.43],
        'payment_methods' => ['cash'],
        'tng_references' => [''],
        'cash_received' => 15.00,
        'change_amount' => 2.55,
        'payment_statuses' => ['completed'],
        'processed_by_name' => 'John Admin',
        'latest_payment_date' => '2024-01-15 14:30:00'
    ],
    [
        'payment_ids' => '1002,1003',
        'order_ids' => '2002,2003',
        'table_number' => '8',
        'item_count' => 5,
        'items_list' => 'Nasi Lemak (1), Teh Tarik (2), Roti Canai (2)',
        'total_amount' => 18.92, // Will round to 18.90
        'payment_amounts_array' => [9.46, 9.46],
        'payment_methods' => ['tng', 'cash'],
        'tng_references' => ['1234', ''],
        'cash_received' => 20.00,
        'change_amount' => 1.10,
        'payment_statuses' => ['completed', 'completed'],
        'processed_by_name' => 'Sarah Staff',
        'latest_payment_date' => '2024-01-15 13:45:00'
    ],
    [
        'payment_ids' => '1004',
        'order_ids' => '2004',
        'table_number' => '12',
        'item_count' => 2,
        'items_list' => 'Char Kway Teow (1), Ice Lemon Tea (1)',
        'total_amount' => 8.77, // Will round to 8.75
        'payment_amounts_array' => [8.77],
        'payment_methods' => ['card'],
        'tng_references' => [''],
        'cash_received' => 0.00,
        'change_amount' => 0.00,
        'payment_statuses' => ['completed'],
        'processed_by_name' => 'Mike Manager',
        'latest_payment_date' => '2024-01-15 12:15:00'
    ],
    [
        'payment_ids' => '1005',
        'order_ids' => '2005',
        'table_number' => '3',
        'item_count' => 4,
        'items_list' => 'Fried Rice (1), Soup (1), Tea (2)',
        'total_amount' => 15.48, // Will round to 15.50
        'payment_amounts_array' => [15.48],
        'payment_methods' => ['cash'],
        'tng_references' => [''],
        'cash_received' => 20.00,
        'change_amount' => 4.50,
        'payment_statuses' => ['completed'],
        'processed_by_name' => 'Lisa Cashier',
        'latest_payment_date' => '2024-01-15 11:30:00'
    ]
];

$total_payments = 5;
$total_amount = 55.60; // Sum of rounded amounts: 12.45 + 18.90 + 8.75 + 15.50

$page_title = "Payment Details Design Test";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Restaurant Admin</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/payment_details.css" rel="stylesheet">
</head>
<body>
    <div class="main-content">
        <!-- Page content -->
        <div class="container-fluid py-4">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Payment Details Design Test
                </h1>
            </div>

            <div class="date-filter">
                <form class="date-inputs">
                    <input type="date" class="date-input" id="start_date" name="start_date" 
                           value="2024-01-01">
                    <input type="date" class="date-input" id="end_date" name="end_date" 
                           value="2024-01-15">
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
                            <?php foreach ($sample_payments as $payment): ?>
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
                                                return '<a href="#" class="order-link">#' . 
                                                       str_pad($id, 4, '0', STR_PAD_LEFT) . '</a>';
                                            }, $order_ids);
                                            echo implode(', ', array_slice($formatted_order_ids, 0, 3));
                                            if (count($formatted_order_ids) > 3) echo ', ...';
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <a href="#" class="order-link">
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
                                            return 'RM ' . number_format(customRound($amt), 2);
                                        }, $payment['payment_amounts_array']);
                                        echo implode(', ', array_slice($amount_details, 0, 3));
                                        if (count($amount_details) > 3) echo ', ...';
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="small text-muted" style="font-size: 0.75rem; color: #6b7280;">
                                        Original: RM <?php echo number_format($payment['total_amount'], 2); ?> → Rounded: RM <?php echo number_format(customRound($payment['total_amount']), 2); ?>
                                    </div>
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
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <div class="total-payments">
                        Showing <?php echo count($sample_payments); ?> grouped entries (<?php echo $total_payments; ?> total payments)
                    </div>
                    <a href="#" class="export-btn">
                        <i class="fas fa-download"></i>
                        Export to Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Date filter functionality
    document.querySelector(".date-inputs").addEventListener("submit", function(e) {
        e.preventDefault();
        alert("Date filter functionality would work here!");
    });
    
    // Print receipt functionality
    document.addEventListener("click", function(e) {
        if (e.target.classList.contains("print-receipt") || e.target.closest(".print-receipt")) {
            const button = e.target.classList.contains("print-receipt") ? e.target : e.target.closest(".print-receipt");
            const paymentId = button.getAttribute("data-payment-id");
            alert("Print receipt for payment ID: " + paymentId);
        }
    });
    </script>
</body>
</html>
