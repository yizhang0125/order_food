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

// Custom CSS with optimized selectors
$extra_css = '
<style>
.stat-card {
    transition: transform 0.3s ease;
    border: none;
    border-radius: 10px;
}
.stat-card:hover { transform: translateY(-5px) }
.chart-card {
    height: 400px;
    border: none;
    border-radius: 10px;
}
.activity-item {
    padding: 1rem;
    border-radius: 8px;
    transition: background-color 0.3s;
}
.activity-item:hover { background: rgba(67, 97, 238, 0.05) }
</style>';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard</h1>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($username); ?></p>
        </div>
        <button type="button" class="btn btn-light" id="refreshStats">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Today's Orders</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['total_orders'] ?? 0; ?></h2>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 p-3">
                            <i class="fas fa-shopping-cart fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Today's Revenue</h6>
                            <h2 class="mt-2 mb-0">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h2>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 p-3">
                            <i class="fas fa-dollar-sign fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Active Tables</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['active_tables'] ?? 0; ?></h2>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 p-3">
                            <i class="fas fa-chair fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Menu Items</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['total_items'] ?? 0; ?></h2>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 p-3">
                            <i class="fas fa-utensils fa-2x text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">Recent Orders</h5>
                        <a href="orders.php" class="btn btn-light btn-sm">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
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
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['table_name']); ?></td>
                                    <td><?php echo $order['item_count']; ?> items</td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($order['status']) {
                                                'completed' => 'success',
                                                'processing' => 'primary',
                                                'pending' => 'warning',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for refresh functionality
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("refreshStats").addEventListener("click", function() {
        location.reload();
    });
});
</script>';

include 'includes/layout.php';
?> 