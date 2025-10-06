<?php
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/classes/PaymentController.php');

$database = new Database();
$db = $database->getConnection();
$paymentController = new PaymentController($db);

// Test various payment amounts to demonstrate 四舍五入 rounding
$test_amounts = [
    8.41, 8.42, 8.43, 8.44, 8.45, 8.46, 8.47, 8.48, 8.49, 8.50,
    12.345, 12.355, 12.365, 12.375, 12.385, 12.395,
    25.001, 25.004, 25.005, 25.006, 25.009,
    100.123, 100.125, 100.126, 100.127, 100.128, 100.129,
    67.84, 67.85, 67.86, 67.87, 67.88, 67.89
];

echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Payment Amount Rounding Test (四舍五入)</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo ".test-table { margin-top: 20px; }";
echo ".highlight { background-color: #f8f9fa; }";
echo ".round-up { background-color: #d4edda; }";
echo ".round-down { background-color: #f8d7da; }";
echo ".no-change { background-color: #d1ecf1; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1 class='text-center mb-4'>Payment Amount Cash Rounding Test (Nearest 0.05)</h1>";
echo "<p class='text-center text-muted'>Testing cash rounding to nearest 5 cents for payment amounts</p>";

echo "<table class='table table-striped test-table'>";
echo "<thead class='table-dark'>";
echo "<tr>";
echo "<th>Original Amount</th>";
echo "<th>Rounded Amount</th>";
echo "<th>Rounding Type</th>";
echo "<th>Explanation</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

foreach ($test_amounts as $amount) {
    $rounded = $paymentController->customRound($amount);
    $original_formatted = number_format($amount, 3);
    $rounded_formatted = number_format($rounded, 2);
    
    // Determine cash rounding type
    $cents = intval(($amount * 100) % 100);
    $last_digit = $cents % 10;
    $rounding_type = "";
    $explanation = "";
    $row_class = "";
    
    if ($cents % 5 == 0) {
        $rounding_type = "No Change";
        $explanation = "Already at 5-cent increment";
        $row_class = "no-change";
    } elseif ($last_digit >= 1 && $last_digit <= 2) {
        $rounding_type = "Round Down";
        $explanation = "Round down to nearest 0.05";
        $row_class = "round-down";
    } elseif ($last_digit >= 3 && $last_digit <= 4) {
        $rounding_type = "Round Up";
        $explanation = "Round up to nearest 0.05";
        $row_class = "round-up";
    } elseif ($last_digit >= 6 && $last_digit <= 7) {
        $rounding_type = "Round Down";
        $explanation = "Round down to nearest 0.05";
        $row_class = "round-down";
    } elseif ($last_digit >= 8 && $last_digit <= 9) {
        $rounding_type = "Round Up";
        $explanation = "Round up to nearest 0.05";
        $row_class = "round-up";
    }
    
    echo "<tr class='$row_class'>";
    echo "<td>RM " . $original_formatted . "</td>";
    echo "<td><strong>RM " . $rounded_formatted . "</strong></td>";
    echo "<td>" . $rounding_type . "</td>";
    echo "<td>" . $explanation . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

echo "<div class='alert alert-info mt-4'>";
echo "<h5><i class='fas fa-info-circle'></i> How Cash Rounding Works (Nearest 0.05):</h5>";
echo "<ul>";
echo "<li><strong>Round Down:</strong> If cents end in 1-2 or 6-7, round down to nearest 0.05</li>";
echo "<li><strong>Round Up:</strong> If cents end in 3-4 or 8-9, round up to nearest 0.05</li>";
echo "<li><strong>No Change:</strong> If already at 0.05 increment (.00, .05, .10, .15, etc.)</li>";
echo "<li><strong>Examples:</strong>";
echo "<ul>";
echo "<li>8.41 → 8.40 (round down)</li>";
echo "<li>8.43 → 8.45 (round up)</li>";
echo "<li>8.46 → 8.45 (round down)</li>";
echo "<li>8.48 → 8.50 (round up)</li>";
echo "</ul>";
echo "</li>";
echo "</ul>";
echo "</div>";

echo "<div class='text-center mt-4'>";
echo "<a href='payment_details.php' class='btn btn-primary'>View Payment Details</a>";
echo "<a href='payment_counter.php' class='btn btn-success ms-2'>Payment Counter</a>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>

