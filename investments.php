<?php
// investments.php - .1 Investment Calculation & Capital Management
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('investments', 'view');

// Handle Add Investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_investment') {
    require_permission('investments', 'add');
    $investor_name = trim($_POST['investor_name'] ?? '');
    $investment_type = trim($_POST['investment_type'] ?? 'Capital');
    $amount = (float)($_POST['amount'] ?? 0);
    $investment_date = trim($_POST['investment_date'] ?? date('Y-m-d'));
    $payment_mode = trim($_POST['payment_mode'] ?? 'Bank Transfer');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($investor_name) || $amount <= 0 || empty($purpose)) {
        set_flash_message('danger', 'Please provide Investor Name, valid Amount, and Purpose.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO investments (investor_name, investment_type, amount, investment_date, payment_mode, reference_no, purpose, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)
        ");
        $stmt->execute([$investor_name, $investment_type, $amount, $investment_date, $payment_mode, $reference_no, $purpose, $notes]);
        set_flash_message('success', 'Investment record of ' . format_money($amount) . ' added successfully!');
        header('Location: investments.php');
        exit;
    }
}

// Handle Status Change or Delete
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    require_permission('investments', 'edit');
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT status FROM investments WHERE id = ?");
    $stmt->execute([$id]);
    $curr = $stmt->fetchColumn();
    if ($curr) {
        $new_status = $curr === 'Active' ? 'Returned' : 'Active';
        $up = $pdo->prepare("UPDATE investments SET status = ? WHERE id = ?");
        $up->execute([$new_status, $id]);
        set_flash_message('success', "Investment #{$id} status updated to {$new_status}.");
    }
    header('Location: investments.php');
    exit;
}

if (isset($_GET['delete_id'])) {
    require_permission('investments', 'delete');
    $id = (int)$_GET['delete_id'];
    $del = $pdo->prepare("DELETE FROM investments WHERE id = ?");
    $del->execute([$id]);
    set_flash_message('success', "Investment #{$id} deleted successfully.");
    header('Location: investments.php');
    exit;
}

// Query Investments
$filter_type = $_GET['type'] ?? '';
$sql = "SELECT * FROM investments WHERE 1=1";
$params = [];

if (!empty($filter_type)) {
    $sql .= " AND investment_type = ?";
    $params[] = $filter_type;
}
$sql .= " ORDER BY investment_date DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$investments = $stmt->fetchAll();

// Metrics
$total_capital = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE status = 'Active'")->fetchColumn();
$partner_equity = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE investment_type = 'Partner Equity' AND status = 'Active'")->fetchColumn();
$loans = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE investment_type IN ('Bank Loan', 'Personal Loan') AND status = 'Active'")->fetchColumn();
$returned = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE status = 'Returned'")->fetchColumn();

