<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/MenuItemController.php');

$database = new Database();
$db = $database->getConnection();
$controller = new MenuItemController($db);

// Check permissions
$controller->checkPermissions();

// Check if item ID is provided
if (!isset($_GET['id'])) {
    header('Location: menu_management.php');
    exit();
}

$item_id = intval($_GET['id']);
$page_title = 'Edit Menu Item';

// Get menu item details
$item = $controller->getMenuItem($item_id);

if (!$item) {
    header('Location: menu_management.php');
    exit();
}

// Handle form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $controller->updateMenuItem($item_id, $_POST, $_FILES['image'] ?? null);
    
    if ($result['success']) {
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = "success";
        header('Location: menu_management.php');
        exit();
    } else {
        $error = $result['message'];
    }
}

// Get all active categories
$categories = $controller->getActiveCategories();

// Include external CSS
$extra_css = '<link href="css/menu_item_forms.css" rel="stylesheet">';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Edit Menu Item</h1>
            <p class="text-muted">Update the menu item details</p>
        </div>
        <a href="menu_management.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Menu
        </a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="menuItemForm">
        <input type="hidden" name="id" value="<?php echo $item_id; ?>">
        <div class="row">
            <div class="col-md-8">
                <div class="card form-card">
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control form-control-lg" name="name" required
                                   value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select class="form-select form-select-lg" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category):
                                        $selected = (isset($item['category_id']) && $item['category_id'] == $category['id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Price (RM)</label>
                                <div class="price-input-group">
                                    <span class="currency-symbol">RM</span>
                                    <input type="text" class="form-control form-control-lg price-input" name="price" id="price"
                                           value="<?php echo isset($item['price']) ? number_format($item['price'], 2, '.', '') : ''; ?>" required>
                                </div>
                                <div class="price-validation"></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="status" id="status" value="active" <?php echo (isset($item['status']) && $item['status'] === 'active') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">Active</label>
                        </div>

                        <div class="text-end">
                            <a href="menu_management.php" class="btn btn-light btn-lg me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
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

                            <?php
                            // Determine whether the stored image path points to an existing file
                            $image_exists = !empty($item['image_path']) && file_exists(__DIR__ . '/../' . $item['image_path']);
                            ?>
                            <div class="image-container" id="newImageContainer" style="display: <?php echo $image_exists ? 'block' : 'none'; ?>;">
                                <img id="imagePreview" class="preview-image" src="<?php echo $image_exists ? htmlspecialchars('../' . $item['image_path']) : ''; ?>">
                                <div class="remove-image" id="removeImage">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>

                            <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;" form="menuItemForm">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const imageInput = document.getElementById("imageInput");
    const imagePreview = document.getElementById("imagePreview");
    const uploadSection = document.getElementById("uploadSection");
    const removeImage = document.getElementById("removeImage");
    const newImageContainer = document.getElementById("newImageContainer");

    if (imageInput) {
        imageInput.addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    alert("File size must be less than 2MB");
                    this.value = "";
                    return;
                }
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
    }

    if (removeImage) {
        removeImage.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            imageInput.value = "";
            newImageContainer.style.display = "none";
            uploadSection.style.display = "block";
        });
    }

    const uploadCard = document.querySelector(".image-upload-card");
    if (uploadCard) {
        ["dragenter", "dragover", "dragleave", "drop"].forEach(eventName => {
            uploadCard.addEventListener(eventName, preventDefaults, false);
        });
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
        ["dragenter", "dragover"].forEach(eventName => uploadCard.addEventListener(eventName, highlight, false));
        ["dragleave", "drop"].forEach(eventName => uploadCard.addEventListener(eventName, unhighlight, false));
        function highlight(e) { uploadCard.classList.add("border-primary"); }
        function unhighlight(e) { uploadCard.classList.remove("border-primary"); }
        uploadCard.addEventListener("drop", function(e) {
            const dt = e.dataTransfer;
            const file = dt.files[0];
            imageInput.files = dt.files;
            imageInput.dispatchEvent(new Event("change"));
        }, false);
    }

    const priceInput = document.getElementById("price");
    const priceValidation = document.querySelector(".price-validation");
    if (priceInput && priceValidation) {
        priceInput.addEventListener("input", function(e) {
            let value = e.target.value;
            value = value.replace(/[^\d.]/g, "");
            const decimalPoints = value.match(/\./g);
            if (decimalPoints && decimalPoints.length > 1) value = value.replace(/\.+$/, "");
            if (value.includes(".")) {
                const parts = value.split(".");
                value = parts[0] + "." + (parts[1] || "").slice(0, 2);
            }
            e.target.value = value;
            if (value === "") {
                priceValidation.textContent = "Price is required";
                priceValidation.className = "price-validation invalid";
            } else if (isNaN(value) || parseFloat(value) <= 0) {
                priceValidation.textContent = "Please enter a valid price";
                priceValidation.className = "price-validation invalid";
            } else {
                priceValidation.textContent = "Valid price format";
                priceValidation.className = "price-validation valid";
            }
        });
        priceInput.addEventListener("blur", function(e) {
            const value = e.target.value;
            if (value && !isNaN(value)) e.target.value = parseFloat(value).toFixed(2);
        });
    }
});
</script>
';

include 'includes/layout.php';
?>