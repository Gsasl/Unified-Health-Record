# HRec - Entity Relationship Diagram

This diagram maps out the flow between our Users, Doctors (GPs & SPs), and Pharmacies. 
# HRec - Physical Relational Schema

While an ER diagram shows the logic, a Relational Schema (Physical Data Model) shows the structure—exactly how the tables, primary keys, and foreign keys will look in the MySQL database. 

In the table below, **Bold** indicates a Primary Key (PK), and *Italics* indicates a Foreign Key (FK).

| Table | Attributes |
| :--- | :--- |
| **Users** | **UserID**, Username, Email, DOB |
| **Health_Records** | **RecordID**, *UserID (Unique)*, BloodType, ChronicConditions, Allergies |
| **GP_Doctors** | **DoctorID**, Name, LicenseNumber |
| **SP_Specialists** | **SpecID**, Name, Specialization |
| **Referrals** | **ReferralID**, *UserID*, *IssuingDoctorID*, *ReceivingSpecID*, DateIssued, Reason |
| **Pharmacies** | **PharmaID**, StoreName, Location |
| **Medications** | **MedID**, MedName, BasePrice |
| **Inventory** | **InventoryID**, *PharmaID*, *MedID*, StockQuantity, RetailPrice |
| **User_Med_Logs** | **LogID**, *UserID*, *MedID*, Dosage, LogDate |

---
```mermaid
erDiagram
    USER ||--o{ HEALTH_RECORD : "inputs & owns"
    GP ||--o{ HEALTH_RECORD : "monitors & flags"
    GP ||--o{ SP : "issues referral to"
    SP ||--o{ HEALTH_RECORD : "reviews specialized"
    PHARMACY ||--o{ MEDICATION : "tracks stock & revenue"
    USER }o--o{ MEDICATION : "logs usage & buys"

    USER {
        int UserID PK
        string Name
        decimal Expense_Log
    }
    GP {
        int DoctorID PK
        string License_No
    }
    SP {
        int DoctorID PK
        int Specialization_ID FK
    }
    HEALTH_RECORD {
        int RecordID PK
        int UserID FK
        string Symptoms
        string System_Remarks
    }
    PHARMACY {
        int PharmaID PK
        int Stock_Level
        decimal Revenue
    }
    MEDICATION {
        int MedID PK
        string GenericName
        decimal Price
        int Efficacy_Rating
    }