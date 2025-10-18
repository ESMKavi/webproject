<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') { header("Location: login.php"); exit(); }
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed."); }
$session_id = $_GET['session_id'] ?? 0;
if ($session_id === 0) { die("Invalid session ID."); }

// Fetch session details, including the new 'session_status' field
$session = $conn->query("SELECT * FROM group_sessions WHERE id = $session_id")->fetch_assoc();
$is_completed = ($session && $session['session_status'] === 'Completed');

$students = $conn->query("SELECT r.full_name, r.registration_code, r.id_number, sa.id as attendance_id, sa.status 
                          FROM session_attendance sa JOIN registrations r ON sa.student_id = r.id
                          WHERE sa.session_id = $session_id ORDER BY r.full_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Mark Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        #qr-reader { border: 2px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        #scan-status { font-size: 1.2rem; font-weight: 500; }
    </style>
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand mb-0 h1"><i class="bi bi-clipboard-check-fill"></i> Attendance Sheet</span></div></nav>
    <main class="container py-4">
        <?php if (!$session): ?>
            <div class="alert alert-danger">Session not found.</div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center">
                <div class="dashboard-header">
                    <h2><?php echo htmlspecialchars($session['session_title']); ?></h2>
                    <p class="text-muted"><?php echo htmlspecialchars(date('l, F j, Y', strtotime($session['session_date']))); ?></p>
                </div>
                <div>
                    <button id="completeSessionBtn" class="btn btn-success btn-lg" onclick="completeSession()" <?php if ($is_completed) echo 'disabled'; ?>>
                        <i class="bi bi-check-circle-fill"></i> <?php echo $is_completed ? 'Session Completed' : 'Complete Session'; ?>
                    </button>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-lg-5">
                    <div class="card shadow-sm">
                        <div class="card-header text-center"><h5>Scan Student QR Code</h5></div>
                        <div class="card-body">
                            <div id="qr-reader" style="width:100%;"></div>
                            <div id="scan-status" class="alert alert-secondary text-center mt-3" role="alert">
                                Awaiting Scan...
                            </div>
                        </div>
                        <div class="card-footer">
                             <label for="manualLookupInput" class="form-label">Or Enter Student ID Manually:</label>
                             <div class="input-group">
                                <input type="text" id="manualLookupInput" class="form-control" placeholder="Type ID and press Enter...">
                                <button class="btn btn-primary" onclick="handleManualEntry()"><i class="bi bi-arrow-return-left"></i></button>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-sm">
                        <div class="card-header"><h5>Enrolled Students</h5></div>
                        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                            <div class="list-group" id="studentList">
                                <?php if (empty($students)): ?>
                                    <div class="list-group-item text-center p-4">No students are enrolled in this session.</div>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <div class="list-group-item" data-id-number="<?php echo htmlspecialchars($student['id_number']); ?>">
                                            <div class="row align-items-center">
                                                <div class="col-8">
                                                    <h6 class="mb-0 student-name"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['id_number']); ?></small>
                                                </div>
                                                <div class="col-4 text-end student-status">
                                                    <?php 
                                                        if($student['status'] === 'Present') echo '<span class="badge fs-6 bg-success">Present</span>';
                                                        elseif($student['status'] === 'Absent') echo '<span class="badge fs-6 bg-danger">Absent</span>';
                                                        else echo '<span class="badge fs-6 bg-secondary">Enrolled</span>';
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4"><a href="instructor_dashboard.php" class="btn btn-secondary">&larr; Back to Dashboard</a></div>
        <?php endif; ?>
        
        <audio id="success-sound" src="sounds/success.mp3" preload="auto"></audio>
        <audio id="error-sound" src="sounds/error.mp3" preload="auto"></audio>

    </main>
    
    <script src="js/html5-qrcode.min.js"></script>
    <script>
        const sessionId = <?php echo $session_id; ?>;
        const statusDisplay = document.getElementById('scan-status');
        const successSound = document.getElementById('success-sound');
        const errorSound = document.getElementById('error-sound');
        const manualInput = document.getElementById('manualLookupInput');
        const completeSessionBtn = document.getElementById('completeSessionBtn');

        // --- COMPLETE SESSION LOGIC ---
        async function completeSession() {
            if (!confirm("Are you sure you want to complete this session? All remaining students will be marked as 'Absent'.")) {
                return;
            }

            completeSessionBtn.disabled = true;
            completeSessionBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Finalizing...';

            const formData = new FormData();
            formData.append('action', 'complete_session');
            formData.append('session_id', sessionId);

            try {
                const response = await fetch('process_group_sessions.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    // Update UI for all remaining students
                    document.querySelectorAll('.student-status').forEach(statusDiv => {
                        if (statusDiv.textContent.trim() === 'Enrolled') {
                            statusDiv.innerHTML = '<span class="badge fs-6 bg-danger">Absent</span>';
                        }
                    });
                    completeSessionBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Session Completed';
                    alert(data.message);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                alert(`Error: ${error.message}`);
                completeSessionBtn.disabled = false;
                completeSessionBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Complete Session';
            }
        }


        // --- SCAN AND VALIDATE LOGIC ---
        async function validateStudent(idNumber) {
            if (completeSessionBtn.disabled) {
                statusDisplay.className = 'alert alert-warning';
                statusDisplay.textContent = 'Session is already completed. Cannot mark new attendance.';
                errorSound.play();
                return;
            }

            statusDisplay.className = 'alert alert-info';
            statusDisplay.textContent = 'Validating...';

            const formData = new FormData();
            formData.append('action', 'scan_and_validate');
            formData.append('id_number', idNumber);
            formData.append('session_id', sessionId);
            
            try {
                const response = await fetch('process_group_sessions.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    statusDisplay.className = 'alert alert-success';
                    statusDisplay.textContent = `Success: ${data.student_name} marked present!`;
                    successSound.play();
                    
                    const studentRow = document.querySelector(`.list-group-item[data-id-number='${idNumber}']`);
                    if(studentRow) {
                        studentRow.querySelector('.student-status').innerHTML = '<span class="badge fs-6 bg-success">Present</span>';
                        studentRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                statusDisplay.className = 'alert alert-danger';
                statusDisplay.textContent = `Error: ${error.message}`;
                errorSound.play();
            }
        }

        // --- QR Code Scanner Setup ---
        function onScanSuccess(decodedText, decodedResult) {
            try {
                const url = new URL(decodedText);
                const idNumber = url.searchParams.get('id_number');
                if (idNumber) {
                    validateStudent(idNumber);
                } else {
                    throw new Error('QR code does not contain a valid student ID number.');
                }
            } catch (e) {
                statusDisplay.className = 'alert alert-danger';
                statusDisplay.textContent = 'Invalid QR Code Format.';
                errorSound.play();
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            if(document.getElementById('qr-reader')) {
                let html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader", { fps: 10, qrbox: { width: 250, height: 250 } }, false
                );
                html5QrcodeScanner.render(onScanSuccess, () => {});
            }
        });

        // --- Manual Entry Setup ---
        function handleManualEntry() {
            const idNumber = manualInput.value.trim();
            if (idNumber) {
                validateStudent(idNumber);
                manualInput.value = ''; 
            }
        }
        manualInput.addEventListener('keypress', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleManualEntry();
            }
        });
    </script>
       <footer class="site-footer bg-dark text-white text-center py-3">
        <div class="container">
            <p class="mb-1"><small>If you have any technical questions, please contact the IT Department.</small></p>
            <p class="mb-0"><small>&copy; <?php echo date('Y'); ?> Sriyani DS-IT Department. All rights reserved.</small></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>