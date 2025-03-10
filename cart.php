<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/MenuItem.php');

$database = new Database();
$db = $database->getConnection();
$menuItemModel = new MenuItem($db);
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
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color) !important;
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
            width: 120px;
            height: 120px;
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
                width: 150px;
                height: 150px;
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .price-details {
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-utensils me-2"></i>
                Gourmet Delights
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Cart Content -->
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="cart-container">
                    <h2 class="mb-4">Shopping Cart</h2>
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
                    <button id="checkoutButton" class="checkout-btn">
                        Proceed to Checkout
                    </button>
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
                        <a href="index.php" class="continue-shopping">
                            <i class="fas fa-arrow-left"></i>
                            Continue Shopping
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
        
        document.getElementById('checkoutButton').addEventListener('click', function() {
            if (cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            // Add checkout logic here
            alert('Proceeding to checkout...');
        });
        
        // Initial cart display
        updateCartDisplay();
    });
    </script>
</body>
</html> 