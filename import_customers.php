<?php
// import_customers.php - Bulk Customer CSV Import Utility
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('import_customers', 'view');

$page_title = 'Import Customers';
require_once __DIR__ . '/includes/header.php';

// Download Sample CSV Template
if (isset($_GET['action']) && $_GET['action'] === 'sample_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sample_customers_template.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Phone', 'Email', 'Address', 'Monthly_Bill', 'Opening_Due', 'IP_Address', 'PPPoE_Username', 'ONU_MAC', 'Fiber_Box']);
    fputcsv($out, ['Rahim Ahmed', '+880 1711-998877', 'rahim@gmail.com', 'House 5, Road 2, Mirpur 10', '800.00', '0.00', '103.166.230.45', 'rahim_m10', '48:8D:36:11:22:33', 'TJ Box 01, Port 2']);
    fputcsv($out, ['Karim Enterprise', '+880 1819-554433', 'info@karim.com', 'Plot 18, Sector 3, Uttara', '1200.00', '1200.00', '103.166.230.46', 'karim_corp', 'E0:67:B3:99:88:77', 'TJ Box 04, Port 1']);
    fclose($out);
    exit;
}

$imported_count = 0;
$error_count = 0;
$import_logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (empty($file) || !is_uploaded_file($file)) {
        set_flash_message('danger', 'Please choose a valid CSV file to upload.');
    } else {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // Skip header row

        $default_zone_id = !empty($_POST['default_zone_id']) ? (int)$_POST['default_zone_id'] : 1;
        $default_package_id = !empty($_POST['default_package_id']) ? (int)$_POST['default_package_id'] : 1;

        $ins = $pdo->prepare("
            INSERT INTO customers (
                customer_code, name, phone, email, address, zone_id, package_id, 
                monthly_bill, total_due, ip_address, pppoe_username, onu_mac, 
                fiber_core_box, join_date, billing_start_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, CURRENT_DATE, 'Active')
        ");

        $last_id = (int)$pdo->query("SELECT id FROM customers ORDER BY id DESC LIMIT 1")->fetchColumn();

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0]) || empty($row[1])) continue;

            $name = trim($row[0]);
            $phone = trim($row[1]);
            $email = trim($row[2] ?? '');
            $address = trim($row[3] ?? 'Dhaka, Bangladesh');
            $monthly_bill = (float)($row[4] ?? 800.00);
            $opening_due = (float)($row[5] ?? 0.00);
            $ip = trim($row[6] ?? '');
            $pppoe = trim($row[7] ?? '');
            $mac = trim($row[8] ?? '');
            $box = trim($row[9] ?? '');

            $last_id++;
            $customer_code = 'CUST-' . str_pad($last_id + 1000, 4, '0', STR_PAD_LEFT);

            try {
                $ins->execute([
                    $customer_code, $name, $phone, $email, $address, $default_zone_id, $default_package_id,
                    $monthly_bill, $opening_due, $ip, $pppoe, $mac, $box
                ]);
                $imported_count++;
                $import_logs[] = ['status' => 'success', 'msg' => "Provisioned {$name} ({$customer_code})"];
            } catch (Exception $e) {
                $error_count++;
                $import_logs[] = ['status' => 'error', 'msg' => "Failed {$name}: " . $e->getMessage()];
            }
        }
        fclose($handle);

        set_flash_message('success', "CSV Import Complete: Successfully imported {$imported_count} customers! ({$error_count} errors)");
    }
}

$zones = $pdo->query("SELECT * FROM zones ORDER BY name ASC")->fetchAll();
$packages = $pdo->query("SELECT * FROM packages ORDER BY price ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-dark mb-0 font-sans">Bulk Customer CSV Import</h4>
            <span class="badge bg-cyan-50 text-cyan-700 border border-cyan-200 font-mono text-xs">
                Data Migration
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Quickly onboard existing subscribers by uploading a spreadsheet of names, phone numbers, addresses, and rates.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="import_customers.php?action=sample_csv" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-excel me-1"></i> Download Sample CSV
        </a>
        <a href="customers.php" class="btn btn-sm btn-cyan font-mono text-xs">
            <i class="fa-solid fa-users me-1"></i> View Customers
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Upload Form -->
    <div class="col-12 col-lg-6">
        <div class="card card-noc h-100">
            <div class="card-header">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-700">
                    <i class="fa-solid fa-upload text-cyan-400 me-2"></i>Upload CSV File
                </span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="import_customers.php" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label-noc">Select CSV File <span class="text-rose-400">*</span></label>
                        <input type="file" name="csv_file" class="form-control form-control-noc" accept=".csv" required>
                        <div class="text-slate-400 font-mono mt-1" style="font-size:10px;">Format: Comma separated (.csv)</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-noc">Assign Default Zone</label>
                            <select name="default_zone_id" class="form-select form-select-noc">
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-noc">Assign Default Package</label>
                            <select name="default_package_id" class="form-select form-select-noc">
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?> (<?= $pkg['speed_mbps'] ?>M)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 mb-4 rounded bg-light border text-xs">
                        <strong>Expected Columns in CSV:</strong><br>
                        <code>Name, Phone, Email, Address, Monthly_Bill, Opening_Due, IP_Address, PPPoE_Username, ONU_MAC, Fiber_Box</code>
                    </div>

                    <button type="submit" class="btn btn-cyan w-100 font-mono fw-bold py-2">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload &amp; Import Subscribers
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Results & Guidance -->
    <div class="col-12 col-lg-6">
        <div class="card card-noc h-100">
            <div class="card-header">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-700">
                    <i class="fa-solid fa-list-check text-emerald-400 me-2"></i>Import Logs &amp; Status
                </span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($import_logs)): ?>
                    <div class="text-center py-5 text-slate-400 font-mono text-xs">
                        <i class="fa-solid fa-file-import fa-3x mb-3 text-slate-300 d-block"></i>
                        No import run in this session. Download the sample CSV template and upload your file on the left.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info py-2 px-3 text-xs font-mono mb-3">
                        Imported: <strong><?= $imported_count ?></strong> | Failed: <strong><?= $error_count ?></strong>
                    </div>
                    <div class="list-group list-group-flush border rounded overflow-auto" style="max-height: 280px;">
                        <?php foreach ($import_logs as $log): ?>
                            <div class="list-group-item p-2 text-xs font-mono <?= $log['status'] === 'success' ? 'text-success' : 'text-danger' ?>">
                                <i class="fa-solid <?= $log['status'] === 'success' ? 'fa-check' : 'fa-xmark' ?> me-1"></i>
                                <?= htmlspecialchars($log['msg']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

