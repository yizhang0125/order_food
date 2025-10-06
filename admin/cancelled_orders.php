<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);

// Cash rounding function - rounds to nearest 0.05 (5 cents)
if (!function_exists('customRound')) {
    function customRound($amount) {
        // Round to nearest 0.05 (5 cents) for cash transactions
        // Multiply by 20, round to nearest integer, then divide by 20
        return round($amount * 20) / 20;
    }
}

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if user has permission to view cancelled orders
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('view_orders', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Cancelled Orders') . '&type=warning');
    exit();
}

// Get date range from query parameters or default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get cancelled orders
$cancelled_orders = $orderModel->getCancelledOrders($start_date, $end_date);

// Set page title
$page_title = "Cancelled Orders";

// Start output buffering
ob_start();
?>

<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-times-circle"></i>
            Cancelled Orders
        </h1>
    </div>

    <div class="date-filter">
        <form class="date-inputs">
            <input type="date" class="date-input" id="start_date" name="start_date" 
                   value="<?php echo $start_date; ?>">
            <input type="date" class="date-input" id="end_date" name="end_date" 
                   value="<?php echo $end_date; ?>">
            <button type="submit" class="filter-btn">
                <i class="fas fa-filter"></i>
                Filter Orders
            </button>
        </form>
    </div>

    <div class="orders-container">
        <div class="table-responsive">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Table</th>
                        <th>Items</th>
                        <th>Special Instructions</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Cancelled At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cancelled_orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x mb-3 text-muted d-block"></i>
                            No cancelled orders found for the selected date range (<?php echo $start_date; ?> to <?php echo $end_date; ?>)
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $grouped_orders = [];
                        // Group orders by table number AND cancellation time
                        foreach ($cancelled_orders as $order) {
                            // Create a key using table number AND created_at timestamp (rounded to minutes)
                            $timestamp = strtotime($order['created_at']);
                            $timeKey = date('Y-m-d H:i', $timestamp); // Remove seconds for grouping
                            $key = $order['table_number'] . '_' . $timeKey;
                            
                            if (!isset($grouped_orders[$key])) {
                                $grouped_orders[$key] = [
                                    'id' => $order['id'],
                                    'table_number' => $order['table_number'],
                                    'items_list' => $order['items_list'],
                                    'item_count' => $order['item_count'],
                                    'special_instructions' => $order['special_instructions'],
                                    'total_amount' => $order['total_amount'],
                                    'created_at' => $order['created_at']
                                ];
                            }
                        }

                        // Sort by created_at date (newest first) and then by table number
                        uasort($grouped_orders, function($a, $b) {
                            $dateCompare = strtotime($b['created_at']) - strtotime($a['created_at']);
                            if ($dateCompare === 0) {
                                return $a['table_number'] - $b['table_number'];
                            }
                            return $dateCompare;
                        });

                        foreach ($grouped_orders as $order): 
                        ?>
                        <tr>
                            <td>
                                <span class="order-id">
                                    #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>
                                </span>
                            </td>
                            <td>Table <?php echo htmlspecialchars($order['table_number']); ?></td>
                            <td>
                                <div class="order-items">
                                    <span class="item-count"><?php echo $order['item_count']; ?> items</span>
                                    <div class="item-details small text-muted">
                                        <?php 
                                        if (!empty($order['items_list'])) {
                                            $items = explode(', ', $order['items_list']);
                                            $itemsList = [];
                                            foreach($items as $item) {
                                                $itemsList[] = preg_replace('/ - Note:.*$/', '', $item);
                                            }
                                            echo implode(', ', $itemsList);
                                        } else {
                                            echo '<span class="text-danger">No items found</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="special-instructions">
                                    <?php 
                                    if (!empty($order['special_instructions'])) {
                                        foreach($order['special_instructions'] as $instruction) {
                                            echo "<div class='instruction-item'><strong>" . htmlspecialchars($instruction['item']) . "</strong>: " . htmlspecialchars($instruction['instructions']) . "</div>";
                                        }
                                    } else {
                                        echo '<span class="text-muted">No special instructions</span>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <span class="order-amount">RM <?php echo number_format(customRound($order['total_amount']), 2); ?></span>
                            </td>
                            <td>
                                <span class="status-badge cancelled">
                                    <i class="fas fa-times-circle"></i>
                                    Cancelled
                                </span>
                            </td>
                            <td>
                                <span class="order-date">
                                    <?php 
                                    echo date('d M Y, h:i A', strtotime($order['created_at']));
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_order.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="total-orders">
                Total Cancelled Orders: <?php echo count($cancelled_orders); ?>
            </div>
            <a href="export_cancelled_orders.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="export-btn"
               target="_blank">
                <i class="fas fa-download"></i>
                Export to Excel
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add custom CSS
$extra_css = '
<style>
    :root {
        --primary: #4f46e5;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
    }

    .page-header {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-title i {
        color: var(--danger);
    }

    .date-filter {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .date-inputs {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .date-input {
        padding: 0.75rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        font-size: 0.95rem;
    }

    .filter-btn {
        padding: 0.75rem 1.5rem;
        background: var(--danger);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    }

    .orders-container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .orders-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .orders-table th {
        background: var(--gray-50);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--gray-600);
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--gray-200);
    }

    .orders-table td {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        color: var(--gray-700);
        font-size: 0.95rem;
    }

    .order-id {
        font-weight: 600;
        color: var(--danger);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .status-badge.cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .order-amount {
        font-weight: 600;
        color: var(--gray-800);
    }

    .order-date {
        color: var(--gray-500);
    }

    .reason-text {
        color: var(--gray-600);
        font-size: 0.875rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        max-width: 200px;
    }

    .table-footer {
        padding: 1rem 1.5rem;
        background: var(--gray-50);
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .total-orders {
        font-weight: 600;
        color: var(--gray-700);
    }

    .export-btn {
        padding: 0.75rem 1.5rem;
        background: var(--danger);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .export-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
        color: white;
    }

    .order-items {
        max-width: 300px;
    }

    .item-count {
        font-weight: 600;
        color: var(--gray-700);
        display: block;
        margin-bottom: 0.25rem;
    }

    .item-details {
        font-size: 0.85rem;
        color: var(--gray-500);
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    @media (max-width: 768px) {
        .date-inputs {
            flex-direction: column;
        }
        
        .date-input {
            width: 100%;
        }
        
        .orders-table {
            display: block;
            overflow-x: auto;
        }
    }
</style>';

// Add custom JavaScript
$extra_js = '
<script>
    // Date filter functionality using AJAX
    document.querySelector(".date-inputs").addEventListener("submit", function(e) {
        e.preventDefault();
        const startDate = document.getElementById("start_date").value;
        const endDate = document.getElementById("end_date").value;
        
        // Show loading state
        const filterBtn = document.querySelector(".filter-btn");
        const originalBtnText = filterBtn.innerHTML;
        filterBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Loading...`;
        filterBtn.disabled = true;

        // Make AJAX request
        fetch(`cancelled_orders.php?start_date=${startDate}&end_date=${endDate}&ajax=true`)
            .then(response => response.text())
            .then(html => {
                // Update only the table content
                document.querySelector(".orders-container").innerHTML = html;
                
                // Update URL without reloading
                const newUrl = `cancelled_orders.php?start_date=${startDate}&end_date=${endDate}`;
                window.history.pushState({ path: newUrl }, "", newUrl);
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading orders. Please try again.");
            })
            .finally(() => {
                // Restore button state
                filterBtn.innerHTML = originalBtnText;
                filterBtn.disabled = false;
            });
    });
</script>';

// If this is an AJAX request, only return the table content
if (isset($_GET["ajax"]) && $_GET["ajax"] === "true") {
    ?>
    <div class="table-responsive">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Table</th>
                    <th>Items</th>
                    <th>Special Instructions</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Cancelled At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cancelled_orders)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-inbox fa-2x mb-3 text-muted d-block"></i>
                        No cancelled orders found for the selected date range (<?php echo $start_date; ?> to <?php echo $end_date; ?>)
                    </td>
                </tr>
                <?php else: ?>
                    <?php 
                    $grouped_orders = [];
                    // Group orders by table number AND cancellation time
                    foreach ($cancelled_orders as $order) {
                        // Create a key using table number AND created_at timestamp (rounded to minutes)
                        $timestamp = strtotime($order['created_at']);
                        $timeKey = date('Y-m-d H:i', $timestamp); // Remove seconds for grouping
                        $key = $order['table_number'] . '_' . $timeKey;
                        
                        if (!isset($grouped_orders[$key])) {
                            $grouped_orders[$key] = [
                                'id' => $order['id'],
                                'table_number' => $order['table_number'],
                                'items_list' => $order['items_list'],
                                'item_count' => $order['item_count'],
                                'special_instructions' => $order['special_instructions'],
                                'total_amount' => $order['total_amount'],
                                'created_at' => $order['created_at']
                            ];
                        }
                    }

                    // Sort by created_at date (newest first) and then by table number
                    uasort($grouped_orders, function($a, $b) {
                        $dateCompare = strtotime($b['created_at']) - strtotime($a['created_at']);
                        if ($dateCompare === 0) {
                            return $a['table_number'] - $b['table_number'];
                        }
                        return $dateCompare;
                    });

                    foreach ($grouped_orders as $order): 
                    ?>
                    <tr>
                        <td>
                            <span class="order-id">
                                #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>
                            </span>
                        </td>
                        <td>Table <?php echo htmlspecialchars($order['table_number']); ?></td>
                        <td>
                            <div class="order-items">
                                <span class="item-count"><?php echo $order['item_count']; ?> items</span>
                                <div class="item-details small text-muted">
                                    <?php 
                                    if (!empty($order['items_list'])) {
                                        $items = explode(', ', $order['items_list']);
                                        $itemsList = [];
                                        foreach($items as $item) {
                                            $itemsList[] = preg_replace('/ - Note:.*$/', '', $item);
                                        }
                                        echo implode(', ', $itemsList);
                                    } else {
                                        echo '<span class="text-danger">No items found</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="special-instructions">
                                <?php 
                                if (!empty($order['special_instructions'])) {
                                    foreach($order['special_instructions'] as $instruction) {
                                        echo "<div class='instruction-item'><strong>" . htmlspecialchars($instruction['item']) . "</strong>: " . htmlspecialchars($instruction['instructions']) . "</div>";
                                    }
                                } else {
                                    echo '<span class="text-muted">No special instructions</span>';
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <span class="order-amount">RM <?php echo number_format(customRound($order['total_amount']), 2); ?></span>
                        </td>
                        <td>
                            <span class="status-badge cancelled">
                                <i class="fas fa-times-circle"></i>
                                Cancelled
                            </span>
                        </td>
                        <td>
                            <span class="order-date">
                                <?php 
                                echo date('d M Y, h:i A', strtotime($order['created_at']));
                                ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_order.php?id=<?php echo $order['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <div class="total-orders">
            Total Cancelled Orders: <?php echo count($cancelled_orders); ?>
        </div>
        <a href="export_cancelled_orders.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
           class="export-btn"
           target="_blank">
            <i class="fas fa-download"></i>
            Export to Excel
        </a>
    </div>
    <?php
    exit;
}

// Include the layout
include 'includes/layout.php';
?> 

// Include the layout
include 'includes/layout.php';
?> 
