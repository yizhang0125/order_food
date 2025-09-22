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

// Get all tables
try {
    $query = "SELECT t.*, 
              COUNT(DISTINCT o.id) as total_orders,
              COUNT(DISTINCT CASE WHEN o.status IN ('pending', 'processing') THEN o.id END) as pending_orders,
              COUNT(DISTINCT CASE WHEN qc.is_active = 1 AND (qc.expires_at IS NULL OR qc.expires_at > NOW()) THEN qc.id END) as active_qr_codes,
              CASE 
                  WHEN EXISTS (
                      SELECT 1 FROM orders o2 
                      WHERE o2.table_id = t.id 
                      AND o2.status IN ('pending', 'processing')
                  ) THEN 'occupied'
                  ELSE 'available'
              END as table_status
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
                        <!-- Debug info (remove this in production) -->
                        <small style="display: block; color: #666; font-size: 0.75rem; margin-top: 0.25rem;">
                            Status: <?php echo $table_status; ?> | Orders: <?php echo $table['total_orders']; ?> | Pending: <?php echo $table['pending_orders']; ?>
                        </small>
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
                                <i class="fas fa-clock"></i>
                                Pending
                            </div>
                            <div class="stat-value"><?php echo $table['pending_orders']; ?></div>
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