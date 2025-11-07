<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Dashboard.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$dashboard = new Dashboard($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user info - all logged-in users can access dashboard
$page_title = 'Dashboard';

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff') {
    $username = $_SESSION['staff_name'] ?? 'Staff';
    $staff_permissions = $_SESSION['staff_permissions'] ?? [];
} else {
    $username = $_SESSION['admin_username'] ?? 'Admin';
}

try {
    // Fetch all dashboard data in one go
    $dashboardData = $dashboard->getDashboardData();
    $stats = $dashboardData['stats'];
    $recentOrders = $dashboardData['recentOrders'];
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Add CSS Styles
$extra_css = include('includes/dashboard_styles.php');

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1 class="welcome-message">Welcome back, <?php echo htmlspecialchars($username); ?></h1>
        <p class="date-display"><?php echo date("l, F j, Y"); ?></p>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-4">
        <?php if ($_SESSION['user_type'] === 'admin' || in_array('view_orders', $staff_permissions) || in_array('all', $staff_permissions)): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card" style="color: var(--primary);">
                <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h6 class="stat-title">Today's Orders</h6>
                <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>12.5% vs yesterday</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['user_type'] === 'admin' || in_array('view_sales', $staff_permissions) || in_array('all', $staff_permissions)): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card" style="color: var(--success);">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h6 class="stat-title">Total Daily Sales</h6>
                <div class="stat-value">RM <?php echo number_format($stats['total_daily_sales'] ?? 0, 2); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-info-circle"></i>
                    <span>Total sales for today</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card" style="color: var(--info);">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                    <i class="fas fa-table"></i>
                </div>
                <h6 class="stat-title">Total Tables</h6>
                <div class="stat-value"><?php echo $stats['total_tables'] ?? 0; ?></div>
                <div class="stat-change">
                    <i class="fas fa-info-circle"></i>
                    <span>Total Restaurant Tables</span>
                </div>
            </div>
        </div>

        <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card" style="color: var(--warning);">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                    <i class="fas fa-utensils"></i>
                </div>
                <h6 class="stat-title">Menu Items</h6>
                <div class="stat-value"><?php echo $stats['total_items'] ?? 0; ?></div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-down"></i>
                    <span>2.1% vs last week</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($_SESSION['user_type'] === 'admin' || (isset($staff_permissions) && (in_array('view_orders', $staff_permissions) || in_array('all', $staff_permissions)))): ?>
    <!-- Recent Orders -->
    <div class="recent-orders">
        <div class="orders-header">
            <h5 class="orders-title">Recent Orders</h5>
            <a href="orders.php" class="view-all">
                View All Orders
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Table</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td>
                            <span class="order-id">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></span>
                        </td>
                        <td>Table <?php echo htmlspecialchars($order['table_name']); ?></td>
                        <td><?php echo $order['item_count']; ?> items</td>
                        <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="status-badge <?php echo $order['status']; ?>">
                                <i class="fas fa-<?php 
                                    echo match($order['status']) {
                                        'completed' => 'check-circle',
                                        'processing' => 'clock',
                                        'pending' => 'hourglass',
                                        default => 'info-circle'
                                    };
                                ?>"></i>
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('h:i A', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>