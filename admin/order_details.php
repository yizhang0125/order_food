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

// Get order ID from URL
$order_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

// Get order details
    $order = $orderModel->getOrder($order_id);

    if (!$order) {
    header('Location: orders.php');
    exit();
}

$page_title = "Order Details #" . str_pad($order_id, 4, '0', STR_PAD_LEFT);
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Back button and header -->
    <div class="d-flex align-items-center mb-4">
        <a href="orders.php" class="btn btn-link text-dark p-0 me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="page-title mb-0">
            Order Details
            <span class="order-number">#<?php echo str_pad($order_id, 4, '0', STR_PAD_LEFT); ?></span>
        </h1>
    </div>

    <div class="row">
        <!-- Order Summary Card -->
        <div class="col-lg-4 mb-4">
            <div class="card order-summary">
                <div class="card-body">
                    <h5 class="card-title mb-4">Order Summary</h5>
                    
                    <div class="summary-item">
                        <span class="label">Status</span>
                            <?php
                            $status_colors = [
                                'pending' => 'warning',
                                'processing' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $status_color = $status_colors[$order['status']] ?? 'secondary';
                            ?>
                        <span class="status-badge <?php echo $order['status']; ?>">
                            <i class="fas fa-<?php 
                                echo match($order['status']) {
                                    'completed' => 'check-circle',
                                    'processing' => 'clock',
                                    'pending' => 'hourglass',
                                    'cancelled' => 'times-circle',
                                    default => 'info-circle'
                                };
                            ?>"></i>
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>

                    <div class="summary-item">
                        <span class="label">Table Number</span>
                        <span class="value">Table <?php echo htmlspecialchars($order['table_number']); ?></span>
                                </div>

                    <div class="summary-item">
                        <span class="label">Order Date</span>
                        <span class="value"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        
                    <div class="summary-item">
                        <span class="label">Total Amount</span>
                        <span class="value amount">RM <?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>

                    <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                    <div class="mt-4">
                        <button type="button" class="btn btn-primary w-100 mb-2" 
                                data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class="fas fa-edit me-2"></i>Update Status
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Items Card -->
        <div class="col-lg-8 mb-4">
            <div class="card order-items">
                <div class="card-body">
                    <h5 class="card-title mb-4">Order Items</h5>
                    
                    <div class="table-responsive">
                        <table class="table items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                        <div class="item-image">
                                                <?php if (!empty($item['image_path'])): 
                                                    // Remove any duplicate paths if they exist
                                                    $image_path = str_replace('uploads/menu_items/', '', $item['image_path']);
                                                ?>
                                                <img src="/food1/uploads/menu_items/<?php echo htmlspecialchars($image_path); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-utensils"></i>
                                            <?php endif; ?>
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="item-price text-muted small">RM <?php echo number_format($item['price'] * 1.06, 2); ?> (Incl. 6% SST)</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="quantity"><?php echo $item['quantity']; ?></span>
                                    </td>
                                    <td>
                                        <div>RM <?php echo number_format($item['price'], 2); ?></div>
                                        <div class="text-muted small">+RM <?php echo number_format($item['price'] * 0.06, 2); ?> SST</div>
                                    </td>
                                    <td>
                                        <div>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                        <div class="text-muted small">+RM <?php echo number_format($item['price'] * $item['quantity'] * 0.06, 2); ?> SST</div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end">Subtotal:</td>
                                    <td>RM <?php echo number_format($order['total_amount'] / 1.06, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end">SST (6%):</td>
                                    <td>RM <?php echo number_format($order['total_amount'] - ($order['total_amount'] / 1.06), 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Total Amount:</td>
                                    <td class="fw-bold">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="status-info p-3 bg-light rounded-3 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <div class="text-muted small mb-1">Current Status</div>
                                <span class="status-badge <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <div class="status-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            <div class="flex-grow-1">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <label class="form-label text-muted small mb-1">New Status</label>
                                <select name="new_status" class="form-select">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
:root {
    --primary: #4f46e5;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.order-number {
    color: var(--primary);
    font-size: 1.5rem;
}

.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    background: white;
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
}

.order-summary .summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--gray-200);
}

.order-summary .summary-item:last-child {
    border-bottom: none;
}

.summary-item .label {
    color: var(--gray-600);
    font-weight: 500;
}

.summary-item .value {
    font-weight: 600;
    color: var(--gray-800);
}

.summary-item .amount {
    font-size: 1.1rem;
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

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.items-table {
    margin: 0;
}

.items-table th {
    background: var(--gray-50);
    padding: 1rem;
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.items-table td {
    padding: 1rem;
    vertical-align: middle;
}

.item-image {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    overflow: hidden;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-image i {
    font-size: 2rem;
    color: var(--gray-500);
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: 500;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.quantity {
    background: var(--gray-100);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 500;
    color: var(--gray-700);
}

.btn-link {
    text-decoration: none;
}

.btn-link:hover {
    color: var(--primary) !important;
}

@media (max-width: 768px) {
    .order-summary {
        margin-bottom: 2rem;
    }
    
    .items-table {
        white-space: nowrap;
    }
    
    .item-image {
        width: 60px;
        height: 60px;
    }
}
</style>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?> 