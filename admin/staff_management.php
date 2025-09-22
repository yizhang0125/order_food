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
    header('Location: dashboard.php?message=' . urlencode('You do not have permission to manage staff') . '&type=warning');
    exit();
}

$page_title = 'Staff Management';
$message = '';
$message_type = '';

// Resolve current user's permissions
try {
    $current_staff_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
    $current_permissions = $current_staff_id ? $staffController->getStaffPermissionNames($current_staff_id) : [];
} catch (Exception $e) {
    $current_permissions = [];
}

// Helper permission checks
$can_view_staff = in_array('staff.view', $current_permissions) || isset($_SESSION['admin_id']);
$can_add_staff = in_array('staff.add', $current_permissions) || isset($_SESSION['admin_id']);
$can_edit_staff = in_array('staff.edit', $current_permissions) || isset($_SESSION['admin_id']);
$can_delete_staff = in_array('staff.delete', $current_permissions) || isset($_SESSION['admin_id']);

// Get available permissions
try {
    $perm_query = "SELECT id, name, description FROM permissions ORDER BY name";
    $perm_stmt = $db->prepare($perm_query);
    $perm_stmt->execute();
    $available_permissions = $perm_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "Error loading permissions: " . $e->getMessage();
    $message_type = "danger";
    $available_permissions = [];
}

