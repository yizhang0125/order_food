<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/Category.php');
require_once(__DIR__ . '/classes/MenuItem.php');

$database = new Database();
$db = $database->getConnection();

$categoryModel = new Category($db);
$menuItemModel = new MenuItem($db);

$categories = $categoryModel->getActiveCategories();
$menu_by_category = $menuItemModel->getItemsByCategory();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Restaurant Menu</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1d4ed8;
            --accent-color: #3b82f6;
            --background-color: #f8fafc;
            --text-color: #1e293b;
            --card-background: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            padding-top: 80px;
            color: var(--text-color);
        }

        /* Navbar Styles */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
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

        /* Category Navigation */
        .category-nav {
            position: sticky;
            top: 80px;
            background: var(--card-background);
            z-index: 1020;
            padding: 1rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .nav-scroll-wrapper {
            position: relative;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .nav-scroll-wrapper::-webkit-scrollbar {
            display: none;
        }

        .category-nav .nav {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            padding: 0.5rem;
        }

        .category-nav .nav-link {
            color: var(--text-color);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            white-space: nowrap;
            font-weight: 500;
            background: transparent;
            border: 2px solid transparent;
        }

        .category-nav .nav-link:hover {
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.1);
            transform: translateY(-1px);
        }

        .category-nav .nav-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Menu Section Styles */
        .menu-section {
            padding: 3rem 0;
        }

        .category-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
        }

        .category-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50%;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        /* Menu Item Card Styles */
        .menu-item-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            background: var(--card-background);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .menu-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .menu-item-image {
            height: 250px;
            position: relative;
            overflow: hidden;
        }

        .menu-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .menu-item-card:hover .menu-item-image img {
            transform: scale(1.1);
        }

        .menu-item-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.95);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            color: var(--primary-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 1.5rem;
        }

        .menu-item-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .menu-item-description {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .menu-item-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .order-button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .order-button i {
            font-size: 1.1rem;
        }

        /* Cart Icon Styles */
        .cart-icon,
        .cart-badge {
            display: none;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }

            .category-nav {
                top: 70px;
                padding: 0.5rem 0;
            }

            .category-nav .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .menu-item-image {
                height: 200px;
            }

            .menu-item-title {
                font-size: 1.1rem;
            }

            .menu-item-price {
                font-size: 1.25rem;
            }

            .order-button {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .floating-cart {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1030;
        }

        .cart-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            border: none;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            background: var(--secondary-color);
        }

        .cart-button i {
            font-size: 1.5rem;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .floating-cart {
                bottom: 1rem;
                right: 1rem;
            }

            .cart-button {
                width: 50px;
                height: 50px;
            }

            .cart-button i {
                font-size: 1.25rem;
            }

            .cart-badge {
                width: 20px;
                height: 20px;
                font-size: 0.75rem;
            }
        }

        /* Add animation for category filtering */
        .menu-section {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .menu-section.fade-in {
            animation: fadeIn 0.5s ease-in forwards;
        }

        .menu-section:not(.fade-in) {
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        /* Updated flying animation styles */
        .flying-image {
            position: fixed;
            width: 80px;
            height: 80px;
            pointer-events: none;
            z-index: 9999;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .flying-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        @keyframes floatEffect {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(-5deg);
            }
            100% {
                transform: translateY(0) rotate(0deg);
            }
        }

        .cart-button.receiving {
            animation: cartReceive 0.6s ease-out;
        }

        @keyframes cartReceive {
            0% { transform: scale(1); }
            40% { transform: scale(1.4); }
            100% { transform: scale(1); }
        }

        /* New parabolic animation styles */
        .parabolic-item {
            position: fixed;
            width: 60px;
            height: 60px;
            pointer-events: none;
            z-index: 9999;
            transition: none;
        }

        .parabolic-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(720deg); }
        }

        .cart-button.pop {
            animation: popEffect 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes popEffect {
            0% { transform: scale(1); }
            50% { transform: scale(1.35); }
            65% { transform: scale(0.9); }
            80% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Cart Modal Styles */
        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            animation: slideIn 0.3s ease-out;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 1rem;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .cart-item-price {
            color: var(--primary-color);
            font-weight: 600;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn {
            width: 24px;
            height: 24px;
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
            min-width: 20px;
            text-align: center;
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

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            padding: 1.5rem;
        }

        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1.5rem;
        }

        #checkoutButton {
            border-radius: 50px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #checkoutButton:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-utensils"></i>
                Gourmet Delights
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Cart icon removed -->
                </ul>
            </div>
        </div>
    </nav>

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
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <img src="assets/images/default-food.jpg" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
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
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="menu-item-price">
                                    RM <?php echo number_format($item['price'], 2); ?>
                                </span>
                                <button class="order-button" onclick="addToCart(<?php echo $item['id']; ?>, event)">
                                    <i class="fas fa-plus"></i>
                                    Add to Cart
                                </button>
                            </div>
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Category filter functionality
        document.querySelectorAll('.category-nav .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Update active state
                document.querySelectorAll('.category-nav .nav-link').forEach(l => {
                    l.classList.remove('active');
                });
                this.classList.add('active');
                
                // Get category from href
                const targetId = this.getAttribute('href').substring(1);
                
                // Show/hide sections based on category
                document.querySelectorAll('.menu-section').forEach(section => {
                    if (targetId === 'all') {
                        section.style.display = 'block';
                        section.classList.add('fade-in');
                    } else {
                        if (section.id === targetId) {
                            section.style.display = 'block';
                            section.classList.add('fade-in');
                        } else {
                            section.style.display = 'none';
                            section.classList.remove('fade-in');
                        }
                    }
                });

                // Smooth scroll to top of menu section
                if (targetId === 'all') {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                } else {
                    const targetSection = document.getElementById(targetId);
                    if (targetSection) {
                        targetSection.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });

        // Intersection Observer for fade-in animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.menu-section').forEach(section => {
            observer.observe(section);
        });

        // Initialize cart from localStorage
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        updateCartBadge(); // Update badge with stored cart items
        
        // Enhanced addToCart function
        window.addToCart = function(itemId, event) {
            const button = event.target.closest('.order-button');
            const card = button.closest('.menu-item-card');
            const itemName = card.querySelector('.menu-item-title').textContent;
            const itemPrice = parseFloat(card.querySelector('.menu-item-price').textContent.replace('RM ', ''));
            const itemImage = card.querySelector('.menu-item-image img').src;
            
            // Check if item already exists in cart
            const existingItem = cart.find(item => item.id === itemId);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    id: itemId,
                    name: itemName,
                    price: itemPrice,
                    image: itemImage,
                    quantity: 1
                });
            }
            
            // Save cart to localStorage
            localStorage.setItem('cart', JSON.stringify(cart));
            
            // Animate item to cart
            animateToCart(button, itemImage);
        updateCartBadge();
        }
        
        function animateToCart(button, itemImage) {
            const buttonRect = button.getBoundingClientRect();
            const cartButton = document.querySelector('.cart-button');
            const cartRect = cartButton.getBoundingClientRect();
            
            const parabolicItem = document.createElement('div');
            parabolicItem.className = 'parabolic-item';
            parabolicItem.innerHTML = `<img src="${itemImage}" alt="Item">`;
            
            // Set initial position
            const startX = buttonRect.left + buttonRect.width / 2;
            const startY = buttonRect.top + window.scrollY;
            const endX = cartRect.left + cartRect.width / 2;
            const endY = cartRect.top + window.scrollY;
            
            parabolicItem.style.left = startX - 30 + 'px';
            parabolicItem.style.top = startY - 30 + 'px';
            document.body.appendChild(parabolicItem);
            
            // Button feedback
            button.innerHTML = '<i class="fas fa-check"></i> Added';
            button.style.background = '#10b981';
            button.style.transform = 'scale(0.95)';
            
            // Animation
            const duration = 1000;
            const startTime = performance.now();
            
            parabolicItem.querySelector('img').style.animation = 'spin 1s linear infinite';
            
            function animate(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = elapsed / duration;
                
                if (progress >= 1) {
                    document.body.removeChild(parabolicItem);
                    cartButton.classList.add('pop');
                    updateCartBadge();
                    return;
                }
                
                const x = startX + (endX - startX) * progress;
                const heightOffset = 200 * Math.sin(progress * Math.PI);
                const y = startY + (endY - startY) * progress - heightOffset;
                
                parabolicItem.style.left = (x - 30) + 'px';
                parabolicItem.style.top = (y - 30) + 'px';
                parabolicItem.style.transform = `scale(${1 - (progress * 0.5)})`;
                
                requestAnimationFrame(animate);
            }
            
            requestAnimationFrame(animate);
            
        setTimeout(() => {
                button.innerHTML = '<i class="fas fa-plus"></i> Add to Cart';
            button.style.background = '';
                button.style.transform = '';
                cartButton.classList.remove('pop');
            }, 1200);
        }
        
        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            const emptyCartMessage = document.getElementById('emptyCartMessage');
            const cartTotal = document.getElementById('cartTotal');
            const cartBadge = document.querySelector('.cart-badge');
            
            // Update cart badge
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            cartBadge.textContent = totalItems;
            
            // Show/hide empty cart message
            if (cart.length === 0) {
                cartItems.innerHTML = '';
                emptyCartMessage.style.display = 'block';
                cartTotal.textContent = 'RM 0.00';
                return;
            }
            
            emptyCartMessage.style.display = 'none';
            
            // Update cart items
            cartItems.innerHTML = cart.map(item => `
                <div class="cart-item" data-id="${item.id}">
                    <div class="cart-item-image">
                        <img src="${item.image}" alt="${item.name}">
                    </div>
                    <div class="cart-item-details">
                        <div class="cart-item-title">${item.name}</div>
                        <div class="cart-item-price">RM ${(item.price * item.quantity).toFixed(2)}</div>
                        <div class="cart-item-quantity mt-2">
                            <button class="quantity-btn minus" onclick="updateQuantity(${item.id}, -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity-value">${item.quantity}</span>
                            <button class="quantity-btn plus" onclick="updateQuantity(${item.id}, 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
            
            // Update total
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            cartTotal.textContent = `RM ${total.toFixed(2)}`;
        }
        
        window.updateQuantity = function(itemId, change) {
            const item = cart.find(item => item.id === itemId);
            if (item) {
                item.quantity += change;
                if (item.quantity <= 0) {
                    cart = cart.filter(item => item.id !== itemId);
                }
                updateCartDisplay();
            }
    }
    
    function updateCartBadge() {
            const cartBadge = document.querySelector('.cart-badge');
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            cartBadge.textContent = totalItems;
            cartBadge.style.display = totalItems > 0 ? 'flex' : 'none';
        }
        
        // Redirect to cart page when clicking cart button
        document.querySelector('.cart-button').addEventListener('click', function() {
            window.location.href = 'cart.php';
        });
    });
    </script>

    <!-- Floating cart button -->
    <div class="floating-cart">
        <button class="cart-button">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-badge">0</span>
        </button>
    </div>
</body>
</html> 