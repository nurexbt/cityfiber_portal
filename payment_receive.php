<?php
// payment_receive.php - 5. Customer Bill Payment Receive & Cashier Desk
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('payments', 'view');
$user_data = get_current_user_data();

$prefill_customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$prefill_bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive_payment') {
    require_permission('payments', 'add');
    $customer_id = (int)$_POST['customer_id'];
    $bill_id = !empty($_POST['bill_id']) ? (int)$_POST['bill_id'] : null;
    $amount = (float)($_POST['amount'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $collected_by = trim($_POST['collected_by'] ?? $user_data['username']);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($customer_id <= 0 || $amount <= 0) {
        set_flash_message('danger', 'Please select a customer and enter a valid payment amount.');
    } else {
        // Generate Receipt No: REC-YYYYMM-XXXX
        $prefix = 'REC-' . date('Ym', strtotime($payment_date)) . '-';
        $last_p = $pdo->query("SELECT id FROM payments ORDER BY id DESC LIMIT 1")->fetchColumn();
        $receipt_no = $prefix . str_pad(($last_p ? $last_p + 1 : 1), 4, '0', STR_PAD_LEFT);

        // Record payment
        $stmt = $pdo->prepare("
            INSERT INTO payments (receipt_no, bill_id, customer_id, payment_date, amount, discount, payment_method, transaction_id, collected_by, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$receipt_no, $bill_id, $customer_id, $payment_date, $amount, $discount, $payment_method, $transaction_id, $collected_by, $remarks]);

        // Settle Bill if attached
        if ($bill_id) {
            $b_stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
            $b_stmt->execute([$bill_id]);
            $bill = $b_stmt->fetch();
            if ($bill) {
                $new_paid = (float)$bill['paid_amount'] + $amount + $discount;
                $new_due = max(0, (float)$bill['total_payable'] - $new_paid);
                $new_status = ($new_due <= 0.01) ? 'Paid' : 'Partial';

                $b_up = $pdo->prepare("UPDATE bills SET paid_amount = ?, due_amount = ?, status = ? WHERE id = ?");
                $b_up->execute([$new_paid, $new_due, $new_status, $bill_id]);
            }
        }

        // Update Customer Total Due
        $effective_reduction = $amount + $discount;
        $c_up = $pdo->prepare("UPDATE customers SET total_due = GREATEST(0, total_due - ?) WHERE id = ?");
        $c_up->execute([$effective_reduction, $customer_id]);

        // Check if customer status should transition from Due to Active
        $chk_due = $pdo->prepare("SELECT total_due, status FROM customers WHERE id = ?");
        $chk_due->execute([$customer_id]);
        $cust_data = $chk_due->fetch();
        if ($cust_data && (float)$cust_data['total_due'] <= 0.01 && $cust_data['status'] === 'Due') {
            $pdo->prepare("UPDATE customers SET status = 'Active' WHERE id = ?")->execute([$customer_id]);
        }

        set_flash_message('success', "Payment receipt {$receipt_no} for " . format_money($amount) . " successfully collected!");
        header("Location: invoice.php?receipt_no={$receipt_no}");
        exit;
    }
}

// Cancel / Revert Payment
if (isset($_GET['delete_payment_id'])) {
    require_permission('payments', 'delete');
    $del_p_id = (int)$_GET['delete_payment_id'];
    $p_stmt = $pdo->prepare("SELECT p.*, c.name AS customer_name FROM payments p JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
    $p_stmt->execute([$del_p_id]);
    $p_row = $p_stmt->fetch();
    if ($p_row) {
        $revert_amt = (float)$p_row['amount'] + (float)$p_row['discount'];
        // Revert customer due
        $pdo->prepare("UPDATE customers SET total_due = total_due + ? WHERE id = ?")->execute([$revert_amt, $p_row['customer_id']]);
        if ($p_row['bill_id']) {
            $pdo->prepare("UPDATE bills SET paid_amount = GREATEST(0, paid_amount - ?), due_amount = due_amount + ?, status = 'Unpaid' WHERE id = ?")
                ->execute([$revert_amt, $revert_amt, $p_row['bill_id']]);
        }
        $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$del_p_id]);
        set_flash_message('success', "Payment receipt {$p_row['receipt_no']} for " . format_money($p_row['amount']) . " cancelled and customer balance restored.");
    }
    header('Location: payment_receive.php');
    exit;
}

