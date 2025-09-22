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
    $orders = $orderModel->getOrders(1, 1000, $search); // Get all orders (limit 1000 to prevent memory issues)
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $orders = [];
}

$page_title = "Orders";
ob_start();
?>

<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-shopping-cart"></i>
            Active Orders
        </h1>
    </div>

    <div class="date-filter">
        <form class="date-inputs">
            <input type="text" class="form-control" name="search" placeholder="Search orders..." 
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
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x mb-3 text-muted d-block"></i>
                            No active orders found
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

<style>
:root {
    --primary: #4f46e5;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;
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

.order-id {
    font-weight: 600;
    color: var(--primary);
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

.status-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-badge.processing {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.order-amount {
    font-weight: 600;
    color: var(--gray-800);
}

.order-date {
    color: var(--gray-500);
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


.btn-outline-primary {
    color: var(--primary);
    border-color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
}

.special-instructions {
    max-width: 250px;
    font-size: 0.9rem;
}

.instruction-item {
    background: rgba(79, 70, 229, 0.1);
    padding: 0.5rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.instruction-item:last-child {
    margin-bottom: 0;
}

.instruction-item strong {
    color: var(--primary);
    display: block;
    margin-bottom: 0.25rem;
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
</style>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?> 