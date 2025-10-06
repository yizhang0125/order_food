// Navbar Notification System for Completed Payments
class PaymentNotifications {
    constructor() {
        this.notificationList = document.getElementById('notificationList');
        this.notificationBadge = document.getElementById('notificationBadge');
        this.notificationBtn = document.getElementById('notificationBtn');
        this.liveToast = document.getElementById('liveToast');
        this.notifications = [];
        this.lastCheckTime = new Date().toISOString();
        this.lastCleanupTime = new Date();
        
        this.init();
    }
    
    init() {
        // Load initial notifications
        this.loadNotifications();
        
        // Set up auto-refresh every 30 seconds
        setInterval(() => {
            this.checkNewNotifications();
        }, 5000); // Check every 5 seconds for faster payment notifications
        
        // Set up daily cleanup - check every hour for old notifications
        setInterval(() => {
            this.cleanupOldNotifications();
        }, 3600000); // 1 hour = 3600000ms
        
        // Set up notification button click handler
        if (this.notificationBtn) {
            this.notificationBtn.addEventListener('click', () => {
                this.loadNotifications();
                // Mark all notifications as read when dropdown is opened
                this.markAllAsRead();
            });
        }
    }
    
    async loadNotifications() {
        try {
            const response = await fetch('ajax/get_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_notifications',
                    last_check: this.lastCheckTime
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.success) {
                    this.notifications = data.notifications || [];
                    this.updateNotifications(this.notifications);
                    this.lastCheckTime = new Date().toISOString();
                } else {
                    console.error('Failed to load notifications:', data.message);
                }
            } else {
                console.error('Response not ok:', response.status, response.statusText);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }
    
    async checkNewNotifications() {
        try {
            // Check for regular notifications
            const response = await fetch('ajax/get_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_new',
                    last_check: this.lastCheckTime
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.new_notifications.length > 0) {
                    this.addNewNotifications(data.new_notifications);
                    this.showToastNotifications(data.new_notifications);
                    this.lastCheckTime = new Date().toISOString();
                }
            }
            
            // Check for payment notifications (more frequent)
            const paymentResponse = await fetch('ajax/get_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_payment_notifications'
                })
            });
            
            if (paymentResponse.ok) {
                const paymentData = await paymentResponse.json();
                if (paymentData.success && paymentData.new_notifications && paymentData.new_notifications.length > 0) {
                    this.addNewNotifications(paymentData.new_notifications);
                    this.showToastNotifications(paymentData.new_notifications);
                }
            }
        } catch (error) {
            console.error('Error checking new notifications:', error);
        }
    }
    
    updateNotifications(notifications) {
        this.notifications = notifications;
        this.renderNotifications();
        this.updateBadge();
    }
    
    addNewNotifications(newNotifications) {
        // Add new notifications to the beginning of the array
        this.notifications = [...newNotifications, ...this.notifications];
        this.renderNotifications();
        this.updateBadge();
    }
    
    renderNotifications() {
        if (!this.notificationList) return;
        
        if (this.notifications.length === 0) {
            this.notificationList.innerHTML = `
            <div class="p-3 text-center text-muted">
                <i class="fas fa-bell-slash fa-2x mb-2"></i>
                <p class="mb-0">No notifications yet</p>
            </div>
        `;
        return;
    }

        // Debug: Log notifications to console
        console.log('Rendering notifications:', this.notifications);

        const notificationsHtml = this.notifications.map(notification => {
            // Debug: Log each notification's created_at value
            console.log('Processing notification:', notification.id, 'created_at:', notification.created_at);
            const timeAgo = this.getTimeAgo(notification.created_at);
            
            if (notification.type === 'order_placed') {
                const orderIcon = this.getOrderStatusIcon(notification.status);
                const itemCount = notification.item_count || 0;
                const itemsList = notification.items_list || '';
                
                return `
                    <div class="notification-item p-3 border-bottom" data-id="${notification.id}">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon me-3">
                                <i class="${orderIcon}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-0 text-dark">New Order Placed</h6>
                                    <small class="text-muted">${timeAgo}</small>
                                </div>
                                <p class="mb-1 text-muted small">
                                    <strong>Table ${notification.table_number}</strong> - 
                                    ${itemCount} item${itemCount !== 1 ? 's' : ''}
                                </p>
                                ${itemsList ? `
                                    <p class="mb-1 text-muted small" style="font-size: 0.75rem; line-height: 1.3;">
                                        ${itemsList.length > 60 ? itemsList.substring(0, 60) + '...' : itemsList}
                                    </p>
                                ` : ''}
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-${this.getOrderStatusColor(notification.status)}">
                                        ${this.getOrderStatusText(notification.status)}
                                    </span>
                                    <small class="text-muted">${this.formatTime(notification.created_at)}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Payment notification with full details matching payment_details.php
                const paymentMethodIcon = this.getPaymentMethodIcon(notification.payment_method);
                const amount = parseFloat(notification.amount).toFixed(2);
                const discountAmount = notification.discount_amount ? parseFloat(notification.discount_amount).toFixed(2) : '0.00';
                const changeAmount = notification.change_amount ? parseFloat(notification.change_amount).toFixed(2) : '0.00';
                const cashReceived = notification.cash_received ? parseFloat(notification.cash_received).toFixed(2) : '0.00';
                
                return `
                    <div class="notification-item p-3 border-bottom" data-id="${notification.id}">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon me-3">
                                <i class="${paymentMethodIcon}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-0 text-dark">Payment Completed</h6>
                                    <small class="text-muted">${timeAgo}</small>
                                </div>
                                <p class="mb-1 text-muted small">
                                    <strong>Table ${notification.table_number}</strong> - 
                                    ${notification.payment_method === 'cash' ? 'Cash Payment' : 'TNG Pay'}
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-success fw-bold">RM ${amount}</span>
                                    <small class="text-muted">${notification.processed_by_name}</small>
                                </div>
                                ${notification.discount_amount > 0 ? `
                                    <div class="mt-1">
                                        <small class="text-warning">
                                            <i class="fas fa-percentage me-1"></i>
                                            Discount: RM ${discountAmount} (${notification.discount_type || 'custom'})
                                        </small>
                                    </div>
                                ` : ''}
                                ${notification.payment_method === 'cash' && notification.cash_received > 0 ? `
                                    <div class="mt-1">
                                        <small class="text-info">
                                            <i class="fas fa-money-bill-wave me-1"></i>
                                            Cash: RM ${cashReceived}
                                        </small>
                                    </div>
                                ` : ''}
                                ${notification.payment_method === 'cash' && notification.change_amount > 0 ? `
                                    <div class="mt-1">
                                        <small class="text-success">
                                            <i class="fas fa-coins me-1"></i>
                                            Change: RM ${changeAmount}
                                        </small>
                                    </div>
                                ` : ''}
                                ${notification.payment_method === 'tng_pay' && notification.tng_reference ? `
                                    <div class="mt-1">
                                        <small class="text-primary">
                                            <i class="fas fa-receipt me-1"></i>
                                            Ref: ${notification.tng_reference}
                                        </small>
                                    </div>
                                ` : ''}
                                <div class="mt-1">
                                    <small class="text-muted">Time: ${this.formatTime(notification.created_at)}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
        }).join('');
        
        this.notificationList.innerHTML = notificationsHtml;
    }
    
    showToastNotifications(notifications) {
        notifications.forEach((notification, index) => {
            setTimeout(() => {
                this.showToast(notification);
            }, index * 1000); // Show each notification 1 second apart
        });
    }
    
    showToast(notification) {
        if (!this.liveToast) return;
        
        const toastBody = this.liveToast.querySelector('.toast-body');
        const toastHeader = this.liveToast.querySelector('.toast-header small');
        
        toastHeader.textContent = 'Just now';
        
        if (notification.type === 'order_placed') {
            const orderIcon = this.getOrderStatusIcon(notification.status);
            const itemCount = notification.item_count || 0;
            const itemsList = notification.items_list || '';
            
            let orderDetails = `Table ${notification.table_number} - ${itemCount} item${itemCount !== 1 ? 's' : ''}`;
            if (itemsList) {
                const shortItemsList = itemsList.length > 40 ? itemsList.substring(0, 40) + '...' : itemsList;
                orderDetails += `<br>Items: ${shortItemsList}`;
            }
            
            toastBody.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="${orderIcon}" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <strong>New Order Placed!</strong><br>
                        <small class="text-muted">
                            ${orderDetails}
                            <br>Status: ${this.getOrderStatusText(notification.status)}
                            <br>Time: ${this.formatTime(notification.created_at)}
                        </small>
                    </div>
                </div>
            `;
        } else {
            // Payment notification with enhanced details
            const paymentMethodIcon = this.getPaymentMethodIcon(notification.payment_method);
            const amount = parseFloat(notification.amount).toFixed(2);
            const discountAmount = notification.discount_amount ? parseFloat(notification.discount_amount).toFixed(2) : '0.00';
            const changeAmount = notification.change_amount ? parseFloat(notification.change_amount).toFixed(2) : '0.00';
            const cashReceived = notification.cash_received ? parseFloat(notification.cash_received).toFixed(2) : '0.00';
            
            let paymentDetails = `Table ${notification.table_number} - RM ${amount}`;
            if (notification.payment_method === 'cash') {
                paymentDetails += ` (Cash)`;
                if (notification.cash_received > 0) {
                    paymentDetails += `<br>Cash: RM ${cashReceived}`;
                }
                if (notification.change_amount > 0) {
                    paymentDetails += ` | Change: RM ${changeAmount}`;
                }
            } else {
                paymentDetails += ` (TNG Pay)`;
                if (notification.tng_reference) {
                    paymentDetails += `<br>Ref: ${notification.tng_reference}`;
                }
            }
            
            if (notification.discount_amount > 0) {
                paymentDetails += `<br>Discount: RM ${discountAmount} (${notification.discount_type || 'custom'})`;
            }
            
            toastBody.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="${paymentMethodIcon} text-success" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <strong>Payment Completed!</strong><br>
                        <small class="text-muted">
                            ${paymentDetails}
                            <br>Processed by: ${notification.processed_by_name}
                            <br>Time: ${this.formatTime(notification.created_at)}
                        </small>
                    </div>
                </div>
            `;
        }
        
        const toast = new bootstrap.Toast(this.liveToast);
    toast.show();
}

    updateBadge() {
        if (!this.notificationBadge) return;
        
        const unreadCount = this.notifications.filter(n => !n.is_read).length;
        
        if (unreadCount > 0) {
            this.notificationBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            this.notificationBadge.style.display = 'flex';
        } else {
            this.notificationBadge.style.display = 'none';
        }
    }
    
    async markAllAsRead() {
        try {
            // Mark all notifications as read locally first
            this.notifications.forEach(notification => {
                notification.is_read = 1;
            });
            
            // Update the badge immediately
            this.updateBadge();
            
            // Send request to server to mark as read
            await fetch('ajax/get_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            });
            
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    }
    
    cleanupOldNotifications() {
        try {
            const oneDayAgo = new Date();
            oneDayAgo.setDate(oneDayAgo.getDate() - 1);
            
            // Filter out notifications older than 1 day
            const oldCount = this.notifications.length;
            this.notifications = this.notifications.filter(notification => {
                const notificationDate = new Date(notification.created_at);
                return notificationDate >= oneDayAgo;
            });
            
            const removedCount = oldCount - this.notifications.length;
            
            if (removedCount > 0) {
                console.log(`Cleaned up ${removedCount} old notifications (older than 1 day)`);
                this.lastCleanupTime = new Date();
                this.renderNotifications();
                this.updateBadge();
            }
        } catch (error) {
            console.error('Error cleaning up old notifications:', error);
        }
    }
    
    getPaymentMethodIcon(paymentMethod) {
        switch (paymentMethod) {
            case 'cash':
                return 'fas fa-money-bill-wave text-success';
            case 'tng_pay':
                return 'fas fa-mobile-alt text-primary';
            case 'card':
                return 'fas fa-credit-card text-info';
            default:
                return 'fas fa-receipt text-secondary';
        }
    }
    
    getOrderStatusIcon(status) {
        switch (status) {
            case 'pending':
                return 'fas fa-clock text-warning';
            case 'processing':
                return 'fas fa-utensils text-info';
            case 'completed':
                return 'fas fa-check-circle text-success';
            case 'cancelled':
                return 'fas fa-times-circle text-danger';
            default:
                return 'fas fa-shopping-cart text-primary';
        }
    }
    
    getOrderStatusColor(status) {
        switch (status) {
            case 'pending':
                return 'warning';
            case 'processing':
                return 'info';
            case 'completed':
                return 'success';
            case 'cancelled':
                return 'danger';
            default:
                return 'primary';
        }
    }
    
    getOrderStatusText(status) {
        switch (status) {
            case 'pending':
                return 'Pending';
            case 'processing':
                return 'Processing';
            case 'completed':
                return 'Completed';
            case 'cancelled':
                return 'Cancelled';
            default:
                return 'Unknown';
        }
    }
    
    getTimeAgo(dateString) {
        try {
            if (!dateString) return 'Unknown time';
            
            console.log('Processing date string:', dateString);
            
            const now = new Date();
            let date;
            
            // Try different date parsing methods
            if (typeof dateString === 'string') {
                // Handle MySQL datetime format: "2024-01-14 15:30:25"
                if (dateString.includes(' ')) {
                    // Replace space with 'T' for ISO format
                    const isoString = dateString.replace(' ', 'T');
                    date = new Date(isoString);
                } else {
                    date = new Date(dateString);
                }
            } else {
                date = new Date(dateString);
            }
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                console.error('Invalid date string:', dateString, 'Parsed as:', date);
                return 'Invalid time';
            }
            
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 0) {
                return 'Just now';
            } else if (diffInSeconds < 60) {
                return 'Just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes}m ago`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours}h ago`;
            } else {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days}d ago`;
            }
        } catch (error) {
            console.error('Error calculating time ago:', error, 'Date string:', dateString);
            return 'Unknown time';
        }
    }
    
    formatTime(dateString) {
        try {
            if (!dateString) return 'Unknown time';
            
            console.log('Formatting time for:', dateString);
            
            let date;
            
            // Try different date parsing methods
            if (typeof dateString === 'string') {
                // Handle MySQL datetime format: "2024-01-14 15:30:25"
                if (dateString.includes(' ')) {
                    // Replace space with 'T' for ISO format
                    const isoString = dateString.replace(' ', 'T');
                    date = new Date(isoString);
                } else {
                    date = new Date(dateString);
                }
            } else {
                date = new Date(dateString);
            }
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                console.error('Invalid date string for formatTime:', dateString, 'Parsed as:', date);
                return 'Invalid time';
            }
            
            return date.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        } catch (error) {
            console.error('Error formatting time:', error, 'Date string:', dateString);
            return 'Unknown time';
        }
    }
}

// Global functions for navbar actions
function clearAllNotifications() {
    if (confirm('Are you sure you want to clear all notifications?')) {
        fetch('ajax/get_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'clear_all'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (window.paymentNotifications) {
                    window.paymentNotifications.notifications = [];
                    window.paymentNotifications.renderNotifications();
                    window.paymentNotifications.updateBadge();
                }
            }
        })
        .catch(error => {
            console.error('Error clearing notifications:', error);
        });
    }
}

function refreshNotifications() {
    if (window.paymentNotifications) {
        // Clean up old notifications first
        window.paymentNotifications.cleanupOldNotifications();
        // Then load fresh notifications
        window.paymentNotifications.loadNotifications();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on a page with the navbar
    if (document.getElementById('notificationList')) {
        window.paymentNotifications = new PaymentNotifications();
    }
});
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
