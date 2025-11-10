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
     * Cash rounding function - rounds to nearest 0.05 (5 cents)
     */
    public function customRound($amount) {
        // Round to nearest 0.05 (5 cents) for cash transactions
        // Multiply by 20, round to nearest integer, then divide by 20
        return round($amount * 20) / 20;
    }
    
    /**
     * Normalize and validate order IDs input.
     * Accepts array or comma-separated string. Returns array of unique ints.
     * Throws Exception if no valid IDs found.
     */
    private function normalizeOrderIds($order_ids) {
        if (is_null($order_ids)) {
            throw new Exception("No order IDs provided");
        }

        // If string like "1,2,3"
        if (!is_array($order_ids)) {
            if (is_string($order_ids)) {
                $order_ids = array_filter(array_map('trim', explode(',', $order_ids)), function($v){ return $v !== ''; });
            } else {
                throw new Exception("Invalid order IDs format");
            }
        }

        // Map to integers and filter zeros / non-numeric
        $order_ids = array_map(function($id){
            return intval($id);
        }, $order_ids);

        $order_ids = array_filter($order_ids, function($id){
            return $id > 0;
        });

        // Unique and reindex
        $order_ids = array_values(array_unique($order_ids));

        if (count($order_ids) === 0) {
            throw new Exception("No valid order IDs provided");
        }

        return $order_ids;
    }

    /**
     * Process TNG Pay payment for multiple orders
     */
    public function processTNGPayment($order_ids, $total_amount, $cashier_name, $tng_reference = null, $discount_amount = 0, $discount_type = null, $discount_reason = null) {
        try {
            // Normalize and validate order IDs
            $order_ids = $this->normalizeOrderIds($order_ids);

            $this->db->beginTransaction();
            
            $payment_id = null;
            $table_number = null;
            
            // Process each order
            foreach ($order_ids as $order_id) {
                // Get individual order amount and table number
                $order_amount_sql = "SELECT o.total_amount, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE o.id = ?";
                $order_amount_stmt = $this->db->prepare($order_amount_sql);
                $order_amount_stmt->execute([$order_id]);
                $order_data = $order_amount_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order_data) {
                    throw new Exception("Order #{$order_id} not found or already expired (QR/order invalid). Payment aborted.");
                }

                $order_amount = $order_data['total_amount'];
                $table_number = $order_data['table_number'];

                // use Malaysia datetime
                $current_datetime = $this->getMalaysiaNow();
                
                // Insert into payments table with TNG Pay details
                $payment_sql = "INSERT INTO payments (order_id, amount, payment_status, payment_date, payment_method, tng_reference, processed_by_name, discount_amount, discount_type, discount_reason) 
                               VALUES (?, ?, 'completed', ?, 'tng_pay', ?, ?, ?, ?, ?)";
                $payment_stmt = $this->db->prepare($payment_sql);
                $payment_success = $payment_stmt->execute([
                    $order_id,
                    $order_amount,
                    $current_datetime,      // payment_date (Malaysia)
                    $tng_reference,
                    $cashier_name,
                    $discount_amount,
                    $discount_type,
                    $discount_reason
                ]);
                
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
            
            // Trigger payment completion notification
            $this->triggerPaymentNotification($payment_id, 'tng_pay', $total_amount, $table_number, $cashier_name, $discount_amount, $discount_type, null, null, $tng_reference);
            
            return $payment_id;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process cash payment for multiple orders
     */
    public function processPayment($order_ids, $total_amount, $cash_received, $cashier_name, $discount_amount = 0, $discount_type = null, $discount_reason = null) {
        try {
            // Normalize and validate order IDs
            $order_ids = $this->normalizeOrderIds($order_ids);

            $this->db->beginTransaction();
            
            $change = $cash_received - $total_amount;
            $payment_id = null;
            $table_number = null;
            
            // Process each order
            foreach ($order_ids as $order_id) {
                // Get individual order amount and table number
                $order_amount_sql = "SELECT o.total_amount, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE o.id = ?";
                $order_amount_stmt = $this->db->prepare($order_amount_sql);
                $order_amount_stmt->execute([$order_id]);
                $order_data = $order_amount_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order_data) {
                    throw new Exception("Order #{$order_id} not found or already expired (QR/order invalid). Payment aborted.");
                }

                $order_amount = $order_data['total_amount'];
                $table_number = $order_data['table_number'];

                // use Malaysia datetime
                $current_datetime = $this->getMalaysiaNow();
                
                // Insert into payments table with individual order amount
                $payment_sql = "INSERT INTO payments (order_id, amount, payment_status, payment_date, payment_method, cash_received, change_amount, processed_by_name, discount_amount, discount_type, discount_reason) 
                               VALUES (?, ?, 'completed', ?, 'cash', ?, ?, ?, ?, ?, ?)";
                $payment_stmt = $this->db->prepare($payment_sql);
                $payment_success = $payment_stmt->execute([
                    $order_id,
                    $order_amount,
                    $current_datetime,   // payment_date (Malaysia)
                    $cash_received,
                    $change,
                    $cashier_name,
                    $discount_amount,
                    $discount_type,
                    $discount_reason
                ]);
                
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
            
            // Trigger payment completion notification
            $this->triggerPaymentNotification($payment_id, 'cash', $total_amount, $table_number, $cashier_name, $discount_amount, $discount_type, $cash_received, $change, null);
            
            return $payment_id;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
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
     * Get all tables with their status and orders
     */
    public function getAllTablesWithStatus() {
        try {
            // Get all active tables
            $tables_query = "SELECT t.id, t.table_number, t.status as table_status 
                            FROM tables t 
                            WHERE t.status = 'active' 
                            ORDER BY t.table_number ASC";
            $tables_stmt = $this->db->prepare($tables_query);
            $tables_stmt->execute();
            $all_tables = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tables_with_status = [];
            
            foreach ($all_tables as $table) {
                $table_number = $table['table_number'];
                $table_id = $table['id'];
                
                // Get current orders for this table
                $orders_query = "SELECT o.*, 
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
                                WHERE o.table_id = ? 
                                AND o.status IN ('pending', 'processing', 'completed')
                                ORDER BY o.created_at DESC";
                
                $orders_stmt = $this->db->prepare($orders_query);
                $orders_stmt->execute([$table_id]);
                $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Determine table status
                $table_status = 'empty';
                $pending_orders = [];
                $completed_orders = [];
                $total_amount = 0;
                
                foreach ($orders as $order) {
                    if ($order['status'] === 'completed') {
                        // Check if payment is pending
                        $payment_check = "SELECT COUNT(*) as payment_count FROM payments WHERE order_id = ? AND payment_status = 'completed'";
                        $payment_stmt = $this->db->prepare($payment_check);
                        $payment_stmt->execute([$order['id']]);
                        $has_payment = $payment_stmt->fetchColumn() > 0;
                        
                        if (!$has_payment) {
                            $completed_orders[] = $order;
                            $total_amount += $order['total_amount'];
                        }
                    } elseif (in_array($order['status'], ['pending', 'processing'])) {
                        $pending_orders[] = $order;
                    }
                }
                
                // Determine final status
                if (!empty($completed_orders)) {
                    $table_status = 'payment_pending';
                } elseif (!empty($pending_orders)) {
                    $table_status = 'occupied';
                }
                
                $tables_with_status[$table_number] = [
                    'table_id' => $table_id,
                    'table_number' => $table_number,
                    'status' => $table_status,
                    'pending_orders' => $pending_orders,
                    'completed_orders' => $completed_orders,
                    'total_amount' => $total_amount,
                    'last_order_time' => !empty($orders) ? $orders[0]['created_at'] : null
                ];
            }
            
            return $tables_with_status;
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::getAllTablesWithStatus: " . $e->getMessage());
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
            $order_ids = $this->normalizeOrderIds($order_ids);

            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $receipt_sql = "SELECT o.*, t.table_number,
                          GROUP_CONCAT(CONCAT(m.name, ':', oi.quantity, ':', m.price) SEPARATOR '||') as item_details
                          FROM orders o 
                          LEFT JOIN tables t ON o.table_id = t.id
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          LEFT JOIN menu_items m ON oi.menu_item_id = m.id
                          WHERE o.id IN ($placeholders)
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
                'payment_date' => $this->getMalaysiaNow() // use Malaysia datetime
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
            $order_ids = $this->normalizeOrderIds($order_ids);

            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $receipt_sql = "SELECT o.*, t.table_number,
                          GROUP_CONCAT(CONCAT(m.name, ':', oi.quantity, ':', m.price) SEPARATOR '||') as item_details
                          FROM orders o 
                          LEFT JOIN tables t ON o.table_id = t.id
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          LEFT JOIN menu_items m ON oi.menu_item_id = m.id
                          WHERE o.id IN ($placeholders)
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
                'payment_date' => $this->getMalaysiaNow() // use Malaysia datetime
            ];
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::prepareReceiptData: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Process payment and automatically trigger receipt printing
     */
    public function processPaymentWithAutoPrint($order_ids, $total_amount, $payment_method, $cashier_name, $cash_received = null, $tng_reference = null, $discount_amount = 0, $discount_type = null, $discount_reason = null) {
        try {
            $payment_id = null;
            
            if ($payment_method === 'tng_pay') {
                $payment_id = $this->processTNGPayment($order_ids, $total_amount, $cashier_name, $tng_reference, $discount_amount, $discount_type, $discount_reason);
            } else {
                $payment_id = $this->processPayment($order_ids, $total_amount, $cash_received, $cashier_name, $discount_amount, $discount_type, $discount_reason);
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
            $_SESSION['auto_print_datetime'] = $this->getMalaysiaNow(); // Malaysia datetime
            
            // Log the auto-print trigger
            error_log("Auto-print triggered for payment ID: " . $payment_id . " at " . $_SESSION['auto_print_datetime']);
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::autoPrintReceipt: " . $e->getMessage());
        }
    }
    
    /**
     * Trigger payment completion notification
     */
    private function triggerPaymentNotification($payment_id, $payment_method, $amount, $table_number, $cashier_name, $discount_amount = 0, $discount_type = null, $cash_received = null, $change_amount = null, $tng_reference = null) {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            // Store payment notification data in session
            $_SESSION['payment_notification'] = [
                'payment_id' => $payment_id,
                'type' => 'payment_completed',
                'amount' => $amount,
                'payment_method' => $payment_method,
                'table_number' => $table_number,
                'processed_by_name' => $cashier_name,
                'discount_amount' => $discount_amount,
                'discount_type' => $discount_type,
                'cash_received' => $cash_received,
                'change_amount' => $change_amount,
                'tng_reference' => $tng_reference,
                'created_at' => $this->getMalaysiaNow(), // Malaysia datetime
                'timestamp' => time()
            ];
            
            // Log the notification trigger
            error_log("Payment notification triggered for payment ID: " . $payment_id . " - Table: " . $table_number . " - Amount: " . $amount . " at " . $_SESSION['payment_notification']['created_at']);
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::triggerPaymentNotification: " . $e->getMessage());
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
    
    /**
     * Get pending payment notification
     */
    public function getPendingPaymentNotification() {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            if (isset($_SESSION['payment_notification']) && isset($_SESSION['payment_notification']['timestamp'])) {
                // Check if the notification is not too old (within 60 seconds)
                if ((time() - $_SESSION['payment_notification']['timestamp']) < 60) {
                    $notification = $_SESSION['payment_notification'];
                    
                    // Clear the session data after retrieving
                    unset($_SESSION['payment_notification']);
                    
                    return $notification;
                } else {
                    // Clear old notification data
                    unset($_SESSION['payment_notification']);
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::getPendingPaymentNotification: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check user permissions for payment counter access
     */
    public function checkPaymentCounterPermissions() {
        require_once(__DIR__ . '/../../classes/Auth.php');
        $auth = new Auth($this->db);
        
        if (!$auth->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }

        // Permission gate: allow admin or staff with manage_payments/all
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
            $staffPerms = isset($_SESSION['staff_permissions']) && is_array($_SESSION['staff_permissions'])
                ? $_SESSION['staff_permissions']
                : [];
            if (!(in_array('manage_payments', $staffPerms) || in_array('all', $staffPerms))) {
                header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Payment Counter') . '&type=warning');
                exit();
            }
        }
    }
    
    /**
     * Get cashier display name
     */
    public function getCashierName() {
        $isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
        return $isAdmin
            ? (isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin')
            : (isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : 'Staff');
    }
    
    /**
     * Get cashier position
     */
    public function getCashierPosition() {
        $isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
        return $isAdmin
            ? 'Administrator'
            : (isset($_SESSION['staff_position']) ? $_SESSION['staff_position'] : 'Staff');
    }
    
    /**
     * Get cashier info (name and position)
     */
    public function getCashierInfo() {
        return [
            'name' => $this->getCashierName(),
            'position' => $this->getCashierPosition()
        ];
    }
    
    /**
     * Process payment counter payment
     */
    public function processPaymentCounterPayment($order_ids, $total_amount, $payment_method, $cash_received = null, $tng_reference = null) {
        $cashierInfo = $this->getCashierInfo();
        $cashierName = $cashierInfo['name'];
        
        try {
            if ($payment_method === 'tng_pay') {
                // Process TNG Pay payment with auto-print
                $payment_id = $this->processPaymentWithAutoPrint($order_ids, $total_amount, 'tng_pay', $cashierName, null, $tng_reference);
            } else {
                // Process cash payment with auto-print
                $payment_id = $this->processPaymentWithAutoPrint($order_ids, $total_amount, 'cash', $cashierName, $cash_received, null);
            }
            
            // Redirect directly to print receipt page for auto-printing
            // Check if this is a merged bill and redirect accordingly
            if ($this->isMergedBill($order_ids)) {
                header('Location: print_receipt_merged.php?payment_id=' . $payment_id . '&auto_print=1');
            } else {
                header('Location: print_receipt.php?payment_id=' . $payment_id . '&auto_print=1');
            }
            exit();
        } catch (Exception $e) {
            return "Error processing payment: " . $e->getMessage();
        }
    }
    
    /**
     * Get filtered tables for payment counter
     */
    public function getFilteredTables($table_filter = null) {
        $all_tables = $this->getAllTablesWithStatus();
        
        if ($table_filter) {
            $tables_with_orders = array_filter($all_tables, function($table) use ($table_filter) {
                return $table['table_number'] == $table_filter;
            });
        } else {
            $tables_with_orders = $all_tables;
        }
        
        return [
            'all_tables' => $all_tables,
            'filtered_tables' => $tables_with_orders,
            'available_tables' => array_keys($all_tables)
        ];
    }
    
    /**
     * Render table cards for payment counter
     */
    public function renderTableCards($tables_with_orders) {
        $html = '';
        
        foreach ($tables_with_orders as $table_number => $table_data) {
            $html .= '<div class="table-card table-status-' . $table_data['status'] . '" onclick="window.location.href=\'table_bills.php?table=' . $table_number . '\'">';
            $html .= '<div class="table-header">';
            $html .= '<div class="table-number">';
            $html .= htmlspecialchars($table_number);
            $html .= '</div>';
            $html .= '<div class="table-status">';
            
            if ($table_data['status'] === 'empty') {
                $html .= 'Empty';
            } else {
                $html .= 'Occupied';
            }
            
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Render table summary for payment counter
     */
    public function renderTableSummary($all_tables) {
        $total_tables = count($all_tables);
        $occupied_count = count(array_filter($all_tables, fn($t) => $t['status'] !== 'empty'));
        $empty_count = count(array_filter($all_tables, fn($t) => $t['status'] === 'empty'));
        
        $html = '<div class="table-summary">';
        $html .= '<div class="table-count">';
        $html .= '<i class="fas fa-table"></i> Total Tables: ' . $total_tables;
        $html .= '<span class="ms-3">';
        $html .= '<span class="badge bg-danger">' . $occupied_count . ' Occupied</span>';
        $html .= '<span class="badge bg-success">' . $empty_count . ' Empty</span>';
        $html .= '</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Check if the order IDs represent a merged bill (multiple tables)
     */
    private function isMergedBill($order_ids) {
        try {
            if (empty($order_ids)) {
                return false;
            }
            
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $query = "SELECT COUNT(DISTINCT t.table_number) as table_count
                      FROM orders o 
                      JOIN tables t ON o.table_id = t.id 
                      WHERE o.id IN ($placeholders)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($order_ids);
            $table_count = $stmt->fetchColumn();
            
            return $table_count > 1;
            
        } catch (Exception $e) {
            error_log("Error in PaymentController::isMergedBill: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Render table dropdown options
     */
    public function renderTableDropdownOptions($available_tables, $selected_table = null) {
        $html = '<option value="">All Tables</option>';
        
        foreach ($available_tables as $table_num) {
            $selected = ($selected_table == $table_num) ? 'selected' : '';
            $html .= '<option value="' . $table_num . '" ' . $selected . '>';
            $html .= 'Table ' . $table_num;
            $html .= '</option>';
        }
        
        return $html;
    }
    
    // Add this helper to provide Malaysia datetime consistently
    private function getMalaysiaNow() {
        // Return current datetime in Asia/Kuala_Lumpur timezone formatted for MySQL DATETIME
        try {
            $tz = new DateTimeZone('Asia/Kuala_Lumpur');
            $dt = new DateTime('now', $tz);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Fallback to server time if timezone creation fails
            error_log("getMalaysiaNow error: " . $e->getMessage());
            return date('Y-m-d H:i:s');
        }
    }
}
?>
