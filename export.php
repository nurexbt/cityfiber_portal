<?php
// export.php - CSV / Excel Export Engine for ISP Modules
require_once __DIR__ . '/config/auth.php';
require_login();

$type = $_GET['type'] ?? '';

if ($type === 'customers') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ISP_Customers_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Customer Code', 'Name', 'Phone', 'Email', 'Address', 'Zone', 'Package', 'Monthly Bill', 'IP Address', 'PPPoE Username', 'ONU MAC', 'Fiber TJ Box', 'Status', 'Total Due']);

    $stmt = $pdo->query("
        SELECT c.customer_code, c.name, c.phone, c.email, c.address, z.name AS zone_name, p.name AS package_name, 
               c.monthly_bill, c.ip_address, c.pppoe_username, c.onu_mac, c.fiber_core_box, c.status, c.total_due
        FROM customers c
        LEFT JOIN zones z ON c.zone_id = z.id
        LEFT JOIN packages p ON c.package_id = p.id
        ORDER BY c.id ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

if ($type === 'bills') {
    $month = $_GET['month'] ?? date('Y-m');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ISP_Bills_' . str_replace('-', '', $month) . '_' . date('His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice Number', 'Customer Code', 'Customer Name', 'Phone', 'Billing Month', 'Bill Date', 'Due Date', 'Package Amount', 'Previous Due', 'Total Payable', 'Paid Amount', 'Due Amount', 'Status']);

    $stmt = $pdo->prepare("
        SELECT b.bill_number, c.customer_code, c.name, c.phone, b.billing_month, b.bill_date, b.due_date, 
               b.package_amount, b.previous_due, b.total_payable, b.paid_amount, b.due_amount, b.status
        FROM bills b
        JOIN customers c ON b.customer_id = c.id
        WHERE b.billing_month = ?
        ORDER BY b.id ASC
    ");
    $stmt->execute([$month]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

if ($type === 'collections') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ISP_Collections_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Receipt Number', 'Customer Code', 'Customer Name', 'Phone', 'Payment Date', 'Amount', 'Discount', 'Payment Method', 'Transaction ID', 'Collected By', 'Remarks']);

    $stmt = $pdo->query("
        SELECT p.receipt_no, c.customer_code, c.name, c.phone, p.payment_date, p.amount, p.discount, 
               p.payment_method, p.transaction_id, p.collected_by, p.remarks
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        ORDER BY p.id DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

if ($type === 'expenses') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ISP_Expenses_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Voucher Number', 'Category', 'Title', 'Amount', 'Expense Date', 'Payment Mode', 'Paid To', 'Paid By', 'Reference No', 'Description']);

    $stmt = $pdo->query("
        SELECT voucher_no, category, title, amount, expense_date, payment_mode, paid_to, paid_by, reference_no, description
        FROM expenses
        ORDER BY expense_date DESC, id DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

if ($type === 'investments') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ISP_Investments_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Investor Name', 'Investment Type', 'Amount', 'Investment Date', 'Payment Mode', 'Reference No', 'Purpose', 'Status', 'Notes']);

    $stmt = $pdo->query("
        SELECT id, investor_name, investment_type, amount, investment_date, payment_mode, reference_no, purpose, status, notes
        FROM investments
        ORDER BY investment_date DESC, id DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

header('Location: index.php');
exit;

