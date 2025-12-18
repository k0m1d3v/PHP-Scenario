-- =========================
-- DB: ArtigianiFinder (Admin CRUD + filtri)
-- MySQL / MariaDB
-- =========================

DROP DATABASE IF EXISTS artigiani_finder;
CREATE DATABASE artigiani_finder
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE artigiani_finder;

-- -------------------------
-- TABELLA: citta
-- -------------------------
CREATE TABLE citta (
  idCitta INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  provincia CHAR(2) NOT NULL,
  UNIQUE KEY uk_citta (nome, provincia)
) ENGINE=InnoDB;

-- -------------------------
-- TABELLA: professione
-- -------------------------
CREATE TABLE professione (
  idProfessione INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(60) NOT NULL,
  UNIQUE KEY uk_professione_nome (nome)
) ENGINE=InnoDB;

-- -------------------------
-- TABELLA: professionista
-- -------------------------
CREATE TABLE professionista (
  idProfessionista INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  descrizione TEXT NULL,
  tariffa_oraria DECIMAL(7,2) NOT NULL CHECK (tariffa_oraria >= 0),
  disponibilita TINYINT(1) NOT NULL DEFAULT 1,
  idCitta INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_professionista_email (email),
  KEY idx_professionista_citta (idCitta),
  KEY idx_professionista_tariffa (tariffa_oraria),
  KEY idx_professionista_disp (disponibilita),

  CONSTRAINT fk_professionista_citta
    FOREIGN KEY (idCitta) REFERENCES citta(idCitta)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -------------------------
-- TABELLA PONTE N-M: professionista_professione
-- -------------------------
CREATE TABLE professionista_professione (
  idProfessionista INT NOT NULL,
  idProfessione INT NOT NULL,
  PRIMARY KEY (idProfessionista, idProfessione),
  KEY idx_pp_professione (idProfessione),

  CONSTRAINT fk_pp_professionista
    FOREIGN KEY (idProfessionista) REFERENCES professionista(idProfessionista)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_pp_professione
    FOREIGN KEY (idProfessione) REFERENCES professione(idProfessione)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================
-- DATI DEMO
-- =========================

INSERT INTO citta (nome, provincia) VALUES
('Lodi','LO'),
('Milano','MI'),
('Piacenza','PC'),
('Pavia','PV'),
('Cremona','CR');

INSERT INTO professione (nome) VALUES
('Idraulico'),
('Elettricista'),
('Cartongessista'),
('Imbianchino'),
('Muratore'),
('Fabbro'),
('Giardiniere'),
('Tecnico Caldaie');

INSERT INTO professionista
(nome, telefono, email, descrizione, tariffa_oraria, disponibilita, idCitta)
VALUES
('Marco Riva', '+39 333 111 2233', 'marco.riva@example.com',
 'Riparazioni idrauliche, perdite, sanitari.', 35.00, 1,
 (SELECT idCitta FROM citta WHERE nome='Lodi' AND provincia='LO')),

('Sara Bianchi', '+39 333 222 3344', 'sara.bianchi@example.com',
 'Impianti elettrici civili, prese, salvavita.', 40.00, 1,
 (SELECT idCitta FROM citta WHERE nome='Milano' AND provincia='MI')),

('Luca Ferri', '+39 333 333 4455', 'luca.ferri@example.com',
 'Cartongesso: controsoffitti e pareti.', 30.00, 0,
 (SELECT idCitta FROM citta WHERE nome='Pavia' AND provincia='PV')),

('Giulia Conti', '+39 333 444 5566', 'giulia.conti@example.com',
 'Imbiancature interne/esterne, finiture.', 28.50, 1,
 (SELECT idCitta FROM citta WHERE nome='Piacenza' AND provincia='PC')),

('Davide Sala', '+39 333 555 6677', 'davide.sala@example.com',
 'Manutenzione caldaie, controlli e sostituzioni.', 45.00, 1,
 (SELECT idCitta FROM citta WHERE nome='Cremona' AND provincia='CR')),

('Nicolò Rizzi', '+39 333 666 7788', 'nicolo.rizzi@example.com',
 'Serrature, aperture porte, cancelli.', 50.00, 1,
 (SELECT idCitta FROM citta WHERE nome='Milano' AND provincia='MI'));

-- Associazioni professionista-professione (N-M)
-- Marco Riva: Idraulico, Tecnico Caldaie
INSERT INTO professionista_professione (idProfessionista, idProfessione)
SELECT p.idProfessionista, pr.idProfessione
FROM professionista p
JOIN professione pr ON pr.nome IN ('Idraulico','Tecnico Caldaie')
WHERE p.email='marco.riva@example.com';

-- Sara Bianchi: Elettricista
INSERT INTO professionista_professione (idProfessionista, idProfessione)
SELECT p.idProfessionista, pr.idProfessione
FROM professionista p
JOIN professione pr ON pr.nome IN ('Elettricista')
WHERE p.email='sara.bianchi@example.com';

-- Luca Ferri: Cartongessista, Muratore
INSERT INTO professionista_professione (idProfessionista, idProfessione)
SELECT p.idProfessionista, pr.idProfessione
FROM professionista p
JOIN professione pr ON pr.nome IN ('Cartongessista','Muratore')
WHERE p.email='luca.ferri@example.com';

-- Giulia Conti: Imbianchino
INSERT INTO professionista_professione (idProfessionista, idProfessione)
SELECT p.idProfessionista, pr.idProfessione
FROM professionista p
JOIN professione pr ON pr.nome IN ('Imbianchino')
WHERE p.email='giulia.conti@example.com';

-- Davide Sala: Tecnico Caldaie, Idraulico
INSERT INTO professionista_professione (idProfessionista, idProfessione)
SELECT p.idProfessionista, pr.idProfessione
FROM professionista p
JOIN professione pr ON pr.nome IN ('Tecnico Caldaie','Idraulico')
WHERE p.email='davide.sala@example.com';

-- Nicolò Rizzi: Fabbro
INSERT INTO professionista_professione (idProfessionista, idProfessione)
SELECT p.idProfessionista, pr.idProfessione
FROM professionista p
JOIN professione pr ON pr.nome IN ('Fabbro')
WHERE p.email='nicolo.rizzi@example.com';
