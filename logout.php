<?php
session_start();

// Unset all of the session variables.
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page with a status for better user experience.
header("Location: login.php?status=loggedout");
exit();
?>```

---

### File 7 of 27: `dashboard.php`

**Purpose:** This file acts as a central router. After a user successfully logs in, they are sent here. The script immediately checks their role (`admin`, `branch_staff`, or the new `instructor` role) and redirects them to the correct dashboard.

```php
<?php
session_start();

// Check if user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirect based on user role
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin_dashboard.php");
            exit();
        case 'branch_staff':
            header("Location: branch_dashboard.php");
            exit();
        case 'instructor':
            header("Location: instructor_dashboard.php");
            exit();
        default:
            // If role is unknown, log them out for security
            session_destroy();
            header("Location: login.php?error=unauthorized_role");
            exit();
    }
}

// Fallback: If role is not set, log out the user.
session_destroy();
header("Location: login.php?error=session_error");
exit();
?>