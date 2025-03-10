<?php
require_once '../config/Database.php';
require_once '../classes/QRCodeGenerator.php';
require_once '../classes/Table.php';

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$table = new Table($db);

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['table_number'])) {
    $tableNumber = $_POST['table_number'];
    $qrGenerator = new QRCodeGenerator($tableNumber);
    $token = $qrGenerator->getToken();
    
    if ($table->createTable($tableNumber, $token)) {
        $qrCode = $qrGenerator->generateQRCode();
        $message = "QR Code generated successfully!";
    } else {
        $message = "Error generating QR code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Code - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Generate QR Code for Table</h2>
        <?php if($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label for="table_number" class="form-label">Table Number:</label>
                <input type="number" class="form-control" id="table_number" name="table_number" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate QR Code</button>
        </form>
        
        <?php if(isset($qrCode)): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">QR Code for Table <?php echo htmlspecialchars($tableNumber); ?></h5>
                    <img src="<?php echo $qrCode; ?>" alt="Table QR Code">
                    <p class="mt-2">Token: <?php echo htmlspecialchars($token); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 