<?php
// config/db.php - Database Connection Configuration

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

$db_host = '127.0.0.1';
$db_ports = [3307, 3306]; // Checks 3307 first (current XAMPP port), then standard 3306
$db_name = 'ownisp_db';
$db_user = 'root';
$db_pass = '';

$pdo = null;
$last_error = '';

foreach ($db_ports as $port) {
    try {
        $dsn = "mysql:host={$db_host};port={$port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        break; // Successfully connected!
    } catch (PDOException $e) {
        $last_error = $e->getMessage();
    }
}

if (!$pdo) {
    die("
    <div style='background:#0b0f19; color:#f43f5e; font-family:monospace; padding:30px; text-align:center;'>
        <h2>Database Connection Error</h2>
        <p>Could not connect to MySQL server on ports 3307 or 3306.</p>
        <p>Error details: " . htmlspecialchars($last_error) . "</p>
        <p>Ensure XAMPP MySQL is started and ownisp_db is imported.</p>
    </div>");
}

// Fetch general settings
try {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
    $app_settings = $stmt->fetch();
    if (!$app_settings) {
        $app_settings = [
            'company_name' => 'City Fiber',
            'company_tagline' => 'Optical Broadband Network',
            'phone' => '+880 1700-000000',
            'email' => 'noc@cityfiber.bd',
            'currency_symbol' => 'BDT',
            'currency_code' => 'BDT',
            'bill_due_day' => 10,
            'address' => 'Mirpur, Dhaka, Bangladesh'
        ];
    }
} catch (Exception $e) {
    $app_settings = [
        'company_name' => 'City Fiber',
        'company_tagline' => 'Optical Broadband Network',
        'phone' => '+880 1700-000000',
        'email' => 'noc@cityfiber.bd',
        'currency_symbol' => 'BDT',
        'currency_code' => 'BDT',
        'bill_due_day' => 10,
        'address' => 'Mirpur, Dhaka, Bangladesh'
    ];
}

// Helper formatting functions
function format_money($amount) {
    global $app_settings;
    $symbol = !empty($app_settings['currency_symbol']) ? $app_settings['currency_symbol'] : 'BDT';
    if ($symbol === 'BDT') { $symbol = 'BDT'; }
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function clean_input($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

