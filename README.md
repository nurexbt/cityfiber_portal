# City Fiber Portal - ISP & NOC Operations Management Platform

A high-density, compact web portal built with **PHP**, **MySQL**, and **Bootstrap 5**, styled after modern Network Operations Centers (NOC).

---

## 🚀 Quick Access

- **Web Portal URL:** [http://localhost:81/ownisp/](http://localhost:81/ownisp/)
- **Default Username:** `Admin`
- **Default Password:** `Team0@247`

---

## 🛠 Features & Modules

### 1. `.1 Investment Calculation` (`investments.php`)
- Track and calculate capital pool investments, partner equity injections, and debt/bank loans.
- Calculate investor shareholdings and ROI / returned capital.
- Filter by investment type and record purpose/asset details.

### 2. `1. Expense Calculation` (`expenses.php`)
- Categorized expense tracking tailored for ISPs:
  - Bandwidth & Upstream Transit (Summit, Fiber@Home)
  - Fiber & Network Gear (ONUs, switches, splicing, TJ boxes)
  - Office Rent & Utilities (Server AC, power, generator fuel)
  - Transport, Marketing, Legal (BTRC), and Petty Cash
- Month & category filtering with auto-generated expense voucher numbers (`EXP-YYYYMM-XXXX`).

### 3. `2. Customer Add & Management` (`customers.php`)
- Complete broadband subscriber management:
  - Identity & Contact: Full name, mobile, NID, address.
  - Network & Hardware Mapping: Assigned Static/DHCP IP, PPPoE username/password, ONU MAC address, Fiber TJ box & core color.
  - Package & Billing Rate: Bandwidth plan, agreed monthly bill, billing start date.
  - Live status tracking: `Active`, `Due`, `Suspended`, `Inactive`.
  - Comprehensive customer view and financial ledger history (`customer_view.php`).

### 4. `3. Customer Bill Calculation` (`billing.php`)
- **Automated Recurring Billing Engine**:
  - 1-Click monthly invoice generation for all active and due subscribers.
  - Automatic balance carryover (adds unpaid dues from prior cycles to current invoice).
  - Configurable monthly due day (e.g. 10th of every month).
  - Duplicate generation prevention.
- Custom single invoice generator for ad-hoc services, router installments, or static IP charges.

### 5. `4. Employees Cost & Payroll` (`employees.php`)
- Employee directory: NOC Engineers, fiber optical splicers, field technicians, billing staff.
- Monthly salary calculation and disbursal:
  - Basic salary, allowances, overtime, and deductions.
  - Disbursal records with salary voucher numbers (`SAL-YYYYMM-XXXX`).
  - Total monthly employee cost tracking by department.

### 6. `5. Customer Bill Payment Receive` (`payment_receive.php`)
- Fast cashier desk for collecting bill payments:
  - Instant subscriber lookup with live due balance display.
  - Supports Cash, bKash, Nagad, Rocket, Bank Transfer, and POS cards.
  - Automatic deduction from customer due balance and invoice status settlement.
  - Immediate generation of printable Money Receipts (`REC-YYYYMM-XXXX`).

### 7. Invoices & Receipts (`invoice.php`)
- Dual format printable documents:
  - **A4 Professional Format**: Full ISP invoice with line items, previous dues, breakdown, and signature blocks.
  - **80mm POS Thermal Format**: Compact thermal receipt for field agents and counter cashiers.

### 8. Financial P&L Reports (`reports.php`)
- Month-by-month and annual consolidated statement:
  - Inflows: Capital Investments + Customer Bill Revenues
  - Outflows: Operating Expenses + Staff Payroll Cost
  - Calculated Net Operating Profit / Cash Flow margin.

---

## 🗄 Database Setup

- **Database Name:** `ownisp_db`
- **Port:** `3307` (with automatic fallback to `3306`)
- **Credentials:** User `root`, password `""` (empty)
- **Schema File:** `schema.sql` (pre-imported with sample ISP demo data)

