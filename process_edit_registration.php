<?php
session_start();
// Security & Access Checks
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) { header("Location: login.php"); exit(); }
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['registration_id'])) { header("Location: dashboard.php"); exit(); }

require_once 'config.php';

// Enable error reporting for debugging ONLY in development environment
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Database connection failed."); }

function nullify_if_empty($value) {
    $trimmed_value = trim($value);
    return !empty($trimmed_value) ? $trimmed_value : null;
}

// --- Automation Logic ---
// 1. If Trial 1 is being marked as 'Pass' and there's a date, set the temp license issue date.
if (isset($_POST['trial_1_result']) && $_POST['trial_1_result'] === 'Pass' && !empty($_POST['trial_1_date'])) {
    $check_stmt = $conn->prepare("SELECT temp_license_issue_date FROM registrations WHERE id = ?");
    $check_stmt->bind_param("i", $_POST['registration_id']);
    $check_stmt->execute();
    $current_temp_date_result = $check_stmt->get_result();
    $current_temp_date_row = $current_temp_date_result->fetch_assoc();
    $current_temp_date = $current_temp_date_row ? $current_temp_date_row['temp_license_issue_date'] : null;
    $check_stmt->close();
    if (empty($current_temp_date)) {
        $_POST['temp_license_issue_date'] = $_POST['trial_1_date'];
    }
}

// 2. If Trial 2 is being marked as 'Pass', set the permanent license status to 'Eligible'.
if (isset($_POST['trial_2_result']) && $_POST['trial_2_result'] === 'Pass') {
     $check_status_stmt = $conn->prepare("SELECT permanent_license_status FROM registrations WHERE id = ?");
     $check_status_stmt->bind_param("i", $_POST['registration_id']);
     $check_status_stmt->execute();
     $current_status_result = $check_status_stmt->get_result();
     $current_status_row = $current_status_result->fetch_assoc();
     $current_perm_status = $current_status_row ? $current_status_row['permanent_license_status'] : 'Not Applicable';
     $check_status_stmt->close();
     if ($current_perm_status !== 'Issued') {
        $_POST['permanent_license_status'] = 'Eligible';
     } else {
         unset($_POST['permanent_license_status']);
     }
}

// --- Dynamic Builder Method ---
$field_map = [ /* ... Keep your full $field_map here ... */
    // Personal Tab
    'full_name' => ['s', 'full_name'], 'name_with_initials' => ['s', 'name_with_initials'], 'id_number' => ['s', 'id_number'],
    'phone_number' => ['s', 'phone_number'], 'address' => ['s', 'address'], 'dob' => ['s', 'dob'], 'age' => ['i', 'age'],
    'sex' => ['s', 'sex'], 'customer_email' => ['s', 'customer_email'],
    // Appointments Tab
    'branch' => ['s', 'branch'], 'license_category' => ['s', 'license_category'], 'appointment_date' => ['s', 'appointment_date'],
    'exam_date_branch' => ['s', 'exam_date_branch'], 'book_release_date' => ['s', 'book_release_date'],
    'appointment_gov_date' => ['s', 'appointment_gov_date'], 'appointment_change_date' => ['s', 'appointment_change_date'], 'appointment_change_date_2' => ['s', 'appointment_change_date_2'],
    // Medical Tab
    'medical_b_date' => ['s', 'medical_b_date'], 'medical_fee' => ['d', 'medical_fee'], 'medical_paid_date' => ['s', 'medical_paid_date'],
    'remedical_b_date_2' => ['s', 'remedical_b_date_2'], 'remedical_ref_no_2' => ['s', 'remedical_ref_no_2'], 'remedical_fee_2' => ['d', 'remedical_fee_2'],
    'remedical_b_date_3' => ['s', 'remedical_b_date_3'], 'remedical_ref_no_3' => ['s', 'remedical_ref_no_3'], 'remedical_fee_3' => ['d', 'remedical_fee_3'],
    // Payment Tab
    'invoice_date' => ['s', 'invoice_date'], 'invoice_no' => ['s', 'invoice_no'], 'total_amount' => ['d', 'total_amount'], 'advance_paid' => ['d', 'advance_paid'],
    'repayment_date' => ['s', 'repayment_date'], 'repayment_amount' => ['d', 'repayment_amount'], 'repayment_date_3' => ['s', 'repayment_date_3'], 'repayment_amount_3' => ['d', 'repayment_amount_3'], 'repayment_date_4' => ['s', 'repayment_date_4'], 'repayment_amount_4' => ['d', 'repayment_amount_4'],
    // Trials & Training Tab
    'trial_1_date' => ['s', 'trial_1_date'], 'trial_1_result' => ['s', 'trial_1_result'], 'trial_1_notes' => ['s', 'trial_1_notes'],
    'trial_2_date' => ['s', 'trial_2_date'], 'trial_2_result' => ['s', 'trial_2_result'], 'trial_2_notes' => ['s', 'trial_2_notes'], 'required_training_days' => ['i', 'required_training_days'],
    // License Tab
    'temp_license_issue_date' => ['s', 'temp_license_issue_date'], 'permanent_license_status' => ['s', 'permanent_license_status'], 'permanent_license_issued_date' => ['s', 'permanent_license_issued_date']
];

$sql_set_parts = [];
$params = [];
$types = ''; // **** ENSURE $types IS INITIALIZED AS AN EMPTY STRING ****

