<?php
require_once 'Model.php';

class Category extends Model {
    protected $table = 'categories';

    /**
     * Get all active categories
     */
    public function getActiveCategories() {
        $query = "SELECT * FROM " . $this->table . " WHERE status = 'active' ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all inactive categories
     */
    public function getInactiveCategories() {
        $query = "SELECT * FROM " . $this->table . " WHERE status = 'inactive' ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get category by ID with debugging
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log the result
        error_log("Category getById - ID: $id, Found: " . ($result ? 'YES' : 'NO'));
        if ($result) {
            error_log("Category data: " . json_encode($result));
        }
        
        return $result;
    }

    /**
     * Update category status
     */
    public function updateStatus($id, $status) {
        // Validate status value
        if (!in_array($status, ['active', 'inactive'])) {
            error_log("Invalid status value: $status");
            return false;
        }
        
        $query = "UPDATE " . $this->table . " SET status = :status, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        
        // Debug: Log the query and parameters
        error_log("Category updateStatus - ID: $id, Status: $status");
        error_log("SQL Query: $query");
        
        $result = $stmt->execute();
        
        // Debug: Log the result and affected rows
        error_log("Update result: " . ($result ? 'SUCCESS' : 'FAILED'));
        error_log("Affected rows: " . $stmt->rowCount());
        
        if (!$result) {
            error_log("PDO Error: " . implode(', ', $stmt->errorInfo()));
        }
        
        return $result;
    }

    /**
     * Get categories with item count
     */
    public function getCategoryWithItemCount() {
        $query = "SELECT c.*, COUNT(m.id) as item_count 
                  FROM " . $this->table . " c 
                  LEFT JOIN menu_items m ON c.id = m.category_id 
                  GROUP BY c.id 
                  ORDER BY c.name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get categories with item count filtered by status
     */
    public function getCategoryWithItemCountByStatus($status = null) {
        $whereClause = '';
        if ($status) {
            $whereClause = "WHERE c.status = :status";
        }
        
        $query = "SELECT c.*, COUNT(m.id) as item_count 
                  FROM " . $this->table . " c 
                  LEFT JOIN menu_items m ON c.id = m.category_id 
                  $whereClause
                  GROUP BY c.id 
                  ORDER BY c.name";
        
        $stmt = $this->db->prepare($query);
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if category name exists (for validation)
     */
    public function nameExists($name, $excludeId = null) {
        $query = "SELECT COUNT(*) FROM " . $this->table . " WHERE name = :name";
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name);
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId);
        }
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats() {
        $query = "SELECT 
                    COUNT(*) as total_categories,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_categories,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_categories
                  FROM " . $this->table;
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search categories by name or description
     */
    public function searchCategories($searchTerm) {
        $query = "SELECT c.*, COUNT(m.id) as item_count 
                  FROM " . $this->table . " c 
                  LEFT JOIN menu_items m ON c.id = m.category_id 
                  WHERE c.name LIKE :search OR c.description LIKE :search
                  GROUP BY c.id 
                  ORDER BY c.name";
        
        $stmt = $this->db->prepare($query);
        $searchParam = '%' . $searchTerm . '%';
        $stmt->bindParam(':search', $searchParam);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get categories with most menu items
     */
    public function getTopCategoriesByItemCount($limit = 5) {
        $query = "SELECT c.*, COUNT(m.id) as item_count 
                  FROM " . $this->table . " c 
                  LEFT JOIN menu_items m ON c.id = m.category_id 
                  WHERE c.status = 'active'
                  GROUP BY c.id 
                  ORDER BY item_count DESC, c.name ASC
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new category with proper timestamps
     */
    public function create($data) {
        // Add timestamps
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return parent::create($data);
    }
    
    /**
     * Update a category with enhanced error logging
     */
    public function update($id, $data) {
        try {
            // Log update attempt
            error_log("Attempting to update category ID: $id with data: " . json_encode($data));

            // Validate data before update
            $errors = $this->validateCategoryData($data);
            if (!empty($errors)) {
                error_log("Validation failed for category update: " . implode(", ", $errors));
                return false;
            }

            // Check if category exists
            $existing = $this->getById($id);
            if (!$existing) {
                error_log("Category not found with ID: $id");
                return false;
            }

            // Check for duplicate name
            if (isset($data['name']) && $this->nameExists($data['name'], $id)) {
                error_log("Category name already exists: {$data['name']}");
                return false;
            }

            // Add updated timestamp
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Perform update
            $result = parent::update($id, $data);
            
            // Log result
            error_log("Category update result for ID $id: " . ($result ? "Success" : "Failed"));
            
            return $result;

        } catch (PDOException $e) {
            error_log("Database error during category update: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Unexpected error during category update: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate category data
     */
    public function validateCategoryData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Category name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors[] = 'Category name must be at least 2 characters long';
        } elseif (strlen($data['name']) > 100) {
            $errors[] = 'Category name must not exceed 100 characters';
        }
        
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors[] = 'Category description must not exceed 500 characters';
        }
        
        return $errors;
    }
    
    /**
     * Test database connection and table structure
     */
    public function testConnection() {
        try {
            // Test basic connection
            $query = "SELECT COUNT(*) as count FROM " . $this->table;
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Database connection test - Total categories: " . $result['count']);
            
            // Test table structure
            $query = "DESCRIBE " . $this->table;
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Table structure: " . json_encode($columns));
            
            return true;
        } catch (Exception $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the last inserted ID
     */
    public function getLastInsertId() {
        return $this->db->lastInsertId();
    }
}
?>