<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/../classes/SystemSettings.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$systemSettings = new SystemSettings($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = "Tax System Test";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Include Layout -->
    <?php include 'includes/layout.php'; ?>
    
    <div class="container-fluid py-4" style="margin-top: 70px;">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-calculator"></i> Tax System Test
                </h1>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Current Tax Settings:</h5>
                    <p>This page shows the current tax settings and allows you to test the tax calculations.</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-cog"></i> Current Settings</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <tr>
                                        <td><strong>Tax Rate:</strong></td>
                                        <td><?php echo $systemSettings->getTaxRatePercent(); ?>%</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tax Name:</strong></td>
                                        <td><?php echo $systemSettings->getTaxName(); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Currency Symbol:</strong></td>
                                        <td><?php echo $systemSettings->getCurrencySymbol(); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Currency Code:</strong></td>
                                        <td><?php echo $systemSettings->getCurrencyCode(); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Restaurant Name:</strong></td>
                                        <td><?php echo $systemSettings->getRestaurantName(); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-calculator"></i> Tax Calculator</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Subtotal Amount:</label>
                                    <input type="number" class="form-control" id="subtotalInput" value="100" step="0.01">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tax Rate:</label>
                                    <input type="number" class="form-control" id="taxRateInput" value="<?php echo $systemSettings->getTaxRatePercent(); ?>" step="0.1">
                                </div>
                                <button class="btn btn-primary" onclick="calculateTax()">
                                    <i class="fas fa-calculator"></i> Calculate
                                </button>
                                
                                <div class="mt-3" id="calculationResults">
                                    <!-- Results will be displayed here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-link"></i> API Test</h5>
                            </div>
                            <div class="card-body">
                                <p>Test the tax settings API endpoint:</p>
                                <button class="btn btn-outline-primary" onclick="testAPI()">
                                    <i class="fas fa-play"></i> Test API
                                </button>
                                <div class="mt-3" id="apiResults">
                                    <!-- API results will be displayed here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-edit"></i> Quick Settings Update</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Tax Rate (%)</label>
                                                <input type="number" class="form-control" name="tax_rate" value="<?php echo $systemSettings->getTaxRatePercent(); ?>" step="0.1">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Tax Name</label>
                                                <input type="text" class="form-control" name="tax_name" value="<?php echo $systemSettings->getTaxName(); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Currency Symbol</label>
                                                <input type="text" class="form-control" name="currency_symbol" value="<?php echo $systemSettings->getCurrencySymbol(); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Currency Code</label>
                                                <input type="text" class="form-control" name="currency_code" value="<?php echo $systemSettings->getCurrencyCode(); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Update Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calculateTax() {
            const subtotal = parseFloat(document.getElementById('subtotalInput').value) || 0;
            const taxRate = parseFloat(document.getElementById('taxRateInput').value) || 0;
            
            const tax = subtotal * (taxRate / 100);
            const total = subtotal + tax;
            
            const results = `
                <div class="alert alert-success">
                    <h6>Calculation Results:</h6>
                    <p><strong>Subtotal:</strong> RM ${subtotal.toFixed(2)}</p>
                    <p><strong>Tax (${taxRate}%):</strong> RM ${tax.toFixed(2)}</p>
                    <p><strong>Total:</strong> RM ${total.toFixed(2)}</p>
                </div>
            `;
            
            document.getElementById('calculationResults').innerHTML = results;
        }
        
        async function testAPI() {
            try {
                const response = await fetch('/api/get_tax_settings.php');
                const data = await response.json();
                
                const results = `
                    <div class="alert alert-success">
                        <h6>API Response:</h6>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
                
                document.getElementById('apiResults').innerHTML = results;
            } catch (error) {
                document.getElementById('apiResults').innerHTML = `
                    <div class="alert alert-danger">
                        <h6>API Error:</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        // Auto-calculate on input change
        document.getElementById('subtotalInput').addEventListener('input', calculateTax);
        document.getElementById('taxRateInput').addEventListener('input', calculateTax);
        
        // Initial calculation
        calculateTax();
    </script>
</body>
</html>

<?php
// Handle quick settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'tax_rate' => [
                'value' => $_POST['tax_rate'] ?? 6,
                'type' => 'number',
                'description' => 'Tax rate percentage'
            ],
            'tax_name' => [
                'value' => $_POST['tax_name'] ?? 'SST',
                'type' => 'string',
                'description' => 'Tax name'
            ],
            'currency_symbol' => [
                'value' => $_POST['currency_symbol'] ?? 'RM',
                'type' => 'string',
                'description' => 'Currency symbol'
            ],
            'currency_code' => [
                'value' => $_POST['currency_code'] ?? 'MYR',
                'type' => 'string',
                'description' => 'Currency code'
            ]
        ];
        
        if ($systemSettings->updateSettings($settings)) {
            echo '<script>alert("Settings updated successfully!"); window.location.reload();</script>';
        } else {
            echo '<script>alert("Failed to update settings!");</script>';
        }
    } catch (Exception $e) {
        echo '<script>alert("Error: ' . $e->getMessage() . '");</script>';
    }
}
?>
