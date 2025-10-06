<?php
// Get table number and token from URL for navbar display
$table_number = isset($_GET['table']) ? htmlspecialchars($_GET['table']) : null;
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : null;

// Check if we're on view_orders.php to show "Back to Menu" instead of "View Orders"
$current_page = basename($_SERVER['PHP_SELF']);
$is_view_orders = ($current_page === 'view_orders.php');

// Get restaurant name from system settings
try {
    require_once(__DIR__ . '/../config/Database.php');
    require_once(__DIR__ . '/../classes/SystemSettings.php');
    
    $database = new Database();
    $db = $database->getConnection();
    $systemSettings = new SystemSettings($db);
    $restaurant_name = $systemSettings->getRestaurantName();
} catch (Exception $e) {
    // Fallback to default restaurant name if there's an error
    $restaurant_name = 'Gourmet Delights';
    error_log('Customer navbar restaurant name error: ' . $e->getMessage());
}
?>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php<?php echo ($table_number && $token) ? '?table=' . $table_number . '&token=' . $token : ''; ?>">
            <i class="fas fa-utensils"></i>
            <?php echo htmlspecialchars($restaurant_name); ?>
        </a>
        <div class="d-flex align-items-center gap-3">
            <?php if ($table_number && $token): ?>
            <div class="table-info-banner">
                <span class="table-number">Table <?php echo $table_number; ?></span>
            </div>
            <?php if ($is_view_orders): ?>
            <a href="index.php?table=<?php echo $table_number; ?>&token=<?php echo $token; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Menu
            </a>
            <?php else: ?>
            <a href="view_orders.php?table=<?php echo $table_number; ?>&token=<?php echo $token; ?>" class="view-orders-btn">
                <i class="fas fa-list-ul"></i>
                View Orders
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Back button styles are now in navbar.css -->
