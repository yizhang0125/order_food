<?php
session_start();
require_once(__DIR__ . '/config/Database.php');
require_once(__DIR__ . '/classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$orderModel = new Order($db);

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
$order = $order_id ? $orderModel->getOrder($order_id) : null;

// Get table number from URL
$table_number = isset($_GET['table']) ? htmlspecialchars($_GET['table']) : null;

// Get token from URL
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1d4ed8;
            --success-color: #10b981;
            --background-color: #f8fafc;
            --text-color: #1e293b;
            --card-background: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            padding-top: 80px;
            color: var(--text-color);
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color) !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            font-size: 1.8rem;
        }

        /* Table Number Display Styles */
        .table-info-banner {
            position: static;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8));
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin: 0 15px;
            transition: all 0.3s ease;
        }

        .table-info-banner:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .table-info-banner .table-number {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        /* View Orders Button Style */
        .view-orders-btn {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .view-orders-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .view-orders-btn i {
            font-size: 1rem;
        }

        .confirmation-container {
            background: var(--card-background);
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 600px;
            margin: 2rem auto;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon i {
            color: white;
            font-size: 2.5rem;
        }

        .confirmation-title {
            color: var(--success-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .order-details {
            background: #f8fafc;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }

        .order-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .back-to-menu {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-to-menu:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            color: white;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .order-items {
            margin-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .order-total {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .view-orders-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .view-orders-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            color: white;
            background: var(--secondary-color);
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .view-orders-btn,
            .back-to-menu {
                width: 100%;
                justify-content: center;
            }
            
            .table-info-banner {
                margin: 0 10px;
                padding: 6px 12px;
            }

            .table-info-banner .table-number {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .table-info-banner {
                margin: 0 8px;
                padding: 4px 10px;
            }

            .table-info-banner .table-number {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php<?php echo $table_number && $token ? '?table=' . $table_number . '&token=' . $token : ''; ?>">
                <i class="fas fa-utensils"></i>
                Gourmet Delights
            </a>
            <div class="d-flex align-items-center gap-3">
                <?php if ($table_number): ?>
                <div class="table-info-banner">
                    <span class="table-number">Table <?php echo $table_number; ?></span>
                </div>
                <?php if ($token): ?>
                <a href="view_orders.php?table=<?php echo $table_number; ?>&token=<?php echo $token; ?>" class="view-orders-btn">
                    <i class="fas fa-list-ul"></i>
                    View Orders
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Confirmation Content -->
    <div class="container">
        <div class="confirmation-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="confirmation-title">Order Confirmed!</h1>
            <p class="mb-4">Thank you for your order. Your food will be prepared shortly.</p>
            
            <?php if ($order): ?>
            <div class="order-details">
                <div class="order-number">
                    Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="detail-row">
                    <span>Table Number:</span>
                    <span>Table <?php echo htmlspecialchars($order['table_number']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Order Time:</span>
                    <span><?php echo date('h:i A', strtotime($order['created_at'])); ?></span>
                </div>
                
                <div class="order-items">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="order-item">
                        <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?></span>
                        <span>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="order-total">
                        <div class="detail-row">
                            <span>Total:</span>
                            <span>RM <?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons mt-4">
                <a href="view_orders.php<?php echo ($table_number && $token) ? '?table=' . $table_number . '&token=' . $token : ''; ?>" class="view-orders-btn">
                    <i class="fas fa-list-ul"></i>
                    View All Orders
                </a>
                
                <a href="index.php<?php echo ($table_number && $token) ? '?table=' . $table_number . '&token=' . $token : ''; ?>" class="back-to-menu">
                    <i class="fas fa-arrow-left"></i>
                    Back to Menu
                </a>
            </div>
        </div>
    </div>

    <script>
        // Clear cart after successful order
        localStorage.removeItem('cart');
    </script>
</body>
</html> 