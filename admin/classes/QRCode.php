<?php
class QRCodeManager {
    private $db;
    private $qr_dir;
    private $qr_web_path;
    private $menu_url;
    private $token_expiry_days;

    public function __construct($db) {
        $this->db = $db;
        $this->qr_dir = __DIR__ . '/../../uploads/qrcodes';
        $this->qr_web_path = '/food1/uploads/qrcodes';
        $this->menu_url = "http://" . $_SERVER['HTTP_HOST'] . "/food1";
        $this->token_expiry_days = 30;
        $this->initializeDirectory();
    }

    private function initializeDirectory() {
        if (!file_exists($this->qr_dir)) {
            if (!mkdir($this->qr_dir, 0777, true)) {
                throw new Exception("Failed to create QR codes directory");
            }
            file_put_contents($this->qr_dir . DIRECTORY_SEPARATOR . 'index.html', '');
        }

        if (!is_writable($this->qr_dir)) {
            chmod($this->qr_dir, 0777);
            if (!is_writable($this->qr_dir)) {
                throw new Exception("QR code directory is not writable");
            }
        }
    }

    public function getAllTables() {
        $query = "SELECT t.id, t.table_number, qc.token, qc.created_at as token_generated_at,
                  CASE 
                    WHEN qc.token IS NOT NULL AND qc.created_at IS NOT NULL 
                    AND qc.expires_at > NOW()
                    THEN 1 ELSE 0 
                  END as is_token_valid,
                  qc.expires_at as expiry_date,
                  qc.image_path
                  FROM tables t
                  LEFT JOIN qr_codes qc ON t.id = qc.table_id AND qc.is_active = 1
                  ORDER BY t.table_number";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateQRCode($table_id, $table_number) {
        try {
            $this->db->beginTransaction();
            
            $token = bin2hex(random_bytes(16));
            $menu_url_with_params = $this->menu_url . "/index.php?table=" . urlencode($table_number) . "&token=" . urlencode($token);
            $qr_filename = 'table_' . $table_number . '_' . time() . '.png';
            $qr_path = $this->qr_dir . DIRECTORY_SEPARATOR . $qr_filename;
            
            // Generate QR code
            QRcode::png($menu_url_with_params, $qr_path, QR_ECLEVEL_L, 10, 2);
            if (!file_exists($qr_path)) {
                throw new Exception("QR code file was not created");
            }
            
            chmod($qr_path, 0644);
            
            // Deactivate old QR codes
            $deactivate_query = "UPDATE qr_codes SET is_active = 0 WHERE table_id = ?";
            $deactivate_stmt = $this->db->prepare($deactivate_query);
            $deactivate_stmt->execute([$table_id]);
            
            // Calculate expiry date
            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$this->token_expiry_days} days"));
            
            // Save new QR code
            $insert_query = "INSERT INTO qr_codes (table_id, token, image_path, created_at, expires_at, is_active) 
                            VALUES (?, ?, ?, NOW(), ?, 1)";
            $insert_stmt = $this->db->prepare($insert_query);
            if (!$insert_stmt->execute([$table_id, $token, $qr_filename, $expiry_date])) {
                throw new Exception("Failed to save QR code information");
            }
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => "QR code generated successfully for Table {$table_number}. Valid until: " . date('Y-m-d', strtotime($expiry_date))
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            if (isset($qr_path) && file_exists($qr_path)) {
                unlink($qr_path);
            }
            return [
                'success' => false,
                'message' => "Error generating QR code: " . $e->getMessage()
            ];
        }
    }

    public function addTable($table_number) {
        try {
            $this->db->beginTransaction();
            
            if (empty($table_number)) {
                throw new Exception("Table number is required");
            }
            
            // Check if exists
            $check_query = "SELECT id FROM tables WHERE table_number = ?";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->execute([$table_number]);
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Table number already exists");
            }
            
            // Insert table
            $insert_query = "INSERT INTO tables (table_number) VALUES (?)";
            $insert_stmt = $this->db->prepare($insert_query);
            
            if (!$insert_stmt->execute([$table_number])) {
                throw new Exception("Failed to add table");
            }
            
            $table_id = $this->db->lastInsertId();
            
            // Generate QR code for new table
            $result = $this->generateQRCode($table_id, $table_number);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => "Table added and QR code generated successfully"
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function deleteTable($table_id) {
        try {
            $this->db->beginTransaction();
            
            // Get QR codes to delete files
            $get_qr_query = "SELECT image_path FROM qr_codes WHERE table_id = ?";
            $get_qr_stmt = $this->db->prepare($get_qr_query);
            $get_qr_stmt->execute([$table_id]);
            $qr_codes = $get_qr_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete physical files
            foreach ($qr_codes as $qr) {
                if (!empty($qr['image_path'])) {
                    $file_path = $this->qr_dir . DIRECTORY_SEPARATOR . $qr['image_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            
            // Delete from database
            $delete_qr_query = "DELETE FROM qr_codes WHERE table_id = ?";
            $delete_qr_stmt = $this->db->prepare($delete_qr_query);
            $delete_qr_stmt->execute([$table_id]);
            
            $delete_query = "DELETE FROM tables WHERE id = ?";
            $delete_stmt = $this->db->prepare($delete_query);
            
            if (!$delete_stmt->execute([$table_id])) {
                throw new Exception("Failed to delete table");
            }
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => "Table and associated QR codes deleted successfully"
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getQRCodePath() {
        return $this->qr_web_path;
    }

    public function getPhysicalPath() {
        return $this->qr_dir;
    }
} 