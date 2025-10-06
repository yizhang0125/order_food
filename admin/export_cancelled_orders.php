<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');
require_once(__DIR__ . '/classes/ReportController.php');

// Check for Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloader)) {
    die('Error: PHPSpreadsheet is not installed. Please run "composer install" in the project root directory.');
}

require_once($autoloader);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Check if user is logged in
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$reportController = new ReportController();

if (!$auth->isLoggedIn()) {
    die('Unauthorized access');
}

try {
    // Get date range with validation
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        die('Invalid date format. Please use YYYY-MM-DD format.');
    }
    
    // Validate date range
    if (strtotime($start_date) > strtotime($end_date)) {
        die('Start date cannot be later than end date.');
    }

    // Get cancelled orders
    $orderModel = new Order($db);
    $cancelled_orders = $orderModel->getCancelledOrders($start_date, $end_date);

    if (empty($cancelled_orders)) {
        die('No cancelled orders found for the selected date range (' . $start_date . ' to ' . $end_date . ').');
    }

    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set sheet title
    $sheet->setTitle('Cancelled Orders');

    // Add report header
    $sheet->setCellValue('A1', 'CANCELLED ORDERS REPORT');
    $sheet->setCellValue('A2', 'Date Range: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)));
    $sheet->setCellValue('A3', 'Generated on: ' . date('d M Y, h:i A'));
    $sheet->setCellValue('A4', 'Total Cancelled Orders: ' . count($cancelled_orders));
    
    // Style report header
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => 'EF4444'],
        ],
    ]);
    
    $sheet->getStyle('A2:A4')->applyFromArray([
        'font' => [
            'size' => 10,
            'color' => ['rgb' => '6B7280'],
        ],
    ]);

    // Set data headers (starting from row 6)
    $headerRow = 6;
    $sheet->setCellValue('A' . $headerRow, 'Order ID');
    $sheet->setCellValue('B' . $headerRow, 'Table Number');
    $sheet->setCellValue('C' . $headerRow, 'Items');
    $sheet->setCellValue('D' . $headerRow, 'Total Amount (RM)');
    $sheet->setCellValue('E' . $headerRow, 'Cancelled At');
    $sheet->setCellValue('F' . $headerRow, 'Reason');

    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EF4444'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'FFFFFF'],
            ],
        ],
    ];
    $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->applyFromArray($headerStyle);

    // Add data
    $row = $headerRow + 1;
    $totalAmount = 0;
    
    foreach ($cancelled_orders as $order) {
        $rounded_amount = $reportController->customRound(floatval($order['total_amount']));
        $totalAmount += $rounded_amount;
        
        $sheet->setCellValue('A' . $row, '#' . str_pad($order['id'], 4, '0', STR_PAD_LEFT));
        $sheet->setCellValue('B' . $row, 'Table ' . $order['table_number']);
        $sheet->setCellValue('C' . $row, $order['items_list']);
        $sheet->setCellValue('D' . $row, $rounded_amount);
        
        // Use cancelled_at if available, otherwise use created_at
        $cancelledDate = isset($order['cancelled_at']) ? $order['cancelled_at'] : $order['created_at'];
        $sheet->setCellValue('E' . $row, date('d M Y, h:i A', strtotime($cancelledDate)));
        $sheet->setCellValue('F' . $row, isset($order['cancel_reason']) ? $order['cancel_reason'] : 'No reason provided');
        
        // Style the data rows
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ]);
        
        // Alternate row colors
        if ($row % 2 == 0) {
            $sheet->getStyle('A'.$row.':F'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FEF2F2');
        }
        
        $row++;
    }

    // Add total row
    $totalRow = $row;
    $sheet->setCellValue('C' . $totalRow, 'TOTAL:');
    $sheet->setCellValue('D' . $totalRow, $totalAmount);
    
    // Style total row
    $sheet->getStyle('C'.$totalRow.':D'.$totalRow)->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 12,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EF4444'],
        ],
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'FFFFFF'],
            ],
        ],
    ]);

    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Format the amount columns
    $sheet->getStyle('D'.($headerRow + 1).':D'.$totalRow)->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Set filename with date range
    $filename = 'cancelled_orders_' . $start_date . '_to_' . $end_date . '_' . date('His') . '.xlsx';

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

    // Add total row
    $totalRow = $row;
    $sheet->setCellValue('C' . $totalRow, 'TOTAL:');
    $sheet->setCellValue('D' . $totalRow, $totalAmount);
    
    // Style total row
    $sheet->getStyle('C'.$totalRow.':D'.$totalRow)->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 12,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EF4444'],
        ],
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'FFFFFF'],
            ],
        ],
    ]);

    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Format the amount columns
    $sheet->getStyle('D'.($headerRow + 1).':D'.$totalRow)->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Set filename with date range
    $filename = 'cancelled_orders_' . $start_date . '_to_' . $end_date . '_' . date('His') . '.xlsx';

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log('Excel Export Error: ' . $e->getMessage());
    die('Error generating Excel file. Please try again later.');
} 