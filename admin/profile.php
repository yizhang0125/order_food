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

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate and update password
    if (!empty($current_password) && !empty($new_password)) {
        if ($new_password === $confirm_password) {
            if ($auth->updatePassword($_SESSION['admin_id'], $current_password, $new_password)) {
                $success_message = "Password updated successfully!";
            } else {
                $error_message = "Current password is incorrect!";
            }
        } else {
            $error_message = "New passwords do not match!";
        }
    }
}

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

.profile-header {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.profile-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
}

.profile-subtitle {
    color: var(--gray-500);
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.profile-section {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.profile-info-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.profile-info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.profile-avatar {
    width: 120px;
    height: 120px;
    background: var(--primary);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.profile-avatar i {
    font-size: 3rem;
    color: white;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    display: flex;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--gray-200);
}

.info-list li:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--gray-500);
    font-weight: 500;
    width: 120px;
}

.info-value {
    color: var(--gray-800);
    font-weight: 600;
}

.password-form {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
}

.form-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-200);
}

.password-toggle {
    cursor: pointer;
    position: absolute;
    right: 1rem;
    top: 65%;
    transform: translateY(-50%);
    color: var(--gray-500);
    transition: color 0.3s ease;
}

.password-toggle:hover {
    color: var(--gray-700);
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

.btn-primary {
    background: var(--primary);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
}
</style>';

// Start output buffering
ob_start();
?>

<div class="container-fluid">
    <!-- Profile Header -->
    <div class="profile-header">
        <h1 class="profile-title">My Profile</h1>
        <p class="profile-subtitle">Manage your account settings and security preferences</p>
    </div>

    <?php if ($success_message || $error_message): ?>
    <div class="alert alert-<?php echo $success_message ? 'success' : 'danger'; ?>" role="alert">
        <i class="fas fa-<?php echo $success_message ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo $success_message ?: $error_message; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile Info Card -->
        <div class="col-12 col-lg-4">
            <div class="profile-info-card">
                <div class="text-center">
                    <div class="profile-avatar mx-auto">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></h4>
                    <p class="text-muted">Administrator</p>
                </div>
                <ul class="info-list mt-4">
                    <li>
                        <span class="info-label">Email</span>
                        <span class="info-value">admin@example.com</span>
                    </li>
                    <li>
                        <span class="info-label">Role</span>
                        <span class="info-value">Administrator</span>
                    </li>
                    <li>
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?php echo date('F Y'); ?></span>
                    </li>
                    <li>
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            <span class="badge bg-success">Active</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Password Change Form -->
        <div class="col-12 col-lg-8">
            <div class="password-form">
                <h3 class="form-title">Change Password</h3>
                <form method="POST" action="">
                    <div class="mb-4 position-relative">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('current_password')"></i>
                    </div>

                    <div class="mb-4 position-relative">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                    </div>

                    <div class="mb-4 position-relative">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Add JavaScript for animations and interactions
$extra_js = '
<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling;
    
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

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

    // Animate cards on load
    const cards = document.querySelectorAll(".profile-info-card, .password-form");
    cards.forEach((card, index) => {
        card.style.opacity = "0";
        card.style.transform = "translateY(20px)";
        setTimeout(() => {
            card.style.transition = "all 0.3s ease";
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
        }, index * 100);
    });
});
</script>';

include 'includes/layout.php';
?> 