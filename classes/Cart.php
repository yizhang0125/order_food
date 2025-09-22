<?php
require_once(__DIR__ . '/SystemSettings.php');

class Cart {
    private $items = [];
    private $systemSettings;
    
    public function __construct() {
        if (isset($_SESSION['cart'])) {
            $this->items = $_SESSION['cart'];
        }
        
        // Initialize system settings for tax calculations
        try {
            require_once(__DIR__ . '/../config/Database.php');
            $database = new Database();
            $db = $database->getConnection();
            $this->systemSettings = new SystemSettings($db);
        } catch (Exception $e) {
            // Fallback if database connection fails
            $this->systemSettings = null;
        }
    }

    public function addItem($item_id, $quantity = 1) {
        if (isset($this->items[$item_id])) {
            $this->items[$item_id] += $quantity;
        } else {
            $this->items[$item_id] = $quantity;
        }
        $this->saveCart();
    }

    public function removeItem($item_id) {
        if (isset($this->items[$item_id])) {
            unset($this->items[$item_id]);
            $this->saveCart();
        }
    }

    public function updateQuantity($item_id, $quantity) {
        if ($quantity <= 0) {
            $this->removeItem($item_id);
        } else {
            $this->items[$item_id] = $quantity;
            $this->saveCart();
        }
    }

    public function getItems() {
        return $this->items;
    }

    public function getItemCount() {
        return array_sum($this->items);
    }

    public function clear() {
        $this->items = [];
        $this->saveCart();
    }

    private function saveCart() {
        $_SESSION['cart'] = $this->items;
    }

    public function calculateTotal($menuItemModel) {
        $total = 0;
        foreach ($this->items as $item_id => $quantity) {
            $item = $menuItemModel->getById($item_id);
            if ($item) {
                $total += $item['price'] * $quantity;
            }
        }
        return $total;
    }
    
    /**
     * Calculate subtotal (without tax)
     */
    public function calculateSubtotal($menuItemModel) {
        return $this->calculateTotal($menuItemModel);
    }
    
    /**
     * Calculate tax amount using admin settings
     */
    public function calculateTax($menuItemModel) {
        $subtotal = $this->calculateSubtotal($menuItemModel);
        
        if ($this->systemSettings) {
            $taxRate = $this->systemSettings->getTaxRate();
            return $subtotal * $taxRate;
        }
        
        // Fallback to 6% if system settings not available
        return $subtotal * 0.06;
    }
    
    /**
     * Calculate total with tax using admin settings
     */
    public function calculateTotalWithTax($menuItemModel) {
        $subtotal = $this->calculateSubtotal($menuItemModel);
        $tax = $this->calculateTax($menuItemModel);
        return $subtotal + $tax;
    }
    
    /**
     * Get tax name from admin settings
     */
    public function getTaxName() {
        if ($this->systemSettings) {
            return $this->systemSettings->getTaxName();
        }
        return 'SST'; // Fallback
    }
    
    /**
     * Get tax rate percentage from admin settings
     */
    public function getTaxRatePercent() {
        if ($this->systemSettings) {
            return $this->systemSettings->getTaxRatePercent();
        }
        return 6; // Fallback
    }
    
    /**
     * Get currency symbol from admin settings
     */
    public function getCurrencySymbol() {
        if ($this->systemSettings) {
            return $this->systemSettings->getCurrencySymbol();
        }
        return 'RM'; // Fallback
    }
}
?> 