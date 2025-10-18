<?php
session_start();
// Security Check & Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    $_SESSION['edit_student_message'] = 'Unauthorized access.';
    $_SESSION['edit_student_message_type'] = 'danger';
    header("Location: login.php"); // Redirect to login if not authorized
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['registration_id_for_pic'])) {
    $_SESSION['edit_student_message'] = 'Invalid request method or missing ID.';
    $_SESSION['edit_student_message_type'] = 'danger';
     // Redirect back if accessed incorrectly, try referring page or dashboard
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    header("Location: " . $redirect_url);
    exit();
}

require_once 'config.php';

// Define the upload directory
define('UPLOAD_DIR', 'uploads/student_photos/');

$registration_id = filter_var($_POST['registration_id_for_pic'], FILTER_VALIDATE_INT);
$message = '';
$message_type = 'danger'; // Default to error

if (!$registration_id) {
    $message = 'Invalid Registration ID provided.';
    $message_type = 'danger';
} else {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $message = "Database connection failed.";
        $message_type = 'danger';
    } else {
        // --- Get current picture path and registration code ---
        $current_pic_path = null;
        $registration_code = null;
        $stmt_get = $conn->prepare("SELECT profile_picture_path, registration_code FROM registrations WHERE id = ?");
        if ($stmt_get) {
            $stmt_get->bind_param("i", $registration_id);
            $stmt_get->execute();
            $stmt_get->bind_result($current_pic_path, $registration_code);
            $stmt_get->fetch();
            $stmt_get->close();
        } else {
             $message = "Error fetching current student data.";
             $message_type = 'danger';
        }

        if ($registration_code) { // Proceed only if student exists
            $new_profile_picture_path = null;
            $upload_error_message = null;

            // Ensure the upload directory exists
            if (!is_dir(UPLOAD_DIR)) {
                if (!mkdir(UPLOAD_DIR, 0777, true)) {
                    $upload_error_message = "Error: Could not create upload directory.";
                }
            }

            if (!$upload_error_message) {
                 // **PRIORITIZE FILE UPLOAD**
                 if (isset($_FILES['profile_picture_file_modal']) && $_FILES['profile_picture_file_modal']['error'] === UPLOAD_ERR_OK) {
                     $fileTmpPath = $_FILES['profile_picture_file_modal']['tmp_name'];
                     $fileName = $_FILES['profile_picture_file_modal']['name'];
                     $fileNameCmps = explode(".", $fileName);
                     $fileExtension = strtolower(end($fileNameCmps));
                     $newFileName = ($registration_code ?: 'STUDENT') . '_' . time() . '.' . $fileExtension;
                     $allowedfileExtensions = ['jpg', 'jpeg', 'gif', 'png'];

                     if (in_array($fileExtension, $allowedfileExtensions)) {
                         $dest_path = UPLOAD_DIR . $newFileName;
                         if (move_uploaded_file($fileTmpPath, $dest_path)) {
                             $new_profile_picture_path = $dest_path;
                         } else {
                             $upload_error_message = 'Error moving uploaded file. Check server permissions.';
                         }
                     } else {
                         $upload_error_message = 'Upload failed. Allowed file types: ' . implode(', ', $allowedfileExtensions);
                     }
                 // **FALLBACK TO CAMERA CAPTURE (Base64)**
                 } elseif (isset($_POST['profile_picture_modal']) && !empty($_POST['profile_picture_modal'])) {
                     $img_data = base64_decode($_POST['profile_picture_modal']);
                     if ($img_data === false) {
                         $upload_error_message = "Error decoding captured profile picture data.";
                     } else {
                         $filename = ($registration_code ?: 'STUDENT') . '_' . time() . '.jpg';
                         $file_path = UPLOAD_DIR . $filename;
                         if (file_put_contents($file_path, $img_data)) {
                             $new_profile_picture_path = $file_path;
                         } else {
                             $upload_error_message = 'Failed to save captured profile picture. Check server permissions.';
                         }
                     }
                 } else {
                     // Only set error if neither file nor base64 was provided intentionally
                      if (!isset($_FILES['profile_picture_file_modal']) || $_FILES['profile_picture_file_modal']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $upload_error_message = 'No new picture file or captured data provided.';
                     } else {
                         // No file was intentionally uploaded, not an error in this context
                     }
                 }
            }


            // --- Update Database if successful upload ---
            // Proceed only if a new path was successfully created AND there wasn't a different upload error
            if ($new_profile_picture_path && !$upload_error_message) {
                $stmt_update = $conn->prepare("UPDATE registrations SET profile_picture_path = ?, last_edited_by_user_id = ?, last_edited_at = NOW() WHERE id = ?");
                 if ($stmt_update) {
                     $current_user_id = $_SESSION['user_id'];
                     $stmt_update->bind_param("sii", $new_profile_picture_path, $current_user_id, $registration_id);
                     if ($stmt_update->execute()) {
                         // Delete old picture if it exists and is different
                         if ($current_pic_path && $current_pic_path !== $new_profile_picture_path && file_exists($current_pic_path)) {
                             @unlink($current_pic_path); // Use @ to suppress errors if file deletion fails
                         }
                         $message = 'Profile picture updated successfully!';
                         $message_type = 'success';
                     } else {
                         $message = 'Database update failed: ' . $stmt_update->error;
                         $message_type = 'danger';
                         // If DB update fails, delete the newly uploaded file
                         if (file_exists($new_profile_picture_path)) {
                             @unlink($new_profile_picture_path);
                         }
                     }
                     $stmt_update->close();
                 } else {
                     $message = 'Database prepare statement failed: ' . $conn->error;
                     $message_type = 'danger';
                      // If DB prepare fails, delete the newly uploaded file
                      if ($new_profile_picture_path && file_exists($new_profile_picture_path)) {
                         @unlink($new_profile_picture_path);
                     }
                 }
            } elseif ($upload_error_message) {
                // Use the upload error message if set during the process
                $message = $upload_error_message;
                $message_type = 'danger';
            } else {
                // If no new picture was uploaded or captured, it's not an error.
                 $message = 'No new picture was provided to update.';
                 $message_type = 'info'; // Use info instead of danger
            }
        } else {
             $message = "Student record not found for the given ID.";
             $message_type = 'danger';
        }
        $conn->close();
    }
}

// Store message in session and redirect back
$_SESSION['edit_student_message'] = $message;
$_SESSION['edit_student_message_type'] = $message_type;
// Ensure registration_id is an integer before redirecting
$redirect_id = filter_var($registration_id, FILTER_VALIDATE_INT);
if ($redirect_id) {
    header("Location: view_edit_registration.php?id=" . $redirect_id);
} else {
    // Fallback redirect if ID is somehow invalid
     header("Location: search_registrations.php");
}
exit();
?>