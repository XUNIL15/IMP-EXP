-- ============================================================
-- BASE DE DONNEES : Gestion Import/Export
-- Généré pour MySQL 5.7+ / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS import_export 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE import_export;

-- ============================================================
-- TABLE : transitaires
-- ============================================================
CREATE TABLE IF NOT EXISTS transitaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    telephone VARCHAR(20),
    email VARCHAR(100),
    adresse TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : clients
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    telephone VARCHAR(20),
    email VARCHAR(100),
    adresse TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : arrivages
-- ============================================================
CREATE TABLE IF NOT EXISTS arrivages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transitaire_id INT NOT NULL,
    date_arrivee DATE NOT NULL,
    nb_colis_total INT NOT NULL DEFAULT 0,
    poids_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cout_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    devise ENUM('FCFA','EUR','USD') DEFAULT 'FCFA',
    notes TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transitaire_id) REFERENCES transitaires(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : colis
-- ============================================================
CREATE TABLE IF NOT EXISTS colis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    arrivage_id INT NOT NULL,
    code_reel VARCHAR(50) NOT NULL,
    code_complet VARCHAR(80) NOT NULL UNIQUE,
    type ENUM('individuel','mixte') NOT NULL DEFAULT 'individuel',
    poids DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    montant DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (arrivage_id) REFERENCES arrivages(id) ON DELETE CASCADE,
    INDEX idx_arrivage (arrivage_id),
    INDEX idx_code_complet (code_complet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : colis_proprietaires
-- ============================================================
CREATE TABLE IF NOT EXISTS colis_proprietaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colis_id INT NOT NULL,
    client_id INT NOT NULL,
    poids DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    montant_du DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    montant_paye DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    solde DECIMAL(15,2) GENERATED ALWAYS AS (montant_du - montant_paye) STORED,
    statut ENUM('non_paye','partiel','paye') DEFAULT 'non_paye',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colis_id) REFERENCES colis(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    INDEX idx_colis (colis_id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : paiements
-- ============================================================
CREATE TABLE IF NOT EXISTS paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    colis_proprietaire_id INT NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    date_paiement DATE NOT NULL,
    mode_paiement ENUM('espece','virement','cheque','mobile_money','autre') DEFAULT 'espece',
    reference VARCHAR(100),
    notes TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (colis_proprietaire_id) REFERENCES colis_proprietaires(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_date (date_paiement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGER : mise à jour statut paiement
-- ============================================================
DELIMITER $$
CREATE TRIGGER after_paiement_insert
AFTER INSERT ON paiements
FOR EACH ROW
BEGIN
    UPDATE colis_proprietaires 
    SET 
        montant_paye = (
            SELECT COALESCE(SUM(montant), 0) 
            FROM paiements 
            WHERE colis_proprietaire_id = NEW.colis_proprietaire_id
        ),
        statut = CASE
            WHEN (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE colis_proprietaire_id = NEW.colis_proprietaire_id) >= montant_du THEN 'paye'
            WHEN (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE colis_proprietaire_id = NEW.colis_proprietaire_id) > 0 THEN 'partiel'
            ELSE 'non_paye'
        END
    WHERE id = NEW.colis_proprietaire_id;
END$$
DELIMITER ;

-- ============================================================
-- DONNEES DE TEST
-- ============================================================

INSERT INTO transitaires (nom, telephone, email, adresse) VALUES
('Transitaire Alpha', '+221 77 100 0001', 'alpha@transit.sn', 'Dakar, Zone Portuaire'),
('LogistiqueX', '+221 78 200 0002', 'contact@logistiquex.sn', 'Abidjan, Port Autonome'),
('FretPlus SARL', '+221 76 300 0003', 'info@fretplus.sn', 'Lomé, Port de Lomé');

INSERT INTO clients (nom, telephone, email, adresse) VALUES
('Mamadou Diallo', '+221 77 111 1111', 'mamadou@email.com', 'Dakar, Médina'),
('Fatou Sow', '+221 78 222 2222', 'fatou@email.com', 'Pikine'),
('Ibrahima Ndiaye', '+221 76 333 3333', NULL, 'Thiès'),
('Aissatou Balde', '+221 75 444 4444', 'aissatou@email.com', 'Saint-Louis'),
('Oumar Traoré', '+221 77 555 5555', NULL, 'Ziguinchor'),
('Marie Dupont', '+221 78 666 6666', 'marie@email.com', 'Dakar, Plateau');

INSERT INTO arrivages (transitaire_id, date_arrivee, nb_colis_total, poids_total, cout_total, devise) VALUES
(1, '2026-03-25', 3, 125.50, 450000, 'FCFA'),
(2, '2026-03-27', 2, 88.00, 320000, 'FCFA'),
(1, '2026-03-29', 4, 210.00, 780000, 'FCFA');

INSERT INTO colis (arrivage_id, code_reel, code_complet, type, poids, montant) VALUES
(1, 'A109', 'A109_250326', 'individuel', 45.00, 162000),
(1, 'B422', 'B422_250326', 'mixte', 50.00, 180000),
(1, 'C001', 'C001_250326', 'individuel', 30.50, 108000),
(2, 'D301', 'D301_270326', 'individuel', 40.00, 144000),
(2, 'E100', 'E100_270326', 'mixte', 48.00, 176000),
(3, 'F200', 'F200_290326', 'individuel', 55.00, 198000),
(3, 'G150', 'G150_290326', 'mixte', 60.00, 216000),
(3, 'H099', 'H099_290326', 'individuel', 50.00, 180000),
(3, 'I777', 'I777_290326', 'individuel', 45.00, 162000);

INSERT INTO colis_proprietaires (colis_id, client_id, poids, montant_du, montant_paye, statut) VALUES
(1, 1, 45.00, 162000, 100000, 'partiel'),
(2, 2, 25.00, 90000, 90000, 'paye'),
(2, 3, 25.00, 90000, 0, 'non_paye'),
(3, 4, 30.50, 108000, 108000, 'paye'),
(4, 5, 40.00, 144000, 50000, 'partiel'),
(5, 1, 24.00, 88000, 0, 'non_paye'),
(5, 6, 24.00, 88000, 88000, 'paye'),
(6, 2, 55.00, 198000, 198000, 'paye'),
(7, 3, 30.00, 108000, 50000, 'partiel'),
(7, 4, 30.00, 108000, 0, 'non_paye'),
(8, 5, 50.00, 180000, 180000, 'paye'),
(9, 6, 45.00, 162000, 0, 'non_paye');

INSERT INTO paiements (client_id, colis_proprietaire_id, montant, date_paiement, mode_paiement) VALUES
(1, 1, 100000, '2026-03-25', 'espece'),
(2, 2, 90000, '2026-03-25', 'espece'),
(4, 4, 108000, '2026-03-25', 'virement'),
(5, 5, 50000, '2026-03-27', 'mobile_money'),
(6, 7, 88000, '2026-03-27', 'espece'),
(2, 8, 198000, '2026-03-29', 'virement'),
(3, 9, 50000, '2026-03-29', 'espece'),
(5, 11, 180000, '2026-03-29', 'espece');

-- ============================================================
-- TABLE : utilisateurs
-- Gestion des accès au système
-- ============================================================
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin', 'gestionnaire') NOT NULL DEFAULT 'gestionnaire',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    derniere_connexion DATETIME NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANT : Lancez setup.php dans votre navigateur pour créer le compte administrateur initial.
-- Exemple : http://localhost/import-export/setup.php
