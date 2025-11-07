<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        // Try admin login first
        $admin_query = "SELECT * FROM admins WHERE username = :username";
        $stmt = $db->prepare($admin_query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['user_type'] = 'admin';
            header('Location: dashboard.php');
            exit();
        }

        // If not admin, try staff login with employee number or email
        $staff_query = "SELECT s.*, GROUP_CONCAT(p.name) as permissions 
                       FROM staff s 
                       LEFT JOIN staff_permissions sp ON s.id = sp.staff_id 
                       LEFT JOIN permissions p ON sp.permission_id = p.id 
                       WHERE s.employee_number = :username OR s.email = :username
                       GROUP BY s.id";
        $stmt = $db->prepare($staff_query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff) {
            if (password_verify($password, $staff['password'])) {
                if ($staff['is_active']) {
                    // Set session variables for staff
                    $_SESSION['staff_id'] = $staff['id'];
                    $_SESSION['staff_name'] = $staff['name'];
                    $_SESSION['staff_position'] = $staff['position'];
                    $_SESSION['staff_permissions'] = $staff['permissions'] ? explode(',', $staff['permissions']) : [];
                    $_SESSION['user_type'] = 'staff';
                    $_SESSION['staff_email'] = $staff['email'];
                    $_SESSION['staff_employee_number'] = $staff['employee_number'];
                    
                    // Log successful login
                    error_log("Staff login successful: {$staff['name']} (ID: {$staff['id']}) - Employee: {$staff['employee_number']}");
                    
                    // Redirect based on position and permissions
                    if (in_array('all', $_SESSION['staff_permissions']) || in_array('view_dashboard', $_SESSION['staff_permissions'])) {
                        header('Location: dashboard.php');
                    } else {
                        switch($staff['position']) {
                            case 'kitchen':
                                header('Location: kitchen.php');
                                break;
                            case 'waiter':
                                header('Location: orders.php');
                                break;
                            default:
                                header('Location: dashboard.php');
                        }
                    }
                    exit();
                } else {
                    $error = "Your account is inactive. Please contact administrator.";
                }
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Invalid login credentials. Staff should use employee number or email, admins should use username.";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../admin/css/login.css">

</head>
<body>
    <div class="background-animation">
        <div class="wave"></div>
        <div class="wave"></div>
        <div class="wave"></div>
    </div>

        <div class="login-container">
        <div class="header-section">
            <div class="brand-logo">
                <i class="fas fa-utensils"></i>
            </div>
            <h1 class="header-title">FoodAdmin</h1>
            <p class="header-text">Welcome to Restaurant Management System. Streamline your restaurant operations with our comprehensive admin dashboard. Manage orders, track inventory, and boost your business efficiency.</p>
        </div>

        <div class="login-form">
            <div class="form-header">
                <h2>Welcome Back!</h2>
                <p>Sign in to your account</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                <div class="form-group">
                    <label class="form-label" for="username">Username or Employee Number</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Enter username (admin) or employee number (staff)" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <i class="fas fa-user"></i>
                    </div>
                        </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <i class="fas fa-lock"></i>
                        </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>