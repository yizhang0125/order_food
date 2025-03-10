<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Category.php');
require_once(__DIR__ . '/../classes/MenuItem.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$categoryModel = new Category($db);
$menuItemModel = new MenuItem($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Category Management';

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
                        'status' => 'active'
                    ];
                    
                    if ($categoryModel->create($data)) {
                        $message = "Category added successfully!";
                        $message_type = "success";
                    }
                    break;

                case 'update':
                    $data = [
                        'name' => $_POST['name'],
                        'description' => $_POST['description']
                    ];
                    
                    if ($categoryModel->update($_POST['category_id'], $data)) {
                        $message = "Category updated successfully!";
                        $message_type = "success";
                    }
                    break;

                case 'update_status':
                    if ($categoryModel->updateStatus($_POST['category_id'], $_POST['status'])) {
                        $message = "Status updated successfully!";
                        $message_type = "success";
                    }
                    break;

                case 'delete':
                    // Check if category has menu items
                    $category_items = $menuItemModel->getItemsByCategory($_POST['category_id']);
                    if (!empty($category_items)) {
                        $message = "Cannot delete category: It contains menu items!";
                        $message_type = "danger";
                    } else {
                        if ($categoryModel->delete($_POST['category_id'])) {
                            $message = "Category deleted successfully!";
                            $message_type = "success";
                        }
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

// Get all categories with item counts
$categories = $categoryModel->getCategoryWithItemCount();

// Custom CSS
$extra_css = '
<style>
.category-card {
    transition: all 0.3s ease;
    border: none;
    border-radius: 15px;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.category-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: rgba(67, 97, 238, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

.category-icon i {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-active {
    background: rgba(46, 204, 113, 0.1);
    color: #2ecc71;
}

.status-inactive {
    background: rgba(255, 71, 87, 0.1);
    color: #ff4757;
}

.item-count {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.search-box {
    max-width: 300px;
}

.search-box input {
    border-radius: 10px;
    padding-left: 40px;
}

.search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
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
            <h1 class="h3 mb-0">Category Management</h1>
            <p class="text-muted">Manage your menu categories</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-2"></i>Add New Category
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="position-relative search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="categorySearch" placeholder="Search categories...">
                    </div>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-primary" data-filter="active">Active</button>
                        <button type="button" class="btn btn-outline-primary" data-filter="inactive">Inactive</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="row g-4">
        <?php foreach ($categories as $category): ?>
        <div class="col-12 col-md-6 col-xl-4 category-item" data-status="<?php echo $category['status']; ?>">
            <div class="card category-card">
                <div class="card-body">
                    <span class="item-count">
                        <?php echo $category['item_count']; ?> items
                    </span>
                    <div class="category-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($category['name']); ?></h5>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($category['description']); ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="status-badge status-<?php echo $category['status']; ?>">
                            <?php echo ucfirst($category['status']); ?>
                        </span>
                        <div class="btn-group">
                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" 
                                    data-bs-target="#editCategoryModal" 
                                    data-category='<?php echo json_encode($category); ?>'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                <input type="hidden" name="status" 
                                       value="<?php echo $category['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit" class="btn btn-light btn-sm">
                                    <i class="fas fa-<?php echo $category['status'] === 'active' ? 'times' : 'check'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                <button type="submit" class="btn btn-light btn-sm text-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
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
            <form method="POST">
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
                    <button type="submit" class="btn btn-primary">Add Category</button>
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
            <form method="POST">
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

// Add JavaScript for search, filter and edit modal
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Search functionality
    const searchInput = document.getElementById("categorySearch");
    const categoryItems = document.querySelectorAll(".category-item");
    
    searchInput.addEventListener("input", function() {
        const searchTerm = this.value.toLowerCase();
        
        categoryItems.forEach(item => {
            const categoryName = item.querySelector(".card-title").textContent.toLowerCase();
            const categoryDesc = item.querySelector(".text-muted").textContent.toLowerCase();
            
            if (categoryName.includes(searchTerm) || categoryDesc.includes(searchTerm)) {
                item.style.display = "";
            } else {
                item.style.display = "none";
            }
        });
    });

    // Filter functionality
    const filterButtons = document.querySelectorAll("[data-filter]");
    
    filterButtons.forEach(button => {
        button.addEventListener("click", function() {
            const filter = this.dataset.filter;
            
            // Update active state
            filterButtons.forEach(btn => btn.classList.remove("active"));
            this.classList.add("active");
            
            // Filter items
            categoryItems.forEach(item => {
                if (filter === "all" || item.dataset.status === filter) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        });
    });

    // Edit modal
    const editModal = document.getElementById("editCategoryModal");
    if (editModal) {
        editModal.addEventListener("show.bs.modal", function(event) {
            const button = event.relatedTarget;
            const category = JSON.parse(button.dataset.category);
            
            // Populate form fields
            document.getElementById("edit_category_id").value = category.id;
            document.getElementById("edit_name").value = category.name;
            document.getElementById("edit_description").value = category.description;
        });
    }
});
</script>
';

// Include the layout template
include 'includes/layout.php';
?> 