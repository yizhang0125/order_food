<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/MenuItem.php');
require_once(__DIR__ . '/classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$menuItemModel = new MenuItem($db);
$orderModel = new Order($db);

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
        
        // Calculate total amount including SST
        $subtotal = array_reduce($cart_items, function($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);
        $total_amount = $subtotal + ($subtotal * 0.06); // Add 6% SST
        
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
    <title>Shopping Cart - Gourmet Delights</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1d4ed8;
            --background-color: #f8fafc;
            --text-color: #1e293b;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            padding-top: 80px;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            top: 0 !important;
            z-index: 1030;
            height: auto;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color) !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            font-size: 1.8rem;
        }

        .cart-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            animation: slideIn 0.3s ease-out;
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            overflow: hidden;
            margin-right: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .special-instructions {
            margin-top: 0.5rem;
            width: 100%;
        }

        .special-instructions textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 60px;
            transition: all 0.3s ease;
        }

        .special-instructions textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }

        .special-instructions textarea::placeholder {
            color: #9ca3af;
        }

        .cart-item-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .price-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            margin: 0.5rem 0;
        }

        .unit-price {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .total-price {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: #f3f4f6;
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-btn:hover {
            background: #e5e7eb;
        }

        .quantity-value {
            font-weight: 600;
            font-size: 1.1rem;
            min-width: 24px;
            text-align: center;
        }

        .cart-summary {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            position: sticky;
            top: 100px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .summary-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #e5e7eb;
        }

        .checkout-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
        }

        .checkout-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .empty-cart {
            text-align: center;
            padding: 3rem;
        }

        .empty-cart i {
            font-size: 4rem;
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }

        .empty-cart p {
            color: #64748b;
            margin-bottom: 2rem;
        }

        .continue-shopping {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .continue-shopping:hover {
            transform: translateX(-5px);
            color: var(--secondary-color);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }

            .cart-item-image {
                width: 180px;
                height: 180px;
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .special-instructions {
                margin-top: 0.75rem;
            }

            .special-instructions textarea {
                text-align: left;
            }

            .price-details {
                align-items: center;
            }
        }

        /* Add table info banner styles */
        .table-info-banner {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8));
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin: 0 15px;
            transition: all 0.3s ease;
        }

        .table-info-banner:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .table-info-banner .table-number {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        /* Add view orders button styles */
        .view-orders-btn {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .view-orders-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .view-orders-btn i {
            font-size: 1rem;
        }

        /* Add responsive styles */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.5rem 0;
            }

            .view-orders-btn {
                padding: 0.4rem 1rem;
                font-size: 0.9rem;
            }

            .table-info-banner {
                margin: 0 10px;
                padding: 6px 12px;
            }

            .table-info-banner .table-number {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 0.4rem 0;
            }

            .table-info-banner {
                margin: 0 8px;
                padding: 4px 10px;
            }

            .table-info-banner .table-number {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php<?php echo ($table_number && $token) ? '?table=' . $table_number . '&token=' . $token : ''; ?>">
                <i class="fas fa-utensils"></i>
                Gourmet Delights
            </a>
            <div class="d-flex align-items-center gap-3">
                <?php if ($table_number && $token): ?>
                <div class="table-info-banner">
                    <span class="table-number">Table <?php echo $table_number; ?></span>
                </div>
                <a href="view_orders.php?table=<?php echo $table_number; ?>&token=<?php echo $token; ?>" class="view-orders-btn">
                    <i class="fas fa-list-ul"></i>
                    View Orders
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

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
                        <span>SST (6%)</span>
                        <span id="tax">RM 0.00</span>
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
        // Load cart from localStorage
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        
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
            const sst = subtotal * 0.06; // Malaysian SST 6%
            const total = subtotal + sst;
            
            document.getElementById('subtotal').textContent = `RM ${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `RM ${sst.toFixed(2)}`;
            document.getElementById('total').textContent = `RM ${total.toFixed(2)}`;
        }
        
        window.updateQuantity = function(itemId, change) {
            const item = cart.find(item => item.id === itemId);
            if (item) {
                item.quantity += change;
                if (item.quantity <= 0) {
                    cart = cart.filter(item => item.id !== itemId);
                }
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartDisplay();
            }
        }
        
        window.removeItem = function(itemId) {
            cart = cart.filter(item => item.id !== itemId);
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartDisplay();
        }

        // Add function to update instructions
        window.updateInstructions = function(itemId, instructions) {
            const item = cart.find(item => item.id === itemId);
            if (item) {
                item.instructions = instructions;
                localStorage.setItem('cart', JSON.stringify(cart));
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
            
            localStorage.removeItem('cart');
        }

        // Initial cart display
        updateCartDisplay();
    });
    </script>
</body>
</html> 