<?php
session_start();
require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../classes/Auth.php');
require_once(__DIR__ . '/classes/QRCodeManager.php');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$qrCode = new QRCodeManager($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get table ID from URL
if (!isset($_GET['table_id'])) {
    die("Table ID is required");
}

$table_id = $_GET['table_id'];

// Get table information
$query = "SELECT t.table_number, qc.image_path, qc.expires_at 
          FROM tables t 
          LEFT JOIN qr_codes qc ON t.id = qc.table_id AND qc.is_active = 1 
          WHERE t.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$table_id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    die("Table not found");
}

// Get QR code path
$qr_file = "/food1/uploads/qrcodes/" . basename($table['image_path']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Code - Table <?php echo htmlspecialchars($table['table_number']); ?></title>
    <style>
        @media print {
            @page {
                margin: 0;
                size: 80mm 210mm;
                page-break-after: avoid;
                page-break-before: avoid;
            }
            
            body {
                margin: 0;
                padding: 0;
                width: 80mm;
                height: 210mm;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print-container {
                width: 80mm;
                margin: 0 auto;
                text-align: center;
                page-break-inside: avoid;
                padding: 5mm;
                box-sizing: border-box;
            }

            .restaurant-name {
                font-size: 16px;
                font-weight: bold;
                color: #000;
                margin: 0 0 5mm;
                text-transform: uppercase;
                font-family: Arial, sans-serif;
            }

            .table-number {
                font-size: 20px;
                font-weight: bold;
                margin: 0 0 5mm;
                color: #000;
            }

            .qr-container {
                margin: 0 auto 5mm;
            }

            .qr-code {
                width: 65mm;
                height: 65mm;
                display: block;
                margin: 0 auto;
            }

            .instructions {
                font-size: 12px;
                color: #000;
                margin: 5mm 0;
                text-align: left;
                font-family: Arial, sans-serif;
            }

            .instructions ol {
                padding-left: 5mm;
                margin: 0;
            }

            .instructions li {
                margin: 2mm 0;
                line-height: 1.3;
            }

            .validity {
                font-size: 16px;
                color: #333;
                margin-top: 5mm;
                text-align: center;
                padding-bottom: 5mm;
                font-weight: 600;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Screen styles */
        @media screen {
            body {
                margin: 20px;
                padding-top: 80px;
                font-family: Arial, sans-serif;
            }

            .print-container {
                max-width: 800px;
                margin: 0 auto;
                text-align: center;
            }

            .print-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 8px rgba(0,0,0,0.15);
                background: #1d4ed8;
            }

            .print-button:active {
                transform: translateY(0);
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <div class="top-actions no-print" style="
        position: fixed;
        top: 20px;
        left: 0;
        right: 0;
        text-align: center;
        z-index: 1000;
        padding: 10px;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    ">
        <button onclick="window.print()" class="print-button" style="
            font-size: 24px;
            padding: 15px 40px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        ">
            <i class="fas fa-print" style="margin-right: 10px;"></i>
            Print QR Code
        </button>
    </div>
    
    <div class="print-container">
        <div class="restaurant-name">Gourmet Delights</div>
        <div class="table-number">Table <?php echo htmlspecialchars($table['table_number']); ?></div>
        
        <div class="qr-container">
            <img src="<?php echo htmlspecialchars($qr_file); ?>" 
                 alt="QR Code for Table <?php echo htmlspecialchars($table['table_number']); ?>"
                 class="qr-code">
        </div>

        <div class="instructions">
            <ol>
                <li>Open your smartphone's camera app</li>
                <li>Point your camera at the QR code above</li>
                <li>Tap the notification to view our digital menu</li>
            </ol>
        </div>

        <div class="validity">
            Valid until: <?php echo date('d/m/Y h:i A', strtotime($table['expires_at'])); ?>
        </div>
    </div>

    <script>
        // Automatically open print dialog when page loads
        window.onload = function() {
            // Small delay to ensure everything is loaded
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // Handle print events to redirect back after printing
        window.addEventListener('afterprint', function() {
            // Redirect back to QR codes page after printing
            setTimeout(function() {
                window.location.href = 'qr_codes.php';
            }, 1000);
        });

        // Fallback: redirect after 10 seconds if print dialog is not used
        setTimeout(function() {
            if (window.location.href.indexOf('print_qr.php') !== -1) {
                window.location.href = 'qr_codes.php';
            }
        }, 10000);
    </script>
</body>
</html>
