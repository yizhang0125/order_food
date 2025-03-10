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

$page_title = 'Add Menu Item';
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
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
            // Clear form data after successful submission
            $_POST = array();
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all active categories for the dropdown
try {
    $categories = $categoryModel->getActiveCategories();
} catch (Exception $e) {
    $message = "Error loading categories: " . $e->getMessage();
    $message_type = "danger";
    $categories = [];
}

// Custom CSS
$extra_css = '
<style>
.form-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}

.image-upload-card {
    border: 2px dashed #dee2e6;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.3s ease;
}

.image-upload-card:hover {
    border-color: var(--primary-color);
    background: rgba(67, 97, 238, 0.05);
}

.preview-image {
    max-width: 100%;
    max-height: 300px;
    border-radius: 10px;
    margin-top: 1rem;
}

.upload-icon {
    font-size: 3rem;
    color: #adb5bd;
    margin-bottom: 1rem;
}

.upload-text {
    color: #6c757d;
    margin-bottom: 0.5rem;
}

.upload-hint {
    font-size: 0.875rem;
    color: #adb5bd;
}

.form-group-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.price-input {
    font-size: 1.5rem;
    font-weight: 600;
}

.price-input:focus {
    box-shadow: none;
    border-color: var(--primary-color);
}

.price-symbol {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.form-control-lg {
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

.form-select-lg {
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

.input-group-lg > .form-control {
    padding: 0.75rem 1rem;
}

.input-group-lg > .input-group-text {
    padding: 0.75rem 1.25rem;
}

.btn-outline-primary {
    border-width: 2px;
}

.btn-outline-primary:hover {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.image-container {
    position: relative;
    display: inline-block;
}

.remove-image {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    padding: 0.5rem;
    cursor: pointer;
}

.remove-image:hover {
    background: #fff;
    color: #dc3545;
}
</style>';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Add Menu Item</h1>
            <p class="text-muted">Create a new menu item</p>
        </div>
        <a href="menu_management.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Menu
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card form-card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="menuItemForm">
                        <!-- Basic Information -->
                        <div class="mb-4">
                            <div class="form-group-title">Menu Item Details</div>
                            
                            <div class="mb-4">
                                <label class="form-label">Item Name</label>
                                <input type="text" class="form-control form-control-lg" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                       placeholder="Enter item name" required>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select class="form-select form-select-lg" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Price</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text price-symbol">$</span>
                                        <input type="number" class="form-control price-input" name="price" 
                                               step="0.01" min="0" required 
                                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                               placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" required
                                          placeholder="Describe your menu item"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-light btn-lg me-2" 
                                    onclick="window.location.href='menu_management.php'">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus me-2"></i>Add Menu Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Image Upload Section -->
        <div class="col-md-4">
            <div class="card form-card">
                <div class="card-body">
                    <div class="form-group-title mb-4">Item Image</div>
                    
                    <label class="image-upload-card w-100" for="imageInput">
                        <div id="uploadSection">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h5 class="upload-text">Drop image here or click to browse</h5>
                            <p class="upload-hint">Supports: JPG, PNG, JPEG</p>
                            <p class="upload-hint">Max size: 2MB</p>
                        </div>
                        <div class="image-container" id="newImageContainer" style="display: none;">
                            <img id="imagePreview" class="preview-image">
                            <div class="remove-image" id="removeImage">
                                <i class="fas fa-times"></i>
                            </div>
                        </div>
                        <input type="file" name="image" id="imageInput" accept="image/*" 
                               style="display: none;" form="menuItemForm">
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for image preview and validation
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const imageInput = document.getElementById("imageInput");
    const imagePreview = document.getElementById("imagePreview");
    const uploadSection = document.getElementById("uploadSection");
    const removeImage = document.getElementById("removeImage");
    const newImageContainer = document.getElementById("newImageContainer");
    
    imageInput.addEventListener("change", function() {
        const file = this.files[0];
        if (file) {
            // Check file size
            if (file.size > 2 * 1024 * 1024) {
                alert("File size must be less than 2MB");
                this.value = "";
                return;
            }
            
            // Check file type
            const validTypes = ["image/jpeg", "image/png", "image/jpg"];
            if (!validTypes.includes(file.type)) {
                alert("Please upload a valid image file (JPG, PNG)");
                this.value = "";
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                uploadSection.style.display = "none";
                newImageContainer.style.display = "block";
            }
            reader.readAsDataURL(file);
        }
    });
    
    removeImage.addEventListener("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        imageInput.value = "";
        newImageContainer.style.display = "none";
        uploadSection.style.display = "block";
    });
    
    // Drag and drop functionality
    const uploadCard = document.querySelector(".image-upload-card");
    
    ["dragenter", "dragover", "dragleave", "drop"].forEach(eventName => {
        uploadCard.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ["dragenter", "dragover"].forEach(eventName => {
        uploadCard.addEventListener(eventName, highlight, false);
    });
    
    ["dragleave", "drop"].forEach(eventName => {
        uploadCard.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        uploadCard.classList.add("border-primary");
    }
    
    function unhighlight(e) {
        uploadCard.classList.remove("border-primary");
    }
    
    uploadCard.addEventListener("drop", handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const file = dt.files[0];
        imageInput.files = dt.files;
        imageInput.dispatchEvent(new Event("change"));
    }
    
    // Form validation
    document.getElementById("menuItemForm").addEventListener("submit", function(event) {
        const price = document.querySelector("[name=price]").value;
        if (parseFloat(price) <= 0) {
            event.preventDefault();
            alert("Price must be greater than 0");
        }
    });
});
</script>';

// Include the layout template
include 'includes/layout.php';
?> 