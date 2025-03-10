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

$page_title = 'Orders';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Get total orders for pagination
    $total_orders = $orderModel->getTotalOrders();
    $total_pages = ceil($total_orders / $per_page);
    
    // Get orders for current page
    $orders = $orderModel->getOrders($page, $per_page, $search);
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Custom CSS
$extra_css = '
<style>
.order-table th, .order-table td {
    vertical-align: middle;
}
.status-badge {
    min-width: 100px;
    text-align: center;
}
.search-box {
    max-width: 300px;
}
</style>';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Orders</h1>
            <p class="text-muted">Manage restaurant orders</p>
        </div>
        <div class="d-flex gap-2">
            <form class="d-flex search-box">
                <input type="search" class="form-control" placeholder="Search orders..." 
                       name="search" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary ms-2">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover order-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Table</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td>Table <?php echo htmlspecialchars($order['table_number']); ?></td>
                            <td>
                                <span class="text-muted"><?php echo $order['item_count']; ?> items</span>
                                <?php if (!empty($order['items'])): ?>
                                <div class="small text-muted text-truncate" style="max-width: 300px;">
                                    <?php echo htmlspecialchars($order['items']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($order['status']) {
                                        'completed' => 'success',
                                        'processing' => 'primary',
                                        'pending' => 'warning',
                                        default => 'secondary'
                                    };
                                ?> status-badge">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="view_order.php?id=<?php echo $order['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-success"
                                            onclick="updateStatus(<?php echo $order['id']; ?>, 'completed')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">No orders found</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for order status updates
$extra_js = '
<script>
function updateStatus(orderId, status) {
    if (confirm("Are you sure you want to update this order\'s status?")) {
        fetch(`update_order_status.php?id=${orderId}&status=${status}`, {
            method: "POST"
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || "Error updating order status");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Error updating order status");
        });
    }
}
</script>';

// Include the layout template
include 'includes/layout.php';
?> 