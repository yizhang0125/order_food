<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');
require_once(__DIR__ . '/classes/PaymentController.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$systemSettings = new SystemSettings($db);
$paymentController = new PaymentController($db);

// Check if user is logged in
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

// Determine cashier display name
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
$cashierName = $isAdmin
    ? (isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin')
    : (isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : 'Staff');

// Process payment if submitted
if (isset($_POST['process_payment'])) {
    $order_ids = explode(',', $_POST['order_ids']);
    $total_amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    
    try {
        if ($payment_method === 'tng_pay') {
            // Process TNG Pay payment with auto-print
            $tng_reference = $_POST['tng_reference'] ?? null;
            $payment_id = $paymentController->processPaymentWithAutoPrint($order_ids, $total_amount, 'tng_pay', $cashierName, null, $tng_reference);
        } else {
            // Process cash payment with auto-print
            $cash_received = $_POST['cash_received'];
            $payment_id = $paymentController->processPaymentWithAutoPrint($order_ids, $total_amount, 'cash', $cashierName, $cash_received);
        }
        
        // Redirect directly to print receipt page for auto-printing
        header('Location: print_receipt.php?payment_id=' . $payment_id . '&auto_print=1');
        exit();
    } catch (Exception $e) {
        $error_message = "Error processing payment: " . $e->getMessage();
        error_log($error_message);
    }
}

// Get table filter from URL
$table_filter = isset($_GET['table']) ? $_GET['table'] : null;

// Get orders waiting for payment using controller
$tables_with_orders = $paymentController->getOrdersWaitingForPayment($table_filter);

// Get available tables using controller
$available_tables = $paymentController->getAvailableTables();

$page_title = "Payment Counter";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Payment Counter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/payment_counter.css" rel="stylesheet">
</head>
<body>
    <div class="payment-counter">
        <div class="restaurant-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-cash-register"></i> Restaurant Payment Counter <small class="ms-2" style="font-size: 1rem; font-weight: 400; opacity: 0.9;">Payment by: <?php echo htmlspecialchars($cashierName); ?></small></h1>
                <a href="dashboard.php" class="btn btn-exit">
                    <i class="fas fa-sign-out-alt me-2"></i>Exit
                </a>
            </div>
            
            <!-- Add search form -->
            <div class="search-section">
                <div class="search-form">
                    <div class="input-group">
                        <select id="tableSelect" class="form-select table-select">
                            <option value="">All Tables</option>
                            <?php foreach ($available_tables as $table_num): ?>
                            <option value="<?php echo $table_num; ?>" <?php echo $table_filter == $table_num ? 'selected' : ''; ?>>
                                Table <?php echo $table_num; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="searchBtn" class="btn search-btn">
                            <i class="fas fa-search"></i> Find Table
                        </button>
                        <button type="button" id="clearBtn" class="btn clear-btn" style="display: <?php echo $table_filter ? 'inline-flex' : 'none'; ?>">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (empty($tables_with_orders)): ?>
        <div class="alert alert-info">
            <?php if ($table_filter): ?>
                <i class="fas fa-info-circle"></i> Table <?php echo $table_filter; ?> has no pending payments.
            <?php else: ?>
                <i class="fas fa-info-circle"></i> No tables waiting for payment.
            <?php endif; ?>
        </div>
        <?php else: ?>
            <!-- Display table count summary -->
            <div class="table-summary">
                <div class="table-count">
                    <i class="fas fa-table"></i> Tables Waiting for Payment: <?php echo count($tables_with_orders); ?>
                </div>
                <?php if ($table_filter): ?>
                <a href="payment_counter.php" class="btn btn-light">
                    <i class="fas fa-list"></i> Show All Tables
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Display all tables in a grid -->
            <div class="tables-grid">
                <?php foreach ($tables_with_orders as $table_number => $table_orders): ?>
                <div class="table-card">
                    <div class="table-header">
                        <div class="table-number">
                            <i class="fas fa-table"></i> Table <?php echo htmlspecialchars($table_number); ?>
                        </div>
                        <div class="table-status">
                            <i class="fas fa-clock"></i> Waiting for Payment
                        </div>
                    </div>

                    <div class="order-details">
                        <div class="order-time">
                            <div><i class="fas fa-hourglass-start"></i> First Order: <?php echo date('h:i A', strtotime($table_orders[0]['created_at'])); ?></div>
                            <div><i class="fas fa-receipt"></i> Orders: <?php echo count($table_orders); ?></div>
                        </div>

                        <div class="items-grid">
                            <div class="item-card header">
                                <div class="item-name">Item</div>
                                <div class="item-quantity">Qty</div>
                                <div class="item-price">Price</div>
                                <div class="item-total">Total</div>
                            </div>
                            <?php 
                            $subtotal = 0;
                            foreach ($table_orders as $order):
                                $items = json_decode($order['items'], true);
                                foreach ($items as $item): 
                                    $item_total = $item['quantity'] * $item['price'];
                                    $subtotal += $item_total;
                            ?>
                            <div class="item-card">
                                <div class="item-name">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                    <?php if (!empty($item['instructions'])): ?>
                                        <br><small class="text-muted">Note: <?php echo htmlspecialchars($item['instructions']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="item-quantity"><?php echo $item['quantity']; ?></div>
                                <div class="item-price"><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($item['price'], 2); ?></div>
                                <div class="item-total"><?php echo $systemSettings->getCurrencySymbol(); ?> <?php echo number_format($item_total, 2); ?></div>
                            </div>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                        </div>
                    </div>

                    <div class="payment-section">
                        <?php
                        // Calculate tax and total using dynamic settings
                        $tax_rate = $systemSettings->getTaxRate();
                        $tax_amount = $subtotal * $tax_rate;
                        $total_with_tax = $subtotal + $tax_amount;
                        $tax_name = $systemSettings->getTaxName();
                        $tax_percent = $systemSettings->getTaxRatePercent();
                        $currency_symbol = $systemSettings->getCurrencySymbol();
                        
                        // Apply custom rounding to total using PaymentController
                        $original_total = $total_with_tax;
                        error_log("Before custom rounding: " . $total_with_tax);
                        $total_with_tax = $paymentController->customRound($total_with_tax);
                        error_log("After custom rounding: " . $total_with_tax);
                        
                        // Debug: Show rounding info on page
                        echo "<!-- DEBUG: Original: " . $original_total . ", Rounded: " . $total_with_tax . " -->";
                        ?>
                        <div class="amount-breakdown">
                            <div class="amount-row">
                                <span>Subtotal:</span>
                                <span><?php echo $currency_symbol; ?> <?php echo number_format(round($subtotal, 2), 2); ?></span>
                            </div>
                            <div class="amount-row">
                                <span><?php echo $tax_name; ?> (<?php echo $tax_percent; ?>%):</span>
                                <span><?php echo $currency_symbol; ?> <?php echo number_format(round($tax_amount, 2), 2); ?></span>
                            </div>
                            <div class="total-amount">
                            Total: <?php echo $currency_symbol; ?> <?php echo number_format($total_with_tax, 2); ?>
                            <!-- <br><small style="color: red;">DEBUG: Original=<?php echo $original_total; ?>, Rounded=<?php echo $total_with_tax; ?></small> -->
                            </div>
                        </div>

                        <form method="POST" onsubmit="return validatePayment(this)" class="payment-form">
                            <input type="hidden" name="order_ids" value="<?php 
                                // Get all order IDs for this table
                                $table_order_ids = array_column($table_orders, 'id');
                                echo implode(',', $table_order_ids); 
                            ?>">
                            <input type="hidden" name="amount" value="<?php echo $total_with_tax; ?>">
                            
                            <!-- Payment Method Selection -->
                            <div class="payment-method-selection">
                                <label class="payment-method-label">Payment Method:</label>
                                <div class="payment-method-options">
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="cash" checked onchange="togglePaymentInputs('cash')">
                                        <span class="payment-option-text">
                                            <i class="fas fa-money-bill-wave"></i> Cash
                                        </span>
                                    </label>
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="tng_pay" onchange="togglePaymentInputs('tng_pay')">
                                        <span class="payment-option-text">
                                            <i class="fas fa-mobile-alt"></i> TNG Pay
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Cash Payment Inputs -->
                            <div id="cash-inputs" class="payment-inputs">
                            <div class="form-group">
                                <input type="number" 
                                       name="cash_received" 
                                       class="cash-input" 
                                       step="0.01" 
                                       min="<?php echo $total_with_tax; ?>"
                                       placeholder="Enter cash amount"
                                       onkeyup="calculateChange(this, <?php echo $total_with_tax; ?>)"
                                       required>
                            </div>
                            
                            <div class="change-display" style="display: none;">
                                Change: <?php echo $systemSettings->getCurrencySymbol(); ?> <span class="change-amount">0.00</span>
                                </div>
                            </div>
                            
                            <!-- TNG Pay Inputs -->
                            <div id="tng-inputs" class="payment-inputs" style="display: none;">
                                <div class="form-group">
                                    <input type="number" 
                                           name="tng_reference" 
                                           class="tng-input" 
                                           placeholder="Enter 4-digit TNG reference (optional)"
                                           min="0"
                                           max="9999"
                                           maxlength="4"
                                           pattern="[0-9]{1,4}"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)">
                                </div>
                                <div class="tng-info">
                                    <i class="fas fa-info-circle"></i> 
                                    TNG Pay payment confirmed. Reference number is optional for tracking.
                                </div>
                            </div>

                            <button type="submit" name="process_payment" class="process-btn" id="process-btn" disabled>
                                <i class="fas fa-check-circle"></i> Process Payment & Print Receipt
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Add this to your existing script
    document.addEventListener('DOMContentLoaded', function() {
        const tableSelect = document.getElementById('tableSelect');
        const searchBtn = document.getElementById('searchBtn');
        const clearBtn = document.getElementById('clearBtn');
        
        // Search button click handler
        searchBtn.addEventListener('click', function() {
            const selectedTable = tableSelect.value;
            if (selectedTable) {
                window.location.href = 'payment_counter.php?table=' + selectedTable;
            } else {
                window.location.href = 'payment_counter.php';
            }
        });
        
        // Clear button click handler
        clearBtn.addEventListener('click', function() {
            window.location.href = 'payment_counter.php';
        });
        
        // Also allow searching by pressing Enter on the select
        tableSelect.addEventListener('keyup', function(event) {
            if (event.key === 'Enter') {
                searchBtn.click();
            }
        });
        
        // Show/hide table cards based on selection without page reload
        tableSelect.addEventListener('change', function() {
            const selectedTable = this.value;
            const tableCards = document.querySelectorAll('.table-card');
            
            if (selectedTable === '') {
                // Show all tables
                tableCards.forEach(card => {
                    card.style.display = 'flex';
                });
                clearBtn.style.display = 'none';
            } else {
                // Show only the selected table
                tableCards.forEach(card => {
                    const tableNumber = card.querySelector('.table-number').textContent.trim().replace('Table ', '');
                    if (tableNumber === selectedTable) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
                clearBtn.style.display = 'inline-flex';
            }
        });
    });

    function togglePaymentInputs(method) {
        const cashInputs = document.getElementById('cash-inputs');
        const tngInputs = document.getElementById('tng-inputs');
        const processBtn = document.getElementById('process-btn');
        
        if (method === 'cash') {
            cashInputs.style.display = 'block';
            tngInputs.style.display = 'none';
            // Re-enable cash validation
            const cashInput = cashInputs.querySelector('input[name="cash_received"]');
            if (cashInput) {
                cashInput.required = true;
                processBtn.disabled = true;
            }
        } else if (method === 'tng_pay') {
            cashInputs.style.display = 'none';
            tngInputs.style.display = 'block';
            // Disable cash validation and enable TNG Pay
            const cashInput = cashInputs.querySelector('input[name="cash_received"]');
            if (cashInput) {
                cashInput.required = false;
            }
            processBtn.disabled = false;
        }
    }

    function calculateChange(input, totalAmount) {
        const form = input.closest('form');
        const cashReceived = parseFloat(input.value) || 0;
        const changeDisplay = form.querySelector('.change-display');
        const changeAmount = changeDisplay.querySelector('.change-amount');
        const submitBtn = form.querySelector('.process-btn');
        
        if (cashReceived >= totalAmount) {
            const change = (cashReceived - totalAmount).toFixed(2);
            changeAmount.textContent = change;
            changeDisplay.style.display = 'block';
            submitBtn.disabled = false;
        } else {
            changeDisplay.style.display = 'none';
            submitBtn.disabled = true;
        }
    }

    function validatePayment(form) {
        const paymentMethod = form.payment_method.value;
        
        if (paymentMethod === 'cash') {
        const cashReceived = parseFloat(form.cash_received.value);
        const totalAmount = parseFloat(form.amount.value);
        return cashReceived >= totalAmount;
        } else if (paymentMethod === 'tng_pay') {
            // TNG Pay is always valid (payment already confirmed)
            return true;
        }
        
        return false;
    }

    // Auto refresh every 30 seconds
    setInterval(function() {
        location.reload();
    }, 30000);
    </script>
</body>
</html> 