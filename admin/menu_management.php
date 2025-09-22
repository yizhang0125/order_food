<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/MenuItem.php');
require_once(__DIR__ . '/../classes/Category.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$menuItemModel = new MenuItem($db);
$categoryModel = new Category($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Menu Management';

// Handle form submissions
$message = '';
$message_type = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    
    // Clear the message after displaying
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    $data = [
                        'name' => $_POST['name'],
                        'description' => $_POST['description'],
                        'price' => $_POST['price'],
                        'category_id' => $_POST['category_id'],
                        'status' => 'available'
                    ];

                    // Handle image upload if present
                    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                        $image_path = $menuItemModel->uploadImage($_FILES['image']);
                        if ($image_path) {
                            $data['image_path'] = $image_path;
                        }
                    }
                    
                    if ($menuItemModel->create($data)) {
                        $message = "Menu item added successfully!";
                        $message_type = "success";
                    }
                    break;

                case 'update':
                    $data = [
                        'name' => $_POST['name'],
                        'description' => $_POST['description'],
                        'price' => $_POST['price'],
                        'category_id' => $_POST['category_id']
                    ];

                    // Handle image upload if present
                    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                        $image_path = $menuItemModel->uploadImage($_FILES['image']);
                        if ($image_path) {
                            // Delete old image
                            $old_item = $menuItemModel->getById($_POST['item_id']);
                            if ($old_item && $old_item['image_path']) {
                                $menuItemModel->deleteImage($old_item['image_path']);
                            }
                            $data['image_path'] = $image_path;
                        }
                    }
                    
                    if ($menuItemModel->update($_POST['item_id'], $data)) {
                        $message = "Menu item updated successfully!";
                        $message_type = "success";
                    }
                    break;

                case 'update_status':
                    if ($menuItemModel->updateStatus($_POST['item_id'], $_POST['status'])) {
                        $message = "Status updated successfully!";
                        $message_type = "success";
                    }
                    break;

                case 'delete':
                    // Get item details to delete image if exists
                    $item = $menuItemModel->getById($_POST['item_id']);
                    if ($item && $menuItemModel->delete($_POST['item_id'])) {
                        if ($item['image_path']) {
                            $menuItemModel->deleteImage($item['image_path']);
                        }
                        $message = "Item deleted successfully!";
                        $message_type = "success";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get all active categories
$categories = $categoryModel->getActiveCategories();

// Get all menu items with category names
$menu_items = $menuItemModel->getAllWithCategories();

// Include external CSS file
$extra_css = '<link rel="stylesheet" href="css/menu_management.css">';

// Start output buffering
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
        <div>
                <h1 class="page-title">
                    <i class="fas fa-utensils"></i>
                    Menu Management
                </h1>
                <p class="page-subtitle">Manage your restaurant's menu items</p>
            </div>
            <div class="header-actions">
                <a href="add_menu_item.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>Add New Item
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Category Filters -->
    <div class="category-filters-container">
        <div class="category-filters">
            <div class="category-filter active" data-category="all">
                <i class="fas fa-th-large"></i>
                All Items
                <span class="filter-count"><?php echo count($menu_items); ?></span>
            </div>
            <?php
            $category_counts = array();
            $category_icons = [
                'Main Course' => 'utensils',
                'Appetizer' => 'concierge-bell',
                'Dessert' => 'ice-cream',
                'Beverage' => 'cocktail',
                'Soup' => 'hotdog',
                'Salad' => 'seedling',
                'Pizza' => 'pizza-slice',
                'Pasta' => 'bread-slice',
                'Seafood' => 'fish',
                'Rice' => 'bowl-rice',
                'Noodles' => 'ramen',
                'Sandwich' => 'burger',
                'Grill' => 'fire',
                'Snacks' => 'cookie',
                'Coffee' => 'mug-hot',
                'Tea' => 'mug-saucer',
                'Juice' => 'glass-water',
                'Smoothie' => 'blender',
                'Breakfast' => 'egg',
                'Lunch' => 'plate-wheat',
                'Dinner' => 'utensils',
                'Special' => 'star',
                'Combo' => 'layer-group',
                'Sides' => 'french-fries',
                'Chicken' => 'drumstick-bite',
                'Meat' => 'meat',
                'Vegetarian' => 'leaf',
                'Spicy' => 'pepper-hot',
                'Healthy' => 'carrot',
                'Kids Menu' => 'ice-cream',
                'default' => 'utensils-alt'
            ];

            foreach ($menu_items as $item) {
                if (!isset($category_counts[$item['category_name']])) {
                    $category_counts[$item['category_name']] = 0;
                }
                $category_counts[$item['category_name']]++;
            }
            
            foreach ($category_counts as $category => $count):
                $icon = $category_icons[htmlspecialchars($category)] ?? $category_icons['default'];
            ?>
            <div class="category-filter" data-category="<?php echo htmlspecialchars($category); ?>">
                <i class="fas fa-<?php echo $icon; ?>"></i>
                <?php echo htmlspecialchars($category); ?>
                <span class="filter-count"><?php echo $count; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Menu Items Grid -->
    <div class="menu-grid">
        <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
            <div class="menu-card" 
                 data-category="<?php echo htmlspecialchars($item['category_name']); ?>"
                 data-id="<?php echo $item['id']; ?>">
                <div class="menu-image-container">
                    <?php if (!empty($item['image_path']) && file_exists('../' . $item['image_path'])): ?>
                        <img src="<?php echo '../' . htmlspecialchars($item['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             class="menu-image">
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <i class="fas fa-utensils"></i>
                            <span>No Image Available</span>
                        </div>
                    <?php endif; ?>
                    <div class="menu-status-badge status-<?php echo $item['status']; ?>">
                        <i class="fas fa-<?php echo $item['status'] === 'available' ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo ucfirst($item['status']); ?>
                    </div>
                    <div class="menu-category-badge">
                        <?php echo htmlspecialchars($item['category_name']); ?>
                    </div>
                </div>

                <div class="menu-content">
                    <h3 class="menu-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p class="menu-description"><?php echo htmlspecialchars($item['description']); ?></p>
                        <div class="menu-price">
                            <span class="price-currency">RM</span>
                            <?php echo number_format($item['price'], 2); ?>
                    </div>
                </div>

                <div class="menu-actions">
                    <button type="button" class="action-btn btn-edit" 
                            onclick="window.location.href='edit_menu_item.php?id=<?php echo $item['id']; ?>'"
                            title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" 
                            class="action-btn btn-toggle"
                            onclick="toggleStatus(<?php echo $item['id']; ?>)"
                            title="<?php echo $item['status'] === 'available' ? 'Mark as Unavailable' : 'Mark as Available'; ?>">
                        <i class="fas fa-<?php echo $item['status'] === 'available' ? 'times' : 'check'; ?>"></i>
                    </button>
                    <button type="button" class="action-btn btn-delete"
                            onclick="deleteItem(<?php echo $item['id']; ?>)"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-utensils empty-icon"></i>
                <h4>No Menu Items Found</h4>
                <p class="empty-text">Start by adding your first menu item</p>
                <a href="add_menu_item.php" class="add-item-btn">
                    <i class="fas fa-plus"></i>
                    Add Menu Item
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for category filtering
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const categoryFilters = document.querySelectorAll(".category-filter");
    const menuCards = document.querySelectorAll(".menu-card");
    
    // Function to handle smooth scrolling
    function smoothScroll(element, target) {
        const start = element.scrollLeft;
        const distance = target - start;
        const duration = 500;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const progress = Math.min(timeElapsed / duration, 1);
            
            const easing = t => t < .5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
            element.scrollLeft = start + distance * easing(progress);

            if (timeElapsed < duration) {
                requestAnimationFrame(animation);
            }
        }

        requestAnimationFrame(animation);
    }

    // Category filtering with smooth transitions
    categoryFilters.forEach(filter => {
        filter.addEventListener("click", function() {
            const category = this.dataset.category;
            
            // Update active state
            categoryFilters.forEach(f => f.classList.remove("active"));
            this.classList.add("active");

            // Smooth scroll to center the active filter
            const container = document.querySelector(".category-filters");
            const scrollTarget = this.offsetLeft - (container.offsetWidth / 2) + (this.offsetWidth / 2);
            smoothScroll(container, scrollTarget);
            
            // Filter items with animation
            menuCards.forEach(card => {
                if (category === "all" || card.dataset.category === category) {
                    card.classList.remove("hiding");
                    card.style.display = "";
                    setTimeout(() => {
                        card.classList.add("showing");
                    }, 50);
                } else {
                    card.classList.add("hiding");
                    card.classList.remove("showing");
                    setTimeout(() => {
                        card.style.display = "none";
                    }, 300);
                }
            });
        });
    });

    // Horizontal scroll handling
    const categoryFiltersContainer = document.querySelector(".category-filters");
    let isScrolling = false;
    let startX;
    let scrollLeft;

    categoryFiltersContainer.addEventListener("mousedown", (e) => {
        isScrolling = true;
        startX = e.pageX - categoryFiltersContainer.offsetLeft;
        scrollLeft = categoryFiltersContainer.scrollLeft;
        categoryFiltersContainer.style.cursor = "grabbing";
    });

    categoryFiltersContainer.addEventListener("mousemove", (e) => {
        if (!isScrolling) return;
        e.preventDefault();
        const x = e.pageX - categoryFiltersContainer.offsetLeft;
        const walk = (x - startX) * 2;
        categoryFiltersContainer.scrollLeft = scrollLeft - walk;
    });

    categoryFiltersContainer.addEventListener("mouseup", () => {
        isScrolling = false;
        categoryFiltersContainer.style.cursor = "grab";
    });

    categoryFiltersContainer.addEventListener("mouseleave", () => {
        isScrolling = false;
        categoryFiltersContainer.style.cursor = "grab";
    });

    // Smooth wheel scrolling
    categoryFiltersContainer.addEventListener("wheel", (e) => {
        e.preventDefault();
        const delta = Math.max(-1, Math.min(1, e.deltaY || -e.detail));
        smoothScroll(categoryFiltersContainer, categoryFiltersContainer.scrollLeft + (delta * 100));
    }, { passive: false });
});
</script>
';

