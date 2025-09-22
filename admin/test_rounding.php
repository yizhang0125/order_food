<?php
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/PaymentController.php');

$database = new Database();
$db = $database->getConnection();
$paymentController = new PaymentController($db);

// Test the custom rounding function
$test_amounts = [8.41, 8.42, 8.43, 8.44, 8.46, 8.47, 8.48, 8.49, 8.40, 8.45, 8.50, 60.42, 67.84];

echo "<h2>Custom Rounding Test</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Original Amount</th><th>Rounded Amount</th><th>Expected</th></tr>";

foreach ($test_amounts as $amount) {
    $rounded = $paymentController->customRound($amount);
    $expected = "";
    
    if ($amount == 248.04) $expected = "248.05";
    elseif ($amount == 67.81 || $amount == 67.82) $expected = "67.80";
    elseif ($amount == 67.83 || $amount == 67.84) $expected = "67.85";
    elseif ($amount == 67.86 || $amount == 67.87) $expected = "67.85";
    elseif ($amount == 67.88 || $amount == 67.89) $expected = "67.90";
    
    echo "<tr>";
    echo "<td>RM " . number_format($amount, 2) . "</td>";
    echo "<td>RM " . number_format($rounded, 2) . "</td>";
    echo "<td>RM " . $expected . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
