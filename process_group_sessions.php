<?php
// Show all errors to find problems easily
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Security Check & DB Connection
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff', 'instructor'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { echo json_encode(['success' => false, 'message' => 'DB Connection Failed.']); exit(); }

$action = $_REQUEST['action'] ?? null;
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'fetch_sessions': fetchSessions($conn); break;
        case 'create_session': createSession($conn, $user_id); break;
        case 'find_students': findStudents($conn); break;
        case 'enroll_student': enrollStudent($conn); break;
        case 'mark_attendance': markAttendance($conn, $user_id); break;
        case 'fetch_student_history': fetchStudentHistory($conn); break;
        case 'get_dropdowns': getDropdownData($conn); break;
        case 'scan_and_validate': scanAndValidateStudent($conn, $user_id); break;
        case 'complete_session': completeSession($conn, $user_id); break;
        default: throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();


function completeSession($conn, $user_id) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    if ($session_id === 0) {
        throw new Exception("Session ID is required.");
    }
    $conn->begin_transaction();
    try {
        $update_students_sql = "UPDATE session_attendance SET status = 'Absent', marked_at = NOW(), marked_by_user_id = ? WHERE session_id = ? AND status = 'Enrolled'";
        $stmt_students = $conn->prepare($update_students_sql);
        $stmt_students->bind_param("ii", $user_id, $session_id);
        $stmt_students->execute();
        $absent_count = $stmt_students->affected_rows;
        $stmt_students->close();
        $update_session_sql = "UPDATE group_sessions SET session_status = 'Completed' WHERE id = ?";
        $stmt_session = $conn->prepare($update_session_sql);
        $stmt_session->bind_param("i", $session_id);
        $stmt_session->execute();
        $stmt_session->close();
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Session completed successfully. " . $absent_count . " student(s) marked as absent."]);
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception("Database error while completing the session: " . $e->getMessage());
    }
}

function scanAndValidateStudent($conn, $user_id) {
    $id_number = trim($_POST['id_number'] ?? '');
    $session_id = (int)($_POST['session_id'] ?? 0);
    if (empty($id_number) || $session_id === 0) {
        throw new Exception("Student ID and Session ID are required.");
    }
    $sql = "SELECT r.id as student_id, r.full_name, r.payment_done, r.required_training_days, sa.id as attendance_id, sa.status
            FROM registrations r
            JOIN session_attendance sa ON r.id = sa.student_id
            WHERE r.id_number = ? AND sa.session_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $id_number, $session_id);
    $stmt->execute();
    $student_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student_data) {
        throw new Exception("Student with ID '$id_number' is not enrolled in this session.");
    }
    if ($student_data['status'] === 'Present' || $student_data['status'] === 'Absent') {
        throw new Exception("Attendance has already been marked for this student.");
    }
    if (!$student_data['payment_done']) {
        throw new Exception("Validation Failed: Student payment is incomplete. Cannot mark attendance.");
    }
    $attended_stmt = $conn->prepare("SELECT COUNT(*) as attended_count FROM session_attendance WHERE student_id = ? AND status = 'Present'");
    $attended_stmt->bind_param("i", $student_data['student_id']);
    $attended_stmt->execute();
    $attended_count = $attended_stmt->get_result()->fetch_assoc()['attended_count'];
    $attended_stmt->close();
    if ($attended_count >= $student_data['required_training_days']) {
        throw new Exception("Validation Failed: Student has already completed the required " . $student_data['required_training_days'] . " training days.");
    }
    $update_stmt = $conn->prepare("UPDATE session_attendance SET status = 'Present', marked_at = NOW(), marked_by_user_id = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $user_id, $student_data['attendance_id']);
    if (!$update_stmt->execute()) {
        throw new Exception('Database error: Could not mark attendance.');
    }
    $update_stmt->close();
    echo json_encode(['success' => true, 'message' => 'Validated! Attendance marked as Present.', 'student_name' => $student_data['full_name'], 'attendance_id' => $student_data['attendance_id']]);
}

function fetchSessions($conn) {
    $branch = $_GET['branch'];
    $sql = "SELECT gs.*, i.full_name as instructor_name FROM group_sessions gs 
            LEFT JOIN instructors i ON gs.instructor_id = i.id
            WHERE gs.branch_name = ? ORDER BY gs.session_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $branch);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($sessions as $key => $session) {
        $student_sql = "SELECT r.full_name, r.id_number, r.registration_code, sa.id as attendance_id, sa.status FROM session_attendance sa JOIN registrations r ON sa.student_id = r.id WHERE sa.session_id = ?";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("i", $session['id']);
        $student_stmt->execute();
        $sessions[$key]['students'] = $student_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $student_stmt->close();
    }
    echo json_encode(['success' => true, 'sessions' => $sessions]);
}

