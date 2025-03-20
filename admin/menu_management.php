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

// Custom CSS
$extra_css = '
<style>
:root {
    --primary: #4F46E5;
    --primary-light: #818CF8;
    --success: #10B981;
    --warning: #F59E0B;
    --danger: #EF4444;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    padding: 2rem;
}

.menu-card {
    position: relative;
    background: var(--surface);
    border-radius: 24px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    height: 420px;
    opacity: 1;
    transform: translateY(0);
}

.menu-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
}

.menu-image-container {
    position: relative;
    height: 220px;
    overflow: hidden;
}

.menu-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.menu-card:hover .menu-image {
    transform: scale(1.1);
}

.menu-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 220px;
    background: linear-gradient(
        180deg,
        rgba(0, 0, 0, 0) 0%,
        rgba(0, 0, 0, 0.2) 100%
    );
    pointer-events: none;
}

.menu-status-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.6rem 1.2rem;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 2;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.status-available {
    background: rgba(16, 185, 129, 0.9);
    color: white;
}

.status-unavailable {
    background: rgba(239, 68, 68, 0.9);
    color: white;
}

.menu-category-badge {
    position: absolute;
    left: 1rem;
    top: 180px;
    background: var(--primary);
    color: white;
    padding: 0.8rem 1.5rem;
    border-radius: 30px;
    font-size: 1rem;
    font-weight: 600;
    z-index: 2;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transform: translateY(-50%);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 2px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.menu-category-badge:hover {
    transform: translateY(-50%) scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
}

.menu-content {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: relative;
}

.menu-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.menu-description {
    color: var(--gray-600);
    font-size: 0.95rem;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin: 0;
}

.menu-price-section {
    margin-top: auto;
    padding: 1rem 0;
}

.menu-price {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.price-currency {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-500);
}

.menu-actions {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 1.5rem 2rem;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 1) 25%
    );
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.menu-card:hover .menu-actions {
    transform: translateY(0);
}

.action-btn {
    border: none;
    padding: 0.75rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
    color: white;
}

.action-btn i {
    font-size: 1rem;
}

.btn-edit {
    background: var(--primary);
}

.btn-edit:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-toggle {
    background: var(--warning);
}

.btn-toggle:hover {
    background: #D97706;
    transform: translateY(-2px);
}

.btn-delete {
    background: var(--danger);
}

.btn-delete:hover {
    background: #DC2626;
    transform: translateY(-2px);
}

.menu-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.badge-new {
    background: var(--primary);
    color: white;
}

.badge-popular {
    background: var(--warning);
    color: white;
}

