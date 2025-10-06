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
    <link href="css/login.css" rel="stylesheet">
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
    <script src="js/login.js"></script>
</body>
</html>
    <style>
        :root {
            --primary: #4f46e5;
            --secondary: #6366f1;
            --accent: #818cf8;
            --background: #0f172a;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #4f46e5;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
            background: linear-gradient(45deg, #4f46e5, #6366f1);
        }

        .background-animation::before,
        .background-animation::after {
            content: '';
            position: absolute;
            width: 150%;
            height: 150%;
            top: -25%;
            left: -25%;
            z-index: -1;
        }

        .background-animation::before {
            background: radial-gradient(circle at center, transparent 30%, rgba(99, 102, 241, 0.2) 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        .background-animation::after {
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 30%);
            animation: float 20s linear infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1) rotate(0deg); opacity: 0.5; }
            50% { transform: scale(1.2) rotate(180deg); opacity: 0.8; }
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-20px) rotate(360deg); }
        }

        .wave {
            position: absolute;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), transparent);
        }

        .wave:nth-child(1) {
            top: -50%;
            left: -50%;
            animation: wave1 15s linear infinite;
        }

        .wave:nth-child(2) {
            top: -45%;
            left: -45%;
            animation: wave2 18s linear infinite;
        }

        .wave:nth-child(3) {
            top: -55%;
            left: -55%;
            animation: wave3 21s linear infinite;
        }

        @keyframes wave1 {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes wave2 {
            0% { transform: rotate(120deg); }
            100% { transform: rotate(480deg); }
        }

        @keyframes wave3 {
            0% { transform: rotate(240deg); }
            100% { transform: rotate(600deg); }
        }

        .particles {
            display: none;
        }

        .login-container {
            width: 100%;
            max-width: 1200px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            display: flex;
            backdrop-filter: blur(20px);
            z-index: 1;
            animation: container-appear 0.6s ease-out;
            margin: 2rem auto;
        }

        @keyframes container-appear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-section {
            background: linear-gradient(rgba(99, 91, 255, 0.85), rgba(99, 91, 255, 0.85)), 
                        url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1470&q=80');
            background-size: cover;
            background-position: center;
            padding: 4rem;
            color: white;
            width: 45%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            animation: header-appear 0.8s ease-out;
        }

        @keyframes header-appear {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.1),
                transparent
            );
            z-index: 1;
        }

        .header-content {
            position: relative;
            z-index: 2;
            padding: 2rem;
            background: rgba(99, 91, 255, 0.2);
            border-radius: 20px;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .brand-logo i {
            font-size: 2.5rem;
            color: white;
        }

        .header-title {
            font-size: 2.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: white;
            line-height: 1.2;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-text {
            font-size: 1.1rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.95);
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .login-form {
            padding: 4rem;
            width: 55%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
            animation: form-slide-in 0.8s ease-out;
        }

        @keyframes form-slide-in {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-header {
            margin-bottom: 3rem;
            animation: header-fade-in 1s ease-out;
        }

        @keyframes header-fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-header h2 {
            color: var(--text-primary);
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradient-text 3s ease infinite;
        }

        @keyframes gradient-text {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 1.15rem;
            margin: 0;
        }

        .form-group {
            margin-bottom: 2rem;
            animation: form-group-slide-up 0.6s ease-out;
        }

        @keyframes form-group-slide-up {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-label {
            display: block;
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .form-control {
            width: 100%;
            padding: 1.25rem 1.25rem 1.25rem 3.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-control:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
            outline: none;
        }

        .form-control:focus + i {
            color: var(--secondary);
            transform: translateY(-50%) scale(1.1);
        }

        .btn-login {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.15rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: 0.5s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -3px rgba(79, 70, 229, 0.4);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .alert {
            background: #fef2f2;
            border: none;
            color: #dc2626;
            padding: 1.25rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1rem;
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.1);
            animation: alert-slide-down 0.5s ease-out;
        }

        @keyframes alert-slide-down {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 992px) {
            body {
                padding: 1rem;
            }

            .login-container {
                flex-direction: column;
                max-width: 600px;
                margin: 1rem auto;
            }
            
            .header-section,
            .login-form {
                width: 100%;
                padding: 3rem 2rem;
            }
            
            .header-section {
                text-align: center;
            }
            
            .brand-logo {
                margin: 0 auto 1.5rem;
            }

            .header-title {
                font-size: 2.25rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 0.5rem;
            }
            
            .login-container {
                margin: 0.5rem;
                border-radius: 20px;
            }
            
            .header-section,
            .login-form {
                padding: 2rem 1.5rem;
            }

            .header-title {
                font-size: 2rem;
            }

            .header-text {
                font-size: 1rem;
            }

            .form-header h2 {
                font-size: 1.75rem;
            }
        }

        /* Add smooth scrolling for the entire page */
        html {
            scroll-behavior: smooth;
        }

        /* Ensure content is scrollable on mobile */
        @media (max-width: 768px) {
            body {
                min-height: unset;
                height: auto;
            }

            .login-container {
                margin: 1rem auto;
            }

            .header-section {
                min-height: unset;
            }
        }

        /* Fix for very tall mobile screens */
        @media (min-height: 800px) {
            body {
                align-items: center;
            }
        }

        /* Fix for landscape mode */
        @media (max-height: 500px) and (orientation: landscape) {
            body {
                padding: 1rem;
            }

            .login-container {
                flex-direction: row;
                height: auto;
            }

            .header-section {
                width: 45%;
            }

            .login-form {
                width: 55%;
            }
        }
    </style>
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