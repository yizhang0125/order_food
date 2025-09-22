<?php
class StaffController {
    private $db;
    private $auth;

    public function __construct($db) {
        $this->db = $db;
        $this->auth = new Auth($db);
    }

    public function getAllPermissions() {
        try {
            $query = "SELECT id, name, description FROM permissions ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error loading permissions: " . $e->getMessage());
        }
    }

    public function getStaffPermissionIds($staff_id) {
        try {
            $query = "SELECT permission_id FROM staff_permissions WHERE staff_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$staff_id]);
            return array_map(function($row) { return (int)$row['permission_id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            throw new Exception("Error loading staff permissions: " . $e->getMessage());
        }
    }

    public function getStaffPermissionNames($staff_id) {
        try {
            $query = "SELECT p.name FROM permissions p 
                      INNER JOIN staff_permissions sp ON sp.permission_id = p.id
                      WHERE sp.staff_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$staff_id]);
            return array_map(function($row) { return $row['name']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            throw new Exception("Error loading staff permission names: " . $e->getMessage());
        }
    }

    public function addStaff($data) {
        try {
            $this->db->beginTransaction();
            
            // Validate and clean input
            $employee_number = trim($data['employee_number']);
            $name = trim($data['name']);
            $email = trim($data['email']);
            $password = $data['password'];
            $position = $data['position'];
            $employment_type = $data['employment_type'];
            
            // Check for duplicates
            if ($this->isDuplicate($employee_number, $email)) {
                throw new Exception("Employee number or email already exists");
            }
            
            // Insert staff
            $staff_id = $this->insertStaffRecord([
                'employee_number' => $employee_number,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'position' => $position,
                'employment_type' => $employment_type
            ]);
            
            // Handle permissions
            if (!empty($data['staff_permissions'])) {
                $this->assignPermissions($staff_id, $data['staff_permissions']);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function isDuplicate($employee_number, $email) {
        $query = "SELECT id FROM staff WHERE employee_number = ? OR email = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$employee_number, $email]);
        return $stmt->rowCount() > 0;
    }

    private function insertStaffRecord($data) {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $query = "INSERT INTO staff (employee_number, name, email, password, position, employment_type) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $data['employee_number'],
            $data['name'],
            $data['email'],
            $hash,
            $data['position'],
            $data['employment_type']
        ]);
        return $this->db->lastInsertId();
    }

    private function assignPermissions($staff_id, $permissions) {
        $query = "INSERT INTO staff_permissions (staff_id, permission_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($query);
        foreach ($permissions as $permission_id) {
            $stmt->execute([$staff_id, $permission_id]);
        }
    }

    public function getAllStaff() {
        try {
            $query = "SELECT id, employee_number, name, email, position, employment_type, is_active, created_at 
                     FROM staff ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error retrieving staff list: " . $e->getMessage());
        }
    }

    public function deleteStaff($staff_id) {
        try {
            $this->db->beginTransaction();

            // Delete staff permissions first
            $delete_perms = "DELETE FROM staff_permissions WHERE staff_id = ?";
            $stmt = $this->db->prepare($delete_perms);
            $stmt->execute([$staff_id]);

            // Delete staff record
            $delete_staff = "DELETE FROM staff WHERE id = ?";
            $stmt = $this->db->prepare($delete_staff);
            $stmt->execute([$staff_id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Error deleting staff member: " . $e->getMessage());
        }
    }

    public function getStaffById($staff_id) {
        try {
            $query = "SELECT * FROM staff WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$staff_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error retrieving staff member: " . $e->getMessage());
        }
    }

    public function updateStaff($staff_id, $data) {
        try {
            $this->db->beginTransaction();
            
            $current_staff = $this->getStaffById($staff_id);
            if (!$current_staff) {
                throw new Exception("Staff member not found");
            }

            // Check for duplicate email/employee number except for current staff
            $check_query = "SELECT id FROM staff WHERE (employee_number = ? OR email = ?) AND id != ?";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->execute([$data['employee_number'], $data['email'], $staff_id]);
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Employee number or email already exists");
            }

            $update_query = "UPDATE staff SET 
                           employee_number = ?,
                           name = ?,
                           email = ?,
                           position = ?,
                           employment_type = ?,
                           is_active = ?
                           WHERE id = ?";

            $stmt = $this->db->prepare($update_query);
            $stmt->execute([
                $data['employee_number'],
                $data['name'],
                $data['email'],
                $data['position'],
                $data['employment_type'],
                isset($data['is_active']) ? 1 : 0,
                $staff_id
            ]);

            // Update password if provided
            if (!empty($data['password'])) {
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $pass_query = "UPDATE staff SET password = ? WHERE id = ?";
                $pass_stmt = $this->db->prepare($pass_query);
                $pass_stmt->execute([$hash, $staff_id]);
            }

            // Update permissions
            if (isset($data['staff_permissions'])) {
                // Delete existing permissions
                $delete_perms = "DELETE FROM staff_permissions WHERE staff_id = ?";
                $stmt = $this->db->prepare($delete_perms);
                $stmt->execute([$staff_id]);

                // Insert new permissions
                if (!empty($data['staff_permissions'])) {
                    $insert_perm = "INSERT INTO staff_permissions (staff_id, permission_id) VALUES (?, ?)";
                    $perm_stmt = $this->db->prepare($insert_perm);
                    foreach ($data['staff_permissions'] as $permission_id) {
                        $perm_stmt->execute([$staff_id, $permission_id]);
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Error updating staff member: " . $e->getMessage());
        }
    }
}