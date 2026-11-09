<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand Logo -->
    <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom border-slate-800" style="height: 58px;">
        <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none">
            <div class="rounded bg-cyan-950 border border-cyan-800 p-1 text-cyan-400 d-flex align-items-center justify-content-center" style="width:30px; height:30px;">
                <i class="fa-solid fa-server font-size-14"></i>
            </div>
            <div class="sidebar-header-text">
                <span class="fw-bold text-dark font-sans text-xs tracking-wide d-block"><?= htmlspecialchars(strtoupper($app_settings['company_name'] ?? 'CITY FIBER')) ?></span>
                <span class="text-slate-500 font-mono" style="font-size: 10px;">OPERATIONS v2.4</span>
            </div>
        </a>
        <!-- Mobile Sidebar Close Button -->
        <button type="button" class="btn btn-sm btn-link text-slate-400 d-lg-none p-1 text-decoration-none" id="sidebarCloseBtn" title="Close Sidebar">
            <i class="fa-solid fa-xmark fs-5"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <?php if (has_permission('dashboard', 'view')): ?>
            <div class="sidebar-category">Operations Center</div>
            <a href="index.php" class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        <?php endif; ?>

        <?php 
        $has_subscribers = has_permission('customers', 'view') || 
                           has_permission('import_customers', 'view') || 
                           has_permission('billing', 'view') || 
                           has_permission('payments', 'view') || 
                           has_permission('collection_sheet', 'view') || 
                           has_permission('sms', 'view');
        if ($has_subscribers): 
        ?>
            <div class="sidebar-category">Billing &amp; Subscribers</div>

            <!-- 2. Customer add / management -->
            <?php if (has_permission('customers', 'view')): ?>
                <a href="customers.php" class="nav-link <?= in_array($current_page, ['customers.php', 'customer_view.php']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i>
                    <span class="nav-text">Customers</span>
                    <?php if (!empty($active_customers_count)): ?>
                        <span class="badge bg-light text-slate-700 border font-mono sidebar-badge ms-auto" style="font-size:10px;"><?= $active_customers_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (has_permission('import_customers', 'view')): ?>
                <a href="import_customers.php" class="nav-link <?= $current_page === 'import_customers.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-import"></i>
                    <span class="nav-text">Import Customers</span>
                </a>
            <?php endif; ?>

            <!-- 3. Customer bill calculation -->
            <?php if (has_permission('billing', 'view')): ?>
                <a href="billing.php" class="nav-link <?= $current_page === 'billing.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span class="nav-text">Bill Calculation</span>
                    <?php if (!empty($due_bills_count)): ?>
                        <span class="badge badge-due font-mono sidebar-badge ms-auto" style="font-size:10px;"><?= $due_bills_count ?> Due</span>
                    <?php endif; ?>
                </a>

                <a href="single_invoice.php" class="nav-link <?= in_array($current_page, ['single_invoice.php', 'manual_invoice.php']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice-dollar text-cyan-400"></i>
                    <span class="nav-text">Single / Manual Invoice</span>
                </a>
            <?php endif; ?>

            <!-- 5. Customer bill payment receive -->
            <?php if (has_permission('payments', 'view')): ?>
                <a href="payment_receive.php" class="nav-link <?= $current_page === 'payment_receive.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-hand-holding-dollar text-emerald-400"></i>
                    <span class="nav-text text-emerald-500 fw-semibold">Payment Receive</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('collection_sheet', 'view')): ?>
                <a href="collection_sheet.php" class="nav-link <?= $current_page === 'collection_sheet.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-list text-cyan-400"></i>
                    <span class="nav-text">Field Collection Sheet</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('sms', 'view')): ?>
                <a href="sms_manager.php" class="nav-link <?= $current_page === 'sms_manager.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-comment-sms text-purple-400"></i>
                    <span class="nav-text">SMS Reminders</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php 
        $has_finance = has_permission('investments', 'view') || 
                       has_permission('expenses', 'view') || 
                       has_permission('employees', 'view') || 
                       has_permission('reports', 'view');
        if ($has_finance): 
        ?>
            <div class="sidebar-category">Finance &amp; Accounts</div>

            <!-- .1 Investment calculation -->
            <?php if (has_permission('investments', 'view')): ?>
                <a href="investments.php" class="nav-link <?= $current_page === 'investments.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-coins text-cyan-400"></i>
                    <span class="nav-text">Investment Calc</span>
                </a>
            <?php endif; ?>

            <!-- 1. Expense calculation -->
            <?php if (has_permission('expenses', 'view')): ?>
                <a href="expenses.php" class="nav-link <?= $current_page === 'expenses.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-receipt text-rose-400"></i>
                    <span class="nav-text">Expense Calc</span>
                </a>
            <?php endif; ?>

            <!-- 4. Employees cost -->
            <?php if (has_permission('employees', 'view')): ?>
                <a href="employees.php" class="nav-link <?= $current_page === 'employees.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-id-badge text-purple-400"></i>
                    <span class="nav-text">Employees Cost</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('reports', 'view')): ?>
                <a href="reports.php" class="nav-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span class="nav-text">P&amp;L Reports</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php 
        $has_tools = has_permission('network_tools', 'view') || 
                     has_permission('packages', 'view') || 
                     has_permission('users', 'view') || 
                     has_permission('settings', 'view');
        if ($has_tools): 
        ?>
            <div class="sidebar-category">Tools &amp; Setup</div>

            <?php if (has_permission('network_tools', 'view')): ?>
                <a href="network_tools.php" class="nav-link <?= $current_page === 'network_tools.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-screwdriver-wrench text-cyan-400"></i>
                    <span class="nav-text">Troubleshooting &amp; Guide</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('packages', 'view')): ?>
                <a href="packages.php" class="nav-link <?= $current_page === 'packages.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-network-wired"></i>
                    <span class="nav-text">Packages &amp; Zones</span>
                </a>
            <?php endif; ?>

            <!-- User Management & Role Permissions -->
            <?php if (has_permission('users', 'view')): ?>
                <a href="users.php" class="nav-link <?= $current_page === 'users.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-shield text-cyan-400"></i>
                    <span class="nav-text">User Management</span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('settings', 'view')): ?>
                <a href="settings.php" class="nav-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-sliders"></i>
                    <span class="nav-text">Settings</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer Quick Info -->
    <div class="p-3 border-top border-slate-800 text-xs font-mono text-slate-500 sidebar-header-text">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span>Radius/SNMP:</span>
            <span class="text-emerald-400 fw-bold">Active</span>
        </div>
        <div class="d-flex align-items-center justify-content-between">
            <span>MySQL DB:</span>
            <span class="text-cyan-400">Connected</span>
        </div>
    </div>
</aside>

<!-- Mobile Offcanvas Backdrop Overlay -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
