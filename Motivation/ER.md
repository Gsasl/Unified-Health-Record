# HRec - Conceptual ER Diagram

This is the high-level, conceptual view of the Unified Health Record system. It focuses purely on the entities and their relationships, without database-specific implementation details.

```mermaid
erDiagram
    %% Core Relationships
    USER ||--o{ HEALTH_RECORD : "creates & owns"
    USER }o--o{ MEDICATION : "needs & logs"
    
    GP ||--o{ HEALTH_RECORD : "monitors & flags"
    GP ||--o{ SP : "refers patient to"
    
    SP ||--o{ HEALTH_RECORD : "reviews specific cases"
    
    PHARMACY ||--|{ MEDICATION : "stocks & manages"