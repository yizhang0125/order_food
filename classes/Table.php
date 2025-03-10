<?php
require_once 'Model.php';

class Table extends Model {
    protected $table_name = "tables";
    
    public function createTable($tableNumber, $token) {
        $query = "INSERT INTO " . $this->table_name . " 
                (table_number, token, status) 
                VALUES (:table_number, :token, 'active')";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":table_number", $tableNumber);
        $stmt->bindParam(":token", $token);
        
        return $stmt->execute();
    }
    
    public function validateToken($token) {
        $query = "SELECT * FROM " . $this->table_name . " 
                WHERE token = :token AND status = 'active'";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $token);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getActiveTables() {
        $query = "SELECT * FROM " . $this->table_name . " 
                WHERE status = 'active' 
                ORDER BY table_number";
                
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                SET status = :status 
                WHERE id = :id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }
}
?> 