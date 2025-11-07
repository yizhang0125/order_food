<?php
require_once(__DIR__ . '/../../config/Database.php');
require_once(__DIR__ . '/../../classes/Auth.php');
require_once(__DIR__ . '/../../classes/SystemSettings.php');
require_once(__DIR__ . '/PaymentController.php');

class TableBillsController {
    private $db;
    private $auth;
    private $systemSettings;
    private $paymentController;
    
    public function __construct($database) {
        $this->db = $database;
        $this->auth = new Auth($database);
        $this->systemSettings = new SystemSettings($database);
        $this->paymentController = new PaymentController($database);
    }
    
    /**
     * Check if user is logged in and has permission
     */
    public function checkPermissions() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }

        // Permission gate: allow admin or staff with manage_payments/all
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
            $staffPerms = isset($_SESSION['staff_permissions']) && is_array($_SESSION['staff_permissions'])
                ? $_SESSION['staff_permissions']
                : [];
            if (!(in_array('manage_payments', $staffPerms) || in_array('all', $staffPerms))) {
                header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Table Bills') . '&type=warning');
                exit();
            }
        }
    }
    
    /**
     * Check if user can apply discounts
     */
    public function canApplyDiscounts() {
        $user_type = $_SESSION['user_type'] ?? '';
        $staff_permissions = $_SESSION['staff_permissions'] ?? [];
        
        // Admin can always apply discounts
        if ($user_type === 'admin') {
            return true;
        }
        
        // Staff can apply discounts if they have the permission
        if ($user_type === 'staff') {
            return in_array('manage_discounts', $staff_permissions) || in_array('all', $staff_permissions);
        }
        
        return false;
    }
    
    /**
     * Get discount settings
     */
    public function getDiscountSettings() {
        return $this->systemSettings->getDiscountSettings();
    }
    
    /**
     * Calculate discount amount
     */
    public function calculateDiscount($subtotal, $discount_type, $discount_percent = null) {
        return $this->systemSettings->calculateDiscount($subtotal, $discount_type, $discount_percent);
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
     * Process payment
     */
    public function processPayment($order_ids, $total_amount, $payment_method, $cash_received = null, $tng_reference = null, $discount_amount = 0, $discount_type = null, $discount_reason = null) {
        $cashierInfo = $this->getCashierInfo();
        $cashierName = $cashierInfo['name'];
        
        // Floating point tolerance for cash payments
        if ($payment_method === 'cash' && $cash_received !== null) {
            if ($cash_received + 0.01 < $total_amount) {
                return "Cash received is less than the total amount due.";
            }
        }
        try {
            // Store discount information in session for receipt printing
            if ($discount_amount > 0) {
                $_SESSION['discount_info'] = [
                    'amount' => $discount_amount,
                    'type' => $discount_type,
                    'reason' => $discount_reason
                ];
            }
            
            if ($payment_method === 'tng_pay') {
                // Process TNG Pay payment with auto-print
                $payment_id = $this->paymentController->processPaymentWithAutoPrint($order_ids, $total_amount, 'tng_pay', $cashierName, null, $tng_reference, $discount_amount, $discount_type, $discount_reason);
            } else {
                // Process cash payment with auto-print
                $payment_id = $this->paymentController->processPaymentWithAutoPrint($order_ids, $total_amount, 'cash', $cashierName, $cash_received, null, $discount_amount, $discount_type, $discount_reason);
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
     * Get table data by table number
     */
    public function getTableData($table_number) {
        try {
            // Get all tables with status
            $all_tables = $this->paymentController->getAllTablesWithStatus();
            
            if (!isset($all_tables[$table_number])) {
                return null;
            }
            
            return $all_tables[$table_number];
            
        } catch (Exception $e) {
            error_log("Error in TableBillsController::getTableData: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate table number from URL
     */
    public function validateTableNumber($table_number) {
        if (!$table_number) {
            header('Location: payment_counter.php?error=' . urlencode('Invalid table number'));
            exit();
        }
        
        $table_data = $this->getTableData($table_number);
        if (!$table_data) {
            header('Location: payment_counter.php?error=' . urlencode('Table ' . $table_number . ' not found'));
            exit();
        }
        
        return $table_data;
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
            error_log("Error in TableBillsController::isMergedBill: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get merged table data for multiple tables
     */
    public function getMergedTableData($table_numbers) {
        try {
            // Get all tables with status
            $all_tables = $this->paymentController->getAllTablesWithStatus();
            
            $merged_data = [
                'table_numbers' => $table_numbers,
                'status' => 'merged',
                'pending_orders' => [],
                'completed_orders' => [],
                'total_amount' => 0,
                'last_order_time' => null
            ];
            
            // Combine data from all selected tables
            foreach ($table_numbers as $table_number) {
                if (!isset($all_tables[$table_number])) {
                    header('Location: payment_counter.php?error=' . urlencode('Table ' . $table_number . ' not found'));
                    exit();
                }
                
                $table_data = $all_tables[$table_number];
                
                // Merge pending orders
                if (!empty($table_data['pending_orders'])) {
                    foreach ($table_data['pending_orders'] as $order) {
                        $order['table_number'] = $table_number; // Add table number to order
                        $merged_data['pending_orders'][] = $order;
                    }
                }
                
                // Merge completed orders
                if (!empty($table_data['completed_orders'])) {
                    foreach ($table_data['completed_orders'] as $order) {
                        $order['table_number'] = $table_number; // Add table number to order
                        $merged_data['completed_orders'][] = $order;
                    }
                }
                
                // Add to total amount
                $merged_data['total_amount'] += $table_data['total_amount'];
                
                // Update last order time
                if ($table_data['last_order_time'] && 
                    (!$merged_data['last_order_time'] || 
                     strtotime($table_data['last_order_time']) > strtotime($merged_data['last_order_time']))) {
                    $merged_data['last_order_time'] = $table_data['last_order_time'];
                }
            }
            
            // Sort orders by creation time
            usort($merged_data['pending_orders'], function($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
            
            usort($merged_data['completed_orders'], function($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
            
            return $merged_data;
            
        } catch (Exception $e) {
            error_log("Error in TableBillsController::getMergedTableData: " . $e->getMessage());
            header('Location: payment_counter.php?error=' . urlencode('Error loading merged table data'));
            exit();
        }
    }
    
    /**
     * Calculate payment totals
     */
    public function calculatePaymentTotals($completed_orders) {
        $subtotal = 0;
        
        foreach ($completed_orders as $order) {
            $items = json_decode($order['items'], true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $item_total = $item['quantity'] * $item['price'];
                    $subtotal += $item_total;
                }
            }
        }
        
        $tax_rate = $this->systemSettings->getTaxRate();
        $service_tax_rate = $this->systemSettings->getServiceTaxRate();
        $tax_amount = $subtotal * $tax_rate;
        $service_tax_amount = $subtotal * $service_tax_rate;
        $total_with_tax = $subtotal + $tax_amount + $service_tax_amount;
        $total_with_tax = $this->paymentController->customRound($total_with_tax);
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($tax_amount, 2),
            'service_tax_amount' => round($service_tax_amount, 2),
            'total_with_tax' => $total_with_tax
        ];
    }
    
    /**
     * Render pending orders section
     */
    public function renderPendingOrders($pending_orders) {
        if (empty($pending_orders)) {
            return '';
        }
        
        $html = '<div class="bill-section mb-4">';
        $html .= '<h4 class="bill-section-title"><i class="fas fa-clock text-warning"></i> Orders In Progress</h4>';
        
        foreach ($pending_orders as $order) {
            $html .= '<div class="bill-card pending">';
            $html .= '<div class="bill-header">';
            $html .= '<div class="bill-info">';
            
            // Check if this is a merged bill (has table_number in order)
            if (isset($order['table_number'])) {
                $html .= '<span class="bill-id">Table ' . $order['table_number'] . ' - Order #' . str_pad($order['id'], 4, '0', STR_PAD_LEFT) . '</span>';
            } else {
                $html .= '<span class="bill-id">Order #' . str_pad($order['id'], 4, '0', STR_PAD_LEFT) . '</span>';
            }
            
            $html .= '<span class="bill-status badge bg-warning">' . ucfirst($order['status']) . '</span>';
            $html .= '</div>';
            $html .= '<div class="bill-time">' . date('M d, Y - h:i A', strtotime($order['created_at'])) . '</div>';
            $html .= '</div>';
            
            // Show items
            $items = json_decode($order['items'], true);
            if (is_array($items)) {
                $html .= '<div class="bill-items">';
                foreach ($items as $item) {
                    $html .= '<div class="bill-item">';
                    $html .= '<div class="item-info">';
                    $html .= '<span class="item-name">' . htmlspecialchars($item['name']) . '</span>';
                    if (!empty($item['instructions'])) {
                        $html .= '<small class="item-note">Note: ' . htmlspecialchars($item['instructions']) . '</small>';
                    }
                    $html .= '</div>';
                    $html .= '<div class="item-details">';
                    $html .= '<span class="item-qty">Qty: ' . $item['quantity'] . '</span>';
                    $html .= '<span class="item-price">' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($item['price'], 2) . '</span>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            
            $html .= '<div class="bill-total">';
            $html .= '<strong>Total: ' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($order['total_amount'], 2) . '</strong>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render completed orders section - Combined Bill View
     */
    public function renderCompletedOrders($completed_orders) {
        if (empty($completed_orders)) {
            return '';
        }
        
        $html = '<div class="bill-section">';
        $html .= '<h4 class="bill-section-title"><i class="fas fa-receipt text-success"></i> Combined Bill - Ready for Payment</h4>';
        
        // Combine all items from all orders
        $combined_items = [];
        $order_ids = [];
        $total_subtotal = 0;
        
        foreach ($completed_orders as $order) {
            $order_ids[] = $order['id'];
            $items = json_decode($order['items'], true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $item_key = $item['name'] . '|' . $item['price'] . '|' . ($item['instructions'] ?? '');
                    
                    if (isset($combined_items[$item_key])) {
                        // Item already exists, add quantity
                        $combined_items[$item_key]['quantity'] += $item['quantity'];
                    } else {
                        // New item
                        $combined_items[$item_key] = [
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'quantity' => $item['quantity'],
                            'instructions' => $item['instructions'] ?? ''
                        ];
                    }
                }
            }
        }
        
        // Display combined bill
        $html .= '<div class="bill-card combined">';
        $html .= '<div class="bill-header">';
        $html .= '<div class="bill-info">';
        
        // Check if this is a merged bill (has table_number in orders)
        $is_merged = !empty($completed_orders) && isset($completed_orders[0]['table_number']);
        
        if ($is_merged) {
            // Group orders by table number
            $orders_by_table = [];
            foreach ($completed_orders as $order) {
                $table_num = $order['table_number'];
                if (!isset($orders_by_table[$table_num])) {
                    $orders_by_table[$table_num] = [];
                }
                $orders_by_table[$table_num][] = $order['id'];
            }
            
            $table_info = [];
            foreach ($orders_by_table as $table_num => $table_order_ids) {
                $table_info[] = 'Table ' . $table_num . ': #' . implode(', #', array_map(function($id) { return str_pad($id, 4, '0', STR_PAD_LEFT); }, $table_order_ids));
            }
            $html .= '<span class="bill-id">' . implode(' | ', $table_info) . '</span>';
        } else {
            $html .= '<span class="bill-id">Orders: #' . implode(', #', array_map(function($id) { return str_pad($id, 4, '0', STR_PAD_LEFT); }, $order_ids)) . '</span>';
        }
        
        $html .= '<span class="bill-status badge bg-success">Ready for Payment</span>';
        $html .= '</div>';
        $html .= '<div class="bill-time">' . date('M d, Y - h:i A') . '</div>';
        $html .= '</div>';
        
        // Show combined items
        $html .= '<div class="bill-items">';
        $html .= '<div class="bill-item header">';
        $html .= '<div class="item-info"><strong>Item</strong></div>';
        $html .= '<span class="item-qty"><strong>Qty</strong></span>';
        $html .= '<span class="item-price"><strong>Price</strong></span>';
        $html .= '<span class="item-total"><strong>Total</strong></span>';
        $html .= '</div>';
        
        foreach ($combined_items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $total_subtotal += $item_total;
            
            $html .= '<div class="bill-item">';
            $html .= '<div class="item-info">';
            $html .= '<span class="item-name">' . htmlspecialchars($item['name']) . '</span>';
            if (!empty($item['instructions'])) {
                $html .= '<small class="item-note">Note: ' . htmlspecialchars($item['instructions']) . '</small>';
            }
            $html .= '</div>';
            $html .= '<span class="item-qty">' . $item['quantity'] . '</span>';
            $html .= '<span class="item-price">' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($item['price'], 2) . '</span>';
            $html .= '<span class="item-total">' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($item_total, 2) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render payment summary and form
     */
    public function renderPaymentForm($completed_orders, $totals) {
        if (empty($completed_orders)) {
            return '';
        }
        
        $html = '<div class="payment-summary">';
        $html .= '<h5 class="payment-title"><i class="fas fa-credit-card"></i> Payment Summary</h5>';
        $html .= '<div class="payment-breakdown">';
        $html .= '<div class="payment-row">';
        $html .= '<span>Subtotal:</span>';
        $html .= '<span>' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($totals['subtotal'], 2) . '</span>';
        $html .= '</div>';
        $html .= '<div class="payment-row">';
        $html .= '<span>' . $this->systemSettings->getTaxName() . ' (' . $this->systemSettings->getTaxRatePercent() . '%):</span>';
        $html .= '<span>' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($totals['tax_amount'], 2) . '</span>';
        $html .= '</div>';
        $html .= '<div class="payment-row">';
        $html .= '<span>' . $this->systemSettings->getServiceTaxName() . ' (' . $this->systemSettings->getServiceTaxRatePercent() . '%):</span>';
        $html .= '<span>' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($totals['service_tax_amount'], 2) . '</span>';
        $html .= '</div>';
        $html .= '<div class="payment-row total">';
        $html .= '<span><strong>Total Amount:</strong></span>';
        $html .= '<span><strong>' . $this->systemSettings->getCurrencySymbol() . ' ' . number_format($totals['total_with_tax'], 2) . '</strong></span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Add remove discount button (will be shown when discount is applied)
        $html .= '<div class="discount-actions mt-3" id="discountActions" style="display: none;">';
        $html .= '<button type="button" class="btn btn-warning btn-sm" onclick="removeDiscount()">';
        $html .= '<i class="fas fa-times"></i> Remove Discount';
        $html .= '</button>';
        $html .= '</div>';
        
        // Add payment form
        $html .= $this->renderPaymentFormFields($completed_orders, $totals['total_with_tax']);
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render payment form fields
     */
    private function renderPaymentFormFields($completed_orders, $total_with_tax) {
        $html = '<div class="payment-form-section mt-4">';
        $html .= '<h6 class="payment-form-title"><i class="fas fa-credit-card"></i> Process Payment</h6>';
        
        $html .= '<form method="POST" onsubmit="return validatePayment(this)" class="payment-form">';
        $html .= '<input type="hidden" name="order_ids" value="' . implode(',', array_column($completed_orders, 'id')) . '">';
        $html .= '<input type="hidden" name="amount" value="' . $total_with_tax . '">';
        
        // Payment Method Selection
        $html .= '<div class="payment-method-selection mb-3">';
        $html .= '<label class="payment-method-label">Payment Method:</label>';
        $html .= '<div class="payment-method-options">';
        $html .= '<label class="payment-option">';
        $html .= '<input type="radio" name="payment_method" value="cash" checked onchange="togglePaymentInputs(\'cash\')">';
        $html .= '<span class="payment-option-text">';
        $html .= '<i class="fas fa-money-bill-wave"></i> Cash';
        $html .= '</span>';
        $html .= '</label>';
        $html .= '<label class="payment-option">';
        $html .= '<input type="radio" name="payment_method" value="tng_pay" onchange="togglePaymentInputs(\'tng_pay\')">';
        $html .= '<span class="payment-option-text">';
        $html .= '<i class="fas fa-mobile-alt"></i> TNG Pay';
        $html .= '</span>';
        $html .= '</label>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Cash Payment Inputs
        $html .= '<div id="cash-inputs" class="payment-inputs mb-3">';
        $html .= '<div class="form-group">';
        $html .= '<label class="form-label">Cash Received:</label>';
        $html .= '<input type="number" name="cash_received" id="cash-received-input" class="form-control cash-input" step="0.01" min="' . $total_with_tax . '" placeholder="0.00" onkeyup="calculateChange(this, ' . $total_with_tax . ')" required>';
        $html .= '</div>';
        
        $html .= '<div class="change-display" style="display: none;">';
        $html .= 'Change: ' . $this->systemSettings->getCurrencySymbol() . ' <span class="change-amount">0.00</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Floating Number Pad
        $html .= $this->renderFloatingNumberPad();
        
        // TNG Pay Inputs
        $html .= '<div id="tng-inputs" class="payment-inputs mb-3" style="display: none;">';
        $html .= '<div class="form-group">';
        $html .= '<label class="form-label">TNG Reference (Optional):</label>';
        $html .= '<input type="number" name="tng_reference" class="form-control tng-input" placeholder="Enter 4-digit TNG reference" min="0" max="9999" maxlength="4" pattern="[0-9]{1,4}" oninput="this.value = this.value.replace(/[^0-9]/g, \'\').slice(0, 4)">';
        $html .= '</div>';
        $html .= '<div class="tng-info">';
        $html .= '<i class="fas fa-info-circle"></i> TNG Pay payment confirmed. Reference number is optional for tracking.';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<button type="submit" name="process_payment" class="btn btn-primary btn-lg w-100" id="process-btn" disabled>';
        $html .= '<i class="fas fa-check-circle me-2"></i>Process Payment & Print Receipt';
        $html .= '</button>';
        $html .= '</form>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render floating number pad
     */
    private function renderFloatingNumberPad() {
        $html = '<div class="floating-number-pad">';
        $html .= '<div class="number-pad-header">';
        $html .= '<h6><i class="fas fa-calculator"></i> Number Pad</h6>';
        $html .= '<button type="button" class="close-pad-btn" onclick="toggleNumberPad()">';
        $html .= '<i class="fas fa-times"></i>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="number-pad">';
        
        // Number pad rows
        $numbers = [
            ['1', '2', '3'],
            ['4', '5', '6'],
            ['7', '8', '9'],
            ['.', '0', 'C']
        ];
        
        foreach ($numbers as $row) {
            $html .= '<div class="number-pad-row">';
            foreach ($row as $number) {
                if ($number === 'C') {
                    $html .= '<button type="button" class="number-btn clear-btn" onclick="clearInput()" data-number="C">C</button>';
                } else {
                    $html .= '<button type="button" class="number-btn" onclick="addNumber(\'' . $number . '\')" data-number="' . $number . '">' . $number . '</button>';
                }
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        // Floating Toggle Button
        $html .= '<button type="button" class="floating-toggle-btn" onclick="toggleNumberPad()">';
        $html .= '<i class="fas fa-calculator"></i>';
        $html .= '</button>';
        
        return $html;
    }
}
?>
