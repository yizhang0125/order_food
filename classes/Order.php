<?php
require_once 'Model.php';

class Order extends Model {
    protected $table_name = "orders";
    protected $conn;
    
    public function __construct($db) {
        parent::__construct($db);
        $this->conn = $db;
    }

    public function createOrder($tableId) {
        $query = "INSERT INTO " . $this->table_name . " 
                (table_id, status, total_amount) 
                VALUES (:table_id, 'pending', 0)";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":table_id", $tableId);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function addOrderItems($orderId, $items) {
        $total_amount = 0;
        
        foreach ($items as $itemId => $quantity) {
            if ($quantity > 0) {
                // Get item price
                $query = "SELECT price FROM menu_items WHERE id = :id AND status = 'available'";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $itemId);
                $stmt->execute();
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $item_total = $item['price'] * $quantity;
                    $total_amount += $item_total;
                    
                    // Insert order item
                    $query = "INSERT INTO order_items (order_id, menu_item_id, quantity, price) 
                             VALUES (:order_id, :item_id, :quantity, :price)";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':order_id', $orderId);
                    $stmt->bindParam(':item_id', $itemId);
                    $stmt->bindParam(':quantity', $quantity);
                    $stmt->bindParam(':price', $item['price']);
                    $stmt->execute();
                }
            }
        }
        
        // Update order total
        $this->updateTotal($orderId, $total_amount);
        return $total_amount;
    }
    
    public function updateTotal($orderId, $total) {
        $query = "UPDATE " . $this->table_name . " 
                SET total_amount = :total 
                WHERE id = :id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':id', $orderId);
        return $stmt->execute();
    }
    
    public function updateStatus($orderId, $status) {
        $query = "UPDATE " . $this->table_name . " 
                SET status = :status 
                WHERE id = :id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $orderId);
        return $stmt->execute();
    }
    
    public function cancelOrderItem($orderItemId) {
        try {
            // First, get the order item details to calculate the refund amount
            $query = "SELECT oi.*, o.id as order_id, o.total_amount 
                     FROM order_items oi 
                     JOIN orders o ON oi.order_id = o.id 
                     WHERE oi.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$orderItemId]);
            $orderItem = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$orderItem) {
                return false;
            }
            
            // Calculate the refund amount
            $refundAmount = $orderItem['price'] * $orderItem['quantity'];
            $newTotal = $orderItem['total_amount'] - $refundAmount;
            
            // Delete the order item
            $deleteQuery = "DELETE FROM order_items WHERE id = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteResult = $deleteStmt->execute([$orderItemId]);
            
            if (!$deleteResult) {
                return false;
            }
            
            // Update the order total
            $updateQuery = "UPDATE orders SET total_amount = ? WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateResult = $updateStmt->execute([$newTotal, $orderItem['order_id']]);
            
            // Check if there are any remaining items in the order
            $checkQuery = "SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$orderItem['order_id']]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // If no items left, cancel the entire order
            if ($result['item_count'] == 0) {
                $this->updateStatus($orderItem['order_id'], 'cancelled');
            }
            
            return $updateResult;
            
        } catch (Exception $e) {
            error_log("Error cancelling order item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test method to check basic database connectivity
     */
    public function testConnection() {
        try {
            $query = "SELECT COUNT(*) as count FROM orders";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Database connection test successful. Total orders: " . $result['count']);
            return true;
        } catch (Exception $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Simple method to get recent orders without complex processing
     */
    public function getSimpleRecentOrders($limit = 100) {
        try {
            $query = "SELECT o.*, t.table_number 
                     FROM orders o
                     LEFT JOIN tables t ON o.table_id = t.id
                     ORDER BY o.created_at DESC
                     LIMIT ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$limit]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add basic item information
            foreach ($orders as &$order) {
                $order['items'] = [];
                $order['order_items'] = [];
                $order['special_instructions'] = [];
                $order['items_list'] = '';
                $order['item_count'] = 0;
                
                // Get items for this order
                $items_query = "SELECT oi.*, mi.name 
                               FROM order_items oi 
                               JOIN menu_items mi ON oi.menu_item_id = mi.id 
                               WHERE oi.order_id = ?";
                $items_stmt = $this->conn->prepare($items_query);
                $items_stmt->execute([$order['id']]);
                $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $order['item_count'] = count($items);
                foreach ($items as $item) {
                    $order['items'][] = $item['name'] . ' (' . $item['quantity'] . ')';
                    $order['order_items'][] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ];
                    if (!empty($item['special_instructions'])) {
                        $order['special_instructions'][] = [
                            'item' => $item['name'],
                            'instructions' => $item['special_instructions']
                        ];
                    }
                }
                $order['items_list'] = implode(', ', $order['items']);
            }
            
            error_log("getSimpleRecentOrders returned " . count($orders) . " orders");
            return $orders;
        } catch (Exception $e) {
            error_log("Error in getSimpleRecentOrders: " . $e->getMessage());
            throw new Exception("Error retrieving recent orders: " . $e->getMessage());
        }
    }

    public function getRecentOrders($start_date = null, $end_date = null, $limit = 10) {
        try {
            // First, let's try a simpler query to test the basic functionality
            $query = "SELECT o.*, t.table_number,
                     COUNT(oi.id) as item_count
                     FROM orders o
                     LEFT JOIN tables t ON o.table_id = t.id
                     LEFT JOIN order_items oi ON o.id = oi.order_id
                     WHERE 1=1";

            $params = [];

            if ($start_date) {
                $query .= " AND DATE(o.created_at) >= ?";
                $params[] = $start_date;
            }

            if ($end_date) {
                $query .= " AND DATE(o.created_at) <= ?";
                $params[] = $end_date;
            }

            $query .= " GROUP BY o.id, o.table_id, o.status, o.total_amount, o.created_at, t.table_number
                       ORDER BY o.created_at DESC
                       LIMIT ?";
            $params[] = $limit;

            error_log("getRecentOrders query: " . $query);
            error_log("getRecentOrders params: " . print_r($params, true));

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            error_log("getRecentOrders query executed successfully");
            $orders = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Initialize arrays
                $row['items'] = [];
                $row['order_items'] = [];
                $row['special_instructions'] = [];
                $row['items_list'] = '';
                
                // Get order items separately to avoid complex JSON issues
                $items_query = "SELECT oi.*, mi.name 
                               FROM order_items oi 
                               JOIN menu_items mi ON oi.menu_item_id = mi.id 
                               WHERE oi.order_id = ?";
                $items_stmt = $this->conn->prepare($items_query);
                $items_stmt->execute([$row['id']]);
                $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($items as $item) {
                    $row['items'][] = $item['name'] . ' (' . $item['quantity'] . ')';
                    $row['order_items'][] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ];
                    if (!empty($item['special_instructions'])) {
                        $row['special_instructions'][] = [
                            'item' => $item['name'],
                            'instructions' => $item['special_instructions']
                        ];
                    }
                }
                
                $row['items_list'] = implode(', ', $row['items']);
                $orders[] = $row;
            }

            error_log("getRecentOrders processed " . count($orders) . " orders");
            return $orders;
        } catch (PDOException $e) {
            error_log("PDO Error in getRecentOrders: " . $e->getMessage());
            error_log("PDO Error Code: " . $e->getCode());
            throw new Exception("Error retrieving recent orders: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("General Error in getRecentOrders: " . $e->getMessage());
            throw new Exception("Error retrieving recent orders: " . $e->getMessage());
        }
    }
    
    public function getOrderDetails($orderId) {
        $query = "SELECT oi.*, mi.name, mi.category 
                FROM order_items oi 
                JOIN menu_items mi ON oi.menu_item_id = mi.id 
                WHERE oi.order_id = :order_id";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalOrders($start_date = null, $end_date = null) {
        $sql = "SELECT COUNT(*) as total FROM orders WHERE 1=1";
        
        if ($start_date && $end_date) {
            $sql .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if ($start_date && $end_date) {
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }

    public function getOrders($page = 1, $per_page = 10, $search = '') {
        try {
            $offset = ($page - 1) * $per_page;
            
            $query = "SELECT o.*, t.table_number, 
                      COUNT(oi.id) as item_count,
                      GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', oi.id,
                            'name', mi.name,
                            'quantity', oi.quantity,
                            'price', oi.price,
                            'instructions', COALESCE(oi.special_instructions, '')
                        )
                      ) as items_data
                      FROM " . $this->table_name . " o
                      LEFT JOIN tables t ON o.table_id = t.id
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
                      WHERE o.status IN ('pending', 'processing')";
            
            if (!empty($search)) {
                $query .= " AND (o.id LIKE :search 
                           OR t.table_number LIKE :search)";
            }
            
            $query .= " GROUP BY o.id, o.table_id, o.status, o.total_amount, o.created_at, t.table_number
                        ORDER BY o.created_at DESC
                        LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            
            if (!empty($search)) {
                $searchTerm = "%{$search}%";
                $stmt->bindParam(':search', $searchTerm);
            }
            
            $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process the JSON data for each order
            foreach ($orders as &$order) {
                $order['items'] = [];
                $order['order_items'] = [];
                $order['special_instructions'] = [];
                $order['items_list'] = '';
                
                if (!empty($order['items_data'])) {
                    // Handle the GROUP_CONCAT JSON data properly
                    $items_data = $order['items_data'];
                    
                    // If it's a single JSON object, wrap it in an array
                    if (substr($items_data, 0, 1) === '{') {
                        $items_data = '[' . $items_data . ']';
                    }
                    
                    // Parse the JSON array
                    $items = json_decode($items_data, true);
                    
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (is_array($item) && isset($item['name'])) {
                                $order['items'][] = $item['name'] . ' (' . $item['quantity'] . ')';
                                $order['order_items'][] = [
                                    'id' => $item['id'],
                                    'name' => $item['name'],
                                    'quantity' => $item['quantity'],
                                    'price' => $item['price']
                                ];
                                if (!empty($item['instructions'])) {
                                    $order['special_instructions'][] = [
                                        'item' => $item['name'],
                                        'instructions' => $item['instructions']
                                    ];
                                }
                            }
                        }
                    }
                }
                
                $order['items_list'] = implode(', ', $order['items']);
            }
            
            return $orders;
        } catch (PDOException $e) {
            error_log("Error in getOrders: " . $e->getMessage());
            return [];
        }
    }

    public function getOrdersByStatus($status) {
        try {
            $query = "SELECT o.*, t.table_number, 
                      COUNT(oi.id) as item_count,
                      GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', oi.id,
                            'name', mi.name,
                            'quantity', oi.quantity,
                            'price', oi.price,
                            'instructions', COALESCE(oi.special_instructions, '')
                        )
                      ) as items_data
                      FROM " . $this->table_name . " o
                      LEFT JOIN tables t ON o.table_id = t.id
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
                      WHERE o.status = :status
                      GROUP BY o.id, o.table_id, o.status, o.total_amount, o.created_at, t.table_number
                      ORDER BY o.created_at ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process the JSON data for each order
            foreach ($orders as &$order) {
                $items_data = explode('},{', trim($order['items_data'], '[]'));
                $order['items'] = [];
                $order['order_items'] = [];
                $order['special_instructions'] = [];
                
                foreach ($items_data as $item_json) {
                    if (!empty($item_json)) {
                        // Fix JSON format if needed
                        if (substr($item_json, -1) !== '}') $item_json .= '}';
                        if (substr($item_json, 0, 1) !== '{') $item_json = '{' . $item_json;
                        
                        $item = json_decode($item_json, true);
                        if ($item) {
                            $order['items'][] = $item['name'] . ' (' . $item['quantity'] . ')';
                            $order['order_items'][] = [
                                'id' => $item['id'],
                                'name' => $item['name'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price']
                            ];
                            if (!empty($item['instructions'])) {
                                $order['special_instructions'][] = [
                                    'item' => $item['name'],
                                    'instructions' => $item['instructions']
                                ];
                            }
                        }
                    }
                }
                $order['items'] = implode(', ', $order['items']);
            }
            
            return $orders;
        } catch (PDOException $e) {
            throw new Exception("Error fetching orders by status: " . $e->getMessage());
        }
    }

    public function getOrder($order_id) {
        try {
            // Get order details
            $query = "SELECT o.*, t.table_number 
                     FROM orders o 
                     JOIN tables t ON o.table_id = t.id 
                     WHERE o.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return null;
            }
            
            // Get order items
            $query = "SELECT oi.*, mi.name, mi.image_path 
                     FROM order_items oi 
                     JOIN menu_items mi ON oi.menu_item_id = mi.id 
                     WHERE oi.order_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$order_id]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $order;
            
        } catch (Exception $e) {
            error_log("Error getting order: " . $e->getMessage());
            return null;
        }
    }

    public function getOrdersByTable($table_number) {
        try {
            $query = "SELECT o.id, o.status, o.total_amount, o.created_at,
                      GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', oi.menu_item_id,
                            'name', mi.name,
                            'quantity', oi.quantity,
                            'price', oi.price,
                            'instructions', oi.special_instructions
                        )
                      ) as items
                      FROM orders o
                      JOIN tables t ON o.table_id = t.id
                      JOIN order_items oi ON o.id = oi.order_id
                      JOIN menu_items mi ON oi.menu_item_id = mi.id
                      WHERE t.table_number = ?
                      GROUP BY o.id
                      ORDER BY o.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$table_number]);
            
            $orders = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Convert the GROUP_CONCAT result into a proper JSON array
                $items_string = '[' . str_replace('}{', '},{', $row['items']) . ']';
                $row['items'] = $items_string;
                $orders[] = $row;
            }
            
            return $orders;
        } catch (PDOException $e) {
            error_log("Error in getOrdersByTable: " . $e->getMessage());
            throw new Exception("Error retrieving orders");
        }
    }

    public function getTotalRevenue($start_date = null, $end_date = null) {
        try {
            $query = "SELECT SUM(total_amount) as total_revenue FROM " . $this->table_name . " WHERE status = 'completed'";
            
            if ($start_date && $end_date) {
                $query .= " AND created_at BETWEEN :start_date AND :end_date";
            }
            
            $stmt = $this->conn->prepare($query);
            
            if ($start_date && $end_date) {
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['total_revenue'] ?? 0;
        } catch (PDOException $e) {
            throw new Exception("Error calculating total revenue: " . $e->getMessage());
        }
    }

    public function getAverageOrderValue($start_date = null, $end_date = null) {
        try {
            $query = "SELECT AVG(total_amount) as average_order_value 
                     FROM " . $this->table_name . " 
                     WHERE status = 'completed' AND total_amount > 0";
            
            if ($start_date && $end_date) {
                $query .= " AND created_at BETWEEN :start_date AND :end_date";
            }
            
            $stmt = $this->conn->prepare($query);
            
            if ($start_date && $end_date) {
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return round($result['average_order_value'] ?? 0, 2);
        } catch (PDOException $e) {
            throw new Exception("Error calculating average order value: " . $e->getMessage());
        }
    }

    public function getPopularItems($start_date = null, $end_date = null, $limit = 5) {
        try {
            $query = "SELECT mi.id, mi.name, SUM(oi.quantity) as quantity 
                     FROM order_items oi 
                     JOIN menu_items mi ON oi.menu_item_id = mi.id 
                     JOIN " . $this->table_name . " o ON oi.order_id = o.id 
                     WHERE o.status != 'cancelled'";
            
            if ($start_date && $end_date) {
                $query .= " AND o.created_at BETWEEN :start_date AND :end_date";
            }
            
            $query .= " GROUP BY mi.id, mi.name 
                       ORDER BY quantity DESC 
                       LIMIT :limit";
            
            $stmt = $this->conn->prepare($query);
            
            if ($start_date && $end_date) {
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error getting popular items: " . $e->getMessage());
        }
    }

    public function getDailyRevenue($start_date = null, $end_date = null) {
        try {
            $query = "SELECT DATE(created_at) as date, 
                     SUM(total_amount) as daily_revenue
                     FROM " . $this->table_name . " 
                     WHERE status = 'completed'";
            
            if ($start_date && $end_date) {
                $query .= " AND created_at BETWEEN :start_date AND :end_date";
            }
            
            $query .= " GROUP BY DATE(created_at)
                       ORDER BY date ASC";
            
            $stmt = $this->conn->prepare($query);
            
            if ($start_date && $end_date) {
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert to associative array with date as key
            $daily_revenue = array();
            foreach ($results as $row) {
                $daily_revenue[$row['date']] = floatval($row['daily_revenue']);
            }
            
            // Fill in missing dates with zero revenue
            if ($start_date && $end_date) {
                $current = new DateTime($start_date);
                $end = new DateTime($end_date);
                $end->modify('+1 day'); // Include end date
                
                while ($current < $end) {
                    $date = $current->format('Y-m-d');
                    if (!isset($daily_revenue[$date])) {
                        $daily_revenue[$date] = 0;
                    }
                    $current->modify('+1 day');
                }
                ksort($daily_revenue); // Sort by date
            }
            
            return $daily_revenue;
        } catch (PDOException $e) {
            throw new Exception("Error getting daily revenue: " . $e->getMessage());
        }
    }

    public function getOrderStatusCounts($start_date = null, $end_date = null) {
        try {
            $query = "SELECT status, COUNT(*) as count 
                     FROM " . $this->table_name;
            
            if ($start_date && $end_date) {
                $query .= " WHERE created_at BETWEEN :start_date AND :end_date";
            }
            
            $query .= " GROUP BY status";
            
            $stmt = $this->conn->prepare($query);
            
            if ($start_date && $end_date) {
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize all possible statuses with 0
            $status_counts = array(
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'cancelled' => 0
            );
            
            // Update counts from database results
            foreach ($results as $row) {
                $status_counts[$row['status']] = intval($row['count']);
            }
            
            return $status_counts;
        } catch (PDOException $e) {
            throw new Exception("Error getting order status counts: " . $e->getMessage());
        }
    }

    public function getCompletedOrders($start_date, $end_date) {
        try {
            $query = "SELECT 
                        o.id, 
                        o.table_id, 
                        o.status, 
                        o.total_amount, 
                        o.created_at,
                        t.table_number,
                        COUNT(oi.id) as item_count,
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', oi.id,
                                'name', mi.name,
                                'quantity', oi.quantity,
                                'price', oi.price,
                                'instructions', COALESCE(oi.special_instructions, '')
                            )
                        ) as items_data
                      FROM " . $this->table_name . " o 
                      LEFT JOIN tables t ON o.table_id = t.id 
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
                      WHERE o.status = 'completed' 
                      AND DATE(o.created_at) BETWEEN :start_date AND :end_date 
                      GROUP BY o.id, o.table_id, o.status, o.total_amount, o.created_at, t.table_number
                      ORDER BY o.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process the JSON data for each order
            foreach ($orders as &$order) {
                $items_data = explode('},{', trim($order['items_data'], '[]'));
                $order['items'] = [];
                $order['order_items'] = [];
                $order['special_instructions'] = [];
                
                foreach ($items_data as $item_json) {
                    if (!empty($item_json)) {
                        // Fix JSON format if needed
                        if (substr($item_json, -1) !== '}') $item_json .= '}';
                        if (substr($item_json, 0, 1) !== '{') $item_json = '{' . $item_json;
                        
                        $item = json_decode($item_json, true);
                        if ($item) {
                            $order['items'][] = $item['name'] . ' (' . $item['quantity'] . ')';
                            $order['order_items'][] = [
                                'id' => $item['id'],
                                'name' => $item['name'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price']
                            ];
                            if (!empty($item['instructions'])) {
                                $order['special_instructions'][] = [
                                    'item' => $item['name'],
                                    'instructions' => $item['instructions']
                                ];
                            }
                        }
                    }
                }
                $order['items_list'] = implode(', ', $order['items']);
            }
            
            return $orders;
        } catch (PDOException $e) {
            error_log("Error fetching completed orders: " . $e->getMessage());
            return [];
        }
    }

    public function getCancelledOrders($start_date, $end_date) {
        try {
            // First, get the basic order data (without cancelled_at and cancel_reason columns)
            $query = "SELECT 
                        o.id, 
                        o.table_id, 
                        o.status, 
                        o.total_amount, 
                        o.created_at,
                        t.table_number, 
                        COUNT(oi.id) as item_count
                       FROM orders o
                       LEFT JOIN tables t ON o.table_id = t.id
                       LEFT JOIN order_items oi ON o.id = oi.order_id
                       WHERE o.status = 'cancelled' 
                       AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                       GROUP BY o.id, o.table_id, o.status, o.total_amount, o.created_at, t.table_number
                       ORDER BY o.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Now get the items for each order separately
            foreach ($orders as &$order) {
                // Initialize arrays
                $order['items'] = [];
                $order['order_items'] = [];
                $order['special_instructions'] = [];
                $order['items_list'] = '';
                
                // Add missing columns with default values
                $order['cancelled_at'] = $order['created_at']; // Use created_at as cancelled_at for now
                $order['cancel_reason'] = 'No reason provided'; // Default cancel reason
                
                // Get order items separately
                $items_query = "SELECT oi.*, mi.name 
                               FROM order_items oi 
                               JOIN menu_items mi ON oi.menu_item_id = mi.id 
                               WHERE oi.order_id = ?";
                $items_stmt = $this->conn->prepare($items_query);
                $items_stmt->execute([$order['id']]);
                $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($items as $item) {
                    $order['items'][] = $item['name'] . ' (' . $item['quantity'] . ')';
                    $order['order_items'][] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ];
                    if (!empty($item['special_instructions'])) {
                        $order['special_instructions'][] = [
                            'item' => $item['name'],
                            'instructions' => $item['special_instructions']
                        ];
                    }
                }
                $order['items_list'] = implode(', ', $order['items']);
            }
            
            return $orders;
        } catch (PDOException $e) {
            error_log("PDO Error in getCancelledOrders: " . $e->getMessage());
            throw new Exception("Error retrieving cancelled orders: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("General Error in getCancelledOrders: " . $e->getMessage());
            throw new Exception("Error retrieving cancelled orders: " . $e->getMessage());
        }
    }

    /**
     * Get total sales for a date range
     */
    public function getTotalSales($start_date, $end_date) {
        try {
            $query = "SELECT COALESCE(SUM(total_amount), 0) as total_sales 
                     FROM orders 
                     WHERE DATE(created_at) BETWEEN :start_date AND :end_date 
                     AND status != 'cancelled'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total_sales'];
        } catch (PDOException $e) {
            error_log("Error in getTotalSales: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total number of cancelled orders for a date range
     */
    public function getTotalCancelledOrders($start_date, $end_date) {
        try {
            $query = "SELECT COUNT(*) as cancelled_orders 
                     FROM orders 
                     WHERE DATE(created_at) BETWEEN :start_date AND :end_date 
                     AND status = 'cancelled'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['cancelled_orders'];
        } catch (PDOException $e) {
            error_log("Error in getTotalCancelledOrders: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get top selling items for a date range
     */
    public function getTopSellingItems($start_date, $end_date, $limit = 5) {
        try {
            $query = "SELECT mi.name, COUNT(*) as total_ordered, SUM(oi.quantity) as quantity_sold
                     FROM order_items oi
                     JOIN menu_items mi ON oi.menu_item_id = mi.id
                     JOIN orders o ON oi.order_id = o.id
                     WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
                     AND o.status != 'cancelled'
                     GROUP BY mi.id, mi.name
                     ORDER BY quantity_sold DESC
                     LIMIT :limit";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getTopSellingItems: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sales trend data for a date range
     */
    public function getSalesTrend($start_date, $end_date) {
        try {
            $query = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as order_count,
                        SUM(total_amount) as daily_sales
                     FROM " . $this->table_name . "
                     WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                     AND status = 'completed'
                     GROUP BY DATE(created_at)
                     ORDER BY date ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fill in missing dates with zero values
            $trend_data = [];
            $current_date = new DateTime($start_date);
            $end = new DateTime($end_date);
            $end->modify('+1 day');
            
            while ($current_date < $end) {
                $date_str = $current_date->format('Y-m-d');
                $trend_data[$date_str] = [
                    'date' => $date_str,
                    'order_count' => 0,
                    'daily_sales' => 0
                ];
                $current_date->modify('+1 day');
            }
            
            // Merge actual data
            foreach ($results as $row) {
                $trend_data[$row['date']] = [
                    'date' => $row['date'],
                    'order_count' => (int)$row['order_count'],
                    'daily_sales' => (float)$row['daily_sales']
                ];
            }
            
            // Calculate additional metrics
            $total_sales = 0;
            $total_orders = 0;
            $daily_averages = [];
            
            foreach ($trend_data as $data) {
                $total_sales += $data['daily_sales'];
                $total_orders += $data['order_count'];
                if ($data['order_count'] > 0) {
                    $daily_averages[] = $data['daily_sales'] / $data['order_count'];
                }
            }
            
            $avg_order_value = count($daily_averages) > 0 ? array_sum($daily_averages) / count($daily_averages) : 0;
            
            return [
                'daily_data' => array_values($trend_data),
                'summary' => [
                    'total_sales' => $total_sales,
                    'total_orders' => $total_orders,
                    'average_order_value' => $avg_order_value
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Error in getSalesTrend: " . $e->getMessage());
            throw new Exception("Error calculating sales trend");
        }
    }

    /**
     * Get orders by table number and specific statuses
     * 
     * @param int $table_number The table number
     * @param array $statuses Array of order statuses to include
     * @return array Orders for the specified table with the specified statuses
     */
    public function getOrdersByTableAndStatus($table_number, $statuses = ['pending', 'processing']) {
        try {
            $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
            $sql = "SELECT o.*, 
                   (SELECT JSON_ARRAYAGG(
                       JSON_OBJECT(
                           'id', oi.id,
                           'name', m.name,
                           'price', m.price,
                           'quantity', oi.quantity,
                           'instructions', oi.special_instructions
                       )
                   ) 
                   FROM order_items oi 
                   JOIN menu_items m ON oi.menu_item_id = m.id 
                   WHERE oi.order_id = o.id) as items
                   FROM orders o
                   JOIN tables t ON o.table_id = t.id
                   WHERE t.table_number = ?
                   AND o.status IN ($placeholders)
                   ORDER BY o.created_at DESC";
            
            $params = array_merge([$table_number], $statuses);
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getOrdersByTableAndStatus: " . $e->getMessage());
            return [];
        }
    }
}
?> 