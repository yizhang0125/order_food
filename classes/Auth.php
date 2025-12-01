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
            // First try admin login
            $admin_query = "SELECT * FROM admins WHERE username = :username OR email = :email";
            $stmt = $this->conn->prepare($admin_query);
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $username);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['user_type'] = 'admin';
                return true;
            }
            
            // If not admin, try staff login
            $staff_query = "SELECT s.*, GROUP_CONCAT(p.name) as permissions 
                          FROM staff s 
                          LEFT JOIN staff_permissions sp ON s.id = sp.staff_id 
                          LEFT JOIN permissions p ON sp.permission_id = p.id 
                          WHERE s.email = :username OR s.employee_number = :username
                          GROUP BY s.id";
            $stmt = $this->conn->prepare($staff_query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // For debugging
            error_log("Staff login attempt - Username: " . $username);
            if ($staff) {
                error_log("Staff found: " . print_r($staff, true));
            } else {
                error_log("No staff found with email/employee number: " . $username);
            }
            
            if ($staff) {
                error_log("Verifying password for staff");
                if (password_verify($password, $staff['password'])) {
                    error_log("Password verified successfully");
                    if ($staff['is_active']) {
                        $_SESSION['staff_id'] = $staff['id'];
                        $_SESSION['staff_name'] = $staff['name'];
                        $_SESSION['staff_position'] = $staff['position'];
                        $_SESSION['staff_permissions'] = $staff['permissions'] ? explode(',', $staff['permissions']) : [];
                        $_SESSION['user_type'] = 'staff';
                        error_log("Staff logged in successfully: " . $staff['name']);
                        return true;
                    } else {
                        error_log("Staff account inactive: " . $staff['name']);
                        throw new Exception("Account is inactive. Please contact administrator.");
                    }
                } else {
                    error_log("Invalid password for staff: " . $staff['name']);
                }
            }
            
            error_log("Login failed for username: " . $username);
            return false;
        } catch(PDOException $e) {
            // For debugging
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function isLoggedIn() {
        return (isset($_SESSION['admin']) && $_SESSION['admin'] === true) || 
               (isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']));
    }
    
    public function logout() {
        // Clear admin session
        unset($_SESSION['admin']);
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        
        // Clear staff session
        unset($_SESSION['staff_id']);
        unset($_SESSION['staff_name']);
        unset($_SESSION['staff_position']);
        unset($_SESSION['staff_permissions']);
        unset($_SESSION['staff_email']);
        unset($_SESSION['staff_employee_number']);
        unset($_SESSION['user_type']);
        
        session_destroy();
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }

    /**
     * Update password for the current logged-in user (admin or staff).
     * If $user_id is provided it will be used, otherwise session IDs are used.
     * Returns true on success, false on failure (e.g., current password mismatch).
     */
    public function updatePassword($user_id = null, $current_password, $new_password) {
        try {
            // Prefer explicit user_id if provided, otherwise use session
            $adminId = $user_id ?? ($_SESSION['admin_id'] ?? null);
            $staffId = $_SESSION['staff_id'] ?? null;

            // If adminId is set and exists in admins table, treat as admin
            if ($adminId) {
                $query = "SELECT id, password FROM admins WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$adminId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return false;
                if (!password_verify($current_password, $row['password'])) {
                    return false;
                }
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = "UPDATE admins SET password = ? WHERE id = ?";
                $ustmt = $this->conn->prepare($update);
                return $ustmt->execute([$hash, $adminId]);
            }

            // Otherwise, try staff
            if ($staffId) {
                $query = "SELECT id, password FROM staff WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$staffId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return false;
                if (!password_verify($current_password, $row['password'])) {
                    return false;
                }
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = "UPDATE staff SET password = ? WHERE id = ?";
                $ustmt = $this->conn->prepare($update);
                return $ustmt->execute([$hash, $staffId]);
            }

            return false;
        } catch (Exception $e) {
            error_log("Error updating password: " . $e->getMessage());
            return false;
        }
    }
}
?> 