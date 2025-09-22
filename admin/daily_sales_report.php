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

// Get date from query parameters or default to today
$report_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Generate daily report using controller
    $report_data = $reportController->generateDailyReport($report_date);
    
    // Extract data from report
    $daily_summary = $report_data['summary'];
    $hourly_sales = $report_data['hourly_sales'];
    $top_items = $report_data['top_items'];
    $table_sales = $report_data['table_sales'];
    $order_status = $report_data['order_status'];
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Set page title
$page_title = "Daily Sales Report - " . date('F j, Y', strtotime($report_date));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-break { page-break-before: always; }
            body { font-size: 12px; }
            .container { max-width: 100% !important; }
        }
        
        .report-header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #2563eb;
            margin-bottom: 1.5rem;
        }
        
        .summary-card.success { border-left-color: #059669; }
        .summary-card.warning { border-left-color: #d97706; }
        .summary-card.danger { border-left-color: #dc2626; }
        
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .summary-label {
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        
        .report-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
            border: none;
        }
        
        .table td {
            border-color: #e2e8f0;
            vertical-align: middle;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        .btn-print {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-print:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: white;
            transform: translateY(-1px);
        }
        
        .payment-method-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .payment-method-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .payment-method-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .hourly-chart {
            height: 300px;
            background: #f8fafc;
            border-radius: 8px;
            display: flex;
            align-items: end;
            padding: 1rem;
            gap: 0.5rem;
        }
        
        .hour-bar {
            flex: 1;
            background: linear-gradient(to top, #2563eb, #3b82f6);
            border-radius: 4px 4px 0 0;
            min-height: 20px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .hour-bar:hover {
            background: linear-gradient(to top, #1d4ed8, #2563eb);
        }
        
        .hour-label {
            position: absolute;
            bottom: -1.5rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .hour-value {
            position: absolute;
            top: -1.5rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: #1e293b;
            font-weight: 600;
            background: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Report Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-chart-line me-3"></i>
                        Daily Sales Report
                    </h1>
                    <h3 class="mb-0"><?php echo date('l, F j, Y', strtotime($report_date)); ?></h3>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="mb-0 mt-2">Generated on: <?php echo date('M j, Y \a\t g:i A'); ?></p>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
        <?php else: ?>

        <!-- Daily Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $reportController->formatCurrency($daily_summary['total_sales'] ?? 0); ?></div>
                    <div class="summary-label">Total Sales</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card success">
                    <div class="summary-value"><?php echo $reportController->formatNumber($daily_summary['total_transactions'] ?? 0); ?></div>
                    <div class="summary-label">Total Transactions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card warning">
                    <div class="summary-value"><?php echo $reportController->formatCurrency($daily_summary['average_sale'] ?? 0); ?></div>
                    <div class="summary-label">Average Sale</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card danger">
                    <div class="summary-value"><?php echo $reportController->formatNumber($daily_summary['tables_served'] ?? 0); ?></div>
                    <div class="summary-label">Tables Served</div>
                </div>
            </div>
        </div>

        <!-- Payment Methods Breakdown -->
        <div class="report-section">
            <h3 class="section-title">
                <i class="fas fa-credit-card me-2"></i>
                Payment Methods Breakdown
            </h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="payment-method-card">
                        <div class="payment-method-value"><?php echo $reportController->formatCurrency($daily_summary['cash_total'] ?? 0); ?></div>
                        <div class="payment-method-label">Cash (<?php echo $reportController->formatNumber($daily_summary['cash_transactions'] ?? 0); ?> transactions)</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="payment-method-card">
                        <div class="payment-method-value"><?php echo $reportController->formatCurrency($daily_summary['card_total'] ?? 0); ?></div>
                        <div class="payment-method-label">Card (<?php echo $reportController->formatNumber($daily_summary['card_transactions'] ?? 0); ?> transactions)</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="payment-method-card">
                        <div class="payment-method-value"><?php echo $reportController->formatCurrency($daily_summary['tng_total'] ?? 0); ?></div>
                        <div class="payment-method-label">Touch 'n Go (<?php echo $reportController->formatNumber($daily_summary['tng_transactions'] ?? 0); ?> transactions)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hourly Sales Chart -->
        <div class="report-section">
            <h3 class="section-title">
                <i class="fas fa-clock me-2"></i>
                Hourly Sales Breakdown
            </h3>
            <div class="hourly-chart">
                <?php 
                $max_sales = 0;
                foreach ($hourly_sales as $hour) {
                    if ($hour['sales_amount'] > $max_sales) {
                        $max_sales = $hour['sales_amount'];
                    }
                }
                
                for ($i = 0; $i < 24; $i++) {
                    $hour_data = null;
                    foreach ($hourly_sales as $hour) {
                        if ($hour['hour'] == $i) {
                            $hour_data = $hour;
                            break;
                        }
                    }
                    
                    $height = $max_sales > 0 ? ($hour_data['sales_amount'] / $max_sales) * 100 : 0;
                    $display_hour = $i == 0 ? '12 AM' : ($i < 12 ? $i . ' AM' : ($i == 12 ? '12 PM' : ($i - 12) . ' PM'));
                    ?>
                    <div class="hour-bar" style="height: <?php echo max($height, 5); ?>%;">
                        <?php if ($hour_data): ?>
                        <div class="hour-value"><?php echo $reportController->formatCurrency($hour_data['sales_amount']); ?></div>
                        <?php endif; ?>
                        <div class="hour-label"><?php echo $display_hour; ?></div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <!-- Top Selling Items -->
        <div class="report-section print-break">
            <h3 class="section-title">
                <i class="fas fa-utensils me-2"></i>
                Top Selling Items
            </h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Item Name</th>
                            <th>Unit Price</th>
                            <th>Quantity Sold</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_items)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No sales data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($top_items as $index => $item): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?php echo $index + 1; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo $reportController->formatCurrency($item['unit_price']); ?></td>
                                <td><?php echo $reportController->formatNumber($item['quantity_sold']); ?></td>
                                <td><?php echo $reportController->formatCurrency($item['total_sales']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sales by Table -->
        <div class="report-section">
            <h3 class="section-title">
                <i class="fas fa-table me-2"></i>
                Sales by Table
            </h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Table Number</th>
                            <th>Transactions</th>
                            <th>Total Sales</th>
                            <th>First Order</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($table_sales)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No table sales data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($table_sales as $table): ?>
                            <tr>
                                <td>Table <?php echo htmlspecialchars($table['table_number']); ?></td>
                                <td><?php echo $reportController->formatNumber($table['transaction_count']); ?></td>
                                <td><?php echo $reportController->formatCurrency($table['total_sales']); ?></td>
                                <td><?php echo date('g:i A', strtotime($table['first_order'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($table['last_order'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Status Breakdown -->
        <div class="report-section">
            <h3 class="section-title">
                <i class="fas fa-list-alt me-2"></i>
                Order Status Breakdown
            </h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                            <th>Total Amount</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_orders = array_sum(array_column($order_status, 'count'));
                        foreach ($order_status as $status): 
                            $percentage = $reportController->calculatePercentage($status['count'], $total_orders);
                            $badge_class = $reportController->getStatusBadgeClass($status['status']);
                        ?>
                        <tr>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($status['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $reportController->formatNumber($status['count']); ?></td>
                            <td><?php echo $reportController->formatCurrency($status['total_amount']); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

        <!-- Report Footer -->
        <div class="text-center mt-4 no-print">
            <p class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                This report was generated on <?php echo date('F j, Y \a\t g:i A'); ?> 
                for <?php echo date('l, F j, Y', strtotime($report_date)); ?>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
