<?php

class Database {
    private $host = "localhost";
    private $db_name = "food_ordering1";
    private $username = "root";
    private $password = "1234";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Ensure PHP uses the correct timezone for all date() calls
            if (!ini_get('date.timezone')) {
                date_default_timezone_set('Asia/Kuala_Lumpur');
            }
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Ensure MySQL session uses the same timezone
            try {
                $this->conn->exec("SET time_zone = '+08:00'");
            } catch (Exception $e) {}
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}
?> 