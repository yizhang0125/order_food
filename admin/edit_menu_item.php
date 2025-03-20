<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if item ID is provided
if (!isset($_GET['id'])) {
    header('Location: menu_management.php');
    exit();
}

$item_id = $_GET['id'];
$page_title = 'Edit Menu Item';

// Get menu item details
$query = "SELECT m.*, c.name as category_name 
          FROM menu_items m 
          LEFT JOIN categories c ON m.category_id = c.id 
          WHERE m.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $item_id);
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: menu_management.php');
    exit();
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle image upload
    $image_path = $item['image_path']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/menu_items/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Delete old image if exists
            if ($item['image_path'] && file_exists('../' . $item['image_path'])) {
                unlink('../' . $item['image_path']);
            }
            $image_path = 'uploads/menu_items/' . $file_name;
        }
    }

    try {
        // Update menu item
        $query = "UPDATE menu_items 
                  SET name = :name, 
                      description = :description, 
                      price = :price, 
                      category_id = :category_id, 
                      status = :status, 
                      image_path = :image_path 
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        
        $status = isset($_POST['status']) ? 'available' : 'unavailable';
        
        // Bind all parameters
        $params = [
            ':name' => $_POST['name'],
            ':description' => $_POST['description'],
            ':price' => $_POST['price'],
            ':category_id' => $_POST['category_id'],
            ':status' => $status,
            ':image_path' => $image_path,
            ':id' => $item_id
        ];
        
        if ($stmt->execute($params)) {
            $_SESSION['message'] = "Menu item updated successfully!";
            $_SESSION['message_type'] = "success";
            header('Location: menu_management.php');
            exit();
        } else {
            $message = "Error updating menu item.";
            $message_type = "danger";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all active categories
$categories_query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

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

.status-switch {
    width: 60px;
    height: 30px;
}

.status-switch .form-check-input {
    width: 60px;
    height: 30px;
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

.image-container {
    position: relative;
    display: inline-block;
}

.current-image-text {
    color: #6c757d;
    margin-bottom: 1rem;
    font-size: 0.9rem;
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
            <h1 class="h3 mb-0">Edit Menu Item</h1>
            <p class="text-muted">Update menu item details</p>
        </div>
        <a href="menu_management.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Menu
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Edit Item Form -->
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
                                <input type="text" class="form-control form-control-lg" name="name" required 
                                       value="<?php echo htmlspecialchars($item['name']); ?>"
                                       placeholder="Enter item name">
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select class="form-select form-select-lg" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $category['id'] == $item['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Price (RM)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">RM</span>
                                        <input type="number" 
                                               class="form-control" 
                                               id="price" 
                                               name="price" 
                                               step="0.10" 
                                               min="0" 
                                               value="<?php echo number_format($item['price'], 2); ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" 
                                          placeholder="Describe your menu item"><?php echo htmlspecialchars($item['description']); ?></textarea>
                            </div>

                            <div class="form-check form-switch status-switch">
                                <input class="form-check-input" type="checkbox" name="status" 
                                       id="statusSwitch" <?php echo $item['status'] == 'available' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="statusSwitch">Available</label>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-light btn-lg me-2" 
                                    onclick="window.location.href='menu_management.php'">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Changes
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
                    
                    <?php if ($item['image_path']): ?>
                        <p class="current-image-text">Current image:</p>
                        <div class="image-container mb-4">
                            <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" 
                                 class="preview-image" style="display: block;">
                        </div>
                    <?php endif; ?>
                    
                    <label class="image-upload-card w-100" for="imageInput">
                        <div id="uploadSection">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h5 class="upload-text">Drop new image here or click to browse</h5>
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

// Add JavaScript for image preview
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
});

document.querySelector("form").addEventListener("submit", function(e) {
    const price = document.getElementById("price").value;
    if (price <= 0) {
        e.preventDefault();
        alert("Price must be greater than RM 0.00");
    }
});
</script>
';

// Include the layout template
include 'includes/layout.php';
?> 