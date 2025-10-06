<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/MenuItemController.php');

$database = new Database();
$db = $database->getConnection();
$controller = new MenuItemController($db);

// Check permissions
$controller->checkPermissions();

// Check if item ID is provided
if (!isset($_GET['id'])) {
    header('Location: menu_management.php');
    exit();
}

$item_id = $_GET['id'];
$page_title = 'Edit Menu Item';

// Get menu item details
$item = $controller->getMenuItem($item_id);

if (!$item) {
    header('Location: menu_management.php');
    exit();
}

// Handle form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $controller->updateMenuItem($item_id, $_POST, $_FILES['image'] ?? null);
    
    if ($result['success']) {
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = "success";
        header('Location: menu_management.php');
        exit();
    } else {
        $error = $result['message'];
    }
}

// Get all active categories
$categories = $controller->getActiveCategories();

// Include external CSS
$extra_css = '<link href="css/menu_item_forms.css" rel="stylesheet">';

// Start output buffering
ob_start();

// Render the form using controller
echo $controller->renderEditForm($item, $categories, $error);

$content = ob_get_clean();

// Add JavaScript from controller
$extra_js = $controller->getFormJavaScript();

// Include the layout template
include 'includes/layout.php';
?> 