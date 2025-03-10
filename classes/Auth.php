<?php
class Auth {
    private $conn;
    private $table_name = "admins";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function register($username, $password, $email) {
        try {
            // Check if username or email already exists
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE username = :username OR email = :email";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(":username", $username);
            $check_stmt->bindParam(":email", $email);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                return ["success" => false, "message" => "Username or email already exists"];
            }
            
            // Create new admin
            $query = "INSERT INTO " . $this->table_name . " (username, password, email) VALUES (:username, :password, :email)";
            $stmt = $this->conn->prepare($query);
            
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":password", $hashed_password);
            $stmt->bindParam(":email", $email);
            
            if($stmt->execute()) {
                return ["success" => true, "message" => "Registration successful"];
            } else {
                return ["success" => false, "message" => "Registration failed"];
            }
        } catch(PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }
    
    public function login($username, $password) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // For debugging
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    $_SESSION['admin'] = true;
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            // For debugging
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
    }
    
    public function logout() {
        unset($_SESSION['admin']);
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        session_destroy();
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
}
?> 