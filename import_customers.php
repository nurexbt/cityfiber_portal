<?php
// import_customers.php - Bulk Customer CSV Import Utility (Aligned with Add Customer Form)
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('import_customers', 'view');

// Helper: Normalize CSV header keys
function normalize_csv_header($str) {
    $str = preg_replace('/^\xEF\xBB\xBF/', '', (string)$str); // Strip UTF-8 BOM
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '_', $str);
    return trim($str, '_');
}

// Helper: Parse flexible date strings (YYYY-MM-DD, DD/MM/YYYY, etc.) into YYYY-MM-DD
function parse_import_date($val, $fallback) {
    $val = trim((string)$val);
    if ($val === '') {
        return $fallback;
    }
    $ts = strtotime(str_replace('/', '-', $val));
    return ($ts !== false) ? date('Y-m-d', $ts) : $fallback;
}

// Download Sample CSV Template (MUST execute before any HTML output)
if (isset($_GET['action']) && $_GET['action'] === 'sample_csv') {
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Grab first zone and packages from DB for realistic sample values
    $sample_zone = 'Mirpur-10';
    $sample_pkg1 = 'Home Basic 10M';
    $sample_pkg2 = 'Turbo Fiber 25M';
    try {
        $z_name = $pdo->query("SELECT name FROM zones ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($z_name) $sample_zone = $z_name;

        $pkgs = $pdo->query("SELECT name, price FROM packages WHERE status = 'Active' ORDER BY price ASC LIMIT 2")->fetchAll();
        if (!empty($pkgs[0])) $sample_pkg1 = $pkgs[0]['name'];
        if (!empty($pkgs[1])) $sample_pkg2 = $pkgs[1]['name'];
    } catch (Exception $e) {}

    $today = date('Y-m-d');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_customers_template.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Write UTF-8 BOM for clean Excel compatibility
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Name',
        'Phone',
        'Email',
        'NID',
        'Address',
        'Zone',
        'Connection_Type',
        'Status',
        'Package',
        'Monthly_Bill',
        'Opening_Due',
        'Join_Date',
        'Billing_Start_Date',
        'IP_Address',
        'PPPoE_Username',
        'PPPoE_Password',
        'ONU_MAC',
        'Fiber_Core_Box',
        'Notes'
    ]);

    fputcsv($out, [
        'Rahim Ahmed',
        '+880 1711-998877',
        'rahim@gmail.com',
        '1990269123456',
        'House 5, Road 2, Mirpur 10',
        $sample_zone,
        'Home',
        'Active',
        $sample_pkg1,
        '800.00',
        '0.00',
        $today,
        $today,
        '103.166.230.45',
        'rahim_m10',
        'rahim@123',
        '48:8D:36:11:22:33',
        'TJ Box 01, Core 2 (Blue)',
        'TP-Link Archer C6, Rx -19.5 dBm'
    ]);

    fputcsv($out, [
        'Karim Enterprise',
        '+880 1819-554433',
        'info@karim.com',
        '1985269987654',
        'Plot 18, Sector 3, Uttara',
        $sample_zone,
        'Corporate',
        'Due',
        $sample_pkg2,
        '1500.00',
        '1500.00',
        $today,
        $today,
        '103.166.230.46',
        'karim_corp',
        'karim#998',
        'E0:67:B3:99:88:77',
        'TJ Box 04, Core 1 (Orange)',
        'Corporate SLA link + Opening installation charge'
    ]);

    fclose($out);
    exit;
}

$imported_count = 0;
$error_count = 0;
$import_logs = [];

