<?php
require_once(__DIR__ . '/../../config/Database.php');
require_once(__DIR__ . '/../../classes/SystemSettings.php');

class PaymentController {
    private $db;
    private $systemSettings;
    
    public function __construct($database) {
        $this->db = $database;
        $this->systemSettings = new SystemSettings($database);
    }
    
    /**
     * Custom rounding function for payment counter
     */
    public function customRound($amount) {
        // Get the decimal part (last 2 digits)
        $decimal_part = fmod($amount * 100, 100);
        
        // Get the second decimal digit (last digit)
        $second_decimal = $decimal_part % 10;
        
        // Handle rounding rules based on second decimal digit
        if ($second_decimal >= 1 && $second_decimal <= 2) {
            // Round to .X0 (e.g., 8.41, 8.42 -> 8.40)
            $result = floor($amount * 10) / 10;
            return $result;
        } elseif ($second_decimal >= 3 && $second_decimal <= 4) {
            // Round to .X5 (e.g., 8.43, 8.44 -> 8.45)
            $result = floor($amount * 10) / 10 + 0.05;
            return $result;
        } elseif ($second_decimal >= 6 && $second_decimal <= 7) {
            // Round to .X5 (e.g., 8.46, 8.47 -> 8.45)
            $result = floor($amount * 10) / 10 + 0.05;
            return $result;
        } elseif ($second_decimal >= 8 && $second_decimal <= 9) {
            // Round to .X0 (next whole number) (e.g., 8.48, 8.49 -> 8.50)
            $result = floor($amount * 10) / 10 + 0.10;
            return $result;
        } else {
            // For 0 and 5, keep as is
            $result = round($amount, 2);
            return $result;
        }
    }
    
