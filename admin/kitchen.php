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

// Handle individual order item cancellation
if (isset($_POST['cancel_item'])) {
    $order_item_id = $_POST['order_item_id'];
    
    if (empty($order_item_id) || !is_numeric($order_item_id)) {
        $error_message = "Invalid order item ID";
    } else {
        if ($orderModel->cancelOrderItem($order_item_id)) {
            $success_message = "Food item cancelled successfully";
        } else {
            $error_message = "Failed to cancel food item";
        }
    }
}

// Update order status if requested
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    // Validate order ID
    if (empty($order_id) || !is_numeric($order_id)) {
        $error_message = "Invalid order ID";
    } else {
        // Add special handling for cancelled status - ALWAYS single order only
        if ($new_status === 'cancelled') {
            // For cancellation, we only handle single orders, never groups
            // Debug: Log the cancellation attempt
            error_log("Kitchen: Attempting to cancel order ID: " . $order_id);
            
            // Check if order exists and is not already cancelled
            $check_query = "SELECT id, status FROM orders WHERE id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$order_id]);
            $order_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order_check) {
                $error_message = "Order not found";
                error_log("Kitchen: Order ID " . $order_id . " not found");
            } elseif ($order_check['status'] === 'cancelled') {
                $error_message = "Order is already cancelled";
                error_log("Kitchen: Order ID " . $order_id . " is already cancelled");
            } elseif ($order_check['status'] === 'completed') {
                $error_message = "Cannot cancel a completed order";
                error_log("Kitchen: Cannot cancel completed order ID " . $order_id);
            } else {
                // Cancel ONLY this specific order
                error_log("Kitchen: Cancelling order ID " . $order_id . " (current status: " . $order_check['status'] . ")");
                if ($orderModel->updateStatus($order_id, 'cancelled')) {
                    $success_message = "Order #" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " cancelled successfully";
                    error_log("Kitchen: Successfully cancelled order ID " . $order_id);
                } else {
                    $error_message = "Failed to cancel order";
                    error_log("Kitchen: Failed to cancel order ID " . $order_id);
                }
            }
        } else {
            // For non-cancellation status updates, check if this is a combined order (multiple IDs separated by commas)
            if (strpos($order_id, ',') !== false) {
                $order_ids = explode(',', $order_id);
                $success = true;
                $updated_count = 0;
                
                // Update status for each order in the group
                foreach ($order_ids as $id) {
                    if (is_numeric($id) && $orderModel->updateStatus($id, $new_status)) {
                        $updated_count++;
                    } else {
                        $success = false;
                    }
                }
                
                if ($success) {
                    $success_message = "All orders status updated successfully";
                } else {
                    $error_message = "Failed to update some order statuses. Updated: " . $updated_count . " out of " . count($order_ids);
                }
            } else {
                // Single order update
                if ($orderModel->updateStatus($order_id, $new_status)) {
                    $status_text = ucfirst($new_status);
                    $success_message = "Order #" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " status updated to " . $status_text;
                } else {
                    $error_message = "Failed to update order status";
                }
            }
        }
    }
}

// Get orders grouped by status
try {
    $pending_orders = $orderModel->getOrdersByStatus('pending');
    $processing_orders = $orderModel->getOrdersByStatus('processing');
    $all_orders = array_merge($pending_orders ?? [], $processing_orders ?? []);
    usort($all_orders, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']); // Latest orders first
    });
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $pending_orders = [];
    $processing_orders = [];
    $all_orders = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./css/kitchen.css">
