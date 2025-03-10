<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Custom CSS for Sidebar -->
<style>
.sidebar {
    width: 280px;
    height: calc(100vh - 70px);
    position: fixed;
    top: 70px;
    left: 0;
    overflow-y: auto;
    background: #fff;
    border-right: 1px solid rgba(231, 231, 231, 0.8);
    transition: all 0.3s ease;
    z-index: 1000;
}

.menu-section {
    padding: 1.5rem;
}

.menu-title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #999;
    margin-bottom: 1rem;
}

.nav-item {
    margin-bottom: 0.5rem;
}

.nav-link {
    color: #555;
    padding: 0.8rem 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nav-link:hover {
    color: var(--primary-color);
    background: rgba(67, 97, 238, 0.05);
    transform: translateX(5px);
}

.nav-link.active {
    color: var(--primary-color);
    background: rgba(67, 97, 238, 0.1);
}

.nav-link i {
    width: 24px;
    font-size: 1.2rem;
    margin-right: 12px;
    transition: all 0.3s ease;
}

.nav-link.active i {
    color: var(--primary-color);
}

.nav-badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    background: var(--primary-color);
    color: white;
    margin-left: auto;
}

.menu-divider {
    height: 1px;
    background: rgba(0, 0, 0, 0.05);
    margin: 1rem 0;
}

.sidebar-footer {
    padding: 1rem 1.5rem;
    background: rgba(67, 97, 238, 0.03);
    border-top: 1px solid rgba(231, 231, 231, 0.8);
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.quick-action-btn {
    padding: 1rem;
    border-radius: 12px;
    background: white;
    border: 1px solid rgba(231, 231, 231, 0.8);
    text-align: center;
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    background: rgba(67, 97, 238, 0.05);
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.quick-action-btn span {
    font-size: 0.8rem;
    color: #555;
    font-weight: 500;
}

@media (max-width: 991.98px) {
    .sidebar {
        margin-left: -280px;
    }
    .sidebar.show {
        margin-left: 0;
    }
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #e0e0e0;
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #d0d0d0;
}
</style>

<div class="sidebar" id="sidebar">
    <!-- Main Navigation -->
    <div class="menu-section">
        <h6 class="menu-title">Main Navigation</h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                    <span class="nav-badge">New</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'kitchen.php' ? 'active' : ''; ?>" href="kitchen.php">
                    <i class="fas fa-utensils"></i>
                    <span>Kitchen Display</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="menu-divider"></div>

    <!-- Restaurant Management -->
    <div class="menu-section">
        <h6 class="menu-title">Management</h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'menu_management.php' ? 'active' : ''; ?>" href="menu_management.php">
                    <i class="fas fa-book-open"></i>
                    <span>Menu Items</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'tables.php' ? 'active' : ''; ?>" href="tables.php">
                    <i class="fas fa-chair"></i>
                    <span>Tables</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'qr_codes.php' ? 'active' : ''; ?>" href="qr_codes.php">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Codes</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="menu-divider"></div>

    <!-- Reports & Settings -->
    <div class="menu-section">
        <h6 class="menu-title">Analytics & Settings</h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Quick Actions Footer -->
    <div class="sidebar-footer">
        <h6 class="menu-title">Quick Actions</h6>
        <div class="quick-actions">
            <a href="add_order.php" class="quick-action-btn text-decoration-none">
                <i class="fas fa-plus-circle"></i>
                <span class="d-block">New Order</span>
            </a>
            <a href="add_item.php" class="quick-action-btn text-decoration-none">
                <i class="fas fa-utensils"></i>
                <span class="d-block">Add Item</span>
            </a>
            <a href="add_table.php" class="quick-action-btn text-decoration-none">
                <i class="fas fa-chair"></i>
                <span class="d-block">Add Table</span>
            </a>
            <a href="reports.php" class="quick-action-btn text-decoration-none">
                <i class="fas fa-chart-bar"></i>
                <span class="d-block">Reports</span>
            </a>
        </div>
    </div>
</div>

<!-- JavaScript for Sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
        });
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 992) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            sidebar.classList.remove('show');
        }
    });
});
</script> 