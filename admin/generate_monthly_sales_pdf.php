<?php
// Monthly Sales PDF Generation using TCPDF with real data
// Start output buffering to prevent headers already sent errors
ob_start();

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');

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

// Get parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

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
    
    // Get real monthly sales data
    $monthly_sql = "SELECT 
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        MIN(DATE(p.payment_date)) as month_start,
                        MAX(DATE(p.payment_date)) as month_end,
                        p.payment_id,
                        p.amount,
                        p.payment_date,
                        GROUP_CONCAT(CONCAT(oi.quantity, ':', m.price) SEPARATOR '||') as item_details
                      FROM payments p
                      JOIN orders o ON p.order_id = o.id
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      LEFT JOIN menu_items m ON oi.menu_item_id = m.id
                      WHERE p.payment_date BETWEEN ? AND ?
                      GROUP BY p.payment_id, DATE_FORMAT(p.payment_date, '%Y-%m')
                      ORDER BY month";
    
    $monthly_stmt = $db->prepare($monthly_sql);
    $monthly_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $raw_monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process monthly data
    $monthly_totals = [];
    $total_sales = 0;
    $total_transactions = 0;
    
    foreach ($raw_monthly_data as $payment) {
        $month = $payment['month'];
        
        if (!isset($monthly_totals[$month])) {
            $monthly_totals[$month] = [
                'month' => $month,
                'month_start' => $payment['month_start'],
                'month_end' => $payment['month_end'],
                'total_sales' => 0,
                'transaction_count' => 0
            ];
        }
        
        // Recalculate with service tax
        $recalculated_amount = recalculatePaymentAmount($payment['item_details'], $systemSettings);
        
        $monthly_totals[$month]['total_sales'] += $recalculated_amount;
        $monthly_totals[$month]['transaction_count']++;
        
        $total_sales += $recalculated_amount;
        $total_transactions++;
    }
    
    // Calculate average sale
    $average_sale = $total_transactions > 0 ? $total_sales / $total_transactions : 0;
    $months_covered = count($monthly_totals);
    
    // Clear output buffer
    ob_end_clean();
    
    // Create TCPDF instance
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Restaurant System');
    $pdf->SetAuthor('Restaurant System');
    $pdf->SetTitle('Monthly Sales Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'MONTHLY SALES REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Date range
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Period: ' . $start_date . ' to ' . $end_date, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Summary section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Summary table
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetFillColor(240, 240, 240);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(80, 8, 'Metric', 1, 0, 'C', true);
    $pdf->Cell(80, 8, 'Value', 1, 1, 'C', true);
    
    // Summary data with real values
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(80, 8, 'Total Sales', 1, 0, 'L');
    $pdf->Cell(80, 8, 'RM ' . number_format($total_sales, 2), 1, 1, 'R');
    
    $pdf->Cell(80, 8, 'Total Transactions', 1, 0, 'L');
    $pdf->Cell(80, 8, number_format($total_transactions), 1, 1, 'R');
    
    $pdf->Cell(80, 8, 'Average Sale', 1, 0, 'L');
    $pdf->Cell(80, 8, 'RM ' . number_format($average_sale, 2), 1, 1, 'R');
    
    $pdf->Cell(80, 8, 'Months Covered', 1, 0, 'L');
    $pdf->Cell(80, 8, number_format($months_covered), 1, 1, 'R');
    
    $pdf->Ln(10);
    
    // Monthly breakdown
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'MONTHLY BREAKDOWN', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(50, 8, 'Month', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Sales', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Transactions', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Average', 1, 1, 'C', true);
    
    // Real monthly data
    $pdf->SetFont('helvetica', '', 10);
    foreach ($monthly_totals as $month_data) {
        $month_name = date('F Y', strtotime($month_data['month'] . '-01'));
        $month_sales = $month_data['total_sales'];
        $month_transactions = $month_data['transaction_count'];
        $month_average = $month_transactions > 0 ? $month_sales / $month_transactions : 0;
        
        $pdf->Cell(50, 8, $month_name, 1, 0, 'L');
        $pdf->Cell(30, 8, 'RM ' . number_format($month_sales, 2), 1, 0, 'R');
        $pdf->Cell(30, 8, number_format($month_transactions), 1, 0, 'R');
        $pdf->Cell(30, 8, 'RM ' . number_format($month_average, 2), 1, 1, 'R');
    }
    
    $pdf->Ln(10);
    
    // Footer
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 8, 'Generated by Restaurant Management System', 0, 1, 'C');
    $pdf->Cell(0, 8, 'This report includes all sales with applicable taxes and service charges', 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('Monthly_Sales_Report_' . $start_date . '_to_' . $end_date . '.pdf', 'D');
    
} catch (Exception $e) {
    // Clean output buffer and show error
    ob_end_clean();
    http_response_code(500);
    echo "PDF generation failed: " . $e->getMessage();
    error_log("PDF generation error: " . $e->getMessage());
}
?>