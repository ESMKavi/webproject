<?php
session_start();
// Security Check: Only admins can access this page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// !!! IMPORTANT: CONFIGURE YOUR DATABASE CREDENTIALS HERE !!!
// Include the database configuration file
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$message = '';
$message_type = '';

// Handle form submission to create a new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $branch_name = $_POST['branch_name'];

    if (empty($username) || empty($password) || empty($full_name) || empty($role) || ($role !== 'admin' && empty($branch_name))) {
        $message = "Please fill all required fields.";
        $message_type = 'danger';
    } else {
        // Hash the password securely
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, branch_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $password_hash, $full_name, $role, $branch_name);
        
        if ($stmt->execute()) {
            $message = "User '{$username}' created successfully.";
            $message_type = 'success';
            
            // If the role is instructor, also create a record in the instructors table
            if ($role === 'instructor') {
                $user_id = $stmt->insert_id;
                $inst_stmt = $conn->prepare("INSERT INTO instructors (user_id, full_name, branch_name, phone_number, license_number) VALUES (?, ?, ?, 'default', 'default')");
                $inst_stmt->bind_param("iss", $user_id, $full_name, $branch_name);
                $inst_stmt->execute();
                $inst_stmt->close();
            }
        } else {
            $message = "Error creating user. The username might already exist.";
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// Fetch all existing users to display in the table
$users = $conn->query("SELECT id, username, full_name, role, branch_name, created_at FROM users ORDER BY role, username")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <!-- Assets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <!-- ... (copy the navbar from your admin dashboard) ... -->
    </nav>

    <main class="container py-4">
        <div class="dashboard-header">
            <h2>User Management</h2>
            <p class="text-muted">Create new users and manage existing system access.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Create User Form -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header"><h5><i class="bi bi-person-plus-fill"></i> Create New User</h5></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="create_user" value="1">
                            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Role</label><select name="role" id="roleSelect" class="form-select" required onchange="toggleBranchSelect()"><option value="branch_staff">Branch Staff</option><option value="instructor">Instructor</option><option value="admin">Admin</option></select></div>
                            <div class="mb-3" id="branchSelectDiv"><label class="form-label">Branch</label><select name="branch_name" class="form-select"><option value="Angunukolapalassa">Angunukolapalassa</option><option value="Ambalantota">Ambalantota</option><option value="Ranna">Ranna</option></select></div>
                            <div class="d-grid"><button type="submit" class="btn btn-primary">Create User</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Existing Users Table -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header"><h5><i class="bi bi-people-fill"></i> Existing Users</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Branch</th></tr></thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></td>
                                        <td><?php echo $user['role'] !== 'admin' ? htmlspecialchars($user['branch_name']) : 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleBranchSelect() {
            const role = document.getElementById('roleSelect').value;
            const branchDiv = document.getElementById('branchSelectDiv');
            branchDiv.style.display = (role === 'admin') ? 'none' : 'block';
        }
        document.addEventListener('DOMContentLoaded', toggleBranchSelect);
    </script>
</body>
</html>