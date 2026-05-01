# Feature to DBMS Mapping

This table provides a quick reference for the CSE370 viva presentation, linking each frontend page directly to the underlying database architecture it demonstrates.

| Feature Code | Page (`pages/`) | Role | DBMS Concept Demonstrated | Main Tables/View Used | Demo Talking Point |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **F1** | `patient_dashboard.php` | Patient | 1:1 Relationships & Generated Columns | `Users` 1:1 `Health_Records` | "Notice the BMI. It's auto-calculated by the database using `GENERATED ALWAYS AS`—the PHP doesn't do any math." |
| **F2** | `medication_log.php` | Patient | M:N Bridge Table with Payload | `Users` ↔ `USER_MED_LOG` ↔ `Brands` | "This bridge table holds extra payload data, like the `Status` and `DateAdded`, preventing many-to-many data anomalies." |
| **F3** | `allergy_check.php` | Patient | JSON Storage vs Normalization Tradeoff | `Health_Records` (JSON column) | "We used MySQL's JSON array for allergies as a practical MVP compromise to avoid excessive joining for flat lists." |
| **F4** | `doctor_overview.php` | Doctor | Complex `LEFT JOIN` & Aggregation Subqueries | `Users` (Doctors) → `Users` (Patients) | "This single query uses `LEFT JOIN` and subqueries to pull patient details, active med count, and unresolved flags without the N+1 problem." |
| **F5** | `prescribe.php` | Doctor | ACID Transactions (Insert block) | `Prescriptions` & `USER_MED_LOG` | "Writing a prescription must be atomic. The `BEGIN` and `COMMIT` block ensures the log and the script are perfectly synced." |
| **F6** | `pharmacy.php` | Pharmacist | ACID Transactions (Concurrency/Stock) | `Prescriptions` & `Brands` | "Fulfilling this script decrements stock and logs an audit. If stock drops below 0, it hits the constraint and the whole block `ROLLBACK`s." |
| **F7** | `cdss_alerts.php` | Doctor/Patient | `VIEW` and `TRIGGER` Output | `CDSS_Active_Conflicts` (View) | "This UI simply queries a massive View. The actual alert generation was handled automatically by a Trigger when the drug was logged." |
