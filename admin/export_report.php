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

// Check if report type is provided
if (!isset($_GET['type'])) {
    echo "Report type is required";
    exit();
}

// Get parameters
$report_type = $_GET['type'];
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set filename and headers for CSV download
$filename = $report_type . '_' . $start_date . '_to_' . $end_date . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

try {
    // Export different reports based on type
    switch ($report_type) {
        case 'daily_sales':
            // Daily sales report
            $sql = "SELECT 
                      DATE(p.payment_date) as sale_date,
                      COUNT(p.payment_id) as transaction_count,
                      SUM(p.amount) as daily_total
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY DATE(p.payment_date)
                    ORDER BY sale_date";
            
            // Write CSV header
            fputcsv($output, ['Date', 'Transactions', 'Total Sales (RM)']);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            
            // Write data rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    date('Y-m-d', strtotime($row['sale_date'])),
                    $row['transaction_count'],
                    number_format($row['daily_total'], 2, '.', '')
                ]);
            }
            break;
            
        case 'weekly_sales':
            // Weekly sales report
            $sql = "SELECT 
                      YEARWEEK(p.payment_date, 1) as year_week,
                      MIN(DATE(p.payment_date)) as week_start,
                      MAX(DATE(p.payment_date)) as week_end,
                      COUNT(p.payment_id) as transaction_count,
                      SUM(p.amount) as weekly_total
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY YEARWEEK(p.payment_date, 1)
                    ORDER BY year_week";
            
            // Write CSV header
            fputcsv($output, ['Week Period', 'Start Date', 'End Date', 'Transactions', 'Total Sales (RM)']);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            
            // Write data rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    'Week ' . date('W, Y', strtotime($row['week_start'])),
                    date('Y-m-d', strtotime($row['week_start'])),
                    date('Y-m-d', strtotime($row['week_end'])),
                    $row['transaction_count'],
                    number_format($row['weekly_total'], 2, '.', '')
                ]);
            }
            break;
            
        case 'monthly_sales':
            // Monthly sales report
            $sql = "SELECT 
                      DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                      MIN(DATE(p.payment_date)) as month_start,
                      MAX(DATE(p.payment_date)) as month_end,
                      COUNT(p.payment_id) as transaction_count,
                      SUM(p.amount) as monthly_total
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                    ORDER BY month";
            
            // Write CSV header
            fputcsv($output, ['Month', 'Start Date', 'End Date', 'Transactions', 'Total Sales (RM)']);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            
            // Write data rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    date('F Y', strtotime($row['month'] . '-01')),
                    date('Y-m-d', strtotime($row['month_start'])),
                    date('Y-m-d', strtotime($row['month_end'])),
                    $row['transaction_count'],
                    number_format($row['monthly_total'], 2, '.', '')
                ]);
            }
            break;
            
        case 'top_items':
            // Top selling items report
            $sql = "SELECT 
                      m.name as item_name,
                      SUM(oi.quantity) as quantity_sold,
                      SUM(oi.quantity * m.price) as total_sales
                    FROM order_items oi
                    JOIN menu_items m ON oi.menu_item_id = m.id
                    JOIN orders o ON oi.order_id = o.id
                    JOIN payments p ON o.id = p.order_id
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY m.id
                    ORDER BY quantity_sold DESC";
            
            // Write CSV header
            fputcsv($output, ['Item', 'Quantity Sold', 'Total Sales (RM)']);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            
            // Write data rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['item_name'],
                    $row['quantity_sold'],
                    number_format($row['total_sales'], 2, '.', '')
                ]);
            }
            break;
            
        case 'table_sales':
            // Sales by table report
            $sql = "SELECT 
                      t.table_number,
                      COUNT(p.payment_id) as transaction_count,
                      SUM(p.amount) as total_sales
                    FROM payments p
                    JOIN orders o ON p.order_id = o.id
                    JOIN tables t ON o.table_id = t.id
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY t.table_number
                    ORDER BY total_sales DESC";
            
            // Write CSV header
            fputcsv($output, ['Table Number', 'Transactions', 'Total Sales (RM)']);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            
            // Write data rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['table_number'],
                    $row['transaction_count'],
                    number_format($row['total_sales'], 2, '.', '')
                ]);
            }
            break;
            
        default:
            fputcsv($output, ['Invalid report type']);
            break;
    }
    
} catch (Exception $e) {
    fputcsv($output, ['Error: ' . $e->getMessage()]);
}

// Close the output stream
fclose($output);
?> 