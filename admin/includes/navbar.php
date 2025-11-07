<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Get restaurant name from system settings
try {
    require_once(__DIR__ . '/../../config/Database.php');
    require_once(__DIR__ . '/../../classes/SystemSettings.php');
    
    $database = new Database();
    $db = $database->getConnection();
    $systemSettings = new SystemSettings($db);
    $restaurant_name = $systemSettings->getRestaurantName();
} catch (Exception $e) {
    // Fallback to default restaurant name if there's an error
    $restaurant_name = 'Gourmet Delights';
    error_log('Navbar restaurant name error: ' . $e->getMessage());
}
?>
<!-- Include Navbar CSS -->
<link href="css/navbar.css" rel="stylesheet">

<nav class="navbar top-navbar">
    <div class="d-flex align-items-center w-100">
        <!-- Brand -->
        <div class="brand-wrapper">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <div class="brand-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <span><?php echo htmlspecialchars($restaurant_name); ?></span>
            </a>
        </div>

        <!-- Toggle Button (only show if user has permissions) -->
        <?php 
        $has_permissions = false;
        if ($_SESSION['user_type'] === 'admin') {
            $has_permissions = true;
        } else if (isset($_SESSION['staff_permissions']) && is_array($_SESSION['staff_permissions'])) {
            $has_permissions = !empty($_SESSION['staff_permissions']);
        }
        ?>
        <?php if ($has_permissions): ?>
        <button class="btn d-lg-none me-2" id="sidebarToggle">
            <i class="fas fa-bars" style="color: var(--primary-color); font-size: 1.4rem;"></i>
        </button>
        <?php endif; ?>

        <!-- Right Menu Items -->
        <div class="d-flex align-items-center ms-auto">
            <!-- Notifications -->
            <div class="dropdown me-2 me-md-3">
                <button class="btn notification-btn" data-bs-toggle="dropdown" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px;">
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Notifications</h6>
                                <small class="text-muted">Last 24 hours</small>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearAllNotifications()" style="font-size: 0.8rem;">Clear All</button>
                        </div>
                    </div>
                    <div class="notification-list" id="notificationList" style="max-height: 400px; overflow-y: auto;">
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                            <p class="mb-0">No notifications yet</p>
                        </div>
                    </div>
                    <div class="p-2 text-center border-top">
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshNotifications()" id="refreshBtn">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <small class="text-muted d-block mt-1">
                            <i class="fas fa-clock me-1"></i>Auto-refresh every 3s
                        </small>
                    </div>
                </div>
            </div>

            <!-- User Profile -->
            <div class="dropdown">
                <a href="#" class="user-profile text-decoration-none" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <p class="user-name"><?php 
                            if ($_SESSION['user_type'] === 'admin') {
                                echo htmlspecialchars($_SESSION['admin_username']);
                            } else {
                                echo htmlspecialchars($_SESSION['staff_name']);
                            }
                        ?></p>
                        <p class="user-role"><?php 
                            echo $_SESSION['user_type'] === 'admin' ? 'Administrator' : ucfirst($_SESSION['staff_position']); 
                        ?></p>
                    </div>
                    <i class="fas fa-chevron-down ms-2" style="color: #777; font-size: 0.8rem;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end mt-2">
                    <li class="px-3 py-2">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <?php 
                                $displayName = ($_SESSION['user_type'] === 'admin')
                                    ? (isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin')
                                    : (isset($_SESSION['staff_name']) ? htmlspecialchars($_SESSION['staff_name']) : 'Staff');
                                $displayRole = ($_SESSION['user_type'] === 'admin')
                                    ? 'Administrator'
                                    : (isset($_SESSION['staff_position']) ? ucfirst(htmlspecialchars($_SESSION['staff_position'])) : 'Staff');
                                ?>
                                <p class="user-name mb-0"><?php echo $displayName; ?></p>
                                <p class="user-role mb-0"><?php echo $displayRole; ?></p>
                            </div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user-circle me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item py-2" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Notification Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="liveToast" class="toast" role="alert">
        <div class="toast-header">
            <i class="fas fa-bell me-2"></i>
            <strong class="me-auto">Notification</strong>
            <small>Just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            <!-- Notification content will be inserted here -->
        </div>
    </div>
</div>

<!-- Include Navbar Notification System -->
<script src="js/navbar_notifications.js"></script> 
<!-- Custom CSS for Navbar -->
<style>
.top-navbar {
    position: fixed;
    top: 0;
    right: 0;
    left: 0;
    height: 70px;
    background: #fff !important;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    z-index: 1040;
    padding: 0 1.5rem;
}

.navbar-brand {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 1.4rem;
    color: var(--primary-color) !important;
    padding: 0;
    text-decoration: none;
    letter-spacing: -0.02em;
    line-height: 1.2;
    transition: all 0.3s ease;
}

.navbar-brand:hover {
    color: var(--secondary-color) !important;
    transform: translateY(-1px);
}

.brand-wrapper {
    width: 280px;
    padding: 0 1rem;
    display: flex;
    align-items: center;
    border-right: 1px solid rgba(0,0,0,0.05);
    margin-right: 1rem;
}

.brand-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-color);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}

