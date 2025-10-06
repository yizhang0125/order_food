<?php
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/PaymentController.php');

$database = new Database();
$db = $database->getConnection();
$paymentController = new PaymentController($db);

// Test the cash rounding function (nearest 0.05)
$test_amounts = [8.41, 8.42, 8.43, 8.44, 8.46, 8.47, 8.48, 8.49, 8.40, 8.45, 8.50, 60.42, 67.84, 8.445, 8.435, 8.465, 8.475, 12.01, 12.02, 12.03, 12.04, 12.06, 12.07, 12.08, 12.09];

echo "<h2>Cash Rounding Test (Nearest 0.05)</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Original Amount</th><th>Cash Rounded</th><th>Standard Rounding</th><th>Difference</th></tr>";

foreach ($test_amounts as $amount) {
    $cash_rounded = $paymentController->customRound($amount);
    $standard_rounding = round($amount, 2);
    $difference = $cash_rounded - $standard_rounding;
    
    echo "<tr>";
    echo "<td>RM " . number_format($amount, 3) . "</td>";
    echo "<td><strong>RM " . number_format($cash_rounded, 2) . "</strong></td>";
    echo "<td>RM " . number_format($standard_rounding, 2) . "</td>";
    echo "<td>" . ($difference != 0 ? "RM " . number_format($difference, 2) : "Same") . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Cash Rounding Examples (Nearest 0.05):</h3>";
echo "<ul>";
echo "<li>8.41 → 8.40 (round down to nearest 0.05)</li>";
echo "<li>8.42 → 8.40 (round down to nearest 0.05)</li>";
echo "<li>8.43 → 8.45 (round up to nearest 0.05)</li>";
echo "<li>8.44 → 8.45 (round up to nearest 0.05)</li>";
echo "<li>8.46 → 8.45 (round down to nearest 0.05)</li>";
echo "<li>8.47 → 8.45 (round down to nearest 0.05)</li>";
echo "<li>8.48 → 8.50 (round up to nearest 0.05)</li>";
echo "<li>8.49 → 8.50 (round up to nearest 0.05)</li>";
echo "<li>12.01 → 12.00 (round down to nearest 0.05)</li>";
echo "<li>12.02 → 12.00 (round down to nearest 0.05)</li>";
echo "<li>12.03 → 12.05 (round up to nearest 0.05)</li>";
echo "<li>12.04 → 12.05 (round up to nearest 0.05)</li>";
echo "</ul>";

echo "<h3>How Cash Rounding Works:</h3>";
echo "<p>Cash rounding rounds amounts to the nearest 5-cent increment (0.05):</p>";
echo "<ul>";
echo "<li>Ends in .01 or .02 → Round down to .00</li>";
echo "<li>Ends in .03 or .04 → Round up to .05</li>";
echo "<li>Ends in .06 or .07 → Round down to .05</li>";
echo "<li>Ends in .08 or .09 → Round up to .10</li>";
echo "<li>Already ends in .00, .05, .10, .15, etc. → No change</li>";
echo "</ul>";
?>
