<?php
// invoice.php - Printable Customer Invoice & Money Receipt
require_once __DIR__ . '/config/auth.php';
require_login();

$receipt_no = $_GET['receipt_no'] ?? '';
$bill_number = $_GET['bill_number'] ?? '';
$format = $_GET['format'] ?? 'a4'; // 'a4' or 'pos'

$payment = null;
$bill = null;
$customer = null;

if (!empty($receipt_no)) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.customer_code, c.name AS customer_name, c.phone, c.address, c.ip_address, c.pppoe_username, 
               z.name AS zone_name, pkg.name AS package_name, pkg.speed_mbps, b.bill_number AS linked_bill, b.billing_month
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        LEFT JOIN zones z ON c.zone_id = z.id
        LEFT JOIN packages pkg ON c.package_id = pkg.id
        LEFT JOIN bills b ON p.bill_id = b.id
        WHERE p.receipt_no = ?
    ");
    $stmt->execute([$receipt_no]);
    $payment = $stmt->fetch();
    if ($payment) {
        $customer = $payment;
    }
} elseif (!empty($bill_number)) {
    $stmt = $pdo->prepare("
        SELECT b.*, c.customer_code, c.name AS customer_name, c.phone, c.address, c.ip_address, c.pppoe_username,
               z.name AS zone_name, pkg.name AS package_name, pkg.speed_mbps
        FROM bills b
        JOIN customers c ON b.customer_id = c.id
        LEFT JOIN zones z ON c.zone_id = z.id
        LEFT JOIN packages pkg ON c.package_id = pkg.id
        WHERE b.bill_number = ?
    ");
    $stmt->execute([$bill_number]);
    $bill = $stmt->fetch();
    if ($bill) {
        $customer = $bill;
    }
}

if (!$customer) {
    die("
    <div style='background:#0b0f19; color:#f43f5e; font-family:monospace; padding:40px; text-align:center;'>
        <h3>Document Not Found</h3>
        <p>Could not find invoice or receipt matching the provided reference.</p>
        <a href='index.php' style='color:#06b6d4;'>&larr; Return to NOC Dashboard</a>
    </div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($receipt_no) ? 'Money Receipt ' . htmlspecialchars($receipt_no) : 'Invoice ' . htmlspecialchars($bill_number) ?> | <?= htmlspecialchars($app_settings['company_name']) ?></title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #0b0f19;
            color: #1e293b;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
        }
        .font-mono { font-family: 'JetBrains Mono', monospace !important; }
        
        /* A4 Document Sheet */
        .doc-sheet {
            background: #ffffff;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            padding: 40px;
            position: relative;
        }

        /* 80mm POS Thermal Receipt Format */
        .pos-sheet {
            background: #ffffff;
            width: 320px;
            margin: 0 auto;
            padding: 20px 15px;
            border-radius: 4px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.5);
            font-size: 12px;
            font-family: 'JetBrains Mono', monospace;
        }

        .seal-stamp {
            position: absolute;
            right: 40px;
            top: 130px;
            border: 3px double #10b981;
            color: #10b981;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 1.1rem;
            padding: 6px 14px;
            transform: rotate(-10deg);
            opacity: 0.85;
            letter-spacing: 2px;
            border-radius: 6px;
        }

        .seal-stamp.stamp-unpaid {
            border-color: #f43f5e;
            color: #f43f5e;
        }

        .header-bar {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .table-invoice th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 10px 12px;
            border-bottom: 2px solid #cbd5e1;
        }

        .table-invoice td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .top-action-bar {
            max-width: 800px;
            margin: 0 auto 15px auto;
        }

        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .top-action-bar, .no-print { display: none !important; }
            .doc-sheet, .pos-sheet { box-shadow: none !important; margin: 0 !important; width: 100% !important; padding: 20px !important; }
        }
    </style>
</head>
<body>

<!-- Top Navigation & Switcher (No Print) -->
<div class="top-action-bar d-flex align-items-center justify-content-between no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-secondary text-slate-300 font-mono text-xs">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
        <a href="billing.php" class="btn btn-sm btn-outline-secondary text-slate-300 font-mono text-xs">
            Invoices
        </a>
        <a href="payment_receive.php" class="btn btn-sm btn-outline-secondary text-slate-300 font-mono text-xs">
            Collections
        </a>
        <a href="single_invoice.php" class="btn btn-sm btn-outline-cyan font-mono text-xs">
            <i class="fa-solid fa-plus me-1"></i> Manual Invoice
        </a>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="?<?= !empty($receipt_no) ? 'receipt_no=' . urlencode($receipt_no) : 'bill_number=' . urlencode($bill_number) ?>&format=a4" class="btn btn-sm <?= $format === 'a4' ? 'btn-cyan text-dark' : 'btn-outline-secondary text-slate-300' ?> font-mono text-xs">
            <i class="fa-solid fa-file-lines me-1"></i> A4 Invoice
        </a>
        <a href="?<?= !empty($receipt_no) ? 'receipt_no=' . urlencode($receipt_no) : 'bill_number=' . urlencode($bill_number) ?>&format=pos" class="btn btn-sm <?= $format === 'pos' ? 'btn-cyan text-dark' : 'btn-outline-secondary text-slate-300' ?> font-mono text-xs">
            <i class="fa-solid fa-receipt me-1"></i> POS 80mm
        </a>
        <button onclick="window.print()" class="btn btn-sm btn-success font-mono text-xs fw-bold">
            <i class="fa-solid fa-print me-1"></i> Print Document
        </button>
    </div>
