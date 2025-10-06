<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Table.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$tableModel = new Table($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = "Table Management";
$message = '';
$message_type = '';

// Handle Add Table
if (isset($_POST['add_table'])) {
    $table_number = trim($_POST['table_number']);
    
    try {
        if (empty($table_number)) {
            throw new Exception("Table number is required");
        }
        
        // Check if table number already exists
        $check_query = "SELECT id FROM tables WHERE table_number = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$table_number]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception("Table number already exists");
        }
        
        // Add new table
        $insert_query = "INSERT INTO tables (table_number, status) VALUES (?, 'active')";
        $insert_stmt = $db->prepare($insert_query);
        
        if ($insert_stmt->execute([$table_number])) {
            $message = "Table added successfully";
            $message_type = "success";
        } else {
            throw new Exception("Failed to add table");
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// Handle Edit Table
if (isset($_POST['edit_table'])) {
    $table_id = $_POST['table_id'];
    $new_table_number = trim($_POST['new_table_number']);
    $new_status = $_POST['new_status'];
    
    try {
        if (empty($new_table_number)) {
            throw new Exception("Table number is required");
        }
        
        // Check if new table number already exists
        $check_query = "SELECT id FROM tables WHERE table_number = ? AND id != ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$new_table_number, $table_id]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception("Table number already exists");
        }
        
        // Update table
        $update_query = "UPDATE tables SET table_number = ?, status = ? WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$new_table_number, $new_status, $table_id])) {
            $message = "Table updated successfully";
            $message_type = "success";
        } else {
            throw new Exception("Failed to update table");
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// Handle Delete Table
if (isset($_POST['delete_table'])) {
    $table_id = $_POST['table_id'];
    
    try {
        // Check if table has any orders
        $check_orders_query = "SELECT COUNT(*) as order_count FROM orders WHERE table_id = ?";
        $check_stmt = $db->prepare($check_orders_query);
        $check_stmt->execute([$table_id]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['order_count'] > 0) {
            // Table has orders - perform soft delete
            $update_query = "UPDATE tables SET status = 'inactive' WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$table_id]);
            
            $message = "Table has order history. The table has been deactivated.";
            $message_type = "info";
        } else {
            // No orders - safe to delete
            $delete_query = "DELETE FROM tables WHERE id = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->execute([$table_id]);
            
            $message = "Table deleted successfully";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// Handle Toggle Table Occupied Status
if (isset($_POST['toggle_occupied'])) {
    $table_id = $_POST['table_id'];
    $action = $_POST['action']; // 'occupy' or 'free'
    
    try {
        if ($action === 'occupy') {
            // Mark table as occupied by creating a dummy order or updating table status
            $update_query = "UPDATE tables SET status = 'occupied' WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$table_id]);
            
            $message = "Table marked as occupied";
            $message_type = "success";
        } else if ($action === 'free') {
            // Mark table as available
            $update_query = "UPDATE tables SET status = 'active' WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$table_id]);
            
            $message = "Table marked as available";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// Handle Clear Table Orders (Complete all pending orders for a table)
if (isset($_POST['clear_table_orders'])) {
    $table_id = $_POST['table_id'];
    
    try {
        // Update all pending/processing orders for this table to completed
        $update_query = "UPDATE orders SET status = 'completed', updated_at = NOW() WHERE table_id = ? AND status IN ('pending', 'processing')";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$table_id]);
        
        $affected_rows = $update_stmt->rowCount();
        
        if ($affected_rows > 0) {
            $message = "Cleared {$affected_rows} pending orders for this table";
            $message_type = "success";
        } else {
            $message = "No pending orders found for this table";
            $message_type = "info";
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// Get all tables with enhanced occupied status
try {
    $query = "SELECT t.*, 
              COUNT(DISTINCT o.id) as total_orders,
              COUNT(DISTINCT CASE WHEN o.status IN ('pending', 'processing') THEN o.id END) as pending_orders,
              COUNT(DISTINCT CASE WHEN o.status = 'completed' AND DATE(o.created_at) = CURDATE() THEN o.id END) as today_orders,
              COUNT(DISTINCT CASE WHEN qc.is_active = 1 AND (qc.expires_at IS NULL OR qc.expires_at > NOW()) THEN qc.id END) as active_qr_codes,
              CASE 
                  -- If no QR code exists for this table, it's available
                  WHEN NOT EXISTS (
                      SELECT 1 FROM qr_codes qc2 
                      WHERE qc2.table_id = t.id 
                      AND qc2.is_active = 1 
                      AND (qc2.expires_at IS NULL OR qc2.expires_at > NOW())
                  ) THEN 'available'
                  -- If QR code exists but no orders today, it's available
                  WHEN EXISTS (
                      SELECT 1 FROM qr_codes qc3 
                      WHERE qc3.table_id = t.id 
                      AND qc3.is_active = 1 
                      AND (qc3.expires_at IS NULL OR qc3.expires_at > NOW())
                  ) AND NOT EXISTS (
                      SELECT 1 FROM orders o2 
                      WHERE o2.table_id = t.id 
                      AND o2.status IN ('pending', 'processing', 'completed')
                      AND DATE(o2.created_at) = CURDATE()
                  ) THEN 'available'
                  -- If QR code exists and has orders today, it's occupied
                  WHEN EXISTS (
                      SELECT 1 FROM qr_codes qc4 
                      WHERE qc4.table_id = t.id 
                      AND qc4.is_active = 1 
                      AND (qc4.expires_at IS NULL OR qc4.expires_at > NOW())
                  ) AND EXISTS (
                      SELECT 1 FROM orders o2 
                      WHERE o2.table_id = t.id 
                      AND o2.status IN ('pending', 'processing', 'completed')
                      AND DATE(o2.created_at) = CURDATE()
                  ) THEN 'occupied'
                  -- Default to available
                  ELSE 'available'
              END as table_status,
              (SELECT MAX(o3.created_at) FROM orders o3 WHERE o3.table_id = t.id AND o3.status IN ('pending', 'processing', 'completed') AND DATE(o3.created_at) = CURDATE()) as last_order_time,
              (SELECT COUNT(*) FROM orders o4 WHERE o4.table_id = t.id AND o4.status IN ('pending', 'processing', 'completed') AND DATE(o4.created_at) = CURDATE()) as current_orders_count
              FROM tables t
              LEFT JOIN orders o ON t.id = o.table_id
              LEFT JOIN qr_codes qc ON t.id = qc.table_id
              WHERE t.status = 'active'
              GROUP BY t.id
              ORDER BY t.table_number";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "Error retrieving tables: " . $e->getMessage();
    $message_type = "danger";
    $tables = [];
}

// Include external CSS file
$extra_css = '<link rel="stylesheet" href="css/tables.css">';

// Start output buffering
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="page-title">
                <i class="fas fa-table"></i>
                Table Management
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTableModal">
                <i class="fas fa-plus me-2"></i>Add New Table
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tables Container -->
    <div class="tables-container">
        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="tableSearch" placeholder="Search tables...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="filter-buttons">
                        <button type="button" class="filter-btn active" data-filter="all">
                            <i class="fas fa-table"></i>All Tables
                        </button>
                        <button type="button" class="filter-btn" data-filter="available">
                            <i class="fas fa-check-circle"></i>Available
                        </button>
                        <button type="button" class="filter-btn" data-filter="occupied">
                            <i class="fas fa-exclamation-triangle"></i>Occupied
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Grid -->
        <?php if (empty($tables)): ?>
        <div class="no-tables">
            <i class="fas fa-table"></i>
            <h3>No Tables Found</h3>
            <p>No tables found. Add new tables to get started.</p>
        </div>
        <?php else: ?>
        <div class="tables-grid">
            <?php foreach ($tables as $table): 
                $table_status = $table['table_status'];
                $is_occupied = $table_status === 'occupied';
                $is_available = $table_status === 'available';
                $is_inactive = $table['status'] === 'inactive';
            ?>
            <div class="table-card <?php echo $table_status; ?>" 
                 data-status="<?php echo $table['status']; ?>" 
                 data-table-status="<?php echo $table_status; ?>">
                
                <!-- Table Header -->
                <div class="table-header">
                    <div class="table-icon <?php echo $is_inactive ? 'inactive' : $table_status; ?>">
                        <i class="fas fa-chair"></i>
                    </div>
                    <div class="table-info">
                        <h3 class="table-number">Table <?php echo htmlspecialchars($table['table_number']); ?></h3>
                        <span class="status-badge status-<?php echo $is_inactive ? 'inactive' : $table_status; ?>">
                            <i class="fas fa-<?php 
                                if ($is_inactive) echo 'times-circle';
                                elseif ($is_occupied) echo 'exclamation-triangle';
                                else echo 'check-circle';
                            ?>"></i>
                            <?php 
                                if ($is_inactive) echo 'Inactive';
                                elseif ($is_occupied) echo 'Occupied';
                                else echo 'Available';
                            ?>
                        </span>
                        <!-- Status Info -->
                        <?php if ($is_occupied): ?>
                        <div class="occupied-info">
                            <small style="display: block; color: #d97706; font-size: 0.75rem; margin-top: 0.25rem; font-weight: 500;">
                                <i class="fas fa-utensils"></i>
                                <?php if ($table['last_order_time']): ?>
                                    Last order: <?php echo date('h:i A', strtotime($table['last_order_time'])); ?>
                                <?php else: ?>
                                    Has orders today
                                <?php endif; ?>
                                <?php if ($table['current_orders_count'] > 0): ?>
                                    (<?php echo $table['current_orders_count']; ?> orders today)
                                <?php endif; ?>
                                <?php if ($table['pending_orders'] > 0): ?>
                                    - <?php echo $table['pending_orders']; ?> pending
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php elseif ($is_available && $table['active_qr_codes'] == 0): ?>
                        <div class="available-info">
                            <small style="display: block; color: #10b981; font-size: 0.75rem; margin-top: 0.25rem; font-weight: 500;">
                                <i class="fas fa-qrcode"></i>
                                No QR code - Table available for walk-in customers
                            </small>
                        </div>
                        <?php elseif ($is_available && $table['active_qr_codes'] > 0): ?>
                        <div class="available-info">
                            <small style="display: block; color: #10b981; font-size: 0.75rem; margin-top: 0.25rem; font-weight: 500;">
                                <i class="fas fa-check-circle"></i>
                                QR code active - Ready for customers
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stats Section -->
                <div class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">
                                <i class="fas fa-clipboard-list"></i>
                                Total Orders
                            </div>
                            <div class="stat-value"><?php echo $table['total_orders']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">
                                <i class="fas fa-calendar-day"></i>
                                Today
                            </div>
                            <div class="stat-value"><?php echo $table['today_orders']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">
                                <i class="fas fa-clock"></i>
                                Pending
                            </div>
                            <div class="stat-value <?php echo $table['pending_orders'] > 0 ? 'text-warning' : ''; ?>"><?php echo $table['pending_orders']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">
                                <i class="fas fa-qrcode"></i>
                                QR Codes
                            </div>
                            <div class="stat-value"><?php echo $table['active_qr_codes']; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Table Actions -->
                <div class="table-actions">
                    <div class="action-buttons">
                        <!-- Occupied-specific actions -->
                        <?php if ($is_occupied): ?>
                        <button type="button" class="btn-action btn-clear" 
                                data-bs-toggle="modal" 
                                data-bs-target="#clearTableModal<?php echo $table['id']; ?>"
                                title="Clear Table Orders">
                            <i class="fas fa-broom"></i>
                        </button>
                        <button type="button" class="btn-action btn-free" 
                                data-bs-toggle="modal" 
                                data-bs-target="#freeTableModal<?php echo $table['id']; ?>"
                                title="Mark as Available">
                            <i class="fas fa-check-circle"></i>
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn-action btn-occupy" 
                                data-bs-toggle="modal" 
                                data-bs-target="#occupyTableModal<?php echo $table['id']; ?>"
                                title="Mark as Occupied">
                            <i class="fas fa-user-plus"></i>
                        </button>
                        <?php endif; ?>
                        
                        <!-- Standard actions -->
                        <button type="button" class="btn-action btn-edit" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editTableModal<?php echo $table['id']; ?>"
                                title="Edit Table">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="qr_codes.php?table=<?php echo $table['id']; ?>" 
                           class="btn-action btn-qr"
                           title="Manage QR Codes">
                            <i class="fas fa-qrcode"></i>
                        </a>
                        <button type="button" class="btn-action btn-delete" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteTableModal<?php echo $table['id']; ?>"
                                title="Delete Table">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Table Modal -->
<div class="modal fade" id="addTableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Table Number</label>
                        <input type="text" class="form-control" name="table_number" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_table" class="btn btn-primary">Add Table</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Table Modals -->
<?php foreach ($tables as $table): ?>
<div class="modal fade" id="editTableModal<?php echo $table['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Table Number</label>
                        <input type="text" class="form-control" name="new_table_number" 
                               value="<?php echo htmlspecialchars($table['table_number']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="new_status">
                            <option value="active" <?php echo $table['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $table['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_table" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Table Modal -->
<div class="modal fade" id="deleteTableModal<?php echo $table['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete Table <?php echo htmlspecialchars($table['table_number']); ?>?</p>
                <?php if ($table['total_orders'] > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This table has order history. It will be deactivated instead of deleted.
                </div>
                <?php endif; ?>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_table" class="btn btn-danger">
                        <?php echo $table['total_orders'] > 0 ? 'Deactivate Table' : 'Delete Table'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Table Orders Modal -->
<div class="modal fade" id="clearTableModal<?php echo $table['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear Table Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to complete all pending orders for Table <?php echo htmlspecialchars($table['table_number']); ?>?</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This will mark all pending and processing orders as completed. Completed orders will remain unchanged.
                </div>
                <?php if ($table['pending_orders'] > 0): ?>
                <p><strong>Pending orders to be completed: <?php echo $table['pending_orders']; ?></strong></p>
                <?php else: ?>
                <p><strong>No pending orders to clear.</strong></p>
                <?php endif; ?>
                <?php if ($table['today_orders'] > 0): ?>
                <p><small class="text-muted">Total orders today: <?php echo $table['today_orders']; ?> (including completed orders)</small></p>
                <?php endif; ?>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="clear_table_orders" class="btn btn-warning">
                        <i class="fas fa-broom me-2"></i>Clear Orders
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Free Table Modal -->
<div class="modal fade" id="freeTableModal<?php echo $table['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Table as Available</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark Table <?php echo htmlspecialchars($table['table_number']); ?> as available?</p>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    This will mark the table as available for new customers. Existing orders will remain unchanged.
                </div>
                <?php if ($table['today_orders'] > 0): ?>
                <p><small class="text-muted">Note: This table has <?php echo $table['today_orders']; ?> orders today, but will still show as available.</small></p>
                <?php endif; ?>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                    <input type="hidden" name="action" value="free">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="toggle_occupied" class="btn btn-success">
                        <i class="fas fa-check-circle me-2"></i>Mark as Available
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Occupy Table Modal -->
<div class="modal fade" id="occupyTableModal<?php echo $table['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Table as Occupied</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark Table <?php echo htmlspecialchars($table['table_number']); ?> as occupied?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This will mark the table as occupied and prevent new orders.
                </div>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                    <input type="hidden" name="action" value="occupy">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="toggle_occupied" class="btn btn-warning">
                        <i class="fas fa-user-plus me-2"></i>Mark as Occupied
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();

// Add JavaScript for search and filter functionality
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Search functionality
    const searchInput = document.getElementById("tableSearch");
    const tableCards = document.querySelectorAll(".table-card");
    
    searchInput.addEventListener("input", function() {
        const searchTerm = this.value.toLowerCase();
        
        tableCards.forEach(card => {
            const tableNumber = card.querySelector(".table-number").textContent.toLowerCase();
            
            if (tableNumber.includes(searchTerm)) {
                card.style.display = "";
            } else {
                card.style.display = "none";
            }
        });
    });

    // Filter functionality
    const filterButtons = document.querySelectorAll(".filter-btn");
    
    filterButtons.forEach(button => {
        button.addEventListener("click", function() {
            const filter = this.dataset.filter;
            
            // Update active state
            filterButtons.forEach(btn => btn.classList.remove("active"));
            this.classList.add("active");
            
            // Filter items
            tableCards.forEach(card => {
                const tableStatus = card.dataset.tableStatus;
                const cardStatus = card.dataset.status;
                
                let shouldShow = false;
                
                if (filter === "all") {
                    shouldShow = true;
                } else if (filter === "available" && tableStatus === "available") {
                    shouldShow = true;
                } else if (filter === "occupied" && tableStatus === "occupied") {
                    shouldShow = true;
                }
                
                if (shouldShow) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        });
    });
    
    // Add hover effects and animations
    tableCards.forEach(card => {
        card.addEventListener("mouseenter", function() {
            this.style.transform = "translateY(-4px)";
        });
        
        card.addEventListener("mouseleave", function() {
            this.style.transform = "translateY(0)";
        });
    });
    
    // Auto-refresh every 30 seconds to update occupied status
    setInterval(function() {
        location.reload();
    }, 30000);
});
</script>
';

// Include the layout template
include 'includes/layout.php';
?> 
?> 