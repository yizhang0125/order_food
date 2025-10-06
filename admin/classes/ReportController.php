<?php
require_once(__DIR__ . '/../../config/Database.php');

class ReportController {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Cash rounding function - rounds to nearest 0.05 (5 cents)
     */
    public function customRound($amount) {
        // Round to nearest 0.05 (5 cents) for cash transactions
        // Multiply by 20, round to nearest integer, then divide by 20
        return round($amount * 20) / 20;
    }
    
    /**
     * Recalculate payment amount with service tax
     */
    private function recalculatePaymentAmount($item_details, $systemSettings) {
        if (empty($item_details)) {
            return 0;
        }
        
        $subtotal = 0;
        $item_details_array = explode('||', $item_details);
        
        foreach ($item_details_array as $item) {
            if (empty(trim($item))) continue;
            list($quantity, $price) = explode(':', $item);
            $subtotal += $quantity * $price;
        }
        
        // Calculate tax and service tax
        $tax_rate = $systemSettings->getTaxRate();
        $service_tax_rate = $systemSettings->getServiceTaxRate();
        $tax_amount = $subtotal * $tax_rate;
        $service_tax_amount = $subtotal * $service_tax_rate;
        $total_with_tax = $subtotal + $tax_amount + $service_tax_amount;
        
        return $this->customRound($total_with_tax);
    }
    
