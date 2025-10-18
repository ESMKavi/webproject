<?php
session_start();
// Security Check: You must be logged in to use this feature.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    header("Location: login.php?redirect_to=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// !!! IMPORTANT: CONFIGURE YOUR DATABASE CREDENTIALS HERE !!!
// Include the database configuration file
require_once 'config.php';

$id_number_from_qr = $_GET['id_number'] ?? '';
$error_message = '';

if (empty($id_number_from_qr)) {
    $error_message = "No ID Number was provided in the QR code.";
} else {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Database connection failed.");
    }

    // Prepare a statement to find the student's internal ID from their national ID number
    $stmt = $conn->prepare("SELECT id FROM registrations WHERE id_number = ? LIMIT 1");
    $stmt->bind_param("s", $id_number_from_qr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $student_internal_id = $student['id'];
        $stmt->close();
        $conn->close();
        
        // Redirect to the student's main profile page for editing/viewing
        header("Location: view_edit_registration.php?id=" . $student_internal_id);
        exit();
    } else {
        $error_message = "No student found with the ID Number: " . htmlspecialchars($id_number_from_qr);
    }
    $stmt->close();
    $conn->close();
}

// If we reach here, an error occurred. Show a user-friendly error page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand mb-0 h1"><i class="bi bi-search"></i> Student Finder</span>
        </div>
    </nav>
    <main class="container py-5 text-center">
        <div class="col-md-6 mx-auto">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h2 class="text-danger"><i class="bi bi-x-circle-fill"></i> Error</h2>
                    <p class="lead mt-3"><?php echo $error_message; ?></p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">Go to Dashboard</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>