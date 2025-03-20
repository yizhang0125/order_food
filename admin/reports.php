<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/Order.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$orderModel = new Order($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
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
    // Sales summary data
    $sales_summary_sql = "SELECT 
                            COUNT(p.payment_id) as total_transactions,
                            SUM(p.amount) as total_sales,
                            AVG(p.amount) as average_sale,
                            COUNT(DISTINCT o.table_id) as tables_served,
                            MAX(p.amount) as highest_sale,
                            MIN(p.amount) as lowest_sale
                          FROM payments p
                          JOIN orders o ON p.order_id = o.id
                          WHERE p.payment_date BETWEEN ? AND ?";
    
    $sales_stmt = $db->prepare($sales_summary_sql);
    $sales_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $sales_summary = $sales_stmt->fetch(PDO::FETCH_ASSOC);
    
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
    
    // Top selling items
    $top_items_sql = "SELECT 
                        m.name as item_name,
                        SUM(oi.quantity) as quantity_sold,
                        SUM(oi.quantity * m.price) as total_sales
                      FROM order_items oi
                      JOIN menu_items m ON oi.menu_item_id = m.id
                      JOIN orders o ON oi.order_id = o.id
                      JOIN payments p ON o.id = p.order_id
                      WHERE p.payment_date BETWEEN ? AND ?
                      GROUP BY m.id
                      ORDER BY quantity_sold DESC
                      LIMIT 10";
    
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
                <h3>Total Amount</h3>
                <p>RM <?php echo number_format($sales_summary['total_sales'] ?? 0, 2); ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="summary-info">
                <h3>Transactions</h3>
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

    <div class="report-row">
        <div class="report-section">
            <div class="section-header">
                <h2>Top Selling Items</h2>
                <div class="export-options">
                    <a href="export_report.php?type=top_items&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity Sold</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_items)): ?>
                        <tr>
                            <td colspan="3" class="text-center">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($top_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo number_format($item['quantity_sold']); ?></td>
                                <td>RM <?php echo number_format($item['total_sales'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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

// Add custom CSS
$extra_css = '
<style>
    :root {
        --primary: #4f46e5;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
    }

    .page-header {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-title i {
        color: var(--primary);
    }

    .date-filter {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .date-inputs {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .date-input {
        padding: 0.75rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        font-size: 0.95rem;
    }

    .filter-btn {
        padding: 0.75rem 1.5rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .summary-icon {
        width: 60px;
        height: 60px;
        background: var(--primary);
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .summary-info h3 {
        font-size: 1rem;
        color: var(--gray-600);
        margin: 0 0 0.5rem 0;
    }

    .summary-info p {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-800);
        margin: 0;
    }

    .report-section {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .section-header h2 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0;
    }

    .chart-container {
        height: 300px;
        position: relative;
    }

    .report-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .report-table th {
        background: var(--gray-50);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--gray-600);
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--gray-200);
    }

    .report-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        color: var(--gray-700);
        font-size: 0.95rem;
    }

    .report-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    .btn-outline-primary {
        color: var(--primary);
        border: 1px solid var(--primary);
        background: transparent;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
        text-decoration: none;
        font-size: 0.875rem;
    }

    .btn-outline-primary:hover {
        background: var(--primary);
        color: white;
    }

    @media (max-width: 768px) {
        .date-inputs {
            flex-direction: column;
        }
        
        .date-input {
            width: 100%;
        }
        
        .report-row {
            grid-template-columns: 1fr;
        }
    }

    .view-selector {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .view-selector label {
        font-weight: 500;
        color: var(--gray-600);
        margin: 0;
    }

    .btn-group {
        display: flex;
    }

    .btn-group .btn {
        border-radius: 0;
    }

    .btn-group .btn:first-child {
        border-top-left-radius: 0.5rem;
        border-bottom-left-radius: 0.5rem;
    }

    .btn-group .btn:last-child {
        border-top-right-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .btn-outline-primary {
        color: var(--primary);
        border-color: var(--primary);
        background-color: transparent;
    }

    .btn-outline-primary:hover {
        background-color: var(--primary);
        color: white;
    }

    .highlight-card {
        background: linear-gradient(135deg, var(--primary), #6366f1);
        color: white;
        border: none;
    }

    .highlight-card .summary-icon {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .highlight-card .summary-info h3 {
        color: rgba(255, 255, 255, 0.8);
    }

    .highlight-card .summary-info p {
        color: white;
        font-size: 2rem;
    }
</style>';

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
    });
</script>';

// Include the layout
include 'includes/layout.php';
?> 