# In-Depth SQL Analysis: `setup.sql`

This document provides a deep dive into the architecture established by `setup.sql`. It explains the reasoning behind the DBMS structure, detailed examples of the SQL queries and concepts used, along with their pros (goods) and cons (bads).

---

## 1. Core Schema and DBMS Structure

The database `bracculs_hrec` is designed strictly following Relational Database Management System (RDBMS) normalization principles, utilizing 10 interconnected tables.

### The "IS-A" Inheritance Pattern
**Table:** `Users` & `Doctors`
**How & Why:** 
Instead of having a monolithic `Users` table with mostly empty columns for patients, or completely separate tables for Patients and Doctors, we use the **IS-A** pattern. 
* `Users` holds universal authentication data (Email, Password, Name).
* `Doctors` holds doctor-specific data (LicenseNo, Speciality).
* The `UserID` in the `Doctors` table acts as **both** the Primary Key and a Foreign Key linking to `Users.UserID`.

**Usage Example:**
```sql
SELECT u.FullName, d.Speciality 
FROM Users u 
JOIN Doctors d ON u.UserID = d.UserID;
```
* **Goods (Pros):** Highly normalized. Eliminates `NULL` bloat. Strict integrity (deleting the user automatically deletes the doctor profile via `ON DELETE CASCADE`).
* **Bads (Cons):** Requires a `JOIN` every time you need to fetch a doctor's full profile, which is slightly slower than querying a single wide table.

---

### Dynamic Calculations (Generated Columns)
**Table:** `Health_Records`
**How & Why:** 
BMI (Body Mass Index) changes whenever weight or height changes. Instead of calculating this in PHP or running an `UPDATE` query every time, the table uses a MySQL 5.7+ feature: **Generated Columns**.
```sql
BMI DECIMAL(4,1) GENERATED ALWAYS AS (ROUND(WeightKG / ((HeightCM / 100) * (HeightCM / 100)), 1)) STORED
```
* **Goods:** 100% data consistency. The backend PHP never has to remember to calculate or update the BMI. `STORED` means it is calculated on insert/update and physically saved, making `SELECT` queries fast.
* **Bads:** Uses slightly more disk space than a `VIRTUAL` generated column. The formula is hardcoded into the DB schema; changing the formula requires an `ALTER TABLE`.

---

### JSON Payload Storage
**Table:** `Health_Records` & `Brands` & `Prescriptions`
**How & Why:**
Allergies, chronic conditions, medication ratings, and prescription items are stored as `JSON` columns rather than rigid relational structures.
```sql
KnownAllergies JSON,
ChronicConditions JSON
```
**Usage Example:**
```sql
-- Checking if a patient has a specific allergy
SELECT * FROM Health_Records WHERE JSON_CONTAINS(KnownAllergies, '"Penicillin"');
```
* **Goods:** Highly flexible. A prescription can have 1 or 10 items without needing a massive `Prescription_Items` child table. Perfect for rapidly prototyping complex datasets.
* **Bads:** Harder to index. Prior to MySQL 8.0, querying inside JSON arrays is slow on massive tables because standard B-Tree indexes cannot index JSON array elements efficiently.

---

## 2. Advanced Queries: The CDSS Alert Engine

The core of the Clinical Decision Support System (CDSS) relies on a massive SQL `VIEW` rather than backend application code.

### The `CDSS_Active_Conflicts` VIEW
**How & Why:**
Instead of pulling all a patient's medications into PHP and running nested `foreach` loops to find conflicts (which is memory-heavy and slow), the DB does the heavy lifting via a self-joining `VIEW`.