    /**
     * Process TNG Pay payment for multiple orders
     */
    public function processTNGPayment($order_ids, $total_amount, $cashier_name, $tng_reference = null) {
        try {
            $this->db->beginTransaction();
            
            $payment_id = null;
            
            // Process each order
            foreach ($order_ids as $order_id) {
                // Get individual order amount
                $order_amount_sql = "SELECT total_amount FROM orders WHERE id = ?";
                $order_amount_stmt = $this->db->prepare($order_amount_sql);
                $order_amount_stmt->execute([$order_id]);
                $order_amount = $order_amount_stmt->fetchColumn();
                
                // Insert into payments table with TNG Pay details
                $payment_sql = "INSERT INTO payments (order_id, amount, payment_status, payment_date, payment_method, tng_reference, processed_by_name) 
                               VALUES (?, ?, 'completed', CURRENT_TIMESTAMP, 'tng_pay', ?, ?)";
                $payment_stmt = $this->db->prepare($payment_sql);
                $payment_success = $payment_stmt->execute([$order_id, $order_amount, $tng_reference, $cashier_name]);
                
                // Get payment details for receipt
                if (!$payment_id) {
                    $payment_id = $this->db->lastInsertId();
                }
                
                // Update order status
                $update_sql = "UPDATE orders SET status = 'completed' WHERE id = ?";
                $update_stmt = $this->db->prepare($update_sql);
                $update_success = $update_stmt->execute([$order_id]);
                
                if (!$payment_success || !$update_success) {
                    throw new Exception("Failed to process TNG Pay payment for order #" . $order_id);
                }
                
                // Delete QR code if table_id exists
                $this->deleteQRCodeForOrder($order_id);
            }
            
            $this->db->commit();
            return $payment_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process cash payment for multiple orders
     */
    public function processPayment($order_ids, $total_amount, $cash_received, $cashier_name) {
        try {
            $this->db->beginTransaction();
            
            $change = $cash_received - $total_amount;
            $payment_id = null;
            
            // Process each order
            foreach ($order_ids as $order_id) {
                // Get individual order amount
                $order_amount_sql = "SELECT total_amount FROM orders WHERE id = ?";
                $order_amount_stmt = $this->db->prepare($order_amount_sql);
                $order_amount_stmt->execute([$order_id]);
                $order_amount = $order_amount_stmt->fetchColumn();
                
                // Insert into payments table with individual order amount
                $payment_sql = "INSERT INTO payments (order_id, amount, payment_status, payment_date, payment_method, cash_received, change_amount, processed_by_name) 
                               VALUES (?, ?, 'completed', CURRENT_TIMESTAMP, 'cash', ?, ?, ?)";
                $payment_stmt = $this->db->prepare($payment_sql);
                $payment_success = $payment_stmt->execute([$order_id, $order_amount, $cash_received, $change, $cashier_name]);
                
                // Get payment details for receipt
                if (!$payment_id) {
                    $payment_id = $this->db->lastInsertId();
                }
                
                // Update order status
                $update_sql = "UPDATE orders SET status = 'completed' WHERE id = ?";
                $update_stmt = $this->db->prepare($update_sql);
                $update_success = $update_stmt->execute([$order_id]);
                
                if (!$payment_success || !$update_success) {
                    throw new Exception("Failed to process payment for order #" . $order_id);
                }
                
                // Delete QR code if table_id exists
                $this->deleteQRCodeForOrder($order_id);
            }
            
            $this->db->commit();
            return $payment_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Delete QR code for completed order
     */
    private function deleteQRCodeForOrder($order_id) {
        try {
            // Get table ID for this order
            $table_query = "SELECT table_id FROM orders WHERE id = ?";
            $table_stmt = $this->db->prepare($table_query);
            $table_stmt->execute([$order_id]);
            $table_id = $table_stmt->fetchColumn();
            
            if ($table_id) {
                // First get the QR code image path before deleting the record
                $qr_path_query = "SELECT image_path FROM qr_codes WHERE table_id = ?";
                $qr_path_stmt = $this->db->prepare($qr_path_query);
                $qr_path_stmt->execute([$table_id]);
                $image_path = $qr_path_stmt->fetchColumn();
                
                // Delete from qr_codes table
                $delete_token_sql = "DELETE FROM qr_codes WHERE table_id = ?";
                $delete_token_stmt = $this->db->prepare($delete_token_sql);
                $delete_token_stmt->execute([$table_id]);
                
                // Delete the physical QR code image file
                if ($image_path) {
                    $this->deleteQRImageFile($image_path);
                }
            }
        } catch (Exception $e) {
            error_log("Warning: Could not delete QR code: " . $e->getMessage());
        }
    }
    
    /**
     * Delete QR code image file
     */
    private function deleteQRImageFile($image_path) {
        $possible_paths = [
            __DIR__ . '/../' . $image_path,
            $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($image_path, '/'),
            __DIR__ . '/../uploads/qrcodes/' . basename($image_path),
            $_SERVER['DOCUMENT_ROOT'] . '/uploads/qrcodes/' . basename($image_path)
        ];
        
        // Try to delete using different path variations
        $deleted = false;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                unlink($path);
                error_log("QR code image deleted: " . $path);
                $deleted = true;
                break;
            }
        }
        
        // Final attempt with just the filename
        if (!$deleted) {
            $filename = basename($image_path);
            $qr_directory = __DIR__ . '/../uploads/qrcodes/';
            if (file_exists($qr_directory . $filename)) {
                unlink($qr_directory . $filename);
                error_log("QR code image deleted using filename only: " . $qr_directory . $filename);
            }
        }
    }
    
    /**
     * Get orders waiting for payment
     */
    public function getOrdersWaitingForPayment($table_filter = null) {
        try {
            $query = "SELECT o.*, 
                      t.table_number,
                      (SELECT JSON_ARRAYAGG(
                          JSON_OBJECT(
                              'id', oi.id,
                              'name', m.name,
                              'price', m.price,
                              'quantity', oi.quantity,
                              'instructions', oi.special_instructions
                          )
                      ) 
                      FROM order_items oi 
                      JOIN menu_items m ON oi.menu_item_id = m.id 
                      WHERE oi.order_id = o.id) as items
                      FROM orders o
                      JOIN tables t ON o.table_id = t.id
                      LEFT JOIN payments p ON o.id = p.order_id
                      WHERE o.status = 'completed' 
                      AND (p.payment_status IS NULL OR p.payment_status != 'completed')";
            
            $params = [];
            if ($table_filter) {
                $query .= " AND t.table_number = ?";
                $params[] = $table_filter;
            }
            
            $query .= " ORDER BY t.table_number ASC, o.created_at ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group orders by table
            $tables_with_orders = [];
            foreach ($orders as $order) {
                $table_number = $order['table_number'];
                if (!isset($tables_with_orders[$table_number])) {
                    $tables_with_orders[$table_number] = [];
                }
                $tables_with_orders[$table_number][] = $order;
            }
            
            return $tables_with_orders;
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::getOrdersWaitingForPayment: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get available table numbers for dropdown
     */
    public function getAvailableTables() {
        try {
            $tables_sql = "SELECT DISTINCT t.table_number 
                           FROM tables t 
                           INNER JOIN orders o ON t.id = o.table_id 
                           WHERE o.status = 'completed' 
                           AND t.status = 'active'
                           AND NOT EXISTS (
                               SELECT 1 FROM payments p 
                               WHERE p.order_id = o.id
                           )
                           ORDER BY t.table_number";
            $tables_stmt = $this->db->prepare($tables_sql);
            $tables_stmt->execute();
            return $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error in PaymentController::getAvailableTables: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Prepare TNG Pay receipt data for printing
     */
    public function prepareTNGReceiptData($order_ids, $payment_id, $tng_reference = null) {
        try {
            $receipt_sql = "SELECT o.*, t.table_number,
                          GROUP_CONCAT(CONCAT(m.name, ':', oi.quantity, ':', m.price) SEPARATOR '||') as item_details
                          FROM orders o 
                          LEFT JOIN tables t ON o.table_id = t.id
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          LEFT JOIN menu_items m ON oi.menu_item_id = m.id
                          WHERE o.id IN (" . implode(',', array_fill(0, count($order_ids), '?')) . ")
                          GROUP BY o.id";
            $receipt_stmt = $this->db->prepare($receipt_sql);
            $receipt_stmt->execute($order_ids);
            $orders_details = $receipt_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process items and calculate totals correctly
            $items_array = [];
            $subtotal = 0;
            
            foreach ($orders_details as $order_detail) {
                $item_details = explode('||', $order_detail['item_details']);
                foreach ($item_details as $item) {
                    if (empty(trim($item))) continue;
                    list($name, $quantity, $price) = explode(':', $item);
                    $item_total = $quantity * $price;
                    $subtotal += $item_total;
                    
                    $items_array[] = [
                        'name' => $name,
                        'quantity' => (int)$quantity,
                        'price' => (float)$price,
                        'total' => $item_total
                    ];
                }
            }
            
            // Calculate tax using dynamic tax rate
            $tax_rate = $this->systemSettings->getTaxRate();
            $tax_amount = $subtotal * $tax_rate;
            $total_with_tax = $subtotal + $tax_amount;
            
            // Apply custom rounding to total
            $total_with_tax = $this->customRound($total_with_tax);
            
            return [
                'payment_id' => $payment_id,
                'order_ids' => $order_ids,
                'table_number' => $orders_details[0]['table_number'],
                'items' => $items_array,
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($tax_amount, 2),
                'total_amount' => round($total_with_tax, 2),
                'payment_method' => 'TNG Pay',
                'tng_reference' => $tng_reference,
                'payment_date' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::prepareTNGReceiptData: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Prepare cash receipt data for printing
     */
    public function prepareReceiptData($order_ids, $payment_id, $cash_received, $change) {
        try {
            $receipt_sql = "SELECT o.*, t.table_number,
                          GROUP_CONCAT(CONCAT(m.name, ':', oi.quantity, ':', m.price) SEPARATOR '||') as item_details
                          FROM orders o 
                          LEFT JOIN tables t ON o.table_id = t.id
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          LEFT JOIN menu_items m ON oi.menu_item_id = m.id
                          WHERE o.id IN (" . implode(',', array_fill(0, count($order_ids), '?')) . ")
                          GROUP BY o.id";
            $receipt_stmt = $this->db->prepare($receipt_sql);
            $receipt_stmt->execute($order_ids);
            $orders_details = $receipt_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process items and calculate totals correctly
            $items_array = [];
            $subtotal = 0;
            
            foreach ($orders_details as $order_detail) {
                $item_details = explode('||', $order_detail['item_details']);
                foreach ($item_details as $item) {
                    if (empty(trim($item))) continue;
                    list($name, $quantity, $price) = explode(':', $item);
                    $item_total = $quantity * $price;
                    $subtotal += $item_total;
                    
                    $items_array[] = [
                        'name' => $name,
                        'quantity' => (int)$quantity,
                        'price' => (float)$price,
                        'total' => $item_total
                    ];
                }
            }
            
            // Calculate tax using dynamic tax rate
            $tax_rate = $this->systemSettings->getTaxRate();
            $tax_amount = $subtotal * $tax_rate;
            $total_with_tax = $subtotal + $tax_amount;
            
            // Apply custom rounding to total
            $total_with_tax = $this->customRound($total_with_tax);
            
            return [
                'payment_id' => $payment_id,
                'order_ids' => $order_ids,
                'table_number' => $orders_details[0]['table_number'],
                'items' => $items_array,
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($tax_amount, 2),
                'total_amount' => round($total_with_tax, 2),
                'payment_method' => 'Cash',
                'cash_received' => round($cash_received, 2),
                'change_amount' => round($change, 2),
                'payment_date' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::prepareReceiptData: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Process payment and automatically trigger receipt printing
     */
    public function processPaymentWithAutoPrint($order_ids, $total_amount, $payment_method, $cashier_name, $cash_received = null, $tng_reference = null) {
        try {
            $payment_id = null;
            
            if ($payment_method === 'tng_pay') {
                $payment_id = $this->processTNGPayment($order_ids, $total_amount, $cashier_name, $tng_reference);
            } else {
                $payment_id = $this->processPayment($order_ids, $total_amount, $cash_received, $cashier_name);
            }
            
            // Automatically trigger receipt printing
            $this->autoPrintReceipt($payment_id);
            
            return $payment_id;
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::processPaymentWithAutoPrint: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Automatically trigger receipt printing
     */
    private function autoPrintReceipt($payment_id) {
        try {
            // Store payment ID in session for auto-printing
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['auto_print_payment_id'] = $payment_id;
            $_SESSION['auto_print_timestamp'] = time();
            
            // Log the auto-print trigger
            error_log("Auto-print triggered for payment ID: " . $payment_id);
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::autoPrintReceipt: " . $e->getMessage());
        }
    }
    
    /**
     * Check if auto-print is pending and return payment ID
     */
    public function getPendingAutoPrint() {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            if (isset($_SESSION['auto_print_payment_id']) && isset($_SESSION['auto_print_timestamp'])) {
                // Check if the auto-print request is not too old (within 30 seconds)
                if ((time() - $_SESSION['auto_print_timestamp']) < 30) {
                    $payment_id = $_SESSION['auto_print_payment_id'];
                    
                    // Clear the session data after retrieving
                    unset($_SESSION['auto_print_payment_id']);
                    unset($_SESSION['auto_print_timestamp']);
                    
                    return $payment_id;
                } else {
                    // Clear old auto-print data
                    unset($_SESSION['auto_print_payment_id']);
                    unset($_SESSION['auto_print_timestamp']);
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::getPendingAutoPrint: " . $e->getMessage());
            return null;
        }
    }
}
?>
