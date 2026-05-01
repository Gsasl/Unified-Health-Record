# Project Rationale & In-Depth Architecture

This document provides a comprehensive overview of the **Unified Health Record (HRec)** platform, detailing design choices, SQL implementations, system drawbacks, and data flow. It acts as a guide for novice viewers and developers looking to understand the core logic of the system.

## 1. Introduction & Project Insights
The Unified Health Record (HRec) is an interconnected web platform and Clinical Decision Support System (CDSS) built as a complete database course (CSE370) project. It bridges the gap between Patients, Doctors, and Pharmacists by keeping a single source of truth for medical records, while actively monitoring for dangerous drug-to-drug interactions.

Instead of isolated prescription pads and scattered medical histories, HRec uses a centralized relational database architecture to achieve Data Integrity, Transaction Security (ACID), and Real-time Clinical Rules Processing.

## 2. Critical Build Guide
To successfully run this project locally, follow these precise steps:
1. **Environment**: Install XAMPP. Ensure both Apache and MySQL modules are running.
2. **Database Provisioning**:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Import the `setup.sql` file. This is the master script that creates the `bracculs_hrec` database, all 10 tables, the CDSS View, 3 Triggers, and populates the system with necessary seed data.
3. **App Deployment**:
   - Place this entire repository folder inside `C:\xampp\htdocs\`.
   - Access the platform via `http://localhost/Unified-Health-Record-main/`.
4. **Password Initialization**:
   - The demo user hashes in the SQL are pre-computed. However, you can register new users through the newly implemented `register.php` page, which handles role assignments and bcrypt hashing automatically.

## 3. Data Acquisition and Filtering
Real-world clinical data ensures the CDSS is authentic.
- **Acquisition Tool**: `tools/medex_scraper.py`
- **Methodology**: The custom Python script utilizes `requests` and `BeautifulSoup` to scrape drug data directly from MedEx (Bangladesh's premier online medicine index). 
- **Filtering**: The script filters HTML DOM elements looking for specific clinical headings (e.g., 'Indications', 'Interaction', 'Precautions & Warnings'). It extracts this critical text and links commercial *Brands* to chemical *Generics*. It avoids storing massive unformatted HTML by cleaning and concatenating the text into pure SQL `INSERT` statements outputted to `seed_data.sql`.

## 4. DBMS Usage & Schema Design
The project relies on a strictly typed, fully normalized MySQL relational database consisting of 10 tables.

**Key Design Choices:**
- **IS-A Inheritance Pattern**: The `Users` table acts as the superclass. The `Doctors` table uses `UserID` simultaneously as its Primary Key and Foreign Key, meaning a Doctor *is a* User. Deleting the User cascades to delete the Doctor profile.
- **1:1 Relationships**: `Health_Records` is linked 1:1 with `Users` through a `UNIQUE` constraint on the FK.
- **Self-Referencing Bridge**: The `Drug_Conflicts` table links `Generics` to `Generics`, establishing clinical rules (e.g., Generic A + Generic B = High Risk).
- **M:N Bridge with Payload**: `USER_MED_LOG` connects `Users` to `Brands` but carries additional payload data like `Status` and `DateAdded` to maintain a patient's historical medical timeline.

## 5. Feature Implementations & SQL Queries
The system implements 7 core features, heavily leaning on advanced SQL capabilities rather than PHP logic:

1. **Data Modeling (patient_dashboard.php)**: Demonstrates 1:1 relationships (Users to Health_Records) and utilizes a MySQL `GENERATED ALWAYS AS` column to calculate BMI instantly on the database level.
2. **M:N Bridge with Payload (medication_log.php)**: Resolves the many-to-many relationship between Users and Brands using the `USER_MED_LOG` bridge table, which carries "payload" columns like Status and PrescribedBy.
3. **JSON Storage Tradeoffs (allergy_check.php)**: Contrasts fully normalized tables with practical `JSON` arrays in MySQL. It explains why a flat list of allergies is stored as JSON to avoid excessive child-table joins, while acknowledging the tradeoff in indexing speed.
4. **Complex LEFT JOINs (doctor_overview.php)**: Uses `LEFT JOIN` to fetch optional 1:1 records (Health Records) while simultaneously using subqueries to count active medications and unresolved flags in a single query, preventing N+1 query problems.
5. **ACID Transactions - Insert (prescribe.php)**: Implements strict PDO Transactions (`BEGIN`, `COMMIT`, `ROLLBACK`) to ensure that a prescription is safely added to the `Prescriptions` table and the patient's active medication log simultaneously.
6. **ACID Transactions - Update (pharmacy.php)**: Demonstrates concurrency and stock control. If a stock deduction fails during fulfillment, the prescription status update rolls back, preventing inventory desynchronization.
7. **Views & Triggers (cdss_alerts.php)**: The heart of the platform. Instead of complex PHP loops, it queries a massive SQL `VIEW` (`CDSS_Active_Conflicts`) containing multiple `JOIN`s comparing active medications. The underlying triggers automatically insert a `CRITICAL` flag into `Medical_Flags` if a conflict exists.

## 6. Drawbacks & Future Work
While robust, the architecture has known limitations:
- **No Real-Time Sockets**: If a doctor prescribes a conflicting drug, the pharmacist won't see the CDSS alert until they refresh the page. Implementing WebSockets (e.g., Ratchet/PHP or Node.js) would enable push notifications.
- **Monolithic Scaling**: PHP and MySQL are tightly coupled. In a massive clinical rollout, separating the CDSS engine into an isolated microservice (e.g., Golang) would prevent the main database from bottlenecking during heavy JOIN operations.
- **JSON Query Indexing**: Storing allergies as JSON is convenient for MVP development but is slower to query than a traditional normalized many-to-many table. High-volume searches for specific allergic reactions would require MySQL 8.0+ Multi-Valued Indexes.
