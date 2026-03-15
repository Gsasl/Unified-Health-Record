erDiagram
    %% Core Record Logic
    USER ||--o{ HEALTH_RECORD : "creates & owns"
    GP ||--o{ HEALTH_RECORD : "monitors & flags"
    SP ||--o{ HEALTH_RECORD : "reviews specialized"
    
    %% The Referral Bridge (Fixes the GP to SP issue)
    GP ||--o{ REFERRAL : "issues"
    SP ||--o{ REFERRAL : "receives"
    USER ||--o{ REFERRAL : "is subject of"

    %% The Inventory Bridge (Fixes the Pharmacy to Medication issue)
    PHARMACY ||--o{ INVENTORY : "manages"
    MEDICATION ||--o{ INVENTORY : "is listed as"

    %% User Medication Log Bridge
    USER ||--o{ USER_MED_LOG : "logs usage"
    MEDICATION ||--o{ USER_MED_LOG : "is tracked in"