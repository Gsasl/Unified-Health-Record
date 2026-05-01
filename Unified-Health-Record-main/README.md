# HRec: Unified Health Record (DBMS Demo)

> **CSE370 Database Project**

## 📖 Project Summary
The Unified Health Record (HRec) is an interconnected web platform and Clinical Decision Support System (CDSS). Built strictly as a pedagogy-focused academic database demonstration, the project centralizes patient medical data to bridge the gap between Patients, Doctors, and Pharmacists. It explicitly avoids complex frontend frameworks, using the UI solely as a visualization layer to highlight robust Relational Database management concepts, including strict normalization, ACID transactions, and automated clinical rules processing via SQL Views and Triggers.

## 🛠️ Quick Setup (XAMPP)
1. Start **XAMPP** (Apache + MySQL).
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Import `setup.sql` to create the `bracculs_hrec` database.
4. Copy this project folder to `C:\xampp\htdocs\Unified-Health-Record-main\`.
5. Access the application via `http://localhost/Unified-Health-Record-main/`.

## 🔑 Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Patient | patient1@hrec.test | patient123 |
| Doctor | doctor@hrec.test | doctor123 |
| Pharmacist | pharma@hrec.test | pharma123 |

## 🗺️ Feature-to-DBMS Mapping
Every major frontend page acts as a live demonstration of a specific SQL concept:
- `patient_dashboard.php` → **1:1 Relationships & Generated Columns**
- `medication_log.php` → **M:N Bridge Tables with Payload**
- `allergy_check.php` → **JSON Storage vs. Normalization**
- `doctor_overview.php` → **Complex LEFT JOINs & Aggregations**
- `prescribe.php` → **ACID Transactions (Multi-table insertion)**
- `pharmacy.php` → **ACID Transactions (Concurrency & Stock Control)**
- `cdss_alerts.php` → **Views & Triggers (Automated Rules Engine)**

## ⚖️ Architectural Tradeoffs
While the core database strictly adheres to Third Normal Form (3NF) and the IS-A inheritance pattern, practical engineering tradeoffs were utilized to simulate real-world MVP development. Most notably, patient allergies are stored in a MySQL `JSON` array rather than a fully normalized `Patient_Allergies` bridge table. This compromise reduces excessive `JOIN` operations for flat, rarely-aggregated lists, acknowledging the sacrifice in B-Tree indexing speed for developmental agility. Furthermore, CDSS alerts rely heavily on database `TRIGGERS` (row-based cursors), which ensures strict data integrity but introduces known I/O overhead during massive data insertions.
