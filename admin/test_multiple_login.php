<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Multiple Login Test';
ob_start();
?>

<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-users"></i>
            Multiple Login Test
        </h1>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Current Session Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>Session ID</th>
                            <td><?php echo session_id(); ?></td>
                        </tr>
                        <tr>
                            <th>User Type</th>
                            <td><?php echo $_SESSION['user_type'] ?? 'Not set'; ?></td>
                        </tr>
                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                        <tr>
                            <th>Admin Username</th>
                            <td><?php echo $_SESSION['admin_username'] ?? 'Not set'; ?></td>
                        </tr>
                        <?php elseif ($_SESSION['user_type'] === 'staff'): ?>
                        <tr>
                            <th>Staff Name</th>
                            <td><?php echo $_SESSION['staff_name'] ?? 'Not set'; ?></td>
                        </tr>
                        <tr>
                            <th>Employee Number</th>
                            <td><?php echo $_SESSION['staff_employee_number'] ?? 'Not set'; ?></td>
                        </tr>
                        <tr>
                            <th>Position</th>
                            <td><?php echo $_SESSION['staff_position'] ?? 'Not set'; ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo $_SESSION['staff_email'] ?? 'Not set'; ?></td>
                        </tr>
                        <tr>
                            <th>Permissions</th>
                            <td>
                                <?php 
                                $permissions = $_SESSION['staff_permissions'] ?? [];
                                if (empty($permissions)) {
                                    echo 'No permissions';
                                } else {
                                    echo '<ul class="mb-0">';
                                    foreach ($permissions as $permission) {
                                        echo '<li>' . htmlspecialchars($permission) . '</li>';
                                    }
                                    echo '</ul>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>All Staff Members</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $query = "SELECT s.*, GROUP_CONCAT(p.name) as permissions 
                                 FROM staff s 
                                 LEFT JOIN staff_permissions sp ON s.id = sp.staff_id 
                                 LEFT JOIN permissions p ON sp.permission_id = p.id 
                                 GROUP BY s.id 
                                 ORDER BY s.name";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $all_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($all_staff)) {
                            echo '<p class="text-muted">No staff members found.</p>';
                        } else {
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-sm">';
                            echo '<thead><tr><th>Name</th><th>Employee #</th><th>Position</th><th>Status</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($all_staff as $staff) {
                                $is_current_user = ($_SESSION['user_type'] === 'staff' && $_SESSION['staff_id'] == $staff['id']);
                                $row_class = $is_current_user ? 'table-success' : '';
                                echo '<tr class="' . $row_class . '">';
                                echo '<td>' . htmlspecialchars($staff['name']) . ($is_current_user ? ' <strong>(You)</strong>' : '') . '</td>';
                                echo '<td>' . htmlspecialchars($staff['employee_number']) . '</td>';
                                echo '<td>' . ucfirst(htmlspecialchars($staff['position'])) . '</td>';
                                echo '<td>';
                                if ($staff['is_active']) {
                                    echo '<span class="badge bg-success">Active</span>';
                                } else {
                                    echo '<span class="badge bg-danger">Inactive</span>';
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Error loading staff: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Test Instructions</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>How to test multiple staff logins:</h6>
                        <ol>
                            <li>Open this page in multiple browser windows/tabs or different browsers</li>
                            <li>Login with different staff credentials in each window</li>
                            <li>Each staff member should be able to login simultaneously</li>
                            <li>Check that each session shows different user information</li>
                        </ol>
                    </div>
                    
                    <h6>Test Credentials:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Manager</h6>
                                    <p><strong>Employee Number:</strong> EMP001<br>
                                    <strong>Email:</strong> john.manager@restaurant.com<br>
                                    <strong>Password:</strong> password123</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Waiter</h6>
                                    <p><strong>Employee Number:</strong> EMP002<br>
                                    <strong>Email:</strong> sarah.waiter@restaurant.com<br>
                                    <strong>Password:</strong> password123</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title i {
    color: #4f46e5;
}

.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
}

.card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    border-radius: 16px 16px 0 0 !important;
}

.card-header h5 {
    margin: 0;
    color: #1e293b;
    font-weight: 600;
}
</style>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
