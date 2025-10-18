<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'branch_staff') { header("Location: login.php"); exit(); }
if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: add_student.php"); exit(); }

require_once 'config.php';

// Define the upload directory relative to this script's location
define('UPLOAD_DIR', 'uploads/student_photos/'); // Make sure this directory exists and is writable!

// Ensure the upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0777, true)) {
        $_SESSION['add_student_message'] = "Error: Could not create upload directory.";
        $_SESSION['add_student_message_type'] = 'danger';
        header("Location: add_student.php");
        exit();
    }
}

/**
 * Generates a new, unique, and sequential registration code for a student
 * using a database transaction to prevent race conditions.
 */
function generateRegistrationCode($conn, $branch_name) {
    $conn->begin_transaction();
    try {
        $prefix = strtoupper(substr($branch_name, 0, 3));
        $search_pattern = $prefix . '%';

        // Lock the relevant rows to prevent race conditions
        $stmt = $conn->prepare("SELECT registration_code FROM registrations WHERE branch = ? AND registration_code LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        if (!$stmt) { throw new Exception("Database prepare failed: " . $conn->error); }

        $stmt->bind_param("ss", $branch_name, $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $latest_code = $result->fetch_assoc();
        $stmt->close();

        $new_suffix_num = 1;
        if ($latest_code) {
            $last_num = (int) substr($latest_code['registration_code'], strlen($prefix));
            $new_suffix_num = $last_num + 1;
        }

        $registration_code = $prefix . sprintf("%05d", $new_suffix_num);

        // Commit transaction and release lock
        $conn->commit();
        return $registration_code;

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
}


$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Database connection failed."); }

function nullify_if_empty($value) { return !empty(trim($value)) ? trim($value) : null; }

// --- Generate Unique Registration Code ---
try {
    $registration_code = generateRegistrationCode($conn, $_POST['branch']);
} catch (Exception $e) {
    $_SESSION['add_student_message'] = "Error generating registration code: " . $e->getMessage();
    $_SESSION['add_student_message_type'] = 'danger';
    header("Location: add_student.php");
    exit();
}

// --- Handle Profile Picture Upload ---
$profile_picture_path = null;
$upload_error_message = null; // To store potential upload errors

// **PRIORITIZE FILE UPLOAD**
if (isset($_FILES['profile_picture_file']) && $_FILES['profile_picture_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_picture_file']['tmp_name'];
    $fileName = $_FILES['profile_picture_file']['name'];
    $fileSize = $_FILES['profile_picture_file']['size'];
    $fileType = $_FILES['profile_picture_file']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    // Sanitize filename (optional but recommended)
    $newFileName = $registration_code . '_' . time() . '.' . $fileExtension;
    $allowedfileExtensions = ['jpg', 'jpeg', 'gif', 'png'];

    if (in_array($fileExtension, $allowedfileExtensions)) {
        $dest_path = UPLOAD_DIR . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            $profile_picture_path = $dest_path;
        } else {
            $upload_error_message = 'Error moving uploaded file. Check server permissions.';
            error_log("Failed to move uploaded profile picture: " . $newFileName);
        }
    } else {
        $upload_error_message = 'Upload failed. Allowed file types: ' . implode(', ', $allowedfileExtensions);
    }
// **FALLBACK TO CAMERA CAPTURE (Base64)**
} elseif (isset($_POST['profile_picture']) && !empty($_POST['profile_picture'])) {
    $img_data = base64_decode($_POST['profile_picture']);
    if ($img_data === false) {
        $upload_error_message = "Error decoding captured profile picture data.";
    } else {
        // Generate a filename based on registration code and timestamp
        $filename = $registration_code . '_' . time() . '.jpg'; // Assume JPEG from camera capture
        $file_path = UPLOAD_DIR . $filename;

        // Save the image file
        if (file_put_contents($file_path, $img_data)) {
            $profile_picture_path = $file_path; // Store the relative path
        } else {
            $upload_error_message = 'Failed to save captured profile picture. Check server permissions.';
            error_log("Failed to save captured profile picture for registration code: " . $registration_code);
        }
    }
}

// Handle upload errors - Add warning message but continue registration
if ($upload_error_message) {
     if (!isset($_SESSION['add_student_message'])) { // Don't overwrite existing success/error messages
         $_SESSION['add_student_message'] = "Student registered, but encountered an issue with the profile picture: " . $upload_error_message;
         $_SESSION['add_student_message_type'] = 'warning';
     }
}


// --- Dynamic Builder for INSERT ---
$field_map = [
    // Personal Info
    'full_name' => ['s', 'full_name'], 'name_with_initials' => ['s', 'name_with_initials'], 'id_number' => ['s', 'id_number'],
    'phone_number' => ['s', 'phone_number'], 'address' => ['s', 'address'], 'dob' => ['s', 'dob'], 'age' => ['i', 'age'],
    'sex' => ['s', 'sex'], 'customer_email' => ['s', 'customer_email'], 'branch' => ['s', 'branch'],

    // Course & Payment Details
    'license_category' => ['s', 'license_category'],
    'appointment_date' => ['s', 'appointment_date'],
    'required_training_days' => ['i', 'required_training_days'], 'invoice_no' => ['s', 'invoice_no'],
    'total_amount' => ['d', 'total_amount'], 'advance_paid' => ['d', 'advance_paid'], 'balance_1' => ['d', 'balance_1'],

    // Other fields from view_edit_registration.php that might be needed
    'exam_date_branch' => ['s', 'exam_date_branch'],
    'book_release_date' => ['s', 'book_release_date'], 'appointment_gov_date' => ['s', 'appointment_gov_date'],
    'appointment_change_date' => ['s', 'appointment_change_date'], 'appointment_change_date_2' => ['s', 'appointment_change_date_2'],
    'medical_b_date' => ['s', 'medical_b_date'], 'medical_fee' => ['d', 'medical_fee'], 'medical_paid_date' => ['s', 'medical_paid_date'],
    'remedical_b_date_2' => ['s', 'remedical_b_date_2'], 'remedical_ref_no_2' => ['s', 'remedical_ref_no_2'], 'remedical_fee_2' => ['d', 'remedical_fee_2'],
    'remedical_b_date_3' => ['s', 'remedical_b_date_3'], 'remedical_ref_no_3' => ['s', 'remedical_ref_no_3'], 'remedical_fee_3' => ['d', 'remedical_fee_3'],
    'invoice_date' => ['s', 'invoice_date'],
    'repayment_date' => ['s', 'repayment_date'], 'repayment_amount' => ['d', 'repayment_amount'], 're_balance_2' => ['d', 're_balance_2'],
    'repayment_date_3' => ['s', 'repayment_date_3'], 'repayment_amount_3' => ['d', 'repayment_amount_3'], 're_balance_3' => ['d', 're_balance_3'],
    'repayment_date_4' => ['s', 'repayment_date_4'], 'repayment_amount_4' => ['d', 'repayment_amount_4'], 're_balance_4' => ['d', 're_balance_4'],
    'trial_1_date' => ['s', 'trial_1_date'], 'trial_1_result' => ['s', 'trial_1_result'], 'trial_1_notes' => ['s', 'trial_1_notes'],
    'trial_2_date' => ['s', 'trial_2_date'], 'trial_2_result' => ['s', 'trial_2_result'], 'trial_2_notes' => ['s', 'trial_2_notes'],
    'temp_license_issue_date' => ['s', 'temp_license_issue_date'],
    'permanent_license_status' => ['s', 'permanent_license_status'],
    'permanent_license_issued_date' => ['s', 'permanent_license_issued_date']
];

$columns = [];
$placeholders = [];
$params = [];
$types = '';

foreach ($field_map as $form_name => $db_info) {
    // Only add fields that were actually submitted in the form
    if (isset($_POST[$form_name])) {
        $columns[] = $db_info[1]; // database column name
        $placeholders[] = '?';
        $types .= $db_info[0]; // data type
        $params[] = nullify_if_empty($_POST[$form_name]);
    }
}

$checkboxes = ['medical_done', 'full_payment_paid', 'payment_done'];
foreach ($checkboxes as $cb) {
    $columns[] = $cb;
    $placeholders[] = '?';
    $types .= 'i';
    $params[] = isset($_POST[$cb]) ? 1 : 0;
}

// Add mandatory/auto-generated fields AND the new profile picture path
array_push($columns, "registration_code", "status", "approval_date", "created_by_user_id", "last_edited_by_user_id", "profile_picture_path"); // Added profile_picture_path
array_push($placeholders, "?", "?", "NOW()", "?", "?", "?"); // Added placeholder for path
$types .= 'ssiis'; // Added 's' for the path string
array_push($params, $registration_code, 'approved', $_SESSION['user_id'], $_SESSION['user_id'], $profile_picture_path); // Added the path variable

// Assemble the final query
$sql = "INSERT INTO registrations (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    // If prepare fails, attempt to delete the uploaded image if it exists
    if ($profile_picture_path && file_exists($profile_picture_path)) {
        unlink($profile_picture_path);
    }
    $_SESSION['add_student_message'] = "Database Error: Could not prepare statement. " . $conn->error;
    $_SESSION['add_student_message_type'] = 'danger';
    header("Location: add_student.php");
    exit();
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Check if a warning was set earlier about the image upload
    if (!isset($_SESSION['add_student_message_type']) || $_SESSION['add_student_message_type'] !== 'warning') {
         $_SESSION['add_student_message'] = "Student '" . htmlspecialchars($_POST['full_name']) . "' registered successfully with code: " . htmlspecialchars($registration_code);
         $_SESSION['add_student_message_type'] = 'success';
    }
} else {
    // If insert fails, attempt to delete the uploaded image if it exists
    if ($profile_picture_path && file_exists($profile_picture_path)) {
        unlink($profile_picture_path);
    }
    $_SESSION['add_student_message'] = "Error registering student: " . $stmt->error;
    $_SESSION['add_student_message_type'] = 'danger';
}

$stmt->close();
$conn->close();
header("Location: add_student.php");
exit();
?>