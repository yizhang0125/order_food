<?php
require_once 'Model.php';

class MenuItem extends Model {
    protected $table = 'menu_items';

    public function getAllWithCategories() {
        $query = "SELECT m.*, c.name as category_name 
                  FROM " . $this->table . " m 
                  LEFT JOIN categories c ON m.category_id = c.id 
                  ORDER BY c.name, m.name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableItems() {
        $query = "SELECT m.*, c.name as category_name 
                  FROM " . $this->table . " m 
                  LEFT JOIN categories c ON m.category_id = c.id 
                  WHERE m.status = 'available' AND c.status = 'active'
                  ORDER BY c.name, m.name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        return $stmt->execute();
    }

    public function uploadImage($file) {
        $upload_dir = '../uploads/menu_items/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return 'uploads/menu_items/' . $file_name;
        }
        return '';
    }

    public function deleteImage($image_path) {
        if ($image_path && file_exists('../' . $image_path)) {
            return unlink('../' . $image_path);
        }
        return false;
    }

    public function getItemsByCategory() {
        $items = $this->getAvailableItems();
        $menu_by_category = [];
        foreach ($items as $item) {
            if (!isset($menu_by_category[$item['category_name']])) {
                $menu_by_category[$item['category_name']] = [];
            }
            $menu_by_category[$item['category_name']][] = $item;
        }
        return $menu_by_category;
    }
}
?> 