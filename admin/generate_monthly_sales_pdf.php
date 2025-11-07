<?php
// Monthly Sales PDF Generation using TCPDF
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');

// Disable direct error output to prevent PDF corruption
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Clear all output buffers to avoid extra whitespace before PDF
while (ob_get_level()) {
    ob_end_clean();
}

// Start session if not active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Input parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-t');

// Utility functions
function customRound($amount) {
    return round($amount * 20) / 20;
}

function recalculatePaymentAmount($item_details, $systemSettings) {
    if (empty($item_details)) return 0;

    $subtotal = 0;
    $item_details_array = explode('||', $item_details);
    foreach ($item_details_array as $item) {
        if (empty(trim($item))) continue;
        list($quantity, $price) = explode(':', $item);
        $subtotal += $quantity * $price;
    }

    $tax_rate = $systemSettings->getTaxRate();
    $service_tax_rate = $systemSettings->getServiceTaxRate();
    $tax_amount = $subtotal * $tax_rate;
    $service_tax_amount = $subtotal * $service_tax_rate;
    $total_with_tax = $subtotal + $tax_amount + $service_tax_amount;

    return customRound($total_with_tax);
}

try {
    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    $systemSettings = new SystemSettings($db);

    // Query sales data
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

    $stmt = $db->prepare($monthly_sql);
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $raw_monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process data
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
        $recalc = recalculatePaymentAmount($payment['item_details'], $systemSettings);
        $monthly_totals[$month]['total_sales'] += $recalc;
        $monthly_totals[$month]['transaction_count']++;
        $total_sales += $recalc;
        $total_transactions++;
    }

    $average_sale = $total_transactions > 0 ? $total_sales / $total_transactions : 0;
    $months_covered = count($monthly_totals);

    // Re-clear all output buffers before sending binary PDF
    while (ob_get_level()) {
        ob_end_clean();
    }

    // ✅ Create PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Restaurant System');
    $pdf->SetAuthor('Restaurant System');
    $pdf->SetTitle('Monthly Sales Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    // Disable compression for safer binary output
    $pdf->setCompression(false);

    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'MONTHLY SALES REPORT', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Period: ' . $start_date . ' to ' . $end_date, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);

    // Summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(80, 8, 'Metric', 1, 0, 'C', true);
    $pdf->Cell(80, 8, 'Value', 1, 1, 'C', true);

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

    // Monthly Breakdown
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'MONTHLY BREAKDOWN', 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(50, 8, 'Month', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Sales', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Transactions', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Average', 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 10);
    foreach ($monthly_totals as $month_data) {
        $month_name = date('F Y', strtotime($month_data['month'] . '-01'));
        $sales = $month_data['total_sales'];
        $tx = $month_data['transaction_count'];
        $avg = $tx > 0 ? $sales / $tx : 0;

        $pdf->Cell(50, 8, $month_name, 1, 0, 'L');
        $pdf->Cell(30, 8, 'RM ' . number_format($sales, 2), 1, 0, 'R');
        $pdf->Cell(30, 8, $tx, 1, 0, 'R');
        $pdf->Cell(30, 8, 'RM ' . number_format($avg, 2), 1, 1, 'R');
    }

    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 8, 'Generated by Restaurant Management System', 0, 1, 'C');
    $pdf->Cell(0, 8, 'This report includes all sales with applicable taxes and service charges', 0, 1, 'C');

    // ✅ Force clean headers before sending binary data
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Output PDF for download
    $filename = 'Monthly_Sales_Report_' . $start_date . '_to_' . $end_date . '.pdf';
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    while (ob_get_level()) ob_end_clean();
    error_log("PDF generation error: " . $e->getMessage());
    http_response_code(500);
    echo "PDF generation failed.";
    exit;
}
