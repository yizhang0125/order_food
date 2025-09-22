<?php
// This file demonstrates how to use the notification system
// Include this in your pages to add notifications
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Examples</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Restaurant Notification System Examples</h1>
        <p>Click the buttons below to test different notification types:</p>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Order Notifications</h3>
                <button class="btn btn-primary mb-2" onclick="notifyNewOrder(1, ['Pizza', 'Salad', 'Drink'], '0015')">
                    <i class="fas fa-utensils"></i> New Order
                </button>
                <button class="btn btn-success mb-2" onclick="notifyPaymentCompleted(2, '67.80', 'TNG Pay', '0014')">
                    <i class="fas fa-money-bill-wave"></i> Payment Completed
                </button>
                <button class="btn btn-info mb-2" onclick="notifyOrderReady(3, '0013')">
                    <i class="fas fa-check-circle"></i> Order Ready
                </button>
                <button class="btn btn-warning mb-2" onclick="notifyOrderCancelled(4, '0012', 'Customer request')">
                    <i class="fas fa-times-circle"></i> Order Cancelled
                </button>
            </div>
            
            <div class="col-md-6">
                <h3>System Notifications</h3>
                <button class="btn btn-secondary mb-2" onclick="notifyTableStatusChange(5, 'occupied')">
                    <i class="fas fa-table"></i> Table Status
                </button>
                <button class="btn btn-dark mb-2" onclick="notifyStaffAction('John Doe', 'logged in')">
                    <i class="fas fa-user"></i> Staff Action
                </button>
                <button class="btn btn-outline-info mb-2" onclick="notifyMenuUpdate('Burger', 'updated')">
                    <i class="fas fa-book-open"></i> Menu Update
                </button>
                <button class="btn btn-danger mb-2" onclick="notifyError('Database connection failed', 'Check server status')">
                    <i class="fas fa-exclamation-circle"></i> Error
                </button>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h3>Custom Notifications</h3>
                <button class="btn btn-outline-primary mb-2" onclick="notifyInfo('Custom Info', 'This is a custom info notification')">
                    <i class="fas fa-info-circle"></i> Info
                </button>
                <button class="btn btn-outline-warning mb-2" onclick="notifyWarning('Warning', 'This is a warning notification')">
                    <i class="fas fa-exclamation-triangle"></i> Warning
                </button>
                <button class="btn btn-outline-success mb-2" onclick="addNotification('success', 'Custom Success', 'This is a custom success notification', 'fas fa-check', 'text-success')">
                    <i class="fas fa-check"></i> Custom Success
                </button>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h3>Notification Management</h3>
                <button class="btn btn-outline-secondary mb-2" onclick="markAllNotificationsAsRead()">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
                <button class="btn btn-outline-danger mb-2" onclick="clearAllNotifications()">
                    <i class="fas fa-trash"></i> Clear All
                </button>
                <button class="btn btn-outline-info mb-2" onclick="refreshNotifications()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h3>Usage Examples</h3>
                <div class="card">
                    <div class="card-body">
                        <h5>JavaScript Examples:</h5>
                        <pre><code>// Basic notification
addNotification('info', 'Title', 'Message');

// Restaurant-specific notifications
notifyNewOrder(1, ['Pizza', 'Salad'], '0015');
notifyPaymentCompleted(2, '45.50', 'Cash', '0014');
notifyOrderReady(3, '0013');

// Custom notifications
notifyInfo('Custom Title', 'Custom message');
notifyError('Error occurred', 'Detailed error message');

// Management functions
markAllNotificationsAsRead();
clearAllNotifications();
refreshNotifications();</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Notification System -->
    <script src="js/notifications.js"></script>
</body>
</html>
