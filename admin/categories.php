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
$extra_css = <<<'CSS'
<style>
:root {
    --primary: #4F46E5;
    --primary-dark: #3730A3;
    --primary-light: #818CF8;
    --success: #059669;
    --warning: #F59E0B;
    --danger: #DC2626;
    --surface: #FFFFFF;
    --gray-50: #F8FAFC;
    --gray-100: #F1F5F9;
    --gray-200: #E2E8F0;
    --gray-600: #475569;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 2rem;
    padding: 2rem;
}

.category-card {
    position: relative;
    background: var(--surface);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s ease;
    border: 1px solid var(--gray-200);
}

.category-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.category-icon {
    position: absolute;
    top: -30px;
    left: -30px;
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: rotate(-15deg);
    transition: all 0.4s ease;
}

.category-card:hover .category-icon {
    transform: rotate(0deg);
}

.category-icon i {
    font-size: 2.5rem;
    color: white;
    transform: rotate(15deg);
    transition: all 0.4s ease;
}

.category-card:hover .category-icon i {
    transform: rotate(0deg) scale(1.2);
}

.category-header {
    position: relative;
    padding: 2rem 2rem 2rem 5rem;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    color: white;
    min-height: 120px;
    display: flex;
    align-items: center;
}

.category-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.category-status {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.status-active {
    background: rgba(5, 150, 105, 0.2);
    color: #10B981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-inactive {
    background: rgba(220, 38, 38, 0.2);
    color: #EF4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.category-body {
    padding: 2rem;
}

.category-description {
    color: var(--gray-600);
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 2rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.category-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-box {
    position: relative;
    padding: 1.5rem;
    border-radius: 15px;
    background: var(--gray-50);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.stat-box:hover {
    background: white;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    transform: translateY(-4px);
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon i {
    font-size: 1.25rem;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    font-weight: 500;
}

.category-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    padding: 1.5rem;
    background: var(--gray-50);
    border-top: 1px solid var(--gray-100);
}

.action-btn {
    position: relative;
    padding: 0.875rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.action-btn i {
    font-size: 1.1rem;
    transition: transform 0.3s ease;
}

.btn-edit {
    background: var(--primary);
    color: white;
}

.btn-edit:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
}

.btn-toggle {
    background: var(--warning);
    color: white;
}

.btn-toggle:hover {
    background: #D97706;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(245, 158, 11, 0.2);
}

.btn-delete {
    background: var(--danger);
    color: white;
}

.btn-delete:hover {
    background: #B91C1C;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(220, 38, 38, 0.2);
}

@media (max-width: 768px) {
    .category-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
        gap: 1.5rem;
    }

    .category-icon {
        width: 100px;
        height: 100px;
        top: -25px;
        left: -25px;
    }

    .category-icon i {
        font-size: 2rem;
    }

    .category-header {
        padding: 1.5rem 1.5rem 1.5rem 4rem;
        min-height: 100px;
    }
}
</style>
CSS;

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
    <div class="category-grid">
        <?php foreach ($categories as $category): ?>
        <div class="category-card category-item" data-status="<?php echo $category['status']; ?>">
            <div class="category-header">
                <h3 class="category-name">
                    <?php echo htmlspecialchars($category['name']); ?>
                </h3>
                <span class="category-status">
                    <i class="fas fa-<?php echo $category['status'] === 'active' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                    <?php echo ucfirst($category['status']); ?>
                </span>
            </div>
            
            <div class="category-body">
                <p class="category-description">
                    <?php echo htmlspecialchars($category['description'] ?: 'No description available'); ?>
                </p>
                
                <div class="category-stats">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $category['item_count']; ?></div>
                        <div class="stat-label">Menu Items</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">
                            <i class="fas fa-<?php echo $category['status'] === 'active' ? 'check' : 'times'; ?>"></i>
                        </div>
                        <div class="stat-label">Status</div>
                    </div>
                </div>
            </div>
            
            <div class="category-actions">
                <button type="button" class="action-btn btn-edit" 
                        data-bs-toggle="modal" 
                        data-bs-target="#editCategoryModal" 
                        data-category='<?php echo json_encode($category); ?>'>
                    <i class="fas fa-edit"></i>
                    Edit
                </button>
                
                <form method="POST" class="d-inline flex-grow-1">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                    <input type="hidden" name="status" 
                           value="<?php echo $category['status'] === 'active' ? 'inactive' : 'active'; ?>">
                    <button type="submit" class="action-btn btn-toggle w-100">
                        <i class="fas fa-<?php echo $category['status'] === 'active' ? 'times' : 'check'; ?>"></i>
                        <?php echo $category['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                    </button>
                </form>
                
                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                    <button type="submit" class="action-btn btn-delete">
                        <i class="fas fa-trash"></i>
                        Delete
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open empty-state-icon"></i>
            <h4>No Categories Found</h4>
            <p class="empty-state-text">Start by adding your first category</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add Category
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
    const searchInput = document.getElementById("categorySearch");
    const categoryItems = document.querySelectorAll(".category-item");
    let searchTimeout;

    // Enhanced search function
    function searchCategories(searchTerm) {
        searchTerm = searchTerm.toLowerCase().trim();
        
        categoryItems.forEach(item => {
            const categoryName = item.querySelector(".category-name").textContent.toLowerCase();
            const categoryDesc = item.querySelector(".category-description").textContent.toLowerCase();
            const itemCount = item.querySelector(".stat-value").textContent;
            const status = item.querySelector(".category-status").textContent.toLowerCase();
            
            // Search in multiple fields
            const matchName = categoryName.includes(searchTerm);
            const matchDesc = categoryDesc.includes(searchTerm);
            const matchCount = itemCount.includes(searchTerm);
            const matchStatus = status.includes(searchTerm);

            // Show/hide with animation
            if (matchName || matchDesc || matchCount || matchStatus) {
                item.style.display = "block";
                item.style.opacity = "1";
                item.style.transform = "translateY(0)";
            } else {
                item.style.opacity = "0";
                item.style.transform = "translateY(20px)";
                setTimeout(() => {
                    if (item.style.opacity === "0") {
                        item.style.display = "none";
                    }
                }, 300);
            }
        });

        // Show/hide empty state
        const visibleItems = document.querySelectorAll(".category-item[style*=\'display: block\']");
        const emptyState = document.querySelector(".empty-state") || createEmptyState();
        
        if (visibleItems.length === 0) {
            emptyState.style.display = "block";
            emptyState.style.opacity = "1";
        } else {
            emptyState.style.opacity = "0";
            setTimeout(() => {
                emptyState.style.display = "none";
            }, 300);
        }
    }

    // Create empty state for search results
    function createEmptyState() {
        const emptyState = document.createElement("div");
        emptyState.className = "empty-state";
        emptyState.innerHTML = `
            <i class="fas fa-search empty-state-icon"></i>
            <h4>No Categories Found</h4>
            <p class="empty-state-text">Try adjusting your search term</p>
            <button class="btn btn-outline-primary" onclick="clearSearch()">
                <i class="fas fa-times me-2"></i>Clear Search
            </button>
        `;
        document.querySelector(".category-grid").appendChild(emptyState);
        return emptyState;
    }

    // Clear search function
    window.clearSearch = function() {
        searchInput.value = "";
        searchCategories("");
        searchInput.focus();
    }

    // Add search input event listener with debounce
    searchInput.addEventListener("input", function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchCategories(this.value);
        }, 300);
    });

    // Add search box enhancements
    searchInput.addEventListener("focus", function() {
        this.parentElement.style.boxShadow = "0 0 0 3px rgba(79, 70, 229, 0.1)";
    });

    searchInput.addEventListener("blur", function() {
        this.parentElement.style.boxShadow = "none";
    });

    // Add clear button to search box
    const searchBox = searchInput.parentElement;
    const clearButton = document.createElement("button");
    clearButton.className = "search-clear-btn";
    clearButton.innerHTML = \'<i class="fas fa-times"></i>\';
    clearButton.style.display = "none";
    searchBox.appendChild(clearButton);

    // Update search box styles
    searchBox.style.position = "relative";
    
    const clearButtonStyles = `
        .search-clear-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .search-clear-btn:hover {
            background: var(--gray-100);
            color: var(--danger);
        }
    `;
    
    // Add styles to document
    const styleSheet = document.createElement("style");
    styleSheet.textContent = clearButtonStyles;
    document.head.appendChild(styleSheet);

    // Handle clear button visibility
    searchInput.addEventListener("input", function() {
        clearButton.style.display = this.value ? "flex" : "none";
    });

    clearButton.addEventListener("click", function() {
        clearSearch();
        this.style.display = "none";
    });

    // Add keyboard shortcuts
    document.addEventListener("keydown", function(e) {
        // Press "/" to focus search
        if (e.key === "/" && !searchInput.matches(":focus")) {
            e.preventDefault();
            searchInput.focus();
        }
        
        // Press "Esc" to clear search
        if (e.key === "Escape" && searchInput.matches(":focus")) {
            clearSearch();
            searchInput.blur();
        }
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
                    item.style.display = "block";
                    item.style.opacity = "1";
                    item.style.transform = "translateY(0)";
                } else {
                    item.style.opacity = "0";
                    item.style.transform = "translateY(20px)";
                    setTimeout(() => {
                        if (item.style.opacity === "0") {
                            item.style.display = "none";
                        }
                    }, 300);
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