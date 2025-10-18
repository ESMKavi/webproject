<?php
// This check prevents direct access to this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_branch_staff = isset($_SESSION['role']) && $_SESSION['role'] === 'branch_staff';
$is_instructor = isset($_SESSION['role']) && $_SESSION['role'] === 'instructor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <?php if (isset($page_title)): ?>
        <title><?php echo htmlspecialchars($page_title); ?> - Sriyani DS</title>
    <?php else: ?>
        <title>Sriyani Driving School</title>
    <?php endif; ?>
</head>
<body class="dashboard-page">
    <header class="site-header sticky-top">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">
                    <img src="images/logo.png" alt="Logo" style="height: 500px; margin-right: 20px;">
                    Sriyani Driving School System
                </a>
                
                    <ul class="navbar-nav ms-auto">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="site-main py-4">
        <div class="container">