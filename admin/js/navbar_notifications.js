/**
 * Navbar Notification System - Correct Functions
 * Use these functions in your navbar notification system
 */

// Initialize notification system
let notifications = JSON.parse(localStorage.getItem('navbar_notifications') || '[]');
let maxNotifications = 50;

/**
 * Add a new notification
 * @param {string} type - order, payment, kitchen, info, warning, error
 * @param {string} title - Notification title
 * @param {string} message - Notification message
 * @param {string} icon - FontAwesome icon (optional)
 * @param {string} color - Bootstrap color class (optional)
 */
function addNotification(type, title, message, icon = null, color = null) {
    // Set default icon and color based on type
    const defaults = getTypeDefaults(type);
    icon = icon || defaults.icon;
    color = color || defaults.color;

    const notification = {
        id: Date.now() + Math.random(),
        type: type,
        title: title,
        message: message,
        time: new Date(),
        read: false,
        icon: icon,
        color: color
    };

    notifications.unshift(notification);
    
    // Keep only the latest notifications
    if (notifications.length > maxNotifications) {
        notifications = notifications.slice(0, maxNotifications);
    }

    saveNotifications();
    updateNotificationBadge();
    renderNotifications();
    showToast(notification);
    
    return notification.id;
}

/**
 * Get default icon and color for notification type
 */
function getTypeDefaults(type) {
    const defaults = {
        order: { icon: 'fas fa-utensils', color: 'text-primary' },
        payment: { icon: 'fas fa-money-bill-wave', color: 'text-success' },
        kitchen: { icon: 'fas fa-check-circle', color: 'text-info' },
        info: { icon: 'fas fa-info-circle', color: 'text-info' },
        warning: { icon: 'fas fa-exclamation-triangle', color: 'text-warning' },
        error: { icon: 'fas fa-exclamation-circle', color: 'text-danger' },
        staff: { icon: 'fas fa-user', color: 'text-secondary' },
        table: { icon: 'fas fa-table', color: 'text-primary' }
    };
    return defaults[type] || defaults.info;
}

/**
 * Mark notification as read
 */
function markAsRead(id) {
    const notification = notifications.find(n => n.id === id);
    if (notification) {
        notification.read = true;
        saveNotifications();
        updateNotificationBadge();
        renderNotifications();
    }
}

/**
 * Mark all notifications as read
 */
function markAllAsRead() {
    notifications.forEach(notification => {
        notification.read = true;
    });
    saveNotifications();
    updateNotificationBadge();
    renderNotifications();
}

/**
 * Clear all notifications
 */
function clearAllNotifications() {
    notifications = [];
    saveNotifications();
    updateNotificationBadge();
    renderNotifications();
}

/**
 * Delete specific notification
 */
function deleteNotification(id) {
    notifications = notifications.filter(n => n.id !== id);
    saveNotifications();
    updateNotificationBadge();
    renderNotifications();
}

/**
 * Save notifications to localStorage
 */
function saveNotifications() {
    localStorage.setItem('navbar_notifications', JSON.stringify(notifications));
}

/**
 * Update notification badge count
 */
function updateNotificationBadge() {
    const unreadCount = notifications.filter(n => !n.read).length;
    const badge = document.getElementById('notificationBadge');
    
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

/**
 * Render notifications in dropdown
 */
function renderNotifications() {
    const container = document.getElementById('notificationList');
    if (!container) return;
    
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="p-3 text-center text-muted">
                <i class="fas fa-bell-slash fa-2x mb-2"></i>
                <p class="mb-0">No notifications yet</p>
            </div>
        `;
        return;
    }

    container.innerHTML = notifications.map(notification => `
        <div class="dropdown-item p-3 border-bottom notification-item ${notification.read ? '' : 'bg-light'}" 
             onclick="markAsRead(${notification.id})" style="cursor: pointer;">
            <div class="d-flex">
                <div class="notification-icon me-3 ${notification.color}">
                    <i class="${notification.icon}"></i>
                </div>
                <div class="flex-grow-1">
                    <p class="mb-1 fw-bold">${notification.title}</p>
                    <p class="mb-1 text-muted" style="font-size: 0.85rem;">${notification.message}</p>
                    <small class="text-muted">${getTimeAgo(notification.time)}</small>
                </div>
                <div class="d-flex flex-column align-items-end">
                    ${!notification.read ? '<div class="notification-dot bg-primary rounded-circle mb-1" style="width: 8px; height: 8px;"></div>' : ''}
                    <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteNotification(${notification.id})" style="font-size: 0.7rem; padding: 0.1rem 0.3rem;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Show toast notification
 */
function showToast(notification) {
    const toastElement = document.getElementById('liveToast');
    if (!toastElement) return;

    const toastBody = toastElement.querySelector('.toast-body');
    const toastHeader = toastElement.querySelector('.toast-header');
    
    toastHeader.innerHTML = `
        <i class="${notification.icon} me-2 ${notification.color}"></i>
        <strong class="me-auto">${notification.title}</strong>
        <small>Just now</small>
        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    `;
    
    toastBody.textContent = notification.message;
    
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

/**
 * Get time ago string
 */
function getTimeAgo(date) {
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    return `${days}d ago`;
}

/**
 * Refresh notifications
 */
function refreshNotifications() {
    renderNotifications();
    updateNotificationBadge();
}

// Restaurant-specific notification functions
function notifyNewOrder(tableNumber, items, orderId) {
    const itemText = items.length === 1 ? '1 item' : `${items.length} items`;
    const itemsList = items.slice(0, 2).join(', ') + (items.length > 2 ? '...' : '');
    
    return addNotification(
        'order',
        'New Order Received',
        `Table ${tableNumber} - ${itemText} (${itemsList})`
    );
}

function notifyPaymentCompleted(tableNumber, amount, method, orderId) {
    return addNotification(
        'payment',
        'Payment Completed',
        `Table ${tableNumber} - RM ${amount} (${method})`
    );
}

function notifyOrderReady(tableNumber, orderId) {
    return addNotification(
        'kitchen',
        'Order Ready',
        `Table ${tableNumber} - Order #${orderId} is ready for pickup`
    );
}

function notifyOrderCancelled(tableNumber, orderId, reason = '') {
    return addNotification(
        'warning',
        'Order Cancelled',
        `Table ${tableNumber} - Order #${orderId} cancelled${reason ? ': ' + reason : ''}`
    );
}

function notifyTableStatusChange(tableNumber, status) {
    return addNotification(
        'table',
        'Table Status Changed',
        `Table ${tableNumber} is now ${status}`
    );
}

function notifyStaffAction(staffName, action) {
    return addNotification(
        'staff',
        'Staff Action',
        `${staffName} ${action}`
    );
}

function notifyError(message, details = '') {
    return addNotification(
        'error',
        'System Error',
        message + (details ? ': ' + details : '')
    );
}

function notifyInfo(title, message) {
    return addNotification(
        'info',
        title,
        message
    );
}

function notifyWarning(title, message) {
    return addNotification(
        'warning',
        title,
        message
    );
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationBadge();
    renderNotifications();
    
    // Add sample notifications if none exist
    if (notifications.length === 0) {
        setTimeout(() => {
            notifyNewOrder(3, ['Burger', 'Fries'], '0012');
            notifyPaymentCompleted(5, '45.50', 'Cash', '0011');
            notifyOrderReady(2, '0010');
            notifyInfo('Welcome', 'Notification system is active');
        }, 1000);
    }
});
