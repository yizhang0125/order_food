// Get table and token from URL to create unique cart key
const urlParams = new URLSearchParams(window.location.search);
const tableNumber = urlParams.get('table');
const token = urlParams.get('token');

// Create unique cart key for this table and token
const cartKey = tableNumber && token ? `cart_${tableNumber}_${token}` : 'cart_default';

// Define cart and functions in global scope
let cart = JSON.parse(localStorage.getItem(cartKey)) || [];

function goToCart() {
    const urlParams = new URLSearchParams(window.location.search);
    const tableNumber = urlParams.get('table');
    const token = urlParams.get('token');
    
    if (tableNumber && token) {
        window.location.href = `cart.php?table=${tableNumber}&token=${token}`;
    } else {
        window.location.href = 'cart.php';
    }
}

function updateCartBadge() {
    const cartBadge = document.querySelector('.cart-badge');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartBadge.textContent = totalItems;
    cartBadge.style.display = totalItems > 0 ? 'flex' : 'none';
}

function animateToCart(button, itemImage) {
    // Get the coordinates
    const buttonRect = button.getBoundingClientRect();
    const cartButton = document.querySelector('.cart-button');
    const cartRect = cartButton.getBoundingClientRect();
    
    // Create the flying item
    const flyingItem = document.createElement('div');
    flyingItem.className = 'parabolic-item';
    flyingItem.innerHTML = `<img src="${itemImage}" alt="Item">`;
    
    // Set initial position (relative to viewport)
    const startX = buttonRect.left + (buttonRect.width / 2);
    const startY = buttonRect.top;
    const endX = cartRect.left + (cartRect.width / 2);
    const endY = cartRect.top;
    
    flyingItem.style.left = `${startX - 30}px`;
    flyingItem.style.top = `${startY - 30}px`;
    document.body.appendChild(flyingItem);
    
    // Button feedback
    button.classList.add('added');
    button.innerHTML = '<i class="fas fa-check"></i> Added';
    
    // Animation
    const duration = 800;
    const startTime = performance.now();
    
    // Add spin animation
    flyingItem.querySelector('img').style.animation = 'spin 0.8s linear infinite';
    
    function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        if (progress >= 1) {
            if (flyingItem.parentNode) {
                document.body.removeChild(flyingItem);
            }
            cartButton.classList.add('pop');
            updateCartBadge();
            return;
        }
        
        // Parabolic motion
        const x = startX + (endX - startX) * progress;
        const heightOffset = 150 * Math.sin(progress * Math.PI);
        const y = startY + (endY - startY) * progress - heightOffset;
        
        // Scale down as it moves
        const scale = 1 - progress * 0.5;
        
        flyingItem.style.left = `${x - 30}px`;
        flyingItem.style.top = `${y - 30}px`;
        flyingItem.style.transform = `scale(${scale})`;
        
        requestAnimationFrame(animate);
    }
    
    requestAnimationFrame(animate);
    
    // Reset button after animation
    setTimeout(() => {
        button.classList.remove('added');
        button.innerHTML = '<i class="fas fa-plus"></i> Add to Cart';
        cartButton.classList.remove('pop');
    }, 1000);
}

// Replace the existing updateQuantity function with this enhanced version
function updateQuantity(button, change) {
    const container = button.parentElement;
    const input = container.querySelector('input[type="number"]');
    const currentValue = parseInt(input.value);
    const newValue = currentValue + change;
    
    if (newValue >= 1 && newValue <= 99) {
        input.value = newValue;
        
        // Update button states
        const minusBtn = container.querySelector('.minus-btn');
        const plusBtn = container.querySelector('.plus-btn');
        
        if (newValue <= 1) {
            minusBtn.style.opacity = '0.5';
            minusBtn.style.cursor = 'not-allowed';
            minusBtn.style.transform = 'scale(0.95)';
        } else {
            minusBtn.style.opacity = '1';
            minusBtn.style.cursor = 'pointer';
            minusBtn.style.transform = 'scale(1)';
        }
        
        if (newValue >= 99) {
            plusBtn.style.opacity = '0.5';
            plusBtn.style.cursor = 'not-allowed';
            plusBtn.style.transform = 'scale(0.95)';
        } else {
            plusBtn.style.opacity = '1';
            plusBtn.style.cursor = 'pointer';
            plusBtn.style.transform = 'scale(1)';
        }

        // Add click effect
        button.style.transform = 'scale(0.9)';
        setTimeout(() => {
            button.style.transform = 'scale(1)';
        }, 100);
    }
}

// Find and replace the existing addToCart function with this updated version
function addToCart(itemId, event) {
    const button = event.target.closest('.order-button');
    const card = button.closest('.menu-item-card');
    const itemName = card.querySelector('.menu-item-title').textContent;
    const itemPrice = parseFloat(card.querySelector('.menu-item-price').textContent.replace('RM ', ''));
    const itemImage = card.querySelector('.menu-item-image img').src;
    const quantity = parseInt(card.querySelector('input[type="number"]').value);
    
    // Check if item already exists in cart
    const existingItem = cart.find(item => item.id === itemId);
    
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: itemId,
            name: itemName,
            price: itemPrice,
            image: itemImage,
            quantity: quantity
        });
    }
    
    // Save cart to localStorage
    localStorage.setItem(cartKey, JSON.stringify(cart));
    
    // Reset quantity to 1 after adding to cart
    card.querySelector('input[type="number"]').value = '1';
    
    // Animate item to cart
    animateToCart(button, itemImage);
    updateCartBadge();
}

