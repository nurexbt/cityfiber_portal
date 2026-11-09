-- ISP Office Management Database Schema
-- Database: ownisp_db

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,
    can_view TINYINT(1) DEFAULT 0,
    can_add TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_role_module (role_id, module),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Operator',
    role_id INT NULL,
    status ENUM('Active', 'Suspended') DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL DEFAULT 'City Fiber',
    company_tagline VARCHAR(255) DEFAULT 'High-Speed Optical Broadband Network',
    phone VARCHAR(50) DEFAULT '+880 1700-000000',
    email VARCHAR(100) DEFAULT 'noc@cityfiber.bd',
    address TEXT,
    currency_symbol VARCHAR(10) DEFAULT 'BDT',
    currency_code VARCHAR(10) DEFAULT 'BDT',
    bill_due_day INT DEFAULT 10,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    speed_mbps INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    type ENUM('Home', 'Corporate', 'Reseller') DEFAULT 'Home',
    description VARCHAR(255),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(30) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(100),
    nid VARCHAR(50),
    address TEXT NOT NULL,
    zone_id INT,
    package_id INT,
    monthly_bill DECIMAL(10,2) NOT NULL,
    connection_type ENUM('Home', 'Corporate', 'Reseller') DEFAULT 'Home',
    ip_address VARCHAR(50),
    pppoe_username VARCHAR(100),
    pppoe_password VARCHAR(100),
    onu_mac VARCHAR(50),
    fiber_core_box VARCHAR(100),
    join_date DATE NOT NULL,
    billing_start_date DATE NOT NULL,
    status ENUM('Active', 'Suspended', 'Due', 'Inactive') DEFAULT 'Active',
    total_due DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (zone_id),
    INDEX (package_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    billing_month VARCHAR(7) NOT NULL,
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    package_amount DECIMAL(10,2) NOT NULL,
    extra_charge DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    previous_due DECIMAL(10,2) DEFAULT 0.00,
    total_payable DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    due_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Unpaid', 'Partial', 'Paid', 'Overdue') DEFAULT 'Unpaid',
    generated_by INT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (customer_id),
    INDEX (billing_month),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(50) UNIQUE NOT NULL,
    bill_id INT NULL,
    customer_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    payment_method ENUM('Cash', 'bKash', 'Nagad', 'Rocket', 'Bank Transfer', 'POS/Card', 'Other') DEFAULT 'Cash',
    transaction_id VARCHAR(100),
    collected_by VARCHAR(100) DEFAULT 'Admin',
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (customer_id),
    INDEX (bill_id),
    INDEX (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investor_name VARCHAR(150) NOT NULL,
    investment_type ENUM('Capital', 'Partner Equity', 'Bank Loan', 'Personal Loan', 'Other') DEFAULT 'Capital',
    amount DECIMAL(12,2) NOT NULL,
    investment_date DATE NOT NULL,
    payment_mode VARCHAR(50) DEFAULT 'Bank Transfer',
    reference_no VARCHAR(100),
    purpose VARCHAR(255) NOT NULL,
    status ENUM('Active', 'Returned', 'Liquidated') DEFAULT 'Active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (investment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_no VARCHAR(50) UNIQUE NOT NULL,
    category ENUM('Bandwidth & Upstream', 'Fiber & Network Gear', 'Office Rent', 'Utilities & Electricity', 'Server & Software', 'Marketing & Promotion', 'Conveyance & Transport', 'Legal & Regulatory', 'Tea & Refreshment', 'Miscellaneous') NOT NULL,
    title VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_mode ENUM('Cash', 'bKash', 'Nagad', 'Bank', 'Cheque') DEFAULT 'Cash',
    paid_to VARCHAR(150),
    paid_by VARCHAR(100),
    reference_no VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (expense_date),
    INDEX (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_code VARCHAR(30) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(100),
    designation VARCHAR(100) NOT NULL,
    department ENUM('NOC & Technical', 'Support & Operations', 'Billing & Accounts', 'Management', 'Field Engineering') DEFAULT 'NOC & Technical',
    basic_salary DECIMAL(10,2) NOT NULL,
    allowance DECIMAL(10,2) DEFAULT 0.00,
    join_date DATE NOT NULL,
    status ENUM('Active', 'On Leave', 'Resigned') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_no VARCHAR(50) UNIQUE NOT NULL,
    employee_id INT NOT NULL,
    salary_month VARCHAR(7) NOT NULL,
    payment_date DATE NOT NULL,
    basic_amount DECIMAL(10,2) NOT NULL,
    allowance DECIMAL(10,2) DEFAULT 0.00,
    deduction DECIMAL(10,2) DEFAULT 0.00,
    net_paid DECIMAL(10,2) NOT NULL,
    payment_mode ENUM('Cash', 'Bank Transfer', 'bKash', 'Nagad', 'Cheque') DEFAULT 'Bank Transfer',
    reference_no VARCHAR(100),
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (employee_id),
    INDEX (salary_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed Settings
INSERT INTO settings (id, company_name, company_tagline, phone, email, address, currency_symbol, currency_code, bill_due_day)
VALUES (1, 'City Fiber', 'Ultra Fast Fiber Optical Broadband Network', '+880 1711-223344', 'noc@cityfiber.bd', 'House 14, Road 5, Block B, Mirpur-10, Dhaka-1216', 'BDT', 'BDT', 10)
ON DUPLICATE KEY UPDATE company_name=VALUES(company_name);

-- Seed Roles
INSERT INTO roles (id, name, description, is_system) VALUES
(1, 'Admin', 'Super Administrator with unrestricted portal authority', 1),
(2, 'Manager', 'Operations Manager with view, add, and edit access across all modules', 0),
(3, 'Operator', 'NOC Operator for subscriber onboarding and line diagnostics', 0),
(4, 'Accountant', 'Financial officer managing collections, expenses, and payroll', 0),
(5, 'Field Collector', 'Field agent with field collection sheet and bill receipt access', 0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Seed Role Permissions (15 Core Modules)
INSERT INTO role_permissions (role_id, module, can_view, can_add, can_edit, can_delete) VALUES
(1, 'dashboard', 1, 1, 1, 1),
(1, 'customers', 1, 1, 1, 1),
(1, 'import_customers', 1, 1, 1, 1),
(1, 'billing', 1, 1, 1, 1),
(1, 'payments', 1, 1, 1, 1),
(1, 'collection_sheet', 1, 1, 1, 1),
(1, 'sms', 1, 1, 1, 1),
(1, 'investments', 1, 1, 1, 1),
(1, 'expenses', 1, 1, 1, 1),
(1, 'employees', 1, 1, 1, 1),
(1, 'reports', 1, 1, 1, 1),
(1, 'network_tools', 1, 1, 1, 1),
(1, 'packages', 1, 1, 1, 1),
(1, 'settings', 1, 1, 1, 1),
(1, 'users', 1, 1, 1, 1),
(2, 'dashboard', 1, 1, 1, 1),
(2, 'customers', 1, 1, 1, 1),
(2, 'import_customers', 1, 1, 1, 1),
(2, 'billing', 1, 1, 1, 1),
(2, 'payments', 1, 1, 1, 1),
(2, 'collection_sheet', 1, 1, 1, 1),
(2, 'sms', 1, 1, 1, 1),
(2, 'investments', 1, 1, 1, 1),
(2, 'expenses', 1, 1, 1, 1),
(2, 'employees', 1, 1, 1, 1),
(2, 'reports', 1, 1, 1, 1),
(2, 'network_tools', 1, 1, 1, 1),
(2, 'packages', 1, 1, 1, 1),
(2, 'settings', 1, 1, 1, 0),
(2, 'users', 1, 1, 1, 0),
(3, 'dashboard', 1, 0, 0, 0),
(3, 'customers', 1, 1, 1, 0),
(3, 'import_customers', 1, 1, 0, 0),
(3, 'billing', 1, 0, 0, 0),
(3, 'payments', 1, 1, 0, 0),
(3, 'collection_sheet', 1, 0, 0, 0),
(3, 'sms', 1, 1, 0, 0),
(3, 'investments', 0, 0, 0, 0),
(3, 'expenses', 0, 0, 0, 0),
(3, 'employees', 0, 0, 0, 0),
(3, 'reports', 0, 0, 0, 0),
(3, 'network_tools', 1, 1, 1, 0),
(3, 'packages', 1, 0, 0, 0),
(3, 'settings', 0, 0, 0, 0),
(3, 'users', 0, 0, 0, 0),
(4, 'dashboard', 1, 0, 0, 0),
(4, 'customers', 1, 0, 0, 0),
(4, 'import_customers', 0, 0, 0, 0),
(4, 'billing', 1, 1, 1, 0),
(4, 'payments', 1, 1, 1, 0),
(4, 'collection_sheet', 1, 0, 0, 0),
(4, 'sms', 1, 1, 0, 0),
(4, 'investments', 1, 1, 1, 0),
(4, 'expenses', 1, 1, 1, 0),
(4, 'employees', 1, 1, 1, 0),
(4, 'reports', 1, 0, 0, 0),
(4, 'network_tools', 0, 0, 0, 0),
(4, 'packages', 1, 0, 0, 0),
(4, 'settings', 0, 0, 0, 0),
(4, 'users', 0, 0, 0, 0),
(5, 'dashboard', 1, 0, 0, 0),
(5, 'customers', 1, 0, 0, 0),
(5, 'import_customers', 0, 0, 0, 0),
(5, 'billing', 1, 0, 0, 0),
(5, 'payments', 1, 1, 0, 0),
(5, 'collection_sheet', 1, 0, 0, 0),
(5, 'sms', 0, 0, 0, 0),
(5, 'investments', 0, 0, 0, 0),
(5, 'expenses', 0, 0, 0, 0),
(5, 'employees', 0, 0, 0, 0),
(5, 'reports', 0, 0, 0, 0),
(5, 'network_tools', 0, 0, 0, 0),
(5, 'packages', 0, 0, 0, 0),
(5, 'settings', 0, 0, 0, 0),
(5, 'users', 0, 0, 0, 0)
ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_add=VALUES(can_add), can_edit=VALUES(can_edit), can_delete=VALUES(can_delete);

-- Seed Default Admin User: Admin / Team0@247
-- Also password_verify handles password_hash or md5 fallback
INSERT INTO users (id, username, password, full_name, role, role_id)
VALUES (1, 'Admin', '$2y$10$wN31X2yA2bKxsq5r0G4n7O9jYjF7Jp6VvI/M30jQf0l20bH649LqS', 'ISP Super Administrator', 'Admin', 1)
ON DUPLICATE KEY UPDATE username=VALUES(username);

-- Seed Zones
INSERT INTO zones (id, name, description) VALUES
(1, 'Zone 1 - Mirpur NOC Ring', 'Covering Mirpur 1, 2, 6, 10, 11 and Senpara'),
(2, 'Zone 2 - Uttara Sector Hub', 'Sectors 3, 7, 9, 11 with 10G OLT Ring'),
(3, 'Zone 3 - Dhanmondi Core', 'Road 27 to 32, residential & commercial customers'),
(4, 'Zone 4 - Banani Corporate', 'Dedicated corporate leased lines and VIP home')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Seed Packages
INSERT INTO packages (id, name, speed_mbps, price, type, description) VALUES
(1, 'Standard Fiber 20M', 20, 800.00, 'Home', '20 Mbps unlimited optical home connection with BDIX 100M'),
(2, 'Turbo Fiber 35M', 35, 1200.00, 'Home', '35 Mbps ultra low ping gaming & streaming'),
(3, 'VIP Ultra 60M', 60, 1800.00, 'Home', '60 Mbps high speed VIP home optical link with real IP'),
(4, 'Corporate 100M Dedicated', 100, 5000.00, 'Corporate', '100 Mbps 1:1 CIR dedicated duplex fiber link with 5 Static IPs'),
(5, 'SME Enterprise 200M', 200, 9500.00, 'Corporate', '200 Mbps enterprise with 99.9% uptime SLA')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Seed Employees
INSERT INTO employees (id, emp_code, name, phone, email, designation, department, basic_salary, allowance, join_date, status) VALUES
(1, 'EMP-101', 'Arifur Rahman', '+880 1819-112233', 'arif@cityfiber.bd', 'Chief NOC Network Engineer', 'NOC & Technical', 45000.00, 5000.00, '2024-01-15', 'Active'),
(2, 'EMP-102', 'Tanvir Hossain', '+880 1712-445566', 'tanvir@cityfiber.bd', 'Senior Fiber Optical Splicer', 'Field Engineering', 28000.00, 3000.00, '2024-03-01', 'Active'),
(3, 'EMP-103', 'Mehedi Hasan', '+880 1913-778899', 'mehedi@cityfiber.bd', 'Field Technician & Line Support', 'Field Engineering', 22000.00, 2500.00, '2024-06-10', 'Active'),
(4, 'EMP-104', 'Nusrat Jahan', '+880 1614-223344', 'nusrat@cityfiber.bd', 'Billing & Accounts Specialist', 'Billing & Accounts', 26000.00, 2000.00, '2024-04-01', 'Active'),
(5, 'EMP-105', 'Kamrul Islam', '+880 1515-998877', 'kamrul@cityfiber.bd', 'Customer Support & Helpdesk', 'Support & Operations', 20000.00, 1500.00, '2024-08-01', 'Active')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Seed Investments (.1 Investment Calculation)
INSERT INTO investments (id, investor_name, investment_type, amount, investment_date, payment_mode, reference_no, purpose, status, notes) VALUES
(1, 'Shahidul Alam (Partner)', 'Partner Equity', 850000.00, '2025-01-10', 'Bank Transfer', 'BRAC-TX-9921', 'Core 16-Port OLT, Cisco Core Switch & Server Rack Setup', 'Active', '40% Equity Stake partner capital'),
(2, 'Mahmudul Hasan (Partner)', 'Partner Equity', 650000.00, '2025-01-15', 'Bank Transfer', 'EBL-TR-8812', '10 KM Aerial 24-core Optical Fiber Rollout Mirpur Ring', 'Active', '30% Equity Stake partner capital'),
(3, 'City Bank SME Fund', 'Bank Loan', 500000.00, '2025-06-01', 'Bank Transfer', 'CBL-LOAN-2025-04', 'Backup Diesel Generator 15KVA & Online Smart UPS 10KVA', 'Active', '3 Years SME loan at 9% interest'),
(4, 'Founders Capital Pool', 'Capital', 300000.00, '2026-02-10', 'Cash / Bank', 'FND-2026-01', 'MikroTik CCR2004 Core Routers & Bandwidth Security Gateway', 'Active', 'Working reserve fund')
ON DUPLICATE KEY UPDATE investor_name=VALUES(investor_name);

-- Seed Expenses (1. Expense Calculation)
INSERT INTO expenses (id, voucher_no, category, title, amount, expense_date, payment_mode, paid_to, paid_by, reference_no, description) VALUES
(1, 'EXP-202611-001', 'Bandwidth & Upstream', 'Summit Communications Upstream 1Gbps Transit', 65000.00, '2026-11-01', 'Bank', 'Summit Communications Ltd', 'Admin', 'SCL-INV-99281', 'Primary upstream IP transit for November 2026'),
(2, 'EXP-202611-002', 'Bandwidth & Upstream', 'Fiber@Home NTTN Transmission Rent', 28000.00, '2026-11-02', 'Bank', 'Fiber@Home Limited', 'Admin', 'FAH-INV-7734', 'Core ring transmission dark fiber lease'),
(3, 'EXP-202611-003', 'Office Rent', 'NOC Main Control Room Office Rent', 32000.00, '2026-11-03', 'Bank', 'Property Owner Mr. Haque', 'Admin', 'RENT-NOV-26', 'Office space 1400 sqft rent for November 2026'),
(4, 'EXP-202611-004', 'Utilities & Electricity', 'DESCO Commercial Electricity Bill', 14500.00, '2026-11-04', 'bKash', 'DESCO', 'Nusrat Jahan', 'DESCO-44129', 'Electricity charges for Server AC and NOC hardware'),
(5, 'EXP-202611-005', 'Fiber & Network Gear', '50 Pcs Dual Band Gigabit ONUs & Patchcords', 42000.00, '2026-11-05', 'Cash', 'Optic Trade BD', 'Tanvir Hossain', 'OTB-9912', 'XPON dual band ONUs for new customer provisioning'),
(6, 'EXP-202611-006', 'Conveyance & Transport', 'Field Technician Bike Fuel & Emergency Splicing', 4800.00, '2026-11-06', 'Cash', 'Mehedi Hasan', 'Admin', 'VOUCH-331', 'Fuel allowance for Mirpur sector outage repair')
ON DUPLICATE KEY UPDATE voucher_no=VALUES(voucher_no);

-- Seed Customers (2. Customer Add)
INSERT INTO customers (id, customer_code, name, phone, email, nid, address, zone_id, package_id, monthly_bill, connection_type, ip_address, pppoe_username, pppoe_password, onu_mac, fiber_core_box, join_date, billing_start_date, status, total_due, notes) VALUES
(1, 'CUST-1001', 'Rashedul Karim', '+880 1711-123456', 'rashed@gmail.com', '1985123456789', 'House 22, Road 4, Section 10, Mirpur', 1, 2, 1200.00, 'Home', '103.166.230.12', 'rashed_mirpur', 'pass1234', 'F4:8E:38:11:22:AA', 'TJ Box-10, Core 4 (Blue)', '2025-02-01', '2025-02-01', 'Active', 0.00, 'VIP Customer, prompt payer'),
(2, 'CUST-1002', 'Syeda Farhana', '+880 1819-234567', 'farhana@yahoo.com', '1990987654321', 'Apt 4B, Green Palace, Sector 7, Uttara', 2, 1, 800.00, 'Home', '103.166.230.15', 'farhana_u7', 'pass5678', '48:8D:36:22:33:BB', 'TJ Box-U7, Core 2 (Orange)', '2025-03-15', '2025-04-01', 'Active', 800.00, 'November bill pending'),
(3, 'CUST-1003', 'Prime Media Software Ltd', '+880 1911-345678', 'billing@primemedia.com', '2001556677889', 'Level 6, Navana Tower, Banani Commercial Area', 4, 4, 5000.00, 'Corporate', '103.166.230.50', 'primemedia_corp', 'prime@99!', 'BC:54:51:77:88:CC', 'Fiber Hub Banani P-1', '2025-01-20', '2025-02-01', 'Active', 0.00, 'Dedicated 100M link, corporate account'),
(4, 'CUST-1004', 'Dr. Ahsan Ullah', '+880 1722-456789', 'ahsan.u@gmail.com', '1978334455667', 'Plot 12, Road 15/A, Dhanmondi', 3, 3, 1800.00, 'Home', '103.166.230.22', 'ahsan_dhan', 'dr@ahsan', 'E0:67:B3:44:55:DD', 'TJ Box-DH15, Core 1', '2025-05-10', '2025-06-01', 'Active', 0.00, 'Medical clinic high priority home office'),
(5, 'CUST-1005', 'Kazi Mahbub', '+880 1611-567890', 'mahbub.kazi@outlook.com', '1992112233445', 'Block C, Lane 2, Mirpur 2', 1, 1, 800.00, 'Home', '103.166.230.31', 'mahbub_m2', 'mk1234', 'A0:B1:C2:55:66:EE', 'TJ Box-M2, Core 3', '2025-08-01', '2025-08-01', 'Due', 1600.00, '2 Months due, contacted via SMS'),
(6, 'CUST-1006', 'Nexus BPO Solutions', '+880 1844-678901', 'admin@nexusbpo.net', '2005889900112', 'Suite 801, Rangs Fortune Square, Dhanmondi', 3, 5, 9500.00, 'Corporate', '103.166.230.90', 'nexus_enterprise', 'bpoSecure#2026', '70:4F:57:99:AA:FF', 'Core POP Dhanmondi', '2025-04-01', '2025-04-01', 'Active', 0.00, 'Enterprise client 200 Mbps with SLA')
ON DUPLICATE KEY UPDATE customer_code=VALUES(customer_code);

-- Seed Bills (3. Customer Bill Calculation)
INSERT INTO bills (id, bill_number, customer_id, billing_month, bill_date, due_date, package_amount, extra_charge, discount, previous_due, total_payable, paid_amount, due_amount, status, notes) VALUES
(1, 'INV-202611-1001', 1, '2026-11', '2026-11-01', '2026-11-10', 1200.00, 0.00, 0.00, 0.00, 1200.00, 1200.00, 0.00, 'Paid', 'Regular monthly internet bill'),
(2, 'INV-202611-1002', 2, '2026-11', '2026-11-01', '2026-11-10', 800.00, 0.00, 0.00, 0.00, 800.00, 0.00, 800.00, 'Unpaid', 'November bill generated'),
(3, 'INV-202611-1003', 3, '2026-11', '2026-11-01', '2026-11-10', 5000.00, 0.00, 0.00, 0.00, 5000.00, 5000.00, 0.00, 'Paid', 'Corporate bill paid by BEFTN Bank Transfer'),
(4, 'INV-202611-1004', 4, '2026-11', '2026-11-01', '2026-11-10', 1800.00, 0.00, 0.00, 0.00, 1800.00, 1800.00, 0.00, 'Paid', 'Paid via bKash Merchant'),
(5, 'INV-202611-1005', 5, '2026-11', '2026-11-01', '2026-11-10', 800.00, 0.00, 0.00, 800.00, 1600.00, 0.00, 1600.00, 'Overdue', 'Previous month 800 + current 800'),
(6, 'INV-202611-1006', 6, '2026-11', '2026-11-01', '2026-11-10', 9500.00, 0.00, 0.00, 0.00, 9500.00, 9500.00, 0.00, 'Paid', 'Enterprise billing auto paid')
ON DUPLICATE KEY UPDATE bill_number=VALUES(bill_number);

-- Seed Payments (5. Customer Bill Payment Receive)
INSERT INTO payments (id, receipt_no, bill_id, customer_id, payment_date, amount, discount, payment_method, transaction_id, collected_by, remarks) VALUES
(1, 'REC-202611-001', 1, 1, '2026-11-03', 1200.00, 0.00, 'bKash', 'BKH88291039', 'Admin', 'Monthly subscription for November 2026'),
(2, 'REC-202611-002', 3, 3, '2026-11-04', 5000.00, 0.00, 'Bank Transfer', 'BEFTN-PRIME-771', 'Admin', 'Corporate payment via The City Bank'),
(3, 'REC-202611-003', 4, 4, '2026-11-05', 1800.00, 0.00, 'Nagad', 'NGD99218273', 'Nusrat Jahan', 'Dr. Ahsan clinic internet bill paid in full'),
(4, 'REC-202611-004', 6, 6, '2026-11-05', 9500.00, 0.00, 'Bank Transfer', 'EBL-NEXUS-883', 'Admin', 'Nexus Enterprise corporate invoice payment')
ON DUPLICATE KEY UPDATE receipt_no=VALUES(receipt_no);

-- Seed Salary Payments (4. Employees Cost)
INSERT INTO salary_payments (id, voucher_no, employee_id, salary_month, payment_date, basic_amount, allowance, deduction, net_paid, payment_mode, reference_no, remarks) VALUES
(1, 'SAL-202610-001', 1, '2026-10', '2026-11-01', 45000.00, 5000.00, 0.00, 50000.00, 'Bank Transfer', 'SAL-TX-101', 'October 2026 Chief NOC Engineer Salary'),
(2, 'SAL-202610-002', 2, '2026-10', '2026-11-01', 28000.00, 3000.00, 0.00, 31000.00, 'Bank Transfer', 'SAL-TX-102', 'October 2026 Senior Splicer Salary'),
(3, 'SAL-202610-003', 3, '2026-10', '2026-11-01', 22000.00, 2500.00, 0.00, 24500.00, 'Cash', 'CASH-REC-103', 'October 2026 Field Technician Salary'),
(4, 'SAL-202610-004', 4, '2026-10', '2026-11-01', 26000.00, 2000.00, 0.00, 28000.00, 'Bank Transfer', 'SAL-TX-104', 'October 2026 Accounts Specialist Salary'),
(5, 'SAL-202610-005', 5, '2026-10', '2026-11-01', 20000.00, 1500.00, 0.00, 21500.00, 'Cash', 'CASH-REC-105', 'October 2026 Helpdesk Executive Salary')
ON DUPLICATE KEY UPDATE voucher_no=VALUES(voucher_no);

