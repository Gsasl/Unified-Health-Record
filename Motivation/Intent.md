# Unified Health Record (HRec) - Project Intent

## 1. Project Overview
The **Unified Health Record (HRec)** system is a smart healthcare management solution designed to bridge the gap between patients (Users), General Practitioners (GPs), and Pharmacies (Pharma). The core objective is to move beyond simple record-keeping and toward active medical safety and supply-chain efficiency.

## 2. Core Features & Logic
* **Smart Health Records:** Users input medication history, diagnosis reports, and stock. The system generates "System Remarks" to flag potential black-box warnings or medicine conflicts.
* **Anonymized GP Interaction:** Doctors (GPs) monitor patient records periodically without compromising identity. They manage referrals to Specialists (SP).
* **Intelligent Pharmacy Management:** Pharmacies track generic-based stock and receive automated alerts for shortages based on real-time user demand.
* **Medication Efficacy Rating:** A unique rating system (Efficacy vs. Price vs. Popularity). If a high-rated medicine is out of stock, the system queries the GP before suggesting a lower-rated alternative.
* **Financial Tracking:** Generation of digital sales receipts and automated revenue calculation for pharmacies (deducting **5% Logistics** and **5% VAT**).

## 3. Technical Stack
* **Backend:** MySQL (10–12 complex queries).
* **Frontend:** PHP/HTML/CSS.
* **Data Acquisition:** Python Scraper (BeautifulSoup) for `medex.com.bd`.
* **UI/UX Inspiration:** `arogga.com` and `shasthosheba.com`.

## 🛠️ Lab ER Outline
| Entity | Primary Attributes | Relationship |
| :--- | :--- | :--- |
| **User** | UserID, Name, Expense_Log | Owns Health Records & Stock |
| **Doctor (GP)** | DoctorID, Specialization | Monitors Health Records |
| **Health_Record** | RecordID, Symptoms, Remarks | Linked to User; Reviewed by GP |
| **Medication** | GenericName, Efficacy, Price | Collected via Scraper |
| **Pharma** | PharmaID, Stock, Revenue | Receives alerts on Demand |