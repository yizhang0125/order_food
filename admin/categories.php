<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Category.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$categoryModel = new Category($db);

// Database connection is handled by the Database class

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Category Management';

// Handle form submissions
$message = '';
$message_type = '';

// Check for messages from URL parameters (after redirect)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    // Simple validation
                    if (empty($_POST['name']) || strlen(trim($_POST['name'])) < 2) {
                        $message = 'Category name must be at least 2 characters long.';
                        $message_type = 'danger';
                    } else {
                        // Check if category name already exists
                        if ($categoryModel->nameExists($_POST['name'])) {
                            $message = 'A category with this name already exists.';
                            $message_type = 'danger';
                        } else {
                            $data = [
                                'name' => trim($_POST['name']),
                                'description' => trim($_POST['description'] ?? ''),
                                'status' => 'active'
                            ];
                            
                            if ($categoryModel->create($data)) {
                                $message = 'Category added successfully!';
                                $message_type = 'success';
                                header("Location: categories.php?message=" . urlencode($message) . "&type=" . $message_type);
                                exit();
                            } else {
                                $message = 'Failed to create category.';
                                $message_type = 'danger';
                            }
                        }
                    }
                    break;

                case 'update':
                    // Simple validation
                    if (empty($_POST['name']) || strlen(trim($_POST['name'])) < 2) {
                        $message = 'Category name must be at least 2 characters long.';
                        $message_type = 'danger';
                    } else {
                        $data = [
                            'name' => trim($_POST['name']),
                            'description' => trim($_POST['description'] ?? '')
                        ];
                        
                        if ($categoryModel->update($_POST['category_id'], $data)) {
                            $message = 'Category updated successfully!';
                            $message_type = 'success';
                            header("Location: categories.php?message=" . urlencode($message) . "&type=" . $message_type);
                            exit();
                        } else {
                            $message = 'Failed to update category.';
                            $message_type = 'danger';
                        }
                    }
                    break;

                case 'update_status':
                    $categoryId = $_POST['category_id'];
                    $newStatus = $_POST['status'];
                    
                    if ($categoryModel->updateStatus($categoryId, $newStatus)) {
                        $message = "Status updated successfully!";
                        $message_type = 'success';
                        header("Location: categories.php?message=" . urlencode($message) . "&type=" . $message_type);
                        exit();
                    } else {
                        $message = "Failed to update status.";
                        $message_type = 'danger';
                    }
                    break;

                case 'delete':
                    // Check if category has menu items
                    $categoryItems = $categoryModel->getCategoryWithItemCount();
                    $hasItems = false;
                    foreach ($categoryItems as $cat) {
                        if ($cat['id'] == $_POST['category_id'] && $cat['item_count'] > 0) {
                            $hasItems = true;
                            break;
                        }
                    }
                    
                    if ($hasItems) {
                        $message = "Cannot delete category: It contains menu items!";
                        $message_type = 'danger';
                    } else {
                        if ($categoryModel->delete($_POST['category_id'])) {
                            $message = "Category deleted successfully!";
                            $message_type = 'success';
                            
                            // Redirect to prevent form resubmission
                            header("Location: categories.php?message=" . urlencode($message) . "&type=" . $message_type);
                            exit();
                        } else {
                            $message = "Failed to delete category.";
                            $message_type = 'danger';
                        }
                    }
                    break;
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get all categories with item counts
$categories = $categoryModel->getCategoryWithItemCount();

// Include external CSS file
$extra_css = '<link rel="stylesheet" href="css/categories.css">';

// Start output buffering
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-folder-open"></i>
                    Category Management
                </h1>
                <p class="page-subtitle">Organize your menu with categories</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i>Add New Category
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Status Filters -->
    <div class="status-filters-container">
        <div class="status-filters">
            <?php
            // Calculate counts
            $totalCount = count($categories);
            $activeCount = count(array_filter($categories, function($cat) { return $cat['status'] === 'active'; }));
            $inactiveCount = $totalCount - $activeCount;
            ?>
            
            <div class="status-filter active" data-status="all">
                <i class="fas fa-th-large"></i>
                All Categories
                <span class="filter-count"><?php echo $totalCount; ?></span>
            </div>
            
            <div class="status-filter" data-status="active">
                <i class="fas fa-check-circle"></i>
                Active
                <span class="filter-count"><?php echo $activeCount; ?></span>
            </div>
            
            <div class="status-filter" data-status="inactive">
                <i class="fas fa-times-circle"></i>
                Inactive
                <span class="filter-count"><?php echo $inactiveCount; ?></span>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="categorySearch" placeholder="Search categories by name or description...">
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="categories-grid">
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $category): ?>
            <div class="category-card category-item" data-status="<?php echo $category['status']; ?>" data-id="<?php echo $category['id']; ?>">
                
                <!-- Category Image/Icon Container -->
                <div class="category-image-container">
                    <div class="category-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                    
                    <!-- Status Badge -->
                    <div class="category-status-badge status-<?php echo $category['status']; ?>">
                        <i class="fas fa-<?php echo $category['status'] === 'active' ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo ucfirst($category['status']); ?>
                    </div>
                    
                    <!-- Item Count Badge -->
                    <div class="category-count-badge">
                        <i class="fas fa-utensils"></i>
                        <?php echo $category['item_count']; ?>
                    </div>
                </div>
                
                <!-- Category Content -->
                <div class="category-content">
                    <h3 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h3>
                    <p class="category-description"><?php echo htmlspecialchars($category['description'] ?: 'No description available'); ?></p>
                    
                    <div class="category-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $category['item_count']; ?></div>
                            <div class="stat-label">Menu Items</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <i class="fas fa-<?php echo $category['status'] === 'active' ? 'check' : 'times'; ?>"></i>
                            </div>
                            <div class="stat-label">Status</div>
                        </div>
                    </div>
                </div>
                
                <!-- Category Actions -->
                <div class="category-actions">
                    <button type="button" class="action-btn btn-edit" 
                            data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                            data-category='<?php echo json_encode($category); ?>' title="Edit Category">
                        <i class="fas fa-edit"></i>
                    </button>
                    
                    <form method="POST" class="d-inline flex-grow-1">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                        <input type="hidden" name="status" value="<?php echo $category['status'] === 'active' ? 'inactive' : 'active'; ?>">
                        <button type="submit" class="action-btn btn-toggle w-100" 
                                title="<?php echo $category['status'] === 'active' ? 'Mark as Inactive' : 'Mark as Active'; ?>">
                            <i class="fas fa-<?php echo $category['status'] === 'active' ? 'times' : 'check'; ?>"></i>
                        </button>
                    </form>
                    
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                        <button type="submit" class="action-btn btn-delete" title="Delete Category">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open empty-icon"></i>
                <h4>No Categories Found</h4>
                <p class="empty-text">Start by adding your first category to organize your menu</p>
                <button class="add-category-btn" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i>Add Category
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addCategoryForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addCategoryBtn">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCategoryForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// No JavaScript needed - pure HTML forms!

// Include the layout template
include 'includes/layout.php';
?> 