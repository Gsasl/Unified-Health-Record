# CSE370: Database Systems
## Project Report
**Project Name:** Unified Health Record (HRec)

### 1. Introduction
The Unified Health Record (HRec) is an interconnected web platform and Clinical Decision Support System (CDSS). It bridges the gap between Patients, Doctors, and Pharmacists by maintaining a single source of truth for medical records, while actively monitoring for dangerous drug-to-drug interactions using advanced SQL logic.

### 2. System Features
* **Auto BMI Calculation**: Uses a MySQL GENERATED STORED column to calculate BMI.
* **Active Medication Log**: Patients can track active medications linking Brands to Generics.
* **CDSS Drug Conflict Detection**: Real-time alerts generated using a complex SQL VIEW and triggers.
* **Allergy Cross-Reference**: Uses JSON_ARRAY_APPEND to manage allergy lists.
* **Doctor Patient Overview**: Doctors can manage patients with Subquery aggregates.
* **Prescription & Best Brand**: Doctors write prescriptions dynamically mapped to Brands.
* **Pharmacist Fulfillment**: Strict PDO Transactions (BEGIN/COMMIT/ROLLBACK) for stock sync.
* **User Registration**: Role-based user creation with secure Bcrypt hashing.
* **Identity-Verified Password Reset**: Two-step identity verification for account recovery.

### 3. Schema & Database Design
The database consists of 10 fully normalized tables with specific relationships:
* Users -> Health_Records (1:1 Relationship using UNIQUE constraint)
* Users -> Doctors (IS-A Inheritance Pattern using UserID as PK and FK)
* Brands -> Generics (N:1 Relationship)
* Drug_Conflicts (Self-referencing bridge between Generics)
* USER_MED_LOG (M:N Bridge Payload connecting Users and Brands)
* Prescriptions (Formal drug orders)
* Medical_Flags (CDSS Output logs)
* SECURITY_AUDIT_LOG (System tracking table)

### 4. Data Acquisition
Clinical data was acquired via a custom Python script (`tools/medex_scraper.py`) utilizing requests and BeautifulSoup. It scrapes clinical headings from MedEx and filters data directly into formatted SQL INSERT statements to populate Generics and Brands.

### 5. Design Rationale & Future Improvements
**Design Choices:**
- Relational integrity ensures no orphaned medical logs.
- Offloading CDSS logic to MySQL Views and Triggers instead of PHP loops reduces application-layer bottleneck.
- PDO Prepared statements heavily mitigate SQL injection risks.

**Drawbacks:**
- Lack of real-time WebSockets requires manual page refresh for Pharmacists to see new alerts.
- Monolithic architecture couples the CDSS engine tightly with the main DB.
- JSON columns for allergies limit multi-valued index querying speed at massive scale.
