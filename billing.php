<?php
// billing.php - 3. Customer Bill Calculation & Monthly Billing Generation Engine
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('billing', 'view');
$user_data = get_current_user_data();

$cur_month = $_GET['month'] ?? date('Y-m');

// Handle Bulk Generate Monthly Bills
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_monthly_bills') {
    require_permission('billing', 'add');
    $target_month = trim($_POST['target_month'] ?? date('Y-m'));
    $bill_date = trim($_POST['bill_date'] ?? date('Y-m-01'));
    $due_day = (int)($app_settings['bill_due_day'] ?? 10);
    $due_date = date('Y-m-', strtotime($target_month . '-01')) . str_pad($due_day, 2, '0', STR_PAD_LEFT);

    // Fetch active customers
    $active_customers = $pdo->query("SELECT * FROM customers WHERE status IN ('Active', 'Due')")->fetchAll();

    $generated_count = 0;
    $skipped_count = 0;

    foreach ($active_customers as $cust) {
        // Check if bill already generated for this customer for this month
        $chk = $pdo->prepare("SELECT id FROM bills WHERE customer_id = ? AND billing_month = ?");
        $chk->execute([$cust['id'], $target_month]);
        if ($chk->fetch()) {
            $skipped_count++;
            continue;
        }

        // Calculate bill amounts
        $pkg_amount = (float)$cust['monthly_bill'];
        $prev_due = (float)$cust['total_due'];
        $total_payable = $pkg_amount + $prev_due;

        $bill_number = 'INV-' . str_replace('-', '', $target_month) . '-' . str_pad($cust['id'], 4, '0', STR_PAD_LEFT);

        $ins = $pdo->prepare("
            INSERT INTO bills (
                bill_number, customer_id, billing_month, bill_date, due_date, 
                package_amount, extra_charge, discount, previous_due, total_payable, 
                paid_amount, due_amount, status, generated_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00, ?, ?, 0.00, ?, 'Unpaid', ?, 'Automated Monthly Billing')
        ");
        $ins->execute([
            $bill_number, $cust['id'], $target_month, $bill_date, $due_date,
            $pkg_amount, $prev_due, $total_payable, $total_payable, $user_data['id']
        ]);

        // Update customer total_due
        $up = $pdo->prepare("UPDATE customers SET total_due = ? WHERE id = ?");
        $up->execute([$total_payable, $cust['id']]);

        $generated_count++;
    }

    set_flash_message('success', "Billing Engine: Generated {$generated_count} invoices for {$target_month}. ({$skipped_count} existing skipped)");
    header("Location: billing.php?month={$target_month}");
    exit;
}

