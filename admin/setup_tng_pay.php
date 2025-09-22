<?php
/**
 * Database setup script for TNG Pay functionality
 * Run this file once to add the required database columns
 */

require_once(__DIR__ . '/../config/Database.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>TNG Pay Database Setup</h2>";
    echo "<p>Setting up database columns for TNG Pay functionality...</p>";
    
    // Check if payment_method column exists
    $check_column_sql = "SHOW COLUMNS FROM payments LIKE 'payment_method'";
    $stmt = $db->prepare($check_column_sql);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Add payment_method column
        $add_payment_method = "ALTER TABLE payments ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cash' AFTER payment_date";
        $db->exec($add_payment_method);
        echo "<p>✅ Added payment_method column</p>";
    } else {
        echo "<p>✅ payment_method column already exists</p>";
    }
    
    // Check if tng_reference column exists
    $check_tng_sql = "SHOW COLUMNS FROM payments LIKE 'tng_reference'";
    $stmt = $db->prepare($check_tng_sql);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Add tng_reference column
        $add_tng_ref = "ALTER TABLE payments ADD COLUMN tng_reference VARCHAR(100) NULL AFTER payment_method";
        $db->exec($add_tng_ref);
        echo "<p>✅ Added tng_reference column</p>";
    } else {
        echo "<p>✅ tng_reference column already exists</p>";
    }
    
    // Update existing records to have 'cash' as payment method
    $update_existing = "UPDATE payments SET payment_method = 'cash' WHERE payment_method IS NULL";
    $affected_rows = $db->exec($update_existing);
    echo "<p>✅ Updated $affected_rows existing payment records</p>";
    
    // Add indexes for better performance
    try {
        $add_index1 = "CREATE INDEX idx_payments_method ON payments(payment_method)";
        $db->exec($add_index1);
        echo "<p>✅ Added index for payment_method</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Index for payment_method already exists</p>";
    }
    
    try {
        $add_index2 = "CREATE INDEX idx_payments_tng_ref ON payments(tng_reference)";
        $db->exec($add_index2);
        echo "<p>✅ Added index for tng_reference</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Index for tng_reference already exists</p>";
    }
    
    echo "<h3>🎉 Database setup completed successfully!</h3>";
    echo "<p>TNG Pay functionality is now ready to use.</p>";
    echo "<p><a href='payment_counter.php'>Go to Payment Counter</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error during setup:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>