// Handle CSV Upload & Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    require_permission('import_customers', 'add');
    $file = $_FILES['csv_file']['tmp_name'] ?? '';

    if (empty($file) || !is_uploaded_file($file)) {
        set_flash_message('danger', 'Please choose a valid CSV file to upload.');
    } else {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header === false || empty($header)) {
            set_flash_message('danger', 'Uploaded CSV file appears empty or unreadable.');
        } else {
            // Form default fallbacks (same fields as Add Customer form)
            $default_zone_id = !empty($_POST['default_zone_id']) ? (int)$_POST['default_zone_id'] : null;
            $default_package_id = !empty($_POST['default_package_id']) ? (int)$_POST['default_package_id'] : null;
            $default_connection_type = in_array($_POST['default_connection_type'] ?? '', ['Home', 'Corporate', 'Reseller']) ? $_POST['default_connection_type'] : 'Home';
            $default_status = in_array($_POST['default_status'] ?? '', ['Active', 'Due', 'Suspended', 'Inactive']) ? $_POST['default_status'] : 'Active';
            $default_monthly_bill = (float)($_POST['default_monthly_bill'] ?? 0);
            $default_join_date = parse_import_date($_POST['default_join_date'] ?? '', date('Y-m-d'));
            $default_billing_start_date = parse_import_date($_POST['default_billing_start_date'] ?? '', date('Y-m-d'));

            // Cache Zones & Packages for fast matching
            $zones_by_id = [];
            $zones_by_name = [];
            foreach ($pdo->query("SELECT * FROM zones") as $z) {
                $zones_by_id[(int)$z['id']] = $z['name'];
                $zones_by_name[strtolower(trim($z['name']))] = (int)$z['id'];
            }

            $packages_by_id = [];
            $packages_by_name = [];
            $packages_by_speed = [];
            foreach ($pdo->query("SELECT * FROM packages") as $p) {
                $packages_by_id[(int)$p['id']] = $p;
                $packages_by_name[strtolower(trim($p['name']))] = $p;
                $packages_by_speed[(int)$p['speed_mbps']] = $p;
            }

            // Build header -> column index map
            $aliases = [
                'customer_code'      => ['customer_code', 'client_id', 'code'],
                'name'               => ['name', 'full_name', 'customer_name', 'subscriber_name'],
                'phone'              => ['phone', 'mobile', 'mobile_number', 'contact', 'phone_number'],
                'email'              => ['email', 'email_address'],
                'nid'                => ['nid', 'national_id', 'passport', 'nid_passport'],
                'address'            => ['address', 'connection_address', 'installation_address'],
                'zone'               => ['zone', 'pop_zone', 'zone_name', 'zone_id'],
                'connection_type'    => ['connection_type', 'conn_type', 'type'],
                'status'             => ['status', 'subscriber_status'],
                'package'            => ['package', 'internet_package', 'package_name', 'package_id', 'plan'],
                'monthly_bill'       => ['monthly_bill', 'bill', 'monthly_fee', 'price', 'rate'],
                'opening_due'        => ['opening_due', 'due', 'total_due', 'opening_balance', 'previous_due'],
                'join_date'          => ['join_date', 'joining_date'],
                'billing_start_date' => ['billing_start_date', 'billing_cycle_start_date', 'start_date'],
                'ip_address'         => ['ip_address', 'assigned_ip_address', 'ip'],
                'pppoe_username'     => ['pppoe_username', 'pppoe_user', 'pppoe'],
                'pppoe_password'     => ['pppoe_password', 'pppoe_pass'],
                'onu_mac'            => ['onu_mac', 'onu_mac_address', 'mac_address', 'mac'],
                'fiber_core_box'     => ['fiber_core_box', 'fiber_box', 'fiber_tj_box', 'tj_box', 'core_box'],
                'notes'              => ['notes', 'special_remarks', 'remarks', 'comment', 'comments'],
            ];

            $col_map = [];
            foreach ($header as $idx => $h_raw) {
                $h_norm = normalize_csv_header($h_raw);
                foreach ($aliases as $canonical => $accepted) {
                    if (in_array($h_norm, $accepted, true) && !isset($col_map[$canonical])) {
                        $col_map[$canonical] = $idx;
                    }
                }
            }

            // Fallback positional order (matches the 19-column Add Customer template)
            $use_positional = !isset($col_map['name']) || !isset($col_map['phone']);
            $positional_keys = [
                'name', 'phone', 'email', 'nid', 'address', 'zone', 'connection_type', 'status',
                'package', 'monthly_bill', 'opening_due', 'join_date', 'billing_start_date',
                'ip_address', 'pppoe_username', 'pppoe_password', 'onu_mac', 'fiber_core_box', 'notes'
            ];

            $get_val = function ($row, $key) use ($col_map, $use_positional, $positional_keys) {
                if (!$use_positional) {
                    return isset($col_map[$key]) ? trim((string)($row[$col_map[$key]] ?? '')) : '';
                }
                $pos = array_search($key, $positional_keys, true);
                return ($pos !== false) ? trim((string)($row[$pos] ?? '')) : '';
            };

            $ins = $pdo->prepare("
                INSERT INTO customers (
                    customer_code, name, phone, email, nid, address, zone_id, package_id, 
                    monthly_bill, connection_type, ip_address, pppoe_username, pppoe_password, 
                    onu_mac, fiber_core_box, join_date, billing_start_date, status, total_due, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $inv_stmt = $pdo->prepare("
                INSERT INTO bills (bill_number, customer_id, billing_month, bill_date, due_date, package_amount, total_payable, due_amount, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', 'Opening Balance / Setup Charges')
            ");

            $zone_ins = $pdo->prepare("INSERT INTO zones (name, description) VALUES (?, 'Imported via CSV')");
            $chk_code = $pdo->prepare("SELECT 1 FROM customers WHERE customer_code = ? LIMIT 1");
            $last_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM customers")->fetchColumn();

            $row_num = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $row_num++;
                // Skip completely blank lines
                if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue;
                }

                $name = $get_val($row, 'name');
                $phone = $get_val($row, 'phone');
                $email = $get_val($row, 'email');
                $nid = $get_val($row, 'nid');
                $address = $get_val($row, 'address');

                if ($name === '' || $phone === '') {
                    $error_count++;
                    $import_logs[] = ['status' => 'error', 'msg' => "Row {$row_num}: Skipped — Name and Mobile Number are required."];
                    continue;
                }
                if ($address === '') {
                    $error_count++;
                    $import_logs[] = ['status' => 'error', 'msg' => "Row {$row_num} ({$name}): Skipped — Connection Address is required."];
                    continue;
                }

                // Resolve POP Zone
                $csv_zone = $get_val($row, 'zone');
                $zone_id = $default_zone_id;
                if ($csv_zone !== '') {
                    if (ctype_digit($csv_zone) && isset($zones_by_id[(int)$csv_zone])) {
                        $zone_id = (int)$csv_zone;
                    } else {
                        $z_key = strtolower($csv_zone);
                        if (isset($zones_by_name[$z_key])) {
                            $zone_id = $zones_by_name[$z_key];
                        } else {
                            // Auto-create new POP zone from CSV
                            try {
                                $zone_ins->execute([$csv_zone]);
                                $new_zid = (int)$pdo->lastInsertId();
                                $zones_by_name[$z_key] = $new_zid;
                                $zones_by_id[$new_zid] = $csv_zone;
                                $zone_id = $new_zid;
                            } catch (Exception $ze) {}
                        }
                    }
                }

                // Resolve Internet Package
                $csv_pkg = $get_val($row, 'package');
                $package_id = $default_package_id;
                $matched_pkg_price = 0.0;
                if ($csv_pkg !== '') {
                    if (ctype_digit($csv_pkg) && isset($packages_by_id[(int)$csv_pkg])) {
                        $package_id = (int)$csv_pkg;
                        $matched_pkg_price = (float)$packages_by_id[$package_id]['price'];
                    } else {
                        $p_key = strtolower($csv_pkg);
                        if (isset($packages_by_name[$p_key])) {
                            $package_id = (int)$packages_by_name[$p_key]['id'];
                            $matched_pkg_price = (float)$packages_by_name[$p_key]['price'];
                        } elseif (preg_match('/(\d+)\s*m/i', $csv_pkg, $m) && isset($packages_by_speed[(int)$m[1]])) {
                            $spd = (int)$m[1];
                            $package_id = (int)$packages_by_speed[$spd]['id'];
                            $matched_pkg_price = (float)$packages_by_speed[$spd]['price'];
                        }
                    }
                } elseif ($default_package_id && isset($packages_by_id[$default_package_id])) {
                    $matched_pkg_price = (float)$packages_by_id[$default_package_id]['price'];
                }

                // Connection Type normalization
                $csv_conn = strtolower($get_val($row, 'connection_type'));
                $connection_type = $default_connection_type;
                if ($csv_conn !== '') {
                    if (strpos($csv_conn, 'corp') !== false) $connection_type = 'Corporate';
                    elseif (strpos($csv_conn, 'resell') !== false) $connection_type = 'Reseller';
                    elseif (strpos($csv_conn, 'home') !== false) $connection_type = 'Home';
                }

                // Status normalization
                $csv_status = strtolower($get_val($row, 'status'));
                $status = $default_status;
                if ($csv_status === 'active') $status = 'Active';
                elseif ($csv_status === 'due') $status = 'Due';
                elseif ($csv_status === 'suspended' || $csv_status === 'suspend') $status = 'Suspended';
                elseif ($csv_status === 'inactive') $status = 'Inactive';

                // Billing amounts
                $raw_bill = str_replace([',', 'BDT', 'bdt', ' '], '', $get_val($row, 'monthly_bill'));
                $monthly_bill = ($raw_bill !== '' && is_numeric($raw_bill)) ? (float)$raw_bill : 0.0;
                if ($monthly_bill <= 0) {
                    $monthly_bill = $matched_pkg_price > 0 ? $matched_pkg_price : $default_monthly_bill;
                }

                if ($monthly_bill <= 0) {
                    $error_count++;
                    $import_logs[] = ['status' => 'error', 'msg' => "Row {$row_num} ({$name}): Skipped — Monthly Bill must be greater than 0."];
                    continue;
                }

                $raw_due = str_replace([',', 'BDT', 'bdt', ' '], '', $get_val($row, 'opening_due'));
                $opening_due = ($raw_due !== '' && is_numeric($raw_due)) ? (float)$raw_due : 0.00;

                // Dates
                $join_date = parse_import_date($get_val($row, 'join_date'), $default_join_date);
                $billing_start_date = parse_import_date($get_val($row, 'billing_start_date'), $default_billing_start_date);

                // Network Routing & Notes
                $ip_address = $get_val($row, 'ip_address');
                $pppoe_username = $get_val($row, 'pppoe_username');
                $pppoe_password = $get_val($row, 'pppoe_password');
                $onu_mac = $get_val($row, 'onu_mac');
                $fiber_core_box = $get_val($row, 'fiber_core_box');
                $notes = $get_val($row, 'notes');

                // Customer Code
                $csv_code = $get_val($row, 'customer_code');
                if ($csv_code !== '') {
                    $customer_code = $csv_code;
                } else {
                    do {
                        $last_id++;
                        $customer_code = 'CUST-' . str_pad($last_id + 1000, 4, '0', STR_PAD_LEFT);
                        $chk_code->execute([$customer_code]);
                    } while ($chk_code->fetchColumn());
                }

                try {
                    $ins->execute([
                        $customer_code, $name, $phone, $email, $nid, $address, $zone_id, $package_id,
                        $monthly_bill, $connection_type, $ip_address, $pppoe_username, $pppoe_password,
                        $onu_mac, $fiber_core_box, $join_date, $billing_start_date, $status, $opening_due, $notes
                    ]);

                    $new_cust_id = (int)$pdo->lastInsertId();
                    $last_id = max($last_id, $new_cust_id);

                    // Same as Add Customer Form: create initial invoice if Opening Due > 0
                    if ($opening_due > 0) {
                        $inv_no = 'INV-INIT-' . date('Ym') . '-' . $new_cust_id;
                        $inv_stmt->execute([
                            $inv_no, $new_cust_id, date('Y-m'), $join_date, $join_date,
                            $opening_due, $opening_due, $opening_due
                        ]);
                    }

                    $imported_count++;
                    $import_logs[] = ['status' => 'success', 'msg' => "Provisioned {$name} ({$customer_code}) — " . format_money($monthly_bill)];
                } catch (Exception $e) {
                    $error_count++;
                    $import_logs[] = ['status' => 'error', 'msg' => "Row {$row_num} ({$name}): Failed — " . $e->getMessage()];
                }
            }
            fclose($handle);

            set_flash_message(
                $imported_count > 0 ? 'success' : 'warning',
                "CSV Import Complete: Successfully imported {$imported_count} customers! ({$error_count} skipped/failed)"
            );
        }
    }
}

