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
    <title>Daily Sales Receipt</title>
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 5mm;
            }
            body { 
                margin: 0; 
                padding: 5px;
                font-size: 10px;
                line-height: 1.1;
                width: 100%;
                max-width: none;
            }
            .no-print { display: none; }
            .receipt-header {
                padding-bottom: 5px;
                margin-bottom: 8px;
                border-bottom: 1px dashed #000;
            }
            .receipt-title {
                font-size: 14px;
            }
            .receipt-subtitle {
                font-size: 8px;
                margin: 2px 0;
            }
            .info-line {
                margin: 1px 0;
                font-size: 8px;
            }
            .receipt-summary {
                padding: 5px 0;
                margin: 8px 0;
                border-top: 1px dashed #000;
                border-bottom: 1px dashed #000;
            }
            .summary-line {
                margin: 2px 0;
                font-size: 10px;
            }
            .summary-line.total {
                font-size: 12px;
                padding-top: 3px;
                margin-top: 4px;
                border-top: 1px solid #000;
            }
            .daily-item {
                margin: 1px 0;
                font-size: 8px;
                padding: 1px 0;
            }
            .divider {
                margin: 5px 0;
                border-top: 1px dashed #000;
            }
            .receipt-footer {
                margin-top: 8px;
                font-size: 6px;
            }
            .daily-breakdown div[style*="font-size: 14px"] {
                font-size: 10px !important;
                margin-bottom: 4px !important;
            }
        }
        
        body {
            font-family: 'Courier New', monospace;
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            padding: 10px;
            background: white;
            color: black;
            line-height: 1.2;
            font-size: 12px;
            min-height: 100vh;
            box-sizing: border-box;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 3px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .receipt-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        
        .receipt-subtitle {
            font-size: 10px;
            margin: 3px 0;
        }
        
        .receipt-info {
            margin-bottom: 15px;
        }
        
        .info-line {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            font-size: 10px;
        }
        
        .receipt-summary {
            border-top: 2px dashed #000;
            border-bottom: 2px dashed #000;
            padding: 8px 0;
            margin: 12px 0;
        }
        
        .summary-line {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 14px;
        }
        
        .summary-line.total {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 6px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 12px;
            font-size: 8px;
        }
        
        .divider {
            border-top: 2px dashed #000;
            margin: 8px 0;
        }
        
        .daily-breakdown {
            margin: 15px 0;
        }
        
        .daily-item {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            font-size: 10px;
            padding: 1px 0;
        }
        
        .daily-date {
            font-weight: bold;
        }
        
        .daily-amount {
            text-align: right;
        }
        
        .print-actions {
            text-align: center;
            margin-top: 20px;
            padding: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 20px;
            margin: 8px;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        
        .btn:hover {
            background: #4338ca;
        }
    </style>
</head>
<body>
    <div class="receipt-header">
        <h1 class="receipt-title">Daily Sales Breakdown</h1>
        <p class="receipt-subtitle">Sales Report Receipt</p>
    </div>
    
    <div class="receipt-info">
        <div class="info-line">
            <span>Date Range:</span>
            <span><?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></span>
        </div>
        <div class="info-line">
            <span>Generated:</span>
            <span><?php echo date('M j, Y g:i A'); ?></span>
        </div>
        <div class="info-line">
            <span>Generated By:</span>
            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'System'); ?></span>
        </div>
        <div class="info-line">
            <span>Report ID:</span>
            <span><?php echo 'DSR-' . date('Ymd') . '-' . substr(md5($start_date . $end_date), 0, 6); ?></span>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div class="receipt-summary">
        <div class="summary-line">
            <span>Total Sales:</span>
            <span><?php echo $reportController->formatCurrency($total_sales); ?></span>
        </div>
        <div class="summary-line">
            <span>Total Transactions:</span>
            <span><?php echo $reportController->formatNumber($total_transactions); ?></span>
        </div>
        <div class="summary-line">
            <span>Average Sale:</span>
            <span><?php echo $reportController->formatCurrency($average_sale); ?></span>
        </div>
        <div class="summary-line">
            <span>Tables Served:</span>
            <span><?php echo $reportController->formatNumber($total_tables_served); ?></span>
        </div>
        <div class="summary-line total">
            <span>Report Period:</span>
            <span><?php echo count($daily_breakdown); ?> days</span>
        </div>
    </div>
    
    <?php if (!empty($daily_breakdown) && count($daily_breakdown) <= 5): ?>
    <div class="daily-breakdown">
        <div class="divider"></div>
        <div style="text-align: center; font-weight: bold; margin-bottom: 6px; font-size: 12px;">DAILY BREAKDOWN</div>
        <?php foreach ($daily_breakdown as $day): ?>
        <div class="daily-item">
            <span class="daily-date"><?php echo date('M j', strtotime($day['sale_date'])); ?></span>
            <span class="daily-amount"><?php echo $reportController->formatCurrency($day['total_sales']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="divider"></div>
    
    <div class="receipt-footer">
        <p>Thank you for using our system!</p>
        <p>This is a computer-generated receipt.</p>
        <p>For detailed breakdown, see full report.</p>
        <p>Report ID: DSR-<?php echo date('Ymd'); ?>-<?php echo substr(md5($start_date . $end_date), 0, 6); ?></p>
    </div>
    
    <div class="print-actions no-print">
        <button class="btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="print_daily_sales_breakdown.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn">
            <i class="fas fa-file-alt"></i> Full Report
        </a>
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
    </script>
</body>
</html>
