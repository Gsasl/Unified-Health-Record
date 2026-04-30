# CSE370 Project Report Draft: Unified Health Record (HRec)

*(This document provides pre-written content aligned with the CSE370 report template. Copy and paste these sections into your final Microsoft Word report, ensuring you manually add your actual ER and Schema diagrams where indicated).*

---

## 1. Introduction
The Unified Health Record (HRec) is an interconnected web platform and Clinical Decision Support System (CDSS). The core objective of this project is to centralize patient medical data, bridging the gap between Patients, Doctors, and Pharmacists. By utilizing a strictly normalized relational database, HRec eliminates fragmented medical histories and actively monitors for dangerous drug-to-drug interactions using advanced SQL techniques like Views, Triggers, and ACID transactions. 

## 2. Project Features
HRec demonstrates several robust database-driven features:
* **Role-Based Workflows:** Distinct interfaces for Patients, Doctors, and Pharmacists, heavily relying on the IS-A inheritance schema design.
* **Auto-Calculation:** Patient BMI is calculated dynamically at the database level using generated columns, ensuring perfect data consistency without backend logic.
* **ACID-Compliant Prescription Fulfillment:** Stock levels and prescription statuses are updated securely via PDO transactions, rolling back automatically upon constraint violations.
* **Clinical Decision Support System (CDSS):** An automated engine utilizing a comprehensive SQL `VIEW` and insertion `TRIGGERS` to detect and flag high-risk drug interactions in real-time.

## 3. ER/EER Diagram Explanation
*(Insert your ER diagram image here)*

Our Entity-Relationship architecture utilizes an **Enhanced ER (EER)** model, specifically demonstrating the **IS-A (Inheritance)** relationship. The `Users` entity acts as a superclass, containing universal attributes (Email, Password Hash, Role). The `Doctors` entity acts as a subclass. In the physical implementation, the `UserID` in the `Doctors` table acts as both the Primary Key and the Foreign Key referencing `Users.UserID`. 

Additionally, the system utilizes a many-to-many (M:N) relationship between `Users` (Patients) and `Brands` (Medications). This is resolved via the `USER_MED_LOG` bridge entity, which carries vital payload attributes such as `Status`, `PrescribedBy`, and `DateAdded`.

## 4. Schema Diagram Explanation
*(Insert your Schema diagram image here)*

The physical schema implements the aforementioned ER model using 10 interconnected tables. Referential integrity is strictly enforced using Foreign Keys with `ON DELETE CASCADE` constraints where appropriate (e.g., deleting a User automatically drops their Health_Records). The schema notably includes a self-referencing bridge table, `Drug_Conflicts`, which links the `Generics` table to itself to establish the rules engine for the CDSS.

## 5. Normalization
The core database architecture adheres strictly to the **Third Normal Form (3NF)**.
* **1NF:** All core tables contain atomic values. Repeated groups were eliminated by extracting data into bridge tables like `USER_MED_LOG` and `Prescriptions`.
* **2NF:** All non-key attributes are fully functionally dependent on the primary key.
* **3NF:** Transitive dependencies were removed. For example, specific drug manufacturer details are kept in the `Brands` table rather than being redundantly stored every time a drug is prescribed in the `USER_MED_LOG`.

**Practical Tradeoffs (JSON Implementation):**
While the core architecture is heavily normalized, a deliberate, practical exception was made for the `KnownAllergies` attribute in the `Health_Records` table. Instead of creating a separate, fully normalized `Patient_Allergies` bridge table, allergies are stored using a MySQL `JSON` array. Since allergies in this MVP context are simple, flat lists that are rarely queried independently for complex aggregation, the JSON column serves as a practical compromise, speeding up development and reducing excessive `JOIN` operations, while acknowledging the tradeoff in B-Tree indexing speed.

## 6. Frontend Development
The frontend was developed using vanilla HTML, CSS, and JavaScript. It serves strictly as a presentation layer for the underlying database concepts. To ensure maximum clarity during academic demonstrations, the UI avoids complex frontend frameworks (like React or Vue) and instead implements a custom "DBMS Panel" component on every page. This panel dynamically acts as a cheat sheet, explaining the active SQL queries, tables, and concepts operating on that specific view.

## 7. Backend Development
The backend is built with native PHP 8, utilizing the PHP Data Objects (PDO) extension to interact with the MySQL database. 
Key backend methodologies include:
* **Prepared Statements:** 100% of user-supplied data is passed through prepared statements to completely neutralize SQL injection vulnerabilities.
* **Transaction Management:** Critical workflows (like prescription generation and pharmacy stock fulfillment) are wrapped in `$pdo->beginTransaction()`, `$pdo->commit()`, and `$pdo->rollBack()` to guarantee ACID compliance.

## 8. Source Code Repository
The complete source code, including the `setup.sql` master database dump, Python scraping scripts used for seed data generation, and all PHP/HTML assets, are available in the project directory. 

## 9. Conclusion
The Unified Health Record successfully demonstrates the power of a "DBMS-First" architecture. By offloading logic to the database layer—using Generated Columns for math, Views for complex `JOIN`s, Triggers for automation, and Transactions for concurrency control—HRec ensures robust data integrity and performance. The project fulfills all CSE370 requirements, illustrating theoretical concepts like Normalization and Inheritance through practical, clinical application.
