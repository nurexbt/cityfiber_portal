<?php
// index.php - ISP NOC Operations Dashboard
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('dashboard', 'view');

$page_title = 'NOC Operations Center';
require_once __DIR__ . '/includes/header.php';

$cur_month = date('Y-m');
$cur_month_label = date('F Y');

// Financial & Operational Calculations
// 1. Total Investment (.1)
$total_investment = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE status = 'Active'")->fetchColumn();
$this_month_investment = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE DATE_FORMAT(investment_date, '%Y-%m') = '{$cur_month}'")->fetchColumn();

// 2. Revenue / Customer Bill Payment Receive (5)
$month_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '{$cur_month}'")->fetchColumn();
$alltime_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();

// 3. Operating Expenses (1)
$month_expenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '{$cur_month}'")->fetchColumn();

// 4. Employees Cost (4)
$month_salaries_paid = $pdo->query("SELECT COALESCE(SUM(net_paid), 0) FROM salary_payments WHERE salary_month = '{$cur_month}'")->fetchColumn();
$total_monthly_payroll_estimate = $pdo->query("SELECT COALESCE(SUM(basic_salary + allowance), 0) FROM employees WHERE status = 'Active'")->fetchColumn();
$employee_cost_display = $month_salaries_paid > 0 ? $month_salaries_paid : $total_monthly_payroll_estimate;

// 5. Customer Bills & Dues (3)
$total_due_bills = $pdo->query("SELECT COALESCE(SUM(due_amount), 0) FROM bills WHERE status IN ('Unpaid', 'Partial', 'Overdue')")->fetchColumn();
$month_invoiced = $pdo->query("SELECT COALESCE(SUM(total_payable), 0) FROM bills WHERE billing_month = '{$cur_month}'")->fetchColumn();

// 6. Net Operating Cash Flow / Profit
$net_monthly_profit = $month_revenue - ($month_expenses + $employee_cost_display);

// 7. Customer Counts (2)
$cust_total = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$cust_active = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Active'")->fetchColumn();
$cust_due = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Due' OR total_due > 0")->fetchColumn();
$cust_suspended = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Suspended'")->fetchColumn();

