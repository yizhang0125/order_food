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

// Get user info from session or database
$username = $_SESSION['username'] ?? 'Admin';
$page_title = 'Dashboard';

try {
    // Fetch all dashboard data in one go
    $dashboardData = $dashboard->getDashboardData();
    $stats = $dashboardData['stats'];
    $recentOrders = $dashboardData['recentOrders'];
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Custom CSS with modern design
$extra_css = '
<style>
:root {
    --primary: #4F46E5;
    --primary-light: #818CF8;
    --success: #10B981;
    --warning: #F59E0B;
    --danger: #EF4444;
    --info: #3B82F6;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
}

.dashboard-header {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.welcome-message {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
}

.date-display {
    color: var(--gray-500);
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.stat-card::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: currentColor;
    opacity: 0.1;
    border-radius: 50%;
    transform: translate(30%, -30%);
    transition: all 0.3s ease;
}

.stat-card:hover::after {
    transform: translate(25%, -25%) scale(1.1);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

.stat-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.stat-change {
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-change.positive {
    color: var(--success);
}

.stat-change.negative {
    color: var(--danger);
}

.recent-orders {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-top: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-200);
}

.orders-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
}

.view-all {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.view-all:hover {
    transform: translateX(5px);
    color: var(--primary-light);
}

.order-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.75rem;
}

.order-table th {
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: var(--gray-600);
    background: var(--gray-50);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.order-table td {
    padding: 1rem;
    background: white;
    border-top: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200);
}

.order-table tr td:first-child {
    border-left: 1px solid var(--gray-200);
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
}

.order-table tr td:last-child {
    border-right: 1px solid var(--gray-200);
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
}

.order-id {
    font-weight: 600;
    color: var(--primary);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.processing {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.refresh-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    background: white;
    border: 1px solid var(--gray-200);
    color: var(--gray-700);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.refresh-btn:hover {
    background: var(--gray-50);
    transform: translateY(-2px);
}

.refresh-btn i {
    transition: transform 0.5s ease;
}

.refresh-btn:hover i {
    transform: rotate(180deg);
}
</style>';

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
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card" style="color: var(--primary);">
                <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h6 class="stat-title">Today\'s Orders</h6>
                <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>12.5% vs yesterday</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card" style="color: var(--success);">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h6 class="stat-title">Today\'s Revenue</h6>
                <div class="stat-value">RM <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>8.2% vs yesterday</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card" style="color: var(--info);">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                    <i class="fas fa-chair"></i>
                </div>
                <h6 class="stat-title">Active Tables</h6>
                <div class="stat-value"><?php echo $stats['active_tables'] ?? 0; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>4.3% vs last hour</span>
                </div>
            </div>
        </div>

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
    </div>

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
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for refresh functionality and animations
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const refreshBtn = document.getElementById("refreshStats");
    
    if (refreshBtn) {
        refreshBtn.addEventListener("click", function() {
            const icon = this.querySelector("i");
            icon.style.transform = "rotate(180deg)";
            setTimeout(() => location.reload(), 500);
        });
    }

    // Animate stats on load
    const statCards = document.querySelectorAll(".stat-card");
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
        }, index * 100);
    });
});
</script>';

include 'includes/layout.php';
?> 