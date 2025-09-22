<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');
require_once(__DIR__ . '/classes/ReportController.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);
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

// Get date range from query parameters or default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';

// Get view mode (daily, weekly, monthly)
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'daily';

try {
    // Get sales summary data using ReportController for consistency
    $sales_summary = $reportController->getSalesSummary($start_date, $end_date);
    
    // Sales data based on view mode
    if ($view_mode == 'weekly') {
        // Weekly sales data for chart
        $sales_sql = "SELECT 
                        YEARWEEK(p.payment_date, 1) as year_week,
                        MIN(DATE(p.payment_date)) as week_start,
                        MAX(DATE(p.payment_date)) as week_end,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as transaction_count
                      FROM payments p
                      WHERE p.payment_date BETWEEN ? AND ?
                      GROUP BY YEARWEEK(p.payment_date, 1)
                      ORDER BY year_week";
    } else if ($view_mode == 'monthly') {
        // Monthly sales data for chart
        $sales_sql = "SELECT 
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        MIN(DATE(p.payment_date)) as month_start,
                        MAX(DATE(p.payment_date)) as month_end,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as transaction_count
                      FROM payments p
                      WHERE p.payment_date BETWEEN ? AND ?
                      GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                      ORDER BY month";
    } else {
        // Daily sales data for chart (default)
        $sales_sql = "SELECT 
                        DATE(p.payment_date) as sale_date,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as transaction_count
                      FROM payments p
                      WHERE p.payment_date BETWEEN ? AND ?
                      GROUP BY DATE(p.payment_date)
                      ORDER BY sale_date";
    }
    
    $sales_stmt = $db->prepare($sales_sql);
    $sales_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top selling items with monthly breakdown
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
    
    // Sales by table
    $table_sales_sql = "SELECT 
                          t.table_number,
                          COUNT(p.payment_id) as transaction_count,
                          SUM(p.amount) as total_sales
                        FROM payments p
                        JOIN orders o ON p.order_id = o.id
                        JOIN tables t ON o.table_id = t.id
                        WHERE p.payment_date BETWEEN ? AND ?
                        GROUP BY t.table_number
                        ORDER BY total_sales DESC";
    
    $table_stmt = $db->prepare($table_sales_sql);
    $table_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $table_sales = $table_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare chart data
    $chart_labels = [];
    $chart_data = [];
    $chart_transactions = [];
    
    foreach ($sales_data as $period) {
        if ($view_mode == 'weekly') {
            // Format: "Week 1 Jan - 7 Jan"
            $chart_labels[] = 'Week ' . date('d M', strtotime($period['week_start'])) . ' - ' . date('d M', strtotime($period['week_end']));
        } else if ($view_mode == 'monthly') {
            // Format: "Jan 2023"
            $chart_labels[] = date('M Y', strtotime($period['month'] . '-01'));
        } else {
            // Daily format: "1 Jan"
            $chart_labels[] = date('d M', strtotime($period['sale_date']));
        }
        
        $chart_data[] = round(floatval($period['total_sales']), 2);
        $chart_transactions[] = intval($period['transaction_count']);
    }
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Set page title
$page_title = "Sales Reports";

// Start output buffering
ob_start();
?>

<!-- Page content -->
<div class="container-fluid py-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-chart-line"></i>
            Sales Reports
        </h1>
    </div>

    <div class="date-filter">
        <form class="date-inputs">
            <input type="date" class="date-input" id="start_date" name="start_date" 
                   value="<?php echo $start_date; ?>">
            <input type="date" class="date-input" id="end_date" name="end_date" 
                   value="<?php echo $end_date; ?>">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
            <div class="view-selector">
                <label>View:</label>
                <div class="btn-group" role="group">
                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&view=daily" 
                       class="btn btn-sm <?php echo $view_mode == 'daily' ? 'btn-primary' : 'btn-outline-primary'; ?>">Daily</a>
                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&view=weekly" 
                       class="btn btn-sm <?php echo $view_mode == 'weekly' ? 'btn-primary' : 'btn-outline-primary'; ?>">Weekly</a>
                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&view=monthly" 
                       class="btn btn-sm <?php echo $view_mode == 'monthly' ? 'btn-primary' : 'btn-outline-primary'; ?>">Monthly</a>
                </div>
            </div>
            <button type="submit" class="filter-btn">
                <i class="fas fa-filter"></i>
                Generate Report
            </button>
        </form>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error_message; ?>
    </div>
    <?php else: ?>

    <div class="summary-cards">
        <div class="summary-card highlight-card">
            <div class="summary-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="summary-info">
                <h3>Total <?php echo $view_mode == 'daily' ? 'Daily' : ($view_mode == 'weekly' ? 'Weekly' : 'Monthly'); ?> Sales</h3>
                <p>RM <?php echo number_format($sales_summary['total_sales'] ?? 0, 2); ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="summary-info">
                <h3>Total Transactions</h3>
                <p><?php echo number_format($sales_summary['total_transactions'] ?? 0); ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="summary-info">
                <h3>Average Sale</h3>
                <p>RM <?php echo number_format($sales_summary['average_sale'] ?? 0, 2); ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="summary-info">
                <h3>Highest Sale</h3>
                <p>RM <?php echo number_format($sales_summary['highest_sale'] ?? 0, 2); ?></p>
            </div>
        </div>
    </div>

    <div class="report-section">
        <div class="section-header">
            <h2>
                <?php 
                if ($view_mode == 'weekly') {
                    echo 'Weekly Sales';
                } else if ($view_mode == 'monthly') {
                    echo 'Monthly Sales';
                } else {
                    echo 'Daily Sales';
                }
                ?>
            </h2>
            <div class="export-options">
                <a href="export_report.php?type=<?php echo $view_mode; ?>_sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-download"></i> Export
                </a>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <?php 
    // Prepare hourly data for JavaScript (for all view modes)
    $hourly_data = [];
    $daily_breakdown = [];
    
    // Get daily sales data for the selected date range
    $daily_breakdown = $reportController->getSalesData($start_date, $end_date, 'daily');
    
    if (!empty($daily_breakdown)) {
        // For different view modes, get hourly data differently
        if ($view_mode == 'daily') {
            // For daily view, show hourly data for the most recent day
            $latest_date = max(array_column($daily_breakdown, 'sale_date'));
            $hourly_sales = $reportController->getHourlySales($latest_date);
        } else if ($view_mode == 'weekly') {
            // For weekly view, aggregate hourly data across all days in the range
            $hourly_sales = [];
            foreach ($daily_breakdown as $day) {
                $day_hourly = $reportController->getHourlySales($day['sale_date']);
                foreach ($day_hourly as $hour) {
                    if (!isset($hourly_sales[$hour['hour']])) {
                        $hourly_sales[$hour['hour']] = [
                            'hour' => $hour['hour'],
                            'sales_amount' => 0,
                            'transactions' => 0
                        ];
                    }
                    $hourly_sales[$hour['hour']]['sales_amount'] += $hour['sales_amount'];
                    $hourly_sales[$hour['hour']]['transactions'] += $hour['transactions'];
                }
            }
            $hourly_sales = array_values($hourly_sales);
        } else if ($view_mode == 'monthly') {
            // For monthly view, aggregate hourly data across all days in the range
            $hourly_sales = [];
            foreach ($daily_breakdown as $day) {
                $day_hourly = $reportController->getHourlySales($day['sale_date']);
                foreach ($day_hourly as $hour) {
                    if (!isset($hourly_sales[$hour['hour']])) {
                        $hourly_sales[$hour['hour']] = [
                            'hour' => $hour['hour'],
                            'sales_amount' => 0,
                            'transactions' => 0
                        ];
                    }
                    $hourly_sales[$hour['hour']]['sales_amount'] += $hour['sales_amount'];
                    $hourly_sales[$hour['hour']]['transactions'] += $hour['transactions'];
                }
            }
            $hourly_sales = array_values($hourly_sales);
        }
        
        // Create array for all 24 hours
        for ($i = 0; $i < 24; $i++) {
            $hourly_data[$i] = [
                'hour' => $i,
                'sales_amount' => 0,
                'transactions' => 0
            ];
        }
        
        // Fill in actual data
        foreach ($hourly_sales as $hour) {
            $hourly_data[$hour['hour']] = $hour;
        }
    }
    ?>

    <?php if ($view_mode == 'daily'): ?>
    <!-- Daily Sales Breakdown -->
    <div class="report-section">
        <div class="section-header">
            <h2><i class="fas fa-calendar-day me-2"></i>Daily Sales Breakdown</h2>
            <div class="export-options">
                <button class="btn btn-sm btn-outline-success" onclick="printDailySalesPDF()">
                    <i class="fas fa-download me-1"></i>Download PDF
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Sales</th>
                        <th>Transactions</th>
                        <th>Average Sale</th>
                        <th>Tables Served</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daily_breakdown)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No sales data available</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($daily_breakdown as $day): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($day['sale_date'])); ?></td>
                            <td><?php echo $reportController->formatCurrency($day['total_sales']); ?></td>
                            <td><?php echo $reportController->formatNumber($day['transaction_count']); ?></td>
                            <td><?php echo $reportController->formatCurrency($day['total_sales'] / max($day['transaction_count'], 1)); ?></td>
                            <td>
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hourly Sales Breakdown (Available for all view modes) -->
    <div class="report-section">
        <div class="section-header">
            <h2>
                <i class="fas fa-clock me-2"></i>
                Hourly Sales Breakdown
            </h2>
            <div class="export-options">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleHourlyView()">
                    <i class="fas fa-table me-1"></i>Toggle Table View
                </button>
                <a href="export_report.php?type=hourly_sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&view=<?php echo $view_mode; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-download"></i> Export
                </a>
            </div>
        </div>
        
        <!-- Hourly Chart (Default View) -->
        <div id="hourlyChartContainer" class="chart-container">
            <canvas id="hourlyChart"></canvas>
        </div>
        
        <!-- Hourly Table (Hidden by Default) -->
        <div id="hourlyTableContainer" style="display: none;">
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Hour</th>
                            <th>Sales Amount</th>
                            <th>Transactions</th>
                            <th>Average Sale</th>
                            <th>Percentage of <?php echo $view_mode == 'daily' ? 'Day' : ($view_mode == 'weekly' ? 'Week' : 'Month'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_period_sales = array_sum(array_column($hourly_data, 'sales_amount'));
                        foreach ($hourly_data as $hour): 
                            $percentage = $total_period_sales > 0 ? ($hour['sales_amount'] / $total_period_sales) * 100 : 0;
                            $display_hour = $hour['hour'] == 0 ? '12 AM' : ($hour['hour'] < 12 ? $hour['hour'] . ' AM' : ($hour['hour'] == 12 ? '12 PM' : ($hour['hour'] - 12) . ' PM'));
                        ?>
                        <tr>
                            <td><?php echo $display_hour; ?></td>
                            <td><?php echo $reportController->formatCurrency($hour['sales_amount']); ?></td>
                            <td><?php echo $reportController->formatNumber($hour['transactions']); ?></td>
                            <td><?php echo $reportController->formatCurrency($hour['transactions'] > 0 ? $hour['sales_amount'] / $hour['transactions'] : 0); ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                         aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="report-row">
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-trophy me-2"></i>Top Selling Items (Monthly Performance)</h2>
                <div class="export-options">
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleTopItemsView()">
                        <i class="fas fa-chart-bar me-1"></i>Toggle Chart View
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="printTopItemsPDF()">
                        <i class="fas fa-download me-1"></i>Download PDF (Single Page)
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="printTopItemsReceipt()">
                        <i class="fas fa-receipt me-1"></i>Print Receipt (Thermal)
                    </button>
                    <a href="export_report.php?type=top_items&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download"></i> Export Excel
                    </a>
                </div>
            </div>
            
            <!-- Top Items Chart (Hidden by Default) -->
            <div id="topItemsChartContainer" style="display: none;">
                <canvas id="topItemsChart"></canvas>
            </div>
            
            <!-- Top Items Table (Default View) -->
            <div id="topItemsTableContainer">
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                                <th>Rank</th>
                                <th>Item Name</th>
                                <th>Unit Price</th>
                            <th>Quantity Sold</th>
                            <th>Total Sales</th>
                                <th>Days Sold</th>
                                <th>Avg per Order</th>
                                <th>Total Orders</th>
                                <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_items)): ?>
                        <tr>
                                <td colspan="9" class="text-center">No data available</td>
                        </tr>
                        <?php else: ?>
                                <?php 
                                $rank = 1;
                                $total_period_sales = array_sum(array_column($top_items, 'total_sales'));
                                foreach ($top_items as $item): 
                                    $percentage = $total_period_sales > 0 ? ($item['total_sales'] / $total_period_sales) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-<?php echo $rank; ?>">
                                            #<?php echo $rank; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="item-info">
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>RM <?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>
                                        <span class="quantity-badge">
                                            <?php echo number_format($item['quantity_sold']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="sales-amount">
                                            RM <?php echo number_format($item['total_sales'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="days-badge">
                                            <?php echo number_format($item['days_sold']); ?> days
                                        </span>
                                    </td>
                                    <td><?php echo number_format($item['avg_quantity_per_order'], 1); ?></td>
                                    <td><?php echo number_format($item['total_orders']); ?></td>
                                    <td>
                                        <div class="performance-bar">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                     aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                            </tr>
                                <?php 
                                $rank++;
                                endforeach; 
                                ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="report-section">
            <div class="section-header">
                <h2>Sales by Table</h2>
                <div class="export-options">
                    <a href="export_report.php?type=table_sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Transactions</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($table_sales)): ?>
                        <tr>
                            <td colspan="3" class="text-center">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($table_sales as $table): ?>
                            <tr>
                                <td>Table <?php echo htmlspecialchars($table['table_number']); ?></td>
                                <td><?php echo number_format($table['transaction_count']); ?></td>
                                <td>RM <?php echo number_format($table['total_sales'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

// Add custom CSS link
$extra_css = '<link rel="stylesheet" href="css/reports.css">';

// Add Chart.js and custom JavaScript
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Chart data
        const labels = ' . json_encode($chart_labels) . ';
        const salesData = ' . json_encode($chart_data) . ';
        const transactionData = ' . json_encode($chart_transactions) . ';
        
        // Create gradient for sales chart
        const ctx = document.getElementById("salesChart").getContext("2d");
        const salesGradient = ctx.createLinearGradient(0, 0, 0, 400);
        salesGradient.addColorStop(0, "rgba(79, 70, 229, 0.4)");
        salesGradient.addColorStop(1, "rgba(79, 70, 229, 0.0)");
        
        // Create chart
        const salesChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Sales (RM)",
                        data: salesData,
                        backgroundColor: salesGradient,
                        borderColor: "rgba(79, 70, 229, 1)",
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: "#ffffff",
                        pointBorderColor: "rgba(79, 70, 229, 1)",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: "#ffffff",
                        pointHoverBorderColor: "rgba(79, 70, 229, 1)",
                        pointHoverBorderWidth: 3,
                        yAxisID: "y"
                    },
                    {
                        label: "Transactions",
                        data: transactionData,
                        type: "bar",
                        backgroundColor: "rgba(16, 185, 129, 0.7)",
                        borderColor: "rgba(16, 185, 129, 1)",
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: "y1"
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: "index",
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: "top",
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: "index",
                        intersect: false,
                        padding: 12,
                        bodySpacing: 6,
                        titleSpacing: 6,
                        backgroundColor: "rgba(0, 0, 0, 0.8)",
                        titleColor: "#fff",
                        bodyColor: "#fff",
                        borderColor: "rgba(0, 0, 0, 0.1)",
                        borderWidth: 1,
                        displayColors: true,
                        usePointStyle: true,
                        titleFont: {
                            size: 14,
                            weight: "bold"
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || "";
                                if (label) {
                                    label += ": ";
                                }
                                if (context.datasetIndex === 0) {
                                    label += "RM " + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: "left",
                        title: {
                            display: true,
                            text: "Sales (RM)",
                            color: "rgba(79, 70, 229, 1)",
                            font: {
                                weight: "bold",
                                size: 13
                            }
                        },
                        grid: {
                            drawBorder: false,
                            color: "rgba(0, 0, 0, 0.05)"
                        },
                        ticks: {
                            padding: 10,
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return "RM " + value.toFixed(2);
                            }
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: "right",
                        grid: {
                            drawOnChartArea: false,
                            drawBorder: false
                        },
                        title: {
                            display: true,
                            text: "Transactions",
                            color: "rgba(16, 185, 129, 1)",
                            font: {
                                weight: "bold",
                                size: 13
                            }
                        },
                        ticks: {
                            padding: 10,
                            precision: 0,
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            drawBorder: false,
                            color: "rgba(0, 0, 0, 0.05)"
                        },
                        ticks: {
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                animations: {
                    tension: {
                        duration: 1000,
                        easing: "linear"
                    }
                }
            }
        });
        
        // Hourly chart functionality
        let hourlyChart = null;
        
        // Create hourly chart on page load (like Monthly Sales)
        createHourlyChart();
        
        // Handle window resize for responsive charts
        let resizeTimeout;
        window.addEventListener("resize", function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (salesChart) {
                    salesChart.resize();
                }
                if (hourlyChart) {
                    hourlyChart.resize();
                }
                if (topItemsChart) {
                    topItemsChart.resize();
                }
            }, 250);
        });
        
        // Handle orientation change on mobile devices
        window.addEventListener("orientationchange", function() {
            setTimeout(function() {
                if (salesChart) {
                    salesChart.resize();
                }
                if (hourlyChart) {
                    hourlyChart.resize();
                }
                if (topItemsChart) {
                    topItemsChart.resize();
                }
            }, 500);
        });
        
        // Toggle between table and chart view for hourly sales
        window.toggleHourlyView = function() {
            const chartContainer = document.getElementById("hourlyChartContainer");
            const tableContainer = document.getElementById("hourlyTableContainer");
            const toggleBtn = document.querySelector(\'[onclick="toggleHourlyView()"]\');
            
            if (chartContainer.style.display === "none") {
                // Show chart
                chartContainer.style.display = "block";
                tableContainer.style.display = "none";
                toggleBtn.innerHTML = \'<i class="fas fa-table me-1"></i>Toggle Table View\';
                
                // Resize chart if it exists
                if (hourlyChart) {
                    hourlyChart.resize();
                }
            } else {
                // Show table
                chartContainer.style.display = "none";
                tableContainer.style.display = "block";
                toggleBtn.innerHTML = \'<i class="fas fa-chart-bar me-1"></i>Toggle Chart View\';
            }
        };
        
        // Toggle between table and chart view for top items
        let topItemsChart = null;
        window.toggleTopItemsView = function() {
            const chartContainer = document.getElementById("topItemsChartContainer");
            const tableContainer = document.getElementById("topItemsTableContainer");
            const toggleBtn = document.querySelector(\'[onclick="toggleTopItemsView()"]\');
            
            if (chartContainer.style.display === "none") {
                // Show chart
                chartContainer.style.display = "block";
                tableContainer.style.display = "none";
                toggleBtn.innerHTML = \'<i class="fas fa-table me-1"></i>Toggle Table View\';
                
                // Create chart if it doesn\'t exist
                if (!topItemsChart) {
                    createTopItemsChart();
                } else {
                    topItemsChart.resize();
                }
            } else {
                // Show table
                chartContainer.style.display = "none";
                tableContainer.style.display = "block";
                toggleBtn.innerHTML = \'<i class="fas fa-chart-bar me-1"></i>Toggle Chart View\';
            }
        };
        
        
        // Create hourly sales chart
        function createHourlyChart() {
            const ctx = document.getElementById("hourlyChart").getContext("2d");
            
            // Get hourly data from PHP
            const hourlyData = ' . json_encode($hourly_data ?? []) . ';
            
            // Prepare hourly data
            const hourlyLabels = [];
            const hourlySalesData = [];
            const hourlyTransactionData = [];
            
            hourlyData.forEach(function(hour) {
                const displayHour = hour.hour == 0 ? \'12 AM\' : 
                                  (hour.hour < 12 ? hour.hour + \' AM\' : 
                                  (hour.hour == 12 ? \'12 PM\' : (hour.hour - 12) + \' PM\'));
                hourlyLabels.push(displayHour);
                hourlySalesData.push(parseFloat(hour.sales_amount) || 0);
                hourlyTransactionData.push(parseInt(hour.transactions) || 0);
            });
            
            // Create gradient for hourly chart
            const hourlyGradient = ctx.createLinearGradient(0, 0, 0, 400);
            hourlyGradient.addColorStop(0, "rgba(16, 185, 129, 0.4)");
            hourlyGradient.addColorStop(1, "rgba(16, 185, 129, 0.0)");
            
            hourlyChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: hourlyLabels,
                    datasets: [
                        {
                            label: "Sales (RM)",
                            data: hourlySalesData,
                            backgroundColor: hourlyGradient,
                            borderColor: "rgba(16, 185, 129, 1)",
                            borderWidth: 2,
                            borderRadius: 4,
                            borderSkipped: false,
                            yAxisID: "y"
                        },
                        {
                            label: "Transactions",
                            data: hourlyTransactionData,
                            type: "line",
                            backgroundColor: "rgba(79, 70, 229, 0.1)",
                            borderColor: "rgba(79, 70, 229, 1)",
                            borderWidth: 3,
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: "#ffffff",
                            pointBorderColor: "rgba(79, 70, 229, 1)",
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            yAxisID: "y1"
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: "index",
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: "top",
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            mode: "index",
                            intersect: false,
                            padding: 12,
                            bodySpacing: 6,
                            titleSpacing: 6,
                            backgroundColor: "rgba(0, 0, 0, 0.8)",
                            titleColor: "#fff",
                            bodyColor: "#fff",
                            borderColor: "rgba(0, 0, 0, 0.1)",
                            borderWidth: 1,
                            displayColors: true,
                            usePointStyle: true,
                            titleFont: {
                                size: 14,
                                weight: "bold"
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || "";
                                    if (label) {
                                        label += ": ";
                                    }
                                    if (context.datasetIndex === 0) {
                                        label += "RM " + context.parsed.y.toFixed(2);
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: "left",
                            title: {
                                display: true,
                                text: "Sales (RM)",
                                color: "rgba(16, 185, 129, 1)",
                                font: {
                                    weight: "bold",
                                    size: 13
                                }
                            },
                            grid: {
                                drawBorder: false,
                                color: "rgba(0, 0, 0, 0.05)"
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return "RM " + value.toFixed(2);
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: "right",
                            grid: {
                                drawOnChartArea: false,
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: "Transactions",
                                color: "rgba(79, 70, 229, 1)",
                                font: {
                                    weight: "bold",
                                    size: 13
                                }
                            },
                            ticks: {
                                padding: 10,
                                precision: 0,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                drawBorder: false,
                                color: "rgba(0, 0, 0, 0.05)"
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 11
                                },
                                maxRotation: 45
                            }
                        }
                    },
                    animations: {
                        tension: {
                            duration: 1000,
                            easing: "linear"
                        }
                    }
                }
            });
        }
        
        // Download Top Items as PDF (Auto Download)
        window.printTopItemsPDF = function() {
            const startDate = "' . $start_date . '";
            const endDate = "' . $end_date . '";
            
            // Create a temporary link and trigger download
            const link = document.createElement("a");
            link.href = "generate_top_items_pdf.php?start_date=" + encodeURIComponent(startDate) + "&end_date=" + encodeURIComponent(endDate);
            link.download = "Top_Selling_Items_" + startDate + "_to_" + endDate + ".pdf";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
        
        // Download Daily Sales as PDF (Auto Download)
        window.printDailySalesPDF = function() {
            const startDate = "' . $start_date . '";
            const endDate = "' . $end_date . '";
            
            // Create a temporary link and trigger download
            const link = document.createElement("a");
            link.href = "generate_daily_sales_pdf.php?start_date=" + encodeURIComponent(startDate) + "&end_date=" + encodeURIComponent(endDate);
            link.download = "Daily_Sales_Breakdown_" + startDate + "_to_" + endDate + ".pdf";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
        
        // Print Top Items as Receipt (Simple Design)
        window.printTopItemsReceipt = function() {
            const topItemsData = ' . json_encode($top_items ?? []) . ';
            const startDate = "' . $start_date . '";
            const endDate = "' . $end_date . '";
            
            // Create a new window for receipt printing
            const printWindow = window.open("", "_blank", "width=400,height=600");
            
            const htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Top Items Receipt</title>
                    <style>
                        body { 
                            font-family: "Arial", sans-serif; 
                            font-size: 14px; 
                            margin: 0; 
                            padding: 10px; 
                            background: white;
                            width: 80mm;
                        }
                        .receipt-header { 
                            text-align: center; 
                            margin-bottom: 15px; 
                            border-bottom: 2px solid #000; 
                            padding-bottom: 10px; 
                        }
                        .receipt-header h1 { 
                            font-size: 22px; 
                            margin: 0 0 5px 0; 
                            font-weight: bold; 
                            text-transform: uppercase;
                        }
                        .receipt-header p { 
                            margin: 2px 0; 
                            font-size: 12px; 
                            font-weight: bold;
                        }
                        .receipt-info { 
                            margin-bottom: 15px; 
                            font-size: 12px;
                            font-weight: bold;
                        }
                        .receipt-info p { 
                            margin: 2px 0; 
                        }
                        .item-row { 
                            display: flex; 
                            justify-content: space-between; 
                            margin-bottom: 8px; 
                            font-size: 12px;
                            font-weight: bold;
                            padding: 5px 0;
                            border-bottom: 1px dotted #ccc;
                        }
                        .item-name { 
                            flex: 1; 
                            margin-right: 10px; 
                            word-wrap: break-word;
                        }
                        .item-stats { 
                            text-align: right; 
                            min-width: 70px;
                            font-size: 11px;
                        }
                        .rank { 
                            font-weight: bold; 
                            margin-right: 5px; 
                        }
                        .receipt-footer { 
                            margin-top: 15px; 
                            border-top: 2px solid #000; 
                            padding-top: 10px; 
                            text-align: center; 
                            font-size: 11px; 
                            font-weight: bold;
                        }
                        .divider {
                            text-align: center;
                            margin: 10px 0;
                            font-size: 12px; 
                        }
                        @media print {
                            body { 
                                margin: 0; 
                                padding: 5px;
                                width: 80mm;
                            }
                            @page { 
                                margin: 0; 
                                size: 80mm 297mm;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-header">
                        <h1>TOP SELLING ITEMS</h1>
                        <p>${startDate} to ${endDate}</p>
                        <p>${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="receipt-info">
                        <p>Total Items: ${topItemsData.length}</p>
                    </div>
                    
                    <div class="items-section">
                    ${topItemsData.slice(0, 10).map((item, index) => {
                        const totalSales = topItemsData.reduce((sum, i) => sum + parseFloat(i.total_sales), 0);
                        const percentage = totalSales > 0 ? ((parseFloat(item.total_sales) / totalSales) * 100).toFixed(1) : 0;
                        return `
                            <div class="item-row">
                                <div class="item-name">
                                    <span class="rank">#${index + 1}</span>
                                    ${item.item_name}
                                </div>
                                <div class="item-stats">
                                    Qty: ${parseInt(item.quantity_sold)}<br>
                                    RM${parseFloat(item.total_sales).toFixed(2)}<br>
                                    ${percentage}%
                                </div>
                            </div>
                        `;
                    }).join("")}
                    </div>
                    
                    <div class="divider">----------------------------</div>
                    
                    <div class="receipt-footer">
                        <p>Generated by Food Ordering System</p>
                        <p>Thank you!</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(htmlContent);
            printWindow.document.close();
            
            // Wait for content to load, then print
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                    printWindow.close();
                }, 500);
            };
        };
        
        // Create top items chart
        function createTopItemsChart() {
            const ctx = document.getElementById("topItemsChart").getContext("2d");
            
            // Get top items data from PHP
            const topItemsData = ' . json_encode($top_items ?? []) . ';
            
            // Prepare chart data
            const itemLabels = [];
            const salesData = [];
            const quantityData = [];
            const colors = [
                \'rgba(255, 215, 0, 0.8)\',   // Gold
                \'rgba(192, 192, 192, 0.8)\', // Silver
                \'rgba(205, 127, 50, 0.8)\',  // Bronze
                \'rgba(79, 70, 229, 0.8)\',   // Blue
                \'rgba(99, 102, 241, 0.8)\',  // Indigo
                \'rgba(107, 114, 128, 0.8)\', // Gray
                \'rgba(156, 163, 175, 0.8)\', // Light Gray
                \'rgba(16, 185, 129, 0.8)\',  // Green
                \'rgba(245, 158, 11, 0.8)\',  // Yellow
                \'rgba(239, 68, 68, 0.8)\',   // Red
                \'rgba(139, 92, 246, 0.8)\',  // Purple
                \'rgba(236, 72, 153, 0.8)\',  // Pink
                \'rgba(6, 182, 212, 0.8)\',   // Cyan
                \'rgba(34, 197, 94, 0.8)\',   // Emerald
                \'rgba(251, 146, 60, 0.8)\'   // Orange
            ];
            
            // Limit to top 10 for better chart readability
            const top10Items = topItemsData.slice(0, 10);
            
            top10Items.forEach(function(item, index) {
                // Truncate long item names
                const shortName = item.item_name.length > 20 ? 
                    item.item_name.substring(0, 17) + \'...\' : 
                    item.item_name;
                itemLabels.push(shortName);
                salesData.push(parseFloat(item.total_sales) || 0);
                quantityData.push(parseInt(item.quantity_sold) || 0);
            });
            
            topItemsChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: itemLabels,
                    datasets: [
                        {
                            label: "Total Sales (RM)",
                            data: salesData,
                            backgroundColor: colors.slice(0, itemLabels.length),
                            borderColor: colors.slice(0, itemLabels.length).map(color => color.replace(\'0.8\', \'1\')),
                            borderWidth: 2,
                            borderRadius: 6,
                            borderSkipped: false,
                            yAxisID: "y"
                        },
                        {
                            label: "Quantity Sold",
                            data: quantityData,
                            type: "line",
                            backgroundColor: "rgba(16, 185, 129, 0.1)",
                            borderColor: "rgba(16, 185, 129, 1)",
                            borderWidth: 3,
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: "#ffffff",
                            pointBorderColor: "rgba(16, 185, 129, 1)",
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8,
                            yAxisID: "y1"
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: "index",
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: "top",
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            mode: "index",
                            intersect: false,
                            padding: 12,
                            bodySpacing: 6,
                            titleSpacing: 6,
                            backgroundColor: "rgba(0, 0, 0, 0.8)",
                            titleColor: "#fff",
                            bodyColor: "#fff",
                            borderColor: "rgba(0, 0, 0, 0.1)",
                            borderWidth: 1,
                            displayColors: true,
                            usePointStyle: true,
                            titleFont: {
                                size: 14,
                                weight: "bold"
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || "";
                                    if (label) {
                                        label += ": ";
                                    }
                                    if (context.datasetIndex === 0) {
                                        label += "RM " + context.parsed.y.toFixed(2);
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: "left",
                            title: {
                                display: true,
                                text: "Sales (RM)",
                                color: "rgba(79, 70, 229, 1)",
                                font: {
                                    weight: "bold",
                                    size: 13
                                }
                            },
                            grid: {
                                drawBorder: false,
                                color: "rgba(0, 0, 0, 0.05)"
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return "RM " + value.toFixed(2);
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: "right",
                            grid: {
                                drawOnChartArea: false,
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: "Quantity Sold",
                                color: "rgba(16, 185, 129, 1)",
                                font: {
                                    weight: "bold",
                                    size: 13
                                }
                            },
                            ticks: {
                                padding: 10,
                                precision: 0,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                drawBorder: false,
                                color: "rgba(0, 0, 0, 0.05)"
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 11
                                },
                                maxRotation: 45
                            }
                        }
                    },
                    animations: {
                        tension: {
                            duration: 1000,
                            easing: "linear"
                        }
                    }
                }
            });
        }
    });
</script>';

// Include the layout
include 'includes/layout.php';
?> 