// Collections Filter
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_method = $_GET['method'] ?? '';

$sql = "
    SELECT p.*, c.customer_code, c.name AS customer_name, c.phone, c.address, b.bill_number
    FROM payments p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN bills b ON p.bill_id = b.id
    WHERE 1=1
";
$params = [];

if (!empty($filter_date)) {
    $sql .= " AND p.payment_date = ?";
    $params[] = $filter_date;
}
if (!empty($filter_method)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $filter_method;
}
$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$collections = $stmt->fetchAll();

// Daily Summary KPI
$today_date = date('Y-m-d');
$today_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date = '{$today_date}'")->fetchColumn();
$today_cash = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date = '{$today_date}' AND payment_method = 'Cash'")->fetchColumn();
$today_mfs = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date = '{$today_date}' AND payment_method IN ('bKash', 'Nagad', 'Rocket')")->fetchColumn();
$today_bank = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date = '{$today_date}' AND payment_method IN ('Bank Transfer', 'POS/Card')")->fetchColumn();

// Fetch customer list with outstanding balances
$customers_due_list = $pdo->query("
    SELECT id, customer_code, name, phone, monthly_bill, total_due 
    FROM customers 
    ORDER BY total_due DESC, name ASC
")->fetchAll();

// If prefilling customer, fetch their pending bills
$pending_bills = [];
if ($prefill_customer_id > 0) {
    $pb_stmt = $pdo->prepare("SELECT id, bill_number, billing_month, total_payable, due_amount FROM bills WHERE customer_id = ? AND status IN ('Unpaid', 'Partial', 'Overdue') ORDER BY id ASC");
    $pb_stmt->execute([$prefill_customer_id]);
    $pending_bills = $pb_stmt->fetchAll();
}

$page_title = 'Payment Receive';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">5. Customer Bill Payment Receive</h4>
            <span class="badge badge-header-emerald font-mono text-xs">
                Collection Desk
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Receive cash, bKash, Nagad, or bank payments, settle customer dues instantly, and issue printed money receipts.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="export.php?type=collections" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-csv me-1"></i> Export Collections
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Today's Total Collection</div>
            <div class="kpi-value text-emerald-400"><?= format_money($today_total) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;"><?= date('d M Y') ?> revenue</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Cash Collections</div>
            <div class="kpi-value text-cyan-400"><?= format_money($today_cash) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Counter & field cash</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">MFS (bKash / Nagad)</div>
            <div class="kpi-value text-purple-400"><?= format_money($today_mfs) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Digital mobile wallet payments</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-blue h-100">
            <div class="kpi-title">Bank & Corporate</div>
            <div class="kpi-value text-blue-400"><?= format_money($today_bank) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">BEFTN, NPSB & Cards</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left Column: Payment Entry Desk Form -->
    <div class="col-12 col-lg-5">
        <div class="card card-noc h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-receipt text-emerald-400 me-2"></i>Quick Payment Entry Desk
                </span>
                <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs">Instant Settle</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="payment_receive.php" id="paymentForm" data-confirm-add="Confirm collecting payment and generating money receipt?" data-confirm-title="Confirm Payment Collection">
                    <input type="hidden" name="action" value="receive_payment">

                    <!-- Customer Selector -->
                    <div class="mb-3">
                        <label class="form-label-noc">Select Subscriber <span class="text-rose-400">*</span></label>
                        <select name="customer_id" id="paymentCustomerSelect" class="form-select form-select-noc" required onchange="onSelectCustomerForPayment(this)">
                            <option value="">-- Choose Subscriber / Search --</option>
                            <?php foreach ($customers_due_list as $cd): ?>
                                <option value="<?= $cd['id'] ?>" 
                                    data-monthly="<?= $cd['monthly_bill'] ?>" 
                                    data-due="<?= $cd['total_due'] ?>"
                                    <?= $prefill_customer_id == $cd['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cd['name']) ?> (<?= $cd['customer_code'] ?>) [Due: BDT<?= number_format($cd['total_due'], 2) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Subscriber Due Display Banner -->
                    <div class="p-3 mb-3 rounded bg-slate-950 border border-slate-800 d-flex justify-content-between align-items-center" id="subscriberInfoBanner">
                        <div>
                            <span class="text-slate-400 text-xs d-block font-mono">CURRENT DUE BALANCE</span>
                            <h4 class="font-mono fw-bold text-rose-400 mb-0" id="displayDueAmount">BDT 0.00</h4>
                        </div>
                        <div class="text-end">
                            <span class="text-slate-400 text-xs d-block font-mono">MONTHLY RATE</span>
                            <span class="font-mono text-slate-200 fw-bold" id="displayMonthlyRate">BDT 0.00</span>
                        </div>
                    </div>

                    <!-- Pending Bill Selector (Optional if specific invoice is being settled) -->
                    <div class="mb-3">
                        <label class="form-label-noc">Link to Unpaid Invoice (Optional)</label>
                        <select name="bill_id" id="paymentBillSelect" class="form-select form-select-noc">
                            <option value="">General Account Payment</option>
                            <?php foreach ($pending_bills as $pb): ?>
                                <option value="<?= $pb['id'] ?>" <?= $prefill_bill_id == $pb['id'] ? 'selected' : '' ?> data-due="<?= $pb['due_amount'] ?>">
                                    <?= htmlspecialchars($pb['bill_number']) ?> (<?= $pb['billing_month'] ?>) - Due: BDT<?= number_format($pb['due_amount'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label-noc">Received Amount (BDT) <span class="text-rose-400">*</span></label>
                            <input type="number" step="0.01" name="amount" id="paymentAmountInput" class="form-control form-control-noc font-mono fs-6 fw-bold text-emerald-400" required placeholder="0.00">
                        </div>
                        <div class="col-5">
                            <label class="form-label-noc">Discount (BDT)</label>
                            <input type="number" step="0.01" name="discount" id="paymentDiscountInput" class="form-control form-control-noc font-mono" value="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Payment Date <span class="text-rose-400">*</span></label>
                            <input type="date" name="payment_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Payment Method <span class="text-rose-400">*</span></label>
                            <select name="payment_method" class="form-select form-select-noc" required>
                                <option value="Cash" selected>Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Rocket">Rocket</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="POS/Card">POS / Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Transaction ID / Slip #</label>
                            <input type="text" name="transaction_id" class="form-control form-control-noc font-mono" placeholder="e.g. BKH99812">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Collected By</label>
                            <input type="text" name="collected_by" class="form-control form-control-noc" value="<?= htmlspecialchars($user_data['username']) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label-noc">Remarks / Memo</label>
                        <input type="text" name="remarks" class="form-control form-control-noc" placeholder="e.g. November broadband subscription paid in full">
                    </div>

                    <button type="submit" class="btn btn-cyan w-100 fw-bold py-2 font-mono">
                        <i class="fa-solid fa-check-circle me-1"></i> Receive & Print Money Receipt
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Collections Ledger Sheet -->
    <div class="col-12 col-lg-7">
        <div class="card card-noc h-100">
            <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                        <i class="fa-solid fa-list-check text-cyan-400 me-2"></i>Collection Records
                    </span>
                    <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs"><?= count($collections) ?> Receipts</span>
                </div>
                <form method="GET" action="payment_receive.php" class="d-flex align-items-center gap-2">
                    <input type="date" name="filter_date" class="form-control form-control-noc form-control-sm font-mono" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()" style="width: auto;">
                    <select name="method" class="form-select form-select-noc form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="">All Methods</option>
                        <option value="Cash" <?= $filter_method === 'Cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="bKash" <?= $filter_method === 'bKash' ? 'selected' : '' ?>>bKash</option>
                        <option value="Nagad" <?= $filter_method === 'Nagad' ? 'selected' : '' ?>>Nagad</option>
                        <option value="Bank Transfer" <?= $filter_method === 'Bank Transfer' ? 'selected' : '' ?>>Bank</option>
                    </select>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Subscriber</th>
                                <th>Method</th>
                                <th>Date</th>
                                <th>Trx / Ref</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($collections)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-slate-500 font-mono">No collections logged for selected date.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($collections as $c): ?>
                                    <tr>
                                        <td>
                                            <a href="invoice.php?receipt_no=<?= urlencode($c['receipt_no']) ?>" target="_blank" class="font-mono text-cyan-400 fw-bold text-decoration-none">
                                                <?= htmlspecialchars($c['receipt_no']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-slate-200"><?= htmlspecialchars($c['customer_name']) ?></div>
                                            <div class="text-slate-500 font-mono" style="font-size:11px;"><?= htmlspecialchars($c['customer_code']) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $pm = strtolower($c['payment_method']);
                                            $pm_cls = 'badge-method';
                                            $pm_ico = 'fa-money-bill-wave';
                                            if (strpos($pm, 'bkash') !== false) { $pm_cls = 'badge-method-bkash'; $pm_ico = 'fa-mobile-screen-button'; }
                                            elseif (strpos($pm, 'nagad') !== false) { $pm_cls = 'badge-method-nagad'; $pm_ico = 'fa-wallet'; }
                                            elseif (strpos($pm, 'bank') !== false) { $pm_cls = 'badge-method-bank'; $pm_ico = 'fa-building-columns'; }
                                            elseif (strpos($pm, 'cash') !== false) { $pm_cls = 'badge-method-cash'; $pm_ico = 'fa-money-bill-1-wave'; }
                                            ?>
                                            <span class="badge <?= $pm_cls ?> font-mono text-xs">
                                                <i class="fa-solid <?= $pm_ico ?> me-1"></i><?= htmlspecialchars($c['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($c['payment_date']) ?></td>
                                        <td class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($c['transaction_id'] ?: '—') ?></td>
                                        <td class="text-end font-mono fw-bold text-emerald-400 fs-6">
                                            +<?= format_money($c['amount']) ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="invoice.php?receipt_no=<?= urlencode($c['receipt_no']) ?>" target="_blank" class="btn btn-outline-cyan border-slate-800 p-1 px-2 font-mono text-xs" title="Print Receipt">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
                                                <?php if (has_permission('payments', 'delete')): ?>
                                                    <a href="payment_receive.php?delete_payment_id=<?= $c['id'] ?>" class="btn btn-outline-danger border-slate-800 p-1 px-2 font-mono text-xs" data-confirm-delete="Cancel & reverse payment receipt <?= htmlspecialchars($c['receipt_no']) ?>?" data-item-name="<?= htmlspecialchars($c['customer_name']) ?>" data-item-code="Amount: <?= format_money($c['amount']) ?>" title="Cancel & Reverse Receipt">
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
    </div>
</div>

<script>
function onSelectCustomerForPayment(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) {
        document.getElementById('displayDueAmount').textContent = 'BDT 0.00';
        document.getElementById('displayMonthlyRate').textContent = 'BDT 0.00';
        document.getElementById('paymentAmountInput').value = '';
        return;
    }

    const due = parseFloat(opt.getAttribute('data-due')) || 0;
    const monthly = parseFloat(opt.getAttribute('data-monthly')) || 0;

    document.getElementById('displayDueAmount').textContent = 'BDT ' + due.toFixed(2);
    document.getElementById('displayMonthlyRate').textContent = 'BDT ' + monthly.toFixed(2);

    // Auto populate amount with due or monthly rate
    document.getElementById('paymentAmountInput').value = due > 0 ? due.toFixed(2) : monthly.toFixed(2);
}

// Trigger initial update if prefilled
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('paymentCustomerSelect');
    if (sel && sel.value) {
        onSelectCustomerForPayment(sel);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

