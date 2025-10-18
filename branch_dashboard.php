<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'branch_staff') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$branch_name = $_SESSION['branch_name'];

// --- Fetch stats for the Stat Cards ---
$pending_count_stmt = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE status = 'pending' AND branch = ?");
$pending_count_stmt->bind_param("s", $branch_name);
$pending_count_stmt->execute();
$pending_registrations_count = $pending_count_stmt->get_result()->fetch_row()[0];
$pending_count_stmt->close();

$approved_count_stmt = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE status = 'approved' AND branch = ?");
$approved_count_stmt->bind_param("s", $branch_name);
$approved_count_stmt->execute();
$approved_students_count = $approved_count_stmt->get_result()->fetch_row()[0];
$approved_count_stmt->close();

// --- Fetch full data for the tables below ---
$pending_stmt = $conn->prepare("SELECT id, full_name, id_number, phone_number, appointment_date, registration_date FROM registrations WHERE branch = ? AND status = 'pending' ORDER BY registration_date DESC");
$pending_stmt->bind_param("s", $branch_name);
$pending_stmt->execute();
$pending_registrations = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_stmt->close();

$approved_stmt = $conn->prepare("SELECT id, registration_code, full_name, id_number, phone_number, approval_date FROM registrations WHERE branch = ? AND status = 'approved' ORDER BY approval_date DESC LIMIT 10");
$approved_stmt->bind_param("s", $branch_name);
$approved_stmt->execute();
$approved_registrations = $approved_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$approved_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($branch_name); ?> Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="branch_dashboard.php">
                <img src="images/logo.png" alt="Logo" class="navbar-logo">
                Sriyani Driving School System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#staffNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="staffNav">
                 <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="branch_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_finances.php">Finances</a></li>
                    <li class="nav-item"><a class="nav-link" href="search_registrations.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_group_sessions.php">Group Training</a></li>
                </ul>
                <ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li></ul>
            </div>
        </div>
    </nav>

    <main class="container py-4">
         <div class="dashboard-header">
            <h2>Branch Overview</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
        </div>

        <div class="row g-4 mb-4">
             <div class="col-lg-4 col-md-6">
                <a href="#pending" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="icon-circle" style="background-color: #ffc107;"><i class="bi bi-person-plus-fill"></i></div>
                            <div>
                                <div class="stat-text">New Registrations</div>
                                <div class="stat-number"><?php echo $pending_registrations_count; ?></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                 <a href="#approved" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="icon-circle" style="background-color: #198754;"><i class="bi bi-person-check-fill"></i></div>
                            <div>
                                <div class="stat-text">Approved Students</div>
                                <div class="stat-number"><?php echo $approved_students_count; ?></div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="add_student.php" class="text-decoration-none">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="icon-circle" style="background-color: #0dcaf0;"><i class="bi bi-pencil-square"></i></div>
                            <div>
                                <div class="stat-text">Add New Student</div>
                                <div class="stat-number">Manual Entry</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <section id="pending" class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">Pending Online Registrations (<?php echo count($pending_registrations); ?>)</h4>
            </div>
            <div class="card-body">
                <?php if (empty($pending_registrations)): ?>
                    <p class="text-center text-muted">No new online registrations to review.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Name</th><th>ID No.</th><th>Phone</th><th>Appt. Date</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pending_registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['id_number']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['appointment_date']); ?></td>
                                        <td><a href="approve_registration.php?id=<?php echo htmlspecialchars($reg['id']); ?>" class="btn btn-sm btn-primary">Review & Approve</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="approved" class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Recently Approved Students</h4>
            </div>
            <div class="card-body">
                <?php if (empty($approved_registrations)): ?>
                    <p class="text-center text-muted">No approved students for this branch yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Reg. Code</th><th>Name</th><th>Phone</th><th>Approved On</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($approved_registrations as $reg): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($reg['registration_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($reg['approval_date']))); ?></td>
                                        <td><a href="view_edit_registration.php?id=<?php echo htmlspecialchars($reg['id']); ?>" class="btn btn-sm btn-outline-secondary">View Details</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                 <div class="text-center mt-3">
                    <a href="search_registrations.php" class="btn btn-secondary">View All Students</a>
                </div>
            </div>
        </section>
        
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