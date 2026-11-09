<?php
// reports.php - Financial Profit & Loss Statement and Comprehensive Calculation
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('reports', 'view');

$page_title = 'P&L Financial Reports';
require_once __DIR__ . '/includes/header.php';

$selected_year = $_GET['year'] ?? date('Y');

// Fetch Year's Monthly Breakdown
$monthly_records = [];
for ($m = 1; $m <= 12; $m++) {
    $month_str = sprintf('%s-%02d', $selected_year, $m);
    
    // Revenue collected (Customer Payments)
    $rev = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '{$month_str}'")->fetchColumn();
    
    // Expenses (Operating Costs)
    $exp = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '{$month_str}'")->fetchColumn();
    
    // Employee Salaries
    $sal = $pdo->query("SELECT COALESCE(SUM(net_paid), 0) FROM salary_payments WHERE salary_month = '{$month_str}'")->fetchColumn();
    
    // Investment Injected
    $inv = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE DATE_FORMAT(investment_date, '%Y-%m') = '{$month_str}' AND status = 'Active'")->fetchColumn();

    $total_outflow = $exp + $sal;
    $net_profit = $rev - $total_outflow;

    $monthly_records[] = [
        'month_num' => $m,
        'month_name' => date('F', mktime(0, 0, 0, $m, 10)),
        'month_key' => $month_str,
        'investment' => (float)$inv,
        'revenue' => (float)$rev,
        'expense' => (float)$exp,
        'salary' => (float)$sal,
        'total_outflow' => (float)$total_outflow,
        'net_profit' => (float)$net_profit
    ];
}

// Annual Totals
$annual_investment = array_sum(array_column($monthly_records, 'investment'));
$annual_revenue = array_sum(array_column($monthly_records, 'revenue'));
$annual_expenses = array_sum(array_column($monthly_records, 'expense'));
$annual_salaries = array_sum(array_column($monthly_records, 'salary'));
$annual_outflow = $annual_expenses + $annual_salaries;
$annual_net_profit = $annual_revenue - $annual_outflow;
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">Financial P&amp;L Statement</h4>
            <span class="badge badge-header-emerald font-mono text-xs">
                Year <?= htmlspecialchars($selected_year) ?>
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Consolidated financial calculation uniting Capital Investment (.1), Expenses (1), Employee Cost (4), and Bill Revenue (5).</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="reports.php" class="d-flex align-items-center gap-2">
            <select name="year" class="form-select form-select-noc form-select-sm font-mono" onchange="this.form.submit()">
                <?php for ($y = date('Y') + 1; $y >= date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-cyan font-mono text-xs">
            <i class="fa-solid fa-print me-1"></i> Print P&L Statement
        </button>
    </div>
</div>

<!-- Annual Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">.1 Capital Invested</div>
            <div class="kpi-value text-cyan-400"><?= format_money($annual_investment) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Annual capital inflow</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">5. Total Bill Revenue</div>
            <div class="kpi-value text-emerald-400"><?= format_money($annual_revenue) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Subscribers bill collection</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-rose h-100">
            <div class="kpi-title">1. Operational Expenses</div>
            <div class="kpi-value text-rose-400"><?= format_money($annual_expenses) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Upstream, fiber & rent</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-2">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">4. Employee Cost</div>
            <div class="kpi-value text-purple-400"><?= format_money($annual_salaries) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Staff payroll disbursed</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card <?= $annual_net_profit >= 0 ? 'kpi-emerald' : 'kpi-rose' ?> h-100">
            <div class="kpi-title">Net Annual Profit / Margin</div>
            <div class="kpi-value <?= $annual_net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>"><?= format_money($annual_net_profit) ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Revenue - Total Operational Costs</div>
        </div>
    </div>
</div>

<!-- Consolidated Monthly Ledger -->
<div class="card card-noc">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
            <i class="fa-solid fa-table-list text-cyan-400 me-2"></i><?= $selected_year ?> Monthly Financial Breakdown
        </span>
        <span class="text-slate-400 font-mono text-xs">All Figures in BDT (BDT)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-noc align-middle">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-end">.1 Investment Inflow</th>
                        <th class="text-end">5. Bill Collections</th>
                        <th class="text-end">1. Operational Expenses</th>
                        <th class="text-end">4. Employee Salaries</th>
                        <th class="text-end">Total Outflow</th>
                        <th class="text-end">Net Profit / Cash Flow</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_records as $rec): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-slate-200"><?= $rec['month_name'] ?></div>
                                <span class="font-mono text-slate-500 text-xs"><?= $rec['month_key'] ?></span>
                            </td>
                            <td class="text-end font-mono text-cyan-400">
                                <?= $rec['investment'] > 0 ? '+' . format_money($rec['investment']) : '—' ?>
                            </td>
                            <td class="text-end font-mono text-emerald-400 fw-bold">
                                <?= $rec['revenue'] > 0 ? '+' . format_money($rec['revenue']) : '—' ?>
                            </td>
                            <td class="text-end font-mono text-rose-400">
                                <?= $rec['expense'] > 0 ? '-' . format_money($rec['expense']) : '—' ?>
                            </td>
                            <td class="text-end font-mono text-purple-400">
                                <?= $rec['salary'] > 0 ? '-' . format_money($rec['salary']) : '—' ?>
                            </td>
                            <td class="text-end font-mono text-slate-300 fw-semibold">
                                <?= $rec['total_outflow'] > 0 ? format_money($rec['total_outflow']) : '—' ?>
                            </td>
                            <td class="text-end font-mono fw-bold fs-6 <?= $rec['net_profit'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                <?= ($rec['revenue'] > 0 || $rec['total_outflow'] > 0) ? format_money($rec['net_profit']) : '—' ?>
                            </td>
                            <td class="text-center">
                                <?php if ($rec['revenue'] == 0 && $rec['total_outflow'] == 0): ?>
                                    <span class="badge badge-suspended">No Activity</span>
                                <?php elseif ($rec['net_profit'] >= 0): ?>
                                    <span class="badge badge-active"><i class="fa-solid fa-arrow-trend-up me-1"></i> Profitable</span>
                                <?php else: ?>
                                    <span class="badge badge-due"><i class="fa-solid fa-arrow-trend-down me-1"></i> Deficit</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="border-top border-slate-700 bg-slate-950 font-mono">
                    <tr class="fw-bold">
                        <td class="text-white">YEAR <?= $selected_year ?> TOTAL:</td>
                        <td class="text-end text-cyan-400"><?= format_money($annual_investment) ?></td>
                        <td class="text-end text-emerald-400"><?= format_money($annual_revenue) ?></td>
                        <td class="text-end text-rose-400"><?= format_money($annual_expenses) ?></td>
                        <td class="text-end text-purple-400"><?= format_money($annual_salaries) ?></td>
                        <td class="text-end text-slate-200"><?= format_money($annual_outflow) ?></td>
                        <td class="text-end fs-5 <?= $annual_net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>"><?= format_money($annual_net_profit) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $annual_net_profit >= 0 ? 'badge-active' : 'badge-due' ?>">
                                <?= $annual_net_profit >= 0 ? 'NET GAIN' : 'NET LOSS' ?>
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

