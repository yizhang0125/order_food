<?php
session_start();
// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/classes/QRCodeManager.php');

// Debug information
error_log("Checking requirements...");

// Check GD extension
if (!extension_loaded('gd')) {
    die("PHP GD extension is not installed. Please enable it in php.ini");
}
error_log("GD extension is installed");

// Check if phpqrcode exists
$qr_lib_path = __DIR__ . '/../vendor/phpqrcode/qrlib.php';
if (!file_exists($qr_lib_path)) {
    die("phpqrcode library not found at: " . $qr_lib_path);
}
error_log("Found phpqrcode at: " . $qr_lib_path);

require_once($qr_lib_path);
if (!class_exists('QRcode')) {
    die("QRcode class not found after including library");
}
error_log("QRcode class exists");

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$qrCode = new QRCodeManager($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'QR Code Management';
$message = '';
$message_type = '';

// Drop token columns if they exist
try {
    // Check and drop token column
    $check_columns_query = "SHOW COLUMNS FROM tables LIKE 'token'";
    $stmt = $db->prepare($check_columns_query);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        try {
            $alter_query = "ALTER TABLE tables DROP COLUMN token";
            $db->exec($alter_query);
            error_log("Dropped token column from tables");
        } catch (Exception $e) {
            error_log("Error dropping token column: " . $e->getMessage());
        }
    }

    // Check and drop token_generated_at column
    $check_columns_query = "SHOW COLUMNS FROM tables LIKE 'token_generated_at'";
    $stmt = $db->prepare($check_columns_query);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        try {
            $alter_query = "ALTER TABLE tables DROP COLUMN token_generated_at";
        $db->exec($alter_query);
            error_log("Dropped token_generated_at column from tables");
        } catch (Exception $e) {
            error_log("Error dropping token_generated_at column: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    $message = "Error modifying database: " . $e->getMessage();
    $message_type = "danger";
    error_log("Database modification error: " . $e->getMessage());
}

// Get base URL - Simplified version
$base_url = "http://" . $_SERVER['HTTP_HOST'];
$menu_url = $base_url . "/food1";

// Token expiration time (in days)
$token_expiry_days = 30;

// Get all tables with QR codes
try {
    $tables = $qrCode->getAllTables();
    
    if (empty($tables)) {
        $message = "No tables found in the database. Please add tables first.";
        $message_type = "warning";
    }
} catch (Exception $e) {
    $message = "Database Error: " . $e->getMessage();
    $message_type = "danger";
    $tables = [];
}

// Get paths from QRCode class
$qr_web_path = $qrCode->getQRCodePath();
$qr_dir = $qrCode->getPhysicalPath();

// Create uploads directory if it doesn't exist
if (!file_exists($qr_dir)) {
    error_log("Creating QR code directory at: " . $qr_dir);
    if (!mkdir($qr_dir, 0777, true)) {
        error_log("Failed to create directory: " . $qr_dir);
        $message .= " Failed to create QR codes directory.";
        $message_type = "danger";
    } else {
        error_log("Successfully created QR code directory");
        // Also create an index.html file to prevent directory listing
        file_put_contents($qr_dir . DIRECTORY_SEPARATOR . 'index.html', '');
    }
}

// Check directory permissions
if (!is_writable($qr_dir)) {
    error_log("QR code directory is not writable: " . $qr_dir);
    // Try to make the directory writable
    chmod($qr_dir, 0777);
    if (!is_writable($qr_dir)) {
        error_log("Failed to make directory writable even after chmod");
        $message .= " QR code directory is not writable.";
        $message_type = "danger";
    } else {
        error_log("Successfully made directory writable");
    }
}

// Add this function after the database connection setup
function deactivateQRCode($db, $table_id) {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Deactivate all QR codes for this table
        $update_query = "UPDATE qr_codes SET is_active = 0 WHERE table_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$table_id]);
        
        // Commit transaction
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error deactivating QR code: " . $e->getMessage());
        return false;
    }
}

// Add this function after the database connection setup
function deactivateQRCodeAfterOrderCompletion($db, $table_id) {
    try {
        // Start transaction
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $db->beginTransaction();
        
        // Get current active QR code
        $get_qr_query = "SELECT id, image_path FROM qr_codes 
                        WHERE table_id = ? AND is_active = 1";
        $get_qr_stmt = $db->prepare($get_qr_query);
        $get_qr_stmt->execute([$table_id]);
        $qr_code = $get_qr_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($qr_code) {
            // Deactivate the QR code
            $update_query = "UPDATE qr_codes 
                           SET is_active = 0, 
                               expires_at = NOW() 
                           WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$qr_code['id']]);
            
            // Delete physical QR code file if it exists
            if (!empty($qr_code['image_path'])) {
                $qr_file_path = __DIR__ . "/../uploads/qrcodes/" . basename($qr_code['image_path']);
                if (file_exists($qr_file_path)) {
                    unlink($qr_file_path);
                }
            }
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error deactivating QR code: " . $e->getMessage());
        return false;
    }
}

