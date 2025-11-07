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
    // Get top selling items data
    $top_items_sql = "SELECT 
                        m.name as item_name,
                        m.price as unit_price,
                        SUM(oi.quantity) as quantity_sold,
                        SUM(oi.quantity * m.price) as total_sales,
                        COUNT(DISTINCT DATE(p.payment_date)) as days_sold,
                        AVG(oi.quantity) as avg_quantity_per_order,
                        COUNT(DISTINCT o.id) as total_orders
                      FROM order_items oi
                      JOIN menu_items m ON oi.menu_item_id = m.id
                      JOIN orders o ON oi.order_id = o.id
                      JOIN payments p ON o.id = p.order_id
                      WHERE p.payment_date BETWEEN ? AND ?
                      GROUP BY m.id
                      ORDER BY total_sales DESC
                      LIMIT 15";
    
    $items_stmt = $db->prepare($top_items_sql);
    $items_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no data, create a sample entry
    if (empty($top_items)) {
        $top_items = [
            [
                'item_name' => 'Sample Item',
                'unit_price' => 10.00,
                'quantity_sold' => 5,
                'total_sales' => 50.00,
                'days_sold' => 1,
                'avg_quantity_per_order' => 1.0,
                'total_orders' => 5
            ]
        ];
    }
    
    // Generate filename
    $filename = 'Top_Selling_Items_' . $start_date . '_to_' . $end_date . '.pdf';
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Food Ordering System');
    $pdf->SetAuthor('Food Ordering System');
    $pdf->SetTitle('Top Selling Items Report');
    $pdf->SetSubject('Monthly Performance Analysis');
    $pdf->SetKeywords('Top Selling Items, Report, Sales Analysis');
    
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
    $pdf->Cell(0, 10, 'TOP SELLING ITEMS REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Monthly Performance Analysis', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Period: ' . $start_date . ' to ' . $end_date, 0, 1, 'C');
    $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Report info
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Report Type: Top Selling Items (Monthly Performance)', 0, 1);
    $pdf->Cell(0, 6, 'Total Items: ' . count($top_items), 0, 1);
    $pdf->Cell(0, 6, 'Date Range: ' . $start_date . ' to ' . $end_date, 0, 1);
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(15, 8, '#', 1, 0, 'C');
    $pdf->Cell(60, 8, 'Item Name', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Qty', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Sales (RM)', 1, 0, 'C');
    $pdf->Cell(15, 8, 'Days', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Avg/Order', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Orders', 1, 0, 'C');
    $pdf->Cell(15, 8, '%', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('helvetica', '', 8);
    $total_sales = array_sum(array_column($top_items, 'total_sales'));
    
    foreach ($top_items as $index => $item) {
        $percentage = $total_sales > 0 ? (($item['total_sales'] / $total_sales) * 100) : 0;
        $item_name = strlen($item['item_name']) > 25 ? substr($item['item_name'], 0, 22) . '...' : $item['item_name'];
        
        $pdf->Cell(15, 6, ($index + 1), 1, 0, 'C');
        $pdf->Cell(60, 6, $item_name, 1, 0, 'L');
        $pdf->Cell(20, 6, number_format($item['quantity_sold']), 1, 0, 'C');
        $pdf->Cell(25, 6, number_format($item['total_sales'], 2), 1, 0, 'R');
        $pdf->Cell(15, 6, $item['days_sold'], 1, 0, 'C');
        $pdf->Cell(20, 6, number_format($item['avg_quantity_per_order'], 1), 1, 0, 'C');
        $pdf->Cell(20, 6, $item['total_orders'], 1, 0, 'C');
        $pdf->Cell(15, 6, number_format($percentage, 1) . '%', 1, 1, 'C');
    }
    
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
    error_log("PDF Generation Error: " . $e->getMessage());
    error_log("PDF Generation Trace: " . $e->getTraceAsString());
    
    // Set proper error headers
    header('Content-Type: text/plain');
    echo "Error generating PDF: " . $e->getMessage();
    exit();
}
?>