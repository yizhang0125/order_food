<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = "Navbar Notification Test";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Include Layout -->
    <?php include 'includes/layout.php'; ?>
    
    <div class="container-fluid py-4" style="margin-top: 70px;">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-bell"></i> Navbar Notification Test
                </h1>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> How to Use:</h5>
                    <p>Click the buttons below to test the navbar notification system. Check the bell icon in the navbar to see notifications.</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h3><i class="fas fa-utensils"></i> Restaurant Notifications</h3>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="notifyNewOrder(1, ['Pizza', 'Salad'], '0015')">
                                <i class="fas fa-plus"></i> New Order
                            </button>
                            <button class="btn btn-success" onclick="notifyPaymentCompleted(2, '67.80', 'TNG Pay', '0014')">
                                <i class="fas fa-money-bill-wave"></i> Payment Completed
                            </button>
                            <button class="btn btn-info" onclick="notifyOrderReady(3, '0013')">
                                <i class="fas fa-check-circle"></i> Order Ready
                            </button>
                            <button class="btn btn-warning" onclick="notifyOrderCancelled(4, '0012', 'Customer request')">
                                <i class="fas fa-times-circle"></i> Order Cancelled
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h3><i class="fas fa-cog"></i> System Notifications</h3>
                        <div class="d-grid gap-2">
                            <button class="btn btn-secondary" onclick="notifyTableStatusChange(5, 'occupied')">
                                <i class="fas fa-table"></i> Table Status
                            </button>
                            <button class="btn btn-dark" onclick="notifyStaffAction('John Doe', 'logged in')">
                                <i class="fas fa-user"></i> Staff Action
                            </button>
                            <button class="btn btn-danger" onclick="notifyError('Database connection failed', 'Check server status')">
                                <i class="fas fa-exclamation-circle"></i> Error
                            </button>
                            <button class="btn btn-outline-primary" onclick="notifyInfo('Custom Info', 'This is a custom info notification')">
                                <i class="fas fa-info-circle"></i> Info
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h3><i class="fas fa-tools"></i> Management</h3>
                        <div class="d-grid gap-2 d-md-flex">
                            <button class="btn btn-outline-secondary" onclick="markAllAsRead()">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                            <button class="btn btn-outline-danger" onclick="clearAllNotifications()">
                                <i class="fas fa-trash"></i> Clear All
                            </button>
                            <button class="btn btn-outline-info" onclick="refreshNotifications()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h3><i class="fas fa-code"></i> Correct Functions to Use</h3>
                        <div class="card">
                            <div class="card-body">
                                <h5>Basic Function:</h5>
                                <pre><code>addNotification(type, title, message, icon, color)</code></pre>
                                
                                <h5>Restaurant Functions:</h5>
                                <pre><code>notifyNewOrder(tableNumber, items, orderId)
notifyPaymentCompleted(tableNumber, amount, method, orderId)
notifyOrderReady(tableNumber, orderId)
notifyOrderCancelled(tableNumber, orderId, reason)
notifyTableStatusChange(tableNumber, status)
notifyStaffAction(staffName, action)</code></pre>
                                
                                <h5>System Functions:</h5>
                                <pre><code>notifyError(message, details)
notifyInfo(title, message)
notifyWarning(title, message)</code></pre>
                                
                                <h5>Management Functions:</h5>
                                <pre><code>markAllAsRead()
clearAllNotifications()
refreshNotifications()</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Navbar Notification System -->
    <script src="js/navbar_notifications.js"></script>
</body>
</html>
