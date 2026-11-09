-- ============================================================================
-- City Fiber - Fresh / Production Database Schema (Clean Setup)
-- Database: ownisp_db
-- Description: Clean tables without sample records (Only Admin & System Roles)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `ownisp_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ownisp_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. ROLES & PERMISSIONS (RBAC)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
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

-- ----------------------------------------------------------------------------
-- 2. PORTAL USERS
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS users;

CREATE TABLE users (
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

-- ----------------------------------------------------------------------------
-- 3. GENERAL SETTINGS
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS settings;

CREATE TABLE settings (
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

-- ----------------------------------------------------------------------------
-- 4. INTERNET PACKAGES & DISTRIBUTION ZONES
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS packages;

CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    speed_mbps INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    type ENUM('Home', 'Corporate', 'Reseller') DEFAULT 'Home',
    description VARCHAR(255),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS zones;

CREATE TABLE zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. SUBSCRIBERS / CUSTOMERS
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS customers;

CREATE TABLE customers (
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

-- ----------------------------------------------------------------------------
-- 6. BILLING & INVOICES
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS bills;

CREATE TABLE bills (
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

-- ----------------------------------------------------------------------------
-- 7. PAYMENTS & MONEY RECEIPTS
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS payments;

CREATE TABLE payments (
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

-- ----------------------------------------------------------------------------
-- 8. CAPITAL INVESTMENTS (.1)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS investments;

CREATE TABLE investments (
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

-- ----------------------------------------------------------------------------
-- 9. OPERATIONAL EXPENSES (1.)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS expenses;

CREATE TABLE expenses (
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

-- ----------------------------------------------------------------------------
-- 10. EMPLOYEES & PAYROLL (4.)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS salary_payments;
DROP TABLE IF EXISTS employees;

CREATE TABLE employees (
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

CREATE TABLE salary_payments (
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

-- ============================================================================
-- ESSENTIAL SEED DATA ONLY (Settings, Roles, Permissions & Super Admin)
-- ============================================================================

-- 1. System Settings
INSERT INTO settings (id, company_name, company_tagline, phone, email, address, currency_symbol, currency_code, bill_due_day)
VALUES (1, 'City Fiber', 'Ultra Fast Fiber Optical Broadband Network', '+880 1711-223344', 'noc@cityfiber.bd', 'House 14, Road 5, Block B, Mirpur-10, Dhaka-1216', 'BDT', 'BDT', 10);

-- 2. System Roles
INSERT INTO roles (id, name, description, is_system) VALUES
(1, 'Admin', 'Super Administrator with unrestricted portal authority', 1),
(2, 'Manager', 'Operations Manager with view, add, and edit access across all modules', 0),
(3, 'Operator', 'NOC Operator for subscriber onboarding and line diagnostics', 0),
(4, 'Accountant', 'Financial officer managing collections, expenses, and payroll', 0),
(5, 'Field Collector', 'Field agent with field collection sheet and bill receipt access', 0);

-- 3. Role Permissions (15 Core Portal Modules)
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
(5, 'users', 0, 0, 0, 0);

-- 4. Default Super Administrator
-- Login: Admin / Team0@247
INSERT INTO users (id, username, password, full_name, email, phone, role, role_id, status)
VALUES (
    1, 
    'Admin', 
    '$2y$10$wN31X2yA2bKxsq5r0G4n7O9jYjF7Jp6VvI/M30jQf0l20bH649LqS', 
    'ISP Super Administrator', 
    'admin@cityfiber.bd', 
    '+880 1700-000000', 
    'Admin', 
    1, 
    'Active'
);

