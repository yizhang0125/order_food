<?php
require_once 'Model.php';

class SystemSettings extends Model {
    protected $table_name = "system_settings";
    protected $conn;
    private $cache = [];
    
    public function __construct($db) {
        parent::__construct($db);
        $this->conn = $db;
    }
    
    /**
     * Get a setting value by key
     */
    public function getSetting($key, $default = null) {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        try {
            $query = "SELECT setting_value, setting_type FROM " . $this->table_name . " WHERE setting_key = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $value = $this->convertValue($result['setting_value'], $result['setting_type']);
                $this->cache[$key] = $value;
                return $value;
            }
            
            return $default;
        } catch (Exception $e) {
            error_log("Error getting setting: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Set a setting value
     */
    public function setSetting($key, $value, $type = 'string', $description = null) {
        try {
            $query = "INSERT INTO " . $this->table_name . " (setting_key, setting_value, setting_type, description) 
                     VALUES (?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE 
                     setting_value = VALUES(setting_value), 
                     setting_type = VALUES(setting_type),
                     description = VALUES(description),
                     updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$key, $value, $type, $description]);
            
            // Update cache
            $this->cache[$key] = $this->convertValue($value, $type);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error setting setting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all settings
     */
    public function getAllSettings() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " ORDER BY setting_key";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_key']] = [
                    'value' => $this->convertValue($row['setting_value'], $row['setting_type']),
                    'type' => $row['setting_type'],
                    'description' => $row['description']
                ];
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Error getting all settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update multiple settings at once
     */
    public function updateSettings($settings) {
        try {
            $this->conn->beginTransaction();
            
            foreach ($settings as $key => $data) {
                $value = $data['value'] ?? $data;
                $type = $data['type'] ?? 'string';
                $description = $data['description'] ?? null;
                
                $this->setSetting($key, $value, $type, $description);
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error updating settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert value based on type
     */
    private function convertValue($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Get tax rate as decimal (e.g., 6% = 0.06)
     */
    public function getTaxRate() {
        return $this->getSetting('tax_rate', 6) / 100;
    }
    
    /**
     * Get tax rate as percentage (e.g., 6)
     */
    public function getTaxRatePercent() {
        return $this->getSetting('tax_rate', 6);
    }
    
    /**
     * Get tax name
     */
    public function getTaxName() {
        return $this->getSetting('tax_name', 'SST');
    }
    
    /**
     * Get currency symbol
     */
    public function getCurrencySymbol() {
        return $this->getSetting('currency_symbol', 'RM');
    }
    
    /**
     * Get currency code
     */
    public function getCurrencyCode() {
        return $this->getSetting('currency_code', 'MYR');
    }
    
    /**
     * Get restaurant name
     */
    public function getRestaurantName() {
        return $this->getSetting('restaurant_name', 'Gourmet Delights');
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
}
?>