.category-filters-container {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin: 0 2rem 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.category-filters {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    scrollbar-width: thin;
    scrollbar-color: var(--primary) #f1f5f9;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}

.category-filters::-webkit-scrollbar {
    height: 6px;
}

.category-filters::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.category-filters::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

.category-filter {
    padding: 0.75rem 1.5rem;
    background: var(--gray-50);
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    user-select: none;
}

.category-filter:hover {
    border-color: var(--primary-light);
    color: var(--primary);
    background: rgba(79, 70, 229, 0.1);
    transform: translateY(-2px);
}

.category-filter.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
}

.category-filter i {
    font-size: 1rem;
    transition: transform 0.3s ease;
}

.category-filter:hover i {
    transform: scale(1.1);
}

.category-filter.active .filter-count {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.filter-count {
    background: var(--gray-200);
    color: var(--gray-600);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.category-filter:hover .filter-count {
    background: rgba(79, 70, 229, 0.2);
    color: var(--primary);
}

.menu-card.hiding {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
}

.menu-card.showing {
    opacity: 1;
    transform: scale(1) translateY(0);
}

@media (max-width: 768px) {
    .category-filters-container {
        margin: 0 1rem 1.5rem;
        padding: 1rem;
    }

    .category-filter {
        padding: 0.6rem 1.2rem;
        font-size: 0.875rem;
    }
}

/* Add these responsive styles to your existing CSS */
<style>
/* Large Desktop (1400px and up) */
@media (min-width: 1400px) {
    .menu-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem;
        padding: 2rem;
    }

    .menu-card {
        height: 450px;
    }

    .menu-image-container {
        height: 250px;
    }
}

/* Desktop (1200px to 1399px) */
@media (min-width: 1200px) and (max-width: 1399px) {
    .menu-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        padding: 2rem;
    }

    .menu-card {
        height: 420px;
    }

    .menu-image-container {
        height: 220px;
    }
}

/* Tablet Landscape (992px to 1199px) */
@media (min-width: 992px) and (max-width: 1199px) {
    .menu-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .menu-card {
        height: 400px;
    }

    .menu-image-container {
        height: 200px;
    }
}

/* Tablet Portrait (768px to 991px) */
@media (min-width: 768px) and (max-width: 991px) {
    .menu-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .menu-card {
        height: 380px;
    }

    .menu-image-container {
        height: 180px;
    }

    .menu-title {
        font-size: 1.25rem;
    }

    .menu-price {
        font-size: 1.5rem;
    }
}

/* Large Mobile (576px to 767px) */
@media (min-width: 576px) and (max-width: 767px) {
    .menu-grid {
        grid-template-columns: repeat(1, 1fr);
        gap: 1.25rem;
        padding: 1.25rem;
    }

    .menu-card {
        height: 360px;
        max-width: 500px;
        margin: 0 auto;
    }

    .menu-image-container {
        height: 200px;
    }

    .category-filters-container {
        margin: 0 1rem 1rem;
        padding: 1rem;
    }

    .category-filter {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}

/* Mobile (428px to 575px) - iPhone Pro Max and similar */
@media (min-width: 428px) and (max-width: 575px) {
    .menu-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 1rem;
    }

    .menu-card {
        height: 380px;
    }

    .menu-image-container {
        height: 220px;
    }

    .menu-content {
        padding: 1.5rem;
    }

    .menu-title {
        font-size: 1.2rem;
    }

    .menu-description {
        font-size: 0.9rem;
    }

    .menu-actions {
        padding: 1rem 1.5rem;
    }
}

/* Small Mobile (375px to 427px) - iPhone Pro and similar */
@media (min-width: 375px) and (max-width: 427px) {
    .menu-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 1rem;
    }

    .menu-card {
        height: 360px;
    }

    .menu-image-container {
        height: 200px;
    }

    .menu-content {
        padding: 1.25rem;
    }

    .menu-actions {
        padding: 1rem;
    }

    .action-btn {
        padding: 0.6rem;
    }
}

/* Extra Small Mobile (below 375px) */
@media (max-width: 374px) {
    .menu-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 0.75rem;
    }

    .menu-card {
        height: 340px;
    }

    .menu-image-container {
        height: 180px;
    }

    .menu-content {
        padding: 1rem;
    }

    .menu-title {
        font-size: 1.1rem;
    }

    .menu-description {
        font-size: 0.85rem;
    }

    .menu-price {
        font-size: 1.25rem;
    }

    .menu-actions {
        padding: 0.75rem;
    }

    .action-btn {
        padding: 0.5rem;
        font-size: 0.8rem;
    }

}

/* Category Filter Responsive Styles */
@media (max-width: 768px) {
    .category-filters-container {
        margin: 0 0.75rem 1rem;
        padding: 0.75rem;
    }

    .category-filters {
        gap: 0.75rem;
    }

    .category-filter {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }

    .filter-count {
        padding: 0.2rem 0.5rem;
        font-size: 0.75rem;
    }
}

/* Header Responsive Styles */
@media (max-width: 576px) {
    .container-fluid {
        padding: 1rem;
    }

    .h3 {
        font-size: 1.5rem;
    }

    .text-muted {
        font-size: 0.9rem;
    }

    .btn-primary {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}

/* Status Badge Responsive */
@media (max-width: 576px) {
    .menu-status-badge {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }

    .menu-category-badge {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
        left: 0.75rem;
        top: 160px;
    }
}

/* Loading State and Animations */
.menu-card {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.menu-card.loading {
    opacity: 0.7;
}

@keyframes cardAppear {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.menu-card {
    animation: cardAppear 0.3s ease forwards;
}
</style>
';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Menu Management</h1>
            <p class="text-muted">Manage your restaurant's menu items</p>
        </div>
        <a href="add_menu_item.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Item
        </a>
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
                    <div class="menu-overlay"></div>
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
                    
                    <div class="menu-price-section">
                        <div class="menu-price">
                            <span class="price-currency">RM</span>
                            <?php echo number_format($item['price'], 2); ?>
                        </div>
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
