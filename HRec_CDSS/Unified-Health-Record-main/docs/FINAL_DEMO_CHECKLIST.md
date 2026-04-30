# Final Presentation Demo Checklist

Use this checklist 15 minutes before your viva to ensure a flawless execution. A good demo focuses tightly on the core message and avoids unexpected technical errors.

## 🛠️ Environment Pre-Flight
- [ ] **XAMPP Running:** Both Apache and MySQL modules are started and green in the XAMPP control panel.
- [ ] **Database Intact:** Open phpMyAdmin and verify `bracculs_hrec` exists with exactly 10 tables.
- [ ] **Data Seeded:** Check the `Users` table to ensure `patient1@hrec.test`, `doctor@hrec.test`, and `pharma@hrec.test` exist.
- [ ] **Browser Prepared:** Close all unrelated tabs. Open `http://localhost/Unified-Health-Record-main/`. Resize the window to a clean 16:9 aspect ratio.
- [ ] **Zoom/Display Ready:** If projecting or screen-sharing, ensure your display scaling doesn't hide the sidebar or bottom UI.

## 👥 Demo Accounts (Keep handy)
* **Patient:** `patient1@hrec.test` / `patient123`
* **Doctor:** `doctor@hrec.test` / `doctor123`
* **Pharmacist:** `pharma@hrec.test` / `pharma123`

## 🎬 Core Demo Sequence (5–8 minutes)
1. **Patient Login:** Show `patient_dashboard.php` (Explain BMI Generated Column).
2. **Patient Nav:** Show `medication_log.php` and `allergy_check.php` (Explain Bridge Payload vs JSON array).
3. **Doctor Login:** Show `doctor_overview.php` (Explain `LEFT JOIN` and subqueries).
4. **Doctor Action:** Prescribe a medication on `prescribe.php` (Explain `BEGIN/COMMIT` ACID transaction).
5. **Pharmacist Login:** Show `pharmacy.php` (Fulfill the prescription, explain constraint Rollbacks).
6. **Alert Check:** Show `cdss_alerts.php` (Explain Views and Triggers).

## 🚨 Fallback Plan
* **"The password isn't working!"**
  * *Fix:* Run `http://localhost/Unified-Health-Record-main/setup_passwords.php` in a new tab immediately, then try again.
* **"The database crashed / tables missing!"**
  * *Fix:* Have `setup.sql` ready on your desktop. Drop the existing database in phpMyAdmin and re-import `setup.sql`. It takes 5 seconds to reset everything perfectly.
* **"A page isn't loading / White Screen!"**
  * *Fix:* Check XAMPP to see if Apache crashed. Restart Apache. If it's a PHP error, politely explain: *"It appears there's a minor session mismatch, let me just re-authenticate,"* and click the Logout button or refresh `index.php`.
