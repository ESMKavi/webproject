<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') { header("Location: login.php"); exit(); }
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed."); }
$user_id = $_SESSION['user_id'];

// Get the instructor's own ID from the `instructors` table
$instructor_id_stmt = $conn->prepare("SELECT id FROM instructors WHERE user_id = ?");
$instructor_id_stmt->bind_param("i", $user_id);
$instructor_id_stmt->execute();
$instructor_id = $instructor_id_stmt->get_result()->fetch_assoc()['id'] ?? 0;
$instructor_id_stmt->close();

// Fetch group sessions assigned to this instructor for today or in the future
$sessions = [];
if ($instructor_id > 0) {
    $sql = "SELECT id, session_title, session_date, license_category FROM group_sessions 
            WHERE instructor_id = ? AND session_date >= CURDATE()
            ORDER BY session_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Instructor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="instructor_dashboard.php">
                <img src="images/logo.png" alt="Logo" class="navbar-logo">
                Sriyani Driving School System
            </a>
            <ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li></ul>
        </div>
    </nav>
    <main class="container py-4">
        <div class="dashboard-header">
            <h2>Your Assigned Group Sessions</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
        </div>

        <div class="list-group shadow-sm">
            <?php if (empty($sessions)): ?>
                <div class="list-group-item text-center text-muted p-4">You have no upcoming group sessions assigned.</div>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($session['session_title']); ?></h5>
                            <p class="mb-1 text-muted"><strong>Date:</strong> <?php echo htmlspecialchars(date('l, F j, Y', strtotime($session['session_date']))); ?></p>
                            <small><strong>Category:</strong> <?php echo htmlspecialchars($session['license_category']); ?></small>
                        </div>
                        <a href="mark_attendance.php?session_id=<?php echo $session['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-clipboard-check-fill"></i> Open Attendance Sheet
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer bg-dark text-white text-center py-3">
        <div class="container">
            <p class="mb-1"><small>If you have any technical questions, please contact the IT Department.</small></p>
            <p class="mb-0"><small>&copy; <?php echo date('Y'); ?> Sriyani DS-IT Department. All rights reserved.</small></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>