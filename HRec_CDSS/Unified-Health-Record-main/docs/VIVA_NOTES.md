# Viva Support Notes

This document is designed to help you prepare for the CSE370 Viva presentation. It contains a structured demo flow, key talking points, and model answers for likely teacher questions.

## 🎤 1-Minute Opening Explanation
"Good [morning/afternoon]. Our project is the Unified Health Record (HRec), a centralized database and Clinical Decision Support System. Instead of focusing heavily on a frontend framework, we designed a strict 'DBMS-First' architecture using native MySQL and PDO. Our goal today is to demonstrate robust relational concepts—including IS-A inheritance, Third Normal Form normalization, complex `JOIN` aggregation, and ACID transactions—while highlighting how we offloaded critical clinical logic directly to the database layer using Views and Triggers."

## ⏱️ 5–8 Minute Demo Flow

**1. Data Modeling & Auto-Calculations (1.5 mins)**
* **Log in:** As `patient1@hrec.test`. Go to `patient_dashboard.php`.
* **Talking Point:** Explain the 1:1 relationship for Health Records. Point out the BMI calculation. Mention it is done using `GENERATED ALWAYS AS` in MySQL, ensuring the database handles data integrity, not the PHP.
* **Transition:** Navigate to **Medication Log** and then **Allergy Check**.
* **Talking Point:** Contrast M:N bridge tables vs JSON. Show `USER_MED_LOG` as a bridge carrying payload data. Note the practical tradeoff of using a `JSON` array for simple flat lists like allergies.

**2. Complex Querying & Subqueries (1.5 mins)**
* **Log in:** As `doctor@hrec.test`. Go to `doctor_overview.php`.
* **Talking Point:** Show the patient list. Emphasize that the data is pulled using a single `LEFT JOIN` combined with inline subqueries (to count active meds and flags), completely avoiding the N+1 query problem.

**3. ACID Transactions (2 mins)**
* **Action:** Go to **Prescribe** (Doctor), prescribe a drug. Then log in as `pharma@hrec.test`, go to **Fulfillment**.
* **Talking Point:** This is the climax. Explain the `BEGIN` and `COMMIT` block. When fulfilling, the system checks stock, decrements stock, updates the prescription, and writes to an audit log. If the stock hits 0, it violates a database constraint and the PHP script safely calls `ROLLBACK`.

**4. Views & Triggers (1.5 mins)**
* **Action:** Navigate to **CDSS Alerts** (as Doctor or Patient).
* **Talking Point:** Show the active conflicts. Explain that the PHP code simply queries a massive SQL `VIEW` (`CDSS_Active_Conflicts`). The alerts themselves were generated instantly by a database `TRIGGER` the moment the conflicting drug was inserted into the database.

## 🎤 30-Second Closing Explanation
"To summarize, HRec proves that a well-architected relational database can do much more than just store data. By enforcing strict constraints, wrapping multi-table operations in ACID transactions, and utilizing native triggers and views for our alert engine, we built a system where data integrity and clinical logic are guaranteed at the lowest level, preventing application-layer errors."

---

## ❓ Strongest 10 Viva Questions & Direct Answers

**1. Q: How did you implement the IS-A inheritance from your ER diagram?**
> **A:** "We used `Users` as the superclass. The `Doctors` table uses `UserID` as both its Primary Key and its Foreign Key pointing to `Users.UserID`. This strictly enforces 1:1 inheritance; deleting a User cascades to delete the Doctor profile."

**2. Q: What is an ACID transaction and where did you use it?**
> **A:** "ACID ensures database operations are atomic and isolated. We used PDO `BEGIN` and `COMMIT` during pharmacy fulfillment. If stock decrementing fails due to a constraint, the prescription status update `ROLLBACK`s, preventing data corruption."

**3. Q: Why use a View for the CDSS conflicts instead of just a PHP query?**
> **A:** "The CDSS engine requires a self-join on the medication log, plus joining generics and conflict rules. Hardcoding this in PHP is messy. Using a `VIEW` allows the database optimizer to handle the complex joins natively, while our PHP simply runs a clean `SELECT * FROM CDSS_Active_Conflicts`."

**4. Q: How do your Triggers work?**
> **A:** "We use an `AFTER INSERT` trigger on the `USER_MED_LOG`. When a new drug is logged, the trigger opens a cursor to check against existing active medications. If a conflict from the rules table is found, it automatically inserts a warning into `Medical_Flags`."

**5. Q: How did you prevent the N+1 query problem on the Doctor Overview page?**
> **A:** "Instead of querying the users, and then running a loop to query each user's medication count, we used a single query with a `LEFT JOIN` for health records and inline `SELECT COUNT(*)` subqueries to fetch the aggregates efficiently."

**6. Q: Are there any SQL Injection vulnerabilities in your project?**
> **A:** "No. Every query that accepts user input utilizes PHP PDO Prepared Statements (`prepare()` and `execute()`). The variables are bound securely at the database engine level."

**7. Q: How is the patient's BMI calculated?**
> **A:** "It is not calculated in PHP. We used a MySQL 5.7+ feature: `GENERATED ALWAYS AS`. The database mathematically calculates the BMI based on the Height and Weight columns and stores it physically upon insert."

**8. Q: (Tradeoff) Why did you use a JSON column for allergies instead of full 3NF normalization?**
> **A:** *(Honest Answer)* "Full 3NF would require a `Patient_Allergies` bridge table. However, since allergies here are flat, simple strings rarely queried independently, we used a MySQL JSON array. It is a practical MVP tradeoff that simplifies development, though we acknowledge it sacrifices B-Tree indexing speed."

**9. Q: (Tradeoff) What is the downside of using Cursors inside your Triggers?**
> **A:** *(Honest Answer)* "Cursors process row-by-row, which is slow in MySQL. We used them to guarantee data integrity at the DB level, but in a massive production environment with millions of rows, this I/O overhead would bottleneck inserts, and we would likely move that logic to an asynchronous background worker."

**10. Q: (Tradeoff) What is the risk of your ACID transaction during Pharmacy Fulfillment?**
> **A:** *(Honest Answer)* "Wrapping multiple `UPDATE`s in a transaction holds row-level locks. While it guarantees our inventory never goes negative (preventing phantom reads), in a high-concurrency environment with hundreds of pharmacists, it could cause temporary lock-wait timeouts."