// Add JavaScript for price validation
$extra_js .= '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const priceInputs = document.querySelectorAll("input[type=number][name=price]");
    
    priceInputs.forEach(input => {
        input.addEventListener("input", function() {
            // Format to 2 decimal places
            if (this.value !== "") {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
        
        input.addEventListener("blur", function() {
            // Ensure minimum value
            if (this.value < 0) {
                this.value = "0.00";
            }
        });
    });
});
</script>
';

// Add this to your existing $extra_js
$extra_js .= '
<script>
// Function to toggle item status
function toggleStatus(itemId) {
    if (!confirm("Are you sure you want to change this item\'s availability?")) {
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";
    form.style.display = "none";

    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "action";
    actionInput.value = "update_status";

    const itemIdInput = document.createElement("input");
    itemIdInput.type = "hidden";
    itemIdInput.name = "item_id";
    itemIdInput.value = itemId;

    // Find current status from the menu card
    const menuCard = document.querySelector(`.menu-card[data-id="${itemId}"]`);
    const currentStatus = menuCard.querySelector(".menu-status-badge").classList.contains("status-available");
    
    const statusInput = document.createElement("input");
    statusInput.type = "hidden";
    statusInput.name = "status";
    statusInput.value = currentStatus ? "unavailable" : "available";

    form.appendChild(actionInput);
    form.appendChild(itemIdInput);
    form.appendChild(statusInput);
    document.body.appendChild(form);
    form.submit();
}

// Function to delete item
function deleteItem(itemId) {
    if (!confirm("Are you sure you want to delete this item? This action cannot be undone.")) {
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";
    form.style.display = "none";

    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "action";
    actionInput.value = "delete";

    const itemIdInput = document.createElement("input");
    itemIdInput.type = "hidden";
    itemIdInput.name = "item_id";
    itemIdInput.value = itemId;

    form.appendChild(actionInput);
    form.appendChild(itemIdInput);
    document.body.appendChild(form);
    form.submit();
}
</script>
';

// Include the layout template
include 'includes/layout.php';
?> 
