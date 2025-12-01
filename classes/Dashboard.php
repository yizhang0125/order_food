<?php
class Dashboard {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getDashboardData() {
        try {
            // Get all stats in a single query
            $statsQuery = "SELECT 
                (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as total_orders,
                (SELECT COUNT(*) FROM orders WHERE status = 'completed' AND DATE(created_at) = CURDATE()) as completed_orders,
                (SELECT COUNT(*) FROM orders WHERE status = 'processing' AND DATE(created_at) = CURDATE()) as processing_orders,
                (SELECT COUNT(*) FROM orders WHERE status = 'pending' AND DATE(created_at) = CURDATE()) as pending_orders,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND payment_status = 'completed') as total_daily_sales,
                (SELECT COUNT(*) FROM tables) as total_tables,
                (SELECT COUNT(*) FROM menu_items WHERE status = 'available') as total_items";

            $stmt = $this->db->prepare($statsQuery);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get recent orders with item count in one query
            $ordersQuery = "SELECT 
                o.id, 
                o.total_amount, 
                o.status, 
                o.created_at,
                t.table_number as table_name,
                COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                GROUP BY o.id, o.total_amount, o.status, o.created_at, t.table_number
                ORDER BY o.created_at DESC
                LIMIT 5";

            $stmt = $this->db->prepare($ordersQuery);
            $stmt->execute();
            $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Return all data in one array
            return [
                'stats' => $stats,
                'recentOrders' => $recentOrders
            ];
        } catch (PDOException $e) {
            throw new Exception("Error fetching dashboard data: " . $e->getMessage());
        }
    }

    public function getOrderStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                        SUM(total_amount) as total_revenue
                     FROM orders 
                     WHERE DATE(created_at) = CURDATE()";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching order stats: " . $e->getMessage());
        }
    }

    public function getTotalTableCount() {
        try {
            $query = "SELECT COUNT(*) as total_tables FROM tables";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total_tables'];
        } catch (PDOException $e) {
            throw new Exception("Error fetching total tables: " . $e->getMessage());
        }
    }

    public function getMenuItemCount() {
        try {
            $query = "SELECT COUNT(*) as total_items FROM menu_items WHERE status = 'available'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total_items'];
        } catch (PDOException $e) {
            throw new Exception("Error fetching menu items count: " . $e->getMessage());
        }
    }

    public function getRecentOrders($limit = 5) {
        try {
            $query = "SELECT o.id, o.total_amount, o.status, o.created_at,
                     t.table_number as table_name,
                     COUNT(oi.id) as item_count
              FROM orders o
              LEFT JOIN tables t ON o.table_id = t.id
              LEFT JOIN order_items oi ON o.id = oi.order_id
              GROUP BY o.id, o.total_amount, o.status, o.created_at, t.table_number
              ORDER BY o.created_at DESC
              LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching recent orders: " . $e->getMessage());
        }
    }

    public function getRecentActivities($limit = 3) {
        try {
            $query = "SELECT 
                        o.id,
                        o.created_at,
                        o.status,
                        t.table_number,
                        CASE 
                            WHEN o.status = 'completed' THEN 'check-circle'
                            WHEN o.status = 'processing' THEN 'clock'
                            WHEN o.status = 'pending' THEN 'hourglass'
                            ELSE 'info-circle'
                        END as icon,
                        CONCAT('Order #', o.id, ' - Table ', t.table_number, ' - ', 
                            CASE 
                                WHEN o.status = 'completed' THEN 'completed'
                                WHEN o.status = 'processing' THEN 'is being prepared'
                                WHEN o.status = 'pending' THEN 'is pending'
                                ELSE 'status updated'
                            END
                        ) as description
                    FROM orders o
                    LEFT JOIN tables t ON o.table_id = t.id
                    ORDER BY o.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching recent activities: " . $e->getMessage());
        }
    }
} 