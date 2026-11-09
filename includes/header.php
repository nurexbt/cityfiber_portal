<?php
// includes/header.php
require_once __DIR__ . '/../config/db.php';
global $pdo, $app_settings;
require_once __DIR__ . '/../config/auth.php';
require_login();

$user_data = get_current_user_data();

// Quick badge stats for navbar / sidebar
try {
    $due_bills_count = ($pdo instanceof PDO) ? $pdo->query("SELECT COUNT(*) FROM bills WHERE status IN ('Unpaid', 'Overdue')")->fetchColumn() : 0;
    $active_customers_count = ($pdo instanceof PDO) ? $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Active'")->fetchColumn() : 0;
    $total_expenses_month = ($pdo instanceof PDO) ? $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')")->fetchColumn() : 0;
    $total_collection_month = ($pdo instanceof PDO) ? $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')")->fetchColumn() : 0;
} catch (Exception $e) {
    $due_bills_count = 0;
    $active_customers_count = 0;
    $total_expenses_month = 0;
    $total_collection_month = 0;
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' | ' : '' ?><?= htmlspecialchars($app_settings['company_name']) ?> Portal</title>
    <!-- Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js 4.4.1 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Theme -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>
        // Check saved theme preference before rendering to avoid flash
        (function() {
            const savedTheme = localStorage.getItem('isp_theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('light');
            } else {
                document.documentElement.classList.add('light');
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>
<body class="bg-noc">
<div class="app-wrapper">

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="app-main" id="appMain">
    <!-- Top Header Bar -->
    <header class="app-header">
        <div class="d-flex align-items-center gap-2 gap-sm-3">
            <button class="btn btn-sm btn-outline-secondary border-slate-700 p-1 px-2" id="sidebarToggleBtn" title="Toggle Sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="d-inline-block d-lg-none fw-bold text-dark font-sans text-xs tracking-wide"><?= htmlspecialchars(strtoupper($app_settings['company_name'] ?? 'CITY FIBER')) ?></span>
        </div>

        <!-- Right Quick Actions & Profile -->
        <div class="d-flex align-items-center gap-2">
            <!-- Theme Toggle Button -->
            <button class="btn btn-sm btn-outline-secondary font-mono text-xs d-flex align-items-center gap-1.5 px-2.5 py-1" id="themeToggleBtn" onclick="toggleTheme()" title="Toggle Light / Dark Mode">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
                <span id="themeText" class="d-none d-sm-inline">Dark</span>
            </button>

            <!-- Quick Action Shortcuts -->
            <?php if (has_permission('payments', 'add')): ?>
                <a href="payment_receive.php" class="btn btn-sm btn-cyan font-mono text-xs d-none d-sm-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="fa-solid fa-receipt"></i> + Receive Bill
                </a>
            <?php endif; ?>
            <?php if (has_permission('customers', 'add')): ?>
                <a href="customers.php?action=new" class="btn btn-sm btn-outline-cyan font-mono text-xs d-none d-lg-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-user-plus"></i> + Add Customer
                </a>
            <?php endif; ?>
            <?php if (has_permission('expenses', 'add')): ?>
                <a href="expenses.php?action=new" class="btn btn-sm btn-outline-danger font-mono text-xs d-none d-xl-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-file-invoice-dollar"></i> + Expense
                </a>
            <?php endif; ?>

            <!-- User Menu Dropdown -->
            <div class="dropdown ms-2">
                <button class="btn btn-sm btn-outline-secondary text-slate-700 dropdown-toggle d-flex align-items-center gap-2 px-2.5 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle bg-cyan-950 text-cyan-400 border border-cyan-800 d-flex align-items-center justify-content-center font-mono fw-bold" style="width:24px; height:24px; line-height:22px; font-size:11px; text-align:center;">
                        <?= strtoupper(substr($user_data['username'], 0, 1)) ?>
                    </div>
                    <span class="font-mono text-xs d-none d-md-inline fw-semibold"><?= htmlspecialchars($user_data['username']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end modal-content-noc shadow-lg text-xs" style="min-width: 190px;">
                    <li class="px-3 py-2 border-bottom border-slate-800">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user_data['full_name']) ?></div>
                        <div class="text-slate-500 font-mono text-xs"><?= htmlspecialchars($user_data['role']) ?></div>
                    </li>
                    <?php if (has_permission('users', 'view')): ?>
                        <li><a class="dropdown-item py-2" href="users.php"><i class="fa-solid fa-user-shield text-cyan-400 me-2"></i>User Management</a></li>
                    <?php endif; ?>
                    <?php if (has_permission('settings', 'view')): ?>
                        <li><a class="dropdown-item py-2" href="settings.php"><i class="fa-solid fa-sliders text-cyan-400 me-2"></i>ISP Settings</a></li>
                    <?php endif; ?>
                    <?php if (has_permission('reports', 'view')): ?>
                        <li><a class="dropdown-item py-2" href="reports.php"><i class="fa-solid fa-chart-line text-emerald-400 me-2"></i>Financial Reports</a></li>
                    <?php endif; ?>
                    <?php if (has_permission('network_tools', 'view')): ?>
                        <li><a class="dropdown-item py-2" href="network_tools.php"><i class="fa-solid fa-screwdriver-wrench text-purple-400 me-2"></i>Field Troubleshooting Guide</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item text-danger py-2" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Main Body Container -->
    <main class="flex-grow-1 p-3 p-lg-4">
        <?php $global_flash = get_flash_message(); ?>
