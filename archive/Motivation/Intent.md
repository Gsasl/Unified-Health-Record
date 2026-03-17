# Unified Health Record (HRec) - Project Intent

## 1. Project Overview
The **Unified Health Record (HRec)** system is a smart healthcare management solution designed to bridge the gap between Patients (Users), General Practitioners (GPs), Specialists (SPs), and Pharmacies (Pharma).

## 2. Core Features & Logic
* **Smart Health Records:** Users input medication history and stock. The system generates "System Remarks" for black-box warnings or medicine conflicts.
* **Gatekeeper GP Interaction:** Doctors (GPs) monitor anonymized patient records for routine anomalies.
* **Specialist Referral (SP):** The GP refers users to an SP if a specific condition is suspected. The SP is defined by a unique `Specialization_ID`.
* **Intelligent Pharmacy Management:** Pharmacies track generic-based stock and receive automated alerts for shortages.
* **Financial Tracking:** * Generate sales receipts and monthly expense logs for Users.
    * Calculate Pharma Revenue: **Net = Gross - (5% Logistics + 5% VAT)**.

## 🛠️ Lab ER Outline
| Entity | Primary Attributes | Relationship |
| :--- | :--- | :--- |
| **User** | UserID, Name, Expense_Log | Owns Records & Stock |
| **GP (Doctor)** | DoctorID, License_No | Monitors records; Issues referrals |
| **SP (Specialist)**| DoctorID, **Specialization_ID** | Receives referrals from GP |
| **Health_Record** | RecordID, Symptoms, Remarks | Reviewed by GP for anomalies |
| **Pharma** | PharmaID, Stock, Revenue | Manages inventory & financial math |

## 🧪 Simulation Scope
* **Data Source:** Crawled via Python+BS4 from `medex.com.bd`.
* **Sample Size:** 10–12 generic medications across variety types.
* **Tech Stack:** MySQL DB , PHP for interface.