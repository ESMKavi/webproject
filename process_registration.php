<?php
// Report all errors for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// !!! IMPORTANT: CONFIGURE YOUR DATABASE CREDENTIALS HERE !!!
// Include the database configuration file
require_once 'config.php';

// !!! IMPORTANT: REPLACE WITH YOUR ACTUAL WEBSITE BASE URL !!!
define('BASE_URL', 'http://localhost/sriyani_final/');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $name = htmlspecialchars(trim($_POST['name']));
    $id_number = htmlspecialchars(trim($_POST['id_number']));
    $address = htmlspecialchars(trim($_POST['address']));
    $age = filter_var(trim($_POST['age']), FILTER_VALIDATE_INT);
    $customer_email = filter_var(trim($_POST['customer_email']), FILTER_VALIDATE_EMAIL);
    $branch = htmlspecialchars(trim($_POST['branch']));
    $appointment_date = htmlspecialchars(trim($_POST['appointment_date']));
    $phone_number = htmlspecialchars(trim($_POST['phone_number']));

    if (empty($name) || empty($id_number) || empty($address) || empty($phone_number) || !$age || !$customer_email || empty($branch) || empty($appointment_date)) {
        die("Error: All fields are required. Please go back and fill out the form completely.");
    }
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    $temp_reg_code = 'TEMP-' . time();
    $status = 'pending';

    $stmt = $conn->prepare("INSERT INTO registrations (registration_code, full_name, id_number, phone_number, address, age, customer_email, branch, appointment_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) { die('Database error: Unable to prepare statement.'); }
    $stmt->bind_param("sssssissss", $temp_reg_code, $name, $id_number, $phone_number, $address, $age, $customer_email, $branch, $appointment_date, $status);

    $db_insert_success = false;
    if ($stmt->execute()) {
        $db_insert_success = true;
    } else {
        error_log("Database insertion failed: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();

    // Display Confirmation Page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Submitted - Pending Approval</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <nav class="navbar navbar-light bg-light">
            <div class="container"><a class="navbar-brand" href="index.html">Sriyani Driving School</a></div>
        </nav>
        <main class="py-5">
            <div class="container text-center form-page-container">
                <div class="py-5">
                    <?php if (!$db_insert_success): ?>
                        <h1 class="display-5 fw-bold text-danger">Registration Failed</h1>
                        <p class="lead mb-4">There was a database error while submitting your registration. Please contact our branch directly to register.</p>
                    <?php else: ?>
                        <h1 class="display-5 fw-bold text-success">Thank You, <?php echo $name; ?>!</h1>
                        <p class="lead mb-4">Your registration has been received and is now **awaiting approval** by our <?php echo $branch; ?> branch. You will be notified once approved.</p>
                        
                        <div class="card my-4">
                            <div class="card-header">Your Digital ID Code</div>
                            <div class="card-body">
                                <p>Please save this QR code. You can use it at the branch for quick identification.</p>
                                <div id="customerQrCode" class="d-flex justify-content-center"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a href="index.html" class="btn btn-primary btn-lg px-4">Go to Home Page</a>
                </div>
            </div>
        </main>
        
        <footer class="mt-auto">
            <div class="container"><p>&copy; <?php echo date('Y'); ?> Sriyani DS-IT Department. All rights reserved.</p></div>
        </footer>
        
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($db_insert_success): ?>
                    const idNumber = "<?php echo htmlspecialchars($id_number); ?>";
                    const siteBaseUrl = "<?php echo rtrim(BASE_URL, '/'); ?>";
                    const qrLink = `${siteBaseUrl}/find_student.php?id_number=${idNumber}`;

                    new QRCode(document.getElementById("customerQrCode"), {
                        text: qrLink,
                        width: 180,
                        height: 180
                    });
                <?php endif; ?>
            });
        </script>
    </body>
    </html>
    <?php
} else {
    // If accessed directly, redirect to the registration form
    header("Location: register.html");
    exit();
}
?>