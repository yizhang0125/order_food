<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/Category.php');
require_once(__DIR__ . '/classes/MenuItem.php');
require_once(__DIR__ . '/classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$systemSettings = new SystemSettings($db);
$restaurant_name = $systemSettings->getRestaurantName();

// Initialize variables
$valid_access = false;
$error_message = '';
$error_type = '';

// Get table number and token from URL
$table_number = isset($_GET['table']) ? htmlspecialchars($_GET['table']) : null;
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : null;

// Validate QR code access first before loading any menu data
if ($table_number && $token) {
    try {
        // Check if QR code is valid and not expired
        $validate_query = "SELECT t.id, t.table_number, t.status as table_status, 
                                 qc.token, qc.is_active, qc.expires_at 
                          FROM tables t 
                          JOIN qr_codes qc ON t.id = qc.table_id 
                          WHERE t.table_number = ? 
                          AND qc.token = ?
                          AND qc.is_active = 1
                          AND (qc.expires_at IS NULL OR qc.expires_at > NOW())
                          AND t.status = 'active'";
        
        $stmt = $db->prepare($validate_query);
        $stmt->execute([$table_number, $token]);
        $qr_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$qr_data) {
            // Check specific reasons for invalid access
            $check_query = "SELECT t.status as table_status, 
                                  qc.is_active, qc.expires_at 
                           FROM tables t 
                           JOIN qr_codes qc ON t.id = qc.table_id 
                           WHERE t.table_number = ? 
                           AND qc.token = ?";
            
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$table_number, $token]);
            $check_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug: Log QR validation details
            error_log("QR Validation Debug - Table: $table_number, Token: $token, Check Data: " . json_encode($check_data));

            if (!$check_data) {
                $error_message = "Invalid QR code. Please scan a valid QR code from your table.";
                $error_type = "invalid";
            } elseif ($check_data['table_status'] !== 'active') {
                $error_message = "This table is currently not available for ordering. Please contact our staff for assistance.";
                $error_type = "inactive_table";
            } elseif (!$check_data['is_active']) {
                $error_message = "This QR code has been deactivated. Please request a new QR code from our staff.";
                $error_type = "inactive_qr";
            } elseif ($check_data['expires_at'] && strtotime($check_data['expires_at']) < time()) {
                $error_message = "This QR code has expired. Please request a new QR code from our staff.";
                $error_type = "expired";
                // Debug: Log expiration check
                error_log("QR Code Expired - Expires at: " . $check_data['expires_at'] . ", Current time: " . date('Y-m-d H:i:s'));
            }
        } else {
            $valid_access = true;
        }
    } catch (PDOException $e) {
        error_log("QR validation error: " . $e->getMessage());
        $error_message = "An error occurred while validating your access. Please try again or contact our staff.";
        $error_type = "system_error";
    }
} else {
    $error_message = "Please scan the QR code from your table to access the menu.";
    $error_type = "no_qr";
}

