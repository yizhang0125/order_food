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
.menu-item-card {
    transition: all 0.3s ease;
}

.menu-item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.menu-item-image {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.menu-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.menu-item-image i {
    font-size: 2rem;
    color: var(--primary-color);
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-available {
    background: rgba(46, 204, 113, 0.1);
    color: #2ecc71;
}

.status-unavailable {
    background: rgba(255, 71, 87, 0.1);
    color: #ff4757;
}

.category-filter {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-filter:hover,
.category-filter.active {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
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
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center overflow-auto">
                <div class="category-filter active me-3" data-category="all">
                    All Items <span class="ms-2 badge bg-primary rounded-pill">
                        <?php echo count($menu_items); ?>
                    </span>
                </div>
                <?php
                $category_counts = array();
                foreach ($menu_items as $item) {
                    if (!isset($category_counts[$item['category_name']])) {
                        $category_counts[$item['category_name']] = 0;
                    }
                    $category_counts[$item['category_name']]++;
                }
                foreach ($category_counts as $category => $count):
                ?>
                <div class="category-filter me-3" data-category="<?php echo htmlspecialchars($category); ?>">
                    <?php echo htmlspecialchars($category); ?>
                    <span class="ms-2 badge bg-primary rounded-pill"><?php echo $count; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Menu Items Grid -->
    <div class="row g-4">
        <?php foreach ($menu_items as $item): ?>
        <div class="col-12 col-md-6 col-xl-4 menu-item" data-category="<?php echo htmlspecialchars($item['category_name']); ?>">
            <div class="card menu-item-card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="menu-item-image me-3">
                            <?php if (!empty($item['image_path']) && file_exists('../' . $item['image_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-utensils"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <span class="badge bg-light text-dark mb-2">
                                        <?php echo htmlspecialchars($item['category_name']); ?>
                                    </span>
                                </div>
                                <h5 class="mb-0 text-primary">$<?php echo number_format($item['price'], 2); ?></h5>
                            </div>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                                <div class="btn-group">
                                    <a href="edit_menu_item.php?id=<?php echo $item['id']; ?>" 
                                       class="btn btn-light btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="status" 
                                               value="<?php echo $item['status'] === 'available' ? 'unavailable' : 'available'; ?>">
                                        <button type="submit" class="btn btn-light btn-sm">
                                            <i class="fas fa-<?php echo $item['status'] === 'available' ? 'times' : 'check'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-light btn-sm text-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for category filtering
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Category filtering
    const categoryFilters = document.querySelectorAll(".category-filter");
    const menuItems = document.querySelectorAll(".menu-item");
    
    categoryFilters.forEach(filter => {
        filter.addEventListener("click", function() {
            const category = this.dataset.category;
            
            // Update active state
            categoryFilters.forEach(f => f.classList.remove("active"));
            this.classList.add("active");
            
            // Filter items
            menuItems.forEach(item => {
                if (category === "all" || item.dataset.category === category) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        });
    });
});
</script>
';

// Include the layout template
include 'includes/layout.php';
?> 