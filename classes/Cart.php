<?php
class Cart {
    private $items = [];
    
    public function __construct() {
        if (isset($_SESSION['cart'])) {
            $this->items = $_SESSION['cart'];
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
}
?> 