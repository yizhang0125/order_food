<?php
require_once 'Model.php';

class Order extends Model {
    protected $table_name = "orders";
    protected $conn;
    
    public function __construct($db) {
        parent::__construct($db);
        $this->conn = $db;
    }

    public function createOrder($tableId) {
        $query = "INSERT INTO " . $this->table_name . " 
                (table_id, status, total_amount) 
                VALUES (:table_id, 'pending', 0)";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":table_id", $tableId);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function addOrderItems($orderId, $items) {
        $total_amount = 0;
        
        foreach ($items as $itemId => $quantity) {
            if ($quantity > 0) {
                // Get item price
                $query = "SELECT price FROM menu_items WHERE id = :id AND status = 'available'";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $itemId);
                $stmt->execute();
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $item_total = $item['price'] * $quantity;
                    $total_amount += $item_total;
                    
                    // Insert order item
                    $query = "INSERT INTO order_items (order_id, menu_item_id, quantity, price) 
                             VALUES (:order_id, :item_id, :quantity, :price)";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':order_id', $orderId);
                    $stmt->bindParam(':item_id', $itemId);
                    $stmt->bindParam(':quantity', $quantity);
                    $stmt->bindParam(':price', $item['price']);
                    $stmt->execute();
                }
            }
        }
        
        // Update order total
        $this->updateTotal($orderId, $total_amount);
        return $total_amount;
    }
    
    public function updateTotal($orderId, $total) {
        $query = "UPDATE " . $this->table_name . " 
                SET total_amount = :total 
                WHERE id = :id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':id', $orderId);
        return $stmt->execute();
    }
    
    public function updateStatus($orderId, $status) {
        $query = "UPDATE " . $this->table_name . " 
                SET status = :status 
                WHERE id = :id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $orderId);
        return $stmt->execute();
    }
    
    public function getRecentOrders($limit = 10) {
        $query = "SELECT o.*, t.table_number 
                FROM " . $this->table_name . " o 
                JOIN tables t ON o.table_id = t.id 
                ORDER BY o.created_at DESC 
                LIMIT :limit";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getOrderDetails($orderId) {
        $query = "SELECT oi.*, mi.name, mi.category 
                FROM order_items oi 
                JOIN menu_items mi ON oi.menu_item_id = mi.id 
                WHERE oi.order_id = :order_id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalOrders() {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'];
        } catch (PDOException $e) {
            throw new Exception("Error getting total orders: " . $e->getMessage());
        }
    }

    public function getOrders($page = 1, $per_page = 10, $search = '') {
        try {
            $offset = ($page - 1) * $per_page;
            
            $query = "SELECT o.*, t.table_number, 
                      COUNT(oi.id) as item_count,
                      GROUP_CONCAT(CONCAT(mi.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
                      FROM " . $this->table_name . " o
                      LEFT JOIN tables t ON o.table_id = t.id
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id";
            
            if (!empty($search)) {
                $query .= " WHERE o.id LIKE :search 
                           OR t.table_number LIKE :search 
                           OR o.status LIKE :search";
            }
            
            $query .= " GROUP BY o.id, o.table_id, o.status, o.total_amount, o.created_at, t.table_number
                        ORDER BY o.created_at DESC
                        LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            
            if (!empty($search)) {
                $searchTerm = "%{$search}%";
                $stmt->bindParam(':search', $searchTerm);
            }
            
            $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching orders: " . $e->getMessage());
        }
    }
}
?> 