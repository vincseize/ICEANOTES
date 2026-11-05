-- ICEANOTES backup 2026-11-05_075931

-- Table etudiants
CREATE TABLE etudiants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    prenom TEXT NOT NULL,
    promotion_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id)
);

INSERT INTO "etudiants" (id, nom, prenom, promotion_id, actif) VALUES (1, 'Casimir', 'Sarah', 3, 1);
INSERT INTO "etudiants" (id, nom, prenom, promotion_id, actif) VALUES (2, 'Vartel', 'Ethan', 3, 1);
INSERT INTO "etudiants" (id, nom, prenom, promotion_id, actif) VALUES (3, 'Hospice', 'Caryl', 3, 1);
INSERT INTO "etudiants" (id, nom, prenom, promotion_id, actif) VALUES (4, 'Ramphort', 'Lenny', 6, 1);
INSERT INTO "etudiants" (id, nom, prenom, promotion_id, actif) VALUES (5, 'Lof', 'Tao', 6, 1);
INSERT INTO "etudiants" (id, nom, prenom, promotion_id, actif) VALUES (6, 'Lapoulia', 'Matthieu', 6, 1);

-- Table filieres
CREATE TABLE filieres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE,
    section_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (section_id) REFERENCES sections(id)
);

INSERT INTO "filieres" (id, nom, section_id, actif) VALUES (1, 'Sciences du Numériques', 1, 1);
INSERT INTO "filieres" (id, nom, section_id, actif) VALUES (2, 'Sciences Sociales', 1, 0);
INSERT INTO "filieres" (id, nom, section_id, actif) VALUES (3, 'Journalisme', 1, 0);

-- Table modules
CREATE TABLE "modules" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    niveau TEXT NOT NULL,
    filiere_id INTEGER NOT NULL,
    professeur_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (professeur_id) REFERENCES users(id)
);

INSERT INTO "modules" (id, nom, niveau, filiere_id, professeur_id, actif) VALUES (1, 'Données Modélisation (UML)', 'L3', 1, 1, 1);
INSERT INTO "modules" (id, nom, niveau, filiere_id, professeur_id, actif) VALUES (2, 'Approche philosophique de l''intelligence artificielle', 'L3', 1, 1, 1);
INSERT INTO "modules" (id, nom, niveau, filiere_id, professeur_id, actif) VALUES (3, 'Stage et Professionnalisation', 'L2', 1, 1, 1);
INSERT INTO "modules" (id, nom, niveau, filiere_id, professeur_id, actif) VALUES (4, 'Structures de données avancées', 'L2', 1, 1, 1);

-- Table note_types
CREATE TABLE note_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL UNIQUE,
                sur REAL NOT NULL DEFAULT 20,
                ordre INTEGER NOT NULL DEFAULT 0,
                actif INTEGER NOT NULL DEFAULT 1
            , coef REAL NOT NULL DEFAULT 1);

INSERT INTO "note_types" (id, nom, sur, ordre, actif, coef) VALUES (1, 'CC1', 20, 1, 1, 1);
INSERT INTO "note_types" (id, nom, sur, ordre, actif, coef) VALUES (2, 'CC2', 20, 2, 1, 1);
INSERT INTO "note_types" (id, nom, sur, ordre, actif, coef) VALUES (3, 'Examen', 20, 3, 1, 1);

-- Table notes
CREATE TABLE "notes" (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                etudiant_id INTEGER NOT NULL,
                module_id INTEGER NOT NULL,
                note_type_id INTEGER NOT NULL,
                valeur REAL NOT NULL,
                date_saisie DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (etudiant_id) REFERENCES etudiants(id),
                FOREIGN KEY (module_id) REFERENCES modules(id),
                FOREIGN KEY (note_type_id) REFERENCES note_types(id),
                UNIQUE(etudiant_id, module_id, note_type_id)
            );

INSERT INTO "notes" (id, etudiant_id, module_id, note_type_id, valeur, date_saisie) VALUES (1, 1, 1, 1, 13, '2026-11-05 05:33:51');
INSERT INTO "notes" (id, etudiant_id, module_id, note_type_id, valeur, date_saisie) VALUES (2, 3, 1, 1, 13, '2026-11-05 05:33:58');
INSERT INTO "notes" (id, etudiant_id, module_id, note_type_id, valeur, date_saisie) VALUES (3, 2, 1, 1, 12, '2026-11-05 05:34:15');

-- Table promotions
CREATE TABLE promotions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    ordre INTEGER NOT NULL DEFAULT 0,
    promo TEXT DEFAULT NULL,
    annee TEXT NOT NULL,
    filiere_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    UNIQUE(nom, annee, filiere_id, promo)
);

INSERT INTO "promotions" (id, nom, ordre, promo, annee, filiere_id, actif) VALUES (3, 'L3', 3, 'Stitch, Lilo et Agoulou Granfal', '2026-2027', 1, 1);
INSERT INTO "promotions" (id, nom, ordre, promo, annee, filiere_id, actif) VALUES (6, 'L2', 0, NULL, '2026-2027', 1, 1);

-- Table remember_tokens
CREATE TABLE remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO "remember_tokens" (id, user_id, token_hash, expires_at) VALUES (2, 1, 'a530af56c8df5ac7d48cf3e6ec011f6f1d1b8f0fa6b1bd26d3461c8439bf9625', '2026-12-05 06:23:21');

-- Table sections
CREATE TABLE sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL UNIQUE
);

INSERT INTO "sections" (id, nom) VALUES (1, 'ICEA');

-- Table users
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    nom TEXT,
    prenom TEXT,
    user_type_id INTEGER NOT NULL,
    actif INTEGER NOT NULL DEFAULT 1,
    protected INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (user_type_id) REFERENCES users_types(id)
);

INSERT INTO "users" (id, email, password, nom, prenom, user_type_id, actif, protected) VALUES (1, 'charles.pottier@icea-edu.fr', '$argon2id$v=19$m=65536,t=4,p=2$a3lxb084cFpJU1dhVFh4SQ$AZa6kohXB3YLbdIZHuqQrLBIurGhx9n8cYN3AiWHP38', 'Pottier', 'Charles', 1, 1, 1);
INSERT INTO "users" (id, email, password, nom, prenom, user_type_id, actif, protected) VALUES (2, 'sylvio.prudent@icea-edu.fr', '$argon2id$v=19$m=65536,t=4,p=2$eEkyUUlxVFdOdzNaRXFnZw$dhDaEmzB1Ub9dWJg15ZphzfSuQ49GPcxobnJynHwCWc', 'Prudent', 'Sylvio', 2, 1, 1);

-- Table users_types
CREATE TABLE users_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL UNIQUE
);

INSERT INTO "users_types" (id, label) VALUES (1, 'admin');
INSERT INTO "users_types" (id, label) VALUES (2, 'superviseur');
INSERT INTO "users_types" (id, label) VALUES (3, 'user');
INSERT INTO "users_types" (id, label) VALUES (4, 'guest');

