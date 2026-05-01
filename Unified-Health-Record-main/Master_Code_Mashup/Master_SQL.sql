
-- ==========================================
-- ORIGINAL FILE: setup.sql
-- ==========================================

-- ============================================================
-- HRec: Unified Health Manager — Master Database Setup
-- CSE370 Database Project | 10 Tables · 1 VIEW · 3 Triggers
-- DB Name: bracculs_hrec
-- Run this entire file once in phpMyAdmin to build everything.
-- ============================================================

CREATE DATABASE IF NOT EXISTS bracculs_hrec
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bracculs_hrec;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS SECURITY_AUDIT_LOG;
DROP TABLE IF EXISTS Medical_Flags;
DROP TABLE IF EXISTS USER_MED_LOG;
DROP TABLE IF EXISTS Prescriptions;
DROP TABLE IF EXISTS Drug_Conflicts;
DROP TABLE IF EXISTS Brands;
DROP TABLE IF EXISTS Generics;
DROP TABLE IF EXISTS Doctors;
DROP TABLE IF EXISTS Health_Records;
DROP TABLE IF EXISTS Users;

DROP VIEW IF EXISTS CDSS_Active_Conflicts;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- TABLE 1: Users
-- All system actors in one table. Role ENUM drives page routing.
-- ============================================================
CREATE TABLE Users (
    UserID       INT AUTO_INCREMENT PRIMARY KEY,
    FullName     VARCHAR(100) NOT NULL,
    Email        VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    DOB          DATE,
    Role         ENUM('Patient', 'Doctor', 'Pharmacist') NOT NULL DEFAULT 'Patient',
    CreatedAt    DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- TABLE 2: Health_Records  (1:1 with Users)
-- UNIQUE on UserID enforces 1:1 at DB level.
-- BMI: GENERATED STORED column — auto-computed, no PHP needed.
-- KnownAllergies & ChronicConditions stored as JSON arrays.
-- ============================================================
CREATE TABLE Health_Records (
    RecordID          INT AUTO_INCREMENT PRIMARY KEY,
    UserID            INT NOT NULL UNIQUE,
    DOB               DATE,
    Sex               ENUM('Male', 'Female', 'Other'),
    BloodType         ENUM('A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'),
    WeightKG          DECIMAL(5,2),
    HeightCM          DECIMAL(5,2),
    BMI               DECIMAL(4,1) GENERATED ALWAYS AS
                      (ROUND(WeightKG / ((HeightCM / 100) * (HeightCM / 100)), 1)) STORED,
    KnownAllergies    JSON,
    ChronicConditions JSON,
    LastUpdated       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
);


-- ============================================================
-- TABLE 3: Doctors  (IS-A Users — UserID is PK + FK)
-- No separate DoctorID. UserID simultaneously serves as PK & FK.
-- DELETE from Users → CASCADE deletes Doctor row.
-- ============================================================
CREATE TABLE Doctors (
    UserID       INT PRIMARY KEY,
    Speciality   VARCHAR(100) DEFAULT 'General Practice',
    LicenseNo    VARCHAR(50)  NOT NULL UNIQUE,
    HospitalAffil VARCHAR(150) DEFAULT NULL,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
);


-- ============================================================
-- TABLE 4: Generics
-- Chemical composition catalog. CDSS rules operate at this level.
-- DrugClass groups generics (e.g. NSAID, ARB, Statin).
-- BlackBoxWarn: 0 = safe, 1 = FDA black box / life-threatening.
-- ============================================================
CREATE TABLE Generics (
    GenericID       INT AUTO_INCREMENT PRIMARY KEY,
    GenericName     VARCHAR(100) NOT NULL UNIQUE,
    DrugClass       VARCHAR(100) DEFAULT NULL,
    TypicalDuration VARCHAR(100) DEFAULT 'As prescribed',
    BlackBoxWarn    TINYINT(1)   NOT NULL DEFAULT 0,
    StopIfCondition TEXT,
    DietWarning     TEXT
);


-- ============================================================
-- TABLE 5: Brands
-- Commercial products linked N:1 to Generics.
-- Ratings JSON: {"efficacy": X.X, "price": X.X, "popularity": X.X}
-- Stock CHECK prevents negative inventory.
-- ============================================================
CREATE TABLE Brands (
    BrandID      INT AUTO_INCREMENT PRIMARY KEY,
    GenericID    INT NOT NULL,
    BrandName    VARCHAR(100) NOT NULL,
    Manufacturer VARCHAR(100),
    UnitPrice    DECIMAL(10,2),
    Stock        INT DEFAULT 50 CHECK (Stock >= 0),
    Ratings      JSON,
    FOREIGN KEY (GenericID) REFERENCES Generics(GenericID) ON DELETE CASCADE
);


-- ============================================================
-- TABLE 6: Drug_Conflicts
-- Self-referencing bridge table. Both FKs point to Generics.
-- Stores the CDSS rule (not the event).
-- ============================================================
CREATE TABLE Drug_Conflicts (
    ConflictID   INT AUTO_INCREMENT PRIMARY KEY,
    GenericID_1  INT NOT NULL,
    GenericID_2  INT NOT NULL,
    Severity     ENUM('LOW', 'MODERATE', 'HIGH', 'CRITICAL') NOT NULL,
    AlertMessage TEXT NOT NULL,
    UNIQUE KEY unique_conflict (GenericID_1, GenericID_2),
    FOREIGN KEY (GenericID_1) REFERENCES Generics(GenericID) ON DELETE CASCADE,
    FOREIGN KEY (GenericID_2) REFERENCES Generics(GenericID) ON DELETE CASCADE
);


-- ============================================================
-- TABLE 7: USER_MED_LOG  (M:N bridge: Users ↔ Brands)
-- PrescribedBy: nullable FK to Doctors(UserID). NULL = self-logged.
-- Soft-delete: Status='Discontinued' preserves history.
-- ============================================================
CREATE TABLE USER_MED_LOG (
    LogID        INT AUTO_INCREMENT PRIMARY KEY,
    UserID       INT NOT NULL,
    BrandID      INT NOT NULL,
    PrescribedBy INT DEFAULT NULL,
    Status       ENUM('Active', 'Discontinued', 'Completed') NOT NULL DEFAULT 'Active',
    DateAdded    DATETIME DEFAULT CURRENT_TIMESTAMP,
    Notes        TEXT,
    FOREIGN KEY (UserID)       REFERENCES Users(UserID)   ON DELETE CASCADE,
    FOREIGN KEY (BrandID)      REFERENCES Brands(BrandID),
    FOREIGN KEY (PrescribedBy) REFERENCES Doctors(UserID) ON DELETE SET NULL
);


-- ============================================================
-- TABLE 8: Prescriptions
-- Doctor formal drug orders with fulfillment tracking.
-- Items JSON: [{"brand_id":1,"dosage":"10mg","frequency":"twice daily"}, ...]
-- Status tracks the prescription lifecycle for pharmacist workflow.
-- ============================================================
CREATE TABLE Prescriptions (
    PrescriptionID INT AUTO_INCREMENT PRIMARY KEY,
    DoctorID       INT NOT NULL,
    PatientID      INT NOT NULL,
    Items          JSON NOT NULL,
    Status         ENUM('Pending', 'Fulfilled', 'Cancelled') NOT NULL DEFAULT 'Pending',
    IssuedAt       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FulfilledAt    DATETIME DEFAULT NULL,
    Notes          TEXT,
    FOREIGN KEY (DoctorID)  REFERENCES Doctors(UserID),
    FOREIGN KEY (PatientID) REFERENCES Users(UserID)
);


-- ============================================================
-- TABLE 9: Medical_Flags
-- CDSS output store. ResolvedAt = NULL means active.
-- TriggerType covers the three CDSS alert categories.
-- ============================================================
CREATE TABLE Medical_Flags (
    FlagID      INT AUTO_INCREMENT PRIMARY KEY,
    PatientID   INT NOT NULL,
    TriggerType ENUM('Conflict', 'Allergy', 'Overdose') NOT NULL,
    ConflictID  INT DEFAULT NULL,
    Severity    ENUM('LOW', 'MODERATE', 'HIGH', 'CRITICAL') NOT NULL,
    Message     TEXT NOT NULL,
    ResolvedAt  DATETIME DEFAULT NULL,
    CreatedAt   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (PatientID)  REFERENCES Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (ConflictID) REFERENCES Drug_Conflicts(ConflictID) ON DELETE SET NULL
);


-- ============================================================
-- TABLE 10: SECURITY_AUDIT_LOG
-- Tracks significant system events for accountability.
-- ============================================================
CREATE TABLE SECURITY_AUDIT_LOG (
    LogID       INT AUTO_INCREMENT PRIMARY KEY,
    UserID      INT DEFAULT NULL,
    Action      VARCHAR(100) NOT NULL,
    TableName   VARCHAR(50)  DEFAULT NULL,
    RecordID    INT          DEFAULT NULL,
    Details     TEXT,
    IPAddress   VARCHAR(45)  DEFAULT NULL,
    CreatedAt   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE SET NULL
);


-- ============================================================
-- SEED DATA — Users
-- Passwords: patient123, doctor123, pharma123 (bcrypt)
-- Hashes generated with password_hash($pw, PASSWORD_BCRYPT)
-- ============================================================
INSERT INTO Users (UserID, FullName, Email, PasswordHash, DOB, Role) VALUES
(1, 'Ahmed Rahman',       'patient1@hrec.test', '$2y$10$LJAZEWmVN.eBKeGOqO7VxODg3GvZS0XKx6RvJHqJGm/3SEhbHIbMa', '1988-03-14', 'Patient'),
(2, 'Sadia Islam',        'patient2@hrec.test', '$2y$10$LJAZEWmVN.eBKeGOqO7VxODg3GvZS0XKx6RvJHqJGm/3SEhbHIbMa', '1995-07-22', 'Patient'),
(3, 'Dr. Farhan Karim',   'doctor@hrec.test',   '$2y$10$v5wBJDmkqy.cAOXOdJF0aOZ7lN8HCXJP.F5u/YR3lSQCZjhM9x0G6', '1980-11-05', 'Doctor'),
(4, 'Rifat Chowdhury',    'pharma@hrec.test',   '$2y$10$R5EkN4xGpP9L0V7fVoKBOeB.zq8GkYpV3S.mYqGYsQJvRWg9rF2oW', '1992-01-18', 'Pharmacist');


-- ============================================================
-- SEED DATA — Doctors
-- UserID is PK (IS-A pattern). Dr. Farhan is UserID 3.
-- ============================================================
INSERT INTO Doctors (UserID, Speciality, LicenseNo, HospitalAffil) VALUES
(3, 'General Practice', 'BMDC-GP-2024-00142', 'United Hospital, Dhaka');


-- ============================================================
-- SEED DATA — Health_Records
-- Patient 1 (Ahmed): full record. Patient 2 (Sadia): partial.
-- BMI auto-computed: 78.5 / (1.75^2) ≈ 25.6
-- ============================================================
INSERT INTO Health_Records (UserID, DOB, Sex, BloodType, WeightKG, HeightCM, KnownAllergies, ChronicConditions) VALUES
(1, '1988-03-14', 'Male', 'B+', 78.5, 175.0,
 JSON_ARRAY('Penicillin', 'Sulfa drugs'),
 JSON_ARRAY('Hypertension', 'Type 2 Diabetes')),
(2, '1995-07-22', 'Female', 'O+', 55.0, 160.0,
 JSON_ARRAY('Aspirin'),
 JSON_ARRAY('Asthma'));


-- ============================================================
-- SEED DATA — Generics (9 drugs with DrugClass)
-- ============================================================
INSERT INTO Generics (GenericID, GenericName, DrugClass, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES
(1, 'Ketorolac Tromethamine', 'NSAID', 'Maximum of 5 days', 0,
   'Contraindicated in patients with active peptic ulcer or history of hypersensitivity to NSAIDs.',
   'Take with food or milk to reduce stomach upset.'),
(2, 'Losartan Potassium', 'ARB', 'As prescribed', 0,
   'Contraindicated in pregnancy (Category D — fetal toxicity).',
   ''),
(3, 'Isotretinoin', 'Retinoid', 'Maximum of 5 months per course', 1,
   'Category X — absolutely contraindicated in pregnancy. Causes severe birth defects.',
   'Avoid vitamin A supplements. Take with a high-fat meal to improve absorption.'),
(4, 'Warfarin Sodium', 'Anticoagulant', 'As prescribed (ongoing)', 0,
   'Contraindicated in patients with active bleeding or haemorrhagic stroke.',
   'Avoid sudden changes in Vitamin K intake (leafy greens, broccoli, spinach). Consistent diet required.'),
(5, 'Metformin Hydrochloride', 'Biguanide', 'As prescribed (ongoing)', 0,
   'Contraindicated in patients with eGFR <30 mL/min (severe renal impairment).',
   'Take with meals to reduce GI side effects. Avoid excessive alcohol.'),
(6, 'Atorvastatin', 'Statin', 'As prescribed (ongoing)', 0,
   'Contraindicated in active liver disease or unexplained persistent elevations of serum transaminases.',
   'Avoid large amounts of grapefruit juice — inhibits CYP3A4 and increases drug levels.'),
(7, 'Azithromycin', 'Macrolide', '3 to 5 days standard course', 0,
   'Contraindicated in patients with a history of hypersensitivity to azithromycin or macrolides.',
   ''),
(8, 'Clopidogrel', 'Antiplatelet', 'As prescribed (ongoing)', 0,
   'Contraindicated in patients with active pathological bleeding such as peptic ulcer.',
   ''),
(9, 'Omeprazole', 'PPI', 'As prescribed', 0,
   'Contraindicated in patients with known hypersensitivity to any proton pump inhibitor.',
   'Best taken 30-60 minutes before a meal for maximum effectiveness.');


-- ============================================================
-- SEED DATA — Brands (18 brands, 2 per generic)
-- Ratings JSON: {"efficacy": X.X, "price": X.X, "popularity": X.X}
-- ============================================================
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, Stock, Ratings) VALUES
(1, 'Torax',    'Square',   130.00, 50, '{"efficacy": 5.0, "price": 4.0, "popularity": 5.0}'),
(1, 'Rollac',   'Incepta',  100.00, 50, '{"efficacy": 4.8, "price": 4.5, "popularity": 4.5}'),
(2, 'Osartil',  'Incepta',   10.00, 50, '{"efficacy": 5.0, "price": 4.5, "popularity": 5.0}'),
(2, 'Angilock', 'Square',     6.00, 50, '{"efficacy": 4.8, "price": 4.0, "popularity": 4.8}'),
(3, 'Tretin',   'Beximco',   20.69, 50, '{"efficacy": 4.8, "price": 4.0, "popularity": 4.0}'),
(3, 'Acnetin',  'Square',    22.00, 50, '{"efficacy": 5.0, "price": 3.5, "popularity": 4.5}'),
(4, 'Orfarin',  'Incepta',    8.03, 50, '{"efficacy": 5.0, "price": 4.5, "popularity": 4.5}'),
(4, 'Warfin',   'Beximco',    7.50, 50, '{"efficacy": 4.8, "price": 4.8, "popularity": 4.0}'),
(5, 'Comet',    'Square',     5.00, 50, '{"efficacy": 5.0, "price": 5.0, "popularity": 5.0}'),
(5, 'Daomin',   'Beximco',    4.50, 50, '{"efficacy": 4.5, "price": 5.0, "popularity": 4.5}'),
(6, 'Lipicon',  'Incepta',    8.02, 50, '{"efficacy": 5.0, "price": 4.0, "popularity": 5.0}'),
(6, 'Atova',    'Beximco',   12.00, 50, '{"efficacy": 4.8, "price": 4.2, "popularity": 4.8}'),
(7, 'Zimax',    'Square',    40.00, 50, '{"efficacy": 5.0, "price": 4.0, "popularity": 5.0}'),
(7, 'Tridosil', 'Incepta',   35.00, 50, '{"efficacy": 4.8, "price": 4.5, "popularity": 4.8}'),
(8, 'Plagrin',  'Square',     5.02, 50, '{"efficacy": 5.0, "price": 4.0, "popularity": 5.0}'),
(8, 'Anclog',   'Incepta',    3.00, 50, '{"efficacy": 4.8, "price": 4.5, "popularity": 4.5}'),
(9, 'Seclo',    'Square',     6.00, 50, '{"efficacy": 5.0, "price": 4.0, "popularity": 5.0}'),
(9, 'Losectil', 'Eskayef',    5.00, 50, '{"efficacy": 4.8, "price": 4.2, "popularity": 4.7}');


-- ============================================================
-- SEED DATA — Drug_Conflicts (CDSS rules)
-- ============================================================
INSERT INTO Drug_Conflicts (GenericID_1, GenericID_2, Severity, AlertMessage) VALUES
(1, 2, 'CRITICAL',
 'Ketorolac (NSAID) + Losartan (ARB): Co-administration inhibits prostaglandin synthesis and renal autoregulation simultaneously. Dramatically increases risk of acute renal failure, especially in elderly or dehydrated patients.'),
(6, 7, 'HIGH',
 'Atorvastatin + Azithromycin: Azithromycin inhibits CYP3A4, reducing atorvastatin metabolism. Elevated statin plasma levels increase risk of myopathy and rhabdomyolysis (muscle breakdown leading to kidney damage).'),
(9, 8, 'HIGH',
 'Omeprazole + Clopidogrel: Omeprazole inhibits CYP2C19, the enzyme that activates clopidogrel. Co-administration reduces clopidogrel antiplatelet efficacy by up to 47%, increasing thrombotic risk.');


-- ============================================================
-- SEED DATA — USER_MED_LOG
-- Ahmed takes Torax(BrandID 1) + Osartil(BrandID 3) → CRITICAL
-- PrescribedBy = 3 (Dr. Farhan's UserID, which is Doctors PK)
-- ============================================================
INSERT INTO USER_MED_LOG (UserID, BrandID, PrescribedBy, Status) VALUES
(1, 1,  3, 'Active'),
(1, 3,  3, 'Active'),
(1, 9,  3, 'Discontinued'),
(2, 9,  NULL, 'Active'),
(2, 14, NULL, 'Active');


-- ============================================================
-- SEED DATA — Medical_Flags
-- Pre-seeded CDSS output for demo.
-- ============================================================
INSERT INTO Medical_Flags (PatientID, TriggerType, ConflictID, Severity, Message) VALUES
(1, 'Conflict', 1, 'CRITICAL',
 'CRITICAL DRUG INTERACTION: Torax (Ketorolac) and Osartil (Losartan) are both active. Co-administration dramatically increases risk of acute renal failure. Immediate clinical review required.');


-- ============================================================
-- SEED DATA — Prescriptions
-- Dr. Farhan prescribed for Ahmed. Status = Pending (for pharmacist).
-- ============================================================
INSERT INTO Prescriptions (DoctorID, PatientID, Items, Status, Notes) VALUES
(3, 1,
 '[{"brand_id": 1, "brand_name": "Torax", "dosage": "10mg", "frequency": "twice daily", "duration": "5 days max"},
   {"brand_id": 9, "brand_name": "Comet", "dosage": "500mg", "frequency": "with each meal"}]',
 'Pending',
 'Patient presenting with post-operative pain and existing T2DM. Monitor renal function closely.'),
(3, 2,
 '[{"brand_id": 14, "brand_name": "Tridosil", "dosage": "500mg", "frequency": "once daily", "duration": "3 days"}]',
 'Pending',
 'Upper respiratory infection. Standard azithromycin course.');


-- ============================================================
-- CDSS VIEW — CDSS_Active_Conflicts
-- Returns all active dangerous drug combinations for all patients.
-- Usage: SELECT * FROM CDSS_Active_Conflicts WHERE PatientID = 1;
-- ============================================================
CREATE OR REPLACE VIEW CDSS_Active_Conflicts AS
SELECT
    uml1.UserID                 AS PatientID,
    u.FullName                  AS PatientName,
    dc.ConflictID,
    dc.Severity,
    dc.AlertMessage,
    b1.BrandName                AS Drug1_Brand,
    g1.GenericName              AS Drug1_Generic,
    b2.BrandName                AS Drug2_Brand,
    g2.GenericName              AS Drug2_Generic,
    GREATEST(uml1.DateAdded, uml2.DateAdded) AS ConflictDetectedAt
FROM USER_MED_LOG uml1
    JOIN Users        u   ON uml1.UserID   = u.UserID
    JOIN Brands       b1  ON uml1.BrandID  = b1.BrandID
    JOIN Generics     g1  ON b1.GenericID  = g1.GenericID
    JOIN USER_MED_LOG uml2
        ON uml1.UserID  = uml2.UserID
       AND uml1.LogID   < uml2.LogID
    JOIN Brands       b2  ON uml2.BrandID  = b2.BrandID
    JOIN Generics     g2  ON b2.GenericID  = g2.GenericID
    JOIN Drug_Conflicts dc
        ON (dc.GenericID_1 = g1.GenericID AND dc.GenericID_2 = g2.GenericID)
        OR (dc.GenericID_1 = g2.GenericID AND dc.GenericID_2 = g1.GenericID)
WHERE uml1.Status = 'Active'
  AND uml2.Status = 'Active';


-- ============================================================
-- TRIGGER 1: After_Med_Log_INSERT
-- Auto-detects drug conflicts when a new medication is logged.
-- Inserts a Medical_Flag row if a conflict is found.
-- ============================================================
DELIMITER //
CREATE TRIGGER After_Med_Log_INSERT
AFTER INSERT ON USER_MED_LOG
FOR EACH ROW
BEGIN
    DECLARE v_new_generic INT;
    DECLARE v_conflict_id INT;
    DECLARE v_severity VARCHAR(10);
    DECLARE v_alert TEXT;
    DECLARE v_other_brand VARCHAR(100);
    DECLARE v_new_brand VARCHAR(100);
    DECLARE done INT DEFAULT FALSE;

    DECLARE conflict_cursor CURSOR FOR
        SELECT dc.ConflictID, dc.Severity, dc.AlertMessage, b2.BrandName
        FROM USER_MED_LOG uml2
        JOIN Brands b2 ON uml2.BrandID = b2.BrandID
        JOIN Generics g2 ON b2.GenericID = g2.GenericID
        JOIN Drug_Conflicts dc
            ON (dc.GenericID_1 = v_new_generic AND dc.GenericID_2 = g2.GenericID)
            OR (dc.GenericID_1 = g2.GenericID AND dc.GenericID_2 = v_new_generic)
        WHERE uml2.UserID = NEW.UserID
          AND uml2.Status = 'Active'
          AND uml2.LogID != NEW.LogID;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    IF NEW.Status = 'Active' THEN
        SELECT b.GenericID, b.BrandName INTO v_new_generic, v_new_brand
        FROM Brands b WHERE b.BrandID = NEW.BrandID;

        OPEN conflict_cursor;
        read_loop: LOOP
            FETCH conflict_cursor INTO v_conflict_id, v_severity, v_alert, v_other_brand;
            IF done THEN LEAVE read_loop; END IF;

            IF NOT EXISTS (
                SELECT 1 FROM Medical_Flags
                WHERE PatientID = NEW.UserID
                  AND ConflictID = v_conflict_id
                  AND ResolvedAt IS NULL
            ) THEN
                INSERT INTO Medical_Flags (PatientID, TriggerType, ConflictID, Severity, Message)
                VALUES (NEW.UserID, 'Conflict', v_conflict_id, v_severity,
                    CONCAT('INTERACTION: ', v_new_brand, ' + ', v_other_brand, ': ', v_alert));
            END IF;
        END LOOP;
        CLOSE conflict_cursor;
    END IF;
END //
DELIMITER ;


-- ============================================================
-- TRIGGER 2: Before_User_Delete_Audit
-- Logs user deletion to the audit table before CASCADE.
-- ============================================================
DELIMITER //
CREATE TRIGGER Before_User_Delete_Audit
BEFORE DELETE ON Users
FOR EACH ROW
BEGIN
    INSERT INTO SECURITY_AUDIT_LOG (UserID, Action, TableName, RecordID, Details)
    VALUES (OLD.UserID, 'USER_DELETED', 'Users', OLD.UserID,
        CONCAT('Deleted user: ', OLD.FullName, ' (', OLD.Email, ') Role: ', OLD.Role));
END //
DELIMITER ;


-- ============================================================
-- TRIGGER 3: After_Prescription_Audit
-- Logs new prescriptions for traceability.
-- ============================================================
DELIMITER //
CREATE TRIGGER After_Prescription_Audit
AFTER INSERT ON Prescriptions
FOR EACH ROW
BEGIN
    INSERT INTO SECURITY_AUDIT_LOG (UserID, Action, TableName, RecordID, Details)
    VALUES (NEW.DoctorID, 'PRESCRIPTION_CREATED', 'Prescriptions', NEW.PrescriptionID,
        CONCAT('Doctor ', NEW.DoctorID, ' prescribed for Patient ', NEW.PatientID));
END //
DELIMITER ;


-- ============================================================
-- SEED DATA — Security Audit Log (initial entries)
-- ============================================================
INSERT INTO SECURITY_AUDIT_LOG (UserID, Action, TableName, Details) VALUES
(3, 'PRESCRIPTION_CREATED', 'Prescriptions', 'Initial seed: Dr. Farhan prescribed for Ahmed Rahman'),
(3, 'PRESCRIPTION_CREATED', 'Prescriptions', 'Initial seed: Dr. Farhan prescribed for Sadia Islam');


-- ============================================================
-- DEMO CREDENTIALS (shown on login page)
-- ============================================================
-- Patient:    patient1@hrec.test  /  patient123
-- Doctor:     doctor@hrec.test    /  doctor123
-- Pharmacist: pharma@hrec.test    /  pharma123
-- ============================================================



-- ==========================================
-- ORIGINAL FILE: tools/seed_data.sql
-- ==========================================

-- Auto-generated Medex Seed Data

INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (1, 'Paracetamol', 'It is contraindicated in known hypersensitivity to Paracetamol.', 'Patients who have taken barbiturates, tricyclic antidepressants and alcohol may show diminished ability to metabolise large doses of Napa. Alcohol can increase the hepatotoxicity of Napa overdosage. Chronic ingestion of anticonvulsants or oral steroid contraceptives induce liver enzymes and may prevent attainment of therapeutic Napa levels by increasing first-pass metabolism or clearance.', 'Care is advised in the administration of Napa to patients with severe renal or severe hepatic impairment. The hazard of overdose is greater in those with non-cirrhotic alcoholic liver disease. Do not exceed the stated dose. Patients should be advised not to take other Napa-containing products concurrently. Napa should only be used by the patient for whom it is prescribed when clearly necessary. Administration of Napa in doses higher than recommended may result in hepatic injury, including the risk of severe hepatotoxicity and death. Do not exceed the maximum recommended daily dose of Napa. Use caution when administering Napa in patients with the following conditions: hepatic impairment or active hepatic disease, alcoholism, chronic malnutrition, severe hypovolemia (e.g., due to dehydration or blood loss), or severe renal impairment (creatinine clearance < 30 ml/min). There were infrequent reports of life-threatening anaphylaxis requiring emergent medical attention. Discontinue Napa IV immediately if symptoms associated with allergy or hypersensitivity occurs. Do not use Napa IV in patients with Napa allergy.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (1, 'Napa', 'Beximco', 1.2, 5.0, 5.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (1, 'Ace', 'Square', 1.2, 5.0, 5.0, 4.8);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (1, 'Fast', 'Acme', 18.0, 4.5, 5.0, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (1, 'Reset', 'Incepta', 200.0, 4.5, 5.0, 4.2);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (2, 'Amoxicillin Trihydrate', 'Amoxicillin is contraindicated in penicillin hypersensitive patients.', 'Concurrent use of Moxacil and Probenecid may result in increased and prolonged blood levels of Moxacil. Moxacil may affect the gut flora, leading to lower estrogen reabsorption and reduced efficacy of combined oral estrogen/progesterone contraceptives.', 'The possibility of superinfections with mycotic or bacterial pathogens should be kept in mind during therapy. If superinfections occur, Moxacil should be discontinued and appropriate therapy should be instituted.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (2, 'Moxacil', 'Square', 7.5, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (2, 'Fimox', 'Beximco', 40.0, 4.8, 4.5, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (2, 'Sinex', 'Opsonin', 230.0, 4.5, 4.8, 4.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (2, 'Tycil', 'Beximco', 40.0, 4.5, 4.5, 4.2);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (3, 'Omeprazole', 'Omeprazole is contraindicated in patients with known hypersensitivity to it. When gastric ulcer is suspected, the possibility of malignancy should be excluded before treatment with omeprazole is instituted, as treatment may alleviate symptoms and delay diagnosis.', 'Due to the decreased intragastric acidity the absorption of ketoconazole may be reduced during Seclo treatment as it is during treatment with other acid secretion inhibitors. As Seclo is metabolised in the liver through cytochrome P450 it can delay the elimination of diazepam, phenytoin and warfarin. Monitoring of patients receiving warfarin or pheytoin is recommended and a reduction of warfarin or phenytoin dose may be necessary. However concomitant treatment with Seclo 20mg daily did not change the blood concentration of phenytoin in patients on continuous treatment with phenytoin. Similarly concomitant treatment with Seclo 20mg daily did not change coagulation time in patients on continuous treatment with warfarin. Plasma concentrations of Seclo and clarithromycin are increased during concomitant administration. This is considered to be a useful interaction during H. pylori eardication. There is no evidence of an interaction with phenacetin, theophylline, caffeine, propranolol, metoprolol, cyclosporin, lidocaine, quinidine, estradiol, amoxycillin or antacids. The absorption of Seclo is not affected by alcohol or food. There is no evidence of an interaction with piroxicam, diclofenac or naproxen. This is considered useful when patients are required to continue these treatments. Simultaneous treatment with Seclo and digoxin in healthy subjects lead to a 10% increase in the bioavailability of digoxin as a consequence of the increased intragastric pH.', 'Avoid concomitant use of clopidogrel and Seclo as the pharmacological activity of clopidogrel is reduced if given concomitantly. Observational studies suggest that proton pump inhibitor (PPI) therapy may be associated with an increased risk for osteoporosis- related fractures of the hip, wrist, or spine. Atrophic gastritis has been noted occasionally in gastric corpus biopsies from patients treated long-term with Seclo. Concomitant use of PPIs with methotrexate may lead to methotrexate toxicities.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (3, 'Seclo', 'Square', 6.0, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (3, 'Losectil', 'Eskayef', 5.0, 4.8, 4.2, 4.7);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (3, 'Progut', 'Renata', 38.0, 4.5, 4.5, 4.2);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (3, 'Ometid', 'Opsonin', 45.0, 4.5, 4.8, 4.0);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (4, 'Metformin Hydrochloride', 'No specific contraindications but caution is required in patients with hypersensitivity to any constituents of the formulation.', '', 'If there is any reason to suppose that adrenal function is impaired, care must be taken while transferring patients from systemic steroid treatment to Trispray. In clinical studies with Trispray administered intranasally, the development of localized infections, on the nose, and pharynx with Candida albicans, has rarely occurred. When such an infection develops it may require treatment with appropriate local therapy and discontinuance of treatment with Trispray. Because of the inhibitory effect of corticosteroids on wound healing in patients who have experienced recent nasal septal ulcers, nasal surgery of trauma, Trispray should be used with caution until healing has occurred.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (4, 'Comet', 'Square', 201.0, 5.0, 5.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (4, 'Daomin', 'Beximco', 520.0, 4.5, 5.0, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (4, 'Bigmet', 'Renata', 4.0, 4.5, 4.8, 4.2);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (4, 'Oramet', 'JMI', 100.0, 4.0, 4.8, 4.0);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (5, 'Losartan Potassium', 'Losartan Potassium is contraindicated in pregnant women and in patients who are hypersensitive to any component of this product. Losartan Potassium should not be administered with Aliskiren in patients with diabetes.', 'Rifampicin and fluconazole reduce levels of active metabolite of Osartil. Concomitant use of Osartil and hydrochlorothiazide may lead to potentiation of the antihypertensive effects. Concomitant use of potassium-sparing diuretics (eg, spironolactone, triamterene, amiloride), potassium supplements or salt substitutes containing potassium may lead to increases in serum potassium. The antihypertensive effect of losartan may be attenuated by the non-steroidal anti-inflammatory drug indomethacin. The use of ACE-inhibitor, angiotensin receptor antagonist, an anti-inflammatory drug and a thiazide diuretic at the same time increases the risk of renal impairment.', 'Use of Osartil during the second and third trimesters of pregnancy reduces fetal renal function and increases fetal and neonatal morbidity and death. In patients who are intravascularly volume-depleted (e.g., those treated with high-dose diuretics), symptomatic hypotension may occur. Plasma concentration of Osartil is significantly increased in cirrhotic patients. Changes in renal function including renal failure have been reported in renal impaired patient.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (5, 'Osartil', 'Incepta', 10.0, 5.0, 4.5, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (5, 'Angilock', 'Square', 6.0, 4.8, 4.0, 4.8);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (5, 'Losar', 'Opsonin', 8.0, 4.5, 4.8, 4.3);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (5, 'Ostan', 'Renata', 10.0, 4.5, 4.8, 4.0);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (6, 'Ketorolac Tromethamine', 'Ceftriaxone should not be given to patients with a history of hypersensitivity to cephalosporin antibiotics.', 'No drug interactions have been reported.', 'As with other cephalosporins, anaphylactic shock cannot be ruled out even if a thorough patient history is taken. Anaphylactic shock requires immediate countermeasures such as intravenous epinephrine followed by a glucocorticoid. In rare cases, shadows suggesting sludge have been detected by sonograms of the gallbladder. This condition was reversible on discontinuation or completion of Arixon therapy. Even if such findings are associated with pain, conservative, nonsurgical management is recommended. During prolonged treatment the blood picture should be checked at regular intervals.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (6, 'Torax', 'Square', 130.0, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (6, 'Rollac', 'Incepta', 100.0, 4.8, 4.5, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (6, 'Emodol', 'Jayson', 11.0, 4.5, 4.8, 4.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (6, 'Rolac', 'Renata', 190.0, 4.5, 4.8, 4.0);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (7, 'Salbutamol', 'Known hypersensitivity to any component of this tablet or substituted benzimidazoles. History of asthmay urticaria or other allergic-type reactions after taking aspirin or other NSAIDs. Use during the peri-operative period in the setting of coronary artery bypass graft (CABG) surgery.', 'With medicine: Concomitant use of NSAIDs may reduce the antihypertensive effect of ACE inhibitors, diuretics & beta-blockers Concomitant use of this tablet and warfarin may result in an increased risk of a bleeding complication. Esomeprazole inhibits gastric acid secretion & may interfere with the absorption of drugs where gastric pH is an important determinant of bioavailability (eg. Ketoconazole, iron salts and digoxin). With food & others: Administration of Naproxen & Esomeprazole together with high-fat food in healthy volunteers does not affect the extent of absorption of naproxen but significantly prolongs t max by 10 hours and decreases peak plasma concentration (C max ) by about 12%', 'General : The combination of Naproxen and Esomeprazole tablet and NSAIDs including cyclooxygenase-2 selective inhibitors should be avoided because of the cumulative risks of inducing serious NSAID-related adverse events. Naproxen and Esomeprazole tablet can be used with low dose acetylsalicylic acid. Undesirable effects may be minimized by using the lowest effective dose for the shortest duration necessary to control symptoms. Risk-factors to develop NSAID related gastro-intestinal complications include high age, concomitant use of anticoagulants, corticosteroids, other NSAIDs including low-dose acetylsalicylic acid, debilitating cardiovascular disease, Helicobacter pylori infection, and a history of gastric and/or duodenal ulcers and upper gastrointestinal bleeding. In patients with the conditions such as Inducible porphyries, Systemic lupus erythematosis and mixed connective tissue disease, Naproxen should only be used after a rigorous benefit-risk ratio. Patients on long-term treatment (particularly those treated for more than a year) should be kept under regular surveillance. Older people : Naproxen: Older people have an increased frequency of adverse reactions especially gastro-intestinal bleeding, and perforation, which may be fatal. The esomeprazole component of Naproxen and Esomeprazole tablet decreased the incidence of ulcers in older people. Gastrointestinal effects : Naproxen: GI bleeding, ulceration or perforation, which can be fatal, has been reported with all NSAIDs at anytime during treatment, with or without warning symptoms or a previous history of serious GI events. The risk of GI bleeding, ulceration or perforation with NSAIDs is higher with increasing NSAID doses, in patients with a history of ulcer, particularly if complicated with haemorrhage or perforation, and in older people. These patients should begin treatment on the lowest dose available. Combination therapy with protective agents (e.g. misoprostol or proton pump inhibitors) should be considered for these patients, and also for patients requiring concomitant low dose acetylsalicylic acid, or other drugs likely to increase gastrointestinal risk. Patients with a history of GI toxicity, particularly older people, should report any unusual abdominal symptoms (especially GI bleeding) particularly in the initial stages of treatment. Caution should be advised in patients receiving NSAIDs with concomitant medications which could increase the risk of ulceration or bleeding, such as oral corticosteroids, anticoagulants such as warfarin, selective serotonin-reuptake inhibitors or anti-platelet agents such as acetylsalicylic acid. When GI bleeding or ulceration occurs in patients receiving Naproxen and Esomeprazole Tablet, the treatment should be withdrawn. NSAIDs should be given with care to patients with a history of gastrointestinal disease (ulcerative colitis, Crohn’s disease) as these conditions may be exacerbated. Esomeprazole: Dyspesia could still occur despite the addition of Esomperazole to the combination tablet. Treatment with proton pump inhibitors may lead to slightly increased risk of gastrointestinal infections such as Salmonella and Campylobacter. Esomeprazole, as all acid-blocking medicines, might reduce the absorption of vitamin B12 (cyanocobalamin) due to hypo- or achlorhydria. This should be considered in patients with reduced body stores or risk factors of reduced vitamin B12 absorption on long-term therapy. Cardiovascular and cerebrovascular effects : Naproxen: Appropriate monitoring and advice are required for patients with a history of hypertension and/or mild to moderate congestive heart failure as fluid retention and oedema have been reported in association with NSAID therapy. Patients with uncontrolled hypertension, congestive heart failure, established ischaemic heart disease, peripheral arterial disease, and/or cerebrovascular disease should only be treated with Naproxen after careful consideration. Similar consideration should be made before initiating longer-term treatment of patients with risk factors for cardiovascular events (e.g. hypertension, hyperlipidaemia, diabetes mellitus, smoking). Renal effects : Naproxen: Long-term administration of NSAIDs has resulted in renal papillary necrosis and other renal injury.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (7, 'Windel', 'Incepta', 15.0, 5.0, 5.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (7, 'Sulolin', 'Square', 9.0, 4.8, 4.8, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (7, 'Brodil', 'Beximco', 8.0, 4.5, 4.5, 4.2);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (7, 'Azmasol', 'Beximco', 250.0, 4.5, 4.5, 4.8);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (8, 'Azithromycin', 'Azithromycin Dihydrate is contraindicated in patients hypersensitive to Azithromycin or any other macrolide antibiotic. Co-administration of ergot derivatives and Azithromycin is contraindicated. Azithromycin is contraindicated in patients with hepatic diseases.', 'Antacid : In patients receiving azithromycin and antacids, azithromycin should be taken at least 1 hour before or 2 hours after the antacid. Carbamazepine: In a pharmacokinetic interaction study in healthy volunteers, no significant effect was observed on the plasma levels of carbamazepine or its active metabolite. Cyclosporin : Some of the related macrolide antibiotics interfere with the metabolism of cyclosporin. In the absence of conclusive data from pharmacokinetic studies or clinical data investigating potential interactions between azithromycin and cyclosporine, caution should be exercised before co-administration of these two drugs. If coadministrations is necessary, cyclosporin levels should be monitored and the dose adjusted accordingly. Digoxin : Some of the macrolide antibiotics have been reported to impair the metabolism of digoxin (in the gut) in some patients. Therefore, in patients receiving concomitant azithromycin and digoxin the possibility of raised digoxin levels should be borne in mind and digoxin levels monitored. Ergot derivatives : Because of the theoretical possibility of ergotism, azithromycin and ergot derivatives should not be co-administered. Methylprednisolone : In a pharmacokinetic interaction study in healthy volunteers, azithromycin had no significant effect on the pharmacokinetics of methylprednisolone. Theophylline : There is no evidence of any pharmacokinetic interaction when azithromycin and theophylline are co-administered to healthy volunteers. In general, however, theophylline levels should be monitored. Warfarin : In a pharmacodynamic interaction study, azithromycin did not alter the anticoagulant effect of a single 15 mg dose of warfarin administered to healthy volunteers. Zimax and warfarin may be co-administered, but monitoring of the prothrombin time should be continued as routinely performed. Terfenadine : Zimax did not affect the pharmacokinetics of terfenadine administered at the recommended dose of 60 mg every 12 hours. Addition of azithromycin did not result in any significant changes in cardiac repolarisation (QTc interval) measured during the steady state dosing of terfenadine.', 'As with erythromycin and other macrolides, rare serious allergic reactions, including angioneurotic oedema and anaphylaxis, has been reported. Some of these reactions with azithromycin have resulted in recurrent symptoms and required a long period of observation and treatment.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (8, 'Zimax', 'Square', 40.0, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (8, 'Tridosil', 'Incepta', 35.0, 4.8, 4.5, 4.8);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (8, 'Zithrin', 'Renata', 40.0, 4.5, 4.8, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (8, 'Odmon', 'Opsonin', 12.0, 4.5, 4.8, 4.0);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (9, 'Pantoprazole', 'Patients with known hypersensitivity to Enoxaparin Sodium, heparin or other low molecular weight heparins. Patients with active major bleeding and conditions with a high risk of uncontrolled hemorrhage including recent hemorrhagic stroke.', 'It is recommended that agents which affect hemostasis should be discontinued prior to Clotinex therapy unless strictly indicated. These agents include medications such as: acetylsalicylic acid (and derivatives), NSAIDs (including ketorolac), ticlopidine,clopidogrel,dextran 40,glucocorticoids, thrombolytics and anticoagulants, other antiplatelet aggregation agents including glycoprotein llb/llla antagonists. If the combination is indicated, should be used with careful clinical and laboratory monitoring.', 'Clotinex should be injected by deep subcutaneous route in prophylactic and curative treatment and by intravascular route during hemodialysis. Do not administer by the intramuscular route. Clotinex should be used with caution in conditions with increased potential for bleeding, such as impaired hemostasis, history of peptic ulcer, recent ischemic stroke, uncontrolled severe arterial hypertension, diabetic retinopathy and recent neuro- or ophthalmologic surgery, concomitant use of medications affecting hemostasis. It is recommended that the platelet counts be measured before the initiation of the treatment and regularly thereafter during treatment.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (9, 'Pantonix', 'Incepta', 451.36, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (9, 'Protonp', 'Square', 461.38, 4.8, 4.2, 4.8);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (9, 'Trupan', 'Opsonin', 45.0, 4.5, 4.5, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (9, 'Pantodac', 'Renata', 576.0, 4.5, 4.8, 4.2);
INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES (10, 'Atorvastatin', 'Losartan Potassium is contraindicated in pregnant women and in patients who are hypersensitive to any component of this product. Losartan Potassium should not be administered with Aliskiren in patients with diabetes.', 'Rifampicin and fluconazole reduce levels of active metabolite of Acusan. Concomitant use of Acusan and hydrochlorothiazide may lead to potentiation of the antihypertensive effects. Concomitant use of potassium-sparing diuretics (eg, spironolactone, triamterene, amiloride), potassium supplements or salt substitutes containing potassium may lead to increases in serum potassium. The antihypertensive effect of losartan may be attenuated by the non-steroidal anti-inflammatory drug indomethacin. The use of ACE-inhibitor, angiotensin receptor antagonist, an anti-inflammatory drug and a thiazide diuretic at the same time increases the risk of renal impairment.', 'Use of Acusan during the second and third trimesters of pregnancy reduces fetal renal function and increases fetal and neonatal morbidity and death. In patients who are intravascularly volume-depleted (e.g., those treated with high-dose diuretics), symptomatic hypotension may occur. Plasma concentration of Acusan is significantly increased in cirrhotic patients. Changes in renal function including renal failure have been reported in renal impaired patient.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (10, 'Lipicon', 'Incepta', 8.02, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (10, 'Atova', 'Beximco', 12.0, 4.8, 4.2, 4.8);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (10, 'Anzitor', 'Square', 12.0, 4.5, 4.5, 4.5);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (10, 'Tigit', 'Opsonin', 5.0, 4.5, 4.8, 4.2);


