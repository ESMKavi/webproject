<?php
session_start();

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'branch_staff') {
    header("Location: login.php");
    exit();
}

// Include required files
require_once 'config.php';
require_once 'mail_config.php';
require_once 'includes/PHPMailer/src/Exception.php';
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';

/**
 * Generates a new, unique, and sequential registration code for a student
 * using a database transaction to prevent race conditions.
 */
function generateRegistrationCode($conn, $branch_name) {
    $conn->begin_transaction();
    try {
        $prefix = strtoupper(substr($branch_name, 0, 3));
        $search_pattern = $prefix . '%';
        $stmt = $conn->prepare("SELECT registration_code FROM registrations WHERE branch = ? AND registration_code LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        if (!$stmt) { throw new Exception("Database prepare failed: " . $conn->error); }
        $stmt->bind_param("ss", $branch_name, $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $latest_code = $result->fetch_assoc();
        $stmt->close();
        $new_suffix_num = 1;
        if ($latest_code) {
            $last_num = (int) substr($latest_code['registration_code'], strlen($prefix));
            $new_suffix_num = $last_num + 1;
        }
        $registration_code = $prefix . sprintf("%05d", $new_suffix_num);
        $conn->commit();
        return $registration_code;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

$message = "";
$message_type = ""; // 'success' or 'danger'
$registration_data = null;
$registration_id = $_GET['id'] ?? null;
$branch_of_logged_in_staff = $_SESSION['branch_name'];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Handle POST request for approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['reg_id'])) {
    $action = $_POST['action'];
    $reg_id_to_update = $_POST['reg_id'];

    $fetch_stmt = $conn->prepare("SELECT * FROM registrations WHERE id = ? AND branch = ?");
    $fetch_stmt->bind_param("is", $reg_id_to_update, $branch_of_logged_in_staff);
    $fetch_stmt->execute();
    $current_reg_data = $fetch_stmt->get_result()->fetch_assoc();
    $fetch_stmt->close();

    if ($current_reg_data) {
        if ($action == 'approve' && $current_reg_data['status'] == 'pending') {
            try {
                $registration_code = generateRegistrationCode($conn, $current_reg_data['branch']);
                $invoice_no = $registration_code; 

                $update_stmt = $conn->prepare("UPDATE registrations SET status = 'approved', approval_date = NOW(), registration_code = ?, invoice_no = ? WHERE id = ?");
                $update_stmt->bind_param("ssi", $registration_code, $invoice_no, $reg_id_to_update);

                if ($update_stmt->execute()) {
                    $message = "Registration approved successfully! New Code: " . htmlspecialchars($registration_code);
                    $message_type = "success";

                    // --- START of PHPMailer Logic ---
                    $mail = new PHPMailer(true);
                    try {
                        // Server settings
                        $mail->SMTPDebug = MAIL_SMTP_DEBUG;
                        $mail->isSMTP();
                        $mail->Host       = MAIL_HOST;
                        $mail->SMTPAuth   = MAIL_SMTP_AUTH;
                        $mail->Username   = MAIL_USERNAME;
                        $mail->Password   = MAIL_PASSWORD;
                        $mail->SMTPSecure = MAIL_SMTP_SECURE;
                        $mail->Port       = MAIL_PORT;

                        // Recipients
                        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
                        $mail->addAddress($current_reg_data['customer_email'], $current_reg_data['full_name']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Registration is Approved!';
                        $mail->Body    = "
                            <h1>Welcome to Sriyani Driving School!</h1>
                            <p>Dear " . htmlspecialchars($current_reg_data['full_name']) . ",</p>
                            <p>We are pleased to inform you that your registration has been <b>approved</b>.</p>
                            <p>Your new official registration code is: <strong>" . htmlspecialchars($registration_code) . "</strong></p>
                            <p>Please use this code for all future communications with our branch. We look forward to seeing you soon!</p>
                            <br>
                            <p>Thank you,<br>The team at Sriyani Driving School (" . htmlspecialchars($current_reg_data['branch']) . " Branch)</p>
                        ";
                        $mail->AltBody = "Dear " . htmlspecialchars($current_reg_data['full_name']) . ",\n\nYour registration has been approved. Your new official registration code is: " . htmlspecialchars($registration_code) . "\n\nThank you,\nThe team at Sriyani Driving School (" . htmlspecialchars($current_reg_data['branch']) . " Branch)";

                        $mail->send();
                        $message .= '<br>Confirmation email sent successfully to the customer.';
                    } catch (Exception $e) {
                        // Email sending failed, but approval still worked.
                        $message .= "<br><span class='fw-bold text-danger'>Warning:</span> Could not send confirmation email. Mailer Error: " . htmlspecialchars($mail->ErrorInfo);
                    }
                    // --- END of PHPMailer Logic ---

                } else {
                    $message = "Error approving registration: " . $update_stmt->error;
                    $message_type = "danger";
                }
                $update_stmt->close();

            } catch (Exception $e) {
                $message = "Could not approve registration. Error: " . $e->getMessage();
                $message_type = "danger";
            }
        } elseif ($action == 'reject') {
            $message = "Rejection logic not yet implemented.";
            $message_type = "info";
        }
    } else {
        $message = "Registration not found or you do not have permission.";
        $message_type = "danger";
    }
}

// Fetch registration details for display
if ($registration_id) {
    $stmt = $conn->prepare("SELECT * FROM registrations WHERE id = ? AND branch = ?");
    $stmt->bind_param("is", $registration_id, $branch_of_logged_in_staff);
    $stmt->execute();
    $registration_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$registration_data) {
        $message = "Registration not found or it does not belong to your branch.";
        $message_type = "danger";
    }
} else {
    $message = "No registration ID provided.";
    $message_type = "danger";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Registration - Sriyani Driving School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="branch_dashboard.php"><?php echo htmlspecialchars($branch_of_logged_in_staff); ?> Panel</a>
            <ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li></ul>
        </div>
    </nav>

    <main class="py-5">
        <div class="container form-page-container">
            <h2 class="text-center mb-4">Registration Review</h2>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> text-center"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($registration_data): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Details for Registration ID: <?php echo htmlspecialchars($registration_data['id']); ?></h5>
                        <?php
                            $status_color = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                            echo '<span class="badge bg-' . ($status_color[$registration_data['status']] ?? 'secondary') . ' p-2">Status: ' . ucfirst(htmlspecialchars($registration_data['status'])) . '</span>';
                        ?>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Full Name:</strong> <?php echo htmlspecialchars($registration_data['full_name']); ?></li>
                        <li class="list-group-item"><strong>ID Number:</strong> <?php echo htmlspecialchars($registration_data['id_number']); ?></li>
                        <li class="list-group-item"><strong>Phone Number:</strong> <?php echo htmlspecialchars($registration_data['phone_number']); ?></li>
                        <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($registration_data['customer_email']); ?></li>
                        <li class="list-group-item"><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($registration_data['address'])); ?></li>
                        <li class="list-group-item"><strong>Age:</strong> <?php echo htmlspecialchars($registration_data['age']); ?></li>
                        <li class="list-group-item"><strong>Preferred Appointment:</strong> <?php echo htmlspecialchars($registration_data['appointment_date']); ?></li>
                        <li class="list-group-item"><strong>Applied On:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($registration_data['registration_date']))); ?></li>
                    </ul>
                    <?php if ($registration_data['status'] == 'pending'): ?>
                        <div class="card-body text-center">
                            <form action="approve_registration.php?id=<?php echo htmlspecialchars($registration_id); ?>" method="POST" class="d-inline">
                                <input type="hidden" name="reg_id" value="<?php echo htmlspecialchars($registration_id); ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-lg mx-2">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg mx-2">Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="text-center mt-4">
                <a href="branch_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </main>
</body>
</html>