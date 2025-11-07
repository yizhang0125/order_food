    <?php
    session_start();
    require_once(__DIR__ . '/../config/Database.php');
    require_once(__DIR__ . '/classes/TableBillsController.php');
    require_once(__DIR__ . '/../classes/SystemSettings.php');

    $database = new Database();
    $db = $database->getConnection();
    $controller = new TableBillsController($db);
    $systemSettings = new SystemSettings($db);

    // Check permissions
    $controller->checkPermissions();

    // Check discount permissions using database
    $user_type = $_SESSION['user_type'] ?? '';
    $can_apply_discounts = false;

    if ($user_type === 'admin') {
        $can_apply_discounts = true;
    } elseif ($user_type === 'staff' && isset($_SESSION['staff_id'])) {
        try {
            // Check if staff has manage_discounts permission
            $permission_query = "SELECT COUNT(*) as has_permission 
                            FROM staff_permissions sp 
                            INNER JOIN permissions p ON sp.permission_id = p.id 
                            WHERE sp.staff_id = ? AND (p.name = 'manage_discounts' OR p.name = 'all')";
            $permission_stmt = $db->prepare($permission_query);
            $permission_stmt->execute([$_SESSION['staff_id']]);
            $permission_result = $permission_stmt->fetch(PDO::FETCH_ASSOC);
            $can_apply_discounts = $permission_result['has_permission'] > 0;
        } catch (Exception $e) {
            error_log("Error checking discount permissions: " . $e->getMessage());
            $can_apply_discounts = false;
        }
    }

    // Check if discounts are enabled in system settings
    $discounts_enabled = $systemSettings->isDiscountEnabled();

    // Process payment if submitted
    $error_message = null;
    if (isset($_POST['process_payment'])) {
        $order_ids = explode(',', $_POST['order_ids']);
        $total_amount = $_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $cash_received = $_POST['cash_received'] ?? null;
        $tng_reference = $_POST['tng_reference'] ?? null;
        
        // Handle discount if applied
        $discount_amount = 0;
        $discount_type = null;
        $discount_reason = null;
        
        if (isset($_POST['discount_amount']) && !empty($_POST['discount_amount'])) {
            // Check if user has permission to apply discounts
            if (!$can_apply_discounts) {
                $error_message = "You do not have permission to apply discounts.";
            } elseif (!$discounts_enabled) {
                $error_message = "Discount system is currently disabled.";
            } else {
                $discount_amount = floatval($_POST['discount_amount']);
                $discount_type = $_POST['discount_type'] ?? 'custom';
                $discount_reason = $_POST['discount_reason'] ?? '';
                
                // Validate discount amount
                if ($discount_amount > 0 && $discount_amount <= $total_amount) {
                    $total_amount = $total_amount - $discount_amount;
                } else {
                    $error_message = "Invalid discount amount";
                }
            }
        }
        
        if (!$error_message) {
            $error_message = $controller->processPayment($order_ids, $total_amount, $payment_method, $cash_received, $tng_reference, $discount_amount, $discount_type, $discount_reason);
        }
    }

    // Check if this is a merged bill request
    $is_merged = isset($_GET['merge']) && $_GET['merge'] === 'true';
    $table_numbers = [];

    if ($is_merged && isset($_GET['tables'])) {
        // Handle merged bills - multiple tables
        $table_numbers = array_map('intval', explode(',', $_GET['tables']));
        $table_numbers = array_filter($table_numbers); // Remove empty values
        
        if (count($table_numbers) < 2) {
            header('Location: payment_counter.php?error=' . urlencode('At least 2 tables required for merge'));
            exit();
        }
        
        // Get merged table data
        $table_data = $controller->getMergedTableData($table_numbers);
        $page_title = "Merged Bills - Tables " . implode(', ', $table_numbers);
    } else {
        // Handle single table
        $table_number = isset($_GET['table']) ? (int)$_GET['table'] : null;
        $table_numbers = [$table_number];
        
        // Validate table number and get table data
        $table_data = $controller->validateTableNumber($table_number);
        $page_title = "Table " . $table_number . " - Bills";
    }

    // Get cashier info
    $cashierInfo = $controller->getCashierInfo();
    $cashierName = $cashierInfo['name'];
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $page_title; ?> - Restaurant Payment Counter</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="css/payment_counter.css" rel="stylesheet">
        <link href="css/table_bills.css" rel="stylesheet">
    </head>
    <body>

        <div class="payment-counter">
            <div class="restaurant-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>
                        <?php if ($is_merged): ?>
                            <i class="fas fa-layer-group"></i> Merged Bills - Tables <?php echo implode(', ', $table_numbers); ?>
                        <?php else: ?>
                            <i class="fas fa-table"></i> Table <?php echo $table_numbers[0]; ?> - Bills
                        <?php endif; ?>
                        <small class="ms-2" style="font-size: 1rem; font-weight: 400; opacity: 0.9;">
                            <?php if ($is_merged): ?>
                                <span class="badge bg-warning">Merged Bills</span>
                            <?php else: ?>
                                Status: 
                                <?php 
                                if ($table_data['status'] === 'empty') {
                                    echo '<span class="badge bg-success">Empty</span>';
                                } else {
                                    echo '<span class="badge bg-danger">Occupied</span>';
                                }
                                ?>
                            <?php endif; ?>
                            <br>
                            <span style="font-size: 0.9rem; margin-top: 0.5rem; display: block;">
                                <i class="fas fa-user"></i> Processed by: <?php echo htmlspecialchars($cashierName); ?>
                            </span>
                        </small>
                    </h1>
                    <a href="payment_counter.php" class="btn btn-exit">
                        <i class="fas fa-arrow-left me-2"></i>Back to Tables
                    </a>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <?php if ($table_data['status'] === 'empty'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> This table has no active orders or bills.
            </div>
            <?php else: ?>
            
            <div class="bills-container">
                <?php
                // Render pending orders
                echo $controller->renderPendingOrders($table_data['pending_orders']);
                
                // Render completed orders
                echo $controller->renderCompletedOrders($table_data['completed_orders']);
                
                // Render payment form if there are completed orders
                if (!empty($table_data['completed_orders'])) {
                    $totals = $controller->calculatePaymentTotals($table_data['completed_orders']);
                    echo $controller->renderPaymentForm($table_data['completed_orders'], $totals);
                }
                ?>
            </div>
            <?php endif; ?>
            
            <!-- Discount Options from Settings -->
            <?php if ($can_apply_discounts && $discounts_enabled): ?>
            <div class="discount-options-panel" id="discountPanel">
                <div class="discount-panel-header">
                    <h6><i class="fas fa-percentage"></i> Apply Discount</h6>
                    <button class="close-discount-panel" onclick="closeDiscountPanel()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="discount-options">
                    <button class="discount-option-btn" onclick="applyPredefinedDiscount('birthday', <?php echo $systemSettings->getBirthdayDiscountPercent(); ?>)">
                        <i class="fas fa-birthday-cake"></i>
                        <span>Birthday</span>
                        <small><?php echo $systemSettings->getBirthdayDiscountPercent(); ?>%</small>
                    </button>
                    <button class="discount-option-btn" onclick="applyPredefinedDiscount('staff', <?php echo $systemSettings->getStaffDiscountPercent(); ?>)">
                        <i class="fas fa-user-tie"></i>
                        <span>Staff</span>
                        <small><?php echo $systemSettings->getStaffDiscountPercent(); ?>%</small>
                    </button>
                    <button class="discount-option-btn" onclick="applyPredefinedDiscount('review', <?php echo $systemSettings->getReviewDiscountPercent(); ?>)">
                        <i class="fas fa-star"></i>
                        <span>Review</span>
                        <small><?php echo $systemSettings->getReviewDiscountPercent(); ?>%</small>
                    </button>
                    <button class="discount-option-btn" onclick="applyPredefinedDiscount('complaint', <?php echo $systemSettings->getComplaintDiscountPercent(); ?>)">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Complaint</span>
                        <small><?php echo $systemSettings->getComplaintDiscountPercent(); ?>%</small>
                    </button>
                </div>
            </div>
            
            <button class="floating-discount-btn" onclick="openDiscountPanel()" title="Apply Discount">
                <i class="fas fa-percentage"></i>
            </button>
            <?php endif; ?>
            
            <!-- Floating Number Pad Toggle Button -->
            <button class="floating-toggle-btn" onclick="toggleNumberPad()" title="Number Pad">
                <i class="fas fa-calculator"></i>
            </button>
            
            <!-- Floating Number Pad -->
            <div class="floating-number-pad" id="numberPad">
                <div class="number-pad-header">
                    <h6><i class="fas fa-calculator"></i> Number Pad</h6>
                    <button class="close-pad-btn" onclick="toggleNumberPad()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="number-pad">
                    <div class="number-pad-row">
                        <button class="number-btn" data-number="1" onclick="addNumber('1')">1</button>
                        <button class="number-btn" data-number="2" onclick="addNumber('2')">2</button>
                        <button class="number-btn" data-number="3" onclick="addNumber('3')">3</button>
                    </div>
                    <div class="number-pad-row">
                        <button class="number-btn" data-number="4" onclick="addNumber('4')">4</button>
                        <button class="number-btn" data-number="5" onclick="addNumber('5')">5</button>
                        <button class="number-btn" data-number="6" onclick="addNumber('6')">6</button>
                    </div>
                    <div class="number-pad-row">
                        <button class="number-btn" data-number="7" onclick="addNumber('7')">7</button>
                        <button class="number-btn" data-number="8" onclick="addNumber('8')">8</button>
                        <button class="number-btn" data-number="9" onclick="addNumber('9')">9</button>
                    </div>
                    <div class="number-pad-row">
                        <button class="number-btn" data-number="." onclick="addNumber('.')">.</button>
                        <button class="number-btn" data-number="0" onclick="addNumber('0')">0</button>
                        <button class="clear-btn" onclick="clearInput()">
                            <i class="fas fa-backspace"></i>
                        </button>
                    </div>
                </div>
            </div>
            
        </div>

        <script>
            // Initialize payment inputs on page load
            document.addEventListener('DOMContentLoaded', function() {
                // Set default to cash payment method
                togglePaymentInputs('cash');
                
                // Add event listener for cash input changes
                const cashInput = document.getElementById('cash-received-input');
                if (cashInput) {
                    cashInput.addEventListener('input', function() {
                        // Always get the current total from min attribute
                        const totalAmount = parseFloat(this.getAttribute('min'));
                        calculateChange(this, totalAmount);
                    });
                    
                    cashInput.addEventListener('keyup', function() {
                        // Always get the current total from min attribute
                        const totalAmount = parseFloat(this.getAttribute('min'));
                        calculateChange(this, totalAmount);
                    });
                }
            });

            function togglePaymentInputs(method) {
                const cashInputs = document.getElementById('cash-inputs');
                const tngInputs = document.getElementById('tng-inputs');
                const processBtn = document.getElementById('process-btn');
                
                if (method === 'cash') {
                    cashInputs.style.display = 'block';
                    tngInputs.style.display = 'none';
                    const cashInput = cashInputs.querySelector('input[name="cash_received"]');
                    if (cashInput && processBtn) {
                        cashInput.required = true;
                        const totalAmount = parseFloat(cashInput.getAttribute('min'));
                        const cashReceived = parseFloat(cashInput.value) || 0;
                        processBtn.disabled = cashReceived < totalAmount;
                        
                        if (cashReceived >= totalAmount) {
                            calculateChange(cashInput, totalAmount);
                        }
                    }
                } else if (method === 'tng_pay') {
                    cashInputs.style.display = 'none';
                    tngInputs.style.display = 'block';
                    const cashInput = cashInputs.querySelector('input[name="cash_received"]');
                    if (cashInput && processBtn) {
                        cashInput.required = false;
                        processBtn.disabled = false;
                    }
                }
                
                const numberPad = document.querySelector('.floating-number-pad');
                const toggleBtn = document.querySelector('.floating-toggle-btn');
                if (numberPad && toggleBtn) {
                    if (numberPad.style.display === 'none') {
                        toggleBtn.style.display = 'flex';
                    }
                }
                
                const decimalBtn = document.querySelector('button[data-number="."]');
                if (decimalBtn) {
                    decimalBtn.style.display = method === 'cash' ? 'flex' : 'none';
                }
            }

            function toggleNumberPad() {
                const numberPad = document.querySelector('.floating-number-pad');
                const toggleBtn = document.querySelector('.floating-toggle-btn');
                
                if (numberPad.style.display === 'none' || numberPad.style.display === '') {
                    numberPad.style.display = 'block';
                    toggleBtn.style.display = 'none';
                } else {
                    numberPad.style.display = 'none';
                    toggleBtn.style.display = 'flex';
                }
            }

            function addNumber(number) {
                const cashMethod = document.querySelector('input[name="payment_method"][value="cash"]');
                const tngMethod = document.querySelector('input[name="payment_method"][value="tng_pay"]');
                
                let targetInput;
                let currentValue;
                
                if (cashMethod && cashMethod.checked) {
                    targetInput = document.getElementById('cash-received-input');
                    currentValue = targetInput.value;
                    
                    if (number === '.') {
                        if (currentValue.includes('.')) return;
                        currentValue = currentValue === '' ? '0.' : currentValue + '.';
                    } else {
                        currentValue = (currentValue === '0' && number !== '.') ? number : currentValue + number;
                    }
                    
                    if (currentValue.includes('.')) {
                        const parts = currentValue.split('.');
                        if (parts[1] && parts[1].length > 2) {
                            parts[1] = parts[1].substring(0, 2);
                            currentValue = parts.join('.');
                        }
                    }
                    
                    targetInput.value = currentValue;
                    // Get current total from min attribute
                    const totalAmount = parseFloat(targetInput.getAttribute('min'));
                    calculateChange(targetInput, totalAmount);
                    
                } else if (tngMethod && tngMethod.checked) {
                    targetInput = document.querySelector('input[name="tng_reference"]');
                    currentValue = targetInput.value;
                    
                    if (number !== '.' && number !== 'C' && currentValue.length < 4) {
                        currentValue += number;
                    }
                    
                    targetInput.value = currentValue;
                }
            }

            function clearInput() {
                const cashMethod = document.querySelector('input[name="payment_method"][value="cash"]');
                const tngMethod = document.querySelector('input[name="payment_method"][value="tng_pay"]');
                
                if (cashMethod && cashMethod.checked) {
                    const cashInput = document.getElementById('cash-received-input');
                    cashInput.value = '';
                    // Get current total from min attribute
                    const totalAmount = parseFloat(cashInput.getAttribute('min'));
                    calculateChange(cashInput, totalAmount);
                } else if (tngMethod && tngMethod.checked) {
                    const tngInput = document.querySelector('input[name="tng_reference"]');
                    tngInput.value = '';
                }
            }

            function calculateChange(input, totalAmount) {
                const form = input.closest('form');
                const cashReceived = parseFloat(input.value) || 0;
                const changeDisplay = form.querySelector('.change-display');
                const changeAmount = changeDisplay.querySelector('.change-amount');
                const submitBtn = document.getElementById('process-btn');
                const EPSILON = 0.01;

                // CRITICAL: Always use min attribute as source of truth (updated when discount applied)
                const actualTotal = parseFloat(input.getAttribute('min'));

                if ((cashReceived + EPSILON) >= actualTotal && actualTotal > 0) {
                    const change = (cashReceived - actualTotal).toFixed(2);
                    changeAmount.textContent = change;
                    changeDisplay.style.display = 'block';
                    if (submitBtn) submitBtn.disabled = false;
                } else {
                    changeDisplay.style.display = 'none';
                    if (submitBtn) submitBtn.disabled = true;
                }
            }

            function validatePayment(form) {
                const paymentMethod = form.payment_method.value;
                
                if (paymentMethod === 'cash') {
                    const cashReceived = parseFloat(form.cash_received.value);
                    const totalAmount = parseFloat(form.amount.value);
                    return cashReceived >= totalAmount;
                } else if (paymentMethod === 'tng_pay') {
                    return true;
                }
                
                return false;
            }

            // Rounds to nearest 0.05 (5 sen)
            function cashRound(amount) {
                return (Math.round(amount * 20) / 20).toFixed(2);
            }

            // Discount functionality
            let currentDiscountAmount = 0;

            function openDiscountPanel() {
                document.getElementById('discountPanel').style.display = 'block';
            }

            function closeDiscountPanel() {
                document.getElementById('discountPanel').style.display = 'none';
            }

            function applyPredefinedDiscount(type, percentage) {
                const totalElement = document.querySelector('.payment-row.total span:last-child');
                if (!totalElement) {
                    showDiscountMessage('No payment total found', 'error');
                    return;
                }
                
                const totalText = totalElement.textContent;
                const totalAmount = parseFloat(totalText.replace(/[^\d.-]/g, '')) || 0;
                const discountAmount = (totalAmount * percentage) / 100;
                
                // Get max discount from PHP (replace with actual value)
                const maxDiscount = parseFloat(document.body.getAttribute('data-max-discount')) || 999999;
                if (discountAmount > maxDiscount) {
                    showDiscountMessage(`Discount cannot exceed ${maxDiscount}`, 'error');
                    return;
                }
                
                currentDiscountAmount = discountAmount;
                // Always round after discount
                const newTotal = parseFloat(cashRound(totalAmount - discountAmount));
                
                updatePaymentSummary(newTotal, discountAmount, type);
                addDiscountToForm(discountAmount, type);
                showRemoveDiscountButton();
                showDiscountMessage(`${type.charAt(0).toUpperCase() + type.slice(1)} discount applied: RM ${discountAmount.toFixed(2)}`, 'success');
                closeDiscountPanel();
            }

            function updatePaymentSummary(newTotal, discountAmount, discountType = null) {
                // Always round to nearest 0.05
                newTotal = parseFloat(cashRound(newTotal));
                // Update total display
                const totalElement = document.querySelector('.payment-row.total span:last-child');
                if (totalElement) {
                    totalElement.textContent = 'RM ' + newTotal.toFixed(2);
                }
                
                // Update hidden amount in form
                const amountInput = document.querySelector('input[name="amount"]');
                if (amountInput) {
                    amountInput.value = newTotal.toFixed(2);
                }
                
                // CRITICAL: Update cash input min attribute with discounted total
                const cashInput = document.getElementById('cash-received-input');
                if (cashInput) {
                    cashInput.setAttribute('min', newTotal.toFixed(2));
                    cashInput.value = '';
                    cashInput.focus();
                    // Trigger recalculation
                    calculateChange(cashInput, newTotal);
                }
                
                // Update discount row display
                let discountRow = document.querySelector('.payment-row.discount');
                if (discountAmount > 0) {
                    const originalTotal = newTotal + discountAmount;
                    const percentageApplied = ((discountAmount / originalTotal) * 100).toFixed(1);
                    
                    if (!discountRow) {
                        const paymentBreakdown = document.querySelector('.payment-breakdown');
                        discountRow = document.createElement('div');
                        discountRow.className = 'payment-row discount';
                        
                        const discountInfo = getDiscountInfo(discountType);
                        discountRow.innerHTML = `
                            <span><i class="${discountInfo.icon} text-warning"></i> ${discountInfo.name} Discount (${percentageApplied}%):</span>
                            <span class="text-danger">-RM ${discountAmount.toFixed(2)}</span>
                        `;
                        
                        const totalRow = document.querySelector('.payment-row.total');
                        paymentBreakdown.insertBefore(discountRow, totalRow);
                    } else {
                        const discountSpan = discountRow.querySelector('span:last-child');
                        discountSpan.textContent = `-RM ${discountAmount.toFixed(2)}`;
                        
                        const discountInfo = getDiscountInfo(discountType);
                        const labelSpan = discountRow.querySelector('span:first-child');
                        labelSpan.innerHTML = `<i class="${discountInfo.icon} text-warning"></i> ${discountInfo.name} Discount (${percentageApplied}%):`;
                    }
                } else if (discountRow) {
                    discountRow.remove();
                }
            }

            function getDiscountInfo(discountType) {
                const discountTypes = {
                    'birthday': { name: 'Birthday', icon: 'fas fa-birthday-cake' },
                    'staff': { name: 'Staff', icon: 'fas fa-user-tie' },
                    'review': { name: 'Review', icon: 'fas fa-star' },
                    'complaint': { name: 'Complaint', icon: 'fas fa-exclamation-triangle' }
                };
                
                return discountTypes[discountType] || { name: 'Discount', icon: 'fas fa-percentage' };
            }

            function addDiscountToForm(amount, type) {
                const existingAmountInput = document.querySelector('input[name="discount_amount"]');
                const existingTypeInput = document.querySelector('input[name="discount_type"]');
                
                if (existingAmountInput) existingAmountInput.remove();
                if (existingTypeInput) existingTypeInput.remove();

                const form = document.querySelector('form');
                if (form) {
                    const discountAmountInput = document.createElement('input');
                    discountAmountInput.type = 'hidden';
                    discountAmountInput.name = 'discount_amount';
                    discountAmountInput.value = amount.toFixed(2);
                    form.appendChild(discountAmountInput);

                    const discountTypeInput = document.createElement('input');
                    discountTypeInput.type = 'hidden';
                    discountTypeInput.name = 'discount_type';
                    discountTypeInput.value = type;
                    form.appendChild(discountTypeInput);
                }
            }

            function showDiscountMessage(message, type = 'success') {
                let messageElement = document.getElementById('discountMessage');
                if (!messageElement) {
                    messageElement = document.createElement('div');
                    messageElement.id = 'discountMessage';
                    messageElement.style.cssText = `
                        position: fixed;
                        top: 20px;
                        left: 50%;
                        transform: translateX(-50%);
                        padding: 1rem 2rem;
                        border-radius: 8px;
                        color: white;
                        font-weight: 600;
                        z-index: 10000;
                        display: none;
                    `;
                    document.body.appendChild(messageElement);
                }
                
                messageElement.textContent = message;
                messageElement.style.backgroundColor = type === 'error' ? '#dc2626' : '#10b981';
                messageElement.style.display = 'block';
                
                setTimeout(() => {
                    messageElement.style.display = 'none';
                }, 3000);
            }

            function showRemoveDiscountButton() {
                const discountActions = document.getElementById('discountActions');
                if (discountActions) {
                    discountActions.style.display = 'block';
                }
            }

            function hideRemoveDiscountButton() {
                const discountActions = document.getElementById('discountActions');
                if (discountActions) {
                    discountActions.style.display = 'none';
                }
            }

            function removeDiscount() {
                const currentTotal = parseFloat(document.querySelector('.payment-row.total span:last-child').textContent.replace(/[^\d.-]/g, ''));
                const originalTotal = currentTotal + currentDiscountAmount;
                
                currentDiscountAmount = 0;
                updatePaymentSummary(originalTotal, 0);
                
                const existingInputs = document.querySelectorAll('input[name="discount_amount"], input[name="discount_type"], input[name="discount_reason"]');
                existingInputs.forEach(input => input.remove());
                
                hideRemoveDiscountButton();
                showDiscountMessage('Discount removed successfully', 'success');
            }
        
        </script>


    </body>
    </html>
