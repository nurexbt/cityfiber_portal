<?php
// collection_sheet.php - Door-to-Door Field Collection Sheet for Line Agents
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('collection_sheet', 'view');
$user_data = get_current_user_data();

$zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
$status_filter = $_GET['status'] ?? 'Active';

$sql = "
    SELECT c.*, z.name AS zone_name, p.name AS package_name, p.speed_mbps 
    FROM customers c
    LEFT JOIN zones z ON c.zone_id = z.id
    LEFT JOIN packages p ON c.package_id = p.id
    WHERE 1=1
";
$params = [];

if ($zone_id > 0) {
    $sql .= " AND c.zone_id = ?";
    $params[] = $zone_id;
}

if ($status_filter === 'Due') {
    $sql .= " AND (c.status = 'Due' OR c.total_due > 0)";
} elseif ($status_filter === 'Active') {
    $sql .= " AND c.status = 'Active'";
}

$sql .= " ORDER BY c.zone_id ASC, c.address ASC, c.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subscribers = $stmt->fetchAll();

$zones = $pdo->query("SELECT * FROM zones ORDER BY name ASC")->fetchAll();
$total_pending_amount = array_sum(array_column($subscribers, 'total_due'));
$total_monthly_amount = array_sum(array_column($subscribers, 'monthly_bill'));

$selected_zone_name = 'All Coverage Zones';
if ($zone_id > 0) {
    foreach ($zones as $z) {
        if ($z['id'] == $zone_id) {
            $selected_zone_name = $z['name'];
            break;
        }
    }
}

$page_title = 'Field Collection Sheet';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Print-Only Header (Appears only on physical printout) -->
<div class="d-none d-print-block mb-3 border-bottom pb-2">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($app_settings['company_name']) ?></h4>
            <div class="small text-muted"><?= htmlspecialchars($app_settings['company_tagline']) ?> | Phone: <?= htmlspecialchars($app_settings['phone']) ?></div>
            <div class="fw-bold mt-1 text-uppercase font-mono" style="font-size: 13px;">Door-to-Door Field Collection Sheet</div>
        </div>
        <div class="text-end font-mono" style="font-size: 11px;">
            <div><strong>Date:</strong> <?= date('d M Y') ?></div>
            <div><strong>Zone:</strong> <?= htmlspecialchars($selected_zone_name) ?></div>
            <div><strong>Total Due:</strong> <?= format_money($total_pending_amount) ?> (<?= count($subscribers) ?> clients)</div>
            <div><strong>Agent Name:</strong> ________________________</div>
        </div>
    </div>
</div>

<!-- Screen Header -->
<div class="d-print-none d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">Field Collection Sheet</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                Zone: <?= htmlspecialchars($selected_zone_name) ?>
            </span>
            <span class="badge badge-subtle font-mono text-xs">
                <?= date('F Y') ?>
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Door-to-door line agent collection ledger sequenced by POP zones, fiber distribution boxes, and street addresses.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-cyan font-mono text-xs shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print Sheet
        </button>
        <a href="export.php?type=customers" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </a>
        <a href="payment_receive.php" class="btn btn-sm btn-outline-emerald font-mono text-xs">
            <i class="fa-solid fa-receipt me-1"></i> Payment Desk
        </a>
    </div>
</div>

<!-- KPI Cards (No Print) -->
<div class="d-print-none row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-rose h-100">
            <div class="kpi-title">Total Due to Collect</div>
            <div class="kpi-value text-rose-400"><?= format_money($total_pending_amount) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Current outstanding balance</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Monthly Rate Target</div>
            <div class="kpi-value text-cyan-400"><?= format_money($total_monthly_amount) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Scheduled monthly bill sum</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">Subscribers Listed</div>
            <div class="kpi-value text-purple-400"><?= count($subscribers) ?> Clients</div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Clients on this route sheet</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Active Coverage POP</div>
            <div class="kpi-value text-emerald-400 fs-6 text-truncate" title="<?= htmlspecialchars($selected_zone_name) ?>">
                <?= htmlspecialchars($selected_zone_name) ?>
            </div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;"><?= $zone_id > 0 ? 'Single Zone View' : 'All Network POPs' ?></div>
        </div>
    </div>
</div>