.brand-icon i {
    color: #fff;
    font-size: 1.4rem;
    transition: all 0.3s ease;
}

.navbar-brand:hover .brand-icon i {
    transform: scale(1.05);
}

.navbar .nav-link {
    color: #555 !important;
    padding: 0.5rem 1rem !important;
    border-radius: 8px;
    font-weight: 500;
}

.navbar .nav-link:hover {
    color: var(--primary-color) !important;
    background: rgba(67, 97, 238, 0.05);
}

.navbar .nav-link.active {
    color: var(--primary-color) !important;
    background: rgba(67, 97, 238, 0.1);
}

.notification-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s ease;
}

.notification-btn:hover {
    background: rgba(67, 97, 238, 0.15);
    transform: translateY(-2px);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    border: 2px solid #fff;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-item {
    transition: all 0.3s ease;
}

.notification-item:hover {
    background-color: rgba(67, 97, 238, 0.05) !important;
    transform: translateX(5px);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(67, 97, 238, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.notification-dot {
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

.user-profile {
    display: flex;
    align-items: center;
    padding: 0.25rem;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.user-profile:hover {
    background: rgba(67, 97, 238, 0.05);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}

.user-avatar i {
    color: #fff;
    font-size: 1.2rem;
}

.user-info {
    margin-right: 10px;
}

.user-name {
    font-weight: 600;
    color: #333;
    margin: 0;
    font-size: 0.95rem;
}

.user-role {
    color: #777;
    font-size: 0.8rem;
    margin: 0;
}

/* Responsive Design for All Devices */

/* Large Tablets and Small Desktops */
@media (max-width: 1199.98px) {
    .brand-wrapper {
        width: 220px;
        padding: 0 0.75rem;
    }
    
    .brand-icon {
        width: 36px;
        height: 36px;
    }
    
    .brand-icon i {
        font-size: 1.2rem;
    }
    
    .navbar-brand {
        font-size: 1.2rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
}

/* Tablets */
@media (max-width: 991.98px) {
    .top-navbar {
        padding: 0 1rem;
    }
    
    .brand-wrapper {
        width: auto;
        border: none;
        margin-right: 0.5rem;
        padding: 0 0.5rem;
    }
    
    .brand-icon {
        width: 34px;
        height: 34px;
        margin-right: 8px;
    }
    
    .brand-icon i {
        font-size: 1.1rem;
    }
    
    .navbar-brand {
        font-size: 1.1rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    
    .user-info {
        display: none;
    }
    
    .notification-btn {
        width: 36px;
        height: 36px;
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
    }
    
    .user-avatar i {
        font-size: 1.1rem;
    }
}

/* Mobile Devices */
@media (max-width: 767.98px) {
    .top-navbar {
        height: 60px;
        padding: 0 0.75rem;
    }
    
    .brand-wrapper {
        padding: 0 0.25rem;
    }
    
    .brand-icon {
        width: 32px;
        height: 32px;
        margin-right: 6px;
    }
    
    .brand-icon i {
        font-size: 1rem;
    }
    
    .navbar-brand {
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    
    .notification-btn {
        width: 34px;
        height: 34px;
        margin-right: 0.5rem;
    }
    
    .notification-btn i {
        font-size: 0.9rem;
    }
    
    .user-avatar {
        width: 34px;
        height: 34px;
    }
    
    .user-avatar i {
        font-size: 1rem;
    }
    
    .dropdown-menu {
        width: 300px !important;
    }
    
    .notification-list {
        max-height: 300px !important;
    }
}

/* Small Mobile Devices */
@media (max-width: 575.98px) {
    .top-navbar {
        height: 55px;
        padding: 0 0.5rem;
    }
    
    .brand-wrapper {
        padding: 0;
    }
    
    .brand-icon {
        width: 30px;
        height: 30px;
        margin-right: 4px;
    }
    
    .brand-icon i {
        font-size: 0.9rem;
    }
    
    .navbar-brand {
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    
    .notification-btn {
        width: 32px;
        height: 32px;
        margin-right: 0.25rem;
    }
    
    .notification-btn i {
        font-size: 0.8rem;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
    }
    
    .user-avatar i {
        font-size: 0.9rem;
    }
    
    .dropdown-menu {
        width: 280px !important;
        left: -200px !important;
    }
    
    .notification-list {
        max-height: 250px !important;
    }
    
    .notification-badge {
        width: 16px;
        height: 16px;
        font-size: 0.6rem;
        top: -3px;
        right: -3px;
    }
}

/* Extra Small Devices */
@media (max-width: 400px) {
    .top-navbar {
        height: 50px;
        padding: 0 0.25rem;
    }
    
    .brand-icon {
        width: 28px;
        height: 28px;
        margin-right: 3px;
    }
    
    .brand-icon i {
        font-size: 0.8rem;
    }
    
    .navbar-brand {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    
    .notification-btn {
        width: 30px;
        height: 30px;
    }
    
    .notification-btn i {
        font-size: 0.7rem;
    }
    
    .user-avatar {
        width: 30px;
        height: 30px;
    }
    
    .user-avatar i {
        font-size: 0.8rem;
    }
    
    .dropdown-menu {
        width: 260px !important;
        left: -180px !important;
    }
}
</style> 