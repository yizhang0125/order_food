<?php
require_once __DIR__ . '/../../vendor/phpqrcode/qrlib.php';

class QRCodeManager {
    private $db;
    private $qr_dir;
    private $qr_web_path;
    private $menu_url;
    private $token_expiry_hours;

    public function __construct($db) {
        $this->db = $db;
        $this->qr_dir = __DIR__ . '/../../uploads/qrcodes';
        $this->qr_web_path = '/uploads/qrcodes';
        $this->menu_url = "http://" . $_SERVER['HTTP_HOST'] . "/food1";
        $this->token_expiry_hours = 2;
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

    /**
     * Complete an order and automatically generate new QR code
     */
    public function completeOrder($token) {
        try {
            $this->db->beginTransaction();
            
            // Get table information from the token
            $query = "SELECT qc.table_id, t.table_number, qc.image_path 
                     FROM qr_codes qc 
                     JOIN tables t ON qc.table_id = t.id 
                     WHERE qc.token = ? AND qc.is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            $table_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$table_info) {
                throw new Exception("Invalid or expired token");
            }
            
            // Delete the old QR code file
            if (!empty($table_info['image_path'])) {
                $old_file_path = $this->qr_dir . DIRECTORY_SEPARATOR . $table_info['image_path'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            
            // Deactivate current QR code
            $deactivate_query = "UPDATE qr_codes SET is_active = 0 WHERE token = ?";
            $deactivate_stmt = $this->db->prepare($deactivate_query);
            if (!$deactivate_stmt->execute([$token])) {
                throw new Exception("Failed to deactivate current QR code");
            }
            
            // Generate new QR code for the table
            $result = $this->generateQRCode($table_info['table_id'], $table_info['table_number'], true);
            if (!$result['success']) {
                throw new Exception("Failed to generate new QR code: " . $result['message']);
            }
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => "Order completed successfully. New QR code generated for Table {$table_info['table_number']}",
                'table_number' => $table_info['table_number'],
                'new_qr_info' => [
                    'path' => $result['qr_path'],
                    'expires_at' => $result['expires_at'],
                    'valid_until' => date('H:i', strtotime($result['expires_at']))
                ]
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return [
                'success' => false,
                'message' => "Error completing order: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get table information by token
     */
    public function getTableInfoByToken($token) {
        try {
            $query = "SELECT t.id as table_id, t.table_number, qc.created_at, qc.expires_at 
                     FROM qr_codes qc 
                     JOIN tables t ON qc.table_id = t.id 
                     WHERE qc.token = ? AND qc.is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    public function generateQRCode($table_id, $table_number, $within_transaction = false) {
        try {
            // Debug logging
            error_log("Starting QR code generation for table " . $table_number);
            error_log("QR directory: " . $this->qr_dir);
            
            if (!$within_transaction) {
                $transaction_started = $this->db->beginTransaction();
                if (!$transaction_started) {
                    throw new Exception("Could not start database transaction");
                }
            }
            
            // Check if phpqrcode library exists
            $qrlib_path = __DIR__ . '/../../vendor/phpqrcode/qrlib.php';
            if (!file_exists($qrlib_path)) {
                throw new Exception("phpqrcode library not found at: " . $qrlib_path);
            }
            error_log("phpqrcode library found at: " . $qrlib_path);
            
            // Verify directory exists and is writable
            if (!file_exists($this->qr_dir)) {
                error_log("Creating QR code directory: " . $this->qr_dir);
                if (!mkdir($this->qr_dir, 0777, true)) {
                    throw new Exception("Failed to create QR code directory: " . $this->qr_dir);
                }
            }
            
            if (!is_writable($this->qr_dir)) {
                error_log("Setting permissions on QR code directory");
                chmod($this->qr_dir, 0777);
                if (!is_writable($this->qr_dir)) {
                    throw new Exception("QR code directory is not writable: " . $this->qr_dir);
                }
            }
            
            $token = bin2hex(random_bytes(16));
            $menu_url_with_params = $this->menu_url . "/index.php?table=" . urlencode($table_number) . "&token=" . urlencode($token);
            $qr_filename = 'table_' . $table_number . '_' . time() . '.png';
            $qr_path = $this->qr_dir . DIRECTORY_SEPARATOR . $qr_filename;
            
            error_log("Attempting to generate QR code:");
            error_log("URL: " . $menu_url_with_params);
            error_log("File path: " . $qr_path);
            error_log("Web path: " . $this->qr_web_path . '/' . $qr_filename);
            
            // Generate QR code with error checking
            try {
                if (!class_exists('QRcode')) {
                    throw new Exception("QRcode class not found. Make sure phpqrcode library is properly included.");
                }
                
                error_log("Generating QR code using QRcode::png()");
                QRcode::png($menu_url_with_params, $qr_path, QR_ECLEVEL_L, 10, 2);
                error_log("QRcode::png() completed");
                
                if (!file_exists($qr_path)) {
                    throw new Exception("QR code file was not created at: " . $qr_path);
                }
                error_log("QR code file created successfully");
                
            } catch (Exception $qr_error) {
                error_log("QR code generation error: " . $qr_error->getMessage());
                throw new Exception("QR code generation failed: " . $qr_error->getMessage());
            }
            
            chmod($qr_path, 0644);
            
            // Clean up old QR codes
            $get_old_qr_query = "SELECT image_path FROM qr_codes WHERE table_id = ? AND is_active = 1";
            $get_old_qr_stmt = $this->db->prepare($get_old_qr_query);
            $get_old_qr_stmt->execute([$table_id]);
            $old_qr_codes = $get_old_qr_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($old_qr_codes as $old_qr) {
                if (!empty($old_qr['image_path'])) {
                    $old_file_path = $this->qr_dir . DIRECTORY_SEPARATOR . $old_qr['image_path'];
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
            }
            
            // Deactivate old QR codes
            $deactivate_query = "UPDATE qr_codes SET is_active = 0 WHERE table_id = ?";
            $deactivate_stmt = $this->db->prepare($deactivate_query);
            $deactivate_stmt->execute([$table_id]);
            
            // Calculate expiry date (2 hours)
            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$this->token_expiry_hours} hours"));
            
            // Save new QR code
            $insert_query = "INSERT INTO qr_codes (table_id, token, image_path, created_at, expires_at, is_active) 
                            VALUES (?, ?, ?, NOW(), ?, 1)";
            $insert_stmt = $this->db->prepare($insert_query);
            if (!$insert_stmt->execute([$table_id, $token, $qr_filename, $expiry_date])) {
                throw new Exception("Failed to save QR code information to database");
            }
            
            if (!$within_transaction) {
                $this->db->commit();
            }
            
            error_log("QR code generation completed successfully");
            return [
                'success' => true,
                'message' => "QR code generated successfully for Table {$table_number}. Valid until: " . date('H:i', strtotime($expiry_date)),
                'qr_path' => $this->qr_web_path . '/' . $qr_filename,
                'full_url' => "http://" . $_SERVER['HTTP_HOST'] . $this->qr_web_path . '/' . $qr_filename,
                'expires_at' => $expiry_date,
                'debug_info' => [
                    'qr_dir' => $this->qr_dir,
                    'qr_web_path' => $this->qr_web_path,
                    'menu_url' => $this->menu_url,
                    'full_url' => "http://" . $_SERVER['HTTP_HOST'] . $this->qr_web_path . '/' . $qr_filename
                ]
            ];
            
        } catch (Exception $e) {
            error_log("QR code generation error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            if (!$within_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (isset($qr_path) && file_exists($qr_path)) {
                unlink($qr_path);
            }
            return [
                'success' => false,
                'message' => "Error generating QR code: " . $e->getMessage(),
                'debug_info' => [
                    'qr_dir' => $this->qr_dir,
                    'qr_web_path' => $this->qr_web_path,
                    'menu_url' => $this->menu_url
                ]
            ];
        }
    }

    public function addTable($table_number) {
        try {
            if (empty($table_number)) {
                throw new Exception("Table number is required");
            }
            
            // Start transaction and store the result
            $transaction_started = $this->db->beginTransaction();
            if (!$transaction_started) {
                throw new Exception("Could not start database transaction");
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
            
            // Generate QR code for new table, passing true to indicate we're within a transaction
            $result = $this->generateQRCode($table_id, $table_number, true);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => "Table added and QR code generated successfully"
            ];
            
        } catch (Exception $e) {
            // Only roll back if a transaction was successfully started
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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

    /**
     * Invalidate a QR code token after payment completion
     */
    public function invalidateTokenAfterPayment($token) {
        try {
            $this->db->beginTransaction();
            
            // Update the QR code to mark it as inactive
            $update_query = "UPDATE qr_codes SET is_active = 0 WHERE token = ? AND is_active = 1";
            $update_stmt = $this->db->prepare($update_query);
            
            if (!$update_stmt->execute([$token])) {
                throw new Exception("Failed to invalidate token");
            }
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => "Token invalidated successfully"
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return [
                'success' => false,
                'message' => "Error invalidating token: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check if a token is valid and not expired
     */
    public function isTokenValid($token) {
        try {
            $query = "SELECT 1 FROM qr_codes 
                     WHERE token = ? 
                     AND is_active = 1 
                     AND expires_at > NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Confirm payment and handle the complete order workflow
     * 1. Validate the current token
     * 2. Mark the current QR code as inactive
     * 3. Generate a new QR code for the next customer
     */
    public function confirmPayment($token) {
        try {
            $this->db->beginTransaction();
            
            // First, check if token is valid
            if (!$this->isTokenValid($token)) {
                throw new Exception("Invalid or expired token");
            }
            
            // Get table information from the token
            $query = "SELECT qc.table_id, t.table_number, qc.image_path 
                     FROM qr_codes qc 
                     JOIN tables t ON qc.table_id = t.id 
                     WHERE qc.token = ? AND qc.is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            $table_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$table_info) {
                throw new Exception("Table information not found");
            }
            
            // Delete the old QR code file
            if (!empty($table_info['image_path'])) {
                $old_file_path = $this->qr_dir . DIRECTORY_SEPARATOR . $table_info['image_path'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            
            // Deactivate the current QR code
            $deactivate_query = "UPDATE qr_codes SET 
                               is_active = 0,
                               payment_completed_at = NOW(),
                               payment_status = 'completed'
                               WHERE token = ?";
            $deactivate_stmt = $this->db->prepare($deactivate_query);
            if (!$deactivate_stmt->execute([$token])) {
                throw new Exception("Failed to deactivate current QR code");
            }
            
            // Generate new QR code for the next customer
            $result = $this->generateQRCode($table_info['table_id'], $table_info['table_number'], true);
            if (!$result['success']) {
                throw new Exception("Failed to generate new QR code: " . $result['message']);
            }
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => "Payment confirmed and order completed for Table {$table_info['table_number']}. New QR code generated for next customer.",
                'table_number' => $table_info['table_number'],
                'new_qr_info' => [
                    'path' => $result['qr_path'],
                    'expires_at' => $result['expires_at'],
                    'valid_until' => date('H:i', strtotime($result['expires_at']))
                ]
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return [
                'success' => false,
                'message' => "Error processing payment: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get active order information by token
     */
    public function getActiveOrderInfo($token) {
        try {
            $query = "SELECT t.table_number, 
                            qc.created_at as order_started_at,
                            qc.expires_at,
                            CASE 
                                WHEN qc.expires_at > NOW() THEN 'active'
                                ELSE 'expired'
                            END as status
                     FROM qr_codes qc 
                     JOIN tables t ON qc.table_id = t.id 
                     WHERE qc.token = ? AND qc.is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the full URL for a QR code image
     */
    public function getQRCodeUrl($image_path) {
        if (empty($image_path)) {
            return null;
        }
        return "http://" . $_SERVER['HTTP_HOST'] . $this->qr_web_path . '/' . $image_path;
    }
} 