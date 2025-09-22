/**
 * Restaurant Notification System
 * Complete working notification system - No Database Required
 */

class RestaurantNotificationSystem {
    constructor() {
        this.notifications = JSON.parse(localStorage.getItem('restaurant_notifications') || '[]');
        this.maxNotifications = 100;
        this.init();
    }

    init() {
        this.updateBadge();
        this.renderNotifications();
        this.startAutoRefresh();
    }

    /**
     * Add a new notification
     */
    addNotification(type, title, message, icon = null, color = null, data = {}) {
        const defaults = this.getTypeDefaults(type);
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
            color: color,
            data: data
        };

        this.notifications.unshift(notification);
        
        if (this.notifications.length > this.maxNotifications) {
            this.notifications = this.notifications.slice(0, this.maxNotifications);
        }

        this.saveNotifications();
        this.updateBadge();
        this.renderNotifications();
        this.showToast(notification);
        
        return notification.id;
    }

    getTypeDefaults(type) {
        const defaults = {
            order: { icon: 'fas fa-utensils', color: 'text-primary' },
            payment: { icon: 'fas fa-money-bill-wave', color: 'text-success' },
            kitchen: { icon: 'fas fa-check-circle', color: 'text-info' },
            info: { icon: 'fas fa-info-circle', color: 'text-info' },
            warning: { icon: 'fas fa-exclamation-triangle', color: 'text-warning' },
            error: { icon: 'fas fa-exclamation-circle', color: 'text-danger' },
            staff: { icon: 'fas fa-user', color: 'text-secondary' },
            table: { icon: 'fas fa-table', color: 'text-primary' },
            menu: { icon: 'fas fa-book-open', color: 'text-info' }
        };
        return defaults[type] || defaults.info;
    }

    markAsRead(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (notification) {
            notification.read = true;
            this.saveNotifications();
            this.updateBadge();
            this.renderNotifications();
        }
    }

    markAllAsRead() {
        this.notifications.forEach(notification => {
            notification.read = true;
        });
        this.saveNotifications();
        this.updateBadge();
        this.renderNotifications();
    }

    clearAll() {
        this.notifications = [];
        this.saveNotifications();
        this.updateBadge();
        this.renderNotifications();
    }

    deleteNotification(id) {
        this.notifications = this.notifications.filter(n => n.id !== id);
        this.saveNotifications();
        this.updateBadge();
        this.renderNotifications();
    }

    getUnreadCount() {
        return this.notifications.filter(n => !n.read).length;
    }

    saveNotifications() {
        localStorage.setItem('restaurant_notifications', JSON.stringify(this.notifications));
    }

    updateBadge() {
        const unreadCount = this.getUnreadCount();
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

    renderNotifications() {
        const container = document.getElementById('notificationList');
        if (!container) return;
        
        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="p-3 text-center text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p class="mb-0">No notifications yet</p>
                </div>
            `;
            return;
        }

        container.innerHTML = this.notifications.map(notification => `
            <div class="dropdown-item p-3 border-bottom notification-item ${notification.read ? '' : 'bg-light'}" 
                 onclick="restaurantNotifications.markAsRead(${notification.id})" style="cursor: pointer;">
                <div class="d-flex">
                    <div class="notification-icon me-3 ${notification.color}">
                        <i class="${notification.icon}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <p class="mb-1 fw-bold">${notification.title}</p>
                        <p class="mb-1 text-muted" style="font-size: 0.85rem;">${notification.message}</p>
                        <small class="text-muted">${this.getTimeAgo(notification.time)}</small>
                    </div>
                    <div class="d-flex flex-column align-items-end">
                        ${!notification.read ? '<div class="notification-dot bg-primary rounded-circle mb-1" style="width: 8px; height: 8px;"></div>' : ''}
                        <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); restaurantNotifications.deleteNotification(${notification.id})" style="font-size: 0.7rem; padding: 0.1rem 0.3rem;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    showToast(notification) {
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

    getTimeAgo(date) {
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

    startAutoRefresh() {
        setInterval(() => {
            this.renderNotifications();
        }, 30000);
    }

    refresh() {
        this.renderNotifications();
        this.updateBadge();
    }
}

// Initialize notification system
const restaurantNotifications = new RestaurantNotificationSystem();

// Global functions for easy access
function addNotification(type, title, message, icon, color, data) {
    return restaurantNotifications.addNotification(type, title, message, icon, color, data);
}

function markNotificationAsRead(id) {
    restaurantNotifications.markAsRead(id);
}

function markAllNotificationsAsRead() {
    restaurantNotifications.markAllAsRead();
}

function clearAllNotifications() {
    restaurantNotifications.clearAll();
}

function refreshNotifications() {
    restaurantNotifications.refresh();
}

// Restaurant-specific notification functions
function notifyNewOrder(tableNumber, items, orderId) {
    const itemText = items.length === 1 ? '1 item' : `${items.length} items`;
    const itemsList = items.slice(0, 2).join(', ') + (items.length > 2 ? '...' : '');
    
    return addNotification(
        'order',
        'New Order Received',
        `Table ${tableNumber} - ${itemText} (${itemsList})`,
        'fas fa-utensils',
        'text-primary',
        { tableNumber, orderId, items }
    );
}

function notifyPaymentCompleted(tableNumber, amount, method, orderId) {
    return addNotification(
        'payment',
        'Payment Completed',
        `Table ${tableNumber} - RM ${amount} (${method})`,
        'fas fa-money-bill-wave',
        'text-success',
        { tableNumber, amount, method, orderId }
    );
}

function notifyOrderReady(tableNumber, orderId) {
    return addNotification(
        'kitchen',
        'Order Ready',
        `Table ${tableNumber} - Order #${orderId} is ready for pickup`,
        'fas fa-check-circle',
        'text-info',
        { tableNumber, orderId }
    );
}

function notifyOrderCancelled(tableNumber, orderId, reason = '') {
    return addNotification(
        'warning',
        'Order Cancelled',
        `Table ${tableNumber} - Order #${orderId} cancelled${reason ? ': ' + reason : ''}`,
        'fas fa-times-circle',
        'text-warning',
        { tableNumber, orderId, reason }
    );
}

function notifyTableStatusChange(tableNumber, status) {
    return addNotification(
        'table',
        'Table Status Changed',
        `Table ${tableNumber} is now ${status}`,
        'fas fa-table',
        'text-primary',
        { tableNumber, status }
    );
}

function notifyStaffAction(staffName, action) {
    return addNotification(
        'staff',
        'Staff Action',
        `${staffName} ${action}`,
        'fas fa-user',
        'text-secondary',
        { staffName, action }
    );
}

function notifyMenuUpdate(itemName, action) {
    return addNotification(
        'menu',
        'Menu Updated',
        `${itemName} has been ${action}`,
        'fas fa-book-open',
        'text-info',
        { itemName, action }
    );
}

function notifyError(message, details = '') {
    return addNotification(
        'error',
        'System Error',
        message + (details ? ': ' + details : ''),
        'fas fa-exclamation-circle',
        'text-danger',
        { details }
    );
}

function notifyInfo(title, message) {
    return addNotification(
        'info',
        title,
        message,
        'fas fa-info-circle',
        'text-info'
    );
}

function notifyWarning(title, message) {
    return addNotification(
        'warning',
        title,
        message,
        'fas fa-exclamation-triangle',
        'text-warning'
    );
}

// Export for use in other scripts
window.restaurantNotifications = restaurantNotifications;
window.addNotification = addNotification;
window.notifyNewOrder = notifyNewOrder;
window.notifyPaymentCompleted = notifyPaymentCompleted;
window.notifyOrderReady = notifyOrderReady;
window.notifyOrderCancelled = notifyOrderCancelled;
window.notifyTableStatusChange = notifyTableStatusChange;
window.notifyStaffAction = notifyStaffAction;
window.notifyMenuUpdate = notifyMenuUpdate;
window.notifyError = notifyError;
window.notifyInfo = notifyInfo;
window.notifyWarning = notifyWarning;