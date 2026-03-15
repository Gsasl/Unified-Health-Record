# HRec - Entity Relationship Diagram

This diagram maps out the flow between our Users, Doctors (GPs & SPs), and Pharmacies. 

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