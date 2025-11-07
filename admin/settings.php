<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$systemSettings = new SystemSettings($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if user has permission to access settings
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('manage_settings', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Settings') . '&type=warning');
    exit();
}

// Check specific permissions for different settings sections using database
$user_type = $_SESSION['user_type'] ?? '';
$can_manage_discounts = false;
$can_manage_payments = false;
$can_manage_tax = false;

if ($user_type === 'admin') {
    $can_manage_discounts = true;
    $can_manage_payments = true;
    $can_manage_tax = true;
} elseif ($user_type === 'staff' && isset($_SESSION['staff_id'])) {
    try {
        // Check discount permissions
        $discount_query = "SELECT COUNT(*) as has_permission 
                          FROM staff_permissions sp 
                          INNER JOIN permissions p ON sp.permission_id = p.id 
                          WHERE sp.staff_id = ? AND (p.name = 'manage_discounts' OR p.name = 'all')";
        $discount_stmt = $db->prepare($discount_query);
        $discount_stmt->execute([$_SESSION['staff_id']]);
        $discount_result = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        $can_manage_discounts = $discount_result['has_permission'] > 0;
        
        // Check payment permissions
        $payment_query = "SELECT COUNT(*) as has_permission 
                         FROM staff_permissions sp 
                         INNER JOIN permissions p ON sp.permission_id = p.id 
                         WHERE sp.staff_id = ? AND (p.name = 'manage_payments' OR p.name = 'all')";
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->execute([$_SESSION['staff_id']]);
        $payment_result = $payment_stmt->fetch(PDO::FETCH_ASSOC);
        $can_manage_payments = $payment_result['has_permission'] > 0;
        
        // Check tax permissions
        $tax_query = "SELECT COUNT(*) as has_permission 
                     FROM staff_permissions sp 
                     INNER JOIN permissions p ON sp.permission_id = p.id 
                     WHERE sp.staff_id = ? AND (p.name = 'manage_tax' OR p.name = 'all')";
        $tax_stmt = $db->prepare($tax_query);
        $tax_stmt->execute([$_SESSION['staff_id']]);
        $tax_result = $tax_stmt->fetch(PDO::FETCH_ASSOC);
        $can_manage_tax = $tax_result['has_permission'] > 0;
        
    } catch (Exception $e) {
        error_log("Error checking settings permissions: " . $e->getMessage());
        $can_manage_discounts = false;
        $can_manage_payments = false;
        $can_manage_tax = false;
    }
}

// Handle settings update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'restaurant_name' => [
                'value' => $_POST['restaurant_name'] ?? '',
                'type' => 'string',
                'description' => 'Restaurant name'
            ],
            'contact_email' => [
                'value' => $_POST['contact_email'] ?? '',
                'type' => 'string',
                'description' => 'Contact email'
            ],
            'opening_time' => [
                'value' => $_POST['opening_time'] ?? '09:00',
                'type' => 'string',
                'description' => 'Opening time'
            ],
            'closing_time' => [
                'value' => $_POST['closing_time'] ?? '22:00',
                'type' => 'string',
                'description' => 'Closing time'
            ],
            'last_order_time' => [
                'value' => $_POST['last_order_time'] ?? '21:30',
                'type' => 'string',
                'description' => 'Last order time'
            ],
            // Only include tax settings if user has permission
            ...($can_manage_tax ? [
                'tax_rate' => [
                    'value' => $_POST['tax_rate'] ?? 6,
                    'type' => 'number',
                    'description' => 'Tax rate percentage'
                ],
                'tax_name' => [
                    'value' => $_POST['tax_name'] ?? 'SST',
                    'type' => 'string',
                    'description' => 'Tax name'
                ],
                'service_tax_rate' => [
                    'value' => $_POST['service_tax_rate'] ?? 10,
                    'type' => 'number',
                    'description' => 'Service tax rate percentage'
                ],
                'service_tax_name' => [
                    'value' => $_POST['service_tax_name'] ?? 'Service Tax',
                    'type' => 'string',
                    'description' => 'Service tax name'
                ],
                'currency_symbol' => [
                    'value' => $_POST['currency_symbol'] ?? 'RM',
                    'type' => 'string',
                    'description' => 'Currency symbol'
                ],
                'currency_code' => [
                    'value' => $_POST['currency_code'] ?? 'MYR',
                    'type' => 'string',
                    'description' => 'Currency code'
                ]
            ] : []),
            'online_ordering' => [
                'value' => isset($_POST['online_ordering']) ? '1' : '0',
                'type' => 'boolean',
                'description' => 'Enable online ordering'
            ],
            'reservations' => [
                'value' => isset($_POST['reservations']) ? '1' : '0',
                'type' => 'boolean',
                'description' => 'Enable reservations'
            ],
            'order_notifications' => [
                'value' => isset($_POST['order_notifications']) ? '1' : '0',
                'type' => 'boolean',
                'description' => 'Send order notifications'
            ],
            // Only include payment settings if user has permission
            ...($can_manage_payments ? [
                'cash_payments' => [
                    'value' => isset($_POST['cash_payments']) ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'Accept cash payments'
                ],
                'card_payments' => [
                    'value' => isset($_POST['card_payments']) ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'Accept card payments'
                ],
                'digital_payments' => [
                    'value' => isset($_POST['digital_payments']) ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'Accept digital payments'
                ]
            ] : []),
            // Only include discount settings if user has permission
            ...($can_manage_discounts ? [
                'enable_discounts' => [
                    'value' => isset($_POST['enable_discounts']) ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'Enable discount system'
                ],
                'birthday_discount_percent' => [
                    'value' => $_POST['birthday_discount_percent'] ?? 10,
                    'type' => 'number',
                    'description' => 'Birthday discount percentage'
                ],
                'staff_discount_percent' => [
                    'value' => $_POST['staff_discount_percent'] ?? 20,
                    'type' => 'number',
                    'description' => 'Staff discount percentage'
                ],
                'review_discount_percent' => [
                    'value' => $_POST['review_discount_percent'] ?? 5,
                    'type' => 'number',
                    'description' => 'Review discount percentage'
                ],
                'complaint_discount_percent' => [
                    'value' => $_POST['complaint_discount_percent'] ?? 15,
                    'type' => 'number',
                    'description' => 'Complaint resolution discount percentage'
                ],
                'max_discount_amount' => [
                    'value' => $_POST['max_discount_amount'] ?? 50,
                    'type' => 'number',
                    'description' => 'Maximum discount amount'
                ]
            ] : [])
        ];
        
        if ($systemSettings->updateSettings($settings)) {
            $success_message = "Settings updated successfully!";
        } else {
            $error_message = "Failed to update settings. Please try again.";
        }
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = $systemSettings->getAllSettings();

