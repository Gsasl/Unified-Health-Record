# Academic Diagram & Figure Captions

Use these pre-written, academically-toned captions for your Microsoft Word report and PowerPoint viva slides. They avoid marketing fluff and focus strictly on database architecture and relational concepts.

## 1. ER/EER Diagram Captions
**Short (For Slides):**
> *Figure 1: Enhanced Entity-Relationship (EER) model illustrating the IS-A inheritance architecture for system users.*

**Long (For Report):**
> *Figure 1: The Enhanced Entity-Relationship (EER) diagram for the Unified Health Record system. The diagram highlights the IS-A superclass/subclass relationship between `Users` and `Doctors`, alongside the M:N bridge entities required for resolving complex many-to-many relationships such as medication logs and drug conflicts.*

## 2. Schema Diagram Captions
**Short (For Slides):**
> *Figure 2: Physical Relational Schema demonstrating Foreign Key constraints and 3NF normalization.*

**Long (For Report):**
> *Figure 2: The Physical Relational Database Schema. The diagram illustrates strict referential integrity via Foreign Keys (`ON DELETE CASCADE`), the self-referencing bridge table utilized for the CDSS rules engine (`Drug_Conflicts`), and the structural adherence to the Third Normal Form (3NF).*

## 3. Architecture/Flow Captions (If Applicable)
**Short (For Slides):**
> *Figure 3: System data flow demonstrating CDSS Trigger automation and View aggregation.*

**Long (For Report):**
> *Figure 3: The logical architecture of the Clinical Decision Support System (CDSS). The figure visualizes the backend process where an `INSERT` into the `USER_MED_LOG` activates a MySQL `TRIGGER`, which subsequently populates the `Medical_Flags` table for frontend retrieval via the `CDSS_Active_Conflicts` View.*

## 4. UI/Feature Screenshot Captions
*(Also cross-reference with `SCREENSHOT_PLAN.md`)*

**For the CDSS Interface:**
> *Figure 4: The frontend UI rendering the output of the `CDSS_Active_Conflicts` View, visualizing database-level trigger flags without heavy application-layer processing.*

**For the Pharmacy/Prescribe Interface:**
> *Figure 5: The transactional interface for prescription fulfillment, reliant on PDO `BEGIN/COMMIT` blocks to ensure atomic updates between inventory constraints and log statuses.*
