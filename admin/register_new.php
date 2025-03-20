<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            if ($auth->registerAdmin($username, $email, $password)) {
            $success = "Registration successful! You can now login.";
        } else {
                $error = "Username or email already exists";
            }
        } catch(PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --background: #2c3e50;
            --card-bg: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
            background: linear-gradient(135deg, #2c3e50, #3498db);
        }

        .background-animation::before {
            content: '';
            position: absolute;
            width: 150%;
            height: 150%;
            top: -25%;
            left: -25%;
            background: 
                radial-gradient(circle at 25% 25%, rgba(52, 152, 219, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(41, 128, 185, 0.2) 0%, transparent 50%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(180deg); }
        }

        .register-container {
            width: 100%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            display: flex;
            flex-direction: row;
            backdrop-filter: blur(10px);
            z-index: 1;
            animation: container-appear 0.6s ease-out;
            margin: 1rem auto;
            min-height: min-content;
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
            background: linear-gradient(rgba(44, 62, 80, 0.85), rgba(44, 62, 80, 0.85)), 
                        url('https://images.unsplash.com/photo-1514933651103-005eec06c04b?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1474&q=80');
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
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
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
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .header-text {
            font-size: 1.1rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.95);
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .register-form {
            padding: 2rem;
            width: 55%;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            background: white;
            overflow-y: auto;
            max-height: 90vh;
            scrollbar-width: thin;
            scrollbar-color: var(--secondary) transparent;
        }

        .register-form::-webkit-scrollbar {
            width: 6px;
        }

        .register-form::-webkit-scrollbar-track {
            background: transparent;
        }

        .register-form::-webkit-scrollbar-thumb {
            background-color: var(--secondary);
            border-radius: 20px;
        }

        .form-header {
            margin-bottom: 3rem;
        }

        .form-header h2 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin: 0;
        }

        .form-group {
            margin-bottom: 2rem;
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
            display: flex;
            align-items: center;
        }

        .input-group i.fas.fa-lock,
        .input-group i.fas.fa-user,
        .input-group i.fas.fa-envelope {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
            font-size: 1rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .form-control {
            width: 100%;
            padding: 1rem 3rem 1rem 3.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8fafc;
            height: auto;
        }

        .form-control:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .form-control:focus {
            border-color: var(--secondary);
            background: white;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
            outline: none;
        }

        .form-control:focus + i {
            color: var(--secondary);
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 2;
            background: none;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .password-toggle:hover {
            color: var(--secondary);
        }

        .btn-register {
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
            box-shadow: 0 4px 6px -1px rgba(44, 62, 80, 0.2);
            margin-bottom: 1.5rem;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -3px rgba(44, 62, 80, 0.4);
        }

        .login-link {
            text-align: center;
            color: var(--text-secondary);
        }

        .login-link a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: var(--primary);
        }

        .alert {
            padding: 1.25rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1rem;
            animation: alert-slide-down 0.5s ease-out;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
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

        @media (min-width: 1400px) {
            .register-container {
                max-width: 1200px;
                min-height: 700px;
            }
        }

        @media (min-width: 992px) and (max-width: 1399px) {
            .register-container {
                max-width: 1000px;
                min-height: 600px;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            body {
                align-items: flex-start;
                padding: 1rem;
            }

            .register-container {
                flex-direction: column;
                max-width: 700px;
                margin: 1rem auto;
            }

            .header-section,
            .register-form {
                width: 100%;
                padding: 2rem;
            }

            .header-section {
                min-height: 250px;
            }

            .register-form {
                max-height: none;
                overflow-y: visible;
            }
        }

        @media (min-width: 576px) and (max-width: 767px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
            }

            .register-container {
                flex-direction: column;
                margin: 0.5rem;
                border-radius: 15px;
            }

            .header-section,
            .register-form {
                width: 100%;
                padding: 1.5rem;
            }

            .header-section {
                min-height: 200px;
            }

            .register-form {
                max-height: none;
                overflow-y: visible;
            }

            .form-control {
                font-size: 16px; /* Prevents zoom on mobile */
            }
        }

        @media (max-width: 575px) {
            body {
                padding: 0;
                align-items: flex-start;
                min-height: 100vh;
            }

            .register-container {
                flex-direction: column;
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }

            .header-section {
                width: 100%;
                height: 100vh; /* Full viewport height */
                padding: 2rem 1.25rem;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background: linear-gradient(rgba(44, 62, 80, 0.85), rgba(44, 62, 80, 0.85)), 
                            url('https://images.unsplash.com/photo-1514933651103-005eec06c04b?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1474&q=80');
                background-size: cover;
                background-position: center;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1;
            }

            .brand-logo {
                width: 80px;
                height: 80px;
                margin: 0 auto 2rem;
            }

            .header-title {
                font-size: 2.5rem;
                margin-bottom: 1.5rem;
                text-align: center;
            }

            .header-text {
                font-size: 1.1rem;
                line-height: 1.6;
                text-align: center;
                margin-bottom: 0;
                max-width: 90%;
                margin: 0 auto;
            }

            .register-form {
                width: 100%;
                min-height: 100vh;
                padding: 2rem 1.5rem;
                margin-top: 100vh; /* Push form below header */
                position: relative;
                z-index: 2;
                background: white;
                border-radius: 30px 30px 0 0;
                box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.1);
            }

            .form-header {
                text-align: center;
                margin-bottom: 2rem;
            }

            .form-header h2 {
                font-size: 2rem;
            }

            .form-header p {
                font-size: 1.1rem;
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-control {
                padding: 1rem 3rem 1rem 3.5rem;
                font-size: 16px;
                height: 56px;
            }

            .password-toggle {
                right: 1.25rem;
                padding: 0;
                font-size: 1.2rem;
            }

            .btn-register {
                padding: 1rem;
                font-size: 1.1rem;
                height: 56px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                margin-top: 1rem;
            }
        }

        /* Small Mobile Devices */
        @media (max-width: 374px) {
            .header-section {
                padding: 1.5rem 1rem;
            }

            .brand-logo {
                width: 70px;
                height: 70px;
                margin-bottom: 1.5rem;
            }

            .header-title {
                font-size: 2rem;
            }

            .header-text {
                font-size: 1rem;
            }

            .register-form {
                padding: 1.5rem 1rem;
            }

            .form-header h2 {
                font-size: 1.75rem;
            }
        }

        /* Fix for very tall mobile screens */
        @media (min-height: 700px) and (max-width: 575px) {
            .header-section {
                height: 100vh;
            }

            .register-form {
                margin-top: 100vh;
            }
        }

        /* Add smooth scrolling for the entire page */
        html {
            scroll-behavior: smooth;
        }

        @media (max-height: 500px) and (orientation: landscape) {
            .register-container {
                flex-direction: row;
            }

            .header-section {
                width: 40%;
                padding: 1.5rem;
                min-height: 100vh;
            }

            .register-form {
                width: 60%;
                height: 100vh;
                overflow-y: auto;
            }

            .header-text {
                display: none;
            }
        }

        @media (hover: none) {
            .form-control,
            .btn-register,
            .password-toggle {
                cursor: default;
            }

            .form-control:hover {
                background: #f8fafc;
                border-color: #e2e8f0;
            }

            .btn-register:hover {
                transform: none;
                box-shadow: 0 4px 6px -1px rgba(44, 62, 80, 0.2);
            }
        }

        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .header-section {
                background-image: url('https://images.unsplash.com/photo-1514933651103-005eec06c04b?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=2948&q=80');
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .register-container,
            .background-animation::before,
            .btn-register,
            .form-control,
            .password-toggle {
                animation: none;
                transition: none;
            }
        }

        @media (prefers-color-scheme: dark) {
            .form-control {
                background: #f8fafc;
            }

            .alert-danger {
                background: #fef2f2;
            }

            .alert-success {
                background: #f0fdf4;
            }
        }
    </style>
</head>
<body>
    <div class="background-animation"></div>

        <div class="register-container">
        <div class="header-section">
            <div class="brand-logo">
                <i class="fas fa-utensils"></i>
            </div>
            <h1 class="header-title">Join FoodAdmin</h1>
            <p class="header-text">Create your admin account to access the restaurant management system. Take control of your restaurant operations with our comprehensive dashboard.</p>
        </div>

        <div class="register-form">
            <div class="form-header">
                <h2>Create Account</h2>
                <p>Fill in your details to get started</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Choose a username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <i class="fas fa-user"></i>
                    </div>
                        </div>
                        
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="Enter your email" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                        </div>
                        
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Create a password" required>
                        <i class="fas fa-lock"></i>
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </span>
                        </div>
                        </div>
                        
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm your password" required>
                        <i class="fas fa-lock"></i>
                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                        </div>
                </div>

                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>

                <p class="login-link">
                    Already have an account? <a href="login.php">Sign In</a>
                </p>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth scroll behavior
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.register-form');
            
            // Smooth scroll to form fields when they receive focus
            const inputs = form.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    if (window.innerWidth <= 768) {
                        const rect = this.getBoundingClientRect();
                        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                        const targetY = rect.top + scrollTop - 100; // 100px padding from top
                        
                        window.scrollTo({
                            top: targetY,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Handle form scroll on keyboard show/hide for mobile devices
            let lastScrollPosition = 0;
            window.addEventListener('resize', function() {
                if (document.activeElement.tagName === 'INPUT') {
                    if (window.innerHeight < lastScrollPosition) {
                        // Keyboard is shown
                        document.activeElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                    lastScrollPosition = window.innerHeight;
                }
            });
        });

        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

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