// Show error page if access is not valid
if (!$valid_access) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Error - <?php echo htmlspecialchars($restaurant_name); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                box-sizing: border-box;
            }
            
            :root {
                --error-color: #ef4444;
                --warning-color: #f59e0b;
                --info-color: #3b82f6;
            }
            
            body {
                font-family: 'Poppins', sans-serif;
                background-color: #f8fafc;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }
            
            .error-container {
                text-align: center;
                padding: 2.5rem;
                background: white;
                border-radius: 1.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                          0 2px 4px -1px rgba(0, 0, 0, 0.06);
                max-width: 450px;
                width: 100%;
            }
            
            .error-icon {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: <?php
                    echo match($error_type) {
                        'expired', 'inactive_qr' => 'var(--warning-color)',
                        'no_qr' => 'var(--info-color)',
                        default => 'var(--error-color)'
                    };
                ?>;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2.5rem;
                margin: 0 auto 1.5rem;
            }
            
            .error-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 1rem;
            }
            
            .error-message {
                color: #4b5563;
                margin-bottom: 1.5rem;
                font-size: 1.1rem;
                line-height: 1.6;
            }
            
            .help-text {
                background: #f8fafc;
                padding: 1rem;
                border-radius: 1rem;
                font-size: 0.95rem;
                color: #64748b;
                margin-top: 1.5rem;
                border: 1px solid #e2e8f0;
            }
            
            .help-text i {
                color: var(--info-color);
                margin-right: 0.5rem;
            }
            
            .restaurant-logo {
                margin-bottom: 2rem;
                font-size: 1.5rem;
                color: #2563eb;
                font-weight: 700;
            }
            
            @media (max-width: 480px) {
                .error-container {
                    padding: 1.5rem;
                }
                
                .error-icon {
                    width: 60px;
                    height: 60px;
                    font-size: 2rem;
                }
                
                .error-title {
                    font-size: 1.25rem;
                }
                
                .error-message {
                    font-size: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="restaurant-logo">
                <i class="fas fa-utensils"></i>
                <span style="font-family: 'Poppins', sans-serif; font-weight: 700; letter-spacing: -0.02em;"><?php echo htmlspecialchars($restaurant_name); ?></span>
            </div>
            
            <div class="error-icon">
                <i class="fas fa-<?php
                    echo match($error_type) {
                        'expired' => 'clock',
                        'inactive_qr' => 'qrcode',
                        'inactive_table' => 'chair',
                        'no_qr' => 'qrcode',
                        default => 'exclamation-circle'
                    };
                ?>"></i>
            </div>
            
            <h1 class="error-title">Access Error</h1>
            <p class="error-message"><?php echo $error_message; ?></p>
            
            <div class="help-text">
                <i class="fas fa-info-circle"></i>
                For immediate assistance, please contact our staff or visit the reception desk.
                <?php if ($error_type === 'expired' || $error_type === 'inactive_qr'): ?>
                <br><br>
                <i class="fas fa-sync-alt"></i>
                Our staff will provide you with a new QR code to access the menu.
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Only load menu data if access is valid
$categoryModel = new Category($db);
$menuItemModel = new MenuItem($db);

$categories = $categoryModel->getActiveCategories();
$menu_by_category = $menuItemModel->getItemsByCategory();

// Continue with the rest of your menu page code
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($restaurant_name); ?> - Digital Menu</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Category Navigation -->
    <div class="category-nav">
        <div class="container">
            <div class="nav-scroll-wrapper">
            <nav class="nav">
                    <a class="nav-link active" href="#all">
                        <i class="fas fa-th-large me-2"></i>All
                    </a>
                <?php foreach ($categories as $category): ?>
                <a class="nav-link" href="#<?php echo htmlspecialchars($category['name']); ?>">
                        <i class="fas fa-utensils me-2"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </a>
                <?php endforeach; ?>
            </nav>
            </div>
        </div>
    </div>

    <!-- Menu Sections -->
    <div class="container">
        <?php foreach ($menu_by_category as $category => $items): ?>
        <section class="menu-section fade-in" id="<?php echo htmlspecialchars($category); ?>">
            <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
            
            <div class="row g-4">
                <?php foreach ($items as $item): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card menu-item-card">
                        <div class="menu-item-image">
                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                <img data-src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                     src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23f3f4f6'/%3E%3C/svg%3E"
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="lazy-load">
                            <?php else: ?>
                                <img data-src="assets/images/default-food.jpg" 
                                     src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23f3f4f6'/%3E%3C/svg%3E"
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="lazy-load">
                            <?php endif; ?>
                            <div class="menu-item-badge">
                                <?php echo htmlspecialchars($category); ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="menu-item-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                            <p class="menu-item-description">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="menu-item-price">
                                    RM <?php echo number_format($item['price'], 2); ?>
                                </span>
                                <div style="
                                    display: flex; 
                                    align-items: center; 
                                    background: #f8fafc; 
                                    padding: 4px; 
                                    border-radius: 50px; 
                                    border: 2px solid #e2e8f0;
                                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <button style="
                                        width: 32px; 
                                        height: 32px; 
                                        border-radius: 50%; 
                                        border: none;
                                        background: white; 
                                        color: #2563eb;
                                        display: flex; 
                                        align-items: center; 
                                        justify-content: center; 
                                        cursor: pointer; 
                                        transition: all 0.3s ease;
                                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);" 
                                        class="minus-btn" 
                                        onclick="updateQuantity(this, -1)">
                                        <i class="fas fa-minus" style="font-size: 0.8rem;"></i>
                                    </button>
                                    <input type="number" 
                                        value="1" 
                                        min="1" 
                                        max="99" 
                                        readonly 
                                        style="
                                            width: 40px; 
                                            border: none;
                                            background: transparent;
                                            text-align: center; 
                                            font-weight: 600; 
                                            color: #1e293b;
                                            font-size: 0.95rem;
                                            padding: 0 4px;
                                            -moz-appearance: textfield;
                                            margin: 0 4px;">
                                    <button style="
                                        width: 32px; 
                                        height: 32px; 
                                        border-radius: 50%; 
                                        border: none;
                                        background: white; 
                                        color: #2563eb;
                                        display: flex; 
                                        align-items: center; 
                                        justify-content: center; 
                                        cursor: pointer; 
                                        transition: all 0.3s ease;
                                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);" 
                                        class="plus-btn" 
                                        onclick="updateQuantity(this, 1)">
                                        <i class="fas fa-plus" style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
                            </div>
                            <button class="order-button" onclick="addToCart(<?php echo $item['id']; ?>, event)">
                                <i class="fas fa-plus"></i>
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

    <!-- Add Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Shopping Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="cartItems" class="cart-items">
                        <!-- Cart items will be dynamically added here -->
                    </div>
                    <div id="emptyCartMessage" class="text-center py-4" style="display: none;">
                        <i class="fas fa-shopping-basket fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Your cart is empty</p>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <div class="w-100">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Total:</span>
                            <span class="fw-bold" id="cartTotal">RM 0.00</span>
                        </div>
                        <button id="checkoutButton" class="btn btn-primary w-100">
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Lazy Loading Script -->
    <script>
        // Lazy loading implementation
        document.addEventListener('DOMContentLoaded', function() {
            const lazyImages = document.querySelectorAll('.lazy-load');
            
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy-load');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });
            
            lazyImages.forEach(img => {
                imageObserver.observe(img);
            });
        });
    </script>
    
    <script src="js/index.js"></script>

    <!-- Floating cart button -->
    <div class="floating-cart">
        <button class="cart-button" onclick="goToCart()">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-badge">0</span>
        </button>
    </div>


    <!-- Add this CSS style for the added feedback -->
    <style>
    .order-button.added {
        background-color: #10b981 !important; /* Success green color */
        transform: scale(0.95);
    }
    </style>
</body>
</html> 