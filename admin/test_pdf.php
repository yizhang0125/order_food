<?php
// Simple test to verify TCPDF is working
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

try {
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Test');
    $pdf->SetAuthor('Test');
    $pdf->SetTitle('Test PDF');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Add content
    $pdf->Cell(0, 10, 'Test PDF Generation', 0, 1, 'C');
    $pdf->Ln(10);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 6, 'This is a test PDF to verify TCPDF is working correctly.', 0, 1);
    $pdf->Cell(0, 6, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1);
    
    // Output PDF
    $pdf->Output('test.pdf', 'D');
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
