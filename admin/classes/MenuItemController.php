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
}
?>
