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

// Function to print daily report
function printDailyReport($reportController, $report_date, $daily_summary, $hourly_sales, $top_items, $table_sales, $order_status) {
    echo "<!DOCTYPE html>\n";
    echo "<html lang='en'>\n";
    echo "<head>\n";
    echo "    <meta charset='UTF-8'>\n";
    echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
    echo "    <title>Daily Sales Report - " . date('F j, Y', strtotime($report_date)) . "</title>\n";
    echo "    <style>\n";
    echo "        @media print {\n";
    echo "            .no-print { display: none !important; }\n";
    echo "            .print-break { page-break-before: always; }\n";
    echo "            body { font-size: 12px; }\n";
    echo "            .container { max-width: 100% !important; }\n";
    echo "        }\n";
    echo "        body { font-family: Arial, sans-serif; margin: 20px; }\n";
    echo "        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }\n";
    echo "        .header h1 { color: #2563eb; margin: 0; }\n";
    echo "        .header h2 { color: #666; margin: 10px 0 0 0; }\n";
    echo "        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }\n";
    echo "        .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #2563eb; }\n";
    echo "        .summary-card.success { border-left-color: #059669; }\n";
    echo "        .summary-card.warning { border-left-color: #d97706; }\n";
    echo "        .summary-card.danger { border-left-color: #dc2626; }\n";
    echo "        .summary-value { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 5px; }\n";
    echo "        .summary-label { color: #666; font-size: 14px; text-transform: uppercase; }\n";
    echo "        .section { margin-bottom: 30px; }\n";
    echo "        .section-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }\n";
    echo "        .payment-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }\n";
    echo "        .payment-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }\n";
    echo "        .payment-value { font-size: 18px; font-weight: bold; color: #333; }\n";
    echo "        .payment-label { color: #666; font-size: 12px; }\n";
    echo "        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }\n";
    echo "        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\n";
    echo "        th { background-color: #f8f9fa; font-weight: bold; }\n";
    echo "        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }\n";
    echo "        .bg-primary { background-color: #2563eb; color: white; }\n";
    echo "        .bg-success { background-color: #059669; color: white; }\n";
    echo "        .bg-warning { background-color: #d97706; color: white; }\n";
    echo "        .bg-danger { background-color: #dc2626; color: white; }\n";
    echo "        .bg-info { background-color: #0284c7; color: white; }\n";
    echo "        .text-center { text-align: center; }\n";
    echo "        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }\n";
    echo "    </style>\n";
    echo "</head>\n";
    echo "<body>\n";
    
    // Header
    echo "    <div class='header'>\n";
    echo "        <h1>Daily Sales Report</h1>\n";
    echo "        <h2>" . date('l, F j, Y', strtotime($report_date)) . "</h2>\n";
    echo "        <p>Generated on: " . date('M j, Y \a\t g:i A') . "</p>\n";
    echo "    </div>\n";
    
    // Summary Cards
    echo "    <div class='summary-grid'>\n";
    echo "        <div class='summary-card'>\n";
    echo "            <div class='summary-value'>" . $reportController->formatCurrency($daily_summary['total_sales'] ?? 0) . "</div>\n";
    echo "            <div class='summary-label'>Total Sales</div>\n";
    echo "        </div>\n";
    echo "        <div class='summary-card success'>\n";
    echo "            <div class='summary-value'>" . $reportController->formatNumber($daily_summary['total_transactions'] ?? 0) . "</div>\n";
    echo "            <div class='summary-label'>Total Transactions</div>\n";
    echo "        </div>\n";
    echo "        <div class='summary-card warning'>\n";
    echo "            <div class='summary-value'>" . $reportController->formatCurrency($daily_summary['average_sale'] ?? 0) . "</div>\n";
    echo "            <div class='summary-label'>Average Sale</div>\n";
    echo "        </div>\n";
    echo "        <div class='summary-card danger'>\n";
    echo "            <div class='summary-value'>" . $reportController->formatNumber($daily_summary['tables_served'] ?? 0) . "</div>\n";
    echo "            <div class='summary-label'>Tables Served</div>\n";
    echo "        </div>\n";
    echo "    </div>\n";
    
    // Payment Methods
    echo "    <div class='section'>\n";
    echo "        <h3 class='section-title'>Payment Methods Breakdown</h3>\n";
    echo "        <div class='payment-methods'>\n";
    echo "            <div class='payment-card'>\n";
    echo "                <div class='payment-value'>" . $reportController->formatCurrency($daily_summary['cash_total'] ?? 0) . "</div>\n";
    echo "                <div class='payment-label'>Cash (" . $reportController->formatNumber($daily_summary['cash_transactions'] ?? 0) . " transactions)</div>\n";
    echo "            </div>\n";
    echo "            <div class='payment-card'>\n";
    echo "                <div class='payment-value'>" . $reportController->formatCurrency($daily_summary['card_total'] ?? 0) . "</div>\n";
    echo "                <div class='payment-label'>Card (" . $reportController->formatNumber($daily_summary['card_transactions'] ?? 0) . " transactions)</div>\n";
    echo "            </div>\n";
    echo "            <div class='payment-card'>\n";
    echo "                <div class='payment-value'>" . $reportController->formatCurrency($daily_summary['tng_total'] ?? 0) . "</div>\n";
    echo "                <div class='payment-label'>Touch 'n Go (" . $reportController->formatNumber($daily_summary['tng_transactions'] ?? 0) . " transactions)</div>\n";
    echo "            </div>\n";
    echo "        </div>\n";
    echo "    </div>\n";
    
    // Top Selling Items
    echo "    <div class='section print-break'>\n";
    echo "        <h3 class='section-title'>Top Selling Items</h3>\n";
    echo "        <table>\n";
    echo "            <thead>\n";
    echo "                <tr>\n";
    echo "                    <th>Rank</th>\n";
    echo "                    <th>Item Name</th>\n";
    echo "                    <th>Unit Price</th>\n";
    echo "                    <th>Quantity Sold</th>\n";
    echo "                    <th>Total Sales</th>\n";
    echo "                </tr>\n";
    echo "            </thead>\n";
    echo "            <tbody>\n";
    
    if (empty($top_items)) {
        echo "                <tr><td colspan='5' class='text-center'>No sales data available</td></tr>\n";
    } else {
        foreach ($top_items as $index => $item) {
            echo "                <tr>\n";
            echo "                    <td class='text-center'><span class='badge bg-primary'>" . ($index + 1) . "</span></td>\n";
            echo "                    <td>" . htmlspecialchars($item['item_name']) . "</td>\n";
            echo "                    <td>" . $reportController->formatCurrency($item['unit_price']) . "</td>\n";
            echo "                    <td>" . $reportController->formatNumber($item['quantity_sold']) . "</td>\n";
            echo "                    <td>" . $reportController->formatCurrency($item['total_sales']) . "</td>\n";
            echo "                </tr>\n";
        }
    }
    
    echo "            </tbody>\n";
    echo "        </table>\n";
    echo "    </div>\n";
    
    // Sales by Table
    echo "    <div class='section'>\n";
    echo "        <h3 class='section-title'>Sales by Table</h3>\n";
    echo "        <table>\n";
    echo "            <thead>\n";
    echo "                <tr>\n";
    echo "                    <th>Table Number</th>\n";
    echo "                    <th>Transactions</th>\n";
    echo "                    <th>Total Sales</th>\n";
    echo "                    <th>First Order</th>\n";
    echo "                    <th>Last Order</th>\n";
    echo "                </tr>\n";
    echo "            </thead>\n";
    echo "            <tbody>\n";
    
    if (empty($table_sales)) {
        echo "                <tr><td colspan='5' class='text-center'>No table sales data available</td></tr>\n";
    } else {
        foreach ($table_sales as $table) {
            echo "                <tr>\n";
            echo "                    <td>Table " . htmlspecialchars($table['table_number']) . "</td>\n";
            echo "                    <td>" . $reportController->formatNumber($table['transaction_count']) . "</td>\n";
            echo "                    <td>" . $reportController->formatCurrency($table['total_sales']) . "</td>\n";
            echo "                    <td>" . date('g:i A', strtotime($table['first_order'])) . "</td>\n";
            echo "                    <td>" . date('g:i A', strtotime($table['last_order'])) . "</td>\n";
            echo "                </tr>\n";
        }
    }
    
    echo "            </tbody>\n";
    echo "        </table>\n";
    echo "    </div>\n";
    
    // Order Status Breakdown
    echo "    <div class='section'>\n";
    echo "        <h3 class='section-title'>Order Status Breakdown</h3>\n";
    echo "        <table>\n";
    echo "            <thead>\n";
    echo "                <tr>\n";
    echo "                    <th>Status</th>\n";
    echo "                    <th>Count</th>\n";
    echo "                    <th>Total Amount</th>\n";
    echo "                    <th>Percentage</th>\n";
    echo "                </tr>\n";
    echo "            </thead>\n";
    echo "            <tbody>\n";
    
    $total_orders = array_sum(array_column($order_status, 'count'));
    foreach ($order_status as $status) {
        $percentage = $reportController->calculatePercentage($status['count'], $total_orders);
        $badge_class = $reportController->getStatusBadgeClass($status['status']);
        
        echo "                <tr>\n";
        echo "                    <td><span class='badge " . $badge_class . "'>" . ucfirst($status['status']) . "</span></td>\n";
        echo "                    <td>" . $reportController->formatNumber($status['count']) . "</td>\n";
        echo "                    <td>" . $reportController->formatCurrency($status['total_amount']) . "</td>\n";
        echo "                    <td>" . number_format($percentage, 1) . "%</td>\n";
        echo "                </tr>\n";
    }
    
    echo "            </tbody>\n";
    echo "        </table>\n";
    echo "    </div>\n";
    
    // Footer
    echo "    <div class='footer'>\n";
    echo "        <p>This report was generated on " . date('F j, Y \a\t g:i A') . " for " . date('l, F j, Y', strtotime($report_date)) . "</p>\n";
    echo "    </div>\n";
    
    echo "</body>\n";
    echo "</html>\n";
}

// Check if this is a print request
if (isset($_GET['print']) && $_GET['print'] == '1') {
    if (isset($error_message)) {
        echo "<h1>Error</h1><p>" . $error_message . "</p>";
    } else {
        printDailyReport($reportController, $report_date, $daily_summary, $hourly_sales, $top_items, $table_sales, $order_status);
    }
    exit();
}

// If not a print request, redirect to the full report
header('Location: daily_sales_report.php?date=' . $report_date);
exit();
?>


