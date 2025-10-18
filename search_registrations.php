<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$search_results = [];
$error_message = '';
$is_admin = ($_SESSION['role'] === 'admin');

$license_categories_filter = [];
$cat_result = $conn->query("SELECT category_name FROM license_categories ORDER BY category_name");
if ($cat_result) { while ($row = $cat_result->fetch_assoc()) { $license_categories_filter[] = $row['category_name']; } }

// Handle search query
if (isset($_GET['search'])) {
    $param_types = '';
    $param_values = [];
    
    $query = "SELECT r.id, r.registration_code, r.full_name, r.id_number, r.phone_number, r.branch, r.status, r.payment_done, r.permanent_license_status FROM registrations r WHERE 1=1";

    if (!$is_admin) {
        $query .= " AND r.branch = ?";
        $param_types .= "s";
        $param_values[] = $_SESSION['branch_name'];
    }

    if (!empty($_GET['q'])) {
        $searchTerm = "%" . $_GET['q'] . "%";
        $query .= " AND (r.registration_code LIKE ? OR r.full_name LIKE ? OR r.id_number LIKE ? OR r.phone_number LIKE ?)";
        $param_types .= "ssss";
        array_push($param_values, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $query .= " AND r.registration_date BETWEEN ? AND ?";
        $param_types .= "ss";
        $param_values[] = $_GET['start_date'];
        $param_values[] = $_GET['end_date'];
    }
    if (isset($_GET['payment_done']) && $_GET['payment_done'] !== '') {
        $query .= " AND r.payment_done = ?";
        $param_types .= "i";
        $param_values[] = $_GET['payment_done'];
    }
    if (!empty($_GET['permanent_license_status'])) {
        $query .= " AND r.permanent_license_status = ?";
        $param_types .= "s";
        $param_values[] = $_GET['permanent_license_status'];
    }
    if (isset($_GET['temp_license_expiring']) && $_GET['temp_license_expiring'] === '1') {
        $query .= " AND r.permanent_license_status != 'Issued' AND r.trial_1_result = 'Pass' AND r.temp_license_issue_date <= DATE_SUB(CURDATE(), INTERVAL 10 MONTH)";
    }
    if ($is_admin && !empty($_GET['branch'])) { $query .= " AND r.branch = ?"; $param_types .= "s"; $param_values[] = $_GET['branch']; }
    if (!empty($_GET['license_category'])) { $query .= " AND r.license_category = ?"; $param_types .= "s"; $param_values[] = $_GET['license_category']; }
    if (!empty($_GET['status'])) { $query .= " AND r.status = ?"; $param_types .= "s"; $param_values[] = $_GET['status']; }
    
    $query .= " ORDER BY r.registration_date DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($param_values)) {
            $stmt->bind_param($param_types, ...$param_values);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $search_results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error_message = "Error preparing the search query.";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Students - Sriyani Driving School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top"></nav>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="dashboard-header">
                <h2>Student Search</h2>
                <p class="text-muted">Find student records using the advanced filters below.</p>
            </div>
            <div>
                <button id="exportBtn" class="btn btn-success" <?php if (!isset($_GET['search'])) echo 'disabled'; ?>>
                    <i class="bi bi-file-earmark-excel-fill"></i> Export to Excel
                </button>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="search_registrations.php" method="GET" id="searchForm">
                    <input type="hidden" name="search" value="1">
                    <div class="input-group mb-3">
                        <input type="text" name="q" class="form-control form-control-lg" placeholder="Search by Reg. Code, Name, ID, or Phone..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Registration Date Range</label><div class="input-group"><input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>"><input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>"></div></div>
                        <div class="col-md-2"><label class="form-label">Payment Status</label><select name="payment_done" class="form-select"><option value="">Any</option><option value="1" <?php echo (isset($_GET['payment_done']) && $_GET['payment_done'] === '1') ? 'selected' : ''; ?>>Completed</option><option value="0" <?php echo (isset($_GET['payment_done']) && $_GET['payment_done'] === '0') ? 'selected' : ''; ?>>Not Completed</option></select></div>
                        <div class="col-md-3"><label class="form-label">Permanent License</label><select name="permanent_license_status" class="form-select"><option value="">Any Status</option><option value="Not Applicable" <?php echo (($_GET['permanent_license_status'] ?? '') == 'Not Applicable') ? 'selected' : ''; ?>>Not Applicable</option><option value="Eligible" <?php echo (($_GET['permanent_license_status'] ?? '') == 'Eligible') ? 'selected' : ''; ?>>Eligible</option><option value="Issued" <?php echo (($_GET['permanent_license_status'] ?? '') == 'Issued') ? 'selected' : ''; ?>>Issued</option></select></div>
                        <div class="col-md-3"><label class="form-label">Temp License Status</label><select name="temp_license_expiring" class="form-select"><option value="">Any</option><option value="1" <?php echo (($_GET['temp_license_expiring'] ?? '') == '1') ? 'selected' : ''; ?>>Expiring (Over 10 months)</option></select></div>
                    </div>
                     <div class="row g-3 mt-1">
                        <div class="col-md-3"><label class="form-label">Branch</label><select name="branch" class="form-select" <?php if (!$is_admin) echo 'disabled'; ?>><option value="">All Branches</option><option value="Angunukolapalassa" <?php echo (($_GET['branch'] ?? '') == 'Angunukolapalassa') ? 'selected' : ''; ?>>Angunukolapalassa</option><option value="Ambalantota" <?php echo (($_GET['branch'] ?? '') == 'Ambalantota') ? 'selected' : ''; ?>>Ambalantota</option><option value="Ranna" <?php echo (($_GET['branch'] ?? '') == 'Ranna') ? 'selected' : ''; ?>>Ranna</option></select></div>
                        <div class="col-md-3"><label class="form-label">Category</label><select name="license_category" class="form-select"><option value="">Any Category</option><?php foreach ($license_categories_filter as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (($_GET['license_category'] ?? '') == $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label">Reg. Status</label><select name="status" class="form-select"><option value="">Any Status</option><option value="pending" <?php echo (($_GET['status'] ?? '') == 'pending') ? 'selected' : ''; ?>>Pending</option><option value="approved" <?php echo (($_GET['status'] ?? '') == 'approved') ? 'selected' : ''; ?>>Approved</option><option value="rejected" <?php echo (($_GET['status'] ?? '') == 'rejected') ? 'selected' : ''; ?>>Rejected</option></select></div>
                        <div class="col-md-3 align-self-end"><a href="search_registrations.php" class="btn btn-outline-secondary w-100">Clear Filters</a></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Search Results (<?php echo count($search_results); ?> found)</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Reg. Code</th><th>Full Name</th><th>Phone</th><?php if ($is_admin) echo '<th>Branch</th>'; ?><th>Payment</th><th>License Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($search_results) && isset($_GET['search'])): ?>
                                <tr><td colspan="<?php echo $is_admin ? '7' : '6'; ?>" class="text-center text-muted">No records found.</td></tr>
                            <?php elseif (empty($search_results)): ?>
                                 <tr><td colspan="<?php echo $is_admin ? '7' : '6'; ?>" class="text-center text-muted">Use the form above to search for students.</td></tr>
                            <?php else: ?>
                                <?php foreach ($search_results as $reg): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($reg['registration_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($reg['phone_number']); ?></td>
                                    <?php if ($is_admin) echo '<td>' . htmlspecialchars($reg['branch']) . '</td>'; ?>
                                    <td><?php echo $reg['payment_done'] ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-warning">Pending</span>'; ?></td>
                                    <td><?php echo '<span class="badge bg-info">' . htmlspecialchars($reg['permanent_license_status']) . '</span>'; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_edit_registration.php?id=<?php echo $reg['id']; ?>" class="btn btn-sm btn-outline-primary" title="View/Edit Details"><i class="bi bi-pencil-fill"></i> View</a>
                                            <a href="print_invoice.php?id=<?php echo $reg['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Invoice"><i class="bi bi-printer-fill"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('exportBtn').addEventListener('click', function() {
            // Get the current form's action and parameters
            const form = document.getElementById('searchForm');
            const params = new URLSearchParams(new FormData(form)).toString();
            
            // Open the export script in a new window/tab with the same parameters
            window.location.href = 'export_students.php?' + params;
        });
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