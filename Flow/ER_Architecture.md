cat << 'EOF' > ER_Architecture.md
# 🏗️ Unified Health Manager: Database Architecture & ER Diagram

![Final ER Diagram](er_final.png) 
*(Note: Conceptual Database Blueprint)*

---

## 1. Database Architecture Documentation

### Entities & Attributes
| Entity | Type | Description | Attributes |
| :--- | :--- | :--- | :--- |
| **Patient** | Core | The user receiving care. | id (PK), name, address, cloud_access |
| **General DOCTOR** | Core | Primary care physician. | ID (PK), rx_oversight, no_schedule_hassle |
| **Specialist** | Core | Secondary care physician. | id (PK), speciality |
| **Pharma** | Core | Pharmacy branch/pharmacist. | id (PK), branch_name |
| **Health Record** | Data | Central patient profile. | id (PK), age (derived), DOB, Sex, weight, height, BMI, ailments |
| **Medication** | Data | Master drug catalog. | id (PK), generic, duration, blackbox_warn, stop_if, pop_price |
| **Prescription** | Transaction | Doctor's drug order. | id (PK), instant_order |
| **Inventory** | Tracker | Pharmacy stock levels. | id (PK), avail_stock, demand |
| **Medical Flag** | Alert | CDSS system warnings. | id (PK), combo_danger, ems_required |
| **Recommendation** | Output | Automated/Manual advice. | id (PK), warning, test_freq, sp_requirement |

### Key Relationships & Cardinalities
| Entity A (Origin) | Relationship | Entity B (Target) | Cardinality | Business Logic Implication |
| :--- | :--- | :--- | :--- | :--- |
| Patient | **owns** | Health Record | 1 : 1 | A patient has one lifetime master record. |
| Patient | **uploads** | Health Record | 1 : N | Patient can log multiple updates/data points over time. |
| General DOCTOR | **Reviews** | Health Record | 1 : N | GPs manage multiple patient profiles. |
| General DOCTOR | **Raises** | Medical Flag | 1 : N | Doctors can manually trigger severe system alerts. |
| General DOCTOR | **refers to** | Specialist | M : N | GPs send complex cases to specialists. |
| Health Record | **prescribed in** | Medication | M : N | The active medication log for a specific patient. |
| Health Record | **generates** | Recommendation | 1 : N | CDSS analyzes the record to output advice. |
| Medication | **Trigger warning** | Medical Flag | 1 : N | Specific drugs/combos automatically set off alerts. |
| Pharma | **fulfills** | Prescription | 1 : N | Pharmacies clear multiple prescription orders. |

---

## 2. Implementation Scope (1-Week MVP)

To ensure a high-quality, fully functional demonstration within a constrained 1-week development cycle, the physical implementation focuses strictly on the **"Golden Thread"** of the architecture: **The Clinical Decision Support System (CDSS).**

### 🟢 Actively Implemented (The Core Loop)
* **Patient Portal (`user_dashboard.php`):** Full CRUD capability for creating a 1:1 lifetime master health record and maintaining a personal active medication log.
* **Doctor Dashboard (`gp_dashboard.php`):** Interface to access patient records and view system-generated CDSS alerts.
* **CDSS Automation Engine (SQL/PHP):** * **Conflict Detection:** Monitors the `prescribed in` bridge table to instantly trigger "Combo Danger" or "Blackbox" Medical Flags if dangerous drug interactions are detected.
  * **Data Pipeline (`medex_scraper.py`):** An automated Python ETL script that pulls real-world pharmaceutical data, transforms it into Boolean flags, and seeds the MySQL `Medication` catalog.

### 🟡 Mocked / Future Scope (To bypass UI bloat)
While the conceptual database fully supports these entities, their frontend UIs are bypassed for the MVP demonstration to prioritize the CDSS logic:
* **Doctor/Specialist/Pharma Auth:** User roles are handled via a simple session toggle rather than a complex registration system.
* **Specialist Referrals:** The logic exists in the DB, but the UI messaging system between doctors is deferred.
* **Inventory Logistics:** Live stock deduction and dropshipping logic are deferred to Phase 2.
EOF

echo "✅ Updated ER_Architecture.md with Implementation Scope!"