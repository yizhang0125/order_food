<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');

// Check for Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloader)) {
    die('Error: PHPSpreadsheet is not installed. Please run "composer install" in the project root directory.');
}

require_once($autoloader);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check if user is logged in
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    die('Unauthorized access');
}

try {
    // Get date range
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

    // Get cancelled orders
    $orderModel = new Order($db);
    $cancelled_orders = $orderModel->getCancelledOrders($start_date, $end_date);

    if (empty($cancelled_orders)) {
        die('No cancelled orders found for the selected date range.');
    }

    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $sheet->setCellValue('A1', 'Order ID');
    $sheet->setCellValue('B1', 'Table Number');
    $sheet->setCellValue('C1', 'Items');
    $sheet->setCellValue('D1', 'Total Amount (RM)');
    $sheet->setCellValue('E1', 'Cancelled At');
    $sheet->setCellValue('F1', 'Reason');

    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EF4444'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ],
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

    // Add data
    $row = 2;
    foreach ($cancelled_orders as $order) {
        $sheet->setCellValue('A' . $row, '#' . str_pad($order['id'], 4, '0', STR_PAD_LEFT));
        $sheet->setCellValue('B' . $row, 'Table ' . $order['table_number']);
        $sheet->setCellValue('C' . $row, $order['items_list']);
        $sheet->setCellValue('D' . $row, (float)$order['total_amount']);
        $sheet->setCellValue('E' . $row, date('d M Y, h:i A', strtotime($order['cancelled_at'] ?? $order['updated_at'])));
        $sheet->setCellValue('F' . $row, $order['cancel_reason'] ?? 'No reason provided');
        
        // Style the data rows
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
        
        if ($row % 2 == 0) {
            $sheet->getStyle('A'.$row.':F'.$row)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FEF2F2');
        }
        
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Add total row
    $lastRow = $row;
    $sheet->setCellValue('C' . $lastRow, 'Total:');
    $sheet->setCellValue('D' . $lastRow, '=SUM(D2:D'.($lastRow-1).')');
    
    // Format the amount columns
    $sheet->getStyle('D2:D'.$lastRow)->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
    
    $sheet->getStyle('C'.$lastRow.':D'.$lastRow)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FEE2E2'],
        ],
    ]);

    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Set filename
    $filename = 'cancelled_orders_' . date('Y-m-d_His') . '.xlsx';

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