// Loop through editable fields submitted via POST
foreach ($field_map as $form_name => $db_info) {
    if (isset($_POST[$form_name])) {
         // Exclude calculated balance fields
         if (!in_array($form_name, ['balance_1', 're_balance_2', 're_balance_3', 're_balance_4'])) {
            $sql_set_parts[] = $db_info[1] . " = ?";
            $types .= $db_info[0];
            $params[] = nullify_if_empty($_POST[$form_name]);
         }
    }
}

// Handle checkboxes separately
$checkboxes = ['medical_done', 'full_payment_paid', 'payment_done'];
foreach ($checkboxes as $cb) {
    $isChecked = isset($_POST[$cb]) && $_POST[$cb] == '1';
    $sql_set_parts[] = $cb . " = ?";
    $types .= 'i';
    $params[] = $isChecked ? 1 : 0;
}

// Always update the last edited user and timestamp
$sql_set_parts[] = "last_edited_by_user_id = ?";
$types .= 'i';
$params[] = $_SESSION['user_id'];
$sql_set_parts[] = "last_edited_at = NOW()";

// Check if there's anything meaningful to update
$meaningfulUpdateDetected = false;
foreach ($field_map as $form_name => $db_info) {
    if (isset($_POST[$form_name]) && !in_array($form_name, ['balance_1', 're_balance_2', 're_balance_3', 're_balance_4'])) {
        $meaningfulUpdateDetected = true; break;
    }
}
if (!$meaningfulUpdateDetected) {
    foreach ($checkboxes as $cb){ if (isset($_POST[$cb]) || !isset($_POST[$cb])) { $meaningfulUpdateDetected = true; break; } }
}

if (!$meaningfulUpdateDetected && count($sql_set_parts) <= 3) {
    $_SESSION['edit_student_message'] = "No changes detected to save.";
    $_SESSION['edit_student_message_type'] = 'info';
    header("Location: view_edit_registration.php?id=" . $_POST['registration_id']);
    exit();
}

// Construct the SQL query
$sql = "UPDATE registrations SET " . implode(', ', $sql_set_parts) . " WHERE id = ?";
$types .= 'i'; // Add type for the WHERE clause ID
$params[] = $_POST['registration_id']; // Add the ID for the WHERE clause

$stmt = $conn->prepare($sql);
if ($stmt === false) {
     $_SESSION['edit_student_message'] = "Error preparing statement: " . $conn->error;
     $_SESSION['edit_student_message_type'] = 'danger';
     error_log("SQL Prepare Error: " . $conn->error . " | SQL: " . $sql);
     header("Location: view_edit_registration.php?id=" . $_POST['registration_id']);
     exit();
}

// *** ADDED Debugging and Stricter Check ***
error_log("DEBUG process_edit_registration: BEFORE BIND: types = '" . var_export($types, true) . "' | params count = " . count($params) . " | sql_set_parts count = " . count($sql_set_parts));

if (!is_string($types) || $types === '') {
     $_SESSION['edit_student_message'] = "Internal Error: Parameter types string is invalid or empty before binding.";
     $_SESSION['edit_student_message_type'] = 'danger';
     error_log("CRITICAL Bind Param Error: types variable is not a valid string or is empty ('" . var_export($types, true) . "'). SQL: " . $sql);
     $stmt->close(); // Close statement before redirecting
     $conn->close();
     header("Location: view_edit_registration.php?id=" . $_POST['registration_id']);
     exit();
}

// Dynamically bind parameters only if there are parameters to bind
if (count($params) > 0) {
    // Check if number of types matches number of params BEFORE binding
    if (strlen($types) !== count($params)) {
        $_SESSION['edit_student_message'] = "Internal Error: Mismatch between parameter types and values.";
        $_SESSION['edit_student_message_type'] = 'danger';
        error_log("CRITICAL Bind Param Error: Mismatch Count. Types length: " . strlen($types) . " | Params count: " . count($params) . " | Types: " . $types);
        $stmt->close();
        $conn->close();
        header("Location: view_edit_registration.php?id=" . $_POST['registration_id']);
        exit();
    }

    // Attempt to bind
    if (!$stmt->bind_param($types, ...$params)) {
         $_SESSION['edit_student_message'] = "Error binding parameters: " . $stmt->error;
         $_SESSION['edit_student_message_type'] = 'danger';
         error_log("Bind Param Error: " . $stmt->error . " | Types: " . $types . " | Params Count: " . count($params));
         $stmt->close();
         $conn->close();
         header("Location: view_edit_registration.php?id=" . $_POST['registration_id']);
         exit();
    }
} else {
     // This case should not happen due to the meaningfulUpdateDetected check
     $_SESSION['edit_student_message'] = "Internal Error: No parameters to bind.";
     $_SESSION['edit_student_message_type'] = 'danger';
     error_log("Bind Param Error: No parameters found for SQL: " . $sql);
     $stmt->close();
     $conn->close();
     header("Location: view_edit_registration.php?id=" . $_POST['registration_id']);
     exit();
}


// Execute the statement
if ($stmt->execute()) {
    $_SESSION['edit_student_message'] = "Registration record updated successfully!";
    $_SESSION['edit_student_message_type'] = 'success';
} else {
    $_SESSION['edit_student_message'] = "Error updating record: " . $stmt->error;
    $_SESSION['edit_student_message_type'] = 'danger';
    error_log("SQL Execute Error: " . $stmt->error . " | SQL: " . $sql);
}

$stmt->close();
$conn->close();

header("Location: view_edit_registration.php?id=" . $_POST['registration_id']);
exit();
?>