# Screenshot Plan for Report & Presentation

Follow this guide to capture clean, professional, and academically relevant screenshots for your CSE370 report and viva slides.

## 📸 General Screenshot Discipline
* **Window Size:** Resize your browser to a standard desktop width (e.g., 1440x900) before capturing.
* **Crop Intelligently:** Do not capture your entire desktop, taskbar, or browser tabs. Crop tightly to the browser viewport or the specific application card being discussed.
* **Consistency:** Ensure the sidebar is either always open or always closed (preferably open to show navigation context) across all full-page captures.

---

## 1. CDSS Alert Engine (The Flagship Feature)
* **Page:** `cdss_alerts.php`
* **Pre-state:** Log in as a Patient. Ensure at least one `CRITICAL` or `HIGH` drug interaction flag is active.
* **Capture Area:** The main content area, specifically framing the "DBMS Concept Demo" panel at the top, followed immediately by the red "Active Drug Conflicts" alert box.
* **Target Destination:** Viva Slides (Climax slide) & Report (Features section).
* **Caption Suggestion:** *Figure X: The CDSS Alert Engine displaying real-time drug conflicts generated via MySQL Triggers and queried through the `CDSS_Active_Conflicts` View.*

## 2. Medication Bridge Table Payload
* **Page:** `medication_log.php`
* **Pre-state:** Log in as a Patient. Have at least 2 active medications and 1 discontinued medication visible in the table.
* **Capture Area:** Crop to the "Your Medications" table card, ensuring the "Status", "Date Added", and "Prescribed By" columns are clearly visible.
* **Target Destination:** Report (Normalization / Database Design section).
* **Caption Suggestion:** *Figure Y: The patient medication log, visualizing the payload attributes (Status, Dates) stored within the M:N `USER_MED_LOG` bridge table.*

## 3. ACID Transaction: Prescription Generation
* **Page:** `prescribe.php`
* **Pre-state:** Log in as a Doctor. Fill out the prescription form but do not submit.
* **Capture Area:** The "Generate Prescription" form and the adjacent "DBMS Concept Demo" panel explaining the transaction.
* **Target Destination:** Report (Backend Development section).
* **Caption Suggestion:** *Figure Z: The prescription generation interface, which utilizes strict PDO transactions (`BEGIN/COMMIT`) to ensure atomicity across the `Prescriptions` and `USER_MED_LOG` tables.*

## 4. Complex Query Aggregation
* **Page:** `doctor_overview.php`
* **Pre-state:** Log in as a Doctor.
* **Capture Area:** The "Patient Directory" table, specifically highlighting the "Active Meds" and "Unresolved Flags" badge columns.
* **Target Destination:** Viva Slides (Query optimization slide).
* **Caption Suggestion:** *Figure W: The Doctor Overview dashboard, demonstrating efficient data retrieval using `LEFT JOIN` and subqueries to aggregate medical counts without N+1 query overhead.*

## 5. ACID Transaction: Pharmacy Fulfillment
* **Page:** `pharmacy.php`
* **Pre-state:** Log in as a Pharmacist. Ensure there is at least one "Pending" prescription visible.
* **Capture Area:** The "Prescriptions" table showing the green "Fulfill" button and the "Low Stock Alert" card.
* **Target Destination:** Report (Conclusion / Concurrency section).
* **Caption Suggestion:** *Figure V: The pharmacy fulfillment queue, where stock decrement logic is safeguarded by rollback constraints during concurrent transaction processing.*
