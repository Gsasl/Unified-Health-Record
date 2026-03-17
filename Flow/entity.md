# Unified Health Manager - Database Implementation & Cardinality

This document defines the strict relational database implementation and cardinality rules for the Unified Health Manager system.

## 1. Cardinality Rules
These rules govern how data interacts across the system to ensure integrity.

* **Patient ↔ Health Record:** `1:1` (Mandatory Lifetime Record. Enforced via Unique FK).
* **Pharma ↔ Inventory:** `1:1` (Each pharmacy branch manages one central inventory ledger).
* **Doctor / Specialist ↔ Health Record:** `1:N` (A doctor reviews many patient records over time).
* **Health Record ↔ Diagnostics / Recommendations:** `1:N` (One record accumulates many test results and system-generated advice logs).
* **Pharma ↔ Prescription:** `1:N` (A pharmacy fulfills multiple prescriptions).
* **Medication ↔ Medical Flag:** `1:N` (A specific drug can trigger multiple warnings across different patients).
* **Prescription ↔ Medication:** `M:N` (A single prescription can contain multiple drugs, and a drug can be on multiple prescriptions. Handled via bridge table).

---

## 2. Database Implementation Table
*Note: **PK** = Primary Key, **FK** = Foreign Key. Foreign keys are critical for enforcing the cardinality rules above.*

| Entity | Attributes (Columns) |
| :--- | :--- |
| **Patients** | **PatientID (PK)**, Name, Cloud_Access_Status |
| **Health_Records** | **RecordID (PK)**, *PatientID (FK - Unique)*, Anony_ID, Online_Report_URL |
| **General_Doctors** | **DoctorID (PK)**, RX_Oversight_Status, Async_Enabled |
| **Specialists** | **SpecID (PK)**, Handles_Complex_Cases |
| **Pharma** | **PharmaID (PK)**, Branch_Name |
| **Medications** | **MedID (PK)**, Generic_Type, Duration_Days, Blackbox_Warn, Stop_If_Condition, Pop_Price_Score |
| **Prescriptions** | **PrescriptionID (PK)**, *PatientID (FK)*, *DoctorID (FK)*, *PharmaID (FK)*, Instant_Order |
| **Prescription_Items** *(Bridge)* | **ItemID (PK)**, *PrescriptionID (FK)*, *MedID (FK)* |
| **Inventory** | **InventoryID (PK)**, *PharmaID (FK - Unique)*, Avail_Stock, Demand_Metric, Dropship_Eligible |
| **Diagnostics** | **DiagID (PK)**, *RecordID (FK)*, Blood_Pressure, USG_Result, X_Ray_Result, Date_Logged |
| **Recommendations** | **RecID (PK)**, *RecordID (FK)*, Diet_Warning, Test_Freq_Days, Date_Generated |
| **Medical_Flags** | **FlagID (PK)**, *RecordID (FK)*, *MedID (FK)*, *DoctorID (FK)*, Combo_Danger, EMS_Required, Status |

---
*Architecture mapping aligned with Flow/plan.md.*