<!-- Filters Card (No Print) -->
<div class="d-print-none card card-noc mb-4">
    <div class="card-body p-3">
        <form method="GET" action="collection_sheet.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <label class="form-label-noc mb-1" style="font-size: 11px;">Filter by Distribution Zone / POP</label>
                <select name="zone_id" class="form-select form-select-noc form-select-sm font-mono" onchange="this.form.submit()">
                    <option value="">All Distribution Zones (Network Wide)</option>
                    <?php foreach ($zones as $z): ?>
                        <option value="<?= $z['id'] ?>" <?= $zone_id == $z['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($z['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label-noc mb-1" style="font-size: 11px;">Customer Balance Status</label>
                <select name="status" class="form-select form-select-noc form-select-sm font-mono" onchange="this.form.submit()">
                    <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>All Active Subscribers</option>
                    <option value="Due" <?= $status_filter === 'Due' ? 'selected' : '' ?>>Due Balance Only</option>
                    <option value="All" <?= $status_filter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label-noc mb-1" style="font-size: 11px;">Instant Table Filter</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control form-control-noc font-mono" placeholder="Filter by name, phone, address, fiber box..." data-noc-search="fieldSheetTable">
                    <?php if ($zone_id > 0 || $status_filter !== 'Due'): ?>
                        <a href="collection_sheet.php" class="btn btn-outline-secondary font-mono" title="Reset Filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Main Collection Sheet Table Card -->
<div class="card card-noc">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
            <i class="fa-solid fa-clipboard-list text-cyan-400 me-2"></i>Field Collection Table (<?= count($subscribers) ?> Records)
        </span>
        <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs d-print-none">
            Sorted by Zone &amp; Address
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-noc align-middle mb-0" id="fieldSheetTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Subscriber ID</th>
                        <th>Subscriber Name &amp; Contact</th>
                        <th>POP Zone &amp; Fiber TJ Box</th>
                        <th>Package Plan</th>
                        <th class="text-end">Monthly</th>
                        <th class="text-end">Total Due</th>
                        <th class="text-center" style="width: 130px;">Cash Collected</th>
                        <th class="text-center" style="width: 100px;">Signature</th>
                        <th class="text-center d-print-none" style="width: 90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscribers)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-slate-500 font-mono">
                                No subscribers found matching current filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $sl = 1; foreach ($subscribers as $s): ?>
                            <tr>
                                <td class="font-mono text-slate-500 text-xs"><?= $sl++ ?></td>
                                <td>
                                    <a href="customer_view.php?id=<?= $s['id'] ?>" class="font-mono text-cyan-400 fw-bold text-decoration-none">
                                        <?= htmlspecialchars($s['customer_code']) ?>
                                    </a>
                                    <div class="text-slate-500 font-mono" style="font-size: 10px;"><?= htmlspecialchars($s['connection_type']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-slate-200"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="font-mono text-slate-300" style="font-size: 11px;">
                                        <i class="fa-solid fa-phone me-1 text-slate-500"></i><?= htmlspecialchars($s['phone']) ?>
                                    </div>
                                    <div class="text-slate-500 text-xs text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($s['address']) ?>">
                                        <?= htmlspecialchars($s['address']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-subtle font-mono" style="font-size: 11px;">
                                        <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($s['zone_name'] ?: 'Main Node') ?>
                                    </span>
                                    <?php if (!empty($s['fiber_core_box'])): ?>
                                        <div class="font-mono text-slate-500 mt-1" style="font-size: 10px;">
                                            <i class="fa-solid fa-network-wired me-1"></i><?= htmlspecialchars($s['fiber_core_box']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate-200 text-xs"><?= htmlspecialchars($s['package_name'] ?: 'Custom Plan') ?></div>
                                    <?php if (!empty($s['speed_mbps'])): ?>
                                        <span class="font-mono text-cyan-400" style="font-size: 11px;"><?= $s['speed_mbps'] ?> Mbps</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-mono text-slate-300 text-xs">
                                    <?= format_money($s['monthly_bill']) ?>
                                </td>
                                <td class="text-end font-mono fw-bold fs-6 <?= $s['total_due'] > 0 ? 'text-rose-400' : 'text-slate-500' ?>">
                                    <?= format_money($s['total_due']) ?>
                                </td>
                                <!-- Field agent handwritten columns (for print/field tracking) -->
                                <td class="text-center font-mono text-slate-400 text-xs">
                                    <span class="d-print-none text-slate-500 font-mono">[ __________ ]</span>
                                    <span class="d-none d-print-inline">________________</span>
                                </td>
                                <td class="text-center font-mono text-slate-400 text-xs">
                                    <span class="d-print-none text-slate-500 font-mono">[ ______ ]</span>
                                    <span class="d-none d-print-inline">____________</span>
                                </td>
                                <!-- Portal Quick Actions (Hidden in Print) -->
                                <td class="text-center d-print-none">
                                    <div class="btn-group btn-group-sm">
                                        <a href="payment_receive.php?customer_id=<?= $s['id'] ?>" class="btn btn-outline-emerald border-slate-800 p-1 px-2" title="Collect Payment Now">
                                            <i class="fa-solid fa-receipt"></i>
                                        </a>
                                        <a href="customer_view.php?id=<?= $s['id'] ?>" class="btn btn-outline-cyan border-slate-800 p-1 px-2" title="View Customer Profile">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold bg-slate-900">
                        <td colspan="5" class="text-end font-mono text-uppercase text-xs text-slate-300">Total Route Target:</td>
                        <td class="text-end font-mono text-slate-200"><?= format_money($total_monthly_amount) ?></td>
                        <td class="text-end font-mono text-rose-400 fs-6"><?= format_money($total_pending_amount) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Print instructions card (No print) -->
<div class="d-print-none mt-3 p-3 rounded bg-slate-900 border border-slate-800 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2 text-xs text-slate-400">
        <i class="fa-solid fa-circle-info text-cyan-400"></i>
        <span><strong>Tip for Field Supervisors:</strong> Filter by a specific distribution zone before clicking <strong>Print Sheet</strong> to hand out clean, per-zone collection routes to line agents.</span>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary font-mono text-xs">
        <i class="fa-solid fa-print me-1"></i> Print Route Sheet
    </button>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
