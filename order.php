<?php
require_once 'config/Database.php';
require_once 'classes/Table.php';
require_once 'classes/Order.php';
require_once 'classes/MenuItem.php';

$database = new Database();
$db = $database->getConnection();

$table = new Table($db);
$order = new Order($db);
$menuItem = new MenuItem($db);

// Get table token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$table_info = $table->validateToken($token);

if (!$table_info) {
    die("Invalid or expired QR code");
}

$success = false;

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order'])) {
    $order_items = $_POST['order'];
    
    // Create new order
    $order_id = $order->createOrder($table_info['id']);
    
    if ($order_id) {
        // Add order items and get total
        $total = $order->addOrderItems($order_id, $order_items);
        if ($total > 0) {
            $success = true;
        }
    }
}

// Get menu items grouped by category
$menu_by_category = $menuItem->getItemsByCategory();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Your Order - Table <?php echo htmlspecialchars($table_info['table_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .quantity-input {
            width: 60px;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <h1 class="text-center mb-4">Table <?php echo htmlspecialchars($table_info['table_number']); ?></h1>
        
        <?php if($success): ?>
        <div class="alert alert-success">
            Your order has been placed successfully! Our staff will bring your food shortly.
        </div>
        <?php endif; ?>

        <form method="POST" id="orderForm">
            <?php foreach($menu_by_category as $category => $items): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($category); ?></h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                                    <td>$<?php echo htmlspecialchars($item['price']); ?></td>
                                    <td>
                                        <input type="number" 
                                               class="form-control quantity-input" 
                                               name="order[<?php echo $item['id']; ?>]" 
                                               value="0" 
                                               min="0" 
                                               max="10">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">Place Order</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            let hasItems = false;
            const inputs = document.querySelectorAll('.quantity-input');
            inputs.forEach(input => {
                if (parseInt(input.value) > 0) {
                    hasItems = true;
                }
            });
            
            if (!hasItems) {
                e.preventDefault();
                alert('Please select at least one item to order.');
            }
        });
    </script>
</body>
</html> 