<?php
// employees.php - 4. Employees Cost, Directory & Monthly Payroll
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('employees', 'view');
$user_data = get_current_user_data();

// Handle Add Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    require_permission('employees', 'add');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $department = trim($_POST['department'] ?? 'NOC & Technical');
    $basic_salary = (float)($_POST['basic_salary'] ?? 0);
    $allowance = (float)($_POST['allowance'] ?? 0);
    $join_date = trim($_POST['join_date'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'Active');

    // Generate emp_code: EMP-XXX
    $last_e = $pdo->query("SELECT id FROM employees ORDER BY id DESC LIMIT 1")->fetchColumn();
    $emp_code = 'EMP-' . str_pad(($last_e ? $last_e + 101 : 101), 3, '0', STR_PAD_LEFT);

    if (empty($name) || empty($phone) || empty($designation) || $basic_salary <= 0) {
        set_flash_message('danger', 'Please enter Name, Phone, Designation, and Basic Salary.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO employees (emp_code, name, phone, email, designation, department, basic_salary, allowance, join_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$emp_code, $name, $phone, $email, $designation, $department, $basic_salary, $allowance, $join_date, $status]);
        set_flash_message('success', "Employee {$name} ({$emp_code}) added successfully!");
        header('Location: employees.php');
        exit;
    }
}

// Handle Record Salary Disbursal (Employees Cost)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disburse_salary') {
    require_permission('employees', 'add');
    $employee_id = (int)$_POST['employee_id'];
    $salary_month = trim($_POST['salary_month'] ?? date('Y-m'));
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $basic_amount = (float)($_POST['basic_amount'] ?? 0);
    $allowance = (float)($_POST['allowance'] ?? 0);
    $deduction = (float)($_POST['deduction'] ?? 0);
    $net_paid = max(0, ($basic_amount + $allowance) - $deduction);
    $payment_mode = trim($_POST['payment_mode'] ?? 'Bank Transfer');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // Voucher: SAL-YYYYMM-XXXX
    $prefix = 'SAL-' . str_replace('-', '', $salary_month) . '-';
    $last_sal = $pdo->query("SELECT id FROM salary_payments ORDER BY id DESC LIMIT 1")->fetchColumn();
    $voucher_no = $prefix . str_pad(($last_sal ? $last_sal + 1 : 1), 3, '0', STR_PAD_LEFT);

    if ($employee_id <= 0 || $net_paid <= 0) {
        set_flash_message('danger', 'Please select an employee and enter valid payment amounts.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO salary_payments (voucher_no, employee_id, salary_month, payment_date, basic_amount, allowance, deduction, net_paid, payment_mode, reference_no, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$voucher_no, $employee_id, $salary_month, $payment_date, $basic_amount, $allowance, $deduction, $net_paid, $payment_mode, $reference_no, $remarks]);

        set_flash_message('success', "Salary voucher {$voucher_no} of " . format_money($net_paid) . " disbursed successfully!");
        header('Location: employees.php?tab=payroll');
        exit;
    }
}

// Delete Salary Record
if (isset($_GET['delete_salary_id'])) {
    require_permission('employees', 'delete');
    $sal_id = (int)$_GET['delete_salary_id'];
    $pdo->prepare("DELETE FROM salary_payments WHERE id = ?")->execute([$sal_id]);
    set_flash_message('success', "Salary payment #{$sal_id} removed.");
    header('Location: employees.php?tab=payroll');
    exit;
}

// Data Queries
$filter_month = $_GET['month'] ?? date('Y-m');

$employees = $pdo->query("SELECT * FROM employees ORDER BY id ASC")->fetchAll();

$salaries_stmt = $pdo->prepare("
    SELECT sp.*, e.name AS emp_name, e.emp_code, e.designation, e.department 
    FROM salary_payments sp 
    JOIN employees e ON sp.employee_id = e.id 
    WHERE sp.salary_month = ? 
    ORDER BY sp.payment_date DESC, sp.id DESC
");
$salaries_stmt->execute([$filter_month]);
$salary_records = $salaries_stmt->fetchAll();

// Cost Calculations
$active_employees_count = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
$total_monthly_payroll = $pdo->query("SELECT COALESCE(SUM(basic_salary + allowance), 0) FROM employees WHERE status = 'Active'")->fetchColumn();
$total_disbursed_month = $pdo->query("SELECT COALESCE(SUM(net_paid), 0) FROM salary_payments WHERE salary_month = '{$filter_month}'")->fetchColumn();
$noc_tech_cost = $pdo->query("SELECT COALESCE(SUM(basic_salary + allowance), 0) FROM employees WHERE status = 'Active' AND department IN ('NOC & Technical', 'Field Engineering')")->fetchColumn();

$active_tab = $_GET['tab'] ?? 'staff';

$page_title = 'Employees Cost & Payroll';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">4. Employees Cost & Payroll</h4>
            <span class="badge badge-header-purple font-mono text-xs">
                Workforce Operations
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Manage ISP engineering staff, fiber splicers, field technicians, monthly salaries, and operational payroll costs.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (has_permission('employees', 'add')): ?>
            <button class="btn btn-sm btn-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#disburseSalaryModal">
                <i class="fa-solid fa-money-bill-wave me-1"></i> + Disburse Salary
            </button>
            <button class="btn btn-sm btn-outline-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                <i class="fa-solid fa-user-plus me-1"></i> + Add Employee
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">Monthly Payroll Budget</div>
            <div class="kpi-value text-purple-400"><?= format_money($total_monthly_payroll) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Base monthly employee cost</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Disbursed (<?= date('M Y', strtotime($filter_month . '-01')) ?>)</div>
            <div class="kpi-value text-emerald-400"><?= format_money($total_disbursed_month) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Salaries paid out this month</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Technical & Field Cost</div>
            <div class="kpi-value text-cyan-400"><?= format_money($noc_tech_cost) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">NOC & fiber linesmen compensation</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-amber h-100">
            <div class="kpi-title">Active Team Members</div>
            <div class="kpi-value text-amber-400"><?= $active_employees_count ?> Staff</div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">ISP office & field manpower</div>
        </div>
    </div>
</div>

<!-- Main Tabs Card -->
<div class="card card-noc">
    <div class="card-header p-2">
        <ul class="nav nav-pills nav-fill text-xs font-mono" id="empTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab !== 'payroll' ? 'active' : '' ?> text-slate-300" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staffTabPane" type="button" role="tab">
                    <i class="fa-solid fa-users me-1"></i> Employees Directory (<?= count($employees) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab === 'payroll' ? 'active' : '' ?> text-slate-300" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payrollTabPane" type="button" role="tab">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i> Salary Disbursal Ledger (<?= count($salary_records) ?>)
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="empTabContent">
            <!-- Staff Directory Tab -->
            <div class="tab-pane fade <?= $active_tab !== 'payroll' ? 'show active' : '' ?>" id="staffTabPane" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom border-slate-800">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">Staff Records</span>
                    <div class="input-group input-group-sm" style="max-width: 220px;">
                        <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control form-control-noc" placeholder="Search staff..." data-noc-search="employeesTable">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-noc align-middle" id="employeesTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Contact</th>
                                <th class="text-end">Basic Salary</th>
                                <th class="text-end">Allowance</th>
                                <th class="text-end">Total Package</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Pay Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td>
                                        <span class="font-mono text-cyan-400 fw-bold"><?= htmlspecialchars($e['emp_code']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-slate-200"><?= htmlspecialchars($e['name']) ?></div>
                                        <div class="text-slate-500 font-mono" style="font-size:10px;">Joined: <?= htmlspecialchars($e['join_date']) ?></div>
                                    </td>
                                    <td>
                                        <span class="text-slate-300 text-xs fw-semibold"><?= htmlspecialchars($e['designation']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $dept = $e['department'];
                                        $dept_badge = 'badge-dept-default';
                                        $dept_icon = 'fa-building';
                                        if (stripos($dept, 'NOC') !== false || stripos($dept, 'Technical') !== false) {
                                            $dept_badge = 'badge-dept-noc';
                                            $dept_icon = 'fa-network-wired';
                                        } elseif (stripos($dept, 'Field') !== false || stripos($dept, 'Engineering') !== false || stripos($dept, 'Splicer') !== false) {
                                            $dept_badge = 'badge-dept-field';
                                            $dept_icon = 'fa-screwdriver-wrench';
                                        } elseif (stripos($dept, 'Billing') !== false || stripos($dept, 'Accounts') !== false) {
                                            $dept_badge = 'badge-dept-billing';
                                            $dept_icon = 'fa-file-invoice-dollar';
                                        } elseif (stripos($dept, 'Support') !== false || stripos($dept, 'Helpdesk') !== false || stripos($dept, 'Operations') !== false) {
                                            $dept_badge = 'badge-dept-support';
                                            $dept_icon = 'fa-headset';
                                        }
                                        ?>
                                        <span class="badge <?= $dept_badge ?> font-mono text-xs">
                                            <i class="fa-solid <?= $dept_icon ?> me-1"></i><?= htmlspecialchars($dept) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-slate-300 font-mono text-xs"><i class="fa-solid fa-phone me-1 text-slate-500"></i><?= htmlspecialchars($e['phone']) ?></div>
                                        <?php if (!empty($e['email'])): ?>
                                            <div class="text-slate-500 text-xs"><?= htmlspecialchars($e['email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end font-mono text-slate-300"><?= format_money($e['basic_salary']) ?></td>
                                    <td class="text-end font-mono text-slate-400"><?= format_money($e['allowance']) ?></td>
                                    <td class="text-end font-mono fw-bold text-purple-400 fs-6">
                                        <?= format_money($e['basic_salary'] + $e['allowance']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($e['status'] === 'Active'): ?>
                                            <span class="badge badge-active"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-suspended"><?= htmlspecialchars($e['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (has_permission('employees', 'add')): ?>
                                            <button class="btn btn-sm btn-cyan font-mono text-xs p-1 px-2" onclick='openSalaryDisburseModal(<?= json_encode($e) ?>)'>
                                                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Disburse
                                            </button>
                                        <?php else: ?>
                                            <span class="text-slate-500 font-mono text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payroll History Tab -->
            <div class="tab-pane fade <?= $active_tab === 'payroll' ? 'show active' : '' ?>" id="payrollTabPane" role="tabpanel">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center p-3 border-bottom border-slate-800 gap-2">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                        Monthly Disbursed Payroll Records
                    </span>
                    <form method="GET" action="employees.php" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="tab" value="payroll">
                        <input type="month" name="month" class="form-control form-control-noc form-control-sm font-mono" value="<?= htmlspecialchars($filter_month) ?>" onchange="this.form.submit()" style="width: auto;">
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Voucher #</th>
                                <th>Staff Member</th>
                                <th>Department</th>
                                <th>Month</th>
                                <th>Pay Date</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Allowance</th>
                                <th class="text-end">Deduction</th>
                                <th class="text-end">Net Paid</th>
                                <th>Method & Ref</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($salary_records)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-slate-500 font-mono">No salary payments disbursed for <?= htmlspecialchars($filter_month) ?>.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salary_records as $sr): ?>
                                    <tr>
                                        <td>
                                            <span class="font-mono text-cyan-400 fw-bold"><?= htmlspecialchars($sr['voucher_no']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-slate-200"><?= htmlspecialchars($sr['emp_name']) ?></div>
                                            <div class="text-slate-500 font-mono" style="font-size:10px;"><?= htmlspecialchars($sr['emp_code']) ?> • <?= htmlspecialchars($sr['designation']) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $sdept = $sr['department'];
                                            $sdept_badge = 'badge-dept-default';
                                            if (stripos($sdept, 'NOC') !== false || stripos($sdept, 'Technical') !== false) {
                                                $sdept_badge = 'badge-dept-noc';
                                            } elseif (stripos($sdept, 'Field') !== false || stripos($sdept, 'Engineering') !== false) {
                                                $sdept_badge = 'badge-dept-field';
                                            } elseif (stripos($sdept, 'Billing') !== false || stripos($sdept, 'Accounts') !== false) {
                                                $sdept_badge = 'badge-dept-billing';
                                            } elseif (stripos($sdept, 'Support') !== false || stripos($sdept, 'Helpdesk') !== false) {
                                                $sdept_badge = 'badge-dept-support';
                                            }
                                            ?>
                                            <span class="badge <?= $sdept_badge ?> font-mono text-xs">
                                                <?= htmlspecialchars($sdept) ?>
                                            </span>
                                        </td>
                                        <td class="font-mono text-slate-300 text-xs"><?= htmlspecialchars($sr['salary_month']) ?></td>
                                        <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($sr['payment_date']) ?></td>
                                        <td class="text-end font-mono text-slate-300"><?= format_money($sr['basic_amount']) ?></td>
                                        <td class="text-end font-mono text-slate-400">+<?= format_money($sr['allowance']) ?></td>
                                        <td class="text-end font-mono text-rose-400">-<?= format_money($sr['deduction']) ?></td>
                                        <td class="text-end font-mono fw-bold text-emerald-400 fs-6">
                                            <?= format_money($sr['net_paid']) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $spm = strtolower($sr['payment_mode']);
                                            $spm_cls = 'badge-method';
                                            if (strpos($spm, 'bkash') !== false) $spm_cls = 'badge-method-bkash';
                                            elseif (strpos($spm, 'nagad') !== false) $spm_cls = 'badge-method-nagad';
                                            elseif (strpos($spm, 'bank') !== false) $spm_cls = 'badge-method-bank';
                                            elseif (strpos($spm, 'cash') !== false) $spm_cls = 'badge-method-cash';
                                            ?>
                                            <span class="badge <?= $spm_cls ?> font-mono" style="font-size:10px;">
                                                <?= htmlspecialchars($sr['payment_mode']) ?>
                                            </span>
                                            <?php if (!empty($sr['reference_no'])): ?>
                                                <div class="font-mono text-slate-500" style="font-size:10px;"><?= htmlspecialchars($sr['reference_no']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (has_permission('employees', 'delete')): ?>
                                                <a href="employees.php?delete_salary_id=<?= $sr['id'] ?>" class="btn btn-sm btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Permanently delete salary voucher <?= htmlspecialchars($sr['voucher_no']) ?>?" data-item-name="<?= htmlspecialchars($sr['employee_name']) ?> (<?= htmlspecialchars($sr['emp_code']) ?>)" data-item-code="Net Paid: <?= format_money($sr['net_paid']) ?>" title="Delete Voucher">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Employee -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-user-plus text-cyan-400 me-2"></i>Add Staff Member
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="employees.php" data-confirm-add="Confirm adding this new staff member to employee records?" data-confirm-title="Confirm New Employee">
                <input type="hidden" name="action" value="add_employee">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-noc">Full Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="name" class="form-control form-control-noc" required placeholder="e.g. Mahfuzur Rahman">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Phone <span class="text-rose-400">*</span></label>
                            <input type="text" name="phone" class="form-control form-control-noc font-mono" required placeholder="+880 17XXXXXXXX">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Email</label>
                            <input type="email" name="email" class="form-control form-control-noc" placeholder="staff@cityfiber.bd">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Designation <span class="text-rose-400">*</span></label>
                            <input type="text" name="designation" class="form-control form-control-noc" required placeholder="e.g. NOC Engineer, Fiber Splicer">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Department</label>
                            <select name="department" class="form-select form-select-noc">
                                <option value="NOC & Technical">NOC & Technical</option>
                                <option value="Field Engineering">Field Engineering</option>
                                <option value="Billing & Accounts">Billing & Accounts</option>
                                <option value="Support & Operations">Support & Operations</option>
                                <option value="Management">Management</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Basic Salary (BDT) <span class="text-rose-400">*</span></label>
                            <input type="number" step="0.01" name="basic_salary" class="form-control form-control-noc font-mono" required placeholder="25000">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Allowance (BDT)</label>
                            <input type="number" step="0.01" name="allowance" class="form-control form-control-noc font-mono" value="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <label class="form-label-noc">Joining Date</label>
                            <input type="date" name="join_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Status</label>
                            <select name="status" class="form-select form-select-noc">
                                <option value="Active">Active</option>
                                <option value="On Leave">On Leave</option>
                                <option value="Resigned">Resigned</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Disburse Salary -->
<div class="modal fade" id="disburseSalaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-money-bill-wave text-cyan-400 me-2"></i>Disburse Employee Salary
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="employees.php" data-confirm-add="Confirm issuing this employee salary payment voucher?" data-confirm-title="Confirm Salary Disbursal">
                <input type="hidden" name="action" value="disburse_salary">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-noc">Employee <span class="text-rose-400">*</span></label>
                        <select name="employee_id" id="disburseEmployeeSelect" class="form-select form-select-noc" required onchange="fillEmployeeSalary(this)">
                            <option value="">Select Employee...</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['id'] ?>" data-basic="<?= $e['basic_salary'] ?>" data-allowance="<?= $e['allowance'] ?>">
                                    <?= htmlspecialchars($e['name']) ?> (<?= $e['emp_code'] ?>) - <?= htmlspecialchars($e['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Salary Month <span class="text-rose-400">*</span></label>
                            <input type="month" name="salary_month" class="form-control form-control-noc font-mono" value="<?= date('Y-m') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Disbursal Date <span class="text-rose-400">*</span></label>
                            <input type="date" name="payment_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label-noc">Basic Salary (BDT)</label>
                            <input type="number" step="0.01" name="basic_amount" id="sal_basic_amount" class="form-control form-control-noc font-mono" required onkeyup="calculateSalaryTotals()">
                        </div>
                        <div class="col-4">
                            <label class="form-label-noc">Allowance (BDT)</label>
                            <input type="number" step="0.01" name="allowance" id="sal_allowance" class="form-control form-control-noc font-mono" value="0.00" onkeyup="calculateSalaryTotals()">
                        </div>
                        <div class="col-4">
                            <label class="form-label-noc">Deductions (BDT)</label>
                            <input type="number" step="0.01" name="deduction" id="sal_deduction" class="form-control form-control-noc font-mono" value="0.00" onkeyup="calculateSalaryTotals()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-noc">Net Disbursed Amount (BDT)</label>
                        <input type="text" id="sal_net_paid" class="form-control form-control-noc font-mono fw-bold text-emerald-400 fs-6" readonly value="0.00">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Payment Mode</label>
                            <select name="payment_mode" class="form-select form-select-noc">
                                <option value="Bank Transfer">Bank Transfer (BEFTN)</option>
                                <option value="Cash">Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Reference # / TrxID</label>
                            <input type="text" name="reference_no" class="form-control form-control-noc font-mono" placeholder="e.g. SAL-TX-9912">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label-noc">Remarks</label>
                        <input type="text" name="remarks" class="form-control form-control-noc" placeholder="e.g. October monthly salary + festival bonus">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Disburse & Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillEmployeeSalary(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const basic = opt.getAttribute('data-basic') || '0.00';
    const allowance = opt.getAttribute('data-allowance') || '0.00';

    document.getElementById('sal_basic_amount').value = parseFloat(basic).toFixed(2);
    document.getElementById('sal_allowance').value = parseFloat(allowance).toFixed(2);
    document.getElementById('sal_deduction').value = '0.00';
    calculateSalaryTotals();
}

function openSalaryDisburseModal(emp) {
    const sel = document.getElementById('disburseEmployeeSelect');
    sel.value = emp.id;
    fillEmployeeSalary(sel);
    const modal = new bootstrap.Modal(document.getElementById('disburseSalaryModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

