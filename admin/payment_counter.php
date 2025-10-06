<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/PaymentController.php');

$database = new Database();
$db = $database->getConnection();
$paymentController = new PaymentController($db);

// Check permissions
$paymentController->checkPaymentCounterPermissions();

// Process payment if submitted
$error_message = null;
if (isset($_POST['process_payment'])) {
    $order_ids = explode(',', $_POST['order_ids']);
    $total_amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $cash_received = $_POST['cash_received'] ?? null;
    $tng_reference = $_POST['tng_reference'] ?? null;
    
    $error_message = $paymentController->processPaymentCounterPayment($order_ids, $total_amount, $payment_method, $cash_received, $tng_reference);
}

// Get table filter from URL
$table_filter = isset($_GET['table']) ? $_GET['table'] : null;

// Get filtered tables using controller
$table_data = $paymentController->getFilteredTables($table_filter);
$all_tables = $table_data['all_tables'];
$tables_with_orders = $table_data['filtered_tables'];
$available_tables = $table_data['available_tables'];

// Get cashier info
$cashierInfo = $paymentController->getCashierInfo();
$cashierName = $cashierInfo['name'];

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
    
    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="payment-counter">
        <div class="restaurant-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-cash-register"></i> Restaurant Payment Counter <small class="ms-2" style="font-size: 1rem; font-weight: 400; opacity: 0.9;">Payment by: <?php echo htmlspecialchars($cashierName); ?></small></h1>
                <div class="header-actions">
                    <button class="btn btn-merge" onclick="openMergeBillModal()" title="Merge Bills">
                        <i class="fas fa-layer-group me-2"></i>Merge Bill
                    </button>
                    <a href="dashboard.php" class="btn btn-exit">
                        <i class="fas fa-sign-out-alt me-2"></i>Exit
                    </a>
                </div>
            </div>
            
            <!-- Add search form -->
            <div class="search-section">
                <div class="search-form">
                    <div class="input-group">
                        <select id="tableSelect" class="form-select table-select">
                            <?php echo $paymentController->renderTableDropdownOptions($available_tables, $table_filter); ?>
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

        <!-- Simple table summary -->
        <?php echo $paymentController->renderTableSummary($all_tables); ?>
        
        <!-- Display all tables in a grid -->
        <div class="tables-grid">
            <?php echo $paymentController->renderTableCards($tables_with_orders); ?>
        </div>
        
        <!-- Floating Merge Bill Button -->
        <button class="floating-merge-btn" onclick="openMergeBillModal()" title="Merge Bills">
            <i class="fas fa-layer-group"></i>
        </button>
    </div>

    <!-- Merge Bill Modal -->
    <div class="modal fade" id="mergeBillModal" tabindex="-1" aria-labelledby="mergeBillModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mergeBillModalLabel">
                        <i class="fas fa-layer-group me-2"></i>Merge Bills
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Select multiple tables with pending payments to merge their bills into one payment.
                    </div>
                    
                    <div class="tables-selection">
                        <h6 class="mb-3">Available Tables with Pending Payments:</h6>
                        <div class="tables-grid-modal" id="tablesGridModal">
                            <!-- Tables will be loaded here via JavaScript -->
                        </div>
                    </div>
                    
                    <div class="selected-tables mt-4" id="selectedTables" style="display: none;">
                        <h6 class="mb-3">Selected Tables:</h6>
                        <div class="selected-list" id="selectedList">
                            <!-- Selected tables will be shown here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="mergeBillsBtn" onclick="processMergedBills()" disabled>
                        <i class="fas fa-layer-group me-2"></i>Merge & Process Payment
                    </button>
                </div>
            </div>
        </div>
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



    // Auto refresh every 30 seconds
    setInterval(function() {
        location.reload();
    }, 30000);
    
    // Merge Bill functionality
    let selectedTables = [];
    
    function openMergeBillModal() {
        // Reset selection
        selectedTables = [];
        
        // Load available tables with pending payments
        loadTablesForMerge();
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('mergeBillModal'));
        modal.show();
    }
    
    function loadTablesForMerge() {
        // Get all tables with pending payments from the current page data
        const tableCards = document.querySelectorAll('.table-card');
        const tablesGridModal = document.getElementById('tablesGridModal');
        tablesGridModal.innerHTML = '';
        
        let hasPendingTables = false;
        
        tableCards.forEach(card => {
            const tableNumber = card.querySelector('.table-number').textContent.trim();
            const status = card.classList.contains('table-status-payment_pending') || 
                          card.classList.contains('table-status-occupied');
            
            if (status) {
                hasPendingTables = true;
                
                const tableCard = document.createElement('div');
                tableCard.className = 'table-card-modal';
                tableCard.innerHTML = `
                    <div class="table-checkbox">
                        <input type="checkbox" id="table-${tableNumber}" value="${tableNumber}" onchange="toggleTableSelection(${tableNumber})">
                        <label for="table-${tableNumber}">
                            <div class="table-number">${tableNumber}</div>
                            <div class="table-status">Pending Payment</div>
                        </label>
                    </div>
                `;
                tablesGridModal.appendChild(tableCard);
            }
        });
        
        if (!hasPendingTables) {
            tablesGridModal.innerHTML = '<div class="alert alert-warning">No tables with pending payments found.</div>';
        }
    }
    
    function toggleTableSelection(tableNumber) {
        const checkbox = document.getElementById(`table-${tableNumber}`);
        const selectedTablesDiv = document.getElementById('selectedTables');
        const selectedList = document.getElementById('selectedList');
        const mergeBtn = document.getElementById('mergeBillsBtn');
        
        if (checkbox.checked) {
            if (!selectedTables.includes(tableNumber)) {
                selectedTables.push(tableNumber);
            }
        } else {
            selectedTables = selectedTables.filter(t => t !== tableNumber);
        }
        
        // Update selected tables display
        if (selectedTables.length > 0) {
            selectedTablesDiv.style.display = 'block';
            selectedList.innerHTML = selectedTables.map(tableNum => 
                `<span class="badge bg-primary me-2 mb-2">Table ${tableNum}</span>`
            ).join('');
            mergeBtn.disabled = false;
        } else {
            selectedTablesDiv.style.display = 'none';
            mergeBtn.disabled = true;
        }
    }
    
    function processMergedBills() {
        if (selectedTables.length < 2) {
            alert('Please select at least 2 tables to merge.');
            return;
        }
        
        // Create comma-separated list of table numbers
        const tableNumbers = selectedTables.join(',');
        
        // Redirect to a new page or modal for merged bill processing
        // For now, we'll redirect to table_bills.php with multiple tables
        window.location.href = `table_bills.php?merge=true&tables=${tableNumbers}`;
    }
    </script>
</body>
</html> 