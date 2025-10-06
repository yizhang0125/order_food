<?php
require_once(__DIR__ . '/../../config/Database.php');
require_once(__DIR__ . '/../../classes/Auth.php');
require_once(__DIR__ . '/../../classes/MenuItem.php');
require_once(__DIR__ . '/../../classes/Category.php');

class MenuItemController {
    private $db;
    private $auth;
    private $menuItemModel;
    private $categoryModel;
    
    public function __construct($database) {
        $this->db = $database;
        $this->auth = new Auth($database);
        $this->menuItemModel = new MenuItem($database);
        $this->categoryModel = new Category($database);
    }
    
    /**
     * Check if user is logged in
     */
    public function checkPermissions() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
    
    /**
     * Get all active categories
     */
    public function getActiveCategories() {
        try {
            return $this->categoryModel->getActiveCategories();
        } catch (Exception $e) {
            error_log("Error in MenuItemController::getActiveCategories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new menu item
     */
    public function createMenuItem($data, $imageFile = null) {
        try {
            // Validate price format
            $price = str_replace(['RM', ' ', ','], '', $data['price']);
            if (!is_numeric($price)) {
                throw new Exception("Invalid price format. Please enter a valid number.");
            }
            
            $menuData = [
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $price,
                'category_id' => $data['category_id'],
                'status' => 'available'
            ];

            // Handle image upload
            if ($imageFile && $imageFile['error'] == 0) {
                $imagePath = $this->handleImageUpload($imageFile);
                if ($imagePath) {
                    $menuData['image_path'] = $imagePath;
                }
            }

            if ($this->menuItemModel->create($menuData)) {
                return [
                    'success' => true,
                    'message' => "Menu item added successfully!"
                ];
            } else {
                throw new Exception("Failed to create menu item.");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get menu item by ID
     */
    public function getMenuItem($itemId) {
        try {
            $query = "SELECT m.*, c.name as category_name 
                      FROM menu_items m 
                      LEFT JOIN categories c ON m.category_id = c.id 
                      WHERE m.id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $itemId);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in MenuItemController::getMenuItem: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update menu item
     */
    public function updateMenuItem($itemId, $data, $imageFile = null) {
        try {
            // Get current item data
            $currentItem = $this->getMenuItem($itemId);
            if (!$currentItem) {
                throw new Exception("Menu item not found.");
            }
            
            // Handle image upload
            $imagePath = $currentItem['image_path']; // Keep existing image by default
            if ($imageFile && $imageFile['error'] == 0) {
                $newImagePath = $this->handleImageUpload($imageFile);
                if ($newImagePath) {
                    // Delete old image if exists
                    if ($currentItem['image_path'] && file_exists('../' . $currentItem['image_path'])) {
                        unlink('../' . $currentItem['image_path']);
                    }
                    $imagePath = $newImagePath;
                }
            }

            // Update menu item
            $query = "UPDATE menu_items 
                      SET name = :name, 
                          description = :description, 
                          price = :price, 
                          category_id = :category_id, 
                          status = :status, 
                          image_path = :image_path 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            
            $status = isset($data['status']) ? 'available' : 'unavailable';
            
            // Bind all parameters
            $params = [
                ':name' => $data['name'],
                ':description' => $data['description'],
                ':price' => $data['price'],
                ':category_id' => $data['category_id'],
                ':status' => $status,
                ':image_path' => $imagePath,
                ':id' => $itemId
            ];
            
            if ($stmt->execute($params)) {
                return [
                    'success' => true,
                    'message' => "Menu item updated successfully!"
                ];
            } else {
                throw new Exception("Error updating menu item.");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle image upload
     */
    private function handleImageUpload($imageFile) {
        $target_dir = "../uploads/menu_items/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed.");
        }

        if (move_uploaded_file($imageFile['tmp_name'], $target_file)) {
            return 'uploads/menu_items/' . $new_filename;
        } else {
            throw new Exception("Failed to upload image.");
        }
    }
    
    /**
     * Render add menu item form
     */
    public function renderAddForm($categories, $formData = [], $error = '') {
        $html = '<div class="container-fluid">';
        
        // Header
        $html .= '<div class="d-flex justify-content-between align-items-center mb-4">';
        $html .= '<div>';
        $html .= '<h1 class="h3 mb-0">Add Menu Item</h1>';
        $html .= '<p class="text-muted">Create a new menu item</p>';
        $html .= '</div>';
        $html .= '<a href="menu_management.php" class="btn btn-outline-primary">';
        $html .= '<i class="fas fa-arrow-left me-2"></i>Back to Menu';
        $html .= '</a>';
        $html .= '</div>';

        // Error message
        if ($error) {
            $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            $html .= htmlspecialchars($error);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
        }

        $html .= '<div class="row">';
        $html .= '<div class="col-md-8">';
        $html .= '<div class="card form-card">';
        $html .= '<div class="card-body">';
        $html .= '<form method="POST" enctype="multipart/form-data" id="menuItemForm">';
        
        // Basic Information
        $html .= '<div class="mb-4">';
        $html .= '<div class="form-group-title">Menu Item Details</div>';
        
        // Item Name
        $html .= '<div class="mb-4">';
        $html .= '<label class="form-label">Item Name</label>';
        $html .= '<input type="text" class="form-control form-control-lg" name="name" ';
        $html .= 'value="' . htmlspecialchars($formData['name'] ?? '') . '" ';
        $html .= 'placeholder="Enter item name" required>';
        $html .= '</div>';
        
        // Category and Price
        $html .= '<div class="row mb-4">';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Category</label>';
        $html .= '<select class="form-select form-select-lg" name="category_id" required>';
        $html .= '<option value="">Select Category</option>';
        
        foreach ($categories as $category) {
            $selected = (isset($formData['category_id']) && $formData['category_id'] == $category['id']) ? 'selected' : '';
            $html .= '<option value="' . $category['id'] . '" ' . $selected . '>';
            $html .= htmlspecialchars($category['name']);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        // Price Input
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Price (RM)</label>';
        $html .= '<div class="price-input-group">';
        $html .= '<span class="currency-symbol">RM</span>';
        $html .= '<input type="text" class="form-control form-control-lg price-input" name="price" id="price" required placeholder="0.00" pattern="^\d*\.?\d{0,2}$">';
        $html .= '</div>';
        $html .= '<div class="price-validation"></div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Description
        $html .= '<div class="mb-4">';
        $html .= '<label class="form-label">Description</label>';
        $html .= '<textarea class="form-control" name="description" rows="4" required placeholder="Describe your menu item">';
        $html .= htmlspecialchars($formData['description'] ?? '');
        $html .= '</textarea>';
        $html .= '</div>';
        $html .= '</div>';

        // Submit buttons
        $html .= '<div class="text-end">';
        $html .= '<button type="button" class="btn btn-light btn-lg me-2" onclick="window.location.href=\'menu_management.php\'">Cancel</button>';
        $html .= '<button type="submit" class="btn btn-primary btn-lg">';
        $html .= '<i class="fas fa-plus me-2"></i>Add Menu Item';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Image Upload Section
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card form-card">';
        $html .= '<div class="card-body">';
        $html .= '<div class="form-group-title mb-4">Item Image</div>';
        
        $html .= '<label class="image-upload-card w-100" for="imageInput">';
        $html .= '<div id="uploadSection">';
        $html .= '<i class="fas fa-cloud-upload-alt upload-icon"></i>';
        $html .= '<h5 class="upload-text">Drop image here or click to browse</h5>';
        $html .= '<p class="upload-hint">Supports: JPG, PNG, JPEG</p>';
        $html .= '<p class="upload-hint">Max size: 2MB</p>';
        $html .= '</div>';
        $html .= '<div class="image-container" id="newImageContainer" style="display: none;">';
        $html .= '<img id="imagePreview" class="preview-image">';
        $html .= '<div class="remove-image" id="removeImage">';
        $html .= '<i class="fas fa-times"></i>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<input type="file" name="image" id="imageInput" accept="image/*" style="display: none;" form="menuItemForm">';
        $html .= '</label>';
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render edit menu item form
     */
    public function renderEditForm($item, $categories, $error = '') {
        $html = '<div class="container-fluid">';
        
        // Header
        $html .= '<div class="d-flex justify-content-between align-items-center mb-4">';
        $html .= '<div>';
        $html .= '<h1 class="h3 mb-0">Edit Menu Item</h1>';
        $html .= '<p class="text-muted">Update menu item details</p>';
        $html .= '</div>';
        $html .= '<a href="menu_management.php" class="btn btn-outline-primary">';
        $html .= '<i class="fas fa-arrow-left me-2"></i>Back to Menu';
        $html .= '</a>';
        $html .= '</div>';

        // Error message
        if ($error) {
            $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            $html .= htmlspecialchars($error);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
        }

        $html .= '<div class="row">';
        $html .= '<div class="col-md-8">';
        $html .= '<div class="card form-card">';
        $html .= '<div class="card-body">';
        $html .= '<form method="POST" enctype="multipart/form-data" id="menuItemForm">';
        
        // Basic Information
        $html .= '<div class="mb-4">';
        $html .= '<div class="form-group-title">Menu Item Details</div>';
        
        // Item Name
        $html .= '<div class="mb-4">';
        $html .= '<label class="form-label">Item Name</label>';
        $html .= '<input type="text" class="form-control form-control-lg" name="name" required ';
        $html .= 'value="' . htmlspecialchars($item['name']) . '" placeholder="Enter item name">';
        $html .= '</div>';
        
        // Category and Price
        $html .= '<div class="row mb-4">';
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Category</label>';
        $html .= '<select class="form-select form-select-lg" name="category_id" required>';
        $html .= '<option value="">Select Category</option>';
        
        foreach ($categories as $category) {
            $selected = $category['id'] == $item['category_id'] ? 'selected' : '';
            $html .= '<option value="' . $category['id'] . '" ' . $selected . '>';
            $html .= htmlspecialchars($category['name']);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        // Price Input
        $html .= '<div class="col-md-6">';
        $html .= '<label class="form-label">Price (RM)</label>';
        $html .= '<div class="input-group">';
        $html .= '<span class="input-group-text">RM</span>';
        $html .= '<input type="number" class="form-control" id="price" name="price" step="0.10" min="0" ';
        $html .= 'value="' . number_format($item['price'], 2) . '" required>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Description
        $html .= '<div class="mb-4">';
        $html .= '<label class="form-label">Description</label>';
        $html .= '<textarea class="form-control" name="description" rows="4" placeholder="Describe your menu item">';
        $html .= htmlspecialchars($item['description']);
        $html .= '</textarea>';
        $html .= '</div>';

        // Status switch
        $html .= '<div class="form-check form-switch status-switch">';
        $html .= '<input class="form-check-input" type="checkbox" name="status" id="statusSwitch" ';
        $html .= ($item['status'] == 'available' ? 'checked' : '') . '>';
        $html .= '<label class="form-check-label" for="statusSwitch">Available</label>';
        $html .= '</div>';
        $html .= '</div>';

        // Submit buttons
        $html .= '<div class="text-end">';
        $html .= '<button type="button" class="btn btn-light btn-lg me-2" onclick="window.location.href=\'menu_management.php\'">Cancel</button>';
        $html .= '<button type="submit" class="btn btn-primary btn-lg">';
        $html .= '<i class="fas fa-save me-2"></i>Save Changes';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Image Upload Section
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card form-card">';
        $html .= '<div class="card-body">';
        $html .= '<div class="form-group-title mb-4">Item Image</div>';
        
        // Current image
        if ($item['image_path']) {
            $html .= '<p class="current-image-text">Current image:</p>';
            $html .= '<div class="image-container mb-4">';
            $html .= '<img src="../' . htmlspecialchars($item['image_path']) . '" class="preview-image" style="display: block;">';
            $html .= '</div>';
        }
        
        $html .= '<label class="image-upload-card w-100" for="imageInput">';
        $html .= '<div id="uploadSection">';
        $html .= '<i class="fas fa-cloud-upload-alt upload-icon"></i>';
        $html .= '<h5 class="upload-text">Drop new image here or click to browse</h5>';
        $html .= '<p class="upload-hint">Supports: JPG, PNG, JPEG</p>';
        $html .= '<p class="upload-hint">Max size: 2MB</p>';
        $html .= '</div>';
        $html .= '<div class="image-container" id="newImageContainer" style="display: none;">';
        $html .= '<img id="imagePreview" class="preview-image">';
        $html .= '<div class="remove-image" id="removeImage">';
        $html .= '<i class="fas fa-times"></i>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<input type="file" name="image" id="imageInput" accept="image/*" style="display: none;" form="menuItemForm">';
        $html .= '</label>';
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get JavaScript for form functionality
     */
    public function getFormJavaScript() {
        return '
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
            
            // Drag and drop functionality
            const uploadCard = document.querySelector(".image-upload-card");
            if (uploadCard) {
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
            }
            
            // Form validation
            const form = document.getElementById("menuItemForm");
            if (form) {
                form.addEventListener("submit", function(event) {
                    const price = document.querySelector("[name=price]") || document.getElementById("price");
                    if (price && parseFloat(price.value) <= 0) {
                        event.preventDefault();
                        alert("Price must be greater than 0");
                    }
                });
            }
            
            // Price formatting for add form
            const priceInput = document.getElementById("price");
            const priceValidation = document.querySelector(".price-validation");
            
            if (priceInput && priceValidation) {
                priceInput.addEventListener("input", function(e) {
                    let value = e.target.value;
                    
                    // Remove any non-numeric characters except decimal point
                    value = value.replace(/[^\d.]/g, "");
                    
                    // Ensure only one decimal point
                    const decimalPoints = value.match(/\./g);
                    if (decimalPoints && decimalPoints.length > 1) {
                        value = value.replace(/\.+$/, "");
                    }
                    
                    // Limit to two decimal places
                    if (value.includes(".")) {
                        const parts = value.split(".");
                        value = parts[0] + "." + (parts[1] || "").slice(0, 2);
                    }
                    
                    // Update input value
                    e.target.value = value;
                    
                    // Validate price
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
                
                // Format on blur
                priceInput.addEventListener("blur", function(e) {
                    const value = e.target.value;
                    if (value && !isNaN(value)) {
                        e.target.value = parseFloat(value).toFixed(2);
                    }
                });
            }
        });
        </script>';
    }
}
?>
