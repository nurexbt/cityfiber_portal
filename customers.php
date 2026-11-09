<?php
// customers.php - 2. Customer Add & Management
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('customers', 'view');

// Handle Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    require_permission('customers', 'add');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nid = trim($_POST['nid'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
    $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
    $monthly_bill = (float)($_POST['monthly_bill'] ?? 0);
    $connection_type = trim($_POST['connection_type'] ?? 'Home');
    $ip_address = trim($_POST['ip_address'] ?? '');
    $pppoe_username = trim($_POST['pppoe_username'] ?? '');
    $pppoe_password = trim($_POST['pppoe_password'] ?? '');
    $onu_mac = trim($_POST['onu_mac'] ?? '');
    $fiber_core_box = trim($_POST['fiber_core_box'] ?? '');
    $join_date = trim($_POST['join_date'] ?? date('Y-m-d'));
    $billing_start_date = trim($_POST['billing_start_date'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'Active');
    $opening_due = (float)($_POST['opening_due'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Auto-generate customer_code if not supplied
    $last_id = $pdo->query("SELECT id FROM customers ORDER BY id DESC LIMIT 1")->fetchColumn();
    $customer_code = 'CUST-' . str_pad(($last_id ? $last_id + 1001 : 1001), 4, '0', STR_PAD_LEFT);

    if (empty($name) || empty($phone) || empty($address) || $monthly_bill <= 0) {
        set_flash_message('danger', 'Please enter Name, Phone, Address, and agreed Monthly Bill.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO customers (
                customer_code, name, phone, email, nid, address, zone_id, package_id, 
                monthly_bill, connection_type, ip_address, pppoe_username, pppoe_password, 
                onu_mac, fiber_core_box, join_date, billing_start_date, status, total_due, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $customer_code, $name, $phone, $email, $nid, $address, $zone_id, $package_id,
            $monthly_bill, $connection_type, $ip_address, $pppoe_username, $pppoe_password,
            $onu_mac, $fiber_core_box, $join_date, $billing_start_date, $status, $opening_due, $notes
        ]);

        $new_cust_id = $pdo->lastInsertId();

        // If opening due was specified, create an initial invoice record
        if ($opening_due > 0) {
            $inv_no = 'INV-INIT-' . date('Ym') . '-' . $new_cust_id;
            $inv = $pdo->prepare("
                INSERT INTO bills (bill_number, customer_id, billing_month, bill_date, due_date, package_amount, total_payable, due_amount, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', 'Opening Balance / Setup Charges')
            ");
            $inv->execute([$inv_no, $new_cust_id, date('Y-m'), $join_date, $join_date, $opening_due, $opening_due, $opening_due]);
        }

        set_flash_message('success', "Customer {$name} ({$customer_code}) created successfully!");
        header('Location: customers.php');
        exit;
    }
}

// Handle Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_customer') {
    require_permission('customers', 'edit');
    $id = (int)$_POST['customer_id'];
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nid = trim($_POST['nid'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
    $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
    $monthly_bill = (float)($_POST['monthly_bill'] ?? 0);
    $connection_type = trim($_POST['connection_type'] ?? 'Home');
    $ip_address = trim($_POST['ip_address'] ?? '');
    $pppoe_username = trim($_POST['pppoe_username'] ?? '');
    $pppoe_password = trim($_POST['pppoe_password'] ?? '');
    $onu_mac = trim($_POST['onu_mac'] ?? '');
    $fiber_core_box = trim($_POST['fiber_core_box'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $pdo->prepare("
        UPDATE customers SET 
            name = ?, phone = ?, email = ?, nid = ?, address = ?, zone_id = ?, package_id = ?,
            monthly_bill = ?, connection_type = ?, ip_address = ?, pppoe_username = ?, pppoe_password = ?,
            onu_mac = ?, fiber_core_box = ?, status = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name, $phone, $email, $nid, $address, $zone_id, $package_id,
        $monthly_bill, $connection_type, $ip_address, $pppoe_username, $pppoe_password,
        $onu_mac, $fiber_core_box, $status, $notes, $id
    ]);

    set_flash_message('success', "Customer #{$id} details updated successfully!");
    header('Location: customers.php');
    exit;
}

// Delete Customer
if (isset($_GET['delete_customer_id'])) {
    require_permission('customers', 'delete');
    $del_id = (int)$_GET['delete_customer_id'];
    $stmt = $pdo->prepare("SELECT customer_code, name FROM customers WHERE id = ?");
    $stmt->execute([$del_id]);
    $c_del = $stmt->fetch();
    
    if ($c_del) {
        $pdo->prepare("DELETE FROM payments WHERE customer_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM bills WHERE customer_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$del_id]);
        set_flash_message('success', "Customer {$c_del['name']} ({$c_del['customer_code']}) and records deleted successfully.");
    }
    header('Location: customers.php');
    exit;
}

// Toggle Status (Active / Suspended)
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    require_permission('customers', 'edit');
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT status FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $curr = $stmt->fetchColumn();
    if ($curr) {
        $new_status = ($curr === 'Active') ? 'Suspended' : 'Active';
        $up = $pdo->prepare("UPDATE customers SET status = ? WHERE id = ?");
        $up->execute([$new_status, $id]);
        set_flash_message('success', "Customer status toggled to {$new_status}.");
    }
    header('Location: customers.php');
    exit;
}

// Filters
$filter_zone = $_GET['zone_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_package = $_GET['package_id'] ?? '';

$sql = "
    SELECT c.*, z.name AS zone_name, p.name AS package_name, p.speed_mbps 
    FROM customers c
    LEFT JOIN zones z ON c.zone_id = z.id
    LEFT JOIN packages p ON c.package_id = p.id
    WHERE 1=1
";
$params = [];

if (!empty($filter_zone)) {
    $sql .= " AND c.zone_id = ?";
    $params[] = $filter_zone;
}
if (!empty($filter_status)) {
    $sql .= " AND c.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_package)) {
    $sql .= " AND c.package_id = ?";
    $params[] = $filter_package;
}
$sql .= " ORDER BY c.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Fetch auxiliary lists for dropdowns
$zones = $pdo->query("SELECT * FROM zones ORDER BY name ASC")->fetchAll();
$packages = $pdo->query("SELECT * FROM packages WHERE status = 'Active' ORDER BY price ASC")->fetchAll();

// KPI Summary
$total_subscribers = count($customers);
$total_mrr = $pdo->query("SELECT COALESCE(SUM(monthly_bill), 0) FROM customers WHERE status = 'Active'")->fetchColumn();
$total_active = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Active'")->fetchColumn();
$total_due_subscribers = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Due' OR total_due > 0")->fetchColumn();

$page_title = 'Customers Management';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">2. Customer Management</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                Subscriber Network
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Add new broadband subscribers, assign fiber TJ cores, IP/PPPoE credentials, and monitor live billing status.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="export.php?type=customers" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </a>
        <?php if (has_permission('customers', 'add')): ?>
            <button class="btn btn-sm btn-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fa-solid fa-user-plus me-1"></i> + Add New Customer
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Total Active Lines</div>
            <div class="kpi-value text-cyan-400"><?= $total_active ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Active optical fiber connections</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Monthly Recurring Revenue (MRR)</div>
            <div class="kpi-value text-emerald-400"><?= format_money($total_mrr) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Expected billable package revenue</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-amber h-100">
            <div class="kpi-title">Subscribers in Due</div>
            <div class="kpi-value text-amber-400"><?= $total_due_subscribers ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Pending bill settlement</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">Network POP Zones</div>
            <div class="kpi-value text-purple-400"><?= count($zones) ?> Zones</div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Active distribution rings</div>
        </div>
    </div>
</div>

<!-- Customer List Card -->
<div class="card card-noc">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                <i class="fa-solid fa-users text-cyan-400 me-2"></i>Subscribers Directory
            </span>
            <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs"><?= count($customers) ?> Found</span>
        </div>
        <form method="GET" action="customers.php" class="d-flex align-items-center flex-wrap gap-2">
            <!-- Search Input -->
            <div class="input-group input-group-sm" style="max-width: 200px;">
                <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" class="form-control form-control-noc" placeholder="Search name/IP/phone..." data-noc-search="customersTable">
            </div>

            <!-- Zone Filter -->
            <select name="zone_id" class="form-select form-select-noc form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Zones</option>
                <?php foreach ($zones as $z): ?>
                    <option value="<?= $z['id'] ?>" <?= $filter_zone == $z['id'] ? 'selected' : '' ?>><?= htmlspecialchars($z['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Status Filter -->
            <select name="status" class="form-select form-select-noc form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="Active" <?= $filter_status === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Due" <?= $filter_status === 'Due' ? 'selected' : '' ?>>Due</option>
                <option value="Suspended" <?= $filter_status === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                <option value="Inactive" <?= $filter_status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-noc align-middle" id="customersTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subscriber Name</th>
                        <th>Contact / Address</th>
                        <th>Zone / POP</th>
                        <th>Package</th>
                        <th>IP / PPPoE / ONU</th>
                        <th class="text-end">Monthly Bill</th>
                        <th class="text-end">Due (BDT)</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-slate-500 font-mono">No customer accounts matched.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <a href="customer_view.php?id=<?= $c['id'] ?>" class="font-mono text-cyan-400 fw-bold text-decoration-none">
                                        <?= htmlspecialchars($c['customer_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-slate-200"><?= htmlspecialchars($c['name']) ?></div>
                                    <?php
                                    $conn_type = $c['connection_type'];
                                    $conn_badge = (stripos($conn_type, 'corp') !== false) ? 'badge-conn-corp' : 'badge-conn-home';
                                    $conn_icon = (stripos($conn_type, 'corp') !== false) ? 'fa-building' : 'fa-house-signal';
                                    ?>
                                    <span class="badge <?= $conn_badge ?> font-mono text-xs">
                                        <i class="fa-solid <?= $conn_icon ?> me-1"></i><?= htmlspecialchars($conn_type) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-slate-300 font-mono text-xs"><i class="fa-solid fa-phone me-1 text-slate-500"></i><?= htmlspecialchars($c['phone']) ?></div>
                                    <div class="text-slate-500 text-xs text-truncate" style="max-width: 170px;"><?= htmlspecialchars($c['address']) ?></div>
                                </td>
                                <td>
                                    <span class="text-slate-300 text-xs"><?= htmlspecialchars($c['zone_name'] ?: 'Default POP') ?></span>
                                    <?php if (!empty($c['fiber_core_box'])): ?>
                                        <div class="font-mono text-slate-500" style="font-size:10px;"><i class="fa-solid fa-network-wired me-1"></i><?= htmlspecialchars($c['fiber_core_box']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate-200 text-xs"><?= htmlspecialchars($c['package_name'] ?: 'Custom Plan') ?></div>
                                    <span class="text-cyan-400 font-mono" style="font-size:11px;"><?= $c['speed_mbps'] ? $c['speed_mbps'] . ' Mbps' : '' ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($c['ip_address'])): ?>
                                        <div class="font-mono text-emerald-400" style="font-size:11px;"><?= htmlspecialchars($c['ip_address']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['pppoe_username'])): ?>
                                        <div class="font-mono text-slate-400" style="font-size:11px;"><?= htmlspecialchars($c['pppoe_username']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['onu_mac'])): ?>
                                        <div class="font-mono text-slate-500" style="font-size:10px;">MAC: <?= htmlspecialchars($c['onu_mac']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-mono fw-bold text-slate-200">
                                    <?= format_money($c['monthly_bill']) ?>
                                </td>
                                <td class="text-end font-mono fw-bold <?= $c['total_due'] > 0 ? 'text-rose-400' : 'text-slate-500' ?>">
                                    <?= format_money($c['total_due']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($c['status'] === 'Active'): ?>
                                        <span class="badge badge-active"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                    <?php elseif ($c['status'] === 'Due'): ?>
                                        <span class="badge badge-due"><i class="fa-solid fa-clock me-1"></i> Due</span>
                                    <?php elseif ($c['status'] === 'Suspended'): ?>
                                        <span class="badge badge-suspended"><i class="fa-solid fa-ban me-1"></i> Suspended</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive"><?= htmlspecialchars($c['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="customer_view.php?id=<?= $c['id'] ?>" class="btn btn-outline-cyan border-slate-800 p-1 px-2" title="View Full Profile & Ledger">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if (has_permission('payments', 'add')): ?>
                                            <a href="payment_receive.php?customer_id=<?= $c['id'] ?>" class="btn btn-outline-emerald border-slate-800 p-1 px-2" title="Collect Payment">
                                                <i class="fa-solid fa-receipt"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('customers', 'edit')): ?>
                                            <button type="button" class="btn btn-outline-secondary border-slate-800 p-1 px-2 text-slate-300" onclick='openEditCustomerModal(<?= json_encode($c) ?>)' title="Edit Details">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <a href="customers.php?toggle_status=1&id=<?= $c['id'] ?>" class="btn btn-outline-warning border-slate-800 p-1 px-2" title="Toggle Active/Suspended">
                                                <i class="fa-solid fa-power-off"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('customers', 'delete')): ?>
                                            <a href="customers.php?delete_customer_id=<?= $c['id'] ?>" class="btn btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Permanently delete subscriber <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['customer_code']) ?>) and all billing records?" data-item-name="<?= htmlspecialchars($c['name']) ?>" data-item-code="<?= htmlspecialchars($c['customer_code']) ?>" title="Delete Customer">
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

<!-- Modal: Add Customer -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-user-plus text-cyan-400 me-2"></i>2. Provision New Subscriber
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="customers.php" data-confirm-add="Confirm provisioning and activating this new subscriber?" data-confirm-title="Confirm Customer Provisioning">
                <input type="hidden" name="action" value="add_customer">
                <div class="modal-body p-4">
                    <h6 class="text-xs text-uppercase tracking-wider text-cyan-400 fw-bold mb-3 border-bottom border-slate-800 pb-1">
                        <i class="fa-solid fa-id-card me-1"></i> Subscriber Identity & Location
                    </h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">Full Name <span class="text-rose-400">*</span></label>
                            <input type="text" name="name" class="form-control form-control-noc" required placeholder="e.g. Mahfuzur Rahman">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">Mobile Number <span class="text-rose-400">*</span></label>
                            <input type="text" name="phone" class="form-control form-control-noc font-mono" required placeholder="+880 17XXXXXXXX">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-noc" placeholder="client@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">National ID (NID) / Passport</label>
                            <input type="text" name="nid" class="form-control form-control-noc font-mono" placeholder="NID number">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-noc">Connection Address / Flat / Building <span class="text-rose-400">*</span></label>
                        <input type="text" name="address" class="form-control form-control-noc" required placeholder="House 12, Road 4, Block B...">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-4">
                            <label class="form-label-noc">POP Zone</label>
                            <select name="zone_id" class="form-select form-select-noc">
                                <option value="">Select Zone</option>
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">Connection Type</label>
                            <select name="connection_type" class="form-select form-select-noc">
                                <option value="Home">Home Broadband</option>
                                <option value="Corporate">Corporate / Leased</option>
                                <option value="Reseller">Reseller / Sub-ISP</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">Subscriber Status</label>
                            <select name="status" class="form-select form-select-noc">
                                <option value="Active">Active</option>
                                <option value="Due">Due</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-xs text-uppercase tracking-wider text-cyan-400 fw-bold mb-3 border-bottom border-slate-800 pb-1">
                        <i class="fa-solid fa-receipt me-1"></i> Billing & Package Details
                    </h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">Internet Package</label>
                            <select name="package_id" id="addPackageSelect" class="form-select form-select-noc" onchange="syncPackagePrice(this)">
                                <option value="">Custom Package</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>" data-price="<?= $pkg['price'] ?>">
                                        <?= htmlspecialchars($pkg['name']) ?> (<?= $pkg['speed_mbps'] ?>M) - BDT<?= number_format($pkg['price'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-noc">Monthly Bill (BDT) <span class="text-rose-400">*</span></label>
                            <input type="number" step="0.01" name="monthly_bill" id="addMonthlyBill" class="form-control form-control-noc font-mono" required placeholder="800.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-noc">Opening Due / Setup (BDT)</label>
                            <input type="number" step="0.01" name="opening_due" class="form-control form-control-noc font-mono" value="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="form-label-noc">Join Date</label>
                            <input type="date" name="join_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">Billing Cycle Start Date</label>
                            <input type="date" name="billing_start_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <h6 class="text-xs text-uppercase tracking-wider text-cyan-400 fw-bold mb-3 border-bottom border-slate-800 pb-1">
                        <i class="fa-solid fa-network-wired me-1"></i> Technical Network Routing (PPPoE / IP / ONU)
                    </h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-noc">Assigned IP Address</label>
                            <input type="text" name="ip_address" class="form-control form-control-noc font-mono" placeholder="103.166.230.XX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">PPPoE Username</label>
                            <input type="text" name="pppoe_username" class="form-control form-control-noc font-mono" placeholder="username_isp">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">PPPoE Password</label>
                            <input type="text" name="pppoe_password" class="form-control form-control-noc font-mono" placeholder="secret">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">ONU MAC Address</label>
                            <input type="text" name="onu_mac" class="form-control form-control-noc font-mono" placeholder="AA:BB:CC:DD:EE:FF">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">Fiber Core Box / Port / Color</label>
                            <input type="text" name="fiber_core_box" class="form-control form-control-noc" placeholder="TJ Box 02, Core 4 (Blue)">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label-noc">Special Remarks / Notes</label>
                        <textarea name="notes" class="form-control form-control-noc" rows="2" placeholder="Router model, optical power (dBm), notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Provision Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Customer -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans">
                    <i class="fa-solid fa-pen-to-square text-cyan-400 me-2"></i>Edit Subscriber Profile
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">Full Name <span class="text-rose-400">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control form-control-noc" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">Mobile Phone <span class="text-rose-400">*</span></label>
                            <input type="text" name="phone" id="edit_phone" class="form-control form-control-noc font-mono" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control form-control-noc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">National ID (NID)</label>
                            <input type="text" name="nid" id="edit_nid" class="form-control form-control-noc font-mono">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-noc">Address <span class="text-rose-400">*</span></label>
                        <input type="text" name="address" id="edit_address" class="form-control form-control-noc" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-noc">Zone</label>
                            <select name="zone_id" id="edit_zone_id" class="form-select form-select-noc">
                                <option value="">Select Zone</option>
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">Package</label>
                            <select name="package_id" id="edit_package_id" class="form-select form-select-noc">
                                <option value="">Custom Package</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>" data-price="<?= $pkg['price'] ?>">
                                        <?= htmlspecialchars($pkg['name']) ?> (BDT<?= number_format($pkg['price'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">Monthly Bill (BDT)</label>
                            <input type="number" step="0.01" name="monthly_bill" id="edit_monthly_bill" class="form-control form-control-noc font-mono" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-noc">Connection Type</label>
                            <select name="connection_type" id="edit_connection_type" class="form-select form-select-noc">
                                <option value="Home">Home Broadband</option>
                                <option value="Corporate">Corporate / Leased</option>
                                <option value="Reseller">Reseller / Sub-ISP</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">Status</label>
                            <select name="status" id="edit_status" class="form-select form-select-noc">
                                <option value="Active">Active</option>
                                <option value="Due">Due</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">IP Address</label>
                            <input type="text" name="ip_address" id="edit_ip_address" class="form-control form-control-noc font-mono">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-noc">PPPoE Username</label>
                            <input type="text" name="pppoe_username" id="edit_pppoe_username" class="form-control form-control-noc font-mono">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">PPPoE Password</label>
                            <input type="text" name="pppoe_password" id="edit_pppoe_password" class="form-control form-control-noc font-mono">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">ONU MAC</label>
                            <input type="text" name="onu_mac" id="edit_onu_mac" class="form-control form-control-noc font-mono">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">Fiber Core Box / Port / Color</label>
                            <input type="text" name="fiber_core_box" id="edit_fiber_core_box" class="form-control form-control-noc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">Notes</label>
                            <input type="text" name="notes" id="edit_notes" class="form-control form-control-noc">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-check me-1"></i> Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function syncPackagePrice(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('addMonthlyBill').value = parseFloat(price).toFixed(2);
    }
}

function openEditCustomerModal(data) {
    document.getElementById('edit_customer_id').value = data.id || '';
    document.getElementById('edit_name').value = data.name || '';
    document.getElementById('edit_phone').value = data.phone || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_nid').value = data.nid || '';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('edit_zone_id').value = data.zone_id || '';
    document.getElementById('edit_package_id').value = data.package_id || '';
    document.getElementById('edit_monthly_bill').value = data.monthly_bill || '';
    document.getElementById('edit_connection_type').value = data.connection_type || 'Home';
    document.getElementById('edit_status').value = data.status || 'Active';
    document.getElementById('edit_ip_address').value = data.ip_address || '';
    document.getElementById('edit_pppoe_username').value = data.pppoe_username || '';
    document.getElementById('edit_pppoe_password').value = data.pppoe_password || '';
    document.getElementById('edit_onu_mac').value = data.onu_mac || '';
    document.getElementById('edit_fiber_core_box').value = data.fiber_core_box || '';
    document.getElementById('edit_notes').value = data.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
    modal.show();
}

// Auto open add modal if action=new passed in URL
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'new') {
        const addModal = new bootstrap.Modal(document.getElementById('addCustomerModal'));
        addModal.show();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

