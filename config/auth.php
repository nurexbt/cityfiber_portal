<?php
// config/auth.php - Authentication, Session Guards & Role Permissions (RBAC)
require_once __DIR__ . '/db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    // Auto-heal session role and permissions if missing from older sessions
    if (empty($_SESSION['role']) || empty($_SESSION['role_id']) || !isset($_SESSION['permissions'])) {
        global $pdo;
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                $u = $stmt->fetch();
                if ($u) {
                    $_SESSION['role'] = !empty($u['role_name']) ? $u['role_name'] : (!empty($u['role']) ? $u['role'] : 'Admin');
                    $_SESSION['role_id'] = !empty($u['role_id']) ? (int)$u['role_id'] : 1;
                    if (empty($_SESSION['username'])) $_SESSION['username'] = $u['username'];
                    if (empty($_SESSION['full_name'])) $_SESSION['full_name'] = $u['full_name'];
                    load_user_permissions($_SESSION['role_id'], $_SESSION['role']);
                }
            } catch (Exception $e) {}
        }
    }
}

function get_current_user_data() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'Admin',
        'full_name' => $_SESSION['full_name'] ?? 'ISP Administrator',
        'role' => $_SESSION['role'] ?? 'Admin',
        'role_id' => $_SESSION['role_id'] ?? 1
    ];
}

function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Returns all portal modules with meta details
 */
function get_all_modules() {
    return [
        'dashboard' => [
            'name' => 'Dashboard Overview',
            'desc' => 'Operations dashboard, live KPIs, summaries and financial widgets',
            'icon' => 'fa-gauge-high',
            'category' => 'Operations Center'
        ],
        'customers' => [
            'name' => 'Customer Management',
            'desc' => 'Subscriber directory, connections, profiles, and line info',
            'icon' => 'fa-users',
            'category' => 'Billing & Subscribers'
        ],
        'import_customers' => [
            'name' => 'Customer CSV Import',
            'desc' => 'Bulk subscriber list upload from Excel / CSV',
            'icon' => 'fa-file-import',
            'category' => 'Billing & Subscribers'
        ],
        'billing' => [
            'name' => 'Bill Calculation',
            'desc' => 'Monthly billing engine, overdue charges, and custom invoices',
            'icon' => 'fa-file-invoice',
            'category' => 'Billing & Subscribers'
        ],
        'payments' => [
            'name' => 'Payment Receive',
            'desc' => 'Cash/bKash/Nagad/Bank collections and printed money receipts',
            'icon' => 'fa-hand-holding-dollar',
            'category' => 'Billing & Subscribers'
        ],
        'collection_sheet' => [
            'name' => 'Field Collection Sheet',
            'desc' => 'Door-to-door line collector route list and print sheets',
            'icon' => 'fa-clipboard-list',
            'category' => 'Billing & Subscribers'
        ],
        'sms' => [
            'name' => 'SMS Reminders',
            'desc' => 'Automated due alerts, bill reminders, and customer notifications',
            'icon' => 'fa-comment-sms',
            'category' => 'Billing & Subscribers'
        ],
        'investments' => [
            'name' => 'Investment Calculation',
            'desc' => 'Partner equity capital, bank loans, and ROI portfolio (.1)',
            'icon' => 'fa-coins',
            'category' => 'Finance & Accounts'
        ],
        'expenses' => [
            'name' => 'Expense Calculation',
            'desc' => 'Bandwidth transit, fiber maintenance, and office expenditure (1)',
            'icon' => 'fa-receipt',
            'category' => 'Finance & Accounts'
        ],
        'employees' => [
            'name' => 'Employees Cost & Payroll',
            'desc' => 'NOC team, field splicers, salaries, and disbursement vouchers (4)',
            'icon' => 'fa-id-badge',
            'category' => 'Finance & Accounts'
        ],
        'reports' => [
            'name' => 'P&L Reports & Analytics',
            'desc' => 'Annual profit & loss statements and financial performance audits',
            'icon' => 'fa-chart-pie',
            'category' => 'Finance & Accounts'
        ],
        'network_tools' => [
            'name' => 'Field Troubleshooting Guide',
            'desc' => 'Router setup, optical ONU laser standards, and mobile wireless diagnostics',
            'icon' => 'fa-screwdriver-wrench',
            'category' => 'Tools & Setup'
        ],
        'packages' => [
            'name' => 'Packages & POP Zones',
            'desc' => 'Bandwidth speed profiles, tariffs, and distribution POP nodes',
            'icon' => 'fa-network-wired',
            'category' => 'Tools & Setup'
        ],
        'settings' => [
            'name' => 'System Settings',
            'desc' => 'Company profile, branding, currency, and bill policies',
            'icon' => 'fa-sliders',
            'category' => 'Tools & Setup'
        ],
        'users' => [
            'name' => 'User Management & Roles',
            'desc' => 'Portal operators, role permission matrix, and access security',
            'icon' => 'fa-user-shield',
            'category' => 'Tools & Setup'
        ],
    ];
}

