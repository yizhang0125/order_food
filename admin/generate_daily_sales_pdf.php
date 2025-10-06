<?php
// Daily Sales PDF Generation using TCPDF with real data
// Start output buffering to prevent headers already sent errors
ob_start();

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');
require_once(__DIR__ . '/classes/ReportController.php');

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

// Get date range from query parameters
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Function to round to nearest 0.05 (5 cents) for cash transactions
function customRound($amount) {
    return round($amount * 20) / 20;
}

// Function to recalculate payment amount with service tax
function recalculatePaymentAmount($item_details, $systemSettings) {
    if (empty($item_details)) {
        return 0;
    }
    
    $subtotal = 0;
    $item_details_array = explode('||', $item_details);
    
    foreach ($item_details_array as $item) {
        if (empty(trim($item))) continue;
        list($quantity, $price) = explode(':', $item);
        $subtotal += $quantity * $price;
    }
    
    // Calculate tax and service tax
    $tax_rate = $systemSettings->getTaxRate();
    $service_tax_rate = $systemSettings->getServiceTaxRate();
    $tax_amount = $subtotal * $tax_rate;
    $service_tax_amount = $subtotal * $service_tax_rate;
    $total_with_tax = $subtotal + $tax_amount + $service_tax_amount;
    
    return customRound($total_with_tax);
}

try {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    $systemSettings = new SystemSettings($db);
    $reportController = new ReportController();
    
    // Get real daily sales data
    $daily_sql = "SELECT 
                        DATE(p.payment_date) as sale_date,
                        p.payment_id,
                        p.amount,
                        GROUP_CONCAT(CONCAT(oi.quantity, ':', m.price) SEPARATOR '||') as item_details
                      FROM payments p
                      JOIN orders o ON p.order_id = o.id
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      LEFT JOIN menu_items m ON oi.menu_item_id = m.id
                      WHERE p.payment_date BETWEEN ? AND ?
                      AND p.payment_status = 'completed'
                      GROUP BY p.payment_id, DATE(p.payment_date)
                      ORDER BY sale_date";
    
    $daily_stmt = $db->prepare($daily_sql);
    $daily_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $raw_daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process daily data
    $daily_totals = [];
    $total_sales = 0;
    $total_transactions = 0;
    
    foreach ($raw_daily_data as $payment) {
        $sale_date = $payment['sale_date'];
        
        if (!isset($daily_totals[$sale_date])) {
            $daily_totals[$sale_date] = [
                'sale_date' => $sale_date,
                'total_sales' => 0,
                'transaction_count' => 0
            ];
        }
        
        // Recalculate with service tax
        $recalculated_amount = recalculatePaymentAmount($payment['item_details'], $systemSettings);
        
        $daily_totals[$sale_date]['total_sales'] += $recalculated_amount;
        $daily_totals[$sale_date]['transaction_count']++;
        
        $total_sales += $recalculated_amount;
        $total_transactions++;
    }
    
    // Convert to array format
    $daily_breakdown = array_values($daily_totals);
    
    // If no data, create a sample entry
    if (empty($daily_breakdown)) {
        $daily_breakdown = [
            [
                'sale_date' => $start_date,
                'total_sales' => 0.00,
                'transaction_count' => 0
            ]
        ];
    }
    
    // Clear output buffer
    ob_end_clean();
    
    // Generate filename
    $filename = 'Daily_Sales_Breakdown_' . $start_date . '_to_' . $end_date . '.pdf';
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Food Ordering System');
    $pdf->SetAuthor('Food Ordering System');
    $pdf->SetTitle('Daily Sales Breakdown Report');
    $pdf->SetSubject('Daily Sales Analysis');
    $pdf->SetKeywords('Daily Sales, Breakdown, Report, Sales Analysis');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'DAILY SALES BREAKDOWN REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Period: ' . $start_date . ' to ' . $end_date, 0, 1, 'C');
    $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Report info
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Report Type: Daily Sales Breakdown', 0, 1);
    $pdf->Cell(0, 6, 'Total Days: ' . count($daily_breakdown), 0, 1);
    $pdf->Cell(0, 6, 'Date Range: ' . $start_date . ' to ' . $end_date, 0, 1);
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(40, 8, 'Date', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Total Sales (RM)', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Transactions', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Average Sale (RM)', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Tables Served', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('helvetica', '', 8);
    $total_tables = 0;
    
    foreach ($daily_breakdown as $day) {
        $total_transactions += $day['transaction_count'];
        
        // Get tables served for this day
        $tables_sql = "SELECT COUNT(DISTINCT o.table_id) as tables_served 
                      FROM payments p 
                      JOIN orders o ON p.order_id = o.id 
                      WHERE DATE(p.payment_date) = ?";
        $tables_stmt = $db->prepare($tables_sql);
        $tables_stmt->execute([$day['sale_date']]);
        $tables_count = $tables_stmt->fetch(PDO::FETCH_ASSOC)['tables_served'];
        $total_tables += $tables_count;
        
        $average_sale = $day['transaction_count'] > 0 ? $day['total_sales'] / $day['transaction_count'] : 0;
        
        $pdf->Cell(40, 6, date('M j, Y', strtotime($day['sale_date'])), 1, 0, 'L');
        $pdf->Cell(35, 6, number_format($day['total_sales'], 2), 1, 0, 'R');
        $pdf->Cell(30, 6, number_format($day['transaction_count']), 1, 0, 'C');
        $pdf->Cell(35, 6, number_format($average_sale, 2), 1, 0, 'R');
        $pdf->Cell(30, 6, number_format($tables_count), 1, 1, 'C');
    }
    
    // Summary row
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(40, 6, 'TOTAL', 1, 0, 'L');
    $pdf->Cell(35, 6, number_format($total_sales, 2), 1, 0, 'R');
    $pdf->Cell(30, 6, number_format($total_transactions), 1, 0, 'C');
    $pdf->Cell(35, 6, number_format($total_transactions > 0 ? $total_sales / $total_transactions : 0, 2), 1, 0, 'R');
    $pdf->Cell(30, 6, number_format($total_tables), 1, 1, 'C');
    
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 6, 'Generated by Food Ordering System | This report includes all sales with applicable taxes and service charges', 0, 1, 'C');
    
    // Close and output PDF document
    $pdf->Output($filename, 'D'); // 'D' forces download
    
} catch (Exception $e) {
    // Clean any output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the error
    error_log("Daily Sales PDF Generation Error: " . $e->getMessage());
    error_log("Daily Sales PDF Generation Trace: " . $e->getTraceAsString());
    
    // Set proper error headers
    http_response_code(500);
    echo "Error generating PDF: " . $e->getMessage();
    exit();
}
?>