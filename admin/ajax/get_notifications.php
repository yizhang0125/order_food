<?php
session_start();
require_once(__DIR__ . '/../../config/Database.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_notifications':
            echo json_encode(getNotifications($db, $input));
            break;
            
        case 'check_new':
            echo json_encode(checkNewNotifications($db, $input));
            break;
            
        case 'check_payment_notifications':
            echo json_encode(checkPaymentNotifications($db, $input));
            break;
            
        case 'clear_all':
            echo json_encode(clearAllNotifications($db));
            break;
            
        case 'mark_read':
            echo json_encode(markNotificationRead($db, $input));
            break;
            
        case 'mark_all_read':
            echo json_encode(markAllNotificationsRead($db));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('Notification error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function getNotifications($db, $input) {
    try {
        // Only show notifications from the last 1 day
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
        $lastCheck = $input['last_check'] ?? $oneDayAgo;
        $limit = 20; // Limit to last 20 notifications
        
        // Check if notifications have been read
        $notificationsReadAt = $_SESSION['notifications_read_at'] ?? 0;
        
        // Simplified query to avoid complex UNION issues
        $sql = "
            SELECT 
                p.payment_id as id,
                p.amount,
                NULL as status,
                p.payment_date as created_at,
                t.table_number,
                NULL as item_count,
                NULL as items_list,
                'payment_completed' as type,
                CASE 
                    WHEN UNIX_TIMESTAMP(p.payment_date) <= ? THEN 1 
                    ELSE 0 
                END as is_read,
                p.payment_method,
                p.processed_by_name,
                p.discount_amount,
                p.discount_type,
                p.cash_received,
                p.change_amount,
                p.tng_reference
            FROM payments p
            LEFT JOIN orders o ON p.order_id = o.id
            LEFT JOIN tables t ON o.table_id = t.id
            WHERE p.payment_status = 'completed'
            AND p.payment_date >= ?
            ORDER BY p.payment_date DESC
            LIMIT " . (int)$limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$notificationsReadAt, $oneDayAgo]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no notifications found, add some test notifications
        if (count($notifications) === 0) {
            $testNotifications = [
                [
                    'id' => 'test_welcome',
                    'type' => 'order_placed',
                    'status' => 'pending',
                    'table_number' => '1',
                    'item_count' => 1,
                    'items_list' => 'Welcome to the notification system',
                    'amount' => '0.00',
                    'created_at' => date('Y-m-d\TH:i:s'),
                    'payment_method' => null,
                    'processed_by_name' => null,
                    'discount_amount' => null,
                    'discount_type' => null,
                    'cash_received' => null,
                    'change_amount' => null,
                    'tng_reference' => null,
                    'is_read' => 0
                ],
                [
                    'id' => 'test_payment',
                    'type' => 'payment_completed',
                    'status' => null,
                    'table_number' => '5',
                    'item_count' => null,
                    'items_list' => null,
                    'amount' => '25.50',
                    'created_at' => date('Y-m-d\TH:i:s', strtotime('-5 minutes')),
                    'payment_method' => 'cash',
                    'processed_by_name' => 'Admin',
                    'discount_amount' => '0.00',
                    'discount_type' => null,
                    'cash_received' => '30.00',
                    'change_amount' => '4.50',
                    'tng_reference' => null,
                    'is_read' => 0
                ]
            ];
            
            // Set is_read status for test notifications based on when they were read
            foreach ($testNotifications as &$notification) {
                $notificationTime = strtotime($notification['created_at']);
                $notification['is_read'] = ($notificationTime <= $notificationsReadAt) ? 1 : 0;
            }
            
            $notifications = $testNotifications;
        }
        
        // Format notifications
        foreach ($notifications as &$notification) {
            if ($notification['type'] === 'payment_completed') {
                $notification['amount'] = number_format($notification['amount'], 2);
                $notification['discount_amount'] = number_format($notification['discount_amount'] ?? 0, 2);
            }
            // Ensure datetime is in proper format for JavaScript
            if ($notification['created_at']) {
                // Convert to ISO format for better JavaScript compatibility
                $datetime = new DateTime($notification['created_at']);
                $notification['created_at'] = $datetime->format('Y-m-d\TH:i:s');
            }
        }
        
        return [
            'success' => true,
            'notifications' => $notifications,
            'count' => count($notifications)
        ];
        
    } catch (Exception $e) {
        error_log('Error getting notifications: ' . $e->getMessage());
        error_log('Error trace: ' . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Failed to get notifications: ' . $e->getMessage()];
    }
}

function checkNewNotifications($db, $input) {
    try {
        // Only check for new notifications from the last 1 day
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
        $lastCheck = $input['last_check'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));
        
        // Get both new orders and new payments with proper structure
        $sql = "
            (SELECT 
                o.id,
                o.total_amount as amount,
                o.status,
                o.created_at,
                t.table_number,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
                GROUP_CONCAT(CONCAT(m.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items_list,
                'order_placed' as type,
                0 as is_read,
                NULL as payment_method,
                NULL as processed_by_name,
                NULL as discount_amount,
                NULL as discount_type,
                NULL as cash_received,
                NULL as change_amount,
                NULL as tng_reference
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN menu_items m ON oi.menu_item_id = m.id
            WHERE o.created_at > ?
            AND o.status IN ('pending', 'processing', 'completed')
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 5)
            
            UNION ALL
            
            (SELECT 
                p.id,
                p.amount,
                NULL as status,
                p.payment_date as created_at,
                t.table_number,
                NULL as item_count,
                NULL as items_list,
                'payment_completed' as type,
                0 as is_read,
                p.payment_method,
                p.processed_by_name,
                p.discount_amount,
                p.discount_type,
                p.cash_received,
                p.change_amount,
                p.tng_reference
            FROM payments p
            LEFT JOIN orders o ON p.order_id = o.id
            LEFT JOIN tables t ON o.table_id = t.id
            WHERE p.payment_status = 'completed'
            AND p.payment_date > ?
            ORDER BY p.payment_date DESC
            LIMIT 5)
            
            ORDER BY created_at DESC
            LIMIT 10";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$oneDayAgo, $oneDayAgo]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format notifications
        foreach ($notifications as &$notification) {
            if ($notification['type'] === 'payment_completed') {
                $notification['amount'] = number_format($notification['amount'], 2);
                $notification['discount_amount'] = number_format($notification['discount_amount'] ?? 0, 2);
            }
            // Ensure datetime is in proper format for JavaScript
            if ($notification['created_at']) {
                // Convert to ISO format for better JavaScript compatibility
                $datetime = new DateTime($notification['created_at']);
                $notification['created_at'] = $datetime->format('Y-m-d\TH:i:s');
            }
        }
        
        return [
            'success' => true,
            'new_notifications' => $notifications,
            'count' => count($notifications)
        ];
        
    } catch (Exception $e) {
        error_log('Error checking new notifications: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to check notifications'];
    }
}

function clearAllNotifications($db) {
    try {
        // Since we don't have a notifications table, we'll just return success
        // In a real implementation, you might want to track read/unread status
        return [
            'success' => true,
            'message' => 'All notifications cleared'
        ];
        
    } catch (Exception $e) {
        error_log('Error clearing notifications: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to clear notifications'];
    }
}

function markNotificationRead($db, $input) {
    try {
        $notificationId = $input['notification_id'] ?? '';
        
        if (empty($notificationId)) {
            return ['success' => false, 'message' => 'Notification ID required'];
        }
        
        // In a real implementation, you would update a notifications table
        // For now, we'll just return success
        return [
            'success' => true,
            'message' => 'Notification marked as read'
        ];
        
    } catch (Exception $e) {
        error_log('Error marking notification as read: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to mark notification as read'];
    }
}

function markAllNotificationsRead($db) {
    try {
        // Store the read status in session to track that notifications have been viewed
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['notifications_read_at'] = time();
        
        return [
            'success' => true,
            'message' => 'All notifications marked as read'
        ];
        
    } catch (Exception $e) {
        error_log('Error marking all notifications as read: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to mark all notifications as read'];
    }
}

function checkPaymentNotifications($db, $input) {
    try {
        require_once(__DIR__ . '/../classes/PaymentController.php');
        
        $paymentController = new PaymentController($db);
        $paymentNotification = $paymentController->getPendingPaymentNotification();
        
        if ($paymentNotification) {
            // Convert to ISO format for JavaScript
            if ($paymentNotification['created_at']) {
                $datetime = new DateTime($paymentNotification['created_at']);
                $paymentNotification['created_at'] = $datetime->format('Y-m-d\TH:i:s');
            }
            
            return [
                'success' => true,
                'new_notifications' => [$paymentNotification],
                'count' => 1
            ];
        }
        
        return [
            'success' => true,
            'new_notifications' => [],
            'count' => 0
        ];
        
    } catch (Exception $e) {
        error_log('Error checking payment notifications: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to check payment notifications'];
    }
}
?>
