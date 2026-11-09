<?php
// single_invoice.php - Dedicated Manual / Single Customer Invoice Generator with Live Preview & Print
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('billing', 'view');

$user_data = get_current_user_data();

// Fetch registered customers for optional quick-fill
$all_customers = [];
try {
    $stmt = $pdo->query("
        SELECT c.id, c.customer_code, c.name, c.phone, c.email, c.address, c.monthly_bill, c.total_due, 
               c.ip_address, c.pppoe_username, z.name AS zone_name, pkg.name AS package_name, pkg.speed_mbps
        FROM customers c
        LEFT JOIN zones z ON c.zone_id = z.id
        LEFT JOIN packages pkg ON c.package_id = pkg.id
        ORDER BY c.name ASC
    ");
    $all_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_customers = [];
}

// Fetch packages for quick selection
$packages_list = [];
try {
    $packages_list = $pdo->query("SELECT id, name, speed_mbps, price FROM packages WHERE status = 'Active' ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $packages_list = [];
}

// Pre-selected customer from query param if provided
$preselect_cust_id = (int)($_GET['customer_id'] ?? 0);
$preselect_data = null;
if ($preselect_cust_id > 0) {
    foreach ($all_customers as $cust) {
        if ((int)$cust['id'] === $preselect_cust_id) {
            $preselect_data = $cust;
            break;
        }
    }
}

// Handle Save to Database Action (Optional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_invoice_to_db') {
    require_permission('billing', 'add');
    
    $customer_id = (int)($_POST['db_customer_id'] ?? 0);
    $bill_number = trim($_POST['inv_number'] ?? '');
    $billing_month = trim($_POST['inv_month'] ?? date('Y-m'));
    $bill_date = trim($_POST['inv_date'] ?? date('Y-m-d'));
    $due_date = trim($_POST['due_date'] ?? date('Y-m-10'));
    $package_amount = (float)($_POST['subtotal_amount'] ?? 0);
    $extra_charge = (float)($_POST['extra_charge'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $previous_due = (float)($_POST['previous_due'] ?? 0);
    $total_payable = (float)($_POST['total_payable'] ?? 0);
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $due_amount = max(0, $total_payable - $paid_amount);
    $notes = trim($_POST['notes'] ?? 'Single / Manual Invoice');
    $status = $due_amount <= 0 && $total_payable > 0 ? 'Paid' : ($paid_amount > 0 ? 'Partial' : 'Unpaid');

    if (empty($bill_number)) {
        $bill_number = 'INV-' . date('Ym') . '-' . rand(1000, 9999);
    }

    if ($customer_id > 0) {
        // Insert Bill
        $ins = $pdo->prepare("
            INSERT INTO bills (
                bill_number, customer_id, billing_month, bill_date, due_date, 
                package_amount, extra_charge, discount, previous_due, total_payable, 
                paid_amount, due_amount, status, generated_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $bill_number, $customer_id, $billing_month, $bill_date, $due_date,
            $package_amount, $extra_charge, $discount, $previous_due, $total_payable,
            $paid_amount, $due_amount, $status, $user_data['id'], $notes
        ]);
        $bill_id = $pdo->lastInsertId();

        // If payment was collected right away, record payment receipt
        if ($paid_amount > 0) {
            $payment_method = trim($_POST['payment_method'] ?? 'Cash');
            $trx_id = trim($_POST['transaction_id'] ?? '');
            $receipt_no = 'REC-' . date('Ymd') . '-' . rand(1000, 9999);

            $pins = $pdo->prepare("
                INSERT INTO payments (
                    receipt_no, bill_id, customer_id, payment_date, amount, 
                    discount, payment_method, transaction_id, collected_by, remarks
                ) VALUES (?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?)
            ");
            $pins->execute([
                $receipt_no, $bill_id, $customer_id, $bill_date, $paid_amount,
                $payment_method, $trx_id, $user_data['full_name'], 'Manual Invoice Payment'
            ]);
        }

        // Adjust customer total due balance
        $upd = $pdo->prepare("UPDATE customers SET total_due = GREATEST(0, total_due + ? - ?) WHERE id = ?");
        $upd->execute([$total_payable, $paid_amount, $customer_id]);

        set_flash_message('success', "Invoice {$bill_number} recorded in database successfully!");
        header("Location: invoice.php?bill_number=" . urlencode($bill_number));
        exit;
    } else {
        set_flash_message('warning', "Manual invoice printed for off-record/walk-in customer without permanent DB registration.");
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom CSS for Printable Document & Live Studio -->
<style>
/* Portal Studio Styles */
.font-mono { font-family: 'JetBrains Mono', monospace !important; }
.invoice-studio-card {
    background: #0d1527;
    border: 1px solid #1e293b;
    border-radius: 8px;
}
.invoice-preview-wrapper {
    background: #090e17;
    border: 1px solid #1e293b;
    border-radius: 8px;
    padding: 24px 16px;
    display: flex;
    justify-content: center;
    overflow-x: auto;
    min-height: 800px;
}

/* Document Sheet - Matches invoice.php exactly */
.doc-sheet {
    background: #ffffff !important;
    color: #1e293b !important;
    width: 100%;
    max-width: 820px;
    margin: 0 auto;
    border-radius: 6px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.45);
    padding: 40px;
    position: relative;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13px;
    line-height: 1.5;
}

/* 80mm POS Thermal Receipt Format */
.pos-sheet {
    background: #ffffff !important;
    color: #0f172a !important;
    width: 330px;
    margin: 0 auto;
    padding: 20px 16px;
    border-radius: 4px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    font-size: 12px;
    font-family: 'JetBrains Mono', monospace;
    display: none; /* Shown when format is POS */
}

/* Seal Stamp */
.seal-stamp {
    position: absolute;
    right: 40px;
    top: 130px;
    border: 3px double #10b981;
    color: #10b981;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 1.05rem;
    padding: 5px 14px;
    transform: rotate(-10deg);
    opacity: 0.9;
    letter-spacing: 2px;
    border-radius: 6px;
    pointer-events: none;
    z-index: 5;
}
.seal-stamp.stamp-unpaid {
    border-color: #f43f5e;
    color: #f43f5e;
}
.seal-stamp.stamp-partial {
    border-color: #f59e0b;
    color: #f59e0b;
}

/* Table Invoice Styling */
.table-invoice {
    width: 100%;
    border-collapse: collapse;
}
.table-invoice th {
    background-color: #f1f5f9;
    color: #334155;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 10px 12px;
    border-top: 1px solid #cbd5e1;
    border-bottom: 2px solid #cbd5e1;
}
.table-invoice td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    color: #1e293b;
}

.item-delete-btn {
    opacity: 0.6;
    transition: opacity 0.15s ease;
}
.item-delete-btn:hover {
    opacity: 1;
}

/* Print CSS */
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .app-sidebar,
    .app-header,
    .app-footer,
    .sidebar-backdrop,
    .no-print,
    .invoice-editor-col,
    .page-header-actions,
    .top-action-bar,
    .nav-tabs,
    .btn,
    .alert {
        display: none !important;
    }
    .app-main,
    .app-content,
    .container-fluid,
    .invoice-preview-col,
    .invoice-preview-wrapper {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
    }
    .doc-sheet {
        box-shadow: none !important;
        border: none !important;
        margin: 0 auto !important;
        padding: 15mm 15mm !important;
        width: 100% !important;
        max-width: 210mm !important;
        min-height: 297mm !important;
        page-break-after: avoid !important;
    }
    .pos-sheet {
        box-shadow: none !important;
        border: none !important;
        margin: 0 auto !important;
        padding: 5mm !important;
        width: 78mm !important;
        font-size: 11px !important;
        page-break-after: avoid !important;
    }
}
</style>

<!-- Page Header (No Print) -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4 no-print">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">
                <i class="fa-solid fa-file-invoice-dollar text-cyan-400 me-2"></i>Single / Manual Invoice
            </h4>
            <span class="badge badge-header-cyan font-mono text-xs">Live Generator &amp; Print</span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Create customized on-the-fly bills and cash receipts with manual line items, live preview, and instant A4 / POS thermal printing.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="billing.php" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-arrow-left me-1"></i> Bill Calculation
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary text-slate-300 font-mono text-xs" id="btnFillSample" title="Populate realistic demo ISP charges">
            <i class="fa-solid fa-wand-magic-sparkles me-1 text-cyan-400"></i> Sample Items
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary text-slate-300 font-mono text-xs" id="btnClearForm" title="Reset all fields to blank">
            <i class="fa-solid fa-rotate-right me-1"></i> Reset
        </button>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-cyan font-mono text-xs active" id="btnFormatA4">
                <i class="fa-solid fa-file-lines me-1"></i> A4 Invoice
            </button>
            <button type="button" class="btn btn-outline-cyan font-mono text-xs" id="btnFormatPOS">
                <i class="fa-solid fa-receipt me-1"></i> POS 80mm
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-success font-mono text-xs fw-bold px-3" onclick="triggerPrint()">
            <i class="fa-solid fa-print me-1"></i> Print Invoice
        </button>
    </div>
</div>

<!-- Main 2-Column Studio (Form on Left / Preview on Right) -->
<div class="row g-3">
    <!-- LEFT COLUMN: Form Inputs & Item Controls (No Print) -->
    <div class="col-12 col-xl-5 invoice-editor-col no-print">
        <form id="invoiceForm" method="POST" action="single_invoice.php">
            <input type="hidden" name="action" value="save_invoice_to_db">
            <input type="hidden" name="db_customer_id" id="db_customer_id" value="<?= $preselect_data ? (int)$preselect_data['id'] : 0 ?>">
            <input type="hidden" name="subtotal_amount" id="hidden_subtotal" value="0">
            <input type="hidden" name="total_payable" id="hidden_total_payable" value="0">

            <!-- Card 1: Invoice & Customer Source -->
            <div class="card invoice-studio-card mb-3">
                <div class="card-header bg-slate-900 border-bottom border-slate-800 d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-cyan-400">
                        <i class="fa-solid fa-circle-info me-1"></i> 1. Invoice &amp; Customer Details
                    </span>
                    <button type="button" class="btn btn-link btn-sm text-slate-400 text-decoration-none p-0" id="btnNewInvNum" title="Generate New Invoice #">
                        <i class="fa-solid fa-arrows-rotate me-1"></i><span class="font-mono text-xs">New #</span>
                    </button>
                </div>
                <div class="card-body p-3">
                    <!-- Quick Customer Selector Dropdown -->
                    <div class="mb-3 p-2 rounded bg-slate-950 border border-slate-800">
                        <label class="form-label text-slate-300 text-xs fw-semibold mb-1 d-flex justify-content-between">
                            <span><i class="fa-solid fa-user-check text-cyan-400 me-1"></i> Quick Load Registered Subscriber:</span>
                            <span class="text-slate-400 font-mono" style="font-size:10px;">Optional auto-fill</span>
                        </label>
                        <select class="form-select form-select-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" id="quickCustomerSelect">
                            <option value="">-- Choose Existing Customer (or enter manually below) --</option>
                            <?php foreach ($all_customers as $c): ?>
                                <option value="<?= $c['id'] ?>" 
                                    <?= ($preselect_cust_id === (int)$c['id']) ? 'selected' : '' ?>
                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                    data-code="<?= htmlspecialchars($c['customer_code']) ?>"
                                    data-phone="<?= htmlspecialchars($c['phone']) ?>"
                                    data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars($c['address']) ?>"
                                    data-bill="<?= (float)$c['monthly_bill'] ?>"
                                    data-due="<?= (float)$c['total_due'] ?>"
                                    data-zone="<?= htmlspecialchars($c['zone_name'] ?? 'Main Node') ?>"
                                    data-package="<?= htmlspecialchars($c['package_name'] ?? 'Broadband') ?>"
                                    data-speed="<?= htmlspecialchars($c['speed_mbps'] ?? '10') ?>"
                                    data-ip="<?= htmlspecialchars($c['ip_address'] ?? '') ?>"
                                    data-pppoe="<?= htmlspecialchars($c['pppoe_username'] ?? '') ?>">
                                    <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['customer_code']) ?>) - <?= htmlspecialchars($c['phone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Invoice Numbers & Dates -->
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Invoice / Bill #</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs fw-bold" 
                                   id="inv_number" name="inv_number" value="CF-INV-<?= date('Ym') ?>-<?= rand(1000, 9999) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Billing Month</label>
                            <input type="month" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="inv_month" name="inv_month" value="<?= date('Y-m') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Issue Date</label>
                            <input type="date" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="inv_date" name="inv_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Due Date</label>
                            <input type="date" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="due_date" name="due_date" value="<?= date('Y-m-10') ?>">
                        </div>
                    </div>

                    <!-- Customer Manual Fields -->
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label text-slate-400 text-xs mb-1">Customer / Client Name *</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs fw-semibold" 
                                   id="cust_name" name="cust_name" placeholder="e.g. Tanvir Ahmed / Walk-in" value="<?= $preselect_data ? htmlspecialchars($preselect_data['name']) : 'Tanvir Ahmed' ?>">
                        </div>
                        <div class="col-5">
                            <label class="form-label text-slate-400 text-xs mb-1">Client ID / Code</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="cust_code" name="cust_code" placeholder="e.g. CF-1002" value="<?= $preselect_data ? htmlspecialchars($preselect_data['customer_code']) : 'CF-2026-M01' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Contact Phone *</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="cust_phone" name="cust_phone" placeholder="e.g. +880 1711-000000" value="<?= $preselect_data ? htmlspecialchars($preselect_data['phone']) : '+880 1711-234567' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Zone / Node</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs" 
                                   id="cust_zone" name="cust_zone" placeholder="e.g. Mirpur Core Node" value="<?= $preselect_data ? htmlspecialchars($preselect_data['zone_name'] ?? 'Mirpur Node') : 'Mirpur Core Node' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-400 text-xs mb-1">Installation Address</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs" 
                                   id="cust_address" name="cust_address" placeholder="e.g. House 14, Road 5, Block B, Mirpur-10" value="<?= $preselect_data ? htmlspecialchars($preselect_data['address']) : 'House 14, Road 5, Block B, Mirpur-10, Dhaka' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Package Plan / Speed</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs" 
                                   id="cust_package" name="cust_package" placeholder="e.g. Turbo Optical 25 Mbps" value="<?= $preselect_data ? htmlspecialchars($preselect_data['package_name'] . ' (' . $preselect_data['speed_mbps'] . 'M)') : 'Turbo Optical 25 Mbps' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">IP / PPPoE Username</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="cust_conn" name="cust_conn" placeholder="e.g. tanvir.pppoe" value="<?= $preselect_data ? htmlspecialchars($preselect_data['pppoe_username'] ?: $preselect_data['ip_address']) : 'cf.tanvir.pppoe' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Dynamic Line Items -->
            <div class="card invoice-studio-card mb-3">
                <div class="card-header bg-slate-900 border-bottom border-slate-800 d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-cyan-400">
                        <i class="fa-solid fa-list-check me-1"></i> 2. Service &amp; Line Items
                    </span>
                    <button type="button" class="btn btn-xs btn-outline-cyan font-mono py-1 px-2" id="btnAddItemRow">
                        <i class="fa-solid fa-plus me-1"></i> Add Item
                    </button>
                </div>
                <div class="card-body p-3">
                    <!-- Line items container -->
                    <div id="lineItemsContainer">
                        <!-- Line items will be dynamically rendered via JS -->
                    </div>

                    <!-- Common preset buttons for faster entry -->
                    <div class="mt-2 pt-2 border-top border-slate-800">
                        <span class="text-slate-400 font-mono d-block mb-1" style="font-size:10px;">Quick Add Preset:</span>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary text-slate-300 font-mono preset-btn" data-desc="Optical Broadband Internet Plan" data-period="1 Month" data-rate="1000">
                                + Broadband Plan
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary text-slate-300 font-mono preset-btn" data-desc="Dual-Band Gigabit Wi-Fi 6 Router" data-period="Brand New" data-rate="2800">
                                + Wi-Fi Router
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary text-slate-300 font-mono preset-btn" data-desc="EPON/GPON Optical ONU Device" data-period="Gigabit Port" data-rate="1200">
                                + Optical ONU
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary text-slate-300 font-mono preset-btn" data-desc="Drop Cable & Fiber Splicing Setup" data-period="100m + Patch cord" data-rate="800">
                                + Cable & Splicing
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary text-slate-300 font-mono preset-btn" data-desc="Real Public Static IPv4 Address" data-period="Monthly Lease" data-rate="300">
                                + Static IP
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Financial Calculations & Payment Received -->
            <div class="card invoice-studio-card mb-3">
                <div class="card-header bg-slate-900 border-bottom border-slate-800 d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-cyan-400">
                        <i class="fa-solid fa-calculator me-1"></i> 3. Financial Summary &amp; Payment
                    </span>
                    <span class="text-slate-400 font-mono text-xs" id="summarySubtotalBadge">Subtotal: BDT 0.00</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label text-slate-400 text-xs mb-1">Extra / Setup Fee</label>
                            <input type="number" step="0.01" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="input_extra_charge" name="extra_charge" value="0.00">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-slate-400 text-xs mb-1">Previous Due</label>
                            <input type="number" step="0.01" class="form-control form-control-sm bg-slate-900 border-slate-700 text-danger font-mono text-xs" 
                                   id="input_previous_due" name="previous_due" value="<?= $preselect_data ? (float)$preselect_data['total_due'] : '0.00' ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-slate-400 text-xs mb-1">Discount / Waiver</label>
                            <input type="number" step="0.01" class="form-control form-control-sm bg-slate-900 border-slate-700 text-emerald-400 font-mono text-xs" 
                                   id="input_discount" name="discount" value="0.00">
                        </div>
                    </div>

                    <!-- Total & Amount Paid Row -->
                    <div class="row g-2 mb-3 p-2 rounded bg-slate-950 border border-slate-800 align-items-center">
                        <div class="col-4">
                            <div class="text-slate-400 text-xs font-mono">Total Payable:</div>
                            <div class="fs-6 fw-bold text-cyan-400 font-mono" id="disp_total_payable">BDT 0.00</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-slate-300 text-xs mb-1">Amount Paid (BDT)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm bg-slate-900 border-emerald-600 text-emerald-400 font-mono text-xs fw-bold" 
                                   id="input_paid_amount" name="paid_amount" value="1000.00">
                        </div>
                        <div class="col-4">
                            <div class="text-slate-400 text-xs font-mono">Net Due Balance:</div>
                            <div class="fs-6 fw-bold font-mono text-danger" id="disp_net_due">BDT 0.00</div>
                        </div>
                    </div>

                    <!-- Payment Mode & Stamp Override -->
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Payment Method</label>
                            <select class="form-select form-select-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" id="input_payment_method" name="payment_method">
                                <option value="Cash" selected>Cash</option>
                                <option value="bKash">bKash (Merchant / Personal)</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Rocket">Rocket</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="POS/Card">POS / Debit Card</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Other">Other / Direct Pay</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Status Stamp</label>
                            <select class="form-select form-select-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" id="input_status_stamp">
                                <option value="AUTO" selected>Auto (Based on Payment)</option>
                                <option value="PAID">Force "PAID &amp; RECEIVED"</option>
                                <option value="UNPAID">Force "UNPAID / DUE"</option>
                                <option value="PARTIAL">Force "PARTIAL PAID"</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Trx ID / Reference</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                   id="input_transaction_id" name="transaction_id" placeholder="e.g. 9J28A7BC1" value="">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-slate-400 text-xs mb-1">Collected / Issued By</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs" 
                                   id="input_collected_by" name="collected_by" value="<?= htmlspecialchars($user_data['full_name'] ?? 'Admin') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Company Customization & Terms -->
            <div class="card invoice-studio-card mb-3">
                <div class="card-header bg-slate-900 border-bottom border-slate-800 d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-cyan-400">
                        <i class="fa-solid fa-sliders me-1"></i> 4. Terms, Notes &amp; Print Options
                    </span>
                    <button class="btn btn-link btn-sm text-slate-400 p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptions">
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                </div>
                <div class="collapse show" id="collapseOptions">
                    <div class="card-body p-3">
                        <div class="mb-2">
                            <label class="form-label text-slate-400 text-xs mb-1">Company / Brand Name</label>
                            <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs fw-bold" 
                                   id="comp_name" value="<?= htmlspecialchars($app_settings['company_name'] ?? 'City Fiber') ?>">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label text-slate-400 text-xs mb-1">Support Phone</label>
                                <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                       id="comp_phone" value="<?= htmlspecialchars($app_settings['phone'] ?? '+880 1700-000000') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-slate-400 text-xs mb-1">NOC Email</label>
                                <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs" 
                                       id="comp_email" value="<?= htmlspecialchars($app_settings['email'] ?? 'noc@cityfiber.bd') ?>">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-slate-400 text-xs mb-1">Payment Instructions &amp; Bank Info</label>
                            <textarea class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs" 
                                      id="inv_instructions" rows="2">Pay via bKash/Nagad Merchant or to our authorized field billing team. Hotline: <?= htmlspecialchars($app_settings['phone'] ?? '+880 1700-000000') ?>. Thank you for choosing City Fiber!</textarea>
                        </div>
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <div class="form-check form-check-sm">
                                <input class="form-check-input bg-slate-900 border-slate-700" type="checkbox" id="chkSignatures" checked>
                                <label class="form-check-label text-slate-300 text-xs" for="chkSignatures">
                                    Show Signature Lines
                                </label>
                            </div>
                            <div class="form-check form-check-sm">
                                <input class="form-check-input bg-slate-900 border-slate-700" type="checkbox" id="chkHeaderBrand" checked>
                                <label class="form-check-label text-slate-300 text-xs" for="chkHeaderBrand">
                                    Show Brand Banner
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons for Save to DB -->
            <div class="d-flex align-items-center justify-content-between p-3 rounded bg-slate-950 border border-slate-800">
                <div>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono text-xs" id="btnSaveToDb" title="Store record in MySQL bills table">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Save to Billing Database
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-success font-mono text-xs fw-bold px-3" onclick="triggerPrint()">
                        <i class="fa-solid fa-print me-1"></i> Print / Save PDF
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- RIGHT COLUMN: Real-Time Live Document Preview -->
    <div class="col-12 col-xl-7 invoice-preview-col">
        <div class="d-flex align-items-center justify-content-between mb-2 px-1 no-print">
            <span class="text-xs font-mono text-slate-400">
                <i class="fa-solid fa-eye me-1 text-cyan-400"></i> REAL-TIME DOCUMENT PREVIEW (WYSIWYG)
            </span>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-slate-900 border border-slate-700 text-cyan-400 font-mono text-xs" id="activeFormatBadge">Format: A4 Sheet</span>
                <button type="button" class="btn btn-xs btn-outline-secondary text-slate-300 font-mono" onclick="triggerPrint()">
                    <i class="fa-solid fa-print me-1"></i> Print
                </button>
            </div>
        </div>

        <div class="invoice-preview-wrapper" id="previewWrapper">
            <!-- A4 Document Sheet (Matches invoice.php exactly) -->
            <div class="doc-sheet" id="a4DocumentSheet">
                <!-- Status Stamp -->
                <div class="seal-stamp" id="previewSealStamp">PAID &amp; RECEIVED</div>

                <!-- Document Header Bar -->
                <div class="header-bar d-flex justify-content-between align-items-start pb-3 mb-3 border-bottom border-dark" id="previewBrandBanner">
                    <div>
                        <h3 class="fw-bold text-dark mb-0 font-sans" id="previewCompName">
                            <i class="fa-solid fa-server text-info me-2"></i><?= htmlspecialchars($app_settings['company_name'] ?? 'City Fiber') ?>
                        </h3>
                        <div class="text-muted text-xs" id="previewCompTagline"><?= htmlspecialchars($app_settings['company_tagline'] ?? 'Ultra Fast Fiber Optical Broadband Network') ?></div>
                        <div class="text-muted text-xs mt-1" id="previewCompContacts">
                            <?= nl2br(htmlspecialchars($app_settings['address'] ?? 'House 14, Road 5, Block B, Mirpur-10, Dhaka-1216')) ?><br>
                            <strong>Hotline:</strong> <span id="prevCompPhone"><?= htmlspecialchars($app_settings['phone'] ?? '+880 1700-000000') ?></span> | 
                            <strong>Email:</strong> <span id="prevCompEmail"><?= htmlspecialchars($app_settings['email'] ?? 'noc@cityfiber.bd') ?></span>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-dark text-white font-mono text-xs px-3 py-1 mb-2 d-inline-block" id="previewDocBadge">
                            OFFICIAL INVOICE / BILL
                        </span>
                        <div class="font-mono fw-bold fs-6 text-primary" id="previewInvNumber">
                            CF-INV-202611-1001
                        </div>
                        <div class="text-muted text-xs font-mono">
                            Issue Date: <strong id="previewInvDate"><?= date('Y-m-d') ?></strong>
                        </div>
                        <div class="text-danger text-xs font-mono">
                            Due Date: <strong id="previewDueDate"><?= date('Y-m-10') ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Bill To / Customer Information -->
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded border border-light-subtle h-100">
                            <span class="text-muted text-xs font-mono text-uppercase d-block mb-1">SUBSCRIBER DETAILS</span>
                            <h5 class="fw-bold text-dark mb-1" id="previewCustName">Tanvir Ahmed</h5>
                            <div class="text-muted text-xs font-mono mb-1">
                                <strong>Client ID:</strong> <span id="previewCustCode">CF-2026-M01</span>
                            </div>
                            <div class="text-muted text-xs mb-1">
                                <strong>Phone:</strong> <span id="previewCustPhone">+880 1711-234567</span>
                            </div>
                            <div class="text-muted text-xs">
                                <strong>Installation Address:</strong> <span id="previewCustAddress">House 14, Road 5, Block B, Mirpur-10, Dhaka</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded border border-light-subtle h-100">
                            <span class="text-muted text-xs font-mono text-uppercase d-block mb-1">CONNECTION SPECIFICATIONS</span>
                            <div class="text-xs mb-1"><strong>POP Zone:</strong> <span id="previewCustZone">Mirpur Core Node</span></div>
                            <div class="text-xs mb-1"><strong>Package:</strong> <span id="previewCustPackage">Turbo Optical 25 Mbps</span></div>
                            <div class="text-xs font-mono mb-1"><strong>Connection / IP:</strong> <span id="previewCustConn">cf.tanvir.pppoe</span></div>
                            <div class="text-xs font-mono"><strong>Billing Cycle:</strong> <span id="previewBillingMonth"><?= date('F Y') ?></span></div>
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <table class="table table-invoice mb-3">
                    <thead>
                        <tr>
                            <th style="width: 35px;">#</th>
                            <th>Service Description</th>
                            <th>Period / Details</th>
                            <th class="text-center" style="width: 50px;">Qty</th>
                            <th class="text-end" style="width: 100px;">Rate (BDT)</th>
                            <th class="text-end" style="width: 110px;">Total (BDT)</th>
                        </tr>
                    </thead>
                    <tbody id="previewLineItemsTbody">
                        <!-- Dynamic rows populated via JS -->
                    </tbody>
                </table>

                <!-- Totals & Payment Summary -->
                <div class="row align-items-end mb-4">
                    <div class="col-7">
                        <div class="p-3 bg-light rounded text-xs">
                            <strong>Payment Instructions &amp; Policies:</strong><br>
                            <span id="previewInstructions">Pay via bKash/Nagad Merchant or to our authorized field billing team. Hotline: +880 1700-000000. Thank you for choosing City Fiber!</span>
                            <div class="mt-2 text-muted" style="font-size:11px;">
                                Payment Method: <strong id="previewPaymentMethod" class="text-dark">Cash</strong> 
                                <span id="previewTrxIdWrapper"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="border-top border-bottom py-2">
                            <div class="d-flex justify-content-between mb-1 text-xs">
                                <span class="text-muted">Subtotal:</span>
                                <span class="font-mono" id="previewSubtotal">BDT 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 text-xs" id="previewExtraRow">
                                <span class="text-muted">Extra / Setup Fee:</span>
                                <span class="font-mono" id="previewExtraCharge">BDT 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 text-xs text-danger" id="previewDueRow">
                                <span>Previous Dues:</span>
                                <span class="font-mono" id="previewPreviousDue">BDT 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 text-xs text-success" id="previewDiscountRow">
                                <span>Discount / Waiver:</span>
                                <span class="font-mono" id="previewDiscount">BDT 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-top border-dark text-dark fw-bold">
                                <span>Total Payable:</span>
                                <span class="font-mono fs-5" id="previewTotalPayable">BDT 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between text-xs text-success fw-semibold">
                                <span>Amount Paid:</span>
                                <span class="font-mono" id="previewPaidAmount">BDT 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between text-xs fw-bold text-danger" id="previewNetDueWrapper">
                                <span>Net Balance Due:</span>
                                <span class="font-mono fs-6" id="previewNetDue">BDT 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Signatures -->
                <div class="row pt-4 mt-4 text-center text-xs text-muted font-mono" id="previewSignaturesBlock" style="border-top: 1px dashed #cbd5e1;">
                    <div class="col-6">
                        <div style="border-top: 1px solid #94a3b8; width: 180px; margin: 0 auto; padding-top: 4px;">
                            Customer Signature
                        </div>
                    </div>
                    <div class="col-6">
                        <div style="border-top: 1px solid #94a3b8; width: 180px; margin: 0 auto; padding-top: 4px;">
                            <span id="previewSignCollector">Authorized Operator (NOC)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- POS 80mm Thermal Receipt Layout (Toggled on Demand) -->
            <div class="pos-sheet text-dark" id="posReceiptSheet">
                <div class="text-center mb-3">
                    <div class="fw-bold fs-6" id="posCompName"><?= htmlspecialchars($app_settings['company_name'] ?? 'City Fiber') ?></div>
                    <div style="font-size:10px;" id="posCompTagline"><?= htmlspecialchars($app_settings['company_tagline'] ?? 'Optical Broadband Network') ?></div>
                    <div style="font-size:10px;">Hotline: <span id="posCompPhone"><?= htmlspecialchars($app_settings['phone'] ?? '+880 1700-000000') ?></span></div>
                    <div class="border-top border-bottom border-dark py-1 my-2 fw-bold text-uppercase" id="posDocTitle">
                        OFFICIAL CUSTOMER RECEIPT
                    </div>
                </div>

                <div class="mb-2" style="font-size:11px;">
                    <div>Doc #: <span class="fw-bold" id="posInvNum">CF-INV-202611-1001</span></div>
                    <div>Date: <span id="posInvDate"><?= date('Y-m-d') ?></span></div>
                    <div>Cust ID: <span class="fw-bold" id="posCustCode">CF-2026-M01</span></div>
                    <div>Name: <span id="posCustName">Tanvir Ahmed</span></div>
                    <div>Phone: <span id="posCustPhone">+880 1711-234567</span></div>
                    <div>Package: <span id="posCustPkg">Turbo 25M</span></div>
                </div>

                <div class="border-top border-dark pt-2 mb-2">
                    <div class="d-flex justify-content-between fw-bold" style="font-size:11px;">
                        <span>Item / Description</span>
                        <span>Amount (BDT)</span>
                    </div>
                    <div class="border-bottom border-dark pb-1 mb-1"></div>
                    
                    <div id="posLineItemsList">
                        <!-- Populated via JS -->
                    </div>

                    <div class="border-top border-dark pt-1 mt-1 d-flex justify-content-between fw-bold">
                        <span>TOTAL PAYABLE:</span>
                        <span id="posTotalPayable">BDT 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mt-1 text-success fw-bold">
                        <span>Amount Paid:</span>
                        <span id="posPaidAmount">BDT 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold text-danger">
                        <span>Current Due:</span>
                        <span id="posNetDue">BDT 0.00</span>
                    </div>
                </div>

                <div class="text-center my-2 fw-bold" id="posStatusStamp">[ STATUS: PAID (CASH) ]</div>

                <div class="border-top border-dashed border-dark pt-2 text-center" style="font-size:10px;">
                    <div id="posInstructions">Thank you for choosing City Fiber!</div>
                    <div>Hotline: <span id="posFooterPhone"><?= htmlspecialchars($app_settings['phone'] ?? '+880 1700-000000') ?></span></div>
                    <div class="mt-1">Generated: <?= date('Y-m-d H:i') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Studio JavaScript Engine -->
<script>
// State Management
let currentFormat = 'a4';
let lineItems = [
    {
        id: 1,
        desc: 'Optical Broadband Internet Plan (25 Mbps)',
        period: 'November 2026',
        qty: 1,
        rate: 1000.00
    }
];

// Document Elements
const quickCustomerSelect = document.getElementById('quickCustomerSelect');
const lineItemsContainer = document.getElementById('lineItemsContainer');
const btnAddItemRow = document.getElementById('btnAddItemRow');
const btnFillSample = document.getElementById('btnFillSample');
const btnClearForm = document.getElementById('btnClearForm');
const btnNewInvNum = document.getElementById('btnNewInvNum');
const btnFormatA4 = document.getElementById('btnFormatA4');
const btnFormatPOS = document.getElementById('btnFormatPOS');
const a4Sheet = document.getElementById('a4DocumentSheet');
const posSheet = document.getElementById('posReceiptSheet');
const activeFormatBadge = document.getElementById('activeFormatBadge');

// Format Currency
function formatBDT(amount) {
    return 'BDT ' + parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Render Line Items in Form Editor
function renderEditorLineItems() {
    lineItemsContainer.innerHTML = '';
    lineItems.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'line-item-row p-2 mb-2 rounded bg-slate-950 border border-slate-800';
        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="font-mono text-cyan-400 text-xs fw-bold">#${index + 1}</span>
                ${lineItems.length > 1 ? `
                    <button type="button" class="btn btn-link btn-sm text-danger p-0 item-delete-btn" onclick="removeLineItem(${item.id})" title="Remove item">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                ` : ''}
            </div>
            <div class="row g-1">
                <div class="col-7">
                    <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs item-desc-input" 
                           placeholder="Service / Item Description" value="${escapeHtml(item.desc)}" oninput="updateLineItem(${item.id}, 'desc', this.value)">
                </div>
                <div class="col-5">
                    <input type="text" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white text-xs item-period-input" 
                           placeholder="Period / Specs" value="${escapeHtml(item.period)}" oninput="updateLineItem(${item.id}, 'period', this.value)">
                </div>
                <div class="col-3">
                    <label class="text-slate-400 font-mono" style="font-size:10px;">Qty</label>
                    <input type="number" min="1" step="1" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs item-qty-input" 
                           value="${item.qty}" oninput="updateLineItem(${item.id}, 'qty', this.value)">
                </div>
                <div class="col-4">
                    <label class="text-slate-400 font-mono" style="font-size:10px;">Rate (BDT)</label>
                    <input type="number" min="0" step="0.01" class="form-control form-control-sm bg-slate-900 border-slate-700 text-white font-mono text-xs item-rate-input" 
                           value="${item.rate}" oninput="updateLineItem(${item.id}, 'rate', this.value)">
                </div>
                <div class="col-5">
                    <label class="text-slate-400 font-mono" style="font-size:10px;">Total</label>
                    <div class="form-control form-control-sm bg-slate-900 border-slate-700 text-cyan-400 font-mono text-xs fw-bold">
                        ${formatBDT(item.qty * item.rate)}
                    </div>
                </div>
            </div>
        `;
        lineItemsContainer.appendChild(row);
    });

    updatePreview();
}

// Add Item
function addLineItem(desc = '', period = '', rate = 0, qty = 1) {
    const nextId = lineItems.length > 0 ? Math.max(...lineItems.map(i => i.id)) + 1 : 1;
    lineItems.push({ id: nextId, desc, period, rate: parseFloat(rate) || 0, qty: parseInt(qty) || 1 });
    renderEditorLineItems();
}

// Remove Item
function removeLineItem(id) {
    if (lineItems.length <= 1) return;
    lineItems = lineItems.filter(i => i.id !== id);
    renderEditorLineItems();
}

// Update item field
function updateLineItem(id, field, value) {
    const item = lineItems.find(i => i.id === id);
    if (item) {
        if (field === 'qty') item.qty = parseInt(value) || 1;
        else if (field === 'rate') item.rate = parseFloat(value) || 0;
        else item[field] = value;
    }
    updatePreview();
}

// Escape HTML utility
function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

// Main Preview Update Function
function updatePreview() {
    // 1. Gather all inputs
    const invNumber = document.getElementById('inv_number').value || 'INV-000';
    const invMonth = document.getElementById('inv_month').value;
    const invDate = document.getElementById('inv_date').value;
    const dueDate = document.getElementById('due_date').value;

    const custName = document.getElementById('cust_name').value || 'Valued Subscriber';
    const custCode = document.getElementById('cust_code').value || 'CF-000';
    const custPhone = document.getElementById('cust_phone').value || '—';
    const custAddress = document.getElementById('cust_address').value || '—';
    const custZone = document.getElementById('cust_zone').value || 'Primary Node';
    const custPackage = document.getElementById('cust_package').value || 'Broadband';
    const custConn = document.getElementById('cust_conn').value || '—';

    const extraCharge = parseFloat(document.getElementById('input_extra_charge').value) || 0;
    const previousDue = parseFloat(document.getElementById('input_previous_due').value) || 0;
    const discount = parseFloat(document.getElementById('input_discount').value) || 0;
    const paidAmount = parseFloat(document.getElementById('input_paid_amount').value) || 0;

    const paymentMethod = document.getElementById('input_payment_method').value;
    const trxId = document.getElementById('input_transaction_id').value.trim();
    const collectedBy = document.getElementById('input_collected_by').value.trim();
    const statusStampMode = document.getElementById('input_status_stamp').value;

    const compName = document.getElementById('comp_name').value;
    const compPhone = document.getElementById('comp_phone').value;
    const compEmail = document.getElementById('comp_email').value;
    const instructions = document.getElementById('inv_instructions').value;

    const showSignatures = document.getElementById('chkSignatures').checked;
    const showHeaderBrand = document.getElementById('chkHeaderBrand').checked;

    // 2. Calculate Subtotal & Totals
    let subtotal = 0;
    lineItems.forEach(i => {
        subtotal += (i.qty * i.rate);
    });

    const totalPayable = Math.max(0, (subtotal + extraCharge + previousDue) - discount);
    const netDue = Math.max(0, totalPayable - paidAmount);

    // Save hidden inputs for form submit
    document.getElementById('hidden_subtotal').value = subtotal;
    document.getElementById('hidden_total_payable').value = totalPayable;
    document.getElementById('summarySubtotalBadge').innerText = 'Subtotal: ' + formatBDT(subtotal);
    document.getElementById('disp_total_payable').innerText = formatBDT(totalPayable);
    document.getElementById('disp_net_due').innerText = formatBDT(netDue);

    // 3. Update Status Stamp
    let finalStatus = 'PAID';
    if (statusStampMode === 'AUTO') {
        if (netDue <= 0 && totalPayable > 0 && paidAmount > 0) {
            finalStatus = 'PAID';
        } else if (paidAmount > 0 && netDue > 0) {
            finalStatus = 'PARTIAL';
        } else {
            finalStatus = 'UNPAID';
        }
    } else {
        finalStatus = statusStampMode;
    }

    const previewSealStamp = document.getElementById('previewSealStamp');
    previewSealStamp.className = 'seal-stamp';
    if (finalStatus === 'PAID') {
        previewSealStamp.innerText = 'PAID & RECEIVED';
        previewSealStamp.classList.remove('stamp-unpaid', 'stamp-partial');
    } else if (finalStatus === 'PARTIAL') {
        previewSealStamp.innerText = 'PARTIAL PAID';
        previewSealStamp.classList.add('stamp-partial');
    } else {
        previewSealStamp.innerText = 'UNPAID / DUE';
        previewSealStamp.classList.add('stamp-unpaid');
    }

    // 4. Update A4 Sheet Elements
    document.getElementById('previewCompName').innerHTML = `<i class="fa-solid fa-server text-info me-2"></i>${escapeHtml(compName)}`;
    document.getElementById('prevCompPhone').innerText = compPhone;
    document.getElementById('prevCompEmail').innerText = compEmail;
    document.getElementById('previewBrandBanner').style.display = showHeaderBrand ? 'flex' : 'none';

    document.getElementById('previewInvNumber').innerText = invNumber;
    document.getElementById('previewInvDate').innerText = invDate;
    document.getElementById('previewDueDate').innerText = dueDate;

    document.getElementById('previewCustName').innerText = custName;
    document.getElementById('previewCustCode').innerText = custCode;
    document.getElementById('previewCustPhone').innerText = custPhone;
    document.getElementById('previewCustAddress').innerText = custAddress;
    document.getElementById('previewCustZone').innerText = custZone;
    document.getElementById('previewCustPackage').innerText = custPackage;
    document.getElementById('previewCustConn').innerText = custConn;

    if (invMonth) {
        const parts = invMonth.split('-');
        const dateObj = new Date(parts[0], parseInt(parts[1]) - 1, 1);
        document.getElementById('previewBillingMonth').innerText = dateObj.toLocaleString('en-US', { month: 'long', year: 'numeric' });
    }

    // A4 Line Items Table
    const tbody = document.getElementById('previewLineItemsTbody');
    tbody.innerHTML = '';
    lineItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        const numPad = (index + 1).toString().padStart(2, '0');
        const lineTotal = item.qty * item.rate;
        tr.innerHTML = `
            <td class="font-mono text-muted">${numPad}</td>
            <td>
                <div class="fw-bold text-dark">${escapeHtml(item.desc)}</div>
                ${item.period ? `<div class="text-muted text-xs">${escapeHtml(item.period)}</div>` : ''}
            </td>
            <td class="font-mono text-xs text-muted">${escapeHtml(item.period || '—')}</td>
            <td class="text-center font-mono">${item.qty}</td>
            <td class="text-end font-mono">${parseFloat(item.rate).toFixed(2)}</td>
            <td class="text-end font-mono fw-bold text-dark">${parseFloat(lineTotal).toFixed(2)}</td>
        `;
        tbody.appendChild(tr);
    });

    // A4 Summary
    document.getElementById('previewSubtotal').innerText = formatBDT(subtotal);
    
    const extraRow = document.getElementById('previewExtraRow');
    if (extraCharge > 0) {
        extraRow.style.display = 'flex';
        document.getElementById('previewExtraCharge').innerText = formatBDT(extraCharge);
    } else {
        extraRow.style.display = 'none';
    }

    const dueRow = document.getElementById('previewDueRow');
    if (previousDue > 0) {
        dueRow.style.display = 'flex';
        document.getElementById('previewPreviousDue').innerText = formatBDT(previousDue);
    } else {
        dueRow.style.display = 'none';
    }

    const discountRow = document.getElementById('previewDiscountRow');
    if (discount > 0) {
        discountRow.style.display = 'flex';
        document.getElementById('previewDiscount').innerText = '- ' + formatBDT(discount);
    } else {
        discountRow.style.display = 'none';
    }

    document.getElementById('previewTotalPayable').innerText = formatBDT(totalPayable);
    document.getElementById('previewPaidAmount').innerText = formatBDT(paidAmount);
    document.getElementById('previewNetDue').innerText = formatBDT(netDue);

    // Payment details
    document.getElementById('previewPaymentMethod').innerText = paymentMethod;
    const trxWrapper = document.getElementById('previewTrxIdWrapper');
    if (trxId) {
        trxWrapper.innerHTML = `| Trx: <strong class="font-mono">${escapeHtml(trxId)}</strong>`;
    } else {
        trxWrapper.innerHTML = '';
    }

    document.getElementById('previewInstructions').innerText = instructions;
    document.getElementById('previewSignaturesBlock').style.display = showSignatures ? 'flex' : 'none';
    document.getElementById('previewSignCollector').innerText = collectedBy ? `Issued by: ${collectedBy}` : 'Authorized Operator (NOC)';

    // 5. Update POS Receipt Elements
    document.getElementById('posCompName').innerText = compName;
    document.getElementById('posCompPhone').innerText = compPhone;
    document.getElementById('posFooterPhone').innerText = compPhone;
    document.getElementById('posInvNum').innerText = invNumber;
    document.getElementById('posInvDate').innerText = invDate;
    document.getElementById('posCustName').innerText = custName;
    document.getElementById('posCustCode').innerText = custCode;
    document.getElementById('posCustPhone').innerText = custPhone;
    document.getElementById('posCustPkg').innerText = custPackage;

    const posItemsList = document.getElementById('posLineItemsList');
    posItemsList.innerHTML = '';
    lineItems.forEach(item => {
        const row = document.createElement('div');
        row.className = 'd-flex justify-content-between mb-1';
        row.innerHTML = `
            <span>${escapeHtml(item.desc)} x${item.qty}</span>
            <span class="fw-bold">${(item.qty * item.rate).toFixed(2)}</span>
        `;
        posItemsList.appendChild(row);
    });

    if (extraCharge > 0) {
        const er = document.createElement('div');
        er.className = 'd-flex justify-content-between text-muted';
        er.innerHTML = `<span>Extra/Setup Fee</span><span>${extraCharge.toFixed(2)}</span>`;
        posItemsList.appendChild(er);
    }
    if (previousDue > 0) {
        const dr = document.createElement('div');
        dr.className = 'd-flex justify-content-between text-danger';
        dr.innerHTML = `<span>Previous Due</span><span>${previousDue.toFixed(2)}</span>`;
        posItemsList.appendChild(dr);
    }

    document.getElementById('posTotalPayable').innerText = 'BDT ' + totalPayable.toFixed(2);
    document.getElementById('posPaidAmount').innerText = 'BDT ' + paidAmount.toFixed(2);
    document.getElementById('posNetDue').innerText = 'BDT ' + netDue.toFixed(2);
    document.getElementById('posStatusStamp').innerText = `[ STATUS: ${finalStatus} (${paymentMethod.toUpperCase()}) ]`;
    document.getElementById('posInstructions').innerText = instructions;
}

// Quick customer change listener
quickCustomerSelect.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (this.value && opt) {
        document.getElementById('db_customer_id').value = this.value;
        document.getElementById('cust_name').value = opt.getAttribute('data-name') || '';
        document.getElementById('cust_code').value = opt.getAttribute('data-code') || '';
        document.getElementById('cust_phone').value = opt.getAttribute('data-phone') || '';
        document.getElementById('cust_address').value = opt.getAttribute('data-address') || '';
        document.getElementById('cust_zone').value = opt.getAttribute('data-zone') || '';
        document.getElementById('cust_package').value = (opt.getAttribute('data-package') || '') + ' (' + (opt.getAttribute('data-speed') || '') + 'M)';
        document.getElementById('cust_conn').value = opt.getAttribute('data-pppoe') || opt.getAttribute('data-ip') || '';

        const bill = parseFloat(opt.getAttribute('data-bill')) || 0;
        const due = parseFloat(opt.getAttribute('data-due')) || 0;

        document.getElementById('input_previous_due').value = due.toFixed(2);
        document.getElementById('input_paid_amount').value = (bill + due).toFixed(2);

        // Update default line item
        if (lineItems.length > 0) {
            lineItems[0].desc = 'Optical Broadband - ' + (opt.getAttribute('data-package') || 'Monthly Plan');
            lineItems[0].rate = bill;
        }
        renderEditorLineItems();
    } else {
        document.getElementById('db_customer_id').value = 0;
    }
    updatePreview();
});

// Format Switcher Handlers
btnFormatA4.addEventListener('click', function() {
    currentFormat = 'a4';
    btnFormatA4.classList.add('active', 'btn-cyan');
    btnFormatA4.classList.remove('btn-outline-cyan');
    btnFormatPOS.classList.remove('active', 'btn-cyan');
    btnFormatPOS.classList.add('btn-outline-cyan');

    a4Sheet.style.display = 'block';
    posSheet.style.display = 'none';
    activeFormatBadge.innerText = 'Format: A4 Sheet';
});

btnFormatPOS.addEventListener('click', function() {
    currentFormat = 'pos';
    btnFormatPOS.classList.add('active', 'btn-cyan');
    btnFormatPOS.classList.remove('btn-outline-cyan');
    btnFormatA4.classList.remove('active', 'btn-cyan');
    btnFormatA4.classList.add('btn-outline-cyan');

    a4Sheet.style.display = 'none';
    posSheet.style.display = 'block';
    activeFormatBadge.innerText = 'Format: POS 80mm';
});

// Generate new invoice number
btnNewInvNum.addEventListener('click', function() {
    const randomNum = Math.floor(1000 + Math.random() * 9000);
    const dateStr = new Date().toISOString().slice(0,7).replace('-', '');
    document.getElementById('inv_number').value = `CF-INV-${dateStr}-${randomNum}`;
    updatePreview();
});

// Fill Sample Data Button
btnFillSample.addEventListener('click', function() {
    document.getElementById('cust_name').value = 'Rafiqul Islam';
    document.getElementById('cust_code').value = 'CF-2026-N88';
    document.getElementById('cust_phone').value = '+880 1819-556677';
    document.getElementById('cust_address').value = 'Flat 4B, Green View Tower, Mirpur-2, Dhaka';
    document.getElementById('cust_zone').value = 'Mirpur Ring-2';
    document.getElementById('cust_package').value = 'Power Optical (30 Mbps)';
    document.getElementById('cust_conn').value = 'cf.rafiqul.pppoe';

    lineItems = [
        {
            id: 1,
            desc: 'Optical Broadband Monthly Internet (30 Mbps)',
            period: 'November 2026',
            qty: 1,
            rate: 1200.00
        },
        {
            id: 2,
            desc: 'Dual-Band Gigabit Wi-Fi 6 Router (Setup Included)',
            period: 'TP-Link Archer AX12',
            qty: 1,
            rate: 3200.00
        },
        {
            id: 3,
            desc: 'Drop Fiber Cable & Splice Fusion Setup',
            period: '120m Fiber Core',
            qty: 1,
            rate: 800.00
        }
    ];

    document.getElementById('input_extra_charge').value = '0.00';
    document.getElementById('input_previous_due').value = '0.00';
    document.getElementById('input_discount').value = '200.00';
    document.getElementById('input_paid_amount').value = '5000.00';
    document.getElementById('input_payment_method').value = 'bKash';
    document.getElementById('input_transaction_id').value = '8X92B7KC9';

    renderEditorLineItems();
});

// Reset Form Button
btnClearForm.addEventListener('click', function() {
    if (confirm('Reset invoice editor to blank state?')) {
        document.getElementById('invoiceForm').reset();
        document.getElementById('db_customer_id').value = 0;
        lineItems = [
            { id: 1, desc: 'Optical Broadband Subscription', period: '', qty: 1, rate: 0 }
        ];
        renderEditorLineItems();
    }
});

// Preset Buttons
document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const desc = this.getAttribute('data-desc');
        const period = this.getAttribute('data-period');
        const rate = parseFloat(this.getAttribute('data-rate')) || 0;
        addLineItem(desc, period, rate, 1);
    });
});

// Add Item Button
btnAddItemRow.addEventListener('click', function() {
    addLineItem('', '', 0, 1);
});

// Print Trigger
function triggerPrint() {
    window.print();
}

// Bind live changes to all form inputs
const reactiveInputIds = [
    'inv_number', 'inv_month', 'inv_date', 'due_date',
    'cust_name', 'cust_code', 'cust_phone', 'cust_zone', 'cust_address', 'cust_package', 'cust_conn',
    'input_extra_charge', 'input_previous_due', 'input_discount', 'input_paid_amount',
    'input_payment_method', 'input_status_stamp', 'input_transaction_id', 'input_collected_by',
    'comp_name', 'comp_phone', 'comp_email', 'inv_instructions',
    'chkSignatures', 'chkHeaderBrand'
];

reactiveInputIds.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    renderEditorLineItems();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
