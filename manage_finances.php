<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_staff'])) {
    header("Location: login.php");
    exit();
}

// !!! IMPORTANT: CONFIGURE YOUR DATABASE CREDENTIALS HERE !!!
// Include the database configuration file
require_once 'config.php';

$is_admin = $_SESSION['role'] === 'admin';
$branch_to_view = $_SESSION['branch_name'];
if ($is_admin && isset($_GET['branch']) && !empty($_GET['branch'])) {
    $branch_to_view = $_GET['branch'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Finances</title>
    <!-- Assets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <!-- ... (copy the navbar from your admin or branch dashboard) ... -->
    </nav>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Daily Income & Expenses - <span class="text-primary"><?php echo htmlspecialchars($branch_to_view); ?></span></h2>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle"></i> Add New Transaction</h5>
            </div>
            <div class="card-body">
                <form id="financeForm" class="row g-3">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="branch_name" value="<?php echo htmlspecialchars($branch_to_view); ?>">
                    <div class="col-md-3"><label for="transaction_date" class="form-label">Date</label><input type="date" class="form-control" name="transaction_date" id="transaction_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="col-md-3"><label for="type" class="form-label">Type</label><select name="type" id="type" class="form-select" required><option value="Income">Income</option><option value="Expense">Expense</option></select></div>
                    <div class="col-md-6"><label for="description" class="form-label">Description</label><input type="text" class="form-control" name="description" id="description" placeholder="e.g., Student Full Payment, Fuel" required></div>
                    <div class="col-md-4"><label for="amount" class="form-label">Amount (LKR)</label><input type="number" step="0.01" class="form-control" name="amount" id="amount" required></div>
                    <div class="col-md-4"><label for="category" class="form-label">Category</label><input type="text" class="form-control" name="category" id="category" placeholder="e.g., Course Fee, Fuel, Rent" required></div>
                    <div class="col-md-4"><label for="studentId" class="form-label">Link to Student (Optional)</label><select class="form-select" id="studentId" name="student_id"></select></div>
                    <div class="col-12 text-end"><button type="button" class="btn btn-primary" onclick="saveTransaction()">Save Transaction</button></div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
             <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label>Filter by Date Range</label>
                        <div class="input-group">
                           <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_GET['start_date'] ?? date('Y-m-01')); ?>">
                           <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($_GET['end_date'] ?? date('Y-m-d')); ?>">
                        </div>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="col-md-4">
                        <label for="branch" class="form-label">Filter by Branch</label>
                        <select name="branch" id="branch" class="form-select">
                            <option value="Angunukolapalassa" <?php if($branch_to_view == 'Angunukolapalassa') echo 'selected'; ?>>Angunukolapalassa</option>
                            <option value="Ambalantota" <?php if($branch_to_view == 'Ambalantota') echo 'selected'; ?>>Ambalantota</option>
                            <option value="Ranna" <?php if($branch_to_view == 'Ranna') echo 'selected'; ?>>Ranna</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4"><button type="submit" class="btn btn-secondary w-100">Apply Filter</button></div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Transaction History</h5></div>
            <div class="card-body">
                <div id="financeSummary" class="mb-3 border-bottom pb-3"></div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Type</th><th class="text-end">Amount</th><th>Actions</th></tr></thead>
                        <tbody id="financeTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const branchName = "<?php echo htmlspecialchars($branch_to_view); ?>";
        const startDate = "<?php echo htmlspecialchars($_GET['start_date'] ?? date('Y-m-01')); ?>";
        const endDate = "<?php echo htmlspecialchars($_GET['end_date'] ?? date('Y-m-d')); ?>";

        document.addEventListener('DOMContentLoaded', function() {
            loadTransactions();
            loadStudentsForDropdown();
        });

        function loadTransactions() {
            fetch(`process_finance.php?action=fetch&branch=${branchName}&start_date=${startDate}&end_date=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('financeTableBody');
                    const summaryDiv = document.getElementById('financeSummary');
                    tableBody.innerHTML = '';
                    if (data.success && data.transactions.length > 0) {
                        data.transactions.forEach(t => {
                            const isIncome = t.type === 'Income';
                            tableBody.innerHTML += `
                                <tr>
                                    <td>${t.transaction_date}</td><td>${t.description}</td><td><span class="badge bg-secondary">${t.category}</span></td>
                                    <td><span class="badge ${isIncome ? 'bg-success' : 'bg-danger'}">${t.type}</span></td>
                                    <td class="text-end fw-bold ${isIncome ? 'text-success' : 'text-danger'}">${isIncome ? '+' : '-'} ${parseFloat(t.amount).toFixed(2)}</td>
                                    <td><button class="btn btn-sm btn-outline-danger" onclick="deleteTransaction(${t.id})"><i class="bi bi-trash"></i></button></td>
                                </tr>`;
                        });
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No transactions found for this period.</td></tr>';
                    }
                    summaryDiv.innerHTML = `
                        <div class="row text-center">
                            <div class="col-4"><h5>Total Income<br><span class="text-success">${parseFloat(data.summary.total_income || 0).toFixed(2)}</span></h5></div>
                            <div class="col-4"><h5>Total Expense<br><span class="text-danger">${parseFloat(data.summary.total_expense || 0).toFixed(2)}</span></h5></div>
                            <div class="col-4"><h5>Net Profit / Loss<br><span class="text-primary">${parseFloat(data.summary.net_profit || 0).toFixed(2)}</span></h5></div>
                        </div>`;
                });
        }
        
        function loadStudentsForDropdown() {
             fetch(`process_schedule.php?action=get_dropdowns&branch=${branchName}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        const studentSelect = document.getElementById('studentId');
                        studentSelect.innerHTML = '<option value="">(None)</option>';
                        data.students.forEach(item => {
                            studentSelect.innerHTML += `<option value="${item.id}">${item.full_name} (${item.registration_code})</option>`;
                        });
                    }
                });
        }
        
        function saveTransaction() {
            const form = document.getElementById('financeForm');
            const formData = new FormData(form);
            fetch('process_finance.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) {
                    form.reset();
                    document.getElementById('transaction_date').value = "<?php echo date('Y-m-d'); ?>";
                    loadTransactions();
                } else { alert('Error: ' + data.message); }
            });
        }

        function deleteTransaction(id) {
            if (!confirm('Are you sure you want to delete this transaction?')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('transaction_id', id);
            fetch('process_finance.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) { loadTransactions(); } 
                else { alert('Error: ' + data.message); }
            });
        }
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