$page_title = 'Investment Calculation';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">.1 Investment Calculation</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                Capital Portfolio
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Track partner equity, bank/personal loans, infrastructure capital injections, and ROI.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="export.php?type=investments" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </a>
        <?php if (has_permission('investments', 'add')): ?>
            <button class="btn btn-sm btn-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addInvestmentModal">
                <i class="fa-solid fa-plus me-1"></i> + Record Investment
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Active Capital Pool</div>
            <div class="kpi-value text-cyan-400"><?= format_money($total_capital) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Total working infrastructure capital</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Partner Equity Share</div>
            <div class="kpi-value text-emerald-400"><?= format_money($partner_equity) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Shareholders capital injection</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-amber h-100">
            <div class="kpi-title">Debt & Bank Loans</div>
            <div class="kpi-value text-amber-400"><?= format_money($loans) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Borrowed investment funds</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">Returned / Liquidated</div>
            <div class="kpi-value text-purple-400"><?= format_money($returned) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Capital returned to investors</div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card card-noc">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                <i class="fa-solid fa-coins text-cyan-400 me-2"></i>Investment Ledger
            </span>
            <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs"><?= count($investments) ?> Entries</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Search Input -->
            <div class="input-group input-group-sm" style="max-width: 220px;">
                <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" class="form-control form-control-noc" placeholder="Search investor..." data-noc-search="investmentsTable">
            </div>
            <!-- Filter Dropdown -->
            <select class="form-select form-select-noc form-select-sm" style="width: auto;" onchange="location.href='investments.php?type=' + this.value">
                <option value="">All Types</option>
                <option value="Capital" <?= $filter_type === 'Capital' ? 'selected' : '' ?>>Capital</option>
                <option value="Partner Equity" <?= $filter_type === 'Partner Equity' ? 'selected' : '' ?>>Partner Equity</option>
                <option value="Bank Loan" <?= $filter_type === 'Bank Loan' ? 'selected' : '' ?>>Bank Loan</option>
                <option value="Personal Loan" <?= $filter_type === 'Personal Loan' ? 'selected' : '' ?>>Personal Loan</option>
                <option value="Other" <?= $filter_type === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-noc align-middle" id="investmentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Investor / Partner</th>
                        <th>Type</th>
                        <th>Purpose / Asset</th>
                        <th>Date</th>
                        <th>Method & Ref</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($investments)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-slate-500 font-mono">No investment records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($investments as $inv): ?>
                            <tr>
                                <td class="font-mono text-slate-500" style="font-size:11px;">#<?= $inv['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-slate-200"><?= htmlspecialchars($inv['investor_name']) ?></div>
                                    <?php if (!empty($inv['notes'])): ?>
                                        <div class="text-slate-500 text-xs text-truncate" style="max-width: 200px;"><?= htmlspecialchars($inv['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $inv_type = $inv['investment_type'];
                                    $inv_badge = 'badge-investment-partner';
                                    $inv_icon = 'fa-handshake';
                                    if (stripos($inv_type, 'loan') !== false) {
                                        $inv_badge = 'badge-investment-loan';
                                        $inv_icon = 'fa-landmark';
                                    } elseif (stripos($inv_type, 'capital') !== false) {
                                        $inv_badge = 'badge-investment-capital';
                                        $inv_icon = 'fa-chart-line';
                                    }
                                    ?>
                                    <span class="badge <?= $inv_badge ?> font-mono text-xs">
                                        <i class="fa-solid <?= $inv_icon ?> me-1"></i><?= htmlspecialchars($inv_type) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-slate-300 text-xs fw-semibold"><?= htmlspecialchars($inv['purpose']) ?></div>
                                </td>
                                <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($inv['investment_date']) ?></td>
                                <td>
                                    <div class="text-slate-300 text-xs"><?= htmlspecialchars($inv['payment_mode']) ?></div>
                                    <?php if (!empty($inv['reference_no'])): ?>
                                        <span class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($inv['reference_no']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-mono fw-bold text-cyan-400 fs-6">
                                    <?= format_money($inv['amount']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($inv['status'] === 'Active'): ?>
                                        <span class="badge badge-active"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-suspended">Returned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (has_permission('investments', 'edit')): ?>
                                            <a href="investments.php?toggle_status=1&id=<?= $inv['id'] ?>" class="btn btn-outline-secondary border-slate-800 p-1 px-2 text-slate-300" title="Toggle Status (Active/Returned)">
                                                <i class="fa-solid fa-rotate"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('investments', 'delete')): ?>
                                            <a href="investments.php?delete_id=<?= $inv['id'] ?>" class="btn btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Permanently remove investment record #<?= $inv['id'] ?> from <?= htmlspecialchars($inv['investor_name']) ?>?" data-item-name="<?= htmlspecialchars($inv['investor_name']) ?> (<?= htmlspecialchars($inv['investment_type']) ?>)" data-item-code="Amount: <?= format_money($inv['amount']) ?>" title="Delete Record">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Investment -->
<div class="modal fade" id="addInvestmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-coins text-cyan-400 me-2"></i>Record New Investment
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="investments.php" data-confirm-add="Confirm recording this new capital investment into the ledger?" data-confirm-title="Confirm Capital Investment">
                <input type="hidden" name="action" value="add_investment">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-noc">Investor / Partner Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="investor_name" class="form-control form-control-noc" required placeholder="e.g. Shahidul Alam, Bank Asia SME">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Investment Type <span class="text-rose-400">*</span></label>
                            <select name="investment_type" class="form-select form-select-noc" required>
                                <option value="Capital">Capital (Founder Pool)</option>
                                <option value="Partner Equity" selected>Partner Equity (Share)</option>
                                <option value="Bank Loan">Bank Loan</option>
                                <option value="Personal Loan">Personal Loan</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Amount (BDT) <span class="text-rose-400">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-noc font-mono" required placeholder="500000">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Date <span class="text-rose-400">*</span></label>
                            <input type="date" name="investment_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Payment Mode</label>
                            <select name="payment_mode" class="form-select form-select-noc">
                                <option value="Bank Transfer">Bank Transfer / Cheque</option>
                                <option value="Cash">Cash</option>
                                <option value="bKash / Nagad">bKash / Nagad</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-noc">Reference # / TrxID / Cheque No</label>
                        <input type="text" name="reference_no" class="form-control form-control-noc font-mono" placeholder="e.g. BRAC-TRX-10293">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-noc">Investment Purpose / Asset Detail <span class="text-rose-400">*</span></label>
                        <input type="text" name="purpose" class="form-control form-control-noc" required placeholder="e.g. Purchase of 16-Port OLT & Core Mikrotik Router">
                    </div>

                    <div class="mb-0">
                        <label class="form-label-noc">Notes / Equity Terms</label>
                        <textarea name="notes" class="form-control form-control-noc" rows="2" placeholder="e.g. 25% profit share on quarterly settlement"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Save Investment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

