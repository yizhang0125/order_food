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

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
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
                <a href="orders.php" class="nav-link <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payment_counter.php" class="nav-link <?php echo $current_page == 'payment_counter.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cash-register"></i>
                    <span>Payment Counter</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'completed_orders.php' ? 'active' : ''; ?>" href="completed_orders.php">
                    <i class="fas fa-check-circle"></i>
                    <span>Completed Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'cancelled_orders.php' ? 'active' : ''; ?>" href="cancelled_orders.php">
                    <i class="fas fa-times-circle"></i>
                    <span>Cancelled Orders</span>
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
                <a class="nav-link <?php echo $current_page == 'payment_details.php' ? 'active' : ''; ?>" href="payment_details.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payment Details</span>
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
                <a class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- JavaScript for Sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const body = document.body;

    // Function to check window width
    function isDesktop() {
        return window.innerWidth >= 992;
    }

    // Function to create overlay
    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.addEventListener('click', () => {
            toggleSidebar();
        });
        return overlay;
    }

    // Toggle sidebar function
    function toggleSidebar() {
        if (!sidebar) return;
        
        sidebar.classList.toggle('show');
        
        // Toggle icon
        if (sidebarToggle) {
            const icon = sidebarToggle.querySelector('i');
            if (sidebar.classList.contains('show')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
        
        // Handle overlay
        if (!isDesktop()) {
            const existingOverlay = document.querySelector('.sidebar-overlay');
            if (sidebar.classList.contains('show')) {
                if (!existingOverlay) {
                    const overlay = createOverlay();
                    body.appendChild(overlay);
                    // Fade in effect
                    setTimeout(() => overlay.style.opacity = '1', 0);
                }
            } else {
                if (existingOverlay) {
                    // Fade out effect
                    existingOverlay.style.opacity = '0';
                    setTimeout(() => existingOverlay.remove(), 300);
                }
            }
        }
    }

    // Initialize toggle button event
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (!isDesktop()) {
            if (sidebar && sidebar.classList.contains('show')) {
                if (!sidebar.contains(e.target) && 
                    !sidebarToggle.contains(e.target)) {
                    toggleSidebar();
                }
            }
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (isDesktop()) {
                sidebar.classList.remove('show');
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    overlay.remove();
                }
                // Reset toggle button icon
                if (sidebarToggle) {
                    const icon = sidebarToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        }, 250);
    });
});
</script> 