<?php
session_start();
header('Content-Type: application/json');

// Security Check & DB Connection
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// !!! IMPORTANT: CONFIGURE YOUR DATABASE CREDENTIALS HERE !!!
// Include the database configuration file
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$action = $_REQUEST['action'] ?? null;
$is_admin = $_SESSION['role'] === 'admin';
$user_id = $_SESSION['user_id'];

// --- Action Router ---
try {
    switch ($action) {
        case 'fetch':
            fetchTransactions($conn, $is_admin);
            break;
        case 'create':
            createTransaction($conn, $user_id, $is_admin);
            break;
        case 'delete':
            deleteTransaction($conn, $is_admin);
            break;
        default:
            throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();

// --- Functions ---

function fetchTransactions($conn, $is_admin) {
    $branch = $_GET['branch'] ?? $_SESSION['branch_name'];
    if (!$is_admin && $branch !== $_SESSION['branch_name']) { throw new Exception('Unauthorized branch access.'); }
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');

    // Fetch transactions
    $sql = "SELECT * FROM finances WHERE branch_name = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC, id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $branch, $startDate, $endDate);
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Fetch summary
    $summary_sql = "SELECT 
                        SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) as total_income,
                        SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) as total_expense
                    FROM finances WHERE branch_name = ? AND transaction_date BETWEEN ? AND ?";
    $summary_stmt = $conn->prepare($summary_sql);
    $summary_stmt->bind_param("sss", $branch, $startDate, $endDate);
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc();
    $summary['net_profit'] = ($summary['total_income'] ?? 0) - ($summary['total_expense'] ?? 0);
    $summary_stmt->close();

    echo json_encode(['success' => true, 'transactions' => $transactions, 'summary' => $summary]);
}

function createTransaction($conn, $user_id, $is_admin) {
    $branch_name = $_POST['branch_name'];
    if (!$is_admin && $branch_name !== $_SESSION['branch_name']) { throw new Exception('Unauthorized branch access.'); }

    $student_id = !empty($_POST['student_id']) ? $_POST['student_id'] : null;
    $sql = "INSERT INTO finances (transaction_date, type, description, amount, category, student_id, branch_name, entered_by_user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdsisi",
        $_POST['transaction_date'],
        $_POST['type'],
        $_POST['description'],
        $_POST['amount'],
        $_POST['category'],
        $student_id,
        $branch_name,
        $user_id
    );
    if (!$stmt->execute()) { throw new Exception('Database Error: ' . $stmt->error); }
    echo json_encode(['success' => true, 'message' => 'Transaction saved.']);
    $stmt->close();
}

function deleteTransaction($conn, $is_admin) {
    $id = $_POST['transaction_id'];
    $sql = "DELETE FROM finances WHERE id = ?";
    $params = [$id];
    $types = "i";

    if (!$is_admin) {
        $sql .= " AND branch_name = ?";
        $params[] = $_SESSION['branch_name'];
        $types .= "s";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Transaction deleted.']);
        } else {
            throw new Exception('Could not delete transaction or unauthorized.');
        }
    } else {
        throw new Exception('Database error on delete.');
    }
    $stmt->close();
}
?>