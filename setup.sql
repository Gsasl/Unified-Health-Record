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