/**
 * Loads role permissions from DB into session
 */
function load_user_permissions($role_id = null, $role_name = null) {
    global $pdo;

    $modules = get_all_modules();
    $perms = [];

    // Admin has full permissions across all modules
    if (strcasecmp($role_name ?? '', 'admin') === 0 || ($role_id == 1)) {
        foreach (array_keys($modules) as $m) {
            $perms[$m] = ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1];
        }
        $_SESSION['permissions'] = $perms;
        return $perms;
    }

    // If role_id is not provided, look it up
    if (!$role_id && $role_name && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $stmt->execute([$role_name]);
            $role_id = $stmt->fetchColumn();
        } catch (Exception $e) {}
    }

    if ($role_id && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT module, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$role_id]);
            $rows = $stmt->fetchAll();

            foreach ($rows as $r) {
                $perms[$r['module']] = [
                    'view'   => (int)$r['can_view'],
                    'add'    => (int)$r['can_add'],
                    'edit'   => (int)$r['can_edit'],
                    'delete' => (int)$r['can_delete'],
                ];
            }
        } catch (Exception $e) {}
    }

    // Default missing modules to 0
    foreach (array_keys($modules) as $m) {
        if (!isset($perms[$m])) {
            $perms[$m] = ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0];
        }
    }

    $_SESSION['permissions'] = $perms;
    return $perms;
}

/**
 * Checks if current user has permission for a module and action (view, add, edit, delete)
 */
function has_permission($module, $action = 'view') {
    if (!is_logged_in()) {
        return false;
    }

    $role = $_SESSION['role'] ?? '';
    $username = $_SESSION['username'] ?? '';

    // Admin bypasses all checks with full permissions
    if (strcasecmp($role, 'admin') === 0 || strcasecmp($username, 'admin') === 0 || ($_SESSION['role_id'] ?? null) == 1) {
        return true;
    }

    if (!isset($_SESSION['permissions'])) {
        load_user_permissions($_SESSION['role_id'] ?? null, $role);
    }

    $perms = $_SESSION['permissions'] ?? [];
    return !empty($perms[$module][$action]);
}

/**
 * Enforces permission or halts execution with 403 Forbidden
 */
function require_permission($module, $action = 'view') {
    global $pdo, $app_settings, $user_data;
    require_login();

    if (!has_permission($module, $action)) {
        // If AJAX request or JSON requested
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => "Access Denied: You do not have '{$action}' permission for {$module}."
            ]);
            exit;
        }

        // Render sleek NOC 403 Access Denied screen
        http_response_code(403);
        $page_title = 'Access Denied (403)';
        require_once __DIR__ . '/../includes/header.php';
        ?>
        <div class="row justify-content-center py-5">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card card-noc text-center p-4 shadow-sm border-danger">
                    <div class="mb-3">
                        <div class="rounded-circle bg-rose-500/15 border border-rose-500/30 text-rose-400 d-inline-flex align-items-center justify-content-center" style="width:70px; height:70px;">
                            <i class="fa-solid fa-shield-halved fa-2x"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-white mb-2 font-sans">403 — Access Restricted</h4>
                    <p class="text-slate-400 text-xs mb-3">
                        Your user account has the role <span class="badge badge-dept-support"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>, 
                        which does not have <strong><?= strtoupper(htmlspecialchars($action)) ?></strong> permission for the 
                        <span class="badge badge-header-cyan"><?= htmlspecialchars($module) ?></span> module.
                    </p>
                    <div class="alert alert-warning text-xs font-mono mb-4 text-start bg-amber-500/10 border border-amber-500/30 text-amber-300">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Please contact your ISP Administrator to update your role permissions in <strong>Tools &amp; Setup &gt; User Management</strong>.
                    </div>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary font-mono text-xs">
                            <i class="fa-solid fa-arrow-left me-1"></i> Go Back
                        </a>
                        <a href="index.php" class="btn btn-sm btn-cyan font-mono text-xs">
                            <i class="fa-solid fa-gauge-high me-1"></i> Operations Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
}


