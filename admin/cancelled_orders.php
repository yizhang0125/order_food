<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);

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

// Check if user has permission to view cancelled orders
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('view_orders', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Cancelled Orders') . '&type=warning');
    exit();
}

// Get date range from query parameters or default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Add debugging
error_log("Fetching cancelled orders for dates: $start_date to $end_date");
$cancelled_orders = $orderModel->getCancelledOrders($start_date, $end_date);
error_log("Found " . count($cancelled_orders) . " cancelled orders");
if (!empty($cancelled_orders)) {
    error_log("First order details: " . json_encode($cancelled_orders[0]));
}

// Set page title
$page_title = "Cancelled Orders";

// Start output buffering
ob_start();
?>
<link rel="stylesheet" href="../admin/css/cancelled_orders.css">


<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-times-circle"></i>
            Cancelled Orders
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
                Filter Orders
            </button>
        </form>
    </div>

    <div class="orders-container">
        <div class="table-responsive">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Table</th>
                        <th>Items & Special Instructions</th>
                        <th>Total Amount</th>
                        <th>Cancelled At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cancelled_orders)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h4>No Cancelled Orders Today</h4>
                                <p>There are no cancelled orders for today (<?php echo date('d M Y'); ?>)</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($cancelled_orders as $order): ?>
                        <tr>
                            <td>
                                <span class="order-id">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td>
                                <span class="table-number">Table <?php echo htmlspecialchars($order['table_number']); ?></span>
                            </td>
                            <td>
                                <div class="order-items">
                                    <div class="items-list">
                                        <?php echo htmlspecialchars($order['items_list']); ?>
                                    </div>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <div class="special-instructions-list mt-2">
                                            <?php foreach ($order['special_instructions'] as $instruction): ?>
                                                <div class="special-instruction">
                                                    <span class="item-name"><?php echo htmlspecialchars($instruction['item']); ?> 
                                                    (×<?php echo $instruction['quantity']; ?>)</span>
                                                    <span class="instruction-text">
                                                        <i class="fas fa-info-circle text-primary"></i>
                                                        <?php echo htmlspecialchars($instruction['instructions']); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="order-amount">RM <?php echo number_format(customRound($order['total_amount']), 2); ?></span>
                            </td>
                            <td>
                                <span class="cancelled-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('h:i A', strtotime($order['cancelled_at'])); ?>
                            </span>
                            </td>
                            <td>
                                <a href="view_order.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .special-instructions-list {
        margin-top: 0.5rem;
        padding-left: 1rem;
        border-left: 3px solid #e2e8f0;
    }
    
    .special-instruction {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    
    .special-instruction .item-name {
        font-weight: 600;
        color: #334155;
    }
    
    .special-instruction .instruction-text {
        margin-left: 0.5rem;
        color: #dc2626;
    }
    
    .cancelled-time {
        color: #64748b;
        font-size: 0.875rem;
    }
    
    .cancelled-time i {
        margin-right: 0.25rem;
    }
</style>

<?php
$content = ob_get_clean();



// Add custom JavaScript
$extra_js = '
<script>
    // Date filter functionality using AJAX
    document.querySelector(".date-inputs").addEventListener("submit", function(e) {
        e.preventDefault();
        const startDate = document.getElementById("start_date").value;
        const endDate = document.getElementById("end_date").value;
        
        // Show loading state
        const filterBtn = document.querySelector(".filter-btn");
        const originalBtnText = filterBtn.innerHTML;
        filterBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Loading...`;
        filterBtn.disabled = true;

        // Make AJAX request
        fetch(`cancelled_orders.php?start_date=${startDate}&end_date=${endDate}&ajax=true`)
            .then(response => response.text())
            .then(html => {
                // Update only the table content
                document.querySelector(".orders-container").innerHTML = html;
                
                // Update URL without reloading
                const newUrl = `cancelled_orders.php?start_date=${startDate}&end_date=${endDate}`;
                window.history.pushState({ path: newUrl }, "", newUrl);
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading orders. Please try again.");
            })
            .finally(() => {
                // Restore button state
                filterBtn.innerHTML = originalBtnText;
                filterBtn.disabled = false;
            });
    });
</script>';


// Include the layout
include 'includes/layout.php';
?>
