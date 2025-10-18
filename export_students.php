<?php
session_start();

// Include the Composer autoloader to access the PhpSpreadsheet library
require 'vendor/autoload.php';

// Import the necessary classes from PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Security Check: Only admins and branch staff can access this feature.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$is_admin = ($_SESSION['role'] === 'admin');
$param_types = '';
$param_values = [];

// Base query: Select all student fields and join with the users table to get the creator's username
$query = "SELECT r.*, u.username as created_by_username 
          FROM registrations r 
          LEFT JOIN users u ON r.created_by_user_id = u.id 
          WHERE 1=1";

// --- Apply Filters (same logic as search_registrations.php) ---
if (!$is_admin) { $query .= " AND r.branch = ?"; $param_types .= "s"; $param_values[] = $_SESSION['branch_name']; }
if (!empty($_GET['q'])) { $searchTerm = "%" . $_GET['q'] . "%"; $query .= " AND (r.registration_code LIKE ? OR r.full_name LIKE ? OR r.id_number LIKE ? OR r.phone_number LIKE ?)"; $param_types .= "ssss"; array_push($param_values, $searchTerm, $searchTerm, $searchTerm, $searchTerm); }
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) { $query .= " AND r.registration_date BETWEEN ? AND ?"; $param_types .= "ss"; $param_values[] = $_GET['start_date']; $param_values[] = $_GET['end_date']; }
if (isset($_GET['payment_done']) && $_GET['payment_done'] !== '') { $query .= " AND r.payment_done = ?"; $param_types .= "i"; $param_values[] = $_GET['payment_done']; }
if (!empty($_GET['permanent_license_status'])) { $query .= " AND r.permanent_license_status = ?"; $param_types .= "s"; $param_values[] = $_GET['permanent_license_status']; }
if (isset($_GET['temp_license_expiring']) && $_GET['temp_license_expiring'] === '1') { $query .= " AND r.permanent_license_status != 'Issued' AND r.trial_1_result = 'Pass' AND r.temp_license_issue_date <= DATE_SUB(CURDATE(), INTERVAL 10 MONTH)"; }
if ($is_admin && !empty($_GET['branch'])) { $query .= " AND r.branch = ?"; $param_types .= "s"; $param_values[] = $_GET['branch']; }
if (!empty($_GET['license_category'])) { $query .= " AND r.license_category = ?"; $param_types .= "s"; $param_values[] = $_GET['license_category']; }
if (!empty($_GET['status'])) { $query .= " AND r.status = ?"; $param_types .= "s"; $param_values[] = $_GET['status']; }

$query .= " ORDER BY r.id ASC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($param_values)) { $stmt->bind_param($param_types, ...$param_values); }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Error preparing the search query.");
}

// --- Generate Styled Excel File ---

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ** Design the Template **

// 1. Set Title & Subtitle
$sheet->mergeCells('A1:J1')->setCellValue('A1', 'Sriyani Driving School - Student Report');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->mergeCells('A2:J2')->setCellValue('A2', 'Generated on: ' . date('Y-m-d H:i:s'));
$sheet->getStyle('A2')->getFont()->setItalic(true);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 3. **Dynamically Generate Header Columns**
$header_row = [];
$first_row_data = $result->fetch_assoc(); // Fetch the first row to get column names

if ($first_row_data) {
    foreach ($first_row_data as $key => $value) {
        // Exclude internal IDs unless you want them
        if ($key === 'id' || $key === 'created_by_user_id' || $key === 'last_edited_by_user_id') {
            continue;
        }
        // Admin-only check
        if (!$is_admin && $key === 'created_by_username') {
            continue;
        }
        // Convert snake_case to Title Case for better readability
        $header_row[] = ucwords(str_replace('_', ' ', $key));
    }
    // This is the line that was causing the error and has been removed:
    // fputcsv($output, $header_row); 
    $sheet->fromArray($header_row, NULL, 'A4');
} else {
    // If no results, create a default header
    $sheet->setCellValue('A4', 'No students found for the selected filters.');
}


// 4. Style the Header Row
$header_style = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];
if ($first_row_data) {
    $sheet->getStyle('A4:' . $sheet->getHighestColumn() . '4')->applyFromArray($header_style);
}

// 5. **Dynamically Write All Data**
$row_index = 5; // Start writing data from row 5
if ($first_row_data) {
    // Process the first row we already fetched
    $data_to_write = [];
    foreach ($first_row_data as $key => $value) {
        if ($key === 'id' || $key === 'created_by_user_id' || $key === 'last_edited_by_user_id' || (!$is_admin && $key === 'created_by_username')) continue;
        $data_to_write[] = $value;
    }
    $sheet->fromArray($data_to_write, NULL, 'A' . $row_index);
    $row_index++;

    // Process the rest of the rows
    while ($row_data = $result->fetch_assoc()) {
        $data_to_write = [];
        foreach ($row_data as $key => $value) {
            if ($key === 'id' || $key === 'created_by_user_id' || $key === 'last_edited_by_user_id' || (!$is_admin && $key === 'created_by_username')) continue;
            $data_to_write[] = $value;
        }
        $sheet->fromArray($data_to_write, NULL, 'A' . $row_index);
        $row_index++;
    }
}


// 6. Style the Data Rows and Auto-size Columns
$last_col = $sheet->getHighestColumn();
foreach (range('A', $last_col) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}
if ($result->num_rows > 0) {
    $last_row = $row_index - 1;
    $data_range = 'A5:' . $last_col . $last_row;
    $sheet->getStyle($data_range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// --- Send the file to the browser ---
$filename = "student_export_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$stmt->close();
$conn->close();
exit();
?>