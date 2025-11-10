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
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to add staff') . '&type=warning');
    exit();
}

$page_title = 'Add New Staff';
$message = '';
$message_type = '';

// Get all permissions
try {
    $permissions = $staffController->getAllPermissions();
} catch (Exception $e) {
    $message = "Error loading permissions: " . $e->getMessage();
    $message_type = "danger";
    $permissions = [];
}

// Handle Add Staff
if (isset($_POST['add_staff'])) {
    try {
        if ($staffController->addStaff($_POST)) {
            header('Location: staff_management.php?message=Staff member added successfully&type=success');
            exit();
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

ob_start();
?>
<link rel="stylesheet" href="../admin/css/add_staff.css">

<div class="container-fluid py-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="page-title">
                <i class="fas fa-user-plus"></i>
                Add New Staff
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
                            <input type="text" class="form-control" name="employee_number" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-user me-2"></i>Full Name
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="name" required>
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
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" name="password" required>
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
                                <option value="manager">Manager</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="waiter">Waiter</option>
                                <option value="kitchen">Kitchen Staff</option>
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
                                <option value="full-time">Full Time</option>
                                <option value="part-time">Part Time</option>
                            </select>
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
                                'icon' => 'fa-kitchen-set',
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
                        
                        foreach ($permissions as $perm): 
                            $permInfo = $permissionIcons[$perm['name']] ?? [
                                'icon' => 'fa-lock',
                                'color' => '#64748B',
                                'description' => $perm['description']
                            ];
                        ?>
                        <div class="permission-item" data-permission="<?php echo htmlspecialchars($perm['name']); ?>">
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
                                                   id="perm_<?php echo $perm['id']; ?>">
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
                    <a href="staff_management.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" name="add_staff" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllBtn = document.getElementById('selectAllPermissions');
    const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
    let allSelected = false;
    
    // Select All / Deselect All functionality
    selectAllBtn.addEventListener('click', function() {
        allSelected = !allSelected;
        
        permissionCheckboxes.forEach(checkbox => {
            checkbox.checked = allSelected;
            
            // Add visual feedback
            const card = checkbox.closest('.permission-item');
            if (allSelected) {
                card.style.transform = 'scale(1.02)';
                card.style.boxShadow = '0 8px 25px rgba(79, 70, 229, 0.15)';
            } else {
                card.style.transform = '';
                card.style.boxShadow = '';
            }
        });
        
        // Update button text and icon
        if (allSelected) {
            selectAllBtn.innerHTML = '<i class="fas fa-times me-2"></i>Deselect All';
            selectAllBtn.className = 'btn btn-outline-danger btn-sm';
        } else {
            selectAllBtn.innerHTML = '<i class="fas fa-check-double me-2"></i>Select All';
            selectAllBtn.className = 'btn btn-outline-primary btn-sm';
        }
    });
    
    // Individual checkbox change handler
    permissionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const card = this.closest('.permission-item');
            
            if (this.checked) {
                card.style.transform = 'scale(1.02)';
                card.style.boxShadow = '0 8px 25px rgba(16, 185, 129, 0.15)';
            } else {
                card.style.transform = '';
                card.style.boxShadow = '';
                
                // Update select all button if not all are selected
                if (allSelected) {
                    allSelected = false;
                    selectAllBtn.innerHTML = '<i class="fas fa-check-double me-2"></i>Select All';
                    selectAllBtn.className = 'btn btn-outline-primary btn-sm';
                }
            }
            
            // Check if all checkboxes are now selected
            const checkedCount = document.querySelectorAll('.permission-checkbox:checked').length;
            if (checkedCount === permissionCheckboxes.length && !allSelected) {
                allSelected = true;
                selectAllBtn.innerHTML = '<i class="fas fa-times me-2"></i>Deselect All';
                selectAllBtn.className = 'btn btn-outline-danger btn-sm';
            }
        });
    });
    
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
    
    // Auto-generate employee number (optional feature)
    const employeeNumberField = document.querySelector('input[name="employee_number"]');
    const nameField = document.querySelector('input[name="name"]');
    
    nameField.addEventListener('input', function() {
        if (!employeeNumberField.value) {
            const name = this.value.trim();
            if (name) {
                // Generate employee number from name initials + random number
                const initials = name.split(' ').map(word => word.charAt(0).toUpperCase()).join('');
                const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
                employeeNumberField.value = initials + randomNum;
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>