// Handle Single Custom Bill Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_custom_bill') {
    require_permission('billing', 'add');
    $customer_id = (int)$_POST['customer_id'];
    $billing_month = trim($_POST['billing_month'] ?? date('Y-m'));
    $bill_date = trim($_POST['bill_date'] ?? date('Y-m-d'));
    $due_date = trim($_POST['due_date'] ?? date('Y-m-10'));
    $package_amount = (float)($_POST['package_amount'] ?? 0);
    $extra_charge = (float)($_POST['extra_charge'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $previous_due = (float)($_POST['previous_due'] ?? 0);
    $notes = trim($_POST['notes'] ?? 'Manual Invoice');

    $total_payable = max(0, ($package_amount + $extra_charge + $previous_due) - $discount);

    // Bill number
    $bill_number = 'INV-' . str_replace('-', '', $billing_month) . '-' . $customer_id . '-' . rand(100, 999);

    $stmt = $pdo->prepare("
        INSERT INTO bills (
            bill_number, customer_id, billing_month, bill_date, due_date, 
            package_amount, extra_charge, discount, previous_due, total_payable, 
            paid_amount, due_amount, status, generated_by, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 'Unpaid', ?, ?)
    ");
    $stmt->execute([
        $bill_number, $customer_id, $billing_month, $bill_date, $due_date,
        $package_amount, $extra_charge, $discount, $previous_due, $total_payable,
        $total_payable, $user_data['id'], $notes
    ]);

    // Update customer total due
    $upd = $pdo->prepare("UPDATE customers SET total_due = total_due + ? WHERE id = ?");
    $upd->execute([$total_payable, $customer_id]);

    set_flash_message('success', "Invoice {$bill_number} generated successfully!");
    header("Location: billing.php?month={$billing_month}");
    exit;
}

// Delete Bill
if (isset($_GET['delete_bill_id'])) {
    require_permission('billing', 'delete');
    $del_b_id = (int)$_GET['delete_bill_id'];
    $stmt = $pdo->prepare("SELECT customer_id, due_amount, bill_number FROM bills WHERE id = ?");
    $stmt->execute([$del_b_id]);
    $b_row = $stmt->fetch();
    if ($b_row) {
        $pdo->prepare("UPDATE customers SET total_due = GREATEST(0, total_due - ?) WHERE id = ?")->execute([$b_row['due_amount'], $b_row['customer_id']]);
        $pdo->prepare("DELETE FROM payments WHERE bill_id = ?")->execute([$del_b_id]);
        $pdo->prepare("DELETE FROM bills WHERE id = ?")->execute([$del_b_id]);
        set_flash_message('success', "Invoice {$b_row['bill_number']} removed and balance adjusted.");
    }
    header("Location: billing.php?month=" . urlencode($cur_month));
    exit;
}

// Filtering
$filter_status = $_GET['status'] ?? '';
$filter_zone = $_GET['zone_id'] ?? '';

$sql = "
    SELECT b.*, c.customer_code, c.name AS customer_name, c.phone, c.address, z.name AS zone_name, p.name AS package_name
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    LEFT JOIN zones z ON c.zone_id = z.id
    LEFT JOIN packages p ON c.package_id = p.id
    WHERE 1=1
";
$params = [];

if (!empty($cur_month)) {
    $sql .= " AND b.billing_month = ?";
    $params[] = $cur_month;
}
if (!empty($filter_status)) {
    $sql .= " AND b.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_zone)) {
    $sql .= " AND c.zone_id = ?";
    $params[] = $filter_zone;
}
$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// KPI Calculations for the selected month
$calc_invoiced = $pdo->prepare("SELECT COALESCE(SUM(total_payable), 0) FROM bills WHERE billing_month = ?");
$calc_invoiced->execute([$cur_month]);
$total_invoiced_month = $calc_invoiced->fetchColumn();

$calc_collected = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM bills WHERE billing_month = ?");
$calc_collected->execute([$cur_month]);
$total_collected_month = $calc_collected->fetchColumn();

$calc_due = $pdo->prepare("SELECT COALESCE(SUM(due_amount), 0) FROM bills WHERE billing_month = ?");
$calc_due->execute([$cur_month]);
$total_due_month = $calc_due->fetchColumn();

$zones = $pdo->query("SELECT * FROM zones ORDER BY name ASC")->fetchAll();
$all_customers = $pdo->query("SELECT id, customer_code, name, phone, monthly_bill, total_due FROM customers ORDER BY name ASC")->fetchAll();

$page_title = 'Customer Bill Calculation';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">3. Customer Bill Calculation</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                Month: <?= date('F Y', strtotime($cur_month . '-01')) ?>
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Automated recurring billing generation engine, overdue interest/penalty calculation, and balance forward tracking.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="export.php?type=bills&month=<?= urlencode($cur_month) ?>" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-csv me-1"></i> Export Bills
        </a>
        <a href="collection_sheet.php" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-clipboard-list me-1"></i> Field Sheet
        </a>
        <a href="single_invoice.php" class="btn btn-sm btn-outline-cyan font-mono text-xs">
            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Single / Manual Invoice
        </a>
        <?php if (has_permission('billing', 'add')): ?>
            <button class="btn btn-sm btn-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#generateBulkModal">
                <i class="fa-solid fa-bolt me-1"></i> Generate Monthly Bills
            </button>
            <button class="btn btn-sm btn-outline-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addCustomBillModal">
                <i class="fa-solid fa-plus me-1"></i> + Custom Invoice
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Total Invoiced Amount</div>
            <div class="kpi-value text-cyan-400"><?= format_money($total_invoiced_month) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Total billing demand for <?= date('M Y', strtotime($cur_month . '-01')) ?></div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Bills Collected</div>
            <div class="kpi-value text-emerald-400"><?= format_money($total_collected_month) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Payment received against invoices</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-rose h-100">
            <div class="kpi-title">Outstanding / Due</div>
            <div class="kpi-value text-rose-400"><?= format_money($total_due_month) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Uncollected revenue balance</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">Invoices in Batch</div>
            <div class="kpi-value text-purple-400"><?= count($bills) ?> Invoices</div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Total subscriber invoices generated</div>
        </div>
    </div>
</div>

<!-- Bills Table Card -->
<div class="card card-noc">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                <i class="fa-solid fa-file-invoice text-cyan-400 me-2"></i>Invoices Ledger
            </span>
            <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs"><?= count($bills) ?> Invoices</span>
        </div>
        <form method="GET" action="billing.php" class="d-flex align-items-center flex-wrap gap-2">
            <!-- Search Input -->
            <div class="input-group input-group-sm" style="max-width: 190px;">
                <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" class="form-control form-control-noc" placeholder="Search bill/client..." data-noc-search="billingTable">
            </div>

            <!-- Month Filter -->
            <input type="month" name="month" class="form-control form-control-noc form-control-sm font-mono" value="<?= htmlspecialchars($cur_month) ?>" onchange="this.form.submit()" style="width: auto;">

            <!-- Status Filter -->
            <select name="status" class="form-select form-select-noc form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="Unpaid" <?= $filter_status === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                <option value="Partial" <?= $filter_status === 'Partial' ? 'selected' : '' ?>>Partial</option>
                <option value="Paid" <?= $filter_status === 'Paid' ? 'selected' : '' ?>>Paid</option>
                <option value="Overdue" <?= $filter_status === 'Overdue' ? 'selected' : '' ?>>Overdue</option>
            </select>

            <!-- Zone Filter -->
            <select name="zone_id" class="form-select form-select-noc form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Zones</option>
                <?php foreach ($zones as $z): ?>
                    <option value="<?= $z['id'] ?>" <?= $filter_zone == $z['id'] ? 'selected' : '' ?>><?= htmlspecialchars($z['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-noc align-middle" id="billingTable">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Subscriber</th>
                        <th>Zone</th>
                        <th>Bill Date</th>
                        <th>Due Date</th>
                        <th class="text-end">Package</th>
                        <th class="text-end">Prev Due</th>
                        <th class="text-end">Payable</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-4 text-slate-500 font-mono">No bills generated for <?= htmlspecialchars($cur_month) ?>. Click "Generate Monthly Bills" to calculate now!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td>
                                    <a href="invoice.php?bill_number=<?= urlencode($b['bill_number']) ?>" target="_blank" class="font-mono text-cyan-400 fw-bold text-decoration-none">
                                        <?= htmlspecialchars($b['bill_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-slate-200"><?= htmlspecialchars($b['customer_name']) ?></div>
                                    <div class="text-slate-500 font-mono" style="font-size:11px;">
                                        <?= htmlspecialchars($b['customer_code']) ?> • <?= htmlspecialchars($b['phone']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-slate-400 text-xs"><?= htmlspecialchars($b['zone_name'] ?: 'Main Node') ?></span>
                                </td>
                                <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($b['bill_date']) ?></td>
                                <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($b['due_date']) ?></td>
                                <td class="text-end font-mono text-slate-300"><?= format_money($b['package_amount']) ?></td>
                                <td class="text-end font-mono text-slate-400"><?= format_money($b['previous_due']) ?></td>
                                <td class="text-end font-mono fw-bold text-slate-200"><?= format_money($b['total_payable']) ?></td>
                                <td class="text-end font-mono text-emerald-400"><?= format_money($b['paid_amount']) ?></td>
                                <td class="text-end font-mono fw-bold <?= $b['due_amount'] > 0 ? 'text-rose-400' : 'text-slate-500' ?>">
                                    <?= format_money($b['due_amount']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($b['status'] === 'Paid'): ?>
                                        <span class="badge badge-paid">Paid</span>
                                    <?php elseif ($b['status'] === 'Partial'): ?>
                                        <span class="badge badge-partial">Partial</span>
                                    <?php elseif ($b['status'] === 'Overdue'): ?>
                                        <span class="badge badge-due">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-due">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($b['due_amount'] > 0 && has_permission('payments', 'add')): ?>
                                            <a href="payment_receive.php?customer_id=<?= $b['customer_id'] ?>&bill_id=<?= $b['id'] ?>" class="btn btn-cyan p-1 px-2 text-dark font-mono text-xs fw-bold" title="Receive Payment">
                                                <i class="fa-solid fa-hand-holding-dollar"></i> Pay
                                            </a>
                                        <?php endif; ?>
                                        <a href="invoice.php?bill_number=<?= urlencode($b['bill_number']) ?>" target="_blank" class="btn btn-outline-cyan border-slate-800 p-1 px-2" title="Print Invoice">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                        <?php if (has_permission('billing', 'delete')): ?>
                                            <a href="billing.php?delete_bill_id=<?= $b['id'] ?>" class="btn btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Permanently remove invoice <?= htmlspecialchars($b['bill_number']) ?>?" data-item-name="<?= htmlspecialchars($b['customer_name']) ?>" data-item-code="Payable: <?= format_money($b['total_payable']) ?>" title="Delete Invoice">
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

<!-- Modal: Generate Bulk Monthly Bills -->
<div class="modal fade" id="generateBulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-bolt text-cyan-400 me-2"></i>Run Automated Monthly Billing Engine
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="billing.php" data-confirm-add="Start the automated billing engine for all active subscribers for month <?= htmlspecialchars($cur_month) ?>?" data-confirm-title="Confirm Billing Engine Run">
                <input type="hidden" name="action" value="generate_monthly_bills">
                <div class="modal-body p-4">
                    <p class="text-slate-300 text-xs mb-3">
                        This automated billing engine calculates monthly fees for all active and due customers, automatically rolling over previous unpaid balances and applying agreed package pricing.
                    </p>

                    <div class="mb-3">
                        <label class="form-label-noc">Target Billing Month <span class="text-rose-400">*</span></label>
                        <input type="month" name="target_month" class="form-control form-control-noc font-mono" value="<?= date('Y-m') ?>" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Bill Generation Date</label>
                            <input type="date" name="bill_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-01') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Due Day of Month</label>
                            <input type="number" class="form-control form-control-noc font-mono" value="<?= $app_settings['bill_due_day'] ?>" disabled>
                            <div class="text-slate-500 font-mono" style="font-size:10px;">Set in system settings</div>
                        </div>
                    </div>

                    <div class="alert alert-info bg-cyan-950/40 border border-cyan-800 text-cyan-300 text-xs py-2 px-3 mb-0">
                        <i class="fa-solid fa-shield-halved me-1"></i> Duplicate prevention: Subscribers who already have an invoice for the target month will be safely skipped.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-calculator me-1"></i> Calculate & Generate Invoices</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Custom Single Bill -->
<div class="modal fade" id="addCustomBillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-plus text-cyan-400 me-2"></i>Create Custom Invoice
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="billing.php" data-confirm-add="Confirm creating this single custom invoice?" data-confirm-title="Confirm Custom Invoice">
                <input type="hidden" name="action" value="add_custom_bill">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-noc">Select Subscriber <span class="text-rose-400">*</span></label>
                        <select name="customer_id" id="singleBillCustomerSelect" class="form-select form-select-noc" required onchange="fillCustomerBillDetails(this)">
                            <option value="">Choose subscriber...</option>
                            <?php foreach ($all_customers as $ac): ?>
                                <option value="<?= $ac['id'] ?>" data-bill="<?= $ac['monthly_bill'] ?>" data-due="<?= $ac['total_due'] ?>">
                                    <?= htmlspecialchars($ac['name']) ?> (<?= $ac['customer_code'] ?>) - Bill: BDT<?= $ac['monthly_bill'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Billing Month</label>
                            <input type="month" name="billing_month" class="form-control form-control-noc font-mono" value="<?= date('Y-m') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Due Date</label>
                            <input type="date" name="due_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-10') ?>" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Package Rate (BDT)</label>
                            <input type="number" step="0.01" name="package_amount" id="bill_package_amount" class="form-control form-control-noc font-mono" required onkeyup="calculateBillTotals()">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Extra / Router EMI (BDT)</label>
                            <input type="number" step="0.01" name="extra_charge" id="bill_extra_charge" class="form-control form-control-noc font-mono" value="0.00" onkeyup="calculateBillTotals()">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Previous Due (BDT)</label>
                            <input type="number" step="0.01" name="previous_due" id="bill_previous_due" class="form-control form-control-noc font-mono" value="0.00" onkeyup="calculateBillTotals()">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Discount (BDT)</label>
                            <input type="number" step="0.01" name="discount" id="bill_discount" class="form-control form-control-noc font-mono" value="0.00" onkeyup="calculateBillTotals()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-noc">Total Net Payable (BDT)</label>
                        <input type="text" id="bill_total_payable" class="form-control form-control-noc font-mono fw-bold text-cyan-400" readonly value="0.00">
                    </div>

                    <div class="mb-0">
                        <label class="form-label-noc">Invoice Notes</label>
                        <input type="text" name="notes" class="form-control form-control-noc" placeholder="e.g. Fiber setup fee + monthly subscription">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Issue Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillCustomerBillDetails(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const bill = opt.getAttribute('data-bill') || '0.00';
    const due = opt.getAttribute('data-due') || '0.00';

    document.getElementById('bill_package_amount').value = parseFloat(bill).toFixed(2);
    document.getElementById('bill_previous_due').value = parseFloat(due).toFixed(2);
    calculateBillTotals();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

