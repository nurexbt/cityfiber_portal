<?php
// expenses.php - 1. Expense Calculation & Outflow Management
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('expenses', 'view');
$user_data = get_current_user_data();

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    require_permission('expenses', 'add');
    $category = trim($_POST['category'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $payment_mode = trim($_POST['payment_mode'] ?? 'Cash');
    $paid_to = trim($_POST['paid_to'] ?? '');
    $paid_by = trim($_POST['paid_by'] ?? $user_data['username']);
    $reference_no = trim($_POST['reference_no'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Generate unique Voucher No: EXP-YYYYMM-XXXX
    $prefix = 'EXP-' . date('Ym', strtotime($expense_date)) . '-';
    $last_id = $pdo->query("SELECT id FROM expenses ORDER BY id DESC LIMIT 1")->fetchColumn();
    $voucher_no = $prefix . str_pad(($last_id ? $last_id + 1 : 1), 4, '0', STR_PAD_LEFT);

    if (empty($title) || $amount <= 0 || empty($category)) {
        set_flash_message('danger', 'Please provide a Category, Title, and valid Amount.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO expenses (voucher_no, category, title, amount, expense_date, payment_mode, paid_to, paid_by, reference_no, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$voucher_no, $category, $title, $amount, $expense_date, $payment_mode, $paid_to, $paid_by, $reference_no, $description]);
        set_flash_message('success', "Expense voucher {$voucher_no} of " . format_money($amount) . " logged successfully!");
        header('Location: expenses.php');
        exit;
    }
}

// Handle Delete Expense
if (isset($_GET['delete_id'])) {
    require_permission('expenses', 'delete');
    $id = (int)$_GET['delete_id'];
    $del = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    $del->execute([$id]);
    set_flash_message('success', "Expense record #{$id} deleted successfully.");
    header('Location: expenses.php');
    exit;
}

// Filtering
$filter_category = $_GET['category'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');

$sql = "SELECT * FROM expenses WHERE 1=1";
$params = [];

if (!empty($filter_month)) {
    $sql .= " AND DATE_FORMAT(expense_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}
if (!empty($filter_category)) {
    $sql .= " AND category = ?";
    $params[] = $filter_category;
}
$sql .= " ORDER BY expense_date DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Expense Metrics
$total_month_exp = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '{$filter_month}'")->fetchColumn();
$bandwidth_cost = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category = 'Bandwidth & Upstream' AND DATE_FORMAT(expense_date, '%Y-%m') = '{$filter_month}'")->fetchColumn();
$fiber_hardware = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category = 'Fiber & Network Gear' AND DATE_FORMAT(expense_date, '%Y-%m') = '{$filter_month}'")->fetchColumn();
$office_rent_util = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category IN ('Office Rent', 'Utilities & Electricity') AND DATE_FORMAT(expense_date, '%Y-%m') = '{$filter_month}'")->fetchColumn();

// Available categories
$categories_list = [
    'Bandwidth & Upstream',
    'Fiber & Network Gear',
    'Office Rent',
    'Utilities & Electricity',
    'Server & Software',
    'Marketing & Promotion',
    'Conveyance & Transport',
    'Legal & Regulatory',
    'Tea & Refreshment',
    'Miscellaneous'
];

$page_title = 'Expense Calculation';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">1. Expense Calculation</h4>
            <span class="badge badge-header-rose font-mono text-xs">
                Month: <?= date('M Y', strtotime($filter_month . '-01')) ?>
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Record and audit ISP operational overheads: IP transit bandwidth, fiber maintenance, rent, utilities, and transport.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="export.php?type=expenses" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </a>
        <?php if (has_permission('expenses', 'add')): ?>
            <button class="btn btn-sm btn-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fa-solid fa-plus me-1"></i> + Record Expense
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-rose h-100">
            <div class="kpi-title">Monthly Total Expense</div>
            <div class="kpi-value text-rose-400"><?= format_money($total_month_exp) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Selected month total outflow</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Bandwidth & Transit</div>
            <div class="kpi-value text-cyan-400"><?= format_money($bandwidth_cost) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Upstream & NTTN transmission</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Fiber & Hardware</div>
            <div class="kpi-value text-emerald-400"><?= format_money($fiber_hardware) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">ONUs, switches, fiber repairs</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-amber h-100">
            <div class="kpi-title">Office Rent & Power</div>
            <div class="kpi-value text-amber-400"><?= format_money($office_rent_util) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Office premise & server AC</div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card card-noc">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                <i class="fa-solid fa-receipt text-rose-400 me-2"></i>Expense Vouchers
            </span>
            <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs"><?= count($expenses) ?> Records</span>
        </div>
        <form method="GET" action="expenses.php" class="d-flex align-items-center flex-wrap gap-2">
            <!-- Search Input -->
            <div class="input-group input-group-sm" style="max-width: 190px;">
                <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" class="form-control form-control-noc" placeholder="Search expense..." data-noc-search="expensesTable">
            </div>

            <!-- Month Filter -->
            <input type="month" name="month" class="form-control form-control-noc form-control-sm font-mono" value="<?= htmlspecialchars($filter_month) ?>" onchange="this.form.submit()" style="width: auto;">

            <!-- Category Filter -->
            <select name="category" class="form-select form-select-noc form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories_list as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-noc align-middle" id="expensesTable">
                <thead>
                    <tr>
                        <th>Voucher #</th>
                        <th>Category</th>
                        <th>Title / Description</th>
                        <th>Date</th>
                        <th>Paid To & By</th>
                        <th>Method & Ref</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-slate-500 font-mono">No expenses found for selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td>
                                    <span class="font-mono text-cyan-400 fw-semibold text-xs"><?= htmlspecialchars($exp['voucher_no']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-category font-mono text-xs">
                                        <i class="fa-solid fa-tag me-1"></i><?= htmlspecialchars($exp['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate-200"><?= htmlspecialchars($exp['title']) ?></div>
                                    <?php if (!empty($exp['description'])): ?>
                                        <div class="text-slate-500 text-xs text-truncate" style="max-width: 250px;"><?= htmlspecialchars($exp['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($exp['expense_date']) ?></td>
                                <td>
                                    <div class="text-slate-300 text-xs"><?= htmlspecialchars($exp['paid_to'] ?: '—') ?></div>
                                    <div class="text-slate-500 text-xs font-mono">By: <?= htmlspecialchars($exp['paid_by']) ?></div>
                                </td>
                                <td>
                                    <?php
                                    $expm = strtolower($exp['payment_mode']);
                                    $expm_cls = 'badge-method';
                                    if (strpos($expm, 'bkash') !== false) $expm_cls = 'badge-method-bkash';
                                    elseif (strpos($expm, 'nagad') !== false) $expm_cls = 'badge-method-nagad';
                                    elseif (strpos($expm, 'bank') !== false) $expm_cls = 'badge-method-bank';
                                    elseif (strpos($expm, 'cash') !== false) $expm_cls = 'badge-method-cash';
                                    ?>
                                    <span class="badge <?= $expm_cls ?> font-mono" style="font-size:10px;">
                                        <?= htmlspecialchars($exp['payment_mode']) ?>
                                    </span>
                                    <?php if (!empty($exp['reference_no'])): ?>
                                        <div class="font-mono text-slate-500" style="font-size:10px;"><?= htmlspecialchars($exp['reference_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-mono fw-bold text-rose-400 fs-6">
                                    -<?= format_money($exp['amount']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if (has_permission('expenses', 'delete')): ?>
                                        <a href="expenses.php?delete_id=<?= $exp['id'] ?>" class="btn btn-sm btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Permanently delete expense voucher #<?= $exp['id'] ?> (<?= htmlspecialchars($exp['category']) ?>)?" data-item-name="<?= htmlspecialchars($exp['category']) ?>" data-item-code="Amount: <?= format_money($exp['amount']) ?>" title="Delete Expense">
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

<!-- Modal: Add Expense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-receipt text-rose-400 me-2"></i>Record New Expense
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="expenses.php" data-confirm-add="Confirm posting this expense voucher into monthly accounts?" data-confirm-title="Confirm Expense Voucher">
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Category <span class="text-rose-400">*</span></label>
                            <select name="category" class="form-select form-select-noc" required>
                                <?php foreach ($categories_list as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Amount (BDT) <span class="text-rose-400">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-noc font-mono" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-noc">Expense Title / Particulars <span class="text-rose-400">*</span></label>
                        <input type="text" name="title" class="form-control form-control-noc" required placeholder="e.g. Summit 1Gbps Bandwidth, 24-Port Gigabit Switch">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Expense Date <span class="text-rose-400">*</span></label>
                            <input type="date" name="expense_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Payment Mode</label>
                            <select name="payment_mode" class="form-select form-select-noc">
                                <option value="Cash" selected>Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Bank">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Paid To (Vendor / Person)</label>
                            <input type="text" name="paid_to" class="form-control form-control-noc" placeholder="e.g. Fiber@Home Ltd, Landlord">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Invoice / Ref / TrxID</label>
                            <input type="text" name="reference_no" class="form-control form-control-noc font-mono" placeholder="e.g. INV-8821">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label-noc">Description / Memo</label>
                        <textarea name="description" class="form-control form-control-noc" rows="2" placeholder="Additional notes or itemized details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

