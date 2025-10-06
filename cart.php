<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/MenuItem.php');
require_once(__DIR__ . '/classes/Order.php');
require_once(__DIR__ . '/classes/Cart.php');
require_once(__DIR__ . '/classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$menuItemModel = new MenuItem($db);
$orderModel = new Order($db);
$cart = new Cart();
$systemSettings = new SystemSettings($db);
$restaurant_name = $systemSettings->getRestaurantName();

// Cash rounding function - rounds to nearest 0.05 (5 cents)
if (!function_exists('customRound')) {
    function customRound($amount) {
        // Round to nearest 0.05 (5 cents) for cash transactions
        // Multiply by 20, round to nearest integer, then divide by 20
        return round($amount * 20) / 20;
    }
}

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $cart_items = isset($_POST['cart_items']) ? json_decode($_POST['cart_items'], true) : [];
        $table_number = isset($_POST['table_number']) ? $_POST['table_number'] : null;
        $token = isset($_POST['token']) ? $_POST['token'] : null;
        
        if (empty($cart_items)) {
            throw new Exception('Cart is empty');
        }
        
        if (!$table_number || !$token) {
            throw new Exception('Table number and token are required');
        }
        
        // Validate token
        $validate_query = "SELECT t.id, t.table_number, qc.token 
                          FROM tables t 
                          JOIN qr_codes qc ON t.id = qc.table_id 
                          WHERE t.table_number = ? 
                          AND qc.token = ? 
                          AND qc.is_active = 1 
                          AND (qc.expires_at IS NULL OR qc.expires_at > NOW())";
        $stmt = $db->prepare($validate_query);
        $stmt->execute([$table_number, $token]);
        $table_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$table_data) {
            throw new Exception('Invalid or expired QR code');
        }
        
        // Calculate total amount including tax and service tax using Cart class
        $subtotal = array_reduce($cart_items, function($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);
        $tax_rate = $cart->getTaxRatePercent() / 100; // Get dynamic tax rate from Cart class
        $service_tax_rate = $cart->getServiceTaxRatePercent() / 100; // Get dynamic service tax rate from Cart class
        $tax_amount = $subtotal * $tax_rate;
        $service_tax_amount = $subtotal * $service_tax_rate;
        $total_amount = $subtotal + $tax_amount + $service_tax_amount;
        
        // Apply cash rounding to the final total
        $total_amount = customRound($total_amount);
        
        // Start transaction
        $db->beginTransaction();
        
        // Create order
        $stmt = $db->prepare("INSERT INTO orders (table_id, status, total_amount, created_at) VALUES (?, 'pending', ?, NOW())");
        $stmt->execute([$table_data['id'], $total_amount]);
        $order_id = $db->lastInsertId();
        
        // Insert order items with special instructions
        $stmt = $db->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, special_instructions, created_at) 
                             VALUES (?, ?, ?, ?, ?, NOW())");
        
        foreach ($cart_items as $item) {
            $stmt->execute([
                $order_id,
                $item['id'],
                $item['quantity'],
                $item['price'],
                isset($item['instructions']) ? $item['instructions'] : null
            ]);
        }
        
        // Commit transaction
        $db->commit();
        
        $response['success'] = true;
        $response['order_id'] = $order_id;
        
        // If it's an AJAX request, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        }
        
        // For non-AJAX requests, redirect with table number and token
        header("Location: order_confirmation.php?order_id=" . $order_id . 
               "&table=" . $table_number . 
               "&token=" . $token);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction if there was an error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        $response['message'] = $e->getMessage();
        
        // If it's an AJAX request, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        }
        
        // For non-AJAX requests, set error message and continue to display page
        $error_message = $e->getMessage();
    }
}

// Get table number and token from URL
$table_number = isset($_GET['table']) ? htmlspecialchars($_GET['table']) : null;
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : null;

