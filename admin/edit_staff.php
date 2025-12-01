<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/classes/StaffController.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$staffController = new StaffController($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if user has permission to manage staff
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('staff_management', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to edit staff') . '&type=warning');
    exit();
}

$page_title = 'Edit Staff Member';
$message = '';
$message_type = '';

// Get staff details
$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
try {
    $staff = $staffController->getStaffById($staff_id);
    if (!$staff) {
        header('Location: staff_management.php?message=Staff not found&type=error');
        exit();
    }
    // Load permissions catalog and current assignments
    $all_permissions = $staffController->getAllPermissions();
    $assigned_permission_ids = $staffController->getStaffPermissionIds($staff_id);
} catch (Exception $e) {
    header('Location: staff_management.php?message=' . urlencode($e->getMessage()) . '&type=error');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    try {
        $staffController->updateStaff($staff_id, $_POST);
        header('Location: staff_management.php?message=Staff updated successfully&type=success');
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

ob_start();
?>

<div class="container-fluid py-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i>
                Edit Staff Member
            </h1>
            <a href="staff_management.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Staff List
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="form-card">
            <form method="POST" class="needs-validation" novalidate>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-id-card me-2"></i>Employee Number
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="text" class="form-control" name="employee_number" 
                                   value="<?php echo htmlspecialchars($staff['employee_number']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-user me-2"></i>Full Name
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-lock me-2"></i>New Password (leave blank to keep current)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" name="password" id="password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Leave blank to keep the current password
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-briefcase me-2"></i>Position
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                            <select class="form-select" name="position" required>
                                <option value="manager" <?php echo $staff['position'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="supervisor" <?php echo $staff['position'] === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="waiter" <?php echo $staff['position'] === 'waiter' ? 'selected' : ''; ?>>Waiter</option>
                                <option value="kitchen" <?php echo $staff['position'] === 'kitchen' ? 'selected' : ''; ?>>Kitchen Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-clock me-2"></i>Employment Type
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-business-time"></i></span>
                            <select class="form-select" name="employment_type" required>
                                <option value="full-time" <?php echo $staff['employment_type'] === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                <option value="part-time" <?php echo $staff['employment_type'] === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-toggle-on me-2"></i>Account Status
                        </label>
                        <div class="status-toggle-container">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" 
                                       id="active_status" <?php echo $staff['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active_status">
                                    <span class="status-text"><?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                </label>
                            </div>
                            <div class="status-description">
                                <?php if ($staff['is_active']): ?>
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    Staff member can login and access the system
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger me-1"></i>
                                    Staff member cannot login to the system
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-calendar-alt me-2"></i>Account Information
                        </label>
                        <div class="account-info">
                            <div class="info-item">
                                <i class="fas fa-user-plus me-2"></i>
                                <span>Created: <?php echo date('M d, Y', strtotime($staff['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($staff['updated_at']) && $staff['updated_at'] !== $staff['created_at']): ?>
                            <div class="info-item">
                                <i class="fas fa-edit me-2"></i>
                                <span>Last Updated: <?php echo date('M d, Y', strtotime($staff['updated_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="permissions-section mb-4">
                    <div class="permissions-header">
                        <div class="d-flex align-items-center">
                            <div class="permission-icon-main">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h5 class="mb-0">Staff Permissions</h5>
                        </div>
                        <button type="button" class="btn btn-select-all" id="selectAllPermissions">
                            <i class="fas fa-check-double me-2"></i>
                            <span>Select All</span>
                        </button>
                    </div>
                    <div class="permissions-grid">
                        <?php 
                        $permissionIcons = [
                            'all' => [
                                'icon' => 'fa-key',
                                'color' => '#4F46E5',
                                'description' => 'Access to all system features'
                            ],
                            'kitchen_view' => [
                                'icon' => 'fa-utensils',
                                'color' => '#10B981',
                                'description' => 'View and update kitchen order status'
                            ],
                            'manage_discounts' => [
                                'icon' => 'fa-percent',
                                'color' => '#F59E0B',
                                'description' => 'Manage customer discounts and promotional offers'
                            ],
                            'manage_menu' => [
                                'icon' => 'fa-utensils',
                                'color' => '#EC4899',
                                'description' => 'Add, edit, or remove menu items'
                            ],
                            'manage_orders' => [
                                'icon' => 'fa-clipboard-list',
                                'color' => '#8B5CF6',
                                'description' => 'Handle customer orders'
                            ],
                            'manage_payments' => [
                                'icon' => 'fa-cash-register',
                                'color' => '#059669',
                                'description' => 'Access and process payments at payment counter'
                            ],
                            'staff_management' => [
                                'icon' => 'fa-users-cog',
                                'color' => '#3B82F6',
                                'description' => 'Manage staff members and their permissions'
                            ],
                            'table_management_qr' => [
                                'icon' => 'fa-qrcode',
                                'color' => '#6366F1',
                                'description' => 'Manage table assignments and generate QR codes'
                            ],
                            'view_sales' => [
                                'icon' => 'fa-chart-line',
                                'color' => '#7C3AED',
                                'description' => 'View sales reports and analytics'
                            ]
                        ];
                        
                        foreach ($all_permissions as $perm): 
                            // Skip deprecated/internal-only permission 'view_dashboard' from UI
                            if (isset($perm['name']) && $perm['name'] === 'view_dashboard') continue;
                            $permInfo = $permissionIcons[$perm['name']] ?? [
                                'icon' => 'fa-lock',
                                'color' => '#64748B',
                                'description' => $perm['description']
                            ];
                            $isChecked = in_array((int)$perm['id'], $assigned_permission_ids, true);
                        ?>
                        <div class="permission-item <?php echo $isChecked ? 'selected' : ''; ?>" data-permission="<?php echo htmlspecialchars($perm['name']); ?>">
                            <div class="permission-card">
                                <div class="permission-card-header">
                                    <div class="permission-info">
                                        <div class="permission-icon" style="background-color: <?php echo $permInfo['color']; ?>">
                                            <i class="fas <?php echo $permInfo['icon']; ?>"></i>
                                        </div>
                                        <div class="permission-details">
                                            <h6 class="permission-name">
                                                <?php echo ucwords(str_replace('_', ' ', $perm['name'])); ?>
                                            </h6>
                                            <p class="permission-description">
                                                <?php echo htmlspecialchars($permInfo['description']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="permission-toggle">
                                        <label class="switch">
                                            <input type="checkbox" 
                                                   class="permission-checkbox" 
                                                   name="staff_permissions[]" 
                                                   value="<?php echo $perm['id']; ?>" 
                                                   id="perm_<?php echo $perm['id']; ?>"
                                                   <?php echo $isChecked ? 'checked' : ''; ?>>
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="deleteStaff(<?php echo $staff_id; ?>)">
                        <i class="fas fa-trash me-2"></i>Delete Staff
                    </button>
                    <div class="action-buttons">
                        <a href="staff_management.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" name="update_staff" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

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
    color: var(--primary);
}

.form-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.form-card {
    padding: 2rem;
}

.form-control, .form-select {
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.1);
    outline: none;
}

.input-group-text {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    color: var(--gray-600);
    border-radius: 12px 0 0 12px;
}

.input-group .form-control {
    border-left: none;
    border-radius: 0 12px 12px 0;
}

.input-group .form-select {
    border-left: none;
    border-radius: 0 12px 12px 0;
}

.form-label {
    color: var(--gray-700);
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.form-label i {
    color: var(--primary);
}

.form-text {
    color: var(--gray-500);
    font-size: 0.875rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
}

.status-toggle-container {
    background: var(--gray-50);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
}

.form-check-input {
    width: 3rem;
    height: 1.5rem;
    background-color: var(--gray-300);
    border: none;
    border-radius: 1rem;
    transition: all 0.3s ease;
}

.form-check-input:checked {
    background-color: var(--success);
    border-color: var(--success);
}

.status-text {
    font-weight: 600;
    color: var(--gray-700);
    margin-left: 0.5rem;
}

.status-description {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--gray-600);
    display: flex;
    align-items: center;
}

.account-info {
    background: var(--gray-50);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
}

.info-item {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
    color: var(--gray-600);
    font-size: 0.9rem;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item i {
    color: var(--primary);
    width: 16px;
}

.permissions-section {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.permissions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--gray-50);
    border-bottom: 2px solid var(--gray-100);
}

.permission-icon-main {
    width: 48px;
    height: 48px;
    background: var(--primary);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.permission-icon-main i {
    font-size: 1.5rem;
    color: white;
}

.permissions-header h5 {
    margin: 0;
    color: var(--gray-800);
    font-size: 1.25rem;
    font-weight: 700;
}

.btn-select-all {
    background: white;
    border: 2px solid var(--primary);
    color: var(--primary);
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.btn-select-all:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.btn-select-all.active {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
}

.permissions-grid {
    padding: 1.5rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.permission-item {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.permission-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.permission-item.selected {
    border-color: var(--success);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
}

.permission-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.permission-card-header {
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.permission-info {
    display: flex;
    gap: 1rem;
    flex: 1;
}

.permission-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.permission-icon i {
    color: white;
}

.permission-details {
    flex: 1;
}

.permission-name {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-800);
}

.permission-description {
    margin: 0;
    font-size: 0.875rem;
    color: var(--gray-600);
    line-height: 1.4;
}

/* Custom Toggle Switch */
.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--gray-300);
    transition: .4s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
}

input:checked + .slider {
    background-color: var(--success);
}

input:focus + .slider {
    box-shadow: 0 0 1px var(--success);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.slider.round {
    border-radius: 34px;
}

.slider.round:before {
    border-radius: 50%;
}

/* Animation for permission selection */
.permission-item.selected .permission-icon {
    transform: scale(1.1);
}

.permission-item.selected .permission-name {
    color: var(--success);
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 2rem;
    border-top: 2px solid var(--gray-100);
    margin-top: 2rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    border: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #3730a3;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(79, 70, 229, 0.25);
    color: white;
}

.btn-secondary {
    background: var(--gray-200);
    color: var(--gray-700);
}

.btn-secondary:hover {
    background: var(--gray-300);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    color: var(--gray-700);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.25);
    color: white;
}

.alert {
    border-radius: 12px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .permissions-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header .d-flex {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch !important;
    }
    
    .page-title {
        font-size: 1.5rem;
        text-align: center;
    }
    
    .form-card {
        padding: 1.5rem;
    }
    
    .permissions-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
    
    .permission-card-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .permission-info {
        flex-direction: column;
        text-align: center;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .page-header {
        padding: 1rem;
    }
    
    .form-card {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.25rem;
    }
    
    .permission-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .permission-name {
        font-size: 0.9rem;
    }
    
    .permission-description {
        font-size: 0.8rem;
    }
    
    .form-control, .form-select {
        font-size: 16px; /* Prevents zoom on iOS */
    }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
    .btn {
        min-height: 44px;
        padding: 12px 16px;
    }
    
    .permission-card:hover {
        transform: none;
    }
    
    .form-switch .form-check-input {
        min-height: 44px;
        min-width: 44px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --gray-50: #1f2937;
        --gray-100: #374151;
        --gray-200: #4b5563;
        --gray-300: #6b7280;
        --gray-800: #f9fafb;
        --gray-700: #f3f4f6;
        --gray-600: #e5e7eb;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllBtn = document.getElementById('selectAllPermissions');
    const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
    let allSelected = false;
    
    // Check initial state
    const checkedCount = document.querySelectorAll('.permission-checkbox:checked').length;
    if (checkedCount === permissionCheckboxes.length) {
        allSelected = true;
        updateSelectAllButton();
    }
    
    // Select All / Deselect All functionality
    selectAllBtn.addEventListener('click', function() {
        allSelected = !allSelected;
        
        permissionCheckboxes.forEach(checkbox => {
            checkbox.checked = allSelected;
            updatePermissionItem(checkbox);
        });
        
        updateSelectAllButton();
    });
    
    // Individual checkbox change handler
    permissionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updatePermissionItem(this);
            
            // Check if all checkboxes are now selected
            const checkedCount = document.querySelectorAll('.permission-checkbox:checked').length;
            if (checkedCount === permissionCheckboxes.length && !allSelected) {
                allSelected = true;
                updateSelectAllButton();
            } else if (checkedCount < permissionCheckboxes.length && allSelected) {
                allSelected = false;
                updateSelectAllButton();
            }
        });
    });
    
    function updatePermissionItem(checkbox) {
        const card = checkbox.closest('.permission-item');
        if (checkbox.checked) {
            card.classList.add('selected');
            card.style.transform = 'scale(1.02)';
            card.style.boxShadow = '0 8px 25px rgba(16, 185, 129, 0.15)';
        } else {
            card.classList.remove('selected');
            card.style.transform = '';
            card.style.boxShadow = '';
        }
    }
    
    function updateSelectAllButton() {
        if (allSelected) {
            selectAllBtn.innerHTML = '<i class="fas fa-times me-2"></i>Deselect All';
            selectAllBtn.className = 'btn btn-select-all active';
        } else {
            selectAllBtn.innerHTML = '<i class="fas fa-check-double me-2"></i>Select All';
            selectAllBtn.className = 'btn btn-select-all';
        }
    }
    
    // Password toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    
    if (togglePassword && passwordField) {
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
    
    // Status toggle functionality
    const statusToggle = document.getElementById('active_status');
    const statusText = document.querySelector('.status-text');
    const statusDescription = document.querySelector('.status-description');
    
    if (statusToggle && statusText && statusDescription) {
        statusToggle.addEventListener('change', function() {
            if (this.checked) {
                statusText.textContent = 'Active';
                statusDescription.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>Staff member can login and access the system';
            } else {
                statusText.textContent = 'Inactive';
                statusDescription.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i>Staff member cannot login to the system';
            }
        });
    }
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                // Remove invalid class after user starts typing
                field.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                }, { once: true });
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            // Scroll to first invalid field
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
        }
    });
});

function deleteStaff(staffId) {
    Swal.fire({
        title: 'Delete Staff Member',
        html: 'Are you sure you want to delete this staff member?<br><strong>This action cannot be undone!</strong>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, delete',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await fetch('staff_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `delete_staff=1&staff_id=${staffId}`
                });
                
                if (!response.ok) {
                    throw new Error('Failed to delete staff member');
                }
                
                return response;
            } catch (error) {
                Swal.showValidationMessage(
                    `Delete failed: ${error.message}`
                );
            }
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleted Successfully',
                text: 'Staff member has been deleted.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'staff_management.php';
            });
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>