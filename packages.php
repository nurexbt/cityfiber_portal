<?php
// packages.php - ISP Internet Packages & Distribution Zones Management
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('packages', 'view');

// Handle Add / Edit Package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_package') {
    $pkg_id = !empty($_POST['pkg_id']) ? (int)$_POST['pkg_id'] : null;
    require_permission('packages', $pkg_id ? 'edit' : 'add');
    $name = trim($_POST['name'] ?? '');
    $speed_mbps = (int)($_POST['speed_mbps'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $type = trim($_POST['type'] ?? 'Home');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');

    if ($pkg_id) {
        $stmt = $pdo->prepare("UPDATE packages SET name = ?, speed_mbps = ?, price = ?, type = ?, description = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $speed_mbps, $price, $type, $description, $status, $pkg_id]);
        set_flash_message('success', "Package {$name} updated.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO packages (name, speed_mbps, price, type, description, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $speed_mbps, $price, $type, $description, $status]);
        set_flash_message('success', "New package {$name} created.");
    }
    header('Location: packages.php');
    exit;
}

// Handle Add / Edit Zone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_zone') {
    $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
    require_permission('packages', $zone_id ? 'edit' : 'add');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($zone_id) {
        $stmt = $pdo->prepare("UPDATE zones SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $zone_id]);
        set_flash_message('success', "Zone {$name} updated.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO zones (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        set_flash_message('success', "New zone {$name} created.");
    }
    header('Location: packages.php');
    exit;
}

// Delete Package
if (isset($_GET['delete_pkg_id'])) {
    require_permission('packages', 'delete');
    $del_pkg_id = (int)$_GET['delete_pkg_id'];
    $sub_count = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE package_id = ?");
    $sub_count->execute([$del_pkg_id]);
    if ($sub_count->fetchColumn() > 0) {
        set_flash_message('danger', 'Cannot delete package because it is currently assigned to active subscribers.');
    } else {
        $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$del_pkg_id]);
        set_flash_message('success', 'Package removed successfully.');
    }
    header('Location: packages.php');
    exit;
}

// Delete Zone
if (isset($_GET['delete_zone_id'])) {
    require_permission('packages', 'delete');
    $del_zone_id = (int)$_GET['delete_zone_id'];
    $sub_count = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE zone_id = ?");
    $sub_count->execute([$del_zone_id]);
    if ($sub_count->fetchColumn() > 0) {
        set_flash_message('danger', 'Cannot delete zone because subscribers are assigned to it.');
    } else {
        $pdo->prepare("DELETE FROM zones WHERE id = ?")->execute([$del_zone_id]);
        set_flash_message('success', 'Distribution zone removed successfully.');
    }
    header('Location: packages.php');
    exit;
}

// Queries
$packages = $pdo->query("
    SELECT p.*, (SELECT COUNT(*) FROM customers c WHERE c.package_id = p.id) AS subscriber_count 
    FROM packages p 
    ORDER BY p.price ASC
")->fetchAll();

$zones = $pdo->query("
    SELECT z.*, (SELECT COUNT(*) FROM customers c WHERE c.zone_id = z.id) AS subscriber_count 
    FROM zones z 
    ORDER BY z.name ASC
")->fetchAll();

$page_title = 'Packages & Zones';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">Packages &amp; POP Zones</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                Network Configuration
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Configure bandwidth speed profiles, subscriber monthly tariff rates, and distribution POP coverage areas.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (has_permission('packages', 'add')): ?>
            <button class="btn btn-sm btn-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="clearPackageForm()">
                <i class="fa-solid fa-plus me-1"></i> + New Package
            </button>
            <button class="btn btn-sm btn-outline-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#zoneModal" onclick="clearZoneForm()">
                <i class="fa-solid fa-map-location-dot me-1"></i> + New Zone
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <!-- Left: Packages Table -->
    <div class="col-12 col-lg-7">
        <div class="card card-noc h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-network-wired text-cyan-400 me-2"></i>Internet Packages / Plans (<?= count($packages) ?>)
                </span>
                <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs">Tariffs</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Package Name</th>
                                <th>Bandwidth</th>
                                <th>Type</th>
                                <th class="text-end">Monthly Price</th>
                                <th class="text-center">Users</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $pkg): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-slate-200"><?= htmlspecialchars($pkg['name']) ?></div>
                                        <?php if (!empty($pkg['description'])): ?>
                                            <div class="text-slate-500 text-xs text-truncate" style="max-width: 200px;"><?= htmlspecialchars($pkg['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-investment-partner font-mono text-xs">
                                            <i class="fa-solid fa-gauge-high me-1"></i><?= $pkg['speed_mbps'] ?> Mbps
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-slate-400 text-xs"><?= htmlspecialchars($pkg['type']) ?></span>
                                    </td>
                                    <td class="text-end font-mono fw-bold text-emerald-400 fs-6">
                                        <?= format_money($pkg['price']) ?>
                                    </td>
                                    <td class="text-center font-mono text-slate-300">
                                        <?= $pkg['subscriber_count'] ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($pkg['status'] === 'Active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-suspended">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?php if (has_permission('packages', 'edit')): ?>
                                                <button class="btn btn-outline-secondary border-slate-800 p-1 px-2 text-slate-300" onclick='editPackage(<?= json_encode($pkg) ?>)' title="Edit Package">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (has_permission('packages', 'delete')): ?>
                                                <a href="packages.php?delete_pkg_id=<?= $pkg['id'] ?>" class="btn btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Delete internet package <?= htmlspecialchars($pkg['name']) ?>?" data-item-name="<?= htmlspecialchars($pkg['name']) ?> (<?= $pkg['speed_mbps'] ?> Mbps)" data-item-code="Price: <?= format_money($pkg['price']) ?>" title="Delete Package">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Distribution Zones Table -->
    <div class="col-12 col-lg-5">
        <div class="card card-noc h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-map-pin text-purple-400 me-2"></i>Coverage Areas / POP Zones (<?= count($zones) ?>)
                </span>
                <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs">Rings</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Zone / POP Name</th>
                                <th>Description</th>
                                <th class="text-center">Subscribers</th>
                                <th class="text-center">Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($zones as $z): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-slate-200"><?= htmlspecialchars($z['name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-slate-400 text-xs text-truncate" style="max-width: 180px;"><?= htmlspecialchars($z['description']) ?></div>
                                    </td>
                                    <td class="text-center font-mono fw-bold text-cyan-400">
                                        <?= $z['subscriber_count'] ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?php if (has_permission('packages', 'edit')): ?>
                                                <button class="btn btn-outline-secondary border-slate-800 p-1 px-2 text-slate-300" onclick='editZone(<?= json_encode($z) ?>)' title="Edit Zone">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (has_permission('packages', 'delete')): ?>
                                                <a href="packages.php?delete_zone_id=<?= $z['id'] ?>" class="btn btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Delete distribution coverage zone <?= htmlspecialchars($z['name']) ?>?" data-item-name="<?= htmlspecialchars($z['name']) ?>" data-item-code="Subscribers: <?= $z['subscriber_count'] ?>" title="Delete Zone">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Package -->
<div class="modal fade" id="packageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans" id="pkgModalTitle">Configure Package</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="packages.php" data-confirm-add="Confirm saving package configuration?" data-confirm-title="Confirm Package Configuration">
                <input type="hidden" name="action" value="save_package">
                <input type="hidden" name="pkg_id" id="pkg_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-noc">Package Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="name" id="pkg_name" class="form-control form-control-noc" required placeholder="e.g. Turbo Optical 30M">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Bandwidth (Mbps) <span class="text-rose-400">*</span></label>
                            <input type="number" name="speed_mbps" id="pkg_speed" class="form-control form-control-noc font-mono" required placeholder="30">
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Monthly Price (BDT) <span class="text-rose-400">*</span></label>
                            <input type="number" step="0.01" name="price" id="pkg_price" class="form-control form-control-noc font-mono" required placeholder="1000">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Connection Type</label>
                            <select name="type" id="pkg_type" class="form-select form-select-noc">
                                <option value="Home">Home Broadband</option>
                                <option value="Corporate">Corporate / Leased Line</option>
                                <option value="Reseller">Reseller / Bandwidth</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Status</label>
                            <select name="status" id="pkg_status" class="form-select form-select-noc">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-noc">Description / Features</label>
                        <input type="text" name="description" id="pkg_description" class="form-control form-control-noc" placeholder="e.g. Low ping, 4K streaming, BDIX speed">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Save Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Zone -->
<div class="modal fade" id="zoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-white font-sans" id="zoneModalTitle">Configure Zone</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="packages.php" data-confirm-add="Confirm saving distribution coverage zone?" data-confirm-title="Confirm Coverage Zone">
                <input type="hidden" name="action" value="save_zone">
                <input type="hidden" name="zone_id" id="zone_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-noc">Zone / POP Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="name" id="zone_name" class="form-control form-control-noc" required placeholder="e.g. Zone 5 - Dhanmondi West">
                    </div>
                    <div class="mb-0">
                        <label class="form-label-noc">Coverage Description</label>
                        <textarea name="description" id="zone_description" class="form-control form-control-noc" rows="3" placeholder="Roads, sectors, or primary optical ring details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 text-slate-300" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono"><i class="fa-solid fa-save me-1"></i> Save Zone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearPackageForm() {
    document.getElementById('pkgModalTitle').textContent = 'Add New Package';
    document.getElementById('pkg_id').value = '';
    document.getElementById('pkg_name').value = '';
    document.getElementById('pkg_speed').value = '';
    document.getElementById('pkg_price').value = '';
    document.getElementById('pkg_type').value = 'Home';
    document.getElementById('pkg_status').value = 'Active';
    document.getElementById('pkg_description').value = '';
}

function editPackage(pkg) {
    document.getElementById('pkgModalTitle').textContent = 'Edit Package: ' + pkg.name;
    document.getElementById('pkg_id').value = pkg.id;
    document.getElementById('pkg_name').value = pkg.name;
    document.getElementById('pkg_speed').value = pkg.speed_mbps;
    document.getElementById('pkg_price').value = pkg.price;
    document.getElementById('pkg_type').value = pkg.type;
    document.getElementById('pkg_status').value = pkg.status;
    document.getElementById('pkg_description').value = pkg.description || '';
    new bootstrap.Modal(document.getElementById('packageModal')).show();
}

function clearZoneForm() {
    document.getElementById('zoneModalTitle').textContent = 'Add New Zone';
    document.getElementById('zone_id').value = '';
    document.getElementById('zone_name').value = '';
    document.getElementById('zone_description').value = '';
}

function editZone(z) {
    document.getElementById('zoneModalTitle').textContent = 'Edit Zone: ' + z.name;
    document.getElementById('zone_id').value = z.id;
    document.getElementById('zone_name').value = z.name;
    document.getElementById('zone_description').value = z.description || '';
    new bootstrap.Modal(document.getElementById('zoneModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