// Scroll handling variables
let lastScroll = 0;
const navbar = document.querySelector('.navbar');
const categoryNav = document.querySelector('.category-nav');
const delta = 5;
const navbarHeight = navbar.offsetHeight;
let scrollTimer = null;

// Scroll event handler
function handleScroll() {
    const currentScroll = window.pageYOffset;
    
    // Clear the existing timer
    if (scrollTimer !== null) {
        clearTimeout(scrollTimer);
    }
    
    // Set a new timer
    scrollTimer = setTimeout(() => {
        if (Math.abs(lastScroll - currentScroll) <= delta) return;
        
        if (currentScroll > lastScroll && currentScroll > navbarHeight) {
            // Scrolling down - hide only the main navbar
            navbar.classList.add('nav-up');
        } else if (currentScroll < lastScroll) {
            // Scrolling up - show the navbar again
            navbar.classList.remove('nav-up');
        }
        
        lastScroll = currentScroll;
    }, 10); // Small delay for smoother animation
}

// Smooth scroll for category links
function handleCategoryScroll(link) {
    const targetId = link.getAttribute('href').substring(1);
    const targetSection = document.getElementById(targetId);
    if (targetSection) {
        const offset = categoryNav.offsetHeight + 20;
        const targetPosition = targetSection.offsetTop - offset;
        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
        });
    }
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart badge
    updateCartBadge();

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

            // Smooth scroll
            if (targetId === 'all') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    targetSection.scrollIntoView({ behavior: 'smooth' });
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
    }, { threshold: 0.1 });

    document.querySelectorAll('.menu-section').forEach(section => {
        observer.observe(section);
    });

    // Add click event for cart button
    document.querySelector('.cart-button').addEventListener('click', goToCart);

    // Initialize quantity buttons
    document.querySelectorAll('input[type="number"]').forEach(input => {
        const container = input.parentElement;
        const minusBtn = container.querySelector('.minus-btn');
        
        // Set initial state of minus button
        minusBtn.style.opacity = '0.5';
        minusBtn.style.cursor = 'not-allowed';
        minusBtn.style.transform = 'scale(0.95)';

        // Add hover effects for buttons
        const buttons = container.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.addEventListener('mouseover', function() {
                if (this.style.cursor !== 'not-allowed') {
                    this.style.transform = 'scale(1.05)';
                    this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                }
            });
            
            btn.addEventListener('mouseout', function() {
                if (this.style.cursor !== 'not-allowed') {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)';
                }
            });
        });
    });

    // Handle image loading and optimization
    document.querySelectorAll('.menu-item-image img').forEach(img => {
        img.parentElement.classList.add('loading');
        
        img.onload = function() {
            this.parentElement.classList.remove('loading');
            
            // Get device characteristics
            const width = window.innerWidth;
            const height = window.innerHeight;
            const aspectRatio = this.naturalWidth / this.naturalHeight;
            const devicePixelRatio = window.devicePixelRatio || 1;
            
            // Optimize image positioning
            if (width >= 1200) {
                // Large desktop
                this.style.objectPosition = `center ${aspectRatio < 1 ? '20%' : '30%'}`;
            } else if (width >= 992) {
                // Normal desktop
                this.style.objectPosition = `center ${aspectRatio < 1 ? '25%' : '30%'}`;
            } else if (width >= 768) {
                // Tablet
                this.style.objectPosition = `center ${aspectRatio < 1 ? '20%' : '25%'}`;
            } else if (width >= 428) {
                // Large phones
                this.style.objectPosition = `center ${aspectRatio < 1 ? '15%' : '20%'}`;
            } else {
                // Small phones
                this.style.objectPosition = `center ${aspectRatio < 1 ? '15%' : '20%'}`;
            }

            // High-DPI device optimizations
            if (devicePixelRatio >= 2) {
                this.style.imageRendering = 'crisp-edges';
            }

            // Add smooth transitions
            this.style.transition = 'transform 0.5s ease, object-position 0.3s ease';
        };
        
        img.onerror = function() {
            this.src = 'assets/images/default-food.jpg';
            this.parentElement.classList.remove('loading');
        };
    });

    // Handle orientation changes
    window.addEventListener('orientationchange', function() {
        setTimeout(() => {
            document.querySelectorAll('.menu-item-image img').forEach(img => {
                const width = window.innerWidth;
                const aspectRatio = img.naturalWidth / img.naturalHeight;
                
                // Readjust image positioning after orientation change
                if (width >= 768) {
                    img.style.objectPosition = `center ${aspectRatio < 1 ? '25%' : '30%'}`;
                } else {
                    img.style.objectPosition = `center ${aspectRatio < 1 ? '15%' : '20%'}`;
                }
            });
        }, 100);
    });

    // Add scroll event listener
    window.addEventListener('scroll', handleScroll);

    // Add smooth scroll for category links (duplicate functionality for scroll script)
    document.querySelectorAll('.category-nav .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            handleCategoryScroll(this);
        });
    });
});
