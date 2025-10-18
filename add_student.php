<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'branch_staff') {
    header("Location: login.php");
    exit();
}

// !!! IMPORTANT: CONFIGURE YOUR DATABASE CREDENTIALS HERE !!!
// Include the database configuration file
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$branch_name = $_SESSION['branch_name'];

// Fetch available license categories for the dropdown
$license_categories = [];
$cat_result = $conn->query("SELECT category_name, base_price FROM license_categories ORDER BY category_name");
if ($cat_result) { while ($row = $cat_result->fetch_assoc()) { $license_categories[] = $row; } }
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student - <?php echo htmlspecialchars($branch_name); ?> Branch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
         <div class="container">
            <a class="navbar-brand" href="branch_dashboard.php">
                <img src="images/logo.png" alt="Logo" class="navbar-logo">
                Sriyani Driving School System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#staffNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="staffNav">
                 <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="branch_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_finances.php">Finances</a></li>
                    <li class="nav-item"><a class="nav-link" href="search_registrations.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_group_sessions.php">Group Training</a></li>
                </ul>
                <ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li></ul>
            </div>
        </div>
    </nav>

    <main class="py-4">
        <div class="container form-page-container" style="max-width: 900px;">
            <h2 class="text-center mb-4 section-title">Manually Register New Student</h2>
            <?php
            if (isset($_SESSION['add_student_message'])) {
                $alert_class = 'alert-danger'; // Default to danger
                if ($_SESSION['add_student_message_type'] == 'success') {
                    $alert_class = 'alert-success';
                } elseif ($_SESSION['add_student_message_type'] == 'warning') {
                     $alert_class = 'alert-warning';
                }
                echo '<div class="alert ' . $alert_class . ' text-center" role="alert">' . htmlspecialchars($_SESSION['add_student_message']) . '</div>';
                unset($_SESSION['add_student_message'], $_SESSION['add_student_message_type']);
            }
            ?>
            <form action="process_add_student.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch_name); ?>">

                <div class="form-section-title">Personal Information</div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" id="full_name" name="full_name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label for="name_with_initials" class="form-label">Name With Initials</label><input type="text" id="name_with_initials" name="name_with_initials" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="id_number" class="form-label">ID Number</label><input type="text" id="id_number" name="id_number" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label for="phone_number" class="form-label">Phone Number</label><input type="tel" id="phone_number" name="phone_number" class="form-control" required></div>
                </div>
                <div class="mb-3"><label for="address" class="form-label">Address</label><textarea id="address" name="address" rows="3" class="form-control" required></textarea></div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label for="dob" class="form-label">Date of Birth</label><input type="date" id="dob" name="dob" class="form-control" required onchange="calculateAge()"></div>
                    <div class="col-md-4 mb-3"><label for="age" class="form-label">Age</label><input type="number" id="age" name="age" class="form-control" readonly required placeholder="Auto-calculated"></div>
                    <div class="col-md-4 mb-3"><label for="sex" class="form-label">Sex</label><select id="sex" name="sex" class="form-select" required><option value="">Select...</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
                </div>
                <div class="mb-3"><label for="customer_email" class="form-label">Student Email (Optional)</label><input type="email" id="customer_email" name="customer_email" class="form-control"></div>

                <div class="form-section-title">Profile Picture</div>
                <div class="row align-items-center">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Preview</label>
                        <div id="camera-container">
                             <video id="video-feed" autoplay style="display: none;"></video>
                             <canvas id="photo-canvas" style="display: none;"></canvas> <img id="photo-preview" src="#" alt="Photo preview" style="display: none;"/>
                             <div class="camera-overlay-text" id="camera-placeholder">Camera feed or selected image will appear here</div>
                        </div>
                        <input type="hidden" name="profile_picture" id="profile_picture_data">
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="mb-3">
                             <label for="profile_picture_file" class="form-label">Upload Photo File</label>
                             <input class="form-control" type="file" name="profile_picture_file" id="profile_picture_file" accept="image/jpeg, image/png, image/gif">
                             <small class="form-text text-muted">Or use the camera options below.</small>
                        </div>
                         <hr>
                         <button type="button" id="start-camera-btn" class="btn btn-secondary mb-2 w-100"><i class="bi bi-camera-video-fill"></i> Start/Stop Camera</button>
                         <button type="button" id="capture-btn" class="btn btn-primary mb-2 w-100" disabled><i class="bi bi-camera-fill"></i> Capture Photo</button>
                         <button type="button" id="retake-btn" class="btn btn-warning w-100" style="display: none;"><i class="bi bi-arrow-clockwise"></i> Clear/Retake</button>
                    </div>
                </div>
                <div class="form-section-title">Course & Payment Details</div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="license_category" class="form-label">License Category</label><select id="license_category" name="license_category" class="form-select" required onchange="updateLicensePrice()"><option value="">Select...</option><?php foreach ($license_categories as $cat): ?><option value="<?php echo htmlspecialchars($cat['category_name']); ?>" data-price="<?php echo htmlspecialchars($cat['base_price']); ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label for="appointment_date" class="form-label">Enrollment Date</label><input type="date" id="appointment_date" name="appointment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="required_training_days" class="form-label">Required Training Days</label><input type="number" class="form-control" name="required_training_days" id="required_training_days" value="30" required></div>
                    <div class="col-md-6 mb-3"><label for="invoice_no" class="form-label">Invoice No (Optional)</label><input type="text" id="invoice_no" name="invoice_no" class="form-control" placeholder="Auto-generates from Reg. Code if blank"></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label for="total_amount" class="form-label">Total Amount (LKR)</label><input type="number" step="0.01" id="total_amount" name="total_amount" class="form-control" required oninput="calculateBalance()"></div>
                    <div class="col-md-4 mb-3"><label for="advance_paid" class="form-label">Advance Paid (LKR)</label><input type="number" step="0.01" id="advance_paid" name="advance_paid" class="form-control" value="0.00" oninput="calculateBalance()"></div>
                    <div class="col-md-4 mb-3"><label for="balance_1" class="form-label">Balance (LKR)</label><input type="number" step="0.01" id="balance_1" name="balance_1" class="form-control" readonly placeholder="Auto-calculated"></div>
                </div>
                 <div class="form-check mb-3">
                    <input type="checkbox" id="payment_done" name="payment_done" value="1" class="form-check-input">
                    <label for="payment_done" class="form-check-label">Full Payment is Complete</label>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Register Student</button>
                    <a href="branch_dashboard.php" class="btn btn-secondary mt-2">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <footer class="site-footer bg-dark text-white text-center py-3">
        <div class="container">
            <p class="mb-1"><small>If you have any technical questions, please contact the IT Department.</small></p>
            <p class="mb-0"><small>&copy; <?php echo date('Y'); ?> Sriyani DS-IT Department. All rights reserved.</small></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- AGE CALCULATION ---
        function calculateAge() {
            const dobInput = document.getElementById('dob').value;
            if (dobInput) {
                const birthDate = new Date(dobInput);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                document.getElementById('age').value = age;
            } else {
                document.getElementById('age').value = '';
            }
        }

        // --- PAYMENT CALCULATION ---
         function updateLicensePrice() {
            const select = document.getElementById('license_category');
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const totalAmountInput = document.getElementById('total_amount');
            if (price) {
                totalAmountInput.value = parseFloat(price).toFixed(2);
                calculateBalance();
            } else {
                // Optionally clear or set to 0 if no category is selected
                 totalAmountInput.value = '0.00';
                 calculateBalance();
            }
        }

        function calculateBalance() {
            const total = parseFloat(document.getElementById('total_amount').value) || 0;
            const advance = parseFloat(document.getElementById('advance_paid').value) || 0;
            const balance = total - advance;
            document.getElementById('balance_1').value = balance.toFixed(2);
            document.getElementById('payment_done').checked = (balance <= 0 && total > 0);
        }

        // Initialize balance on page load
        document.addEventListener('DOMContentLoaded', calculateBalance);

        // --- PROFILE PICTURE CAPTURE & UPLOAD ---
        const video = document.getElementById('video-feed');
        const canvas = document.getElementById('photo-canvas');
        const photoPreview = document.getElementById('photo-preview');
        const startCameraButton = document.getElementById('start-camera-btn');
        const captureButton = document.getElementById('capture-btn');
        const retakeButton = document.getElementById('retake-btn');
        const hiddenInput = document.getElementById('profile_picture_data');
        const cameraPlaceholder = document.getElementById('camera-placeholder');
        const fileInput = document.getElementById('profile_picture_file');

        let stream = null;

        // --- Camera Functions ---
        startCameraButton.addEventListener('click', async () => {
            if (stream && video.style.display === 'block') { // If camera is running, stop it
                stopCamera();
            } else { // Otherwise, start it
                clearFileInput(); // Ensure file input is cleared if camera starts
                if (stream) { // Stop existing stream just in case
                    stream.getTracks().forEach(track => track.stop());
                }
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    video.srcObject = stream;
                    video.style.display = 'block';
                    photoPreview.style.display = 'none';
                    retakeButton.style.display = 'none'; // Hide retake initially
                    captureButton.disabled = false;
                    startCameraButton.textContent = 'Stop Camera';
                    startCameraButton.classList.remove('btn-secondary');
                    startCameraButton.classList.add('btn-danger');
                    cameraPlaceholder.style.display = 'none';
                    hiddenInput.value = ''; // Clear previous data
                } catch (err) {
                    console.error("Error accessing camera: ", err);
                    cameraPlaceholder.textContent = 'Error: Could not access camera.';
                    cameraPlaceholder.style.display = 'block';
                    alert('Could not access the camera. Please ensure permissions are granted and try again.');
                }
            }
        });

        captureButton.addEventListener('click', () => {
            if (!stream || video.style.display === 'none') return;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.8);

            photoPreview.src = dataUrl;
            photoPreview.style.display = 'block';
            hiddenInput.value = dataUrl.split(',')[1]; // Store Base64

            stopCamera(); // Stop camera after capture

            captureButton.disabled = true;
            retakeButton.style.display = 'block'; // Show retake button
            fileInput.disabled = true; // Disable file input after camera capture
        });

        retakeButton.addEventListener('click', () => {
            stopCamera(); // Stop camera if running
            clearFileInput(); // Clear file input
            photoPreview.style.display = 'none'; // Hide preview
            photoPreview.removeAttribute('src'); // Remove src to prevent broken image icon
            hiddenInput.value = ''; // Clear hidden data
            retakeButton.style.display = 'none'; // Hide retake button
            cameraPlaceholder.style.display = 'block'; // Show placeholder
            cameraPlaceholder.textContent = 'Camera feed or selected image will appear here';
            captureButton.disabled = true; // Disable capture until camera is started
            fileInput.disabled = false; // Re-enable file input
            startCameraButton.disabled = false; // Re-enable camera button
        });

        function stopCamera() {
             if (stream) {
                 stream.getTracks().forEach(track => track.stop());
                 stream = null;
             }
             if (video) video.style.display = 'none';
             if (video) video.srcObject = null;
             if (captureButton) captureButton.disabled = true;
             if (startCameraButton) {
                 startCameraButton.textContent = 'Start/Stop Camera';
                 startCameraButton.classList.remove('btn-danger');
                 startCameraButton.classList.add('btn-secondary');
             }
             if (photoPreview && photoPreview.style.display === 'none') {
                 if (cameraPlaceholder) cameraPlaceholder.style.display = 'block';
                 if (cameraPlaceholder) cameraPlaceholder.textContent = 'Camera feed or selected image will appear here';
             }
        }

        // --- File Input Functions ---
        fileInput.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                stopCamera();
                startCameraButton.disabled = true; // Disable camera button when file is selected

                const reader = new FileReader();
                reader.onload = (e) => {
                    photoPreview.src = e.target.result;
                    photoPreview.style.display = 'block';
                    // We don't store Base64 here anymore, the backend handles the file directly
                    hiddenInput.value = ''; // Clear any camera base64 data
                    cameraPlaceholder.style.display = 'none';
                    retakeButton.style.display = 'block'; // Show clear button
                    captureButton.disabled = true;
                }
                reader.readAsDataURL(file); // Read for preview purpose
            }
        });

        function clearFileInput() {
            fileInput.value = null; // Clear the selected file
            fileInput.disabled = false;
        }

        // Stop camera when leaving the page
        window.addEventListener('beforeunload', stopCamera);

    </script>
</body>
</html>