**The Query:**
```sql
CREATE OR REPLACE VIEW CDSS_Active_Conflicts AS
SELECT ...
FROM USER_MED_LOG uml1
    JOIN Brands b1 ON uml1.BrandID = b1.BrandID
    JOIN Generics g1 ON b1.GenericID = g1.GenericID
    JOIN USER_MED_LOG uml2 ON uml1.UserID = uml2.UserID AND uml1.LogID < uml2.LogID
    JOIN Brands b2 ON uml2.BrandID = b2.BrandID
    JOIN Generics g2 ON b2.GenericID = g2.GenericID
    JOIN Drug_Conflicts dc
        ON (dc.GenericID_1 = g1.GenericID AND dc.GenericID_2 = g2.GenericID)
        OR (dc.GenericID_1 = g2.GenericID AND dc.GenericID_2 = g1.GenericID)
WHERE uml1.Status = 'Active' AND uml2.Status = 'Active';
```
* **How it works:** It takes the medication log (`uml1`) and joins it to ITSELF (`uml2`) for the same user (`uml1.UserID = uml2.UserID`). It uses `uml1.LogID < uml2.LogID` to prevent comparing a drug against itself or generating duplicate A-B and B-A pairs. It then checks if those two generics exist in the `Drug_Conflicts` table.
* **Goods:** Incredible performance compared to application-side logic. The DB engine optimizes the execution plan. The frontend simply runs `SELECT * FROM CDSS_Active_Conflicts WHERE PatientID = 1`.
* **Bads:** The query is complex and computationally expensive. As the `USER_MED_LOG` grows to millions of rows, this View will become a bottleneck unless materialized or heavily indexed.

---

## 3. Automation via Triggers

Triggers act as the "reactive" muscle of the database.

### `After_Med_Log_INSERT` Trigger
**How & Why:**
When a doctor prescribes a drug, or a patient logs a new medication, the system MUST instantly warn them if it conflicts with their current meds. 

**The Query:**
```sql
CREATE TRIGGER After_Med_Log_INSERT
AFTER INSERT ON USER_MED_LOG
FOR EACH ROW ...
```
Inside this trigger, a **Cursor** is used:
```sql
DECLARE conflict_cursor CURSOR FOR SELECT ... FROM Drug_Conflicts ...
```
* **How it works:** After a new row is inserted into `USER_MED_LOG` (`NEW.BrandID`), the trigger opens a cursor to find any existing active medications for that user that conflict with this new drug. If a conflict is found, it automatically inserts a warning into the `Medical_Flags` table.
* **Goods:** Zero-trust architecture. Even if a developer forgets to write the conflict-checking logic in the PHP backend, the database guarantees the alert will be generated.
* **Bads:** Cursors process row-by-row and are notorious for being slow in MySQL. If a patient has 50 active medications, the cursor looping can slow down the `INSERT` operation significantly.

### Security Audit Logging
**How & Why:**
`Before_User_Delete_Audit` and `After_Prescription_Audit` automatically log actions.
```sql
INSERT INTO SECURITY_AUDIT_LOG (UserID, Action, TableName, RecordID, Details)
VALUES (OLD.UserID, 'USER_DELETED', 'Users', OLD.UserID, ...);
```
* **Goods:** Tamper-proof auditing. A malicious user or rogue script deleting data via a raw SQL query will still trigger the audit log.
* **Bads:** Write-heavy operations incur double the I/O cost, as every `INSERT/DELETE` requires a secondary write to the audit table.

---

## 4. ACID Compliance (PHP Side integration)

While mostly SQL, the project relies on **Transactions** in PHP (`pharmacy.php`) when interacting with `setup.sql` schema constraints.

**How & Why:**
When a pharmacist fulfills a prescription, two things must happen:
1. Deduct `Brands.Stock`
2. Update `Prescriptions.Status` to 'Fulfilled'.

If the stock deduction fails (e.g., due to the `CHECK (Stock >= 0)` constraint in `setup.sql`), the status update must not happen.
```php
$pdo->beginTransaction();
// 1. Update Stock
// 2. Update Prescription Status
$pdo->commit();
// On Exception -> $pdo->rollBack();
```
* **Goods:** Prevents partial database updates (data corruption). Ensures inventory numbers perfectly match fulfillment records.
* **Bads:** Holds row locks longer than individual queries. In high-concurrency environments (hundreds of pharmacists working simultaneously), this can cause lock-wait timeouts.
