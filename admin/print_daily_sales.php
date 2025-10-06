<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/classes/ReportController.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$reportController = new ReportController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if user has permission to view reports
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('view_reports', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php');
    exit();
}

// Get date from query parameters or default to today
$report_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Generate daily report using controller
    $report_data = $reportController->generateDailyReport($report_date);
    
    // Extract data from report
    $daily_summary = $report_data['summary'];
    $hourly_sales = $report_data['hourly_sales'];
    $top_items = $report_data['top_items'];
    $table_sales = $report_data['table_sales'];
    $order_status = $report_data['order_status'];
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Set content type for printing
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Sales Report - <?php echo date('F j, Y', strtotime($report_date)); ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-break { page-break-before: always; }
            body { font-size: 12px; margin: 0; padding: 10px; }
            .container { max-width: 100% !important; }
        }
        
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: white;
            color: #333;
        }
        
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid #2563eb; 
            padding-bottom: 20px; 
        }
        
        .header h1 { 
            color: #2563eb; 
            margin: 0; 
            font-size: 28px;
            font-weight: bold;
        }
        
        .header h2 { 
            color: #666; 
            margin: 10px 0 0 0; 
            font-size: 18px;
        }
        
        .header p {
            color: #888;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        .summary-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        
        .summary-card { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center; 
            border-left: 4px solid #2563eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-card.success { border-left-color: #059669; }
        .summary-card.warning { border-left-color: #d97706; }
        .summary-card.danger { border-left-color: #dc2626; }
        
        .summary-value { 
            font-size: 24px; 
            font-weight: bold; 
            color: #333; 
            margin-bottom: 5px; 
        }
        
        .summary-label { 
            color: #666; 
            font-size: 14px; 
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .section { 
            margin-bottom: 30px; 
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-title { 
            font-size: 18px; 
            font-weight: bold; 
            color: #333; 
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }
        
        .section-content {
            padding: 20px;
        }
        
        .payment-methods { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 20px; 
            margin-bottom: 20px; 
        }
        
        .payment-card { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 8px; 
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .payment-value { 
            font-size: 18px; 
            font-weight: bold; 
            color: #333; 
        }
        
        .payment-label { 
            color: #666; 
            font-size: 12px; 
            margin-top: 5px;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
        }
        
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px 8px; 
            text-align: left; 
        }
        
        th { 
            background-color: #f8f9fa; 
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
            font-size: 12px;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .badge { 
            padding: 4px 8px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: bold; 
        }
        
        .bg-primary { background-color: #2563eb; color: white; }
        .bg-success { background-color: #059669; color: white; }
        .bg-warning { background-color: #d97706; color: white; }
        .bg-danger { background-color: #dc2626; color: white; }
        .bg-info { background-color: #0284c7; color: white; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .footer { 
            text-align: center; 
            margin-top: 30px; 
            color: #666; 
            font-size: 12px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .print-btn { 
            background: #2563eb; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .print-btn:hover { 
            background: #1d4ed8; 
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 20px;
            margin-left: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
        <a href="daily_sales_report.php?date=<?php echo $report_date; ?>" class="back-btn">← Back to Full Report</a>
    </div>

    <?php if (isset($error_message)): ?>
    <div style="color: red; padding: 20px; border: 1px solid red; border-radius: 8px; margin-bottom: 20px;">
        <h3>Error</h3>
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
    <?php else: ?>

    <!-- Header -->
    <div class="header">
        <h1>Daily Sales Report</h1>
        <h2><?php echo date('l, F j, Y', strtotime($report_date)); ?></h2>
        <p>Generated on: <?php echo date('M j, Y \a\t g:i A'); ?></p>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-value"><?php echo $reportController->formatCurrency($daily_summary['total_sales'] ?? 0); ?></div>
            <div class="summary-label">Total Sales</div>
        </div>
        <div class="summary-card success">
            <div class="summary-value"><?php echo $reportController->formatNumber($daily_summary['total_transactions'] ?? 0); ?></div>
            <div class="summary-label">Total Transactions</div>
        </div>
        <div class="summary-card warning">
            <div class="summary-value"><?php echo $reportController->formatCurrency($daily_summary['average_sale'] ?? 0); ?></div>
            <div class="summary-label">Average Sale</div>
        </div>
        <div class="summary-card danger">
            <div class="summary-value"><?php echo $reportController->formatNumber($daily_summary['tables_served'] ?? 0); ?></div>
            <div class="summary-label">Tables Served</div>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="section">
        <h3 class="section-title">Payment Methods Breakdown</h3>
        <div class="section-content">
            <div class="payment-methods">
                <div class="payment-card">
                    <div class="payment-value"><?php echo $reportController->formatCurrency($daily_summary['cash_total'] ?? 0); ?></div>
                    <div class="payment-label">Cash (<?php echo $reportController->formatNumber($daily_summary['cash_transactions'] ?? 0); ?> transactions)</div>
                </div>
                <div class="payment-card">
                    <div class="payment-value"><?php echo $reportController->formatCurrency($daily_summary['card_total'] ?? 0); ?></div>
                    <div class="payment-label">Card (<?php echo $reportController->formatNumber($daily_summary['card_transactions'] ?? 0); ?> transactions)</div>
                </div>
                <div class="payment-card">
                    <div class="payment-value"><?php echo $reportController->formatCurrency($daily_summary['tng_total'] ?? 0); ?></div>
                    <div class="payment-label">Touch 'n Go (<?php echo $reportController->formatNumber($daily_summary['tng_transactions'] ?? 0); ?> transactions)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Items -->
    <div class="section print-break">
        <h3 class="section-title">Top Selling Items</h3>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Item Name</th>
                        <th>Unit Price</th>
                        <th>Quantity Sold</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_items)): ?>
                    <tr><td colspan="5" class="no-data">No sales data available</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_items as $index => $item): ?>
                        <tr>
                            <td class="text-center"><span class="badge bg-primary"><?php echo $index + 1; ?></span></td>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td class="text-right"><?php echo $reportController->formatCurrency($item['unit_price']); ?></td>
                            <td class="text-center"><?php echo $reportController->formatNumber($item['quantity_sold']); ?></td>
                            <td class="text-right"><?php echo $reportController->formatCurrency($item['total_sales']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sales by Table -->
    <div class="section">
        <h3 class="section-title">Sales by Table</h3>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Table Number</th>
                        <th>Transactions</th>
                        <th>Total Sales</th>
                        <th>First Order</th>
                        <th>Last Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($table_sales)): ?>
                    <tr><td colspan="5" class="no-data">No table sales data available</td></tr>
                    <?php else: ?>
                        <?php foreach ($table_sales as $table): ?>
                        <tr>
                            <td>Table <?php echo htmlspecialchars($table['table_number']); ?></td>
                            <td class="text-center"><?php echo $reportController->formatNumber($table['transaction_count']); ?></td>
                            <td class="text-right"><?php echo $reportController->formatCurrency($table['total_sales']); ?></td>
                            <td class="text-center"><?php echo date('g:i A', strtotime($table['first_order'])); ?></td>
                            <td class="text-center"><?php echo date('g:i A', strtotime($table['last_order'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Order Status Breakdown -->
    <div class="section">
        <h3 class="section-title">Order Status Breakdown</h3>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Total Amount</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_orders = array_sum(array_column($order_status, 'count'));
                    foreach ($order_status as $status): 
                        $percentage = $reportController->calculatePercentage($status['count'], $total_orders);
                        $badge_class = $reportController->getStatusBadgeClass($status['status']);
                    ?>
                    <tr>
                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status['status']); ?></span></td>
                        <td class="text-center"><?php echo $reportController->formatNumber($status['count']); ?></td>
                        <td class="text-right"><?php echo $reportController->formatCurrency($status['total_amount']); ?></td>
                        <td class="text-center"><?php echo number_format($percentage, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated on <?php echo date('F j, Y \a\t g:i A'); ?> for <?php echo date('l, F j, Y', strtotime($report_date)); ?></p>
        <p>Restaurant Management System - Daily Sales Report</p>
    </div>

    <script>
        // Auto-print when page loads (optional - uncomment if needed)
        // window.onload = function() { 
        //     setTimeout(function() { window.print(); }, 1000); 
        // }
    </script>
</body>
</html>