</div>

<?php if ($format === 'pos'): ?>
    <!-- POS 80mm Thermal Receipt Layout -->
    <div class="pos-sheet text-dark">
        <div class="text-center mb-3">
            <div class="fw-bold fs-6"><?= htmlspecialchars($app_settings['company_name']) ?></div>
            <div style="font-size:10px;"><?= htmlspecialchars($app_settings['company_tagline']) ?></div>
            <div style="font-size:10px;">Hotline: <?= htmlspecialchars($app_settings['phone']) ?></div>
            <div style="font-size:10px;"><?= htmlspecialchars($app_settings['email']) ?></div>
            <div class="border-top border-bottom border-dark py-1 my-2 fw-bold">
                <?= $payment ? 'OFFICIAL MONEY RECEIPT' : 'CUSTOMER BROADBAND BILL' ?>
            </div>
        </div>

        <div class="mb-2" style="font-size:11px;">
            <div>Doc #: <span class="fw-bold"><?= $payment ? htmlspecialchars($payment['receipt_no']) : htmlspecialchars($bill['bill_number']) ?></span></div>
            <div>Date: <?= $payment ? htmlspecialchars($payment['payment_date']) : htmlspecialchars($bill['bill_date']) ?></div>
            <div>Cust ID: <span class="fw-bold"><?= htmlspecialchars($customer['customer_code']) ?></span></div>
            <div>Name: <?= htmlspecialchars($customer['customer_name']) ?></div>
            <div>Phone: <?= htmlspecialchars($customer['phone']) ?></div>
            <div>Zone: <?= htmlspecialchars($customer['zone_name'] ?: 'Main Node') ?></div>
            <div>Package: <?= htmlspecialchars($customer['package_name'] ?: 'Broadband') ?> (<?= $customer['speed_mbps'] ?>M)</div>
            <?php if (!empty($customer['pppoe_username'])): ?>
                <div>PPPoE: <?= htmlspecialchars($customer['pppoe_username']) ?></div>
            <?php endif; ?>
        </div>

        <div class="border-top border-dark pt-2 mb-2">
            <div class="d-flex justify-content-between">
                <span>Description</span>
                <span>Amount (BDT)</span>
            </div>
            <div class="border-bottom border-dark pb-1 mb-1"></div>
            <?php if ($payment): ?>
                <div class="d-flex justify-content-between">
                    <span>Broadband Payment</span>
                    <span class="fw-bold"><?= number_format($payment['amount'], 2) ?></span>
                </div>
                <?php if ($payment['discount'] > 0): ?>
                    <div class="d-flex justify-content-between">
                        <span>Discount Waived</span>
                        <span>-<?= number_format($payment['discount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="border-top border-dark pt-1 mt-1 d-flex justify-content-between fw-bold fs-6">
                    <span>PAID TOTAL:</span>
                    <span>BDT <?= number_format($payment['amount'], 2) ?></span>
                </div>
                <div class="text-center my-1 fw-bold text-success">[ STATUS: PAID (<?= strtoupper($payment['payment_method']) ?>) ]</div>
            <?php else: ?>
                <div class="d-flex justify-content-between">
                    <span>Package Monthly Fee</span>
                    <span><?= number_format($bill['package_amount'], 2) ?></span>
                </div>
                <?php if ($bill['previous_due'] > 0): ?>
                    <div class="d-flex justify-content-between">
                        <span>Previous Outstanding</span>
                        <span><?= number_format($bill['previous_due'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="border-top border-dark pt-1 mt-1 d-flex justify-content-between fw-bold fs-6">
                    <span>TOTAL PAYABLE:</span>
                    <span>BDT <?= number_format($bill['total_payable'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span>Amount Paid:</span>
                    <span>BDT <?= number_format($bill['paid_amount'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Current Due:</span>
                    <span>BDT <?= number_format($bill['due_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="border-top border-dashed border-dark pt-2 text-center" style="font-size:10px;">
            <div>Thank you for choosing <?= htmlspecialchars($app_settings['company_name']) ?>!</div>
            <div>Helpline: <?= htmlspecialchars($app_settings['phone']) ?></div>
            <div class="mt-2">Generated on: <?= date('Y-m-d H:i') ?></div>
        </div>
    </div>

<?php else: ?>
    <!-- A4 Full Professional ISP Document Sheet -->
    <div class="doc-sheet">
        <!-- Stamp Indicator -->
        <?php if ($payment || ($bill && $bill['status'] === 'Paid')): ?>
            <div class="seal-stamp">PAID &amp; RECEIVED</div>
        <?php else: ?>
            <div class="seal-stamp stamp-unpaid"><?= strtoupper($bill['status']) ?></div>
        <?php endif; ?>

        <!-- Document Header Bar -->
        <div class="header-bar d-flex justify-content-between align-items-start">
            <div>
                <h3 class="fw-bold text-dark mb-0 font-sans">
                    <i class="fa-solid fa-server text-info me-2"></i><?= htmlspecialchars($app_settings['company_name']) ?>
                </h3>
                <div class="text-muted text-xs"><?= htmlspecialchars($app_settings['company_tagline']) ?></div>
                <div class="text-muted text-xs mt-1">
                    <?= nl2br(htmlspecialchars($app_settings['address'])) ?><br>
                    <strong>Support Hotline:</strong> <?= htmlspecialchars($app_settings['phone']) ?> | <strong>Email:</strong> <?= htmlspecialchars($app_settings['email']) ?>
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-dark text-white font-mono text-xs px-3 py-1 mb-2 d-inline-block">
                    <?= $payment ? 'OFFICIAL MONEY RECEIPT' : 'INVOICE / BILL' ?>
                </span>
                <div class="font-mono fw-bold fs-6 text-primary">
                    <?= $payment ? htmlspecialchars($payment['receipt_no']) : htmlspecialchars($bill['bill_number']) ?>
                </div>
                <div class="text-muted text-xs font-mono">
                    Issue Date: <strong><?= $payment ? htmlspecialchars($payment['payment_date']) : htmlspecialchars($bill['bill_date']) ?></strong>
                </div>
                <?php if ($bill): ?>
                    <div class="text-danger text-xs font-mono">
                        Due Date: <strong><?= htmlspecialchars($bill['due_date']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bill To / Customer Information -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="p-3 bg-light rounded border border-light-subtle h-100">
                    <span class="text-muted text-xs font-mono text-uppercase d-block mb-1">SUBSCRIBER DETAILS</span>
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($customer['customer_name']) ?></h5>
                    <div class="text-muted text-xs font-mono mb-1">
                        <strong>Client ID:</strong> <?= htmlspecialchars($customer['customer_code']) ?>
                    </div>
                    <div class="text-muted text-xs mb-1">
                        <strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?>
                    </div>
                    <div class="text-muted text-xs">
                        <strong>Installation Address:</strong> <?= htmlspecialchars($customer['address']) ?>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 bg-light rounded border border-light-subtle h-100">
                    <span class="text-muted text-xs font-mono text-uppercase d-block mb-1">CONNECTION SPECIFICATIONS</span>
                    <div class="text-xs mb-1"><strong>POP Zone:</strong> <?= htmlspecialchars($customer['zone_name'] ?: 'Primary Core Ring') ?></div>
                    <div class="text-xs mb-1"><strong>Package:</strong> <?= htmlspecialchars($customer['package_name'] ?: 'High Speed Optical') ?> (<?= $customer['speed_mbps'] ?> Mbps)</div>
                    <div class="text-xs font-mono mb-1"><strong>IP Address:</strong> <?= htmlspecialchars($customer['ip_address'] ?: 'DHCP Automatic') ?></div>
                    <div class="text-xs font-mono"><strong>PPPoE Account:</strong> <?= htmlspecialchars($customer['pppoe_username'] ?: '—') ?></div>
                </div>
            </div>
        </div>

        <!-- Line Items Table -->
        <table class="table table-invoice mb-4">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Service Description</th>
                    <th>Billing Period</th>
                    <th class="text-end">Rate (BDT)</th>
                    <th class="text-end">Total (BDT)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payment): ?>
                    <tr>
                        <td class="font-mono text-muted">01</td>
                        <td>
                            <div class="fw-bold text-dark">Optical Broadband Internet Subscription</div>
                            <div class="text-muted text-xs">Payment Method: <?= htmlspecialchars($payment['payment_method']) ?> <?= !empty($payment['transaction_id']) ? '(Trx: ' . htmlspecialchars($payment['transaction_id']) . ')' : '' ?></div>
                        </td>
                        <td class="font-mono text-xs"><?= htmlspecialchars($payment['billing_month'] ?: date('Y-m', strtotime($payment['payment_date']))) ?></td>
                        <td class="text-end font-mono"><?= number_format($payment['amount'], 2) ?></td>
                        <td class="text-end font-mono fw-bold text-dark"><?= number_format($payment['amount'], 2) ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td class="font-mono text-muted">01</td>
                        <td>
                            <div class="fw-bold text-dark">Optical Broadband Internet - <?= htmlspecialchars($bill['package_name'] ?: 'Monthly Plan') ?></div>
                            <div class="text-muted text-xs">Bandwidth: <?= $bill['speed_mbps'] ?> Mbps Duplex Fiber Connection</div>
                        </td>
                        <td class="font-mono text-xs"><?= htmlspecialchars($bill['billing_month']) ?></td>
                        <td class="text-end font-mono"><?= number_format($bill['package_amount'], 2) ?></td>
                        <td class="text-end font-mono fw-bold text-dark"><?= number_format($bill['package_amount'], 2) ?></td>
                    </tr>
                    <?php if ($bill['extra_charge'] > 0): ?>
                        <tr>
                            <td class="font-mono text-muted">02</td>
                            <td>
                                <div class="fw-bold text-dark">Additional Services / Hardware EMI</div>
                                <div class="text-muted text-xs">Static IP or Optical Device charges</div>
                            </td>
                            <td class="font-mono text-xs"><?= htmlspecialchars($bill['billing_month']) ?></td>
                            <td class="text-end font-mono"><?= number_format($bill['extra_charge'], 2) ?></td>
                            <td class="text-end font-mono fw-bold text-dark"><?= number_format($bill['extra_charge'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($bill['previous_due'] > 0): ?>
                        <tr>
                            <td class="font-mono text-muted"><?= $bill['extra_charge'] > 0 ? '03' : '02' ?></td>
                            <td>
                                <div class="fw-bold text-danger">Previous Outstanding Dues (Carried Forward)</div>
                                <div class="text-muted text-xs">Unpaid balance from prior billing cycles</div>
                            </td>
                            <td class="font-mono text-xs">—</td>
                            <td class="text-end font-mono text-danger"><?= number_format($bill['previous_due'], 2) ?></td>
                            <td class="text-end font-mono fw-bold text-danger"><?= number_format($bill['previous_due'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals & Summary -->
        <div class="row align-items-end mb-4">
            <div class="col-7">
                <div class="p-3 bg-light rounded text-xs">
                    <strong>Payment Instructions:</strong><br>
                    You can pay your bill via bKash / Nagad Merchant, Bank Transfer, or to our field billing agents.<br>
                    Keep this receipt for your records. For any fiber connectivity queries, call hotline: <strong><?= htmlspecialchars($app_settings['phone']) ?></strong>.
                </div>
            </div>
            <div class="col-5">
                <div class="border-top border-bottom py-2">
                    <?php if ($payment): ?>
                        <div class="d-flex justify-content-between mb-1 text-xs">
                            <span class="text-muted">Total Paid Amount:</span>
                            <span class="font-mono fw-bold fs-5 text-success">BDT <?= number_format($payment['amount'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-xs text-muted">
                            <span>Payment Method:</span>
                            <span class="font-mono"><?= htmlspecialchars($payment['payment_method']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-xs text-muted">
                            <span>Collected By:</span>
                            <span><?= htmlspecialchars($payment['collected_by'] ?: 'Admin') ?></span>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between mb-1 text-xs">
                            <span class="text-muted">Subtotal:</span>
                            <span class="font-mono">BDT <?= number_format($bill['package_amount'] + $bill['extra_charge'], 2) ?></span>
                        </div>
                        <?php if ($bill['previous_due'] > 0): ?>
                            <div class="d-flex justify-content-between mb-1 text-xs text-danger">
                                <span>Previous Dues:</span>
                                <span class="font-mono">BDT <?= number_format($bill['previous_due'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between py-1 border-top border-dark text-dark fw-bold">
                            <span>Total Payable:</span>
                            <span class="font-mono fs-5">BDT <?= number_format($bill['total_payable'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-xs text-success">
                            <span>Amount Paid:</span>
                            <span class="font-mono">BDT <?= number_format($bill['paid_amount'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-xs fw-bold <?= $bill['due_amount'] > 0 ? 'text-danger' : 'text-muted' ?>">
                            <span>Net Balance Due:</span>
                            <span class="font-mono fs-6">BDT <?= number_format($bill['due_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer Signatures -->
        <div class="row pt-4 mt-5 text-center text-xs text-muted font-mono" style="border-top: 1px dashed #cbd5e1;">
            <div class="col-6">
                <div style="border-top: 1px solid #94a3b8; width: 180px; margin: 0 auto; padding-top: 4px;">
                    Customer Signature
                </div>
            </div>
            <div class="col-6">
                <div style="border-top: 1px solid #94a3b8; width: 180px; margin: 0 auto; padding-top: 4px;">
                    Authorized Operator (NOC)
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>

