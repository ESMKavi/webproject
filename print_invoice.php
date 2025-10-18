<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    header("Location: login.php");
    exit();
}

// Get the logged-in user's full name from the session
$issued_by_user_name = $_SESSION['full_name'] ?? 'Unknown User'; // Default if name isn't set

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed."); }

$registration_id = $_GET['id'] ?? null;
$invoice_data = null;

if ($registration_id) {
    // Fetch profile_picture_path as well
    $query = "SELECT * FROM registrations WHERE id = ?";
    $params = [$registration_id];
    $param_types = "i";

    if ($_SESSION['role'] === 'branch_staff') {
        $query .= " AND branch = ?";
        $params[] = $_SESSION['branch_name'];
        $param_types .= "s";
    }

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $invoice_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
$conn->close();

// Determine base URL for QR code (adjust if needed)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($protocol . $host . $script_dir, '/');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?php echo htmlspecialchars($invoice_data['invoice_no'] ?? 'N/A'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #eee; font-family: sans-serif; }
        .invoice-box { max-width: 800px; margin: 40px auto; padding: 30px; border: 1px solid #ddd; background: #fff; box-shadow: 0 0 10px rgba(0, 0, 0, .15); }
        .invoice-header { text-align: center; margin-bottom: 20px; }
        .invoice-header h1 { margin: 0; }
        .invoice-header img.logo { max-height: 80px; margin-bottom: 10px; } /* Style for logo */
        .invoice-table th { background-color: #f8f9fa; }
        .profile-pic-invoice { max-width: 100px; height: auto; border-radius: 4px; border: 1px solid #dee2e6; float: left; margin-right: 15px; }
        .qr-code-invoice { border: 1px solid #dee2e6; padding: 5px; }
        .payment-details th, .payment-details td { text-align: right; }
        .payment-details th { width: 70%; } /* Adjust width as needed */
        .issued-by { margin-top: 40px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: right; font-size: 0.9em; color: #555; } /* Style for issued by */
        @media print {
            body { background-color: #fff; margin: 0; padding: 10px; font-size: 10pt; } /* Adjust font size for print */
            .no-print { display: none; }
            .invoice-box { box-shadow: none; border: none; margin: 0; max-width: 100%; padding: 15px; }
            .profile-pic-invoice { max-width: 80px; } /* Slightly smaller for print */
            #studentQrCodeInvoiceDiv canvas, #studentQrCodeInvoiceDiv img { max-width: 80px !important; max-height: 80px !important; } /* Control QR size */
            .table { font-size: 9pt; } /* Smaller table font */
            h1 { font-size: 1.5rem; } h5 { font-size: 1rem; }
            .lead { font-size: 1rem; }
            hr { margin: 0.5rem 0; }
            .mb-4 { margin-bottom: 1rem !important; }
            .issued-by { font-size: 0.8em; margin-top: 20px;} /* Smaller margin/font for print */
        }
    </style>
</head>
<body>
    <div class="no-print text-center py-3 bg-dark">
        <button onclick="window.print();" class="btn btn-primary"><i class="bi bi-printer-fill"></i> Print Invoice</button>
        <a href="view_edit_registration.php?id=<?php echo htmlspecialchars($registration_id); ?>" class="btn btn-secondary">Go Back to Profile</a>
    </div>

    <?php if ($invoice_data): ?>
    <div class="invoice-box">
        <div class="invoice-header">
            <img src="images/logo.png" alt="Sriyani Driving School Logo" class="logo">
            <h1>Sriyani Driving School</h1>
            <p class="lead mb-1"><?php echo htmlspecialchars($invoice_data['branch']); ?> Branch</p>
            <p class="text-muted small">Contact: [Your Branch Phone] | Email: [Your Branch Email]</p>
            <hr>
        </div>
        <div class="row mb-4">
            <div class="col-sm-7">
                <?php if (!empty($invoice_data['profile_picture_path']) && file_exists($invoice_data['profile_picture_path'])): ?>
                    <img src="<?php echo htmlspecialchars($invoice_data['profile_picture_path']); ?>" alt="Profile Picture" class="profile-pic-invoice">
                <?php else: ?>
                    <img src="images/default_avatar.png" alt="No Picture" class="profile-pic-invoice">
                <?php endif; ?>

                <h5>Bill To:</h5>
                <div style="margin-left: 115px;"> <p class="mb-1">
                        <strong><?php echo htmlspecialchars($invoice_data['full_name']); ?></strong><br>
                        ID: <?php echo htmlspecialchars($invoice_data['id_number']); ?><br>
                         Phone: <?php echo htmlspecialchars($invoice_data['phone_number']); ?><br>
                        <?php echo nl2br(htmlspecialchars($invoice_data['address'])); ?>
                    </p>
                 </div>
            </div>
            <div class="col-sm-5 text-sm-end">
                <h5>Invoice Details:</h5>
                <p>
                    <strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice_data['invoice_no'] ?: $invoice_data['registration_code']); ?><br>
                    <strong>Date:</strong> <?php echo htmlspecialchars(date('Y-m-d', strtotime($invoice_data['invoice_date'] ?? $invoice_data['registration_date']))); ?><br>
                    <strong>Reg. Code:</strong> <?php echo htmlspecialchars($invoice_data['registration_code']); ?>
                </p>
                 <div class="mt-2 text-center text-sm-end">
                    <div id="studentQrCodeInvoiceDiv" class="d-inline-block qr-code-invoice"></div>
                    <small class="d-block text-muted">Student ID</small>
                 </div>
            </div>
        </div>

        <h5 class="mb-2">Course Details:</h5>
        <table class="table table-bordered invoice-table mb-4">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end" style="width: 150px;">Amount (LKR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Driving Course Fee - <?php echo htmlspecialchars($invoice_data['license_category']); ?></td>
                    <td class="text-end"><?php echo number_format((float)$invoice_data['total_amount'], 2); ?></td>
                </tr>
                 <?php if (isset($invoice_data['medical_fee']) && (float)$invoice_data['medical_fee'] > 0): ?>
                 <tr>
                    <td>Medical Fee (1st)</td>
                    <td class="text-end"><?php echo number_format((float)$invoice_data['medical_fee'], 2); ?></td>
                 </tr>
                 <?php endif; ?>
                 <?php if (isset($invoice_data['remedical_fee_2']) && (float)$invoice_data['remedical_fee_2'] > 0): ?>
                 <tr>
                    <td>Re-Medical Fee (2nd)</td>
                    <td class="text-end"><?php echo number_format((float)$invoice_data['remedical_fee_2'], 2); ?></td>
                 </tr>
                 <?php endif; ?>
                 <?php if (isset($invoice_data['remedical_fee_3']) && (float)$invoice_data['remedical_fee_3'] > 0): ?>
                 <tr>
                    <td>Re-Medical Fee (3rd)</td>
                    <td class="text-end"><?php echo number_format((float)$invoice_data['remedical_fee_3'], 2); ?></td>
                 </tr>
                 <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td class="text-end">Total Course Fee:</td>
                    <td class="text-end"><?php echo number_format((float)$invoice_data['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <h5 class="mb-2">Payments Received:</h5>
         <table class="table table-sm table-striped mb-4">
             <tbody>
                 <?php $total_paid = 0; ?>
                 <?php if (isset($invoice_data['advance_paid']) && (float)$invoice_data['advance_paid'] > 0):
                       $total_paid += (float)$invoice_data['advance_paid']; ?>
                 <tr>
                     <td>Advance Payment<?php echo isset($invoice_data['invoice_date']) ? ' on ' . htmlspecialchars($invoice_data['invoice_date']) : ''; ?></td>
                     <td class="text-end"><?php echo number_format((float)$invoice_data['advance_paid'], 2); ?></td>
                 </tr>
                 <?php endif; ?>

                 <?php if (isset($invoice_data['repayment_amount']) && (float)$invoice_data['repayment_amount'] > 0):
                       $total_paid += (float)$invoice_data['repayment_amount']; ?>
                 <tr>
                     <td>Second Payment<?php echo isset($invoice_data['repayment_date']) ? ' on ' . htmlspecialchars($invoice_data['repayment_date']) : ''; ?></td>
                     <td class="text-end"><?php echo number_format((float)$invoice_data['repayment_amount'], 2); ?></td>
                 </tr>
                  <?php endif; ?>

                 <?php if (isset($invoice_data['repayment_amount_3']) && (float)$invoice_data['repayment_amount_3'] > 0):
                       $total_paid += (float)$invoice_data['repayment_amount_3']; ?>
                 <tr>
                     <td>Third Payment<?php echo isset($invoice_data['repayment_date_3']) ? ' on ' . htmlspecialchars($invoice_data['repayment_date_3']) : ''; ?></td>
                     <td class="text-end"><?php echo number_format((float)$invoice_data['repayment_amount_3'], 2); ?></td>
                 </tr>
                 <?php endif; ?>

                  <?php if (isset($invoice_data['repayment_amount_4']) && (float)$invoice_data['repayment_amount_4'] > 0):
                       $total_paid += (float)$invoice_data['repayment_amount_4']; ?>
                 <tr>
                     <td>Fourth Payment<?php echo isset($invoice_data['repayment_date_4']) ? ' on ' . htmlspecialchars($invoice_data['repayment_date_4']) : ''; ?></td>
                     <td class="text-end"><?php echo number_format((float)$invoice_data['repayment_amount_4'], 2); ?></td>
                 </tr>
                 <?php endif; ?>

                  <?php if ($total_paid == 0): ?>
                    <tr><td colspan="2" class="text-muted text-center">No payments recorded yet.</td></tr>
                  <?php endif; ?>
             </tbody>
         </table>


        <div class="row">
             <div class="col-6">
                 <p class="text-muted small"><strong>Notes:</strong><br>
                 - Please keep this invoice for your records.<br>
                 - Payments are non-refundable.
                 </p>
             </div>
            <div class="col-6">
                <table class="table table-sm payment-details">
                    <tbody>
                        <tr><th>Total Course Fee:</th><td><?php echo number_format((float)$invoice_data['total_amount'], 2); ?></td></tr>
                        <tr><th>Total Paid:</th><td><?php echo number_format($total_paid, 2); ?></td></tr>
                        <tr class="fw-bold fs-5 border-top">
                            <th>Balance Due:</th>
                            <td><?php echo number_format(((float)$invoice_data['total_amount'] - $total_paid), 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>


        <div class="text-center mt-4"> <p class="text-muted">Thank you for choosing Sriyani Driving School!</p>
        </div>

        <div class="issued-by">
            Issued By: <?php echo htmlspecialchars($issued_by_user_name); ?> on <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger text-center m-5">Invoice data could not be found. Please check the student ID and your permissions.</div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($invoice_data && !empty($invoice_data['id_number'])): ?>
                const studentIdNumberInvoice = "<?php echo htmlspecialchars($invoice_data['id_number']); ?>";
                const siteBaseUrlInvoice = "<?php echo $base_url; ?>"; // Use the PHP calculated base URL
                const qrContainerInvoice = document.getElementById('studentQrCodeInvoiceDiv');

                if (qrContainerInvoice && studentIdNumberInvoice) {
                    const qrLinkInvoice = `${siteBaseUrlInvoice}/find_student.php?id_number=${encodeURIComponent(studentIdNumberInvoice)}`;
                    try {
                        new QRCode(qrContainerInvoice, {
                            text: qrLinkInvoice,
                            width: 100, // Adjust size as needed for invoice
                            height: 100,
                            colorDark : "#000000",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.L // Lower correction level for smaller size
                        });
                     } catch (e) {
                         console.error("QR Code generation failed:", e);
                         qrContainerInvoice.innerHTML = "<small class='text-danger'>Error QR</small>";
                     }
                } else if (qrContainerInvoice) {
                     qrContainerInvoice.innerHTML = "<small class='text-warning'>No ID</small>";
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>