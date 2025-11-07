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
        
        // Set up auto-refresh every 3 seconds for real-time notifications
        setInterval(() => {
            this.checkNewNotifications();
        }, 3000); // Check every 3 seconds for faster payment notifications
        
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
        
        // Add visual indicator for auto-refresh status
        this.addRefreshIndicator();
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
            // Show refresh indicator
            this.showRefreshIndicator();
            
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
            this.showErrorIndicator();
        } finally {
            // Hide refresh indicator after a short delay
            setTimeout(() => {
                this.hideRefreshIndicator();
            }, 500);
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
    
    addRefreshIndicator() {
        // Add a small refresh indicator to the notification button
        if (this.notificationBtn) {
            const indicator = document.createElement('div');
            indicator.id = 'refreshIndicator';
            indicator.style.cssText = `
                position: absolute;
                top: -2px;
                right: -2px;
                width: 8px;
                height: 8px;
                background: #28a745;
                border-radius: 50%;
                border: 2px solid #fff;
                display: none;
                animation: pulse 1s infinite;
            `;
            this.notificationBtn.style.position = 'relative';
            this.notificationBtn.appendChild(indicator);
        }
    }
    
    showRefreshIndicator() {
        const indicator = document.getElementById('refreshIndicator');
        if (indicator) {
            indicator.style.display = 'block';
            indicator.style.background = '#17a2b8'; // Blue for refreshing
        }
    }
    
    hideRefreshIndicator() {
        const indicator = document.getElementById('refreshIndicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    showErrorIndicator() {
        const indicator = document.getElementById('refreshIndicator');
        if (indicator) {
            indicator.style.display = 'block';
            indicator.style.background = '#dc3545'; // Red for error
            setTimeout(() => {
                this.hideRefreshIndicator();
            }, 2000);
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
        // Show loading state on refresh button
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) {
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
            refreshBtn.disabled = true;
            
            // Clean up old notifications first
            window.paymentNotifications.cleanupOldNotifications();
            // Then load fresh notifications
            window.paymentNotifications.loadNotifications();
            
            // Restore button after 1 second
            setTimeout(() => {
                refreshBtn.innerHTML = originalText;
                refreshBtn.disabled = false;
            }, 1000);
        } else {
            // Fallback if button not found
            window.paymentNotifications.cleanupOldNotifications();
            window.paymentNotifications.loadNotifications();
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on a page with the navbar
    if (document.getElementById('notificationList')) {
        window.paymentNotifications = new PaymentNotifications();
    }
});
