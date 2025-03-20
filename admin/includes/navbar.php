<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
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
    font-weight: 700;
    font-size: 1.4rem;
    color: var(--primary-color) !important;
    padding: 0;
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

@media (max-width: 991.98px) {
    .brand-wrapper {
        width: auto;
        border: none;
    }
    
    .user-info {
        display: none;
    }
}
</style>

<nav class="navbar top-navbar">
    <div class="d-flex align-items-center w-100">
        <!-- Brand -->
        <div class="brand-wrapper">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <div class="brand-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <span>FoodAdmin</span>
            </a>
        </div>

        <!-- Toggle Button -->
        <button class="btn d-lg-none me-2" id="sidebarToggle">
            <i class="fas fa-bars" style="color: var(--primary-color); font-size: 1.4rem;"></i>
        </button>

        <!-- Right Menu Items -->
        <div class="d-flex align-items-center ms-auto">
            <!-- Notifications -->
            <div class="dropdown me-3">
                <button class="btn notification-btn" data-bs-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 320px;">
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Notifications</h6>
                            <a href="#" class="text-muted text-decoration-none" style="font-size: 0.8rem;">Mark all as read</a>
                        </div>
                    </div>
                    <div class="notification-list" style="max-height: 300px; overflow-y: auto;">
                        <a href="#" class="dropdown-item p-3 border-bottom">
                            <div class="d-flex">
                                <div class="notification-btn me-3">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <div>
                                    <p class="mb-0 fw-bold">New order received</p>
                                    <p class="mb-0 text-muted" style="font-size: 0.85rem;">Table 5 - 3 items</p>
                                    <small class="text-muted">2 minutes ago</small>
                                </div>
                            </div>
                        </a>
                        <!-- More notifications -->
                    </div>
                    <div class="p-2 text-center border-top">
                        <a href="#" class="text-primary text-decoration-none small">View all notifications</a>
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
                        <p class="user-name"><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?></p>
                        <p class="user-role">Administrator</p>
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
                                <p class="user-name mb-0"><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?></p>
                                <p class="user-role mb-0">Administrator</p>
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