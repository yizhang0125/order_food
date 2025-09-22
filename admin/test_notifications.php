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

$page_title = "Test Notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-button {
            margin: 5px;
            min-width: 200px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
        }
    </style>
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
                    <i class="fas fa-bell"></i> Notification System Test
                </h1>
                
                <!-- Order Notifications -->
                <div class="section">
                    <h3><i class="fas fa-utensils"></i> Order Notifications</h3>
                    <button class="btn btn-primary test-button" onclick="notifyNewOrder(1, ['Pizza', 'Salad', 'Drink'], '0015')">
                        <i class="fas fa-plus"></i> New Order
                    </button>
                    <button class="btn btn-success test-button" onclick="notifyPaymentCompleted(2, '67.80', 'TNG Pay', '0014')">
                        <i class="fas fa-money-bill-wave"></i> Payment Completed
                    </button>
                    <button class="btn btn-info test-button" onclick="notifyOrderReady(3, '0013')">
                        <i class="fas fa-check-circle"></i> Order Ready
                    </button>
                    <button class="btn btn-warning test-button" onclick="notifyOrderCancelled(4, '0012', 'Customer request')">
                        <i class="fas fa-times-circle"></i> Order Cancelled
                    </button>
                </div>
                
                <!-- System Notifications -->
                <div class="section">
                    <h3><i class="fas fa-cog"></i> System Notifications</h3>
                    <button class="btn btn-secondary test-button" onclick="notifyTableStatusChange(5, 'occupied')">
                        <i class="fas fa-table"></i> Table Status
                    </button>
                    <button class="btn btn-dark test-button" onclick="notifyStaffAction('John Doe', 'logged in')">
                        <i class="fas fa-user"></i> Staff Action
                    </button>
                    <button class="btn btn-outline-info test-button" onclick="notifyMenuUpdate('Burger', 'updated')">
                        <i class="fas fa-book-open"></i> Menu Update
                    </button>
                    <button class="btn btn-danger test-button" onclick="notifyError('Database connection failed', 'Check server status')">
                        <i class="fas fa-exclamation-circle"></i> Error
                    </button>
                </div>
                
                <!-- Custom Notifications -->
                <div class="section">
                    <h3><i class="fas fa-bell"></i> Custom Notifications</h3>
                    <button class="btn btn-outline-primary test-button" onclick="notifyInfo('Custom Info', 'This is a custom info notification')">
                        <i class="fas fa-info-circle"></i> Info
                    </button>
                    <button class="btn btn-outline-warning test-button" onclick="notifyWarning('Warning', 'This is a warning notification')">
                        <i class="fas fa-exclamation-triangle"></i> Warning
                    </button>
                    <button class="btn btn-outline-success test-button" onclick="addNotification('success', 'Custom Success', 'This is a custom success notification', 'fas fa-check', 'text-success')">
                        <i class="fas fa-check"></i> Custom Success
                    </button>
                </div>
                
                <!-- Management -->
                <div class="section">
                    <h3><i class="fas fa-tools"></i> Notification Management</h3>
                    <button class="btn btn-outline-secondary test-button" onclick="markAllNotificationsAsRead()">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                    <button class="btn btn-outline-danger test-button" onclick="clearAllNotifications()">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                    <button class="btn btn-outline-info test-button" onclick="refreshNotifications()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                
                <!-- Usage Examples -->
                <div class="section">
                    <h3><i class="fas fa-code"></i> Usage Examples</h3>
                    <div class="card">
                        <div class="card-body">
                            <h5>JavaScript Code Examples:</h5>
                            <pre><code>// Basic notification
addNotification('info', 'Title', 'Message');

// Restaurant notifications
notifyNewOrder(1, ['Pizza', 'Salad'], '0015');
notifyPaymentCompleted(2, '45.50', 'Cash', '0014');
notifyOrderReady(3, '0013');

// Custom notifications
notifyInfo('Custom Title', 'Custom message');
notifyError('Error occurred', 'Detailed error message');

// Management
markAllNotificationsAsRead();
clearAllNotifications();</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Notification System -->
    <script src="js/notifications.js"></script>
    
    <!-- Initialize sample notifications -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add sample notifications if none exist
        if (restaurantNotifications.getUnreadCount() === 0) {
            setTimeout(() => {
                notifyNewOrder(3, ['Burger', 'Fries'], '0012');
                notifyPaymentCompleted(5, '45.50', 'Cash', '0011');
                notifyOrderReady(2, '0010');
                notifyInfo('Welcome', 'Restaurant notification system is active');
            }, 1000);
        }
    });
    </script>
</body>
</html>
