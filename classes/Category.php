<?php
require_once 'Model.php';

class Category extends Model {
    protected $table = 'categories';

    public function getActiveCategories() {
        $query = "SELECT * FROM " . $this->table . " WHERE status = 'active' ORDER BY name";
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
}
?> 