$zones = $pdo->query("SELECT * FROM zones ORDER BY name ASC")->fetchAll();
$packages = $pdo->query("SELECT * FROM packages WHERE status = 'Active' ORDER BY price ASC")->fetchAll();

$page_title = 'Import Customers';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">Bulk Customer CSV Import</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                Data Migration
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Upload subscriber spreadsheets aligned 1-to-1 with every field of the Add Customer provisioning form.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="import_customers.php?action=sample_csv" class="btn btn-sm btn-outline-secondary font-mono text-xs">
            <i class="fa-solid fa-file-excel me-1 text-emerald-400"></i> Download Sample CSV
        </a>
        <a href="customers.php" class="btn btn-sm btn-cyan font-mono text-xs">
            <i class="fa-solid fa-users me-1"></i> View Customers
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Upload Form -->
    <div class="col-12 col-lg-7">
        <div class="card card-noc h-100">
            <div class="card-header">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-upload text-cyan-400 me-2"></i>Upload CSV &amp; Default Provisioning Settings
                </span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="import_customers.php" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label-noc">Select CSV File <span class="text-rose-400">*</span></label>
                        <input type="file" name="csv_file" class="form-control form-control-noc" accept=".csv" required>
                        <div class="text-slate-400 font-mono mt-1" style="font-size:10px;">
                            Format: Comma separated (.csv) &bull; UTF-8 supported &bull; Download sample template above for exact headers
                        </div>
                    </div>

                    <h6 class="text-xs text-uppercase tracking-wider text-cyan-400 fw-bold mb-2 border-bottom border-slate-800 pb-1">
                        <i class="fa-solid fa-sliders me-1"></i> Default Fallbacks (Applied if blank in CSV)
                    </h6>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-noc">Default POP Zone</label>
                            <select name="default_zone_id" class="form-select form-select-noc">
                                <option value="">None / From CSV</option>
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">Connection Type</label>
                            <select name="default_connection_type" class="form-select form-select-noc">
                                <option value="Home">Home Broadband</option>
                                <option value="Corporate">Corporate / Leased</option>
                                <option value="Reseller">Reseller / Sub-ISP</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-noc">Subscriber Status</label>
                            <select name="default_status" class="form-select form-select-noc">
                                <option value="Active">Active</option>
                                <option value="Due">Due</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-noc">Default Internet Package</label>
                            <select name="default_package_id" class="form-select form-select-noc" onchange="syncImportPackagePrice(this)">
                                <option value="">Custom / From CSV</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>" data-price="<?= $pkg['price'] ?>">
                                        <?= htmlspecialchars($pkg['name']) ?> (<?= $pkg['speed_mbps'] ?>M) - BDT <?= number_format($pkg['price'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">Fallback Monthly Bill (BDT)</label>
                            <input type="number" step="0.01" name="default_monthly_bill" id="importDefaultMonthlyBill" class="form-control form-control-noc font-mono" value="800.00" placeholder="800.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="form-label-noc">Default Join Date</label>
                            <input type="date" name="default_join_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-noc">Default Billing Start Date</label>
                            <input type="date" name="default_billing_start_date" class="form-control form-control-noc font-mono" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="p-3 mb-4 rounded bg-slate-900 border border-slate-800 text-xs">
                        <div class="fw-bold text-white mb-2">
                            <i class="fa-solid fa-table-columns text-cyan-400 me-1"></i> CSV Columns (Matches Add Customer Form):
                        </div>
                        <div class="mb-2">
                            <span class="text-cyan-400 font-mono fw-semibold d-block" style="font-size:11px;">Identity &amp; Location:</span>
                            <code class="text-slate-300">Name*, Phone*, Email, NID, Address*, Zone, Connection_Type, Status</code>
                        </div>
                        <div class="mb-2">
                            <span class="text-cyan-400 font-mono fw-semibold d-block" style="font-size:11px;">Billing &amp; Package:</span>
                            <code class="text-slate-300">Package, Monthly_Bill*, Opening_Due, Join_Date, Billing_Start_Date</code>
                        </div>
                        <div>
                            <span class="text-cyan-400 font-mono fw-semibold d-block" style="font-size:11px;">Network Routing (PPPoE / IP / ONU):</span>
                            <code class="text-slate-300">IP_Address, PPPoE_Username, PPPoE_Password, ONU_MAC, Fiber_Core_Box, Notes</code>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-cyan w-100 font-mono fw-bold py-2">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload &amp; Import Subscribers
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Results & Guidance -->
    <div class="col-12 col-lg-5">
        <div class="card card-noc h-100">
            <div class="card-header">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-list-check text-emerald-400 me-2"></i>Import Logs &amp; Field Guide
                </span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($import_logs)): ?>
                    <div class="text-center py-4 text-slate-400 font-mono text-xs">
                        <i class="fa-solid fa-file-import fa-3x mb-3 text-slate-500 d-block"></i>
                        No import run in this session.<br>Download the sample CSV template and upload your file on the left.
                    </div>
                    <div class="p-3 rounded bg-slate-900 border border-slate-800 text-xs text-slate-300 mt-3">
                        <div class="fw-bold text-white mb-2"><i class="fa-solid fa-circle-info text-cyan-400 me-1"></i> Smart Import Notes:</div>
                        <ul class="mb-0 ps-3 d-flex flex-column gap-1" style="font-size: 11px;">
                            <li><strong>Required columns:</strong> <code>Name</code>, <code>Phone</code>, <code>Address</code>, and <code>Monthly_Bill</code> (or matched Package price).</li>
                            <li><strong>POP Zone:</strong> Matches existing zone names or auto-creates new POP zones if not found. Uses form default when blank.</li>
                            <li><strong>Package:</strong> Matches package name (e.g. <em>Turbo Fiber 25M</em>) or speed (e.g. <em>25M</em>).</li>
                            <li><strong>Opening Due:</strong> Any amount &gt; 0 automatically generates an opening invoice (<code>INV-INIT-...</code>) just like the Add Customer form.</li>
                            <li><strong>Dates:</strong> Use <code>YYYY-MM-DD</code> format (defaults to the form dates if left empty).</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info py-2 px-3 text-xs font-mono mb-3">
                        Imported: <strong><?= $imported_count ?></strong> | Skipped/Failed: <strong><?= $error_count ?></strong>
                    </div>
                    <div class="list-group list-group-flush border border-slate-800 rounded overflow-auto" style="max-height: 440px;">
                        <?php foreach ($import_logs as $log): ?>
                            <div class="list-group-item bg-transparent border-slate-800 p-2 text-xs font-mono <?= $log['status'] === 'success' ? 'text-emerald-400' : 'text-rose-400' ?>">
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

<script>
function syncImportPackagePrice(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('importDefaultMonthlyBill').value = parseFloat(price).toFixed(2);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