// Handle Add Staff
if (isset($_POST['add_staff'])) {
    if (!$can_add_staff) {
        $message = "You do not have permission to add staff.";
        $message_type = "danger";
    } else {
    $employee_number = trim($_POST['employee_number']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $position = $_POST['position'];
    $employment_type = $_POST['employment_type'];
    $selected_permissions = isset($_POST['staff_permissions']) ? $_POST['staff_permissions'] : [];
    
    try {
        $db->beginTransaction();
        
        // Check for duplicate employee number or email
        $check_query = "SELECT id FROM staff WHERE employee_number = ? OR email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$employee_number, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception("Employee number or email already exists");
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert_query = "INSERT INTO staff (employee_number, name, email, password, position, employment_type) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($insert_query);
        $stmt->execute([$employee_number, $name, $email, $hash, $position, $employment_type]);
        
        // Assign selected permissions
        if (!empty($selected_permissions)) {
            $insert_perm = "INSERT INTO staff_permissions (staff_id, permission_id) VALUES (?, ?)";
            $perm_stmt = $db->prepare($insert_perm);
            foreach ($selected_permissions as $permission_id) {
                $perm_stmt->execute([$db->lastInsertId(), $permission_id]);
            }
        }

        $message = "Staff member added successfully";
        $message_type = "success";
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
    }
}

// Handle delete staff
if ((isset($_POST['delete_staff']) || isset($_GET['delete_staff'])) && 
    (isset($_POST['staff_id']) || isset($_GET['staff_id']))) {
    try {
        if (!$can_delete_staff) {
            throw new Exception('You do not have permission to delete staff.');
        }
        $staff_id = isset($_POST['staff_id']) ? $_POST['staff_id'] : $_GET['staff_id'];
        $staffController->deleteStaff($staff_id);
        $message = "Staff member deleted successfully";
        $message_type = "success";
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// Get all staff members
try {
    $staff = $staffController->getAllStaff();
} catch (Exception $e) {
    $message = "Error retrieving staff list: " . $e->getMessage();
    $message_type = "danger";
    $staff = [];
}

$extra_css = '
<link rel="stylesheet" href="css/staff_management.css">
<!-- Add SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
';

// Add SweetAlert2 JavaScript to the page
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

ob_start();
?>

<div class="container-fluid py-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="page-title">
                <i class="fas fa-users-cog"></i>
                Staff Management
            </h1>
            <?php if ($can_add_staff): ?>
            <a href="add_staff.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Staff
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-container">
        <div class="filter-card">
            <div class="row">
                <div class="col-md-8">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-filter me-2"></i>Filter by Role
                        </label>
                        <div class="filter-buttons">
                            <button class="btn btn-outline-primary active" data-role="all">
                                <i class="fas fa-users me-1"></i>All Staff
                            </button>
                            <button class="btn btn-outline-primary" data-role="manager">
                                <i class="fas fa-user-tie me-1"></i>Managers
                            </button>
                            <button class="btn btn-outline-primary" data-role="supervisor">
                                <i class="fas fa-user-shield me-1"></i>Supervisors
                            </button>
                            <button class="btn btn-outline-primary" data-role="waiter">
                                <i class="fas fa-concierge-bell me-1"></i>Waiters
                            </button>
                            <button class="btn btn-outline-primary" data-role="kitchen">
                                <i class="fas fa-utensils me-1"></i>Kitchen Staff
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-clock me-2"></i>Employment Type
                        </label>
                        <div class="filter-buttons">
                            <button class="btn btn-outline-success" data-type="full-time">
                                <i class="fas fa-clock me-1"></i>Full Time
                            </button>
                            <button class="btn btn-outline-info" data-type="part-time">
                                <i class="fas fa-clock me-1"></i>Part Time
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="staff-count-display">
                            <span class="badge bg-primary">
                                <i class="fas fa-users me-1"></i>
                                <span class="staff-count"><?php echo count($staff); ?> staff members</span>
                            </span>
                        </div>
                        <div class="filter-actions">
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Cards -->
    <div class="row g-4">
        <?php 
        // Sort staff by position and employment type
        usort($staff, function($a, $b) {
            $positions = ['manager' => 1, 'supervisor' => 2, 'waiter' => 3, 'kitchen' => 4];
            if ($positions[$a['position']] !== $positions[$b['position']]) {
                return $positions[$a['position']] - $positions[$b['position']];
            }
            return strcmp($a['employment_type'], $b['employment_type']);
        });
        
        foreach ($staff as $member): 
        ?>
        <div class="col-12 col-md-6 col-xl-4" 
             data-role="<?php echo $member['position']; ?>"
             data-type="<?php echo $member['employment_type']; ?>"
             data-staff-id="<?php echo $member['id']; ?>">
            <div class="staff-card">
                <!-- Card Header with Avatar and Status -->
                <div class="card-header-custom">
                    <div class="staff-avatar-section">
                        <div class="staff-avatar">
                            <div class="avatar-circle">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="status-indicator <?php echo $member['is_active'] ? 'active' : 'inactive'; ?>"></div>
                        </div>
                        <div class="staff-basic-info">
                            <h5 class="staff-name"><?php echo htmlspecialchars($member['name']); ?></h5>
                            <p class="staff-position">
                                <i class="fas fa-<?php echo $member['position'] == 'manager' ? 'user-tie' : ($member['position'] == 'supervisor' ? 'user-shield' : ($member['position'] == 'waiter' ? 'concierge-bell' : 'utensils')); ?> me-1"></i>
                                <?php echo ucfirst($member['position']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="staff-badges">
                        <span class="badge badge-employment <?php echo $member['employment_type'] == 'full-time' ? 'full-time' : 'part-time'; ?>">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo ucfirst($member['employment_type']); ?>
                        </span>
                    </div>
                </div>

                <!-- Card Body with Information -->
                <div class="card-body-custom">
                    <div class="info-section">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-content">
                                <span class="info-label">Employee ID</span>
                                <span class="info-value"><?php echo htmlspecialchars($member['employee_number']); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($member['email']); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="info-content">
                                <span class="info-label">Joined</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($member['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Footer with Actions -->
                <div class="card-footer-custom">
                    <div class="staff-actions">
                        <?php if ($can_edit_staff): ?>
                        <button type="button" class="btn-action btn-edit" 
                                onclick="editStaff(<?php echo $member['id']; ?>)"
                                title="Edit Staff">
                            <i class="fas fa-edit"></i>
                            <span>Edit</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($can_delete_staff): ?>
                        <button type="button" class="btn-action btn-delete" 
                                onclick="deleteStaff(<?php echo $member['id']; ?>)"
                                title="Delete Staff">
                            <i class="fas fa-trash"></i>
                            <span>Delete</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>


                    


<script>
function editStaff(staffId) {
    window.location.href = `edit_staff.php?id=${staffId}`;
}

function deleteStaff(staffId) {
    const staffCard = document.querySelector(`[data-staff-id="${staffId}"]`);
    const staffName = staffCard ? staffCard.querySelector('.card-title').textContent : 'this staff member';

    Swal.fire({
        title: 'Delete Staff Member',
        html: `Are you sure you want to delete <strong>${staffName}</strong>?<br>This action cannot be undone!`,
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
            if (staffCard) {
                staffCard.classList.add('fade-out');
                setTimeout(() => {
                    staffCard.remove();
                    // Check if there are no more staff members
                    const remainingStaff = document.querySelectorAll('.staff-card').length;
                    if (remainingStaff === 0) {
                        location.reload(); // Refresh if no staff left
                    }
                }, 300);
            }
            
            Swal.fire({
                title: 'Deleted Successfully',
                text: `${staffName} has been deleted.`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Role filter buttons
    document.querySelectorAll('.filter-buttons .btn[data-role]').forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all role filter buttons
            document.querySelectorAll('.filter-buttons .btn[data-role]').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Apply filters
            applyFilters();
        });
    });
    
    // Employment type filter buttons
    document.querySelectorAll('.filter-buttons .btn[data-type]').forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all employment type filter buttons
            document.querySelectorAll('.filter-buttons .btn[data-type]').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Apply filters
            applyFilters();
        });
    });
    
    // Function to update staff count
    function updateStaffCount() {
        const visibleCards = document.querySelectorAll('.col-12[data-role]:not([style*="display: none"])');
        const totalStaff = document.querySelectorAll('.col-12[data-role]').length;
        
        // Update any count displays if they exist
        const countElements = document.querySelectorAll('.staff-count');
        countElements.forEach(element => {
            element.textContent = `${visibleCards.length} of ${totalStaff} staff members`;
        });
    }
    
    // Clear all filters function
    window.clearFilters = function() {
        // Remove active class from all filter buttons
        document.querySelectorAll('.filter-buttons .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Set "All Staff" as active
        const allStaffBtn = document.querySelector('.btn[data-role="all"]');
        if (allStaffBtn) {
            allStaffBtn.classList.add('active');
        }
        
        // Show all staff cards
        document.querySelectorAll('.col-12[data-role]').forEach(card => {
            card.style.display = '';
        });
        
        // Update staff count
        updateStaffCount();
    };
    
    // Enhanced filtering function
    function applyFilters() {
        const activeRoleFilter = document.querySelector('.filter-buttons .btn[data-role].active');
        const activeTypeFilter = document.querySelector('.filter-buttons .btn[data-type].active');
        
        document.querySelectorAll('.col-12[data-role]').forEach(card => {
            let showCard = true;
            
            // Apply role filter
            if (activeRoleFilter && activeRoleFilter.dataset.role !== 'all') {
                if (card.dataset.role !== activeRoleFilter.dataset.role) {
                    showCard = false;
                }
            }
            
            // Apply employment type filter
            if (activeTypeFilter && showCard) {
                if (card.dataset.type !== activeTypeFilter.dataset.type) {
                    showCard = false;
                }
            }
            
            card.style.display = showCard ? '' : 'none';
        });
        
        updateStaffCount();
    }
    
    // Initialize staff count
    updateStaffCount();
});
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>