// Validate token
if ($table_number && $token) {
    try {
        $validate_query = "SELECT t.id, t.table_number, qc.token 
                          FROM tables t 
                          JOIN qr_codes qc ON t.id = qc.table_id 
                          WHERE t.table_number = ? 
                          AND qc.token = ? 
                          AND qc.is_active = 1 
                          AND (qc.expires_at IS NULL OR qc.expires_at > NOW())";
        $stmt = $db->prepare($validate_query);
        $stmt->execute([$table_number, $token]);
        $table_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$table_data) {
            $error_message = "Invalid or expired QR code. Please scan again.";
            $table_number = null;
            $token = null;
        }
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error_message = "Error validating table access.";
        $table_number = null;
        $token = null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - <?php echo htmlspecialchars($restaurant_name); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="admin/css/cart.css" rel="stylesheet">
    
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Cart Content -->
    <div class="container">
        <?php if (!$table_number): ?>
        <div class="alert alert-warning mt-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Please scan the QR code from your table to place an order.
        </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="cart-container">
                    <h2 class="mb-4">Cart</h2>
                    <div id="cartItems">
                        <!-- Cart items will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="cart-summary">
                    <h3 class="mb-4">Order Summary</h3>
                    <div class="summary-item">
                        <span>Subtotal</span>
                        <span id="subtotal">RM 0.00</span>
                    </div>
                    <div class="summary-item">
                        <span id="tax-label"><?php echo $cart->getTaxName(); ?> (<?php echo $cart->getTaxRatePercent(); ?>%)</span>
                        <span id="tax"><?php echo $cart->getCurrencySymbol(); ?> 0.00</span>
                    </div>
                    <div class="summary-item">
                        <span id="service-tax-label"><?php echo $cart->getServiceTaxName(); ?> (<?php echo $cart->getServiceTaxRatePercent(); ?>%)</span>
                        <span id="service-tax"><?php echo $cart->getCurrencySymbol(); ?> 0.00</span>
                    </div>
                    <div class="summary-total">
                        <span>Total</span>
                        <span id="total">RM 0.00</span>
                    </div>
                    <?php if ($table_number): ?>
                    <button type="button" class="checkout-btn" onclick="proceedToCheckout()">
                        Proceed to Checkout
                    </button>
                    <?php else: ?>
                    <button type="button" class="checkout-btn" disabled>
                        Please scan table QR code
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get table and token from URL to create unique cart key
        const urlParams = new URLSearchParams(window.location.search);
        const tableNumber = urlParams.get('table');
        const token = urlParams.get('token');
        
        // Create unique cart key for this table and token
        const cartKey = tableNumber && token ? `cart_${tableNumber}_${token}` : 'cart_default';
        
        // Load cart from localStorage using unique key
        let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
        
        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                        <a href="index.php<?php echo ($table_number && $token) ? '?table=' . $table_number . '&token=' . $token : ''; ?>" class="continue-shopping">
                            <i class="fas fa-arrow-left"></i>
                            Back to Menu
                        </a>
                    </div>`;
                updateSummary();
                return;
            }
            
            cartItems.innerHTML = cart.map(item => `
                <div class="cart-item" data-id="${item.id}">
                    <div class="cart-item-image">
                        <img src="${item.image}" alt="${item.name}">
                    </div>
                    <div class="cart-item-details">
                        <div class="cart-item-title">${item.name}</div>
                        <div class="price-details">
                            <div class="unit-price">
                                Unit Price: RM ${item.price.toFixed(2)}
                            </div>
                            <div class="total-price">
                                Total: RM ${(item.price * item.quantity).toFixed(2)}
                            </div>
                        </div>
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="updateQuantity(${item.id}, -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity-value">${item.quantity}</span>
                            <button class="quantity-btn" onclick="updateQuantity(${item.id}, 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-link text-danger ms-3" onclick="removeItem(${item.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="special-instructions">
                            <textarea 
                                placeholder="Add special instructions (optional)" 
                                onchange="updateInstructions(${item.id}, this.value)"
                                >${item.instructions || ''}</textarea>
                        </div>
                    </div>
                </div>
            `).join('');
            
            updateSummary();
        }
        
        function updateSummary() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            // Use Cart class tax settings (from PHP)
            const taxRate = <?php echo $cart->getTaxRatePercent() / 100; ?>; // Get dynamic tax rate from Cart class
            const serviceTaxRate = <?php echo $cart->getServiceTaxRatePercent() / 100; ?>; // Get dynamic service tax rate from Cart class
            const tax = subtotal * taxRate;
            const serviceTax = subtotal * serviceTaxRate;
            const total = subtotal + tax + serviceTax;
            
            // Apply cash rounding to the total (nearest 0.05)
            const cashRoundedTotal = Math.round(total * 20) / 20;
            
            const currencySymbol = '<?php echo $cart->getCurrencySymbol(); ?>';
            
            document.getElementById('subtotal').textContent = `${currencySymbol} ${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `${currencySymbol} ${tax.toFixed(2)}`;
            document.getElementById('service-tax').textContent = `${currencySymbol} ${serviceTax.toFixed(2)}`;
            document.getElementById('total').textContent = `${currencySymbol} ${cashRoundedTotal.toFixed(2)}`;
        }
        
        window.updateQuantity = function(itemId, change) {
            const item = cart.find(item => item.id === itemId);
            if (item) {
                item.quantity += change;
                if (item.quantity <= 0) {
                    cart = cart.filter(item => item.id !== itemId);
                }
                localStorage.setItem(cartKey, JSON.stringify(cart));
                updateCartDisplay();
            }
        }
        
        window.removeItem = function(itemId) {
            cart = cart.filter(item => item.id !== itemId);
            localStorage.setItem(cartKey, JSON.stringify(cart));
            updateCartDisplay();
        }

        // Add function to update instructions
        window.updateInstructions = function(itemId, instructions) {
            const item = cart.find(item => item.id === itemId);
            if (item) {
                item.instructions = instructions;
                localStorage.setItem(cartKey, JSON.stringify(cart));
            }
        }

        // Modify the proceedToCheckout function to include instructions
        window.proceedToCheckout = function() {
            if (cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            
            const urlParams = new URLSearchParams(window.location.search);
            const tableNumber = urlParams.get('table');
            const token = urlParams.get('token');
            
            if (!tableNumber || !token) {
                alert('Please scan the QR code from your table to place an order.');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'cart.php' + `?table=${tableNumber}&token=${token}`;
            
            const checkoutInput = document.createElement('input');
            checkoutInput.type = 'hidden';
            checkoutInput.name = 'checkout';
            checkoutInput.value = '1';
            form.appendChild(checkoutInput);
            
            const cartInput = document.createElement('input');
            cartInput.type = 'hidden';
            cartInput.name = 'cart_items';
            cartInput.value = JSON.stringify(cart);
            form.appendChild(cartInput);
            
            const tableInput = document.createElement('input');
            tableInput.type = 'hidden';
            tableInput.name = 'table_number';
            tableInput.value = tableNumber;
            form.appendChild(tableInput);
            
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'token';
            tokenInput.value = token;
            form.appendChild(tokenInput);
            
            document.body.appendChild(form);
            form.submit();
            
            localStorage.removeItem(cartKey);
        }

        // Initial cart display
        updateCartDisplay();
    });
    </script>
</body>
</html> 