</head>
<body>
    <div class="kitchen-display">
        <header class="header">
            <div class="header-title">
                <i class="fas fa-utensils fa-lg"></i>
                <h1 class="page-title">Kitchen Display</h1>
            </div>
            <div class="header-stats">
                <div class="stat-box">
                    <i class="fas fa-clock"></i>
                    <span id="currentTime"></span>
                </div>
                <div class="stat-box">
                    <i class="fas fa-hourglass-start"></i>
                    <span>Pending: <?php echo count($pending_orders); ?></span>
                </div>
                <div class="stat-box">
                    <i class="fas fa-fire"></i>
                    <span>Cooking: <?php echo count($processing_orders); ?></span>
                </div>
                <a href="dashboard.php" class="stat-box">
                    <i class="fas fa-times"></i>
                    <span>Exit</span>
                </a>
            </div>
        </header>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom: 1.5rem; border-radius: 10px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-bottom: 1.5rem; border-radius: 10px; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (empty($all_orders)): ?>
        <div class="no-orders">
            <i class="fas fa-check-circle"></i>
            <h2>All Caught Up!</h2>
            <p>No pending orders at the moment</p>
        </div>
        <?php else: ?>
        <div class="orders-grid">
            <?php foreach ($all_orders as $order): 
                $is_pending = $order['status'] === 'pending';
                $order_time = strtotime($order['created_at']);
                $time_diff = time() - $order_time;
                $minutes = round($time_diff / 60);
                $time_warning = ($is_pending && $minutes > 30) || (!$is_pending && $minutes > 45);
            ?>
            <div class="order-card <?php echo $is_pending ? 'pending' : 'cooking'; ?>" 
                 data-order-id="<?php echo $order['id']; ?>"
                 data-status="<?php echo $is_pending ? 'pending' : 'processing'; ?>">
                <div class="order-header">
                    <div class="order-number">
                        #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>
                    </div>
                    <div class="table-info">
                        <div class="table-label">Table</div>
                        <div class="table-number"><?php echo htmlspecialchars($order['table_number']); ?></div>
                    </div>
                    <div class="time-badge <?php echo $time_warning ? 'warning' : ''; ?>" 
                         title="Order placed at <?php echo date('h:i A', $order_time); ?> (<?php echo $minutes; ?> minutes ago)">
                        <i class="fas fa-clock"></i>
                        <?php echo date('h:i A', $order_time); ?>
                    </div>
                </div>

                <div class="items-list">
                    <?php 
                    $total_items = count($order['order_items']);
                    $completed_items = 0;

                    // Display each order item with individual cancel option
                    foreach ($order['order_items'] as $index => $order_item):
                        // Find special instruction for this item
                        $item_instruction = '';
                        if (!empty($order['special_instructions'])) {
                            foreach ($order['special_instructions'] as $instruction) {
                                if ($instruction['item'] === $order_item['name']) {
                                    $item_instruction = $instruction['instructions'];
                                    break;
                                }
                            }
                        }
                    ?>
                    <div class="item" data-item-id="<?php echo $order_item['id']; ?>">
                        <div class="quantity"><?php echo $order_item['quantity']; ?>×</div>
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($order_item['name']); ?></div>
                            <?php if (!empty($item_instruction)): ?>
                                <div class="instruction-note">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($item_instruction); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-actions">
                            <button class="item-btn" onclick="toggleItemComplete(this)" title="Mark as complete">
                                <i class="fas fa-check"></i>
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirmCancelItem('<?php echo htmlspecialchars($order_item['name']); ?>')">
                                <input type="hidden" name="order_item_id" value="<?php echo $order_item['id']; ?>">
                                <button type="submit" name="cancel_item" class="item-btn cancel-item-btn" title="Cancel this food item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                </div>

                <div class="order-footer">
                    <div class="status-badge">
                        <?php if ($is_pending): ?>
                        <i class="fas fa-hourglass-start"></i> PENDING
                        <?php else: ?>
                        <i class="fas fa-fire"></i> COOKING
                        <?php endif; ?>
                    </div>
                    <div class="action-buttons">
                        <form method="POST" class="order-form" style="width: 100%;">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="new_status" 
                                   value="<?php echo $is_pending ? 'processing' : 'completed'; ?>">
                            <button type="submit" name="update_status" class="action-btn" style="width: 100%;">
                                <?php if ($is_pending): ?>
                                <i class="fas fa-fire"></i> START COOKING
                                <?php else: ?>
                                <i class="fas fa-check"></i> COMPLETE ORDER
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                    <div class="action-buttons" style="margin-top: 0.5rem;">
                        <form method="POST" class="cancel-form" onsubmit="return confirmCancel(<?php echo $order['id']; ?>)" style="width: 100%;">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="new_status" value="cancelled">
                            <button type="submit" name="update_status" class="cancel-btn" style="width: 100%;" 
                                    title="Cancel this order - this action cannot be undone">
                                <i class="fas fa-ban"></i> CANCEL ORDER
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('currentTime').textContent = timeString;
    }

    setInterval(updateTime, 1000);
    updateTime();

    // Item completion functionality
    function toggleItemComplete(button) {
        const item = button.closest('.item');
        const orderCard = item.closest('.order-card');
        const itemsList = orderCard.querySelector('.items-list');
        const items = itemsList.querySelectorAll('.item');
        const progressBar = itemsList.querySelector('.progress-fill');
        
        item.classList.toggle('completed');
        button.classList.toggle('complete');
        
        // Update progress bar
        const completedItems = itemsList.querySelectorAll('.item.completed').length;
        const totalItems = items.length;
        const progress = (completedItems / totalItems) * 100;
        progressBar.style.width = progress + '%';
        
        // If all items are completed, enable the complete order button
        const actionBtn = orderCard.querySelector('.action-btn');
        if (completedItems === totalItems) {
            actionBtn.disabled = false;
            actionBtn.style.opacity = '1';
        }
    }

    // Auto refresh every 30 seconds
    setInterval(function() {
        location.reload();
    }, 30000);

    // Play sound for new orders
    document.addEventListener('DOMContentLoaded', function() {
        let currentPendingCount = <?php echo count($pending_orders); ?>;
        
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newPendingCount = doc.querySelectorAll('.order-card.pending').length;
                    
                    if (newPendingCount > currentPendingCount) {
                        const audio = new Audio('assets/notification.mp3');
                        audio.play();
                    }
                    
                    currentPendingCount = newPendingCount;
                });
        }, 30000);

        // Request fullscreen on load
        const elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    });

    function confirmCancel(orderId) {
        return confirm('Are you sure you want to cancel Order #' + String(orderId).padStart(4, '0') + '?\n\nThis action cannot be undone and the order will be marked as cancelled.');
    }

    function confirmCancelItem(itemName) {
        return confirm('Are you sure you want to cancel "' + itemName + '"?\n\nThis will remove this food item from the order. The order total will be updated automatically.');
    }
    </script>
</body>
</html> 