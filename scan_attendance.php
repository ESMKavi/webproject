<?php
session_start();
// Include the database configuration file
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection Failed."); }

$hash = $_GET['hash'] ?? '';
$message = ''; $message_type = 'info';
$lesson_details = null;

if (empty($hash)) {
    $message = 'No lesson identifier provided. Please scan a valid QR code or use manual lookup.';
    $message_type = 'danger';
} else {
    $sql = "SELECT ts.id, ts.status, ts.lesson_datetime, r.full_name as student_name, r.payment_done
            FROM training_schedules ts
            JOIN registrations r ON ts.student_id = r.id
            WHERE ts.qr_code_hash = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $lesson_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lesson_details) { $message = 'Invalid or expired QR code. This lesson was not found.'; $message_type = 'danger'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_present']) && $lesson_details) {
    if ($lesson_details['status'] === 'Scheduled' && $lesson_details['payment_done']) {
        $update_sql = "UPDATE training_schedules SET status = 'Completed', attendance_marked_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $lesson_details['id']);
        if ($update_stmt->execute()) {
            $message = 'Attendance marked successfully!';
            $message_type = 'success';
            $lesson_details['status'] = 'Completed'; // Refresh status for display
        } else {
            $message = 'Error marking attendance. Please try again.';
            $message_type = 'danger';
        }
        $update_stmt->close();
    } else {
        $message = 'Action blocked. Either payment is incomplete or lesson is not in a scannable state.';
        $message_type = 'danger';
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Mark Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand mb-0 h1"><i class="bi bi-qr-code-scan"></i> Attendance Scanner</span></div></nav>
    <main class="container py-5">
        <div class="row justify-content-center"><div class="col-md-6">
            <div class="card text-center shadow-lg">
                <div class="card-header"><h4 class="mb-0">Lesson Attendance Verification</h4></div>
                <div class="card-body p-4">
                    <?php if ($message): ?> <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div> <?php endif; ?>
                    <?php if ($lesson_details): ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($lesson_details['student_name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars(date('l, F j, Y @ g:i A', strtotime($lesson_details['lesson_datetime']))); ?></p>
                        <hr>
                        <?php
                        if ($lesson_details['status'] === 'Completed') {
                            echo '<div class="alert alert-success fs-4"><i class="bi bi-check-circle-fill"></i><br><strong>ATTENDANCE MARKED</strong><br><small>This lesson is already complete.</small></div>';
                        } elseif ($lesson_details['status'] === 'Cancelled') {
                            echo '<div class="alert alert-danger fs-4"><i class="bi bi-x-octagon-fill"></i><br><strong>LESSON CANCELLED</strong><br><small>Attendance cannot be marked.</small></div>';
                        } elseif (!$lesson_details['payment_done']) {
                            echo '<div class="alert alert-warning fs-4"><i class="bi bi-exclamation-triangle-fill"></i><br><strong>PAYMENT INCOMPLETE</strong><br><small>Student cannot attend. Please contact the office.</small></div>';
                        } else {
                            echo '<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Student payment is verified. You can proceed with the lesson.</div>';
                            echo '<form method="POST"><button type="submit" name="mark_present" class="btn btn-success btn-lg w-100 mt-3">Mark as Present & Complete Lesson</button></form>';
                        }
                        ?>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'instructor'): ?>
                        <div class="mt-4"><a href="instructor_dashboard.php" class="btn btn-secondary">&larr; Back to My Dashboard</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div></div>
    </main>
</body>
</html>