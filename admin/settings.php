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
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to access Settings') . '&type=warning');
    exit();
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
            'currency_symbol' => [
                'value' => $_POST['currency_symbol'] ?? 'RM',
                'type' => 'string',
                'description' => 'Currency symbol'
            ],
            'currency_code' => [
                'value' => $_POST['currency_code'] ?? 'MYR',
                'type' => 'string',
                'description' => 'Currency code'
            ],
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

        <!-- Order Settings -->
        <div class="settings-card">
            <h3 class="card-title">
                <i class="fas fa-shopping-cart"></i>
                Order Settings
            </h3>
            <div class="custom-switch">
                <div>
                    <h6 class="switch-label">Enable Online Ordering</h6>
                    <p class="switch-description">Allow customers to place orders through the website</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="online_ordering" <?php echo ($current_settings['online_ordering']['value'] ?? true) ? 'checked' : ''; ?>>
                </div>
            </div>
            <div class="custom-switch">
                <div>
                    <h6 class="switch-label">Table Reservations</h6>
                    <p class="switch-description">Enable table booking system</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="reservations" <?php echo ($current_settings['reservations']['value'] ?? true) ? 'checked' : ''; ?>>
                </div>
            </div>
            <div class="custom-switch">
                <div>
                    <h6 class="switch-label">Order Notifications</h6>
                    <p class="switch-description">Send email notifications for new orders</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="order_notifications" <?php echo ($current_settings['order_notifications']['value'] ?? true) ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>

        <!-- Payment Settings -->
        <div class="settings-card">
            <h3 class="card-title">
                <i class="fas fa-credit-card"></i>
                Payment Settings
            </h3>
            <div class="custom-switch">
                <div>
                    <h6 class="switch-label">Cash Payments</h6>
                    <p class="switch-description">Accept cash payments on delivery</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="cash_payments" <?php echo ($current_settings['cash_payments']['value'] ?? true) ? 'checked' : ''; ?>>
                </div>
            </div>
            <div class="custom-switch">
                <div>
                    <h6 class="switch-label">Card Payments</h6>
                    <p class="switch-description">Accept credit/debit card payments</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="card_payments" <?php echo ($current_settings['card_payments']['value'] ?? true) ? 'checked' : ''; ?>>
                </div>
            </div>
            <div class="custom-switch">
                <div>
                    <h6 class="switch-label">Digital Wallets</h6>
                    <p class="switch-description">Accept payments through digital wallets</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="digital_payments" <?php echo ($current_settings['digital_payments']['value'] ?? false) ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>

        <!-- Tax & Currency -->
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
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" class="form-control" name="currency_symbol" value="<?php echo htmlspecialchars($current_settings['currency_symbol']['value'] ?? 'RM'); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Currency Code</label>
                        <input type="text" class="form-control" name="currency_code" value="<?php echo htmlspecialchars($current_settings['currency_code']['value'] ?? 'MYR'); ?>">
                    </div>
                </div>
            </div>
        </div>

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