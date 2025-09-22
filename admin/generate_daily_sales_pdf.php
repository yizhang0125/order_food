<?php
// Start output buffering to prevent any output before headers
ob_start();

session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/classes/ReportController.php');
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

// Clear any output buffer
ob_end_clean();

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

// Get date range from query parameters
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

try {
    // Get daily sales data
    $daily_breakdown = $reportController->getSalesData($start_date, $end_date, 'daily');
    
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
    $total_sales = 0;
    $total_transactions = 0;
    $total_tables = 0;
    
    foreach ($daily_breakdown as $day) {
        $total_sales += $day['total_sales'];
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
    $pdf->Cell(0, 6, 'Generated by Food Ordering System | For questions contact system administrator', 0, 1, 'C');
    
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
    header('Content-Type: text/plain');
    echo "Error generating PDF: " . $e->getMessage();
    exit();
}
?>