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

// Check if user has permission to view completed orders
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('view_orders', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Completed Orders') . '&type=warning');
    exit();
}

// Get date range from query parameters or default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get completed orders
$completed_orders = $orderModel->getCompletedOrders($start_date, $end_date);

// Set page title
$page_title = "Completed Orders";

// Start output buffering
ob_start();
?>

<link rel="stylesheet" href="css/completed_orders.css">

<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-check-circle"></i>
            Completed Orders
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
                        <th>Items</th>
                        <th>Special Instructions</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Completed At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completed_orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x mb-3 text-muted d-block"></i>
                            No completed orders found for the selected date range
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $grouped_orders = [];
                        // Group orders by table number and completion time
                        foreach ($completed_orders as $order) {
                            $key = $order['table_number'] . '_' . $order['created_at'];
                            if (!isset($grouped_orders[$key])) {
                                $grouped_orders[$key] = [
                                    'id' => [],
                                    'table_number' => $order['table_number'],
                                    'items' => [],
                                    'special_instructions' => [],
                                    'total_amount' => 0,
                                    'created_at' => $order['created_at']
                                ];
                            }
                            $grouped_orders[$key]['id'][] = $order['id'];
                            $grouped_orders[$key]['items'][] = $order['items_list'];
                            if (!empty($order['special_instructions'])) {
                                $grouped_orders[$key]['special_instructions'] = array_merge(
                                    $grouped_orders[$key]['special_instructions'],
                                    $order['special_instructions']
                                );
                            }
                            $grouped_orders[$key]['total_amount'] += $order['total_amount'];
                        }

                        foreach ($grouped_orders as $group): 
                        ?>
                        <tr>
                            <td>
                                <span class="order-id">
                                    #<?php echo implode(', #', array_map(function($id) {
                                        return str_pad($id, 4, '0', STR_PAD_LEFT);
                                    }, $group['id'])); ?>
                                </span>
                            </td>
                            <td>Table <?php echo htmlspecialchars($group['table_number']); ?></td>
                            <td>
                                <div class="order-items">
                                    <span class="item-count"><?php echo count($group['items']); ?> items</span>
                                    <div class="item-details small text-muted">
                                        <?php echo htmlspecialchars(implode(', ', $group['items'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="special-instructions">
                                    <?php if (!empty($group['special_instructions'])): ?>
                                        <?php foreach ($group['special_instructions'] as $instruction): ?>
                                            <div class="instruction-item">
                                                <strong><?php echo htmlspecialchars($instruction['item']); ?></strong>
                                                <span><?php echo htmlspecialchars($instruction['instructions']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No special instructions</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="order-amount">RM <?php echo number_format(customRound($group['total_amount']), 2); ?></span>
                            </td>
                            <td>
                                <span class="status-badge completed">
                                    <i class="fas fa-check-circle"></i>
                                    Completed
                                </span>
                            </td>
                            <td>
                                <span class="order-date">
                                    <?php 
                                    $date = !empty($group['created_at']) ? 
                                        date('d M Y, h:i A', strtotime($group['created_at'])) : 
                                        'Date not available';
                                    echo $date;
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php foreach ($group['id'] as $orderId): ?>
                                <a href="view_order.php?id=<?php echo $orderId; ?>" 
                                   class="btn btn-sm btn-outline-primary mb-1">
                                    <i class="fas fa-eye"></i> View #<?php echo str_pad($orderId, 4, '0', STR_PAD_LEFT); ?>
                                </a>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="total-orders">
                Total Completed Orders: <?php echo count($completed_orders); ?>
            </div>
            <a href="export_completed_orders.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
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
        fetch(`completed_orders.php?start_date=${startDate}&end_date=${endDate}&ajax=true`)
            .then(response => response.text())
            .then(html => {
                // Update only the table content
                document.querySelector(".orders-container").innerHTML = html;
                
                // Update URL without reloading
                const newUrl = `completed_orders.php?start_date=${startDate}&end_date=${endDate}`;
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
