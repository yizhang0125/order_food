<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);

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
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Orders') . '&type=warning');
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Update order status if requested
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    if ($orderModel->updateStatus($order_id, $new_status)) {
        $success_message = "Order status updated successfully";
    } else {
        $error_message = "Failed to update order status";
    }
}

try {
    // Test database connection first
    if (!$orderModel->testConnection()) {
        throw new Exception("Database connection failed");
    }
    
    // Try to get recent orders first - increased limit to show more orders
    try {
        $orders = $orderModel->getRecentOrders(null, null, 100);
        error_log("Retrieved " . count($orders) . " recent orders using getRecentOrders");
    } catch (Exception $e) {
        error_log("getRecentOrders failed, trying getSimpleRecentOrders: " . $e->getMessage());
        try {
            $orders = $orderModel->getSimpleRecentOrders(100);
            error_log("Retrieved " . count($orders) . " orders using getSimpleRecentOrders");
        } catch (Exception $e2) {
            error_log("getSimpleRecentOrders failed, falling back to getOrders: " . $e2->getMessage());
            // Final fallback to the working getOrders method
            $orders = $orderModel->getOrders(1, 100, $search);
            error_log("Retrieved " . count($orders) . " orders using getOrders fallback");
        }
    }
    
    // Filter by search if provided (only if not already filtered by getOrders)
    if (!empty($search) && !isset($orders[0]['items_list'])) {
        $orders = array_filter($orders, function($order) use ($search) {
            return stripos($order['id'], $search) !== false || 
                   stripos($order['table_number'], $search) !== false ||
                   (isset($order['items_list']) && stripos($order['items_list'], $search) !== false);
        });
    }
    
} catch (Exception $e) {
    $error_message = "Error loading orders: " . $e->getMessage();
    error_log("Error in admin/orders.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $orders = [];
}

$page_title = "Orders";
ob_start();
?>
<link rel="stylesheet" href="./css/orders.css">
<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-clock"></i>
            Recent Orders
        </h1>
        <p class="text-muted mt-2">Showing the 100 most recent orders from all statuses</p>
    </div>

    <div class="date-filter">
        <form class="date-inputs">
            <input type="text" class="form-control" name="search" placeholder="Search recent orders by ID, table, or items..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="filter-btn">
                <i class="fas fa-search"></i>
                Search Orders
            </button>
        </form>
    </div>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    

    <div class="orders-container">
        <div class="table-responsive">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Table</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Special Instructions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-clock fa-2x mb-3 text-muted d-block"></i>
                            No recent orders found
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <span class="order-id">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td>Table <?php echo htmlspecialchars($order['table_number']); ?></td>
                            <td>
                                <div class="order-items">
                                    <span class="item-count"><?php echo $order['item_count']; ?> items</span>
                                    <div class="item-details small text-muted">
                                        <?php echo !empty($order['items_list']) ? htmlspecialchars($order['items_list']) : 'No items'; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="order-amount">RM <?php echo number_format($order['total_amount'], 2); ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo strtolower($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="order-date">
                                    <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="special-instructions">
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <?php foreach ($order['special_instructions'] as $instruction): ?>
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
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#statusModal<?php echo $order['id']; ?>">
                                    <i class="fas fa-edit"></i> Update
                                </button>
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>

                        <!-- Status Update Modal -->
                        <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Order Status</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Select New Status</label>
                                                <select name="new_status" class="form-select">
                                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>



<?php
$content = ob_get_clean();
include 'includes/layout.php';
?> 