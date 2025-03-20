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
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get order details
$order = $orderModel->getOrder($order_id);

// Redirect if order not found
if (!$order) {
    header('Location: completed_orders.php');
    exit();
}

// Set page title
$page_title = "Order #" . str_pad($order_id, 4, '0', STR_PAD_LEFT);

// Start output buffering
ob_start();

$status_colors = [
    'pending' => 'warning',
    'processing' => 'info',
    'completed' => 'success',
    'cancelled' => 'danger'
];
?>

<div class="container-fluid py-4">
    <!-- Back button and header -->
    <div class="d-flex align-items-center mb-4">
        <a href="completed_orders.php" class="btn btn-link text-muted p-0 me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="page-title mb-0">
            Order Details #<?php echo str_pad($order_id, 4, '0', STR_PAD_LEFT); ?>
        </h1>
    </div>

    <div class="row">
        <!-- Order Summary Card -->
        <div class="col-12 col-lg-4 mb-4">
            <div class="card order-summary">
                <div class="card-body">
                    <h5 class="card-title">Order Summary</h5>
                    <div class="summary-item">
                        <span class="label">Status</span>
                        <span class="status-badge <?php echo strtolower($order['status']); ?>">
                            <i class="fas fa-<?php echo $order['status'] === 'cancelled' ? 'times' : 'check'; ?>-circle"></i>
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
                </div>
            </div>
        </div>

        <!-- Order Items Card -->
        <div class="col-12 col-lg-8 mb-4">
            <div class="card order-items">
                <div class="card-body">
                    <h5 class="card-title">Order Items</h5>
                    <div class="table-responsive">
                        <table class="table items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subtotal = 0;
                                foreach ($order['items'] as $item): 
                                    $item_total = $item['price'] * $item['quantity'];
                                    $subtotal += $item_total;
                                ?>
                                <tr>
                                    <td>
                                        <div class="item-info">
                                            <div class="item-image">
                                                <?php if (!empty($item['image_path'])): 
                                                    $image_path = str_replace('uploads/menu_items/', '', $item['image_path']);
                                                ?>
                                                <img src="../uploads/menu_items/<?php echo htmlspecialchars($image_path); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                <?php else: ?>
                                                <i class="fas fa-utensils"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-details">
                                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end">RM <?php echo number_format($item['price'], 2); ?></td>
                                    <td class="text-end">RM <?php echo number_format($item_total, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end">Subtotal:</td>
                                    <td class="text-end">RM <?php echo number_format($subtotal, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end">SST (6%):</td>
                                    <td class="text-end">RM <?php echo number_format($subtotal * 0.06, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Total Amount:</td>
                                    <td class="text-end fw-bold">RM <?php echo number_format($subtotal * 1.06, 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
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

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-800);
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
        margin-bottom: 1.5rem;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--gray-200);
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-item .label {
        color: var(--gray-600);
        font-weight: 500;
    }

    .summary-item .value {
        color: var(--gray-800);
        font-weight: 600;
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

    .status-badge.completed {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .status-badge.cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        font-weight: 600;
    }

    .status-badge.pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .status-badge.processing {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info);
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
        letter-spacing: 0.05em;
    }

    .items-table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .item-info {
        display: flex;
        align-items: center;
        gap: 1rem;
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
        border-radius: 8px;
    }

    .item-image i {
        font-size: 2rem;
        color: var(--gray-500);
    }

    .item-name {
        font-weight: 500;
        color: var(--gray-800);
        margin-bottom: 0.25rem;
    }

    .btn-link {
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-link:hover {
        transform: translateX(-5px);
    }

    @media (max-width: 768px) {
        .items-table {
            white-space: nowrap;
        }
        
        .item-image {
            width: 40px;
            height: 40px;
        }
    }

    tfoot tr:not(:last-child) td {
        padding: 0.75rem 1rem;
        color: var(--gray-600);
    }

    tfoot tr:last-child td {
        padding: 1rem;
        font-size: 1.1rem;
        border-top: 2px solid var(--gray-200);
        color: var(--gray-800);
    }
</style>';

// Include the layout
include 'includes/layout.php';
?> 