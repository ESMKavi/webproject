<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$is_admin = $_SESSION['role'] === 'admin';
$branch_to_view = $is_admin ? ($_GET['branch'] ?? 'Angunukolapalassa') : $_SESSION['branch_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Group Training Sessions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top"></nav>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Group Training Sessions - <span class="text-primary"><?php echo htmlspecialchars($branch_to_view); ?></span></h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal" onclick="prepareSessionModal()"><i class="bi bi-plus-circle-fill"></i> Create New Session</button>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <?php if ($is_admin): ?>
                    <div class="col-md-4"><label for="branch" class="form-label">Filter by Branch</label><select name="branch" id="branch" class="form-select" onchange="this.form.submit()"><option value="Angunukolapalassa" <?php if($branch_to_view == 'Angunukolapalassa') echo 'selected'; ?>>Angunukolapalassa</option><option value="Ambalantota" <?php if($branch_to_view == 'Ambalantota') echo 'selected'; ?>>Ambalantota</option><option value="Ranna" <?php if($branch_to_view == 'Ranna') echo 'selected'; ?>>Ranna</option></select></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div id="sessionList"><div class="text-center p-5">Loading sessions...</div></div>
    </main>

    <div class="modal fade" id="sessionModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Create New Training Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <form id="sessionForm">
                <input type="hidden" name="action" value="create_session">
                <input type="hidden" name="branch_name" value="<?php echo htmlspecialchars($branch_to_view); ?>">
                <div class="mb-3"><label class="form-label">Session Title</label><input type="text" name="session_title" class="form-control" placeholder="e.g., Car Theory Class - Week 1" required></div>
                <div class="mb-3"><label class="form-label">Session Date</label><input type="date" name="session_date" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Assign to Instructor</label><select name="instructor_id" id="instructorSelect" class="form-select" required></select></div>
                 <div class="mb-3"><label class="form-label">License Category</label><select name="license_category" id="licenseCategorySelect" class="form-select" required></select></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" id="createSessionBtn" class="btn btn-primary" onclick="saveSession()">Create</button>
        </div>
    </div></div></div>

    <div class="modal fade" id="addStudentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add Students to Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="addStudentSessionId">
            <div class="input-group mb-3"><input type="text" id="studentSearchInput" class="form-control" placeholder="Search students by name, reg code, or ID..."><button class="btn btn-outline-secondary" onclick="searchStudents()">Search</button></div>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;"><table class="table table-sm"><thead><tr><th>Name</th><th>Reg. Code</th><th>Action</th></tr></thead><tbody id="studentSearchResults"></tbody></table></div>
        </div>
    </div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const branchName = "<?php echo htmlspecialchars($branch_to_view); ?>";
        const sessionModal = new bootstrap.Modal(document.getElementById('sessionModal'));
        const addStudentModal = new bootstrap.Modal(document.getElementById('addStudentModal'));
        
        document.addEventListener('DOMContentLoaded', loadSessions);
        
        async function loadSessions() {
            try {
                const response = await fetch(`process_group_sessions.php?action=fetch_sessions&branch=${branchName}`);
                const data = await response.json();
                const sessionList = document.getElementById('sessionList');
                sessionList.innerHTML = '';
                if(data.success && data.sessions.length > 0) {
                    data.sessions.forEach(session => {
                        let studentListHtml = '<p class="text-muted text-center">No students enrolled yet.</p>';
                        if(session.students && session.students.length > 0) {
                            studentListHtml = '<ul class="list-group list-group-flush">';
                            session.students.forEach(student => {
                                let statusBadge = `<span class="badge bg-secondary">${student.status}</span>`;
                                if(student.status === 'Present') statusBadge = `<span class="badge bg-success">${student.status}</span>`;
                                else if(student.status === 'Absent') statusBadge = `<span class="badge bg-danger">${student.status}</span>`;
                                studentListHtml += `<li class="list-group-item d-flex justify-content-between align-items-center">${student.full_name} (${student.registration_code}) ${statusBadge}</li>`;
                            });
                            studentListHtml += '</ul>';
                        }
                        
                        // **NEW: Check session status and prepare UI elements**
                        const isCompleted = session.session_status === 'Completed';
                        const statusBadge = isCompleted ? '<span class="badge bg-success ms-2">Completed</span>' : '';
                        const addStudentBtnDisabled = isCompleted ? 'disabled' : '';
                        
                        const sessionCard = `
                            <div class="card shadow-sm mb-3 ${isCompleted ? 'bg-light' : ''}">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div><h5 class="mb-0 d-inline-block">${session.session_title}</h5> ${statusBadge} <br> <small class="text-muted">${new Date(session.session_date + 'T00:00:00').toDateString()} | Instructor: <strong>${session.instructor_name || 'Not Assigned'}</strong></small></div>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-success" onclick="openAddStudentModal(${session.id})" ${addStudentBtnDisabled}><i class="bi bi-person-plus-fill"></i> Add Students</button>
                                        <a href="reports.php?generate=true&report_type=session_attendance&session_id=${session.id}" class="btn btn-sm btn-info" target="_blank"><i class="bi bi-file-earmark-text"></i> Export Report</a>
                                    </div>
                                </div>
                                <div class="card-body"><h6 class="card-title">Enrolled Students (${session.students.length})</h6>${studentListHtml}</div>
                            </div>`;
                        sessionList.innerHTML += sessionCard;
                    });
                } else {
                    sessionList.innerHTML = '<div class="alert alert-info">No group sessions found for this branch. Click "Create New Session" to get started.</div>';
                }
            } catch (error) {
                console.error("Error loading sessions:", error);
                document.getElementById('sessionList').innerHTML = '<div class="alert alert-danger">Could not load session data. Please check the browser console (F12) for errors.</div>';
            }
        }

        async function prepareSessionModal() {
            const instructorSelect = document.getElementById('instructorSelect');
            const categorySelect = document.getElementById('licenseCategorySelect');
            instructorSelect.innerHTML = '<option value="">Loading instructors...</option>';
            try {
                const response = await fetch(`process_group_sessions.php?action=get_dropdowns&branch=${branchName}`);
                if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
                const data = await response.json();
                if (data.success && data.instructors) {
                    instructorSelect.innerHTML = '<option value="">Select Instructor...</option>';
                    if (data.instructors.length === 0) {
                        instructorSelect.innerHTML = '<option value="">No instructors found for this branch</option>';
                    } else {
                        data.instructors.forEach(inst => { instructorSelect.innerHTML += `<option value="${inst.id}">${inst.full_name}</option>`; });
                    }
                    categorySelect.innerHTML = '<option value="">Select Category...</option>';
                     if (data.license_categories && data.license_categories.length > 0) {
                        data.license_categories.forEach(cat => { categorySelect.innerHTML += `<option value="${cat.category_name}">${cat.category_name}</option>`; });
                    }
                } else {
                    throw new Error(data.message || 'Could not parse instructor data.');
                }
            } catch (error) {
                console.error("Error in prepareSessionModal:", error);
                instructorSelect.innerHTML = '<option value="">Error loading instructors</option>';
                alert("Failed to load instructors. Please check the browser console (F12) for more details.");
            }
        }
        
        async function saveSession() {
            const createButton = document.getElementById('createSessionBtn');
            createButton.disabled = true;
            createButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';
            try {
                const form = document.getElementById('sessionForm');
                const response = await fetch('process_group_sessions.php', { method: 'POST', body: new FormData(form) });
                if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
                const data = await response.json();
                if (data.success) {
                    sessionModal.hide();
                    loadSessions();
                } else { throw new Error(data.message); }
            } catch (error) {
                console.error("Error saving session:", error);
                alert('Error: ' + error.message);
            } finally {
                createButton.disabled = false;
                createButton.innerHTML = 'Create';
            }
        }

        function openAddStudentModal(sessionId) {
            document.getElementById('addStudentSessionId').value = sessionId;
            document.getElementById('studentSearchResults').innerHTML = '';
            document.getElementById('studentSearchInput').value = '';
            addStudentModal.show();
        }

        async function searchStudents() {
            const sessionId = document.getElementById('addStudentSessionId').value;
            const searchTerm = document.getElementById('studentSearchInput').value;
            const resultsBody = document.getElementById('studentSearchResults');
            if(searchTerm.length < 2) { resultsBody.innerHTML = '<tr><td colspan="3">Enter at least 2 characters.</td></tr>'; return; }
            const response = await fetch(`process_group_sessions.php?action=find_students&branch=${branchName}&session_id=${sessionId}&search=${searchTerm}`);
            const data = await response.json();
            resultsBody.innerHTML = '';
            if(data.success && data.students.length > 0) {
                data.students.forEach(student => {
                    resultsBody.innerHTML += `<tr><td>${student.full_name}</td><td>${student.registration_code}</td><td><button class="btn btn-sm btn-primary" onclick="enrollStudent(${sessionId}, ${student.id}, this)">Enroll</button></td></tr>`;
                });
            } else { resultsBody.innerHTML = '<tr><td colspan="3">No eligible students found.</td></tr>'; }
        }

        async function enrollStudent(sessionId, studentId, btn) {
            btn.disabled = true; btn.innerText = 'Enrolling...';
            const formData = new FormData();
            formData.append('action', 'enroll_student'); formData.append('session_id', sessionId); formData.append('student_id', studentId);
            const response = await fetch('process_group_sessions.php', { method: 'POST', body: formData });
            const data = await response.json();
            if(data.success) {
                btn.innerText = 'Enrolled'; btn.classList.replace('btn-primary', 'btn-success');
                // Reload sessions to reflect the new student
                loadSessions();
            } else { 
                alert('Error: ' + data.message); 
                btn.disabled = false; 
                btn.innerText = 'Enroll'; 
            }
        }
    </script>
</body>
</html>