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

// Check if user has permission to view reports
if ($_SESSION['user_type'] !== 'admin' && 
    (!isset($_SESSION['staff_permissions']) || 
    (!in_array('view_reports', $_SESSION['staff_permissions']) && 
     !in_array('all', $_SESSION['staff_permissions'])))) {
    header('Location: dashboard.php');
    exit();
}

// Get date range from query parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    // Get daily sales breakdown data
    $daily_breakdown = $reportController->getSalesData($start_date, $end_date, 'daily');
    
    // Calculate totals
    $total_sales = 0;
    $total_transactions = 0;
    $total_tables_served = 0;
    
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
        $total_tables_served += $tables_count;
    }
    
    $average_sale = $total_transactions > 0 ? $total_sales / $total_transactions : 0;
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $daily_breakdown = [];
    $total_sales = 0;
    $total_transactions = 0;
    $total_tables_served = 0;
    $average_sale = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Sales Breakdown Report</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0.5in;
            }
            .no-print { display: none !important; }
            body { 
                margin: 0; 
                padding: 0;
                font-size: 10px;
                line-height: 1.2;
            }
            .container { 
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .header { 
                padding: 10px 0 !important;
                margin-bottom: 10px !important;
            }
            .summary-grid { 
                margin: 10px 0 !important;
                padding: 8px 0 !important;
            }
            .summary-card { 
                padding: 8px !important;
                margin: 2px !important;
            }
            .summary-value { 
                font-size: 14px !important;
            }
            .summary-label { 
                font-size: 8px !important;
            }
            .table-section { 
                padding: 5px 0 !important;
            }
            .section-title { 
                font-size: 12px !important;
                margin-bottom: 8px !important;
            }
            .report-table th, .report-table td { 
                padding: 4px 6px !important;
                font-size: 9px !important;
            }
            .footer { 
                margin-top: 10px !important;
                padding: 5px 0 !important;
                font-size: 8px !important;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.4;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .header .date-range {
            font-size: 12px;
            margin-top: 8px;
            opacity: 0.8;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            padding: 15px;
            background: #f8fafc;
        }
        
        .summary-card {
            background: white;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .summary-card.highlight {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
        }
        
        .summary-value {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .summary-label {
            font-size: 10px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table-section {
            padding: 15px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            color: #2563eb;
        }
        
        .table-responsive {
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .report-table th {
            background: #2563eb;
            color: white;
            padding: 8px 10px;
            font-weight: 600;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .report-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #374151;
        }
        
        .report-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .report-table tr:hover {
            background-color: #f1f5f9;
        }
        
        .total-row {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe) !important;
            font-weight: 700;
            color: #1e293b;
        }
        
        .total-row td {
            border-top: 2px solid #2563eb;
            border-bottom: 2px solid #2563eb;
        }
        
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #64748b;
            font-style: italic;
        }
        
        .footer {
            background: #f8fafc;
            padding: 15px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #64748b;
        }
        
        .print-actions {
            padding: 15px;
            background: #f8fafc;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0 5px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .container {
                margin: 10px;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Daily Sales Breakdown Report</h1>
            <p class="subtitle">Comprehensive Sales Analysis</p>
            <p class="date-range">
                <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
            </p>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card highlight">
                <div class="summary-value"><?php echo $reportController->formatCurrency($total_sales); ?></div>
                <div class="summary-label">Total Sales</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo $reportController->formatNumber($total_transactions); ?></div>
                <div class="summary-label">Transactions</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo $reportController->formatCurrency($average_sale); ?></div>
                <div class="summary-label">Avg Sale</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo $reportController->formatNumber($total_tables_served); ?></div>
                <div class="summary-label">Tables Served</div>
            </div>
        </div>

        <!-- Daily Sales Table -->
        <div class="table-section">
            <h2 class="section-title">
                <i class="fas fa-table"></i>
                Daily Sales Breakdown
            </h2>
            
            <?php if (isset($error_message)): ?>
            <div class="no-data">
                <p>Error: <?php echo htmlspecialchars($error_message); ?></p>
            </div>
            <?php elseif (empty($daily_breakdown)): ?>
            <div class="no-data">
                <p>No sales data available for the selected date range</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-right">Sales</th>
                            <th class="text-right">Transactions</th>
                            <th class="text-right">Avg Sale</th>
                            <th class="text-right">Tables</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_breakdown as $day): ?>
                        <tr>
                            <td class="font-bold"><?php echo date('M j', strtotime($day['sale_date'])); ?></td>
                            <td class="text-right font-bold"><?php echo $reportController->formatCurrency($day['total_sales']); ?></td>
                            <td class="text-right"><?php echo $reportController->formatNumber($day['transaction_count']); ?></td>
                            <td class="text-right"><?php echo $reportController->formatCurrency($day['total_sales'] / max($day['transaction_count'], 1)); ?></td>
                            <td class="text-right">
                                <?php 
                                // Get tables served for this day
                                $tables_sql = "SELECT COUNT(DISTINCT o.table_id) as tables_served 
                                             FROM payments p 
                                             JOIN orders o ON p.order_id = o.id 
                                             WHERE DATE(p.payment_date) = ?";
                                $tables_stmt = $db->prepare($tables_sql);
                                $tables_stmt->execute([$day['sale_date']]);
                                $tables_count = $tables_stmt->fetch(PDO::FETCH_ASSOC)['tables_served'];
                                echo $reportController->formatNumber($tables_count);
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Total Row -->
                        <tr class="total-row">
                            <td class="font-bold">TOTAL</td>
                            <td class="text-right font-bold"><?php echo $reportController->formatCurrency($total_sales); ?></td>
                            <td class="text-right font-bold"><?php echo $reportController->formatNumber($total_transactions); ?></td>
                            <td class="text-right font-bold"><?php echo $reportController->formatCurrency($average_sale); ?></td>
                            <td class="text-right font-bold"><?php echo $reportController->formatNumber($total_tables_served); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Generated on <?php echo date('M j, Y \a\t g:i A'); ?> by <?php echo htmlspecialchars($_SESSION['username'] ?? 'System'); ?></p>
            <p>Report ID: DSR-<?php echo date('Ymd'); ?>-<?php echo substr(md5($start_date . $end_date), 0, 6); ?></p>
        </div>

        <!-- Print Actions -->
        <div class="print-actions no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-success" onclick="printReceipt()">
                <i class="fas fa-receipt"></i> Print Receipt
            </button>
            <a href="reports.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&view=daily" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <script>
        // Auto-print when page loads (if requested)
        if (window.location.search.includes('autoprint=1')) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            };
        }
        
        // Print Receipt Function
        function printReceipt() {
            // Get date parameters
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            
            // Open receipt page in new window
            const receiptWindow = window.open(`print_daily_sales_receipt.php?start_date=${startDate}&end_date=${endDate}&autoprint=1`, '_blank', 'width=350,height=600');
        }
    </script>
</body>
</html>