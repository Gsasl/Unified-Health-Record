# HRec: Unified Health Manager

> CSE370 Database Project — 10 Tables · 1 VIEW · 3 Triggers · 7 Features

## 🚀 Quick Start (XAMPP)

### 1. Database Setup
1. Start **XAMPP** (Apache + MySQL)
2. Open **phpMyAdmin** → http://localhost/phpmyadmin
3. Click **Import** → select `setup.sql` → click **Go**
4. This creates the `bracculs_hrec` database with all tables, triggers, seed data, and demo users

### 2. Application Setup
1. Copy this entire folder to `C:\xampp\htdocs\Unified-Health-Record-main\`
2. Open http://localhost/Unified-Health-Record-main/ in your browser
3. Login with demo credentials (shown on login page)

### 3. First-Time Password Setup
After importing the SQL, run the password setup helper once:
```
http://localhost/Unified-Health-Record-main/setup_passwords.php
```
This generates proper bcrypt hashes for the 3 demo accounts.

---

## 🔐 Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Patient | patient1@hrec.test | patient123 |
| Doctor | doctor@hrec.test | doctor123 |
| Pharmacist | pharma@hrec.test | pharma123 |

---

## 📋 Features

| # | Feature | Page | Role | Key Queries |
|---|---------|------|------|-------------|
| F1 | Health Record + Auto BMI | `patient_dashboard.php` | Patient | STORED generated column |
| F2 | Active Medication Log | `medication_log.php` | Patient | JOIN Brands, Generics |
| F3 | CDSS Drug Conflict Detection | `cdss_alerts.php` | Patient + Doctor | CDSS_Active_Conflicts VIEW |
| F4 | Allergy Cross-Reference | `allergy_check.php` | Patient | JSON_ARRAY_APPEND |
| F5 | Doctor Patient Overview | `doctor_overview.php` | Doctor | Subquery aggregates |
| F6 | Prescription + Best Brand | `prescribe.php` | Doctor | JSON rating extraction, ORDER BY composite |
| F7 | Pharmacist Fulfillment (ACID) | `pharmacy.php` | Pharmacist | BEGIN/COMMIT/ROLLBACK transaction |
| F8 | User Registration | `register.php` | All Users | Role-based conditional inserts, PDO Transactions |
| F9 | Identity-Verified Password Reset | `reset_password.php` | All Users | Multi-step verification, bcrypt hashing |

---

## 🗄️ Schema (10 Tables)

`Users` → `Health_Records` (1:1) → `Doctors` (IS-A) → `Generics` → `Brands` (N:1) → `Drug_Conflicts` (self-ref bridge) → `USER_MED_LOG` (M:N bridge) → `Prescriptions` → `Medical_Flags` → `SECURITY_AUDIT_LOG`

---

## 🛠️ Tech Stack
- **Frontend:** HTML5, Vanilla CSS, JavaScript
- **Backend:** PHP 8+ (PDO)
- **Database:** MySQL (XAMPP)
- **ETL:** Python 3 (medex_scraper.py)