// Recent 5 Bill Payments
$recent_payments = $pdo->query("
    SELECT p.*, c.name AS customer_name, c.customer_code, c.phone 
    FROM payments p 
    JOIN customers c ON p.customer_id = c.id 
    ORDER BY p.payment_date DESC, p.id DESC 
    LIMIT 5
")->fetchAll();

// Recent 5 Expenses
$recent_expenses = $pdo->query("
    SELECT * FROM expenses 
    ORDER BY expense_date DESC, id DESC 
    LIMIT 5
")->fetchAll();

// Expense by Category for Doughnut Chart
$expense_categories = $pdo->query("
    SELECT category, COALESCE(SUM(amount), 0) AS total 
    FROM expenses 
    GROUP BY category 
    ORDER BY total DESC
")->fetchAll();

$exp_labels = [];
$exp_data = [];
foreach ($expense_categories as $ec) {
    $exp_labels[] = $ec['category'];
    $exp_data[] = (float)$ec['total'];
}

// 6-Month Income vs Expense Trend
$trend_months = [];
$trend_income = [];
$trend_expense = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} month"));
    $trend_months[] = date('M Y', strtotime("-{$i} month"));
    
    $inc = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '{$m}'")->fetchColumn();
    $exp = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '{$m}'")->fetchColumn();
    $sal = $pdo->query("SELECT COALESCE(SUM(net_paid), 0) FROM salary_payments WHERE salary_month = '{$m}'")->fetchColumn();
    
    $trend_income[] = (float)$inc;
    $trend_expense[] = (float)$exp + (float)$sal;
}
?>

<!-- NOC Status Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">ISP Operations Dashboard</h4>
            <span class="badge badge-header-emerald font-mono text-xs">
                <i class="fa-solid fa-signal me-1"></i> <?= $cur_month_label ?>
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Real-time overview of investments, revenues, expenses, payroll, and network subscribers.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="billing.php" class="btn btn-sm btn-outline-cyan font-mono text-xs">
            <i class="fa-solid fa-calculator me-1"></i> Bill Calculation
        </a>
        <a href="payment_receive.php" class="btn btn-sm btn-cyan font-mono text-xs">
            <i class="fa-solid fa-receipt me-1"></i> Receive Payment
        </a>
    </div>
</div>

<!-- 6 Core KPI Metric Cards (Compact NOC Grid) -->
<div class="row g-3 mb-4">
    <!-- .1 Total Investment -->
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-cyan h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-title">.1 Total Investment</div>
                <i class="fa-solid fa-coins text-cyan-400"></i>
            </div>
            <div class="kpi-value text-cyan-400"><?= format_money($total_investment) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">
                <span class="text-slate-300"><?= $this_month_investment > 0 ? '+' . format_money($this_month_investment) : 'Capital in place' ?></span>
            </div>
        </div>
    </div>

    <!-- 5. Customer Bill Received -->
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-emerald h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-title">5. Bill Received (Nov)</div>
                <i class="fa-solid fa-hand-holding-dollar text-emerald-400"></i>
            </div>
            <div class="kpi-value text-emerald-400"><?= format_money($month_revenue) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">
                All-time: <span class="text-slate-300"><?= format_money($alltime_revenue) ?></span>
            </div>
        </div>
    </div>

    <!-- 1. Expense Calculation -->
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-rose h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-title">1. Total Expenses</div>
                <i class="fa-solid fa-receipt text-rose-400"></i>
            </div>
            <div class="kpi-value text-rose-400"><?= format_money($month_expenses) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">
                Bandwidth & Office rent
            </div>
        </div>
    </div>

    <!-- 4. Employees Cost -->
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-purple h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-title">4. Employee Cost</div>
                <i class="fa-solid fa-id-badge text-purple-400"></i>
            </div>
            <div class="kpi-value text-purple-400"><?= format_money($employee_cost_display) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">
                <?= $month_salaries_paid > 0 ? 'Disbursed' : 'Monthly Payroll' ?>
            </div>
        </div>
    </div>

    <!-- Net Cash Flow / Profit -->
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card <?= $net_monthly_profit >= 0 ? 'kpi-emerald' : 'kpi-rose' ?> h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-title">Net Operating Profit</div>
                <i class="fa-solid fa-chart-line <?= $net_monthly_profit >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>"></i>
            </div>
            <div class="kpi-value <?= $net_monthly_profit >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                <?= format_money($net_monthly_profit) ?>
            </div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">
                Inflow - Operating Costs
            </div>
        </div>
    </div>

    <!-- 3. Outstanding Due -->
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-amber h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-title">3. Outstanding Dues</div>
                <i class="fa-solid fa-clock-rotate-left text-amber-400"></i>
            </div>
            <div class="kpi-value text-amber-400"><?= format_money($total_due_bills) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">
                <?= $cust_due ?> Unpaid Subscriber(s)
            </div>
        </div>
    </div>
</div>

<!-- Secondary Info Strip: Customer Metrics (2. Customer Add) -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-noc p-3">
            <div class="row align-items-center g-3 text-center text-sm-start">
                <div class="col-12 col-sm-3 d-flex align-items-center gap-3">
                    <div class="rounded bg-cyan-500/10 border border-cyan-500/30 p-2 text-cyan-400">
                        <i class="fa-solid fa-users-gear fa-xl"></i>
                    </div>
                    <div>
                        <div class="text-slate-400 text-xs font-mono uppercase">2. Total Customers</div>
                        <div class="fw-bold text-white fs-5 font-mono"><?= $cust_total ?> Clients</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <span class="badge badge-active mb-1"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                    <div class="font-mono text-slate-200 fw-bold"><?= $cust_active ?> Online Optical Users</div>
                </div>
                <div class="col-6 col-sm-3">
                    <span class="badge badge-due mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Payment Due</span>
                    <div class="font-mono text-slate-200 fw-bold"><?= $cust_due ?> Subscribers Pending</div>
                </div>
                <div class="col-12 col-sm-3 text-sm-end">
                    <a href="customers.php?action=new" class="btn btn-sm btn-cyan font-mono text-xs">
                        <i class="fa-solid fa-user-plus me-1"></i> + Add New Customer
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Analytics Charts (Chart.js) -->
<div class="row g-3 mb-4">
    <!-- Trend Chart -->
    <div class="col-12 col-lg-8">
        <div class="card card-noc h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-chart-column text-cyan-400 me-2"></i>Income vs Total Outflow (Last 6 Months)
                </span>
                <span class="text-slate-400 font-mono text-xs">Monthly Financial Health</span>
            </div>
            <div class="card-body">
                <div style="height: 250px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Doughnut Chart -->
    <div class="col-12 col-lg-4">
        <div class="card card-noc h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-chart-pie text-rose-400 me-2"></i>Expense Breakdown
                </span>
                <span class="text-slate-400 font-mono text-xs">By Category</span>
            </div>
            <div class="card-body">
                <div style="height: 250px;">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions Feed: Payments Received vs Expenses -->
<div class="row g-3">
    <!-- Recent Payments Received (5) -->
    <div class="col-12 col-lg-7">
        <div class="card card-noc h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-hand-holding-dollar text-emerald-400 me-2"></i>5. Recent Bill Payments Received
                </span>
                <a href="payment_receive.php" class="text-cyan-400 text-xs font-mono text-decoration-none hover-underline">
                    View All Collections <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Customer</th>
                                <th>Method</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-slate-500 font-mono">No payment records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $rp): ?>
                                    <tr>
                                        <td>
                                            <span class="font-mono text-cyan-400 fw-semibold"><?= htmlspecialchars($rp['receipt_no']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-slate-200"><?= htmlspecialchars($rp['customer_name']) ?></div>
                                            <div class="text-slate-500 font-mono" style="font-size:11px;"><?= htmlspecialchars($rp['customer_code']) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $rpm = strtolower($rp['payment_method']);
                                            $rpm_cls = 'badge-method';
                                            $rpm_ico = 'fa-money-bill-wave';
                                            if (strpos($rpm, 'bkash') !== false) { $rpm_cls = 'badge-method-bkash'; $rpm_ico = 'fa-mobile-screen-button'; }
                                            elseif (strpos($rpm, 'nagad') !== false) { $rpm_cls = 'badge-method-nagad'; $rpm_ico = 'fa-wallet'; }
                                            elseif (strpos($rpm, 'bank') !== false) { $rpm_cls = 'badge-method-bank'; $rpm_ico = 'fa-building-columns'; }
                                            elseif (strpos($rpm, 'cash') !== false) { $rpm_cls = 'badge-method-cash'; $rpm_ico = 'fa-money-bill-1-wave'; }
                                            ?>
                                            <span class="badge <?= $rpm_cls ?> font-mono text-xs">
                                                <i class="fa-solid <?= $rpm_ico ?> me-1"></i><?= htmlspecialchars($rp['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td class="font-mono text-slate-400 text-xs"><?= htmlspecialchars($rp['payment_date']) ?></td>
                                        <td class="text-end font-mono fw-bold text-emerald-400">
                                            +<?= format_money($rp['amount']) ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="invoice.php?receipt_no=<?= urlencode($rp['receipt_no']) ?>" target="_blank" class="btn btn-sm btn-outline-cyan p-1 px-2 font-mono text-xs" title="Print Receipt">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Expenses Logged (1) -->
    <div class="col-12 col-lg-5">
        <div class="card card-noc h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-receipt text-rose-400 me-2"></i>1. Recent Expenses
                </span>
                <a href="expenses.php" class="text-rose-400 text-xs font-mono text-decoration-none hover-underline">
                    View All Expenses <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Voucher</th>
                                <th>Category / Title</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_expenses)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-slate-500 font-mono">No expenses logged.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_expenses as $re): ?>
                                    <tr>
                                        <td>
                                            <span class="font-mono text-slate-400" style="font-size:11px;"><?= htmlspecialchars($re['voucher_no']) ?></span>
                                        </td>
                                        <td>
                                            <div class="text-truncate fw-semibold text-slate-300" style="max-width: 160px;"><?= htmlspecialchars($re['title']) ?></div>
                                            <span class="badge badge-category font-mono" style="font-size:10px;"><i class="fa-solid fa-tag me-1"></i><?= htmlspecialchars($re['category']) ?></span>
                                        </td>
                                        <td class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($re['expense_date']) ?></td>
                                        <td class="text-end font-mono fw-bold text-rose-400">
                                            -<?= format_money($re['amount']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark = document.documentElement.classList.contains('dark');
    const labelColor = isDark ? '#94a3b8' : '#475569';
    const tickColor = isDark ? '#64748b' : '#64748b';
    const gridColor = isDark ? 'rgba(51, 65, 85, 0.3)' : 'rgba(226, 232, 240, 0.8)';

    // Trend Chart (Bar & Line Combo)
    const trendCtx = document.getElementById('trendChart')?.getContext('2d');
    if (trendCtx) {
        window.trendChartInstance = new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($trend_months) ?>,
                datasets: [
                    {
                        label: 'Bill Collected (Income)',
                        data: <?= json_encode($trend_income) ?>,
                        backgroundColor: 'rgba(5, 150, 105, 0.75)',
                        borderColor: '#059669',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Expenses & Payroll (Outflow)',
                        data: <?= json_encode($trend_expense) ?>,
                        backgroundColor: 'rgba(225, 29, 72, 0.75)',
                        borderColor: '#e11d48',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: labelColor, font: { family: 'JetBrains Mono', size: 11 } }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: tickColor, font: { family: 'JetBrains Mono', size: 10 } },
                        grid: { color: gridColor }
                    },
                    y: {
                        ticks: { color: tickColor, font: { family: 'JetBrains Mono', size: 10 } },
                        grid: { color: gridColor }
                    }
                }
            }
        });
    }

    // Expense Category Doughnut Chart
    const expCtx = document.getElementById('expenseChart')?.getContext('2d');
    if (expCtx) {
        new Chart(expCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($exp_labels) ?>,
                datasets: [{
                    data: <?= json_encode($exp_data) ?>,
                    backgroundColor: [
                        '#0284c7', '#e11d48', '#d97706', '#059669', '#7c3aed', '#2563eb', '#ec4899', '#64748b'
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: labelColor, font: { family: 'Inter', size: 10 }, boxWidth: 12 }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