// Custom CSS with modern design
$extra_css = '
<style>
:root {
    --primary: #4F46E5;
    --primary-light: #818CF8;
    --success: #10B981;
    --warning: #F59E0B;
    --danger: #EF4444;
    --info: #3B82F6;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
}

.settings-header {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.settings-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
}

.settings-subtitle {
    color: var(--gray-500);
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.settings-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.settings-card:hover {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-title i {
    color: var(--primary);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.form-text {
    color: var(--gray-500);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.custom-switch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--gray-200);
}

.custom-switch:last-child {
    border-bottom: none;
}

.switch-label {
    font-weight: 500;
    color: var(--gray-700);
    margin: 0;
}

.switch-description {
    color: var(--gray-500);
    font-size: 0.875rem;
    margin: 0;
}

.form-switch {
    padding-left: 3em;
}

.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
    cursor: pointer;
}

.form-switch .form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.btn-save {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-save:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
}

.alert {
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border: none;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.time-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
</style>';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Settings Header -->
    <div class="settings-header">
        <h1 class="settings-title">System Settings</h1>
        <p class="settings-subtitle">Configure your restaurant management system preferences</p>
    </div>

    <?php if ($success_message || $error_message): ?>
    <div class="alert alert-<?php echo $success_message ? 'success' : 'danger'; ?>" role="alert">
        <i class="fas fa-<?php echo $success_message ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo $success_message ?: $error_message; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- General Settings -->
        <div class="settings-card">
            <h3 class="card-title">
                <i class="fas fa-cog"></i>
                General Settings
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Restaurant Name</label>
                        <input type="text" class="form-control" name="restaurant_name" value="<?php echo htmlspecialchars($current_settings['restaurant_name']['value'] ?? 'Gourmet Delights'); ?>">
                        <div class="form-text">This name will appear on receipts and the customer interface</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($current_settings['contact_email']['value'] ?? 'contact@restaurant.com'); ?>">
                        <div class="form-text">Primary email for customer support and notifications</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Business Hours -->
        <div class="settings-card">
            <h3 class="card-title">
                <i class="fas fa-clock"></i>
                Business Hours
            </h3>
            <div class="time-grid">
                <div class="form-group">
                    <label class="form-label">Opening Time</label>
                    <input type="time" class="form-control" name="opening_time" value="<?php echo htmlspecialchars($current_settings['opening_time']['value'] ?? '09:00'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Closing Time</label>
                    <input type="time" class="form-control" name="closing_time" value="<?php echo htmlspecialchars($current_settings['closing_time']['value'] ?? '22:00'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Order Time</label>
                    <input type="time" class="form-control" name="last_order_time" value="<?php echo htmlspecialchars($current_settings['last_order_time']['value'] ?? '21:30'); ?>">
                </div>
            </div>
        </div>

        <!-- Tax & Currency -->
        <?php if ($can_manage_tax): ?>
        <div class="settings-card">
            <h3 class="card-title">
                <i class="fas fa-dollar-sign"></i>
                Tax & Currency
            </h3>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" class="form-control" name="tax_rate" value="<?php echo htmlspecialchars($current_settings['tax_rate']['value'] ?? 6); ?>" min="0" max="100" step="0.1">
                        <div class="form-text">Applied to all orders</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Tax Name</label>
                        <input type="text" class="form-control" name="tax_name" value="<?php echo htmlspecialchars($current_settings['tax_name']['value'] ?? 'SST'); ?>">
                        <div class="form-text">e.g., SST, VAT, GST</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Service Tax Rate (%)</label>
                        <input type="number" class="form-control" name="service_tax_rate" value="<?php echo htmlspecialchars($current_settings['service_tax_rate']['value'] ?? 10); ?>" min="0" max="100" step="0.1">
                        <div class="form-text">Applied to service charges</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Service Tax Name</label>
                        <input type="text" class="form-control" name="service_tax_name" value="<?php echo htmlspecialchars($current_settings['service_tax_name']['value'] ?? 'Service Tax'); ?>">
                        <div class="form-text">e.g., Service Tax, Service Charge</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" class="form-control" name="currency_symbol" value="<?php echo htmlspecialchars($current_settings['currency_symbol']['value'] ?? 'RM'); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Currency Code</label>
                        <input type="text" class="form-control" name="currency_code" value="<?php echo htmlspecialchars($current_settings['currency_code']['value'] ?? 'MYR'); ?>">
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Discount Settings -->
        <?php if ($can_manage_discounts): ?>
        <div class="settings-card">
            <h3 class="card-title">
                <i class="fas fa-percentage"></i>
                Discount Settings
            </h3>
            
            <!-- Enable Discounts -->
            <div class="custom-switch">
                <div>
                    <h6 class="switch-label">Enable Discount System</h6>
                    <p class="switch-description">Allow staff to apply discounts to orders</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="enable_discounts" <?php echo ($current_settings['enable_discounts']['value'] ?? true) ? 'checked' : ''; ?>>
                </div>
            </div>

            <!-- Discount Types -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-birthday-cake text-warning"></i>
                            Birthday Discount (%)
                        </label>
                        <input type="number" class="form-control" name="birthday_discount_percent" value="<?php echo htmlspecialchars($current_settings['birthday_discount_percent']['value'] ?? 10); ?>" min="0" max="100" step="1">
                        <div class="form-text">Discount for birthday celebrations</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-tie text-primary"></i>
                            Staff Discount (%)
                        </label>
                        <input type="number" class="form-control" name="staff_discount_percent" value="<?php echo htmlspecialchars($current_settings['staff_discount_percent']['value'] ?? 20); ?>" min="0" max="100" step="1">
                        <div class="form-text">Employee discount rate</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-star text-success"></i>
                            Review Discount (%)
                        </label>
                        <input type="number" class="form-control" name="review_discount_percent" value="<?php echo htmlspecialchars($current_settings['review_discount_percent']['value'] ?? 5); ?>" min="0" max="100" step="1">
                        <div class="form-text">Discount for customer reviews</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                            Complaint Discount (%)
                        </label>
                        <input type="number" class="form-control" name="complaint_discount_percent" value="<?php echo htmlspecialchars($current_settings['complaint_discount_percent']['value'] ?? 15); ?>" min="0" max="100" step="1">
                        <div class="form-text">Discount for complaint resolution</div>
                    </div>
                </div>
            </div>

            <!-- Discount Limits -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-shield-alt text-info"></i>
                            Maximum Discount Amount
                        </label>
                        <input type="number" class="form-control" name="max_discount_amount" value="<?php echo htmlspecialchars($current_settings['max_discount_amount']['value'] ?? 50); ?>" min="0" step="0.01">
                        <div class="form-text">Maximum discount amount in <?php echo htmlspecialchars($current_settings['currency_symbol']['value'] ?? 'RM'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Discount Information -->
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Discount Guidelines:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Birthday Discount:</strong> Applied on customer's birthday month</li>
                    <li><strong>Staff Discount:</strong> For employee meals and purchases</li>
                    <li><strong>Review Discount:</strong> For customers who leave reviews</li>
                    <li><strong>Complaint Discount:</strong> For resolving customer complaints</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Save Button -->
        <div class="text-end mb-4">
            <button type="submit" class="btn btn-save">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for animations and interactions
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Animate alerts
    const alerts = document.querySelectorAll(".alert");
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = "opacity 0.5s ease";
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Animate settings cards on load
    const cards = document.querySelectorAll(".settings-card");
    cards.forEach((card, index) => {
        card.style.opacity = "0";
        card.style.transform = "translateY(20px)";
        setTimeout(() => {
            card.style.transition = "all 0.3s ease";
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
        }, index * 100);
    });

    // Form submission handling
    const form = document.querySelector("form");
    form.addEventListener("submit", function(e) {
        const saveBtn = this.querySelector(".btn-save");
        saveBtn.innerHTML = \'<i class="fas fa-spinner fa-spin me-2"></i>Saving...\';
        saveBtn.disabled = true;
    });
});
</script>';

include 'includes/layout.php';
?> 