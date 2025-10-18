<?php
session_start();

//
// PURPOSE: This file acts as a central router for logged-in users.
// It checks the user's role from the session and redirects them to their specific dashboard.
// Users should never see a blank page here; they should be instantly redirected.
//

// Step 1: Security Check - Is anyone logged in?
// If the 'user_id' is not set in the session, it means the user is not logged in.
// Send them back to the login page immediately.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit(); // Stop the script from running further.
}

// Step 2: Role-based Redirection - Who is logged in?
// If the user is logged in, check their 'role' value.
if (isset($_SESSION['role'])) {
    
    // Use a switch statement to handle different roles.
    switch ($_SESSION['role']) {
        
        // If the role is 'admin', send them to the admin dashboard.
        case 'admin':
            header("Location: admin_dashboard.php");
            exit(); // Stop the script.
        
        // If the role is 'branch_staff', send them to the branch staff dashboard.
        case 'branch_staff':
            header("Location: branch_dashboard.php");
            exit(); // Stop the script.
            
        // If the role is 'instructor', send them to the instructor dashboard.
        case 'instructor':
            header("Location: instructor_dashboard.php");
            exit(); // Stop the script.
            
        // Default case for security: If the role is something else (which shouldn't happen),
        // log them out and send them to the login page with an error.
        default:
            session_destroy();
            header("Location: login.php?error=unauthorized_role");
            exit(); // Stop the script.
    }
}

// Step 3: Fallback Security
// If the user is logged in but their 'role' is not set in the session for some reason,
// it's a potential error. For security, log them out and send them to the login page.
session_destroy();
header("Location: login.php?error=session_error");
exit(); // Stop the script.

?>