    /**
     * Get sales summary for a date range
     */
    public function getSalesSummary($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_sale,
                        MAX(p.amount) as highest_sale
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_sales' => $this->customRound(floatval($result['total_sales'] ?? 0)),
                'total_transactions' => intval($result['total_transactions'] ?? 0),
                'average_sale' => $this->customRound(floatval($result['average_sale'] ?? 0)),
                'highest_sale' => $this->customRound(floatval($result['highest_sale'] ?? 0))
            ];
        } catch (Exception $e) {
            error_log("Error in getSalesSummary: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'highest_sale' => 0
            ];
        }
    }
    
    /**
     * Get sales summary based on view mode (daily, weekly, monthly)
     */
    public function getSalesSummaryByViewMode($start_date, $end_date, $view_mode) {
        try {
            if ($view_mode == 'weekly') {
                return $this->getWeeklySalesSummary($start_date, $end_date);
            } else if ($view_mode == 'monthly') {
                return $this->getMonthlySalesSummary($start_date, $end_date);
            } else {
                // Daily view (default)
                return $this->getDailySalesSummary($start_date, $end_date);
            }
        } catch (Exception $e) {
            error_log("Error in getSalesSummaryByViewMode: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'highest_sale' => 0
            ];
        }
    }
    
    /**
     * Get daily sales summary for a date range (shows individual daily data)
     */
    public function getDailySalesSummary($start_date, $end_date) {
        try {
            // Get the most recent day's data for daily view
            $sql = "SELECT 
                        DATE(p.payment_date) as sale_date,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_sale,
                        MAX(p.amount) as highest_sale
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY DATE(p.payment_date)
                    ORDER BY sale_date DESC
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // If no data found, get the latest available day
                $sql = "SELECT 
                            DATE(p.payment_date) as sale_date,
                            SUM(p.amount) as total_sales,
                            COUNT(p.payment_id) as total_transactions,
                            AVG(p.amount) as average_sale,
                            MAX(p.amount) as highest_sale
                        FROM payments p
                        WHERE p.payment_status = 'completed'
                        GROUP BY DATE(p.payment_date)
                        ORDER BY sale_date DESC
                        LIMIT 1";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return [
                'total_sales' => $this->customRound(floatval($result['total_sales'] ?? 0)),
                'total_transactions' => intval($result['total_transactions'] ?? 0),
                'average_sale' => $this->customRound(floatval($result['average_sale'] ?? 0)),
                'highest_sale' => $this->customRound(floatval($result['highest_sale'] ?? 0)),
                'sale_date' => $result['sale_date'] ?? $start_date
            ];
        } catch (Exception $e) {
            error_log("Error in getDailySalesSummary: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'highest_sale' => 0,
                'sale_date' => $start_date
            ];
        }
    }
    
    /**
     * Get weekly sales summary for a date range (shows individual weekly data)
     */
    public function getWeeklySalesSummary($start_date, $end_date) {
        try {
            // Get the most recent week's data for weekly view
            $sql = "SELECT 
                        YEARWEEK(p.payment_date, 1) as year_week,
                        MIN(DATE(p.payment_date)) as week_start,
                        MAX(DATE(p.payment_date)) as week_end,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_sale,
                        MAX(p.amount) as highest_sale
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY YEARWEEK(p.payment_date, 1)
                    ORDER BY year_week DESC
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // If no data found, get the latest available week
                $sql = "SELECT 
                            YEARWEEK(p.payment_date, 1) as year_week,
                            MIN(DATE(p.payment_date)) as week_start,
                            MAX(DATE(p.payment_date)) as week_end,
                            SUM(p.amount) as total_sales,
                            COUNT(p.payment_id) as total_transactions,
                            AVG(p.amount) as average_sale,
                            MAX(p.amount) as highest_sale
                        FROM payments p
                        WHERE p.payment_status = 'completed'
                        GROUP BY YEARWEEK(p.payment_date, 1)
                        ORDER BY year_week DESC
                        LIMIT 1";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return [
                'total_sales' => $this->customRound(floatval($result['total_sales'] ?? 0)),
                'total_transactions' => intval($result['total_transactions'] ?? 0),
                'average_sale' => $this->customRound(floatval($result['average_sale'] ?? 0)),
                'highest_sale' => $this->customRound(floatval($result['highest_sale'] ?? 0)),
                'year_week' => $result['year_week'] ?? null,
                'week_start' => $result['week_start'] ?? $start_date,
                'week_end' => $result['week_end'] ?? $end_date
            ];
        } catch (Exception $e) {
            error_log("Error in getWeeklySalesSummary: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'highest_sale' => 0,
                'year_week' => null,
                'week_start' => $start_date,
                'week_end' => $end_date
            ];
        }
    }
    
    /**
     * Get monthly sales summary for a date range (shows individual monthly data)
     */
    public function getMonthlySalesSummary($start_date, $end_date) {
        try {
            // Get the most recent month's data for monthly view
            $sql = "SELECT 
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        MIN(DATE(p.payment_date)) as month_start,
                        MAX(DATE(p.payment_date)) as month_end,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_sale,
                        MAX(p.amount) as highest_sale
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                    ORDER BY month DESC
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // If no data found, get the latest available month
                $sql = "SELECT 
                            DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                            MIN(DATE(p.payment_date)) as month_start,
                            MAX(DATE(p.payment_date)) as month_end,
                            SUM(p.amount) as total_sales,
                            COUNT(p.payment_id) as total_transactions,
                            AVG(p.amount) as average_sale,
                            MAX(p.amount) as highest_sale
                        FROM payments p
                        WHERE p.payment_status = 'completed'
                        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                        ORDER BY month DESC
                        LIMIT 1";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return [
                'total_sales' => $this->customRound(floatval($result['total_sales'] ?? 0)),
                'total_transactions' => intval($result['total_transactions'] ?? 0),
                'average_sale' => $this->customRound(floatval($result['average_sale'] ?? 0)),
                'highest_sale' => $this->customRound(floatval($result['highest_sale'] ?? 0)),
                'month' => $result['month'] ?? null,
                'month_start' => $result['month_start'] ?? $start_date,
                'month_end' => $result['month_end'] ?? $end_date
            ];
        } catch (Exception $e) {
            error_log("Error in getMonthlySalesSummary: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'highest_sale' => 0,
                'month' => null,
                'month_start' => $start_date,
                'month_end' => $end_date
            ];
        }
    }
    
    /**
     * Get sales data for a date range
     */
    public function getSalesData($start_date, $end_date, $view_mode = 'daily') {
        try {
            if ($view_mode == 'weekly') {
                $sql = "SELECT 
                            YEARWEEK(p.payment_date, 1) as year_week,
                            MIN(DATE(p.payment_date)) as sale_date,
                            SUM(p.amount) as total_sales,
                            COUNT(p.payment_id) as transaction_count
                        FROM payments p
                        WHERE p.payment_date BETWEEN ? AND ?
                        AND p.payment_status = 'completed'
                        GROUP BY YEARWEEK(p.payment_date, 1)
                        ORDER BY year_week";
            } else if ($view_mode == 'monthly') {
                $sql = "SELECT 
                            DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                            MIN(DATE(p.payment_date)) as sale_date,
                            SUM(p.amount) as total_sales,
                            COUNT(p.payment_id) as transaction_count
                        FROM payments p
                        WHERE p.payment_date BETWEEN ? AND ?
                        AND p.payment_status = 'completed'
                        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                        ORDER BY month";
            } else {
                // Daily view (default) - get individual payments to recalculate with service tax
                $sql = "SELECT 
                            DATE(p.payment_date) as sale_date,
                            p.payment_id,
                            p.amount,
                            GROUP_CONCAT(CONCAT(oi.quantity, ':', m.price) SEPARATOR '||') as item_details
                        FROM payments p
                        JOIN orders o ON p.order_id = o.id
                        LEFT JOIN order_items oi ON o.id = oi.order_id
                        LEFT JOIN menu_items m ON oi.menu_item_id = m.id
                        WHERE p.payment_date BETWEEN ? AND ?
                        AND p.payment_status = 'completed'
                        GROUP BY p.payment_id, DATE(p.payment_date)
                        ORDER BY sale_date";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($view_mode == 'daily') {
                // For daily view, recalculate amounts with service tax and group by date
                require_once(__DIR__ . '/../../classes/SystemSettings.php');
                $systemSettings = new SystemSettings($this->db);
                
                $daily_totals = [];
                foreach ($results as $payment) {
                    $sale_date = $payment['sale_date'];
                    
                    if (!isset($daily_totals[$sale_date])) {
                        $daily_totals[$sale_date] = [
                            'sale_date' => $sale_date,
                            'total_sales' => 0,
                            'transaction_count' => 0
                        ];
                    }
                    
                    // Recalculate amount with service tax
                    $recalculated_amount = $this->recalculatePaymentAmount($payment['item_details'], $systemSettings);
                    $daily_totals[$sale_date]['total_sales'] += $recalculated_amount;
                    $daily_totals[$sale_date]['transaction_count']++;
                }
                
                // Convert back to array format and apply rounding
                $results = array_values($daily_totals);
                foreach ($results as &$result) {
                    $result['total_sales'] = $this->customRound(floatval($result['total_sales']));
                }
            } else {
                // Apply cash rounding to all sales amounts for weekly/monthly
                foreach ($results as &$result) {
                    if (isset($result['total_sales'])) {
                        $result['total_sales'] = $this->customRound(floatval($result['total_sales']));
                    }
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in getSalesData: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get hourly sales data for a specific date
     */
    public function getHourlySales($date) {
        try {
            $sql = "SELECT 
                        HOUR(p.payment_date) as hour,
                        SUM(p.amount) as sales_amount,
                        COUNT(p.payment_id) as transactions
                    FROM payments p
                    WHERE DATE(p.payment_date) = ?
                    AND p.payment_status = 'completed'
                    GROUP BY HOUR(p.payment_date)
                    ORDER BY hour";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$date]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Apply cash rounding to sales amounts
            foreach ($results as &$result) {
                if (isset($result['sales_amount'])) {
                    $result['sales_amount'] = $this->customRound(floatval($result['sales_amount']));
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in getHourlySales: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get top selling items for a date range
     */
    public function getTopItems($start_date, $end_date, $limit = 15) {
        try {
            $sql = "SELECT 
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
                    AND p.payment_status = 'completed'
                    GROUP BY m.id
                    ORDER BY total_sales DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Apply cash rounding to total sales
            foreach ($results as &$result) {
                if (isset($result['total_sales'])) {
                    $result['total_sales'] = $this->customRound(floatval($result['total_sales']));
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in getTopItems: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get sales by table for a date range
     */
    public function getTableSales($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        t.table_number,
                        COUNT(p.payment_id) as transaction_count,
                        SUM(p.amount) as total_sales
                    FROM payments p
                    JOIN orders o ON p.order_id = o.id
                    JOIN tables t ON o.table_id = t.id
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY t.table_number
                    ORDER BY total_sales DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Apply cash rounding to total sales
            foreach ($results as &$result) {
                if (isset($result['total_sales'])) {
                    $result['total_sales'] = $this->customRound(floatval($result['total_sales']));
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in getTableSales: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Format currency amount with cash rounding
     */
    public function formatCurrency($amount) {
        return 'RM ' . number_format($this->customRound(floatval($amount)), 2);
    }
    
    /**
     * Format number with thousand separators
     */
    public function formatNumber($number) {
        return number_format(intval($number));
    }
    
    /**
     * Get total daily sales for a specific date
     */
    public function getTotalDailySales($date) {
        try {
            $sql = "SELECT 
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_sale
                    FROM payments p
                    WHERE DATE(p.payment_date) = ?
                    AND p.payment_status = 'completed'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_sales' => $this->customRound(floatval($result['total_sales'] ?? 0)),
                'total_transactions' => intval($result['total_transactions'] ?? 0),
                'average_sale' => $this->customRound(floatval($result['average_sale'] ?? 0)),
                'date' => $date
            ];
        } catch (Exception $e) {
            error_log("Error in getTotalDailySales: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'date' => $date
            ];
        }
    }
    
    /**
     * Get total weekly sales for a specific week
     */
    public function getTotalWeeklySales($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_sale,
                        MIN(DATE(p.payment_date)) as week_start,
                        MAX(DATE(p.payment_date)) as week_end
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_sales' => $this->customRound(floatval($result['total_sales'] ?? 0)),
                'total_transactions' => intval($result['total_transactions'] ?? 0),
                'average_sale' => $this->customRound(floatval($result['average_sale'] ?? 0)),
                'week_start' => $result['week_start'],
                'week_end' => $result['week_end']
            ];
        } catch (Exception $e) {
            error_log("Error in getTotalWeeklySales: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'week_start' => $start_date,
                'week_end' => $end_date
            ];
        }
    }
    
    /**
     * Get total monthly sales for a specific month
     */
    public function getTotalMonthlySales($year, $month) {
        try {
            $sql = "SELECT 
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_sale,
                        MIN(DATE(p.payment_date)) as month_start,
                        MAX(DATE(p.payment_date)) as month_end
                    FROM payments p
                    WHERE YEAR(p.payment_date) = ? 
                    AND MONTH(p.payment_date) = ?
                    AND p.payment_status = 'completed'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$year, $month]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_sales' => $this->customRound(floatval($result['total_sales'] ?? 0)),
                'total_transactions' => intval($result['total_transactions'] ?? 0),
                'average_sale' => $this->customRound(floatval($result['average_sale'] ?? 0)),
                'month_start' => $result['month_start'],
                'month_end' => $result['month_end'],
                'year' => $year,
                'month' => $month
            ];
        } catch (Exception $e) {
            error_log("Error in getTotalMonthlySales: " . $e->getMessage());
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_sale' => 0,
                'month_start' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01',
                'month_end' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-31',
                'year' => $year,
                'month' => $month
            ];
        }
    }
    
    /**
     * Get today's total sales
     */
    public function getTodaySales() {
        $today = date('Y-m-d');
        return $this->getTotalDailySales($today);
    }
    
    /**
     * Get current week's total sales
     */
    public function getCurrentWeekSales() {
        $start_of_week = date('Y-m-d', strtotime('monday this week'));
        $end_of_week = date('Y-m-d', strtotime('sunday this week'));
        return $this->getTotalWeeklySales($start_of_week, $end_of_week);
    }
    
    /**
     * Get current month's total sales
     */
    public function getCurrentMonthSales() {
        $current_year = date('Y');
        $current_month = date('n'); // 1-12
        return $this->getTotalMonthlySales($current_year, $current_month);
    }
    
    /**
     * Get sales comparison (today vs yesterday, this week vs last week, this month vs last month)
     */
    public function getSalesComparison() {
        try {
            // Today vs Yesterday
            $today = $this->getTodaySales();
            $yesterday = $this->getTotalDailySales(date('Y-m-d', strtotime('-1 day')));
            
            // This week vs Last week
            $this_week = $this->getCurrentWeekSales();
            $last_week_start = date('Y-m-d', strtotime('monday last week'));
            $last_week_end = date('Y-m-d', strtotime('sunday last week'));
            $last_week = $this->getTotalWeeklySales($last_week_start, $last_week_end);
            
            // This month vs Last month
            $this_month = $this->getCurrentMonthSales();
            $last_month_year = date('Y', strtotime('first day of last month'));
            $last_month = date('n', strtotime('first day of last month'));
            $last_month_sales = $this->getTotalMonthlySales($last_month_year, $last_month);
            
            return [
                'daily' => [
                    'today' => $today,
                    'yesterday' => $yesterday,
                    'change' => $today['total_sales'] - $yesterday['total_sales'],
                    'change_percent' => $yesterday['total_sales'] > 0 ? 
                        (($today['total_sales'] - $yesterday['total_sales']) / $yesterday['total_sales']) * 100 : 0
                ],
                'weekly' => [
                    'this_week' => $this_week,
                    'last_week' => $last_week,
                    'change' => $this_week['total_sales'] - $last_week['total_sales'],
                    'change_percent' => $last_week['total_sales'] > 0 ? 
                        (($this_week['total_sales'] - $last_week['total_sales']) / $last_week['total_sales']) * 100 : 0
                ],
                'monthly' => [
                    'this_month' => $this_month,
                    'last_month' => $last_month_sales,
                    'change' => $this_month['total_sales'] - $last_month_sales['total_sales'],
                    'change_percent' => $last_month_sales['total_sales'] > 0 ? 
                        (($this_month['total_sales'] - $last_month_sales['total_sales']) / $last_month_sales['total_sales']) * 100 : 0
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in getSalesComparison: " . $e->getMessage());
            return [
                'daily' => ['today' => ['total_sales' => 0], 'yesterday' => ['total_sales' => 0], 'change' => 0, 'change_percent' => 0],
                'weekly' => ['this_week' => ['total_sales' => 0], 'last_week' => ['total_sales' => 0], 'change' => 0, 'change_percent' => 0],
                'monthly' => ['this_month' => ['total_sales' => 0], 'last_month' => ['total_sales' => 0], 'change' => 0, 'change_percent' => 0]
            ];
        }
    }
    
    /**
     * Get payment method breakdown for a date range
     */
    public function getPaymentMethodBreakdown($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        p.payment_method,
                        COUNT(p.payment_id) as transaction_count,
                        SUM(p.amount) as total_amount
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY p.payment_method
                    ORDER BY total_amount DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getPaymentMethodBreakdown: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get daily sales trend for a date range
     */
    public function getDailySalesTrend($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        DATE(p.payment_date) as sale_date,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as transaction_count,
                        AVG(p.amount) as average_sale
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY DATE(p.payment_date)
                    ORDER BY sale_date";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getDailySalesTrend: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get peak hours analysis for a date range
     */
    public function getPeakHoursAnalysis($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        HOUR(p.payment_date) as hour,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as transaction_count,
                        AVG(p.amount) as average_sale
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'
                    GROUP BY HOUR(p.payment_date)
                    ORDER BY total_sales DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getPeakHoursAnalysis: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get customer analytics for a date range
     */
    public function getCustomerAnalytics($start_date, $end_date) {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT o.table_id) as unique_tables,
                        COUNT(p.payment_id) as total_transactions,
                        AVG(p.amount) as average_transaction_value,
                        SUM(p.amount) as total_revenue
                    FROM payments p
                    JOIN orders o ON p.order_id = o.id
                    WHERE p.payment_date BETWEEN ? AND ?
                    AND p.payment_status = 'completed'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getCustomerAnalytics: " . $e->getMessage());
            return [
                'unique_tables' => 0,
                'total_transactions' => 0,
                'average_transaction_value' => 0,
                'total_revenue' => 0
            ];
        }
    }
}
?>
