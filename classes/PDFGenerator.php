<?php
require_once(__DIR__ . '/../vendor/autoload.php');

class PDFGenerator {
    public static function generateSalesReport($data, $start_date, $end_date) {
        // Clear any output
        ob_end_clean();
        
        // Create PDF
        $pdf = new TCPDF();
        $pdf->SetCreator('Restaurant System');
        $pdf->SetTitle('Sales Report');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // Add content
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Sales Report', 0, 1, 'C');
        
        // Add date range
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, "Period: $start_date to $end_date", 0, 1, 'C');
        
        // Add table
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(60, 7, 'Date', 1);
        $pdf->Cell(60, 7, 'Orders', 1);
        $pdf->Cell(60, 7, 'Total', 1);
        $pdf->Ln();
        
        // Add data rows
        $pdf->SetFont('helvetica', '', 12);
        foreach ($data as $row) {
            $pdf->Cell(60, 7, $row['sale_date'], 1);
            $pdf->Cell(60, 7, $row['order_count'], 1);
            $pdf->Cell(60, 7, number_format($row['total_sales'], 2), 1);
            $pdf->Ln();
        }

        return $pdf;
    }
}
