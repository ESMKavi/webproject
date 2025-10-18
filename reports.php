<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    header("Location: login.php");
    exit();
}

// Include the database configuration file
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed."); }

$is_admin = $_SESSION['role'] === 'admin';

// --- THIS IS THE CORRECTED LOGIC ---
// Safely get variables from the URL, providing a default value if they don't exist.
$branch_filter = $is_admin ? ($_GET['branch'] ?? 'All') : $_SESSION['branch_name'];
$report_type = $_GET['report_type'] ?? 'trial_exam'; // Default to 'trial_exam' report
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
// --- END OF CORRECTION ---

$results = [];
$report_title = '';

// --- Report Logic (No changes here, it's already correct) ---
if (isset($_GET['generate'])) {
    $params = [];
    $types = "";

    if ($report_type === 'trial_exam') {
        $report_title = "Trial Exam Report";
        $sql = "SELECT registration_code, full_name, phone_number, branch, trial_1_date, trial_1_result, trial_2_date, trial_2_result 
                FROM registrations 
                WHERE (trial_1_result != 'Pending' OR trial_2_result != 'Pending')";
        
        if ($branch_filter !== 'All') { $sql .= " AND branch = ?"; $params[] = $branch_filter; $types .= "s"; }
        
    } elseif ($report_type === 'session_attendance' && isset($_GET['session_id'])) {
        $session_id = $_GET['session_id'];
        $session_stmt = $conn->prepare("SELECT session_title, session_date FROM group_sessions WHERE id = ?");
        $session_stmt->bind_param("i", $session_id);
        $session_stmt->execute();
        $session = $session_stmt->get_result()->fetch_assoc();
        $report_title = "Attendance: " . ($session['session_title'] ?? '') . " on " . ($session['session_date'] ?? '');
        
        $sql = "SELECT r.full_name, r.id_number, r.registration_code, sa.status, sa.marked_at, u.full_name as marked_by
                FROM session_attendance sa 
                JOIN registrations r ON sa.student_id = r.id
                LEFT JOIN users u ON sa.marked_by_user_id = u.id
                WHERE sa.session_id = ? ORDER BY r.full_name";
        $params = [$session_id];
        $types = "i";
    }

    if (!empty($sql)) {
        $stmt = $conn->prepare($sql);
        if(!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>System Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style> @media print { .no-print { display: none; } } </style>
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top no-print"><!-- Your Navbar --></nav>
    <main class="container py-4">
        <div class="dashboard-header no-print">
            <h2>System Reports</h2>
            <p class="text-muted">Generate and print reports based on system data.</p>
        </div>

        <div class="card shadow-sm mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="generate" value="true">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" id="reportTypeSelect" class="form-select" onchange="toggleDateFilter()">
                            <option value="trial_exam" <?php if($report_type == 'trial_exam') echo 'selected'; ?>>Trial Exam Report</option>
                            <option value="session_attendance" <?php if($report_type == 'session_attendance') echo 'selected'; ?>>Session Attendance</option>
                        </select>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="col-md-3">
                         <label class="form-label">Branch</label>
                         <select name="branch" class="form-select">
                            <option value="All">All Branches</option>
                            <option value="Angunukolapalassa" <?php if($branch_filter == 'Angunukolapalassa') echo 'selected'; ?>>Angunukolapalassa</option>
                            <option value="Ambalantota" <?php if($branch_filter == 'Ambalantota') echo 'selected'; ?>>Ambalantota</option>
                            <option value="Ranna" <?php if($branch_filter == 'Ranna') echo 'selected'; ?>>Ranna</option>
                         </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4" id="dateFilterDiv" style="display:none;">
                        <label>Date Range</label>
                        <div class="input-group">
                           <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                           <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Generate</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_GET['generate'])): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0"><?php echo htmlspecialchars($report_title); ?></h4>
                    <button class="btn btn-secondary btn-sm no-print" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print Report</button>
                </div>
                
                <div class="table-responsive">
                    <?php if (empty($results)): ?>
                        <p class="text-center text-muted">No data found for the selected criteria.</p>
                    <?php elseif ($report_type === 'trial_exam'): ?>
                        <table class="table table-striped table-bordered">
                            <thead><tr><th>Reg. Code</th><th>Student Name</th><?php if($is_admin && $branch_filter === 'All') echo '<th>Branch</th>'; ?><th>Trial 1 Result</th><th>Trial 2 Result</th></tr></thead>
                            <tbody>
                                <?php foreach($results as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['registration_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <?php if($is_admin && $branch_filter === 'All') echo '<td>' . htmlspecialchars($row['branch']) . '</td>'; ?>
                                        <td><span class="badge <?php echo $row['trial_1_result'] == 'Pass' ? 'bg-success' : ($row['trial_1_result'] == 'Fail' ? 'bg-danger' : 'bg-secondary'); ?>"><?php echo htmlspecialchars($row['trial_1_result']); ?></span></td>
                                        <td><span class="badge <?php echo $row['trial_2_result'] == 'Pass' ? 'bg-success' : ($row['trial_2_result'] == 'Fail' ? 'bg-danger' : 'bg-secondary'); ?>"><?php echo htmlspecialchars($row['trial_2_result']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php elseif ($report_type === 'session_attendance'): ?>
                        <table class="table table-striped table-bordered">
                            <thead><tr><th>#</th><th>Student Name</th><th>ID Number</th><th>Reg. Code</th><th>Status</th><th>Marked By</th><th>Time Marked</th></tr></thead>
                            <tbody>
                                <?php $count = 1; foreach($results as $row): ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['registration_code']); ?></td>
                                        <td><span class="badge <?php echo $row['status'] == 'Present' ? 'bg-success' : ($row['status'] == 'Absent' ? 'bg-danger' : 'bg-secondary'); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['marked_by'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['marked_at'] ? date('Y-m-d g:i:s A', strtotime($row['marked_at'])) : 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
             <div class="text-center p-5 bg-light rounded">
                <p class="lead">Please select your desired report type and filters, then click "Generate" to view the data.</p>
            </div>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDateFilter() {
            const reportType = document.getElementById('reportTypeSelect').value;
            const dateFilter = document.getElementById('dateFilterDiv');
            // Hide the filter for session attendance, as it's not needed
            if (reportType === 'finance_summary' || reportType === 'another_date_report') {
                dateFilter.style.display = 'block';
            } else {
                dateFilter.style.display = 'none';
            }
        }
        document.addEventListener('DOMContentLoaded', toggleDateFilter);
    </script>
       <footer class="site-footer bg-dark text-white text-center py-3">
        <div class="container">
            <p class="mb-1"><small>If you have any technical questions, please contact the IT Department.</small></p>
            <p class="mb-0"><small>&copy; <?php echo date('Y'); ?> Sriyani DS-IT Department. All rights reserved.</small></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>