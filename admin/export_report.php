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
header('Content-Type: text/csv; charset=UTF-8');
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
                $rounded_total = $reportController->customRound(floatval($row['daily_total']));
                
                // Handle date formatting properly for Excel compatibility
                $sale_date = $row['sale_date'];
                if (empty($sale_date) || $sale_date === null) {
                    $formatted_date = 'N/A';
                } else {
                    // Ensure we have a valid date and format it properly
                    $timestamp = strtotime($sale_date);
                    if ($timestamp === false) {
                        $formatted_date = 'Invalid Date';
                    } else {
                        // Use a readable date format: Month Day, Year (e.g., "September 17, 2025")
                        $formatted_date = date('F j, Y', $timestamp);
                    }
                }
                
                // Add a tab character before the date to prevent Excel from treating it as a formula
                $safe_date = "\t" . $formatted_date;
                
                fputcsv($output, [
                    $safe_date,
                    $row['transaction_count'],
                    number_format($rounded_total, 2, '.', '')
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
                $rounded_total = $reportController->customRound(floatval($row['weekly_total']));
                fputcsv($output, [
                    'Week ' . date('W, Y', strtotime($row['week_start'])),
                    date('F j, Y', strtotime($row['week_start'])),
                    date('F j, Y', strtotime($row['week_end'])),
                    $row['transaction_count'],
                    number_format($rounded_total, 2, '.', '')
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
                $rounded_total = $reportController->customRound(floatval($row['monthly_total']));
                fputcsv($output, [
                    date('F Y', strtotime($row['month'] . '-01')),
                    date('F j, Y', strtotime($row['month_start'])),
                    date('F j, Y', strtotime($row['month_end'])),
                    $row['transaction_count'],
                    number_format($rounded_total, 2, '.', '')
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
                $rounded_total = $reportController->customRound(floatval($row['total_sales']));
                fputcsv($output, [
                    $row['item_name'],
                    $row['quantity_sold'],
                    number_format($rounded_total, 2, '.', '')
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
                $rounded_total = $reportController->customRound(floatval($row['total_sales']));
                fputcsv($output, [
                    $row['table_number'],
                    $row['transaction_count'],
                    number_format($rounded_total, 2, '.', '')
                ]);
            }
            break;
            
        case 'hourly_sales':
            // Hourly sales report
            $sql = "SELECT 
                      HOUR(p.payment_date) as hour,
                      COUNT(p.payment_id) as transaction_count,
                      SUM(p.amount) as hourly_total
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY HOUR(p.payment_date)
                    ORDER BY hour";
            
            // Write CSV header
            fputcsv($output, ['Hour', 'Transactions', 'Total Sales (RM)']);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            
            // Write data rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rounded_total = $reportController->customRound(floatval($row['hourly_total']));
                $display_hour = $row['hour'] == 0 ? '12 AM' : 
                               ($row['hour'] < 12 ? $row['hour'] . ' AM' : 
                               ($row['hour'] == 12 ? '12 PM' : ($row['hour'] - 12) . ' PM'));
                fputcsv($output, [
                    $display_hour,
                    $row['transaction_count'],
                    number_format($rounded_total, 2, '.', '')
                ]);
            }
            break;
            
        default:
            fputcsv($output, ['Invalid report type: ' . $report_type]);
            break;
    }
    
} catch (Exception $e) {
    fputcsv($output, ['Error: ' . $e->getMessage()]);
}

// Close the output stream
fclose($output);
?> 