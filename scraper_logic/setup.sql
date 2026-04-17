SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS USER_MED_LOG;
DROP TABLE IF EXISTS Health_Records;
DROP TABLE IF EXISTS Users;
DROP TABLE IF EXISTS Drug_Conflicts;
DROP TABLE IF EXISTS Brands;
DROP TABLE IF EXISTS Generics;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. CREATE PATIENT ENTITIES
CREATE TABLE Users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    FullName VARCHAR(100),
    Email VARCHAR(100),
    PasswordHash VARCHAR(255)
);

CREATE TABLE Health_Records (
    RecordID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT UNIQUE,
    BloodType VARCHAR(5),
    WeightKG DECIMAL(5,2),
    KnownAllergies TEXT,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
);

-- 2. CREATE MEDICATION ENTITIES
CREATE TABLE Generics (
    GenericID INT AUTO_INCREMENT PRIMARY KEY,
    GenericName VARCHAR(100) NOT NULL UNIQUE,
    TypicalDuration VARCHAR(100),
    BlackBoxWarn TINYINT(1) DEFAULT 0,
    StopIfCondition TEXT,
    DietWarning TEXT
);

CREATE TABLE Brands (
    BrandID INT AUTO_INCREMENT PRIMARY KEY,
    GenericID INT,
    BrandName VARCHAR(100) NOT NULL,
    Manufacturer VARCHAR(100),
    UnitPrice DECIMAL(10, 2),
    Stock INT DEFAULT 50,
    EfficacyRating DECIMAL(3, 2),
    PriceRating DECIMAL(3, 2),
    PopularityRating DECIMAL(3, 2),
    FOREIGN KEY (GenericID) REFERENCES Generics(GenericID) ON DELETE CASCADE
);

-- 3. CREATE BRIDGES & LOGS
CREATE TABLE USER_MED_LOG (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    BrandID INT,
    Status VARCHAR(20),
    DateAdded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (BrandID) REFERENCES Brands(BrandID) ON DELETE CASCADE
);

CREATE TABLE Drug_Conflicts (
    ConflictID INT AUTO_INCREMENT PRIMARY KEY,
    GenericID_1 INT,
    GenericID_2 INT,
    Severity VARCHAR(50),
    AlertMessage TEXT,
    FOREIGN KEY (GenericID_1) REFERENCES Generics(GenericID) ON DELETE CASCADE,
    FOREIGN KEY (GenericID_2) REFERENCES Generics(GenericID) ON DELETE CASCADE
);

-- 4. INSERT SEED DATA
INSERT INTO Users (UserID, FullName) VALUES (1, 'Test Patient');

INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (1, 'Ketorolac Tromethamine', 'Standard as prescribed', 0, 'Ceftriaxone should not be given to patients with a history of hypersensitivity to cephalosporin antibiotics.', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (1, 'Torax', 'Square', 130.0, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (1, 'Rollac', 'Incepta', 100.0, 4.8, 4.5, 4.5);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (2, 'Losartan Potassium', 'Standard as prescribed', 0, '', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (2, 'Osartil', 'Incepta', 10.0, 5.0, 4.5, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (2, 'Angilock', 'Square', 6.0, 4.8, 4.0, 4.8);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (3, 'Isotretinoin', 'maximum of 5 days', 1, 'It is contraindicated in known hypersensitivity to Paracetamol.', '* রেজিস্টার্ড চিকিৎসকের পরামর্শ মোতাবেক ঔষধ সেবন করুন ''Actol 120 mg/5 ml Suspension should be taken with food or milk to prevent upset stomach.');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (3, 'Tretin', 'Beximco', 20.69, 4.8, 4.0, 4.0);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (4, 'Warfarin Sodium', 'Standard as prescribed', 0, 'Levofloxacin is contraindicated in patients with a history of hypersensitivity to levofloxacin, quinolone antimicrobial agents, or any other components of this product.', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (4, 'Orfarin', 'Incepta', 8.03, 5.0, 4.5, 4.5);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (5, 'Metformin Hydrochloride', 'Standard as prescribed', 0, 'No specific contraindications but caution is required in patients with hypersensitivity to any constituents of the formulation.', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (5, 'Comet', 'Square', 201.0, 5.0, 5.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (5, 'Daomin', 'Beximco', 520.0, 4.5, 5.0, 4.5);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (6, 'Atorvastatin', 'Standard as prescribed', 0, '', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (6, 'Lipicon', 'Incepta', 8.02, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (6, 'Atova', 'Beximco', 12.0, 4.8, 4.2, 4.8);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (7, 'Azithromycin', 'Standard as prescribed', 0, '', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (7, 'Zimax', 'Square', 40.0, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (7, 'Tridosil', 'Incepta', 35.0, 4.8, 4.5, 4.8);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (8, 'Clopidogrel', 'Standard as prescribed', 0, 'Lansoprazole is contraindicated in patients with known hypersensitivity to any component of the formulation.', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (8, 'Plagrin', 'Square', 5.02, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (8, 'Anclog', 'Incepta', 3.0, 4.8, 4.5, 4.5);
INSERT INTO Generics (GenericID, GenericName, TypicalDuration, BlackBoxWarn, StopIfCondition, DietWarning) VALUES (9, 'Omeprazole', 'Standard as prescribed', 0, 'Omeprazole is contraindicated in patients with known hypersensitivity to it.', '');
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (9, 'Seclo', 'Square', 6.0, 5.0, 4.0, 5.0);
INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES (9, 'Losectil', 'Eskayef', 5.0, 4.8, 4.2, 4.7);

INSERT INTO Drug_Conflicts (GenericID_1, GenericID_2, Severity, AlertMessage) VALUES (1, 2, 'CRITICAL', 'Co-administration increases risk of severe acute renal failure.');
INSERT INTO Drug_Conflicts (GenericID_1, GenericID_2, Severity, AlertMessage) VALUES (6, 7, 'HIGH', 'Increased risk of myopathy and rhabdomyolysis.');
INSERT INTO Drug_Conflicts (GenericID_1, GenericID_2, Severity, AlertMessage) VALUES (9, 8, 'HIGH', 'Omeprazole significantly reduces the antiplatelet effect of Clopidogrel.');