function createSession($conn, $user_id) {
    $sql = "INSERT INTO group_sessions (session_title, session_date, instructor_id, license_category, branch_name, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissi", $_POST['session_title'], $_POST['session_date'], $_POST['instructor_id'], $_POST['license_category'], $_POST['branch_name'], $user_id);
    if (!$stmt->execute()) { throw new Exception('Database error: ' . $stmt->error); }
    echo json_encode(['success' => true, 'message' => 'Session created.']);
    $stmt->close();
}

function findStudents($conn) {
    $branch = $_GET['branch'] ?? '';
    $session_id = $_GET['session_id'] ?? 0;
    $search_term = $_GET['search'] ?? '';
    if (empty($branch) || empty($session_id) || strlen($search_term) < 2) {
        echo json_encode(['success' => true, 'students' => []]);
        return;
    }
    $search_param = "%" . $search_term . "%";
    $sql = "SELECT id, full_name, registration_code FROM registrations 
            WHERE branch = ? AND status = 'approved' AND payment_done = 1
            AND (full_name LIKE ? OR registration_code LIKE ? OR id_number LIKE ?)
            AND id NOT IN (SELECT student_id FROM session_attendance WHERE session_id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $branch, $search_param, $search_param, $search_param, $session_id);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'students' => $students]);
}

// --- UPDATED enrollStudent FUNCTION ---
function enrollStudent($conn) {
    $session_id = (int)($_POST['session_id']);
    $student_id = (int)($_POST['student_id']);

    // **Step 1: Check if the session is already completed**
    $session_check_stmt = $conn->prepare("SELECT session_status FROM group_sessions WHERE id = ?");
    $session_check_stmt->bind_param("i", $session_id);
    $session_check_stmt->execute();
    $session_status = $session_check_stmt->get_result()->fetch_assoc()['session_status'] ?? 'Active';
    $session_check_stmt->close();

    if ($session_status === 'Completed') {
        throw new Exception('Cannot enroll student. This session has been completed by the instructor.');
    }

    // Step 2: Check if student is already enrolled
    $check_stmt = $conn->prepare("SELECT id FROM session_attendance WHERE session_id = ? AND student_id = ?");
    $check_stmt->bind_param("ii", $session_id, $student_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    if($check_stmt->num_rows > 0) { 
        throw new Exception('This student is already enrolled in this session.');
    }
    $check_stmt->close();

    // Step 3: Enroll the student
    $sql = "INSERT INTO session_attendance (session_id, student_id, status) VALUES (?, ?, 'Enrolled')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $session_id, $student_id);
    if (!$stmt->execute()) { 
        throw new Exception('DB error: Could not enroll student.');
    }
    echo json_encode(['success' => true, 'message' => 'Student enrolled.']);
    $stmt->close();
}

function markAttendance($conn, $user_id) {
    $attendance_id = $_POST['attendance_id'];
    $status = $_POST['status'];
    $sql_update = "UPDATE session_attendance SET status = ?, marked_at = NOW(), marked_by_user_id = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sii", $status, $user_id, $attendance_id);
    if (!$stmt_update->execute()) { throw new Exception('DB error: Could not mark attendance.'); }
    echo json_encode(['success' => true, 'message' => 'Attendance marked.']);
    $stmt_update->close();
}

function fetchStudentHistory($conn) {
    $student_id = $_GET['student_id'] ?? 0;
    if (empty($student_id)) { throw new Exception("Student ID is missing."); }
    $sql = "SELECT gs.session_title, gs.session_date, sa.status, sa.marked_at, u.full_name as marked_by
            FROM session_attendance sa
            JOIN group_sessions gs ON sa.session_id = gs.id
            LEFT JOIN users u ON sa.marked_by_user_id = u.id
            WHERE sa.student_id = ? ORDER BY gs.session_date DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) { throw new Exception("Failed to prepare history query."); }
    $stmt->bind_param("i", $student_id);
    if (!$stmt->execute()) { throw new Exception("Failed to execute history query: " . $stmt->error); }
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'history' => $history]);
}

function getDropdownData($conn) {
    $branch = $_GET['branch'] ?? $_SESSION['branch_name'];
    $stmt = $conn->prepare("SELECT id, full_name FROM instructors WHERE branch_name = ? ORDER BY full_name");
    if ($stmt === false) { throw new Exception('Failed to prepare instructor query.'); }
    $stmt->bind_param("s", $branch);
    $stmt->execute();
    $instructors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $categories = $conn->query("SELECT category_name FROM license_categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'instructors' => $instructors, 'license_categories' => $categories]);
}
?>