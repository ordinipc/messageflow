-- Schema MySQL di SEO & GEO Audit.
-- Serve solo se in config.php si imposta 'driver' => 'mysql'.
-- Con SQLite (impostazione predefinita) le tabelle vengono create da sole.

CREATE TABLE IF NOT EXISTS audit (
	id              INT AUTO_INCREMENT PRIMARY KEY,
	sito_nome       VARCHAR(255),
	sito_url        VARCHAR(255),
	file_origine    VARCHAR(255),
	creato_il       VARCHAR(255),
	punteggio       INT,
	problemi_totali INT,
	articoli        INT,
	pagine          INT,
	media           INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documento (
	id              INT AUTO_INCREMENT PRIMARY KEY,
	audit_id        INT NOT NULL,
	wp_id           VARCHAR(255),
	tipo            VARCHAR(255),
	stato           VARCHAR(255),
	titolo          LONGTEXT,
	slug            VARCHAR(255),
	url             LONGTEXT,
	percorso        VARCHAR(255),
	parole          INT,
	h1              INT,
	h2              INT,
	immagini        INT,
	link_in         INT,
	link_out        INT,
	focus_keyword   VARCHAR(255),
	seo_title       LONGTEXT,
	seo_description LONGTEXT,
	gulpease        INT,
	pubblicato      VARCHAR(255),
	modificato      VARCHAR(255),
	INDEX idx_doc_audit (audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area (
	id        INT AUTO_INCREMENT PRIMARY KEY,
	audit_id  INT NOT NULL,
	chiave    VARCHAR(255),
	etichetta VARCHAR(255),
	punteggio INT,
	rilievi   INT,
	peso      INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rilievo (
	id         INT AUTO_INCREMENT PRIMARY KEY,
	audit_id   INT NOT NULL,
	regola     VARCHAR(255),
	area       VARCHAR(255),
	gravita    VARCHAR(255),
	titolo     LONGTEXT,
	perche     LONGTEXT,
	soluzione  LONGTEXT,
	automatico INT,
	occorrenze INT,
	INDEX idx_ril_audit (audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS occorrenza (
	id          INT AUTO_INCREMENT PRIMARY KEY,
	rilievo_id  INT NOT NULL,
	riferimento LONGTEXT,
	dettaglio   LONGTEXT,
	INDEX idx_occ_ril (rilievo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS triage (
	id             INT AUTO_INCREMENT PRIMARY KEY,
	audit_id       INT NOT NULL,
	documento_id   INT NOT NULL,
	categoria      VARCHAR(255),
	qualita        INT,
	intento        VARCHAR(255),
	similarita_max DOUBLE,
	motivo         LONGTEXT,
	azione         LONGTEXT,
	redirect_a     LONGTEXT,
	INDEX idx_tri_audit (audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meta_piano (
	id                   INT AUTO_INCREMENT PRIMARY KEY,
	audit_id             INT NOT NULL,
	documento_id         INT NOT NULL,
	title_nuovo          LONGTEXT,
	description_nuova    LONGTEXT,
	excerpt_nuovo        LONGTEXT,
	slug_nuovo           VARCHAR(255),
	title_cambiato       INT,
	description_cambiata INT,
	slug_cambiato        INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS link_piano (
	id        INT AUTO_INCREMENT PRIMARY KEY,
	audit_id  INT NOT NULL,
	da        VARCHAR(255),
	da_titolo LONGTEXT,
	a         VARCHAR(255),
	a_titolo  LONGTEXT,
	anchor    LONGTEXT,
	motivo    VARCHAR(255),
	punteggio DOUBLE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
