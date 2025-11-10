<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/MenuItemController.php');

$database = new Database();
$db = $database->getConnection();
$controller = new MenuItemController($db);

// Check permissions
$controller->checkPermissions();

$page_title = 'Add Menu Item';
$error = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $controller->createMenuItem($_POST, $_FILES['image'] ?? null);
    
    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
        header('Location: menu_management.php');
        exit();
    } else {
        $error = $result['message'];
        $formData = $_POST; // Keep form data for re-display
    }
}

// Get all active categories for the dropdown
$categories = $controller->getActiveCategories();

// Include external CSS
$extra_css = '<link href="css/menu_item_forms.css" rel="stylesheet">';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Add Menu Item</h1>
            <p class="text-muted">Create a new menu item</p>
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

    <div class="row">
        <div class="col-md-8">
            <div class="card form-card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="menuItemForm">
                        <div class="mb-4">
                            <div class="form-group-title">Menu Item Details</div>

                            <div class="mb-4">
                                <label class="form-label">Item Name</label>
                                <input type="text" class="form-control form-control-lg" name="name"
                                       value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                                       placeholder="Enter item name" required>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select class="form-select form-select-lg" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): 
                                            $selected = (isset($formData['category_id']) && $formData['category_id'] == $category['id']) ? 'selected' : '';
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
                                               value="<?php echo htmlspecialchars($formData['price'] ?? ''); ?>" required placeholder="0.00" pattern="^\d*\.?\d{0,2}$">
                                    </div>
                                    <div class="price-validation"></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" required placeholder="Describe your menu item"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-light btn-lg me-2" onclick="window.location.href='menu_management.php'">Cancel</button>
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

                        <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;" form="menuItemForm">
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript (moved from controller)
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

    const form = document.getElementById("menuItemForm");
    if (form) {
        form.addEventListener("submit", function(event) {
            const price = document.getElementById("price");
            if (price && parseFloat(price.value) <= 0) {
                event.preventDefault();
                alert("Price must be greater than 0");
            }
        });
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
</script>';

// Include the layout template
include 'includes/layout.php';
?>