// Add this to handle order completion
if (isset($_POST['complete_order'])) {
    $table_id = $_POST['table_id'];
    $order_id = $_POST['order_id'];
    
    try {
        // Start transaction
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $db->beginTransaction();
        
        // Update order status to completed
        $update_order_query = "UPDATE orders SET status = 'completed' WHERE id = ?";
        $update_order_stmt = $db->prepare($update_order_query);
        $update_order_stmt->execute([$order_id]);
        
        // Deactivate QR code
        if (deactivateQRCodeAfterOrderCompletion($db, $table_id)) {
            $db->commit();
            $message = "Order completed and QR code deactivated successfully.";
            $message_type = "success";
        } else {
            throw new Exception("Failed to deactivate QR code");
        }
        
        // Check if table has any other active orders
        $check_orders_query = "SELECT COUNT(*) as active_orders 
                             FROM orders 
                             WHERE table_id = ? 
                             AND status IN ('pending', 'processing')";
        $check_stmt = $db->prepare($check_orders_query);
        $check_stmt->execute([$table_id]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no active orders, mark table as available
        if ($result['active_orders'] == 0) {
            $update_table_query = "UPDATE tables SET status = 'active' WHERE id = ?";
            $update_table_stmt = $db->prepare($update_table_query);
            $update_table_stmt->execute([$table_id]);
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "Error completing order: " . $e->getMessage();
        $message_type = "danger";
        error_log("Order completion error: " . $e->getMessage());
    }
    
    // Refresh table data
    $tables = $qrCode->getAllTables();
}

// Handle QR code generation
if (isset($_POST['generate_qr'])) {
    $table_id = $_POST['table_id'];
    $table_number = $_POST['table_number'];
    
    $result = $qrCode->generateQRCode($table_id, $table_number);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    
    // Refresh table data
    $tables = $qrCode->getAllTables();
}

// Handle Add Table
if (isset($_POST['add_table'])) {
    $table_number = trim($_POST['table_number']);
    
    $result = $qrCode->addTable($table_number);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    
    // Refresh table data
    $tables = $qrCode->getAllTables();
}

// Handle Edit Table
if (isset($_POST['edit_table'])) {
    $table_id = $_POST['table_id'];
    $new_table_number = trim($_POST['new_table_number']);
    
    try {
        // Validate input
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
        $update_query = "UPDATE tables SET table_number = ? WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$new_table_number, $table_id])) {
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
        // Start transaction
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $db->beginTransaction();
        
        // Check if table has any pending or active orders
        $check_orders_query = "SELECT COUNT(*) as order_count FROM orders 
                             WHERE table_id = ? 
                             AND status IN ('pending', 'processing')";
        $check_stmt = $db->prepare($check_orders_query);
        $check_stmt->execute([$table_id]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['order_count'] > 0) {
            // Table has active orders - show warning
            $db->rollBack();
            $message = "Cannot delete table: There are " . $result['order_count'] . " active or pending orders. Please complete or cancel these orders first.";
            $message_type = "warning";
        } else {
            // Check for completed orders
            $check_completed_query = "SELECT COUNT(*) as completed_count FROM orders 
                                    WHERE table_id = ? 
                                    AND status = 'completed'";
            $check_completed_stmt = $db->prepare($check_completed_query);
            $check_completed_stmt->execute([$table_id]);
            $completed_result = $check_completed_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($completed_result['completed_count'] > 0) {
                // Table has completed orders - perform soft delete
                $update_query = "UPDATE tables SET status = 'inactive' WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$table_id]);
                
                // Deactivate all QR codes for this table
                $deactivate_qr_query = "UPDATE qr_codes SET is_active = 0 WHERE table_id = ?";
                $deactivate_qr_stmt = $db->prepare($deactivate_qr_query);
                $deactivate_qr_stmt->execute([$table_id]);
                
                // Delete physical QR code files
                $get_qr_query = "SELECT image_path FROM qr_codes WHERE table_id = ?";
                $get_qr_stmt = $db->prepare($get_qr_query);
                $get_qr_stmt->execute([$table_id]);
                while ($qr_result = $get_qr_stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($qr_result['image_path'])) {
                        $qr_file_path = __DIR__ . "/../uploads/qrcodes/" . basename($qr_result['image_path']);
                        if (file_exists($qr_file_path)) {
                            unlink($qr_file_path);
                        }
                    }
                }
                
                $db->commit();
                $message = "Table has order history. The table has been deactivated and its QR codes have been removed.";
                $message_type = "info";
            } else {
                // No orders at all - safe to delete completely
                // First delete associated QR codes
                $delete_qr_query = "DELETE FROM qr_codes WHERE table_id = ?";
                $delete_qr_stmt = $db->prepare($delete_qr_query);
                $delete_qr_stmt->execute([$table_id]);
                
                // Then delete the table
                $delete_table_query = "DELETE FROM tables WHERE id = ?";
                $delete_table_stmt = $db->prepare($delete_table_query);
                $delete_table_stmt->execute([$table_id]);
                
                $db->commit();
                $message = "Table and associated QR codes have been completely deleted.";
                $message_type = "success";
            }
        }
    } catch (Exception $e) {
        $db->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
        error_log("Table deletion error: " . $e->getMessage());
    }
    
    // Refresh table data
    $tables = $qrCode->getAllTables();
}

// Replace the $extra_css variable with this:
$extra_css = '<link rel="stylesheet" href="css/qr_codes.css">';

// Add this to your CSS section
$extra_css .= '
<style>
.qr-status-icon {
    position: absolute;
    top: -10px;
    right: -10px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.qr-status-expired {
    background: #EF4444;
}

.qr-status-invalid {
    background: #F59E0B;
}

.qr-status-active {
    background: #10B981;
}

.qr-code-wrapper {
    position: relative;
    display: inline-block;
}

.qr-code-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    border-radius: 8px;
}

.expired-text {
    color: #EF4444;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.invalid-text {
    color: #F59E0B;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.active-text {
    color: #10B981;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
</style>
';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">QR Code Management</h1>
        <button type="button" class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addTableModal">
            <i class="fas fa-plus"></i> Add New Table
        </button>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- QR Codes Grid -->
    <div class="row g-4">
        <?php 
        // Debug information
        error_log("Number of tables found: " . count($tables));
        
        // Add this debug section at the top of the page
        if (isset($_GET['debug'])) {
            echo '<div class="col-12"><div class="alert alert-info">';
            echo '<h4>Debug Information:</h4>';
            echo 'QR Web Path: ' . $qr_web_path . '<br>';
            echo 'QR Directory: ' . $qr_dir . '<br>';
            echo 'Directory exists: ' . (file_exists($qr_dir) ? 'Yes' : 'No') . '<br>';
            echo 'Directory writable: ' . (is_writable($qr_dir) ? 'Yes' : 'No') . '<br>';
            echo '</div></div>';
        }

        foreach ($tables as $table): 
            // Get the correct paths
            $qr_file = null;
            $qr_path = null;
            if (!empty($table['image_path'])) {
                // Use absolute path for file system and relative path for web
                $qr_file = "/food1/uploads/qrcodes/" . basename($table['image_path']);
                $qr_path = __DIR__ . "/../uploads/qrcodes/" . basename($table['image_path']);
                
                // Debug logging
                error_log("Table " . $table['table_number'] . " QR paths:");
                error_log("Web path: " . $qr_file);
                error_log("File path: " . $qr_path);
                error_log("File exists: " . (file_exists($qr_path) ? "Yes" : "No"));
            }
        ?>
        <div class="col-12 col-sm-6 col-md-4 col-xl-3">
            <div class="card qr-card">
                <div class="card-body text-center">
                    <div class="table-number">
                        Table <?php echo htmlspecialchars($table['table_number']); ?>
                    </div>
                    
                    <?php if (!empty($table['image_path'])): ?>
                        <div class="qr-preview">
                            <?php if (file_exists($qr_path)): ?>
                                <img src="<?php echo htmlspecialchars($qr_file); ?>" 
                                     alt="QR Code for Table <?php echo htmlspecialchars($table['table_number']); ?>"
                                     class="qr-image img-fluid mb-3"
                                     data-bs-toggle="modal"
                                     data-bs-target="#qrModal<?php echo $table['id']; ?>">
                                
                                <!-- Debug info -->
                                <?php if (isset($_GET['debug'])): ?>
                                <div class="alert alert-info mt-2">
                                    <small>
                                        Image Path: <?php echo $table['image_path']; ?><br>
                                        Web Path: <?php echo $qr_file; ?><br>
                                        File Path: <?php echo $qr_path; ?><br>
                                        File Exists: <?php echo file_exists($qr_path) ? 'Yes' : 'No'; ?><br>
                                        File Size: <?php echo file_exists($qr_path) ? filesize($qr_path) . ' bytes' : 'N/A'; ?><br>
                                        File Permissions: <?php echo file_exists($qr_path) ? substr(sprintf('%o', fileperms($qr_path)), -4) : 'N/A'; ?>
                                    </small>
                        </div>
                                <?php endif; ?>

                        <div class="qr-info">
                                    <i class="fas fa-clock me-1"></i>
                                    Valid until: <?php echo date('d/m/Y h:i A', strtotime($table['expiry_date'])); ?>
                        </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    QR code file missing: <?php echo htmlspecialchars($table['image_path']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="qr-placeholder mb-3">
                            <i class="fas fa-qrcode"></i>
                            <div class="mt-2 small text-muted">
                                No QR code generated yet
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="qr-actions mt-3">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                            <input type="hidden" name="table_number" value="<?php echo $table['table_number']; ?>">
                            <button type="submit" name="generate_qr" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i>
                                <?php echo (!empty($table['image_path']) && file_exists($qr_path)) ? 'Regenerate QR' : 'Generate QR'; ?>
                            </button>
                        </form>
                        
                        <?php if (!empty($table['image_path']) && file_exists($qr_path)): ?>
                            <button type="button" class="btn btn-info" onclick="window.open('<?php echo htmlspecialchars($qr_file); ?>', '_blank')">
                                <i class="fas fa-eye me-2"></i>View QR
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-danger" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteTableModal<?php echo $table['id']; ?>">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Code Modal -->
        <?php if (!empty($table['image_path']) && file_exists($qr_path)): ?>
        <div class="modal fade" id="qrModal<?php echo $table['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">QR Code - Table <?php echo htmlspecialchars($table['table_number']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="qr-print-content">
                            <div class="print-table-info">
                                <!-- Elegant Frame -->
                                <div class="frame-border"></div>
                                <div class="frame-corner top-left"></div>
                                <div class="frame-corner top-right"></div>
                                <div class="frame-corner bottom-left"></div>
                                <div class="frame-corner bottom-right"></div>
                                
                                <!-- Restaurant Branding -->
                                <div class="restaurant-logo"></div>
                                <div class="restaurant-name">Gourmet Delights</div>
                                <div class="restaurant-tagline">FINE DINING & CUISINE</div>
                                
                                <!-- Table Information -->
                                <div class="table-number-print">
                                    Table <?php echo htmlspecialchars($table['table_number']); ?>
                                </div>
                                
                                <!-- QR Code -->
                                <div class="qr-container">
                                    <img src="<?php echo htmlspecialchars($qr_file); ?>" 
                                         alt="QR Code" 
                                         class="img-fluid">
                                </div>
                                
                                <!-- Validity Information -->
                                <div class="validity-info">
                                    Valid until: <?php echo date('d/m/Y h:i A', strtotime($table['expiry_date'])); ?>
                                </div>
                                
                                <div class="divider"></div>
                                
                                <!-- Footer Section -->
                                <div class="print-footer">
                                    <div class="scan-instructions">
                                        Experience our digital menu with these simple steps:
                                    </div>
                                    <ul class="scan-steps">
                                        <li>Open your smartphone's camera app</li>
                                        <li>Point your camera at the QR code above</li>
                                        <li>Tap the notification to view our digital menu</li>
                                    </ul>
                                    
                                    <div class="divider"></div>
                                    
                                    <div class="establishment-info">
                                        Gourmet Delights Restaurant<br>
                                        Fine Dining Excellence Since 2024
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" onclick="printQRCode()">
                            <i class="fas fa-print me-2"></i>Print QR
                        </button>
                        <button type="button" class="btn btn-primary" onclick="downloadQRCode('<?php echo htmlspecialchars($qr_file); ?>', 'table_<?php echo htmlspecialchars($table['table_number']); ?>_qr.png')">
                            <i class="fas fa-download me-2"></i>Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Add this debug section at the bottom of the page -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="mt-4">
        <h4>Directory Contents:</h4>
        <pre>
        <?php
        if (file_exists($qr_dir)) {
            $files = scandir($qr_dir);
            print_r($files);
        } else {
            echo "QR code directory does not exist";
        }
        ?>
        </pre>
    </div>
    <?php endif; ?>
</div>

<!-- Add JavaScript for copying URL -->
<script>
function copyToClipboard(element) {
    const text = element.textContent.trim();
            navigator.clipboard.writeText(text).then(() => {
        const originalText = element.innerHTML;
        element.innerHTML = '<i class="fas fa-check me-1"></i>URL copied!';
                setTimeout(() => {
            element.innerHTML = originalText;
                }, 2000);
            });
}

// Add new download function
function downloadQRCode(imageUrl, fileName) {
    fetch(imageUrl)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        })
        .catch(error => {
            console.error('Error downloading QR code:', error);
            alert('Error downloading QR code. Please try again.');
        });
}

// Add this JavaScript function
function generateQR(tableId, tableNumber) {
    const form = document.createElement('form');
    form.method = 'POST';
    
    const tableIdInput = document.createElement('input');
    tableIdInput.type = 'hidden';
    tableIdInput.name = 'table_id';
    tableIdInput.value = tableId;
    
    const tableNumberInput = document.createElement('input');
    tableNumberInput.type = 'hidden';
    tableNumberInput.name = 'table_number';
    tableNumberInput.value = tableNumber;
    
    const generateInput = document.createElement('input');
    generateInput.type = 'hidden';
    generateInput.name = 'generate_qr';
    generateInput.value = '1';
    
    form.appendChild(tableIdInput);
    form.appendChild(tableNumberInput);
    form.appendChild(generateInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Add this to your existing JavaScript
function printQRCode() {
    window.print();
}
</script>

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
                        <label for="table_number" class="form-label">Table Number</label>
                        <input type="text" class="form-control" id="table_number" name="table_number" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_table" class="btn btn-primary">Add Table</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Table Modal -->
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
                        <label for="new_table_number" class="form-label">Table Number</label>
                        <input type="text" class="form-control" id="new_table_number" name="new_table_number" 
                               value="<?php echo htmlspecialchars($table['table_number']); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                <p class="text-danger"><small>This action cannot be undone and will remove all associated QR codes.</small></p>
            </div>
            <form method="POST">
                <div class="modal-footer">
                    <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_table" class="btn btn-danger">Delete Table</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?> 