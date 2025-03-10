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

// Add this to handle order completion
if (isset($_POST['complete_order'])) {
    $table_id = $_POST['table_id'];
    
    try {
        if (deactivateQRCode($db, $table_id)) {
            $message = "Order completed and QR code deactivated successfully";
            $message_type = "success";
        } else {
            throw new Exception("Failed to deactivate QR code");
        }
        
        // Refresh table data
        $tables = $qrCode->getAllTables();
    } catch (Exception $e) {
        $message = "Error completing order: " . $e->getMessage();
    $message_type = "danger";
        error_log("Order completion error: " . $e->getMessage());
    }
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
    
    $result = $qrCode->deleteTable($table_id);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    
    // Refresh table data
    $tables = $qrCode->getAllTables();
}

// Custom CSS
$extra_css = '
<style>
.qr-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
}

.qr-card:hover {
    transform: translateY(-5px);
}

.qr-image {
    max-width: 200px;
    margin: 0 auto;
    padding: 10px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.qr-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 1.5rem;
}

.table-number {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.qr-placeholder {
    width: 200px;
    height: 200px;
    background: #f8f9fa;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    border: 2px dashed #dee2e6;
}

.qr-placeholder i {
    font-size: 3rem;
    color: #dee2e6;
}

.qr-url {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.5rem;
    word-break: break-all;
    cursor: pointer;
    transition: color 0.3s ease;
}

.qr-url:hover {
    color: var(--primary-color);
}

.qr-info {
    margin-top: 1rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 0.875rem;
}

.btn-generate {
    min-width: 140px;
}

.btn-download {
    min-width: 140px;
}

.qr-preview {
    position: relative;
    display: inline-block;
}

.qr-preview::after {
    content: "Click to enlarge";
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.75rem;
    color: #6c757d;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.qr-preview:hover::after {
    opacity: 1;
}

.table-number-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 1rem;
    cursor: pointer;
    padding: 0;
    transition: color 0.3s ease;
}

.table-number-btn:hover {
    color: var(--bs-primary);
}

.table-number-form {
    margin-bottom: 1rem;
}

.qr-placeholder {
    cursor: pointer;
    transition: transform 0.3s ease;
}

.qr-placeholder:hover {
    transform: scale(1.05);
}

.qr-placeholder:hover i {
    color: var(--bs-primary);
}

.modal-xl-custom {
    max-width: 800px;
}

.qr-image-large {
    max-width: 100%;
    height: auto;
}

.qr-preview img {
    cursor: pointer;
    transition: transform 0.2s;
}

.qr-preview img:hover {
    transform: scale(1.05);
}

.btn-info {
    color: #fff;
    background-color: #17a2b8;
    border-color: #17a2b8;
}

.btn-info:hover {
    color: #fff;
    background-color: #138496;
    border-color: #117a8b;
}

/* Print styles */
@media print {
    @page {
        margin: 0;
        size: A4;
    }
    
    body * {
        visibility: hidden;
    }
    
    .modal.show {
        position: absolute !important;
        left: 0;
        top: 0;
        margin: 0;
        padding: 0;
        overflow: visible !important;
    }
    
    .modal.show .modal-dialog {
        transform: translate(0, 0) !important;
        margin: 0;
        width: 100%;
    }
    
    .modal-content {
        border: none !important;
        box-shadow: none !important;
    }
    
    .modal-content * {
        visibility: visible;
    }
    
    .modal-header, .modal-footer, .btn-close {
        display: none !important;
    }
    
    .qr-print-content {
        visibility: visible;
        position: fixed;
        left: 50%;
        transform: translateX(-50%);
        top: 20px;
        width: 100%;
        max-width: 480px;
        margin: 0;
        background: white;
    }
    
    .print-table-info {
        text-align: center;
        background: #ffffff;
        padding: 40px 30px;
        position: relative;
        border: 1px solid #e0e0e0;
    }

    /* Elegant Frame Design */
    .frame-border {
        position: absolute;
        top: 15px;
        left: 15px;
        right: 15px;
        bottom: 15px;
        border: 2px solid #c9a95c;
        pointer-events: none;
    }

    .frame-corner {
        position: absolute;
        width: 30px;
        height: 30px;
        border: 2px solid #c9a95c;
    }

    .frame-corner.top-left {
        top: 8px;
        left: 8px;
        border-right: none;
        border-bottom: none;
    }

    .frame-corner.top-right {
        top: 8px;
        right: 8px;
        border-left: none;
        border-bottom: none;
    }

    .frame-corner.bottom-left {
        bottom: 8px;
        left: 8px;
        border-right: none;
        border-top: none;
    }

    .frame-corner.bottom-right {
        bottom: 8px;
        right: 8px;
        border-left: none;
        border-top: none;
    }
    
    .restaurant-logo {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        position: relative;
    }

    .restaurant-logo::before {
        content: '*';
        font-size: 40px;
        color: #c9a95c;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }
    
    .restaurant-name {
        font-size: 28px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0 0 5px;
        text-transform: uppercase;
        letter-spacing: 4px;
        font-family: 'Times New Roman', serif;
    }

    .restaurant-tagline {
        font-size: 12px;
        color: #666;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 30px;
    }
    
    .table-number-print {
        font-size: 18px;
        font-weight: 500;
        margin: 25px 0;
        color: #1a1a1a;
        letter-spacing: 2px;
        text-transform: uppercase;
        position: relative;
        display: inline-block;
        padding: 12px 35px;
    }

    .table-number-print::before,
    .table-number-print::after {
        content: '';
        position: absolute;
        width: 40px;
        height: 1px;
        background: #c9a95c;
        top: 50%;
    }

    .table-number-print::before {
        left: -20px;
    }

    .table-number-print::after {
        right: -20px;
    }
    
    .qr-container {
        position: relative;
        margin: 30px auto;
        width: 240px;
        padding: 15px;
        background: #fff;
        box-shadow: 0 0 15px rgba(0,0,0,0.05);
    }

    .qr-print-content img {
        width: 210px;
        height: 210px;
        display: block;
        margin: 0 auto;
        background: white;
    }
    
    .validity-info {
        font-size: 12px;
        font-weight: 500;
        color: #666;
        margin: 20px auto;
        letter-spacing: 1px;
        display: inline-block;
        position: relative;
        padding: 8px 0;
    }

    .validity-info::before,
    .validity-info::after {
        content: '•';
        color: #c9a95c;
        margin: 0 10px;
    }
    
    .print-footer {
        margin-top: 25px;
        padding-top: 25px;
        border-top: 1px solid #eee;
        position: relative;
    }

    .scan-instructions {
        font-size: 14px;
        color: #333;
        margin-bottom: 20px;
        font-style: italic;
    }

    .scan-steps {
        text-align: left;
        width: 80%;
        margin: 15px auto;
        padding: 0;
        list-style: none;
    }

    .scan-steps li {
        font-size: 12px;
        color: #666;
        margin: 8px 0;
        padding-left: 25px;
        position: relative;
        line-height: 1.4;
    }

    .scan-steps li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: #c9a95c;
        font-weight: bold;
    }

    .establishment-info {
        margin-top: 25px;
        font-size: 11px;
        color: #999;
        font-style: italic;
    }

    .divider {
        width: 50px;
        height: 1px;
        background: #c9a95c;
        margin: 15px auto;
    }
}
</style>';

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