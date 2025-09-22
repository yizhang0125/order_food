<?php
require_once(__DIR__ . '/../../config/Database.php');

class ReportController {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Get daily sales summary
     */
    public function getDailySalesSummary($date) {
        $start_datetime = $date . ' 00:00:00';
        $end_datetime = $date . ' 23:59:59';
        
        $sql = "SELECT 
                    COUNT(p.payment_id) as total_transactions,
                    SUM(p.amount) as total_sales,
                    AVG(p.amount) as average_sale,
                    COUNT(DISTINCT o.table_id) as tables_served,
                    MAX(p.amount) as highest_sale,
                    MIN(p.amount) as lowest_sale,
                    COUNT(CASE WHEN p.payment_method = 'cash' THEN 1 END) as cash_transactions,
                    COUNT(CASE WHEN p.payment_method = 'card' THEN 1 END) as card_transactions,
                    COUNT(CASE WHEN p.payment_method = 'tng' THEN 1 END) as tng_transactions,
                    SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN p.payment_method = 'card' THEN p.amount ELSE 0 END) as card_total,
                    SUM(CASE WHEN p.payment_method = 'tng' THEN p.amount ELSE 0 END) as tng_total
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE p.payment_date BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get hourly sales breakdown
     */
    public function getHourlySales($date) {
        $start_datetime = $date . ' 00:00:00';
        $end_datetime = $date . ' 23:59:59';
        
        $sql = "SELECT 
                    HOUR(p.payment_date) as hour,
                    COUNT(p.payment_id) as transactions,
                    SUM(p.amount) as sales_amount
                FROM payments p
                WHERE p.payment_date BETWEEN ? AND ?
                GROUP BY HOUR(p.payment_date)
                ORDER BY hour";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top selling items for a specific date
     */
    public function getTopSellingItems($date, $limit = 20) {
        $start_datetime = $date . ' 00:00:00';
        $end_datetime = $date . ' 23:59:59';
        
        // Ensure limit is a positive integer
        $limit = max(1, intval($limit));
        
        $sql = "SELECT 
                    m.name as item_name,
                    SUM(oi.quantity) as quantity_sold,
                    SUM(oi.quantity * oi.price) as total_sales,
                    m.price as unit_price
                FROM order_items oi
                JOIN menu_items m ON oi.menu_item_id = m.id
                JOIN orders o ON oi.order_id = o.id
                JOIN payments p ON o.id = p.order_id
                WHERE p.payment_date BETWEEN ? AND ?
                GROUP BY m.id
                ORDER BY quantity_sold DESC
                LIMIT " . $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get sales by table for a specific date
     */
    public function getSalesByTable($date) {
        $start_datetime = $date . ' 00:00:00';
        $end_datetime = $date . ' 23:59:59';
        
        $sql = "SELECT 
                    t.table_number,
                    COUNT(p.payment_id) as transaction_count,
                    SUM(p.amount) as total_sales,
                    MIN(p.payment_date) as first_order,
                    MAX(p.payment_date) as last_order
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                JOIN tables t ON o.table_id = t.id
                WHERE p.payment_date BETWEEN ? AND ?
                GROUP BY t.table_number
                ORDER BY total_sales DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get order status breakdown for a specific date
     */
    public function getOrderStatusBreakdown($date) {
        $sql = "SELECT 
                    o.status,
                    COUNT(*) as count,
                    SUM(o.total_amount) as total_amount
                FROM orders o
                WHERE DATE(o.created_at) = ?
                GROUP BY o.status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get sales summary for a date range
     */
    public function getSalesSummary($start_date, $end_date) {
        $start_datetime = $start_date . ' 00:00:00';
        $end_datetime = $end_date . ' 23:59:59';
        
        $sql = "SELECT 
                    COUNT(p.payment_id) as total_transactions,
                    SUM(p.amount) as total_sales,
                    AVG(p.amount) as average_sale,
                    COUNT(DISTINCT o.table_id) as tables_served,
                    MAX(p.amount) as highest_sale,
                    MIN(p.amount) as lowest_sale
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE p.payment_date BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get sales data for chart (daily, weekly, monthly)
     */
    public function getSalesData($start_date, $end_date, $view_mode = 'daily') {
        $start_datetime = $start_date . ' 00:00:00';
        $end_datetime = $end_date . ' 23:59:59';
        
        if ($view_mode == 'weekly') {
            $sql = "SELECT 
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
            $sql = "SELECT 
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
            // Daily (default)
            $sql = "SELECT 
                        DATE(p.payment_date) as sale_date,
                        SUM(p.amount) as total_sales,
                        COUNT(p.payment_id) as transaction_count
                    FROM payments p
                    WHERE p.payment_date BETWEEN ? AND ?
                    GROUP BY DATE(p.payment_date)
                    ORDER BY sale_date";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top selling items for a date range
     */
    public function getTopItems($start_date, $end_date, $limit = 10) {
        $start_datetime = $start_date . ' 00:00:00';
        $end_datetime = $end_date . ' 23:59:59';
        
        // Ensure limit is a positive integer
        $limit = max(1, intval($limit));
        
        $sql = "SELECT 
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
                LIMIT " . $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get table sales for a date range
     */
    public function getTableSales($start_date, $end_date) {
        $start_datetime = $start_date . ' 00:00:00';
        $end_datetime = $end_date . ' 23:59:59';
        
        $sql = "SELECT 
                    t.table_number,
                    COUNT(p.payment_id) as transaction_count,
                    SUM(p.amount) as total_sales
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                JOIN tables t ON o.table_id = t.id
                WHERE p.payment_date BETWEEN ? AND ?
                GROUP BY t.table_number
                ORDER BY total_sales DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$start_datetime, $end_datetime]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate daily sales report data
     */
    public function generateDailyReport($date) {
        try {
            $report_data = [
                'date' => $date,
                'summary' => $this->getDailySalesSummary($date),
                'hourly_sales' => $this->getHourlySales($date),
                'top_items' => $this->getTopSellingItems($date),
                'table_sales' => $this->getSalesByTable($date),
                'order_status' => $this->getOrderStatusBreakdown($date)
            ];
            
            return $report_data;
        } catch (Exception $e) {
            throw new Exception("Error generating daily report: " . $e->getMessage());
        }
    }
    
    /**
     * Format currency for display
     */
    public function formatCurrency($amount) {
        return 'RM ' . number_format($amount, 2);
    }
    
    /**
     * Format number for display
     */
    public function formatNumber($number) {
        return number_format($number);
    }
    
    /**
     * Get status badge class
     */
    public function getStatusBadgeClass($status) {
        switch($status) {
            case 'completed': return 'bg-success';
            case 'pending': return 'bg-warning';
            case 'processing': return 'bg-info';
            case 'cancelled': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }
    
    /**
     * Calculate percentage
     */
    public function calculatePercentage($part, $total) {
        return $total > 0 ? ($part / $total) * 100 : 0;
    }
}
?>
