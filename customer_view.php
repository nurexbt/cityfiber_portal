<?php
// customer_view.php - Subscriber Profile & Financial Ledger
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('customers', 'view');

$page_title = 'Customer Ledger & Profile';
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT c.*, z.name AS zone_name, p.name AS package_name, p.speed_mbps 
    FROM customers c
    LEFT JOIN zones z ON c.zone_id = z.id
    LEFT JOIN packages p ON c.package_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash_message('danger', 'Customer not found.');
    header('Location: customers.php');
    exit;
}

// Fetch all Bills for this customer
$bills_stmt = $pdo->prepare("
    SELECT * FROM bills 
    WHERE customer_id = ? 
    ORDER BY bill_date DESC, id DESC
");
$bills_stmt->execute([$id]);
$bills = $bills_stmt->fetchAll();

// Fetch all Payments for this customer
$payments_stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE customer_id = ? 
    ORDER BY payment_date DESC, id DESC
");
$payments_stmt->execute([$id]);
$payments = $payments_stmt->fetchAll();

// Live calculations for this customer
$total_invoiced = 0;
$total_paid = 0;
$total_outstanding = 0;

foreach ($bills as $b) {
    $total_invoiced += $b['total_payable'];
    $total_outstanding += $b['due_amount'];
}
foreach ($payments as $p) {
    $total_paid += $p['amount'];
}
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="customers.php" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300 p-1 px-2">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h4 class="fw-bold text-white mb-0 font-sans"><?= htmlspecialchars($customer['name']) ?></h4>
            <span class="badge bg-cyan-950 text-cyan-400 border border-cyan-800 font-mono text-xs"><?= htmlspecialchars($customer['customer_code']) ?></span>
            <?php if ($customer['status'] === 'Active'): ?>
                <span class="badge badge-active"><i class="fa-solid fa-circle-check me-1"></i> Active Line</span>
            <?php else: ?>
                <span class="badge badge-due"><?= htmlspecialchars($customer['status']) ?></span>
            <?php endif; ?>
        </div>
        <p class="text-slate-400 text-xs mb-0">Subscribed since <?= htmlspecialchars($customer['join_date']) ?> • POP: <?= htmlspecialchars($customer['zone_name'] ?: 'Primary Node') ?></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="single_invoice.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-cyan font-mono text-xs">
            <i class="fa-solid fa-file-invoice me-1"></i> Single Invoice
        </a>
        <a href="payment_receive.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-cyan font-mono text-xs">
            <i class="fa-solid fa-receipt me-1"></i> Receive Bill Payment
        </a>
    </div>
</div>

<!-- Profile Info Strip & Live Financials -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card card-noc h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-network-wired text-cyan-400 me-2"></i>Line Provisioning & Hardware Details
                </span>
                <span class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($customer['connection_type']) ?> Link</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <span class="text-slate-500 text-xs font-mono d-block">MOBILE PHONE</span>
                        <span class="text-slate-200 font-mono fw-bold"><?= htmlspecialchars($customer['phone']) ?></span>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="text-slate-500 text-xs font-mono d-block">IP ADDRESS</span>
                        <span class="text-emerald-400 font-mono fw-bold"><?= htmlspecialchars($customer['ip_address'] ?: 'DHCP Dynamic') ?></span>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="text-slate-500 text-xs font-mono d-block">PPPoE USERNAME</span>
                        <span class="text-cyan-400 font-mono fw-bold"><?= htmlspecialchars($customer['pppoe_username'] ?: '—') ?></span>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="text-slate-500 text-xs font-mono d-block">INTERNET PACKAGE</span>
                        <span class="text-slate-200 fw-semibold"><?= htmlspecialchars($customer['package_name'] ?: 'Custom Plan') ?> (<?= $customer['speed_mbps'] ?>M)</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="text-slate-500 text-xs font-mono d-block">ONU MAC ADDRESS</span>
                        <span class="text-slate-300 font-mono"><?= htmlspecialchars($customer['onu_mac'] ?: 'Not recorded') ?></span>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="text-slate-500 text-xs font-mono d-block">FIBER CORE & TJ BOX</span>
                        <span class="text-slate-300 font-mono"><?= htmlspecialchars($customer['fiber_core_box'] ?: 'Not mapped') ?></span>
                    </div>
                    <div class="col-12 border-top border-slate-800 pt-2">
                        <span class="text-slate-500 text-xs font-mono d-block">INSTALLATION ADDRESS</span>
                        <span class="text-slate-300 text-xs"><?= htmlspecialchars($customer['address']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscriber Balance Summary -->
    <div class="col-12 col-lg-4">
        <div class="card card-noc h-100">
            <div class="card-header">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-scale-balanced text-amber-400 me-2"></i>Account Balance
                </span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-slate-800">
                        <span class="text-slate-400 text-xs">Agreed Monthly Rate:</span>
                        <span class="font-mono fw-bold text-slate-200"><?= format_money($customer['monthly_bill']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-slate-800">
                        <span class="text-slate-400 text-xs">Total Invoiced:</span>
                        <span class="font-mono text-slate-300"><?= format_money($total_invoiced) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-slate-800">
                        <span class="text-slate-400 text-xs">Total Paid:</span>
                        <span class="font-mono text-emerald-400 fw-bold"><?= format_money($total_paid) ?></span>
                    </div>
                </div>
                <div class="p-3 rounded bg-slate-950 border border-slate-800 text-center mt-3">
                    <span class="text-xs text-uppercase tracking-wider font-mono <?= $total_outstanding > 0 ? 'text-rose-400' : 'text-emerald-400' ?>">
                        <?= $total_outstanding > 0 ? 'CURRENT DUE BALANCE' : 'ACCOUNT CLEAR' ?>
                    </span>
                    <h3 class="font-mono fw-bold mb-0 <?= $total_outstanding > 0 ? 'text-rose-400' : 'text-emerald-400' ?>">
                        <?= format_money($total_outstanding) ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bills & Payment History Tabs -->
<div class="card card-noc">
    <div class="card-header p-2">
        <ul class="nav nav-pills nav-fill text-xs font-mono" id="ledgerTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active text-slate-300" id="bills-tab" data-bs-toggle="tab" data-bs-target="#billsTabPane" type="button" role="tab">
                    <i class="fa-solid fa-file-invoice me-1"></i> Generated Invoices (<?= count($bills) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-slate-300" id="payments-tab" data-bs-toggle="tab" data-bs-target="#paymentsTabPane" type="button" role="tab">
                    <i class="fa-solid fa-receipt me-1"></i> Payment Receipts (<?= count($payments) ?>)
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="ledgerTabContent">
            <!-- Bills Tab -->
            <div class="tab-pane fade show active" id="billsTabPane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Billing Month</th>
                                <th>Bill Date</th>
                                <th>Due Date</th>
                                <th class="text-end">Package Rate</th>
                                <th class="text-end">Prev Due</th>
                                <th class="text-end">Total Payable</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Print</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bills)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-slate-500 font-mono">No invoices on file for this customer.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bills as $b): ?>
                                    <tr>
                                        <td>
                                            <a href="invoice.php?bill_number=<?= urlencode($b['bill_number']) ?>" target="_blank" class="font-mono text-cyan-400 fw-semibold text-decoration-none">
                                                <?= htmlspecialchars($b['bill_number']) ?>
                                            </a>
                                        </td>
                                        <td class="font-mono text-slate-300 text-xs"><?= htmlspecialchars($b['billing_month']) ?></td>
                                        <td class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($b['bill_date']) ?></td>
                                        <td class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($b['due_date']) ?></td>
                                        <td class="text-end font-mono text-slate-300"><?= format_money($b['package_amount']) ?></td>
                                        <td class="text-end font-mono text-slate-400"><?= format_money($b['previous_due']) ?></td>
                                        <td class="text-end font-mono fw-bold text-slate-200"><?= format_money($b['total_payable']) ?></td>
                                        <td class="text-end font-mono text-emerald-400"><?= format_money($b['paid_amount']) ?></td>
                                        <td class="text-end font-mono fw-bold <?= $b['due_amount'] > 0 ? 'text-rose-400' : 'text-slate-500' ?>"><?= format_money($b['due_amount']) ?></td>
                                        <td class="text-center">
                                            <?php if ($b['status'] === 'Paid'): ?>
                                                <span class="badge badge-paid">Paid</span>
                                            <?php elseif ($b['status'] === 'Partial'): ?>
                                                <span class="badge badge-partial">Partial</span>
                                            <?php else: ?>
                                                <span class="badge badge-due"><?= htmlspecialchars($b['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="invoice.php?bill_number=<?= urlencode($b['bill_number']) ?>" target="_blank" class="btn btn-sm btn-outline-cyan p-1 px-2 font-mono" title="Print Invoice">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments Tab -->
            <div class="tab-pane fade" id="paymentsTabPane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Payment Date</th>
                                <th>Method</th>
                                <th>Transaction / Ref</th>
                                <th>Collected By</th>
                                <th>Remarks</th>
                                <th class="text-end">Amount Paid</th>
                                <th class="text-center">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-slate-500 font-mono">No payment receipts logged yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td>
                                            <span class="font-mono text-cyan-400 fw-bold"><?= htmlspecialchars($p['receipt_no']) ?></span>
                                        </td>
                                        <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($p['payment_date']) ?></td>
                                        <td>
                                            <?php
                                            $cpm = strtolower($p['payment_method']);
                                            $cpm_cls = 'badge-method';
                                            $cpm_ico = 'fa-money-bill-wave';
                                            if (strpos($cpm, 'bkash') !== false) { $cpm_cls = 'badge-method-bkash'; $cpm_ico = 'fa-mobile-screen-button'; }
                                            elseif (strpos($cpm, 'nagad') !== false) { $cpm_cls = 'badge-method-nagad'; $cpm_ico = 'fa-wallet'; }
                                            elseif (strpos($cpm, 'bank') !== false) { $cpm_cls = 'badge-method-bank'; $cpm_ico = 'fa-building-columns'; }
                                            elseif (strpos($cpm, 'cash') !== false) { $cpm_cls = 'badge-method-cash'; $cpm_ico = 'fa-money-bill-1-wave'; }
                                            ?>
                                            <span class="badge <?= $cpm_cls ?> font-mono text-xs">
                                                <i class="fa-solid <?= $cpm_ico ?> me-1"></i><?= htmlspecialchars($p['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($p['transaction_id'] ?: '—') ?></td>
                                        <td class="text-slate-300 text-xs"><?= htmlspecialchars($p['collected_by'] ?: 'Admin') ?></td>
                                        <td class="text-slate-400 text-xs"><?= htmlspecialchars($p['remarks'] ?: '—') ?></td>
                                        <td class="text-end font-mono fw-bold text-emerald-400 fs-6">
                                            +<?= format_money($p['amount']) ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="invoice.php?receipt_no=<?= urlencode($p['receipt_no']) ?>" target="_blank" class="btn btn-sm btn-outline-cyan p-1 px-2 font-mono text-xs" title="Print Money Receipt">
                                                <i class="fa-solid fa-receipt"></i>
                                            </a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

