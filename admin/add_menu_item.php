<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/MenuItemController.php');

$database = new Database();
$db = $database->getConnection();
$controller = new MenuItemController($db);

// Check permissions
$controller->checkPermissions();

$page_title = 'Add Menu Item';
$error = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $controller->createMenuItem($_POST, $_FILES['image'] ?? null);
    
    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
        header('Location: menu_management.php');
        exit();
    } else {
        $error = $result['message'];
        $formData = $_POST; // Keep form data for re-display
    }
}

// Get all active categories for the dropdown
$categories = $controller->getActiveCategories();

// Include external CSS
$extra_css = '<link href="css/menu_item_forms.css" rel="stylesheet">';

// Start output buffering
ob_start();

// Render the form using controller
echo $controller->renderAddForm($categories, $formData, $error);

$content = ob_get_clean();

// Add JavaScript from controller
$extra_js = $controller->getFormJavaScript();

// Include the layout template
include 'includes/layout.php';
?> 