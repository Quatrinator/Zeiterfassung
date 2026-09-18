SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS schema_versions (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
CREATE TABLE companies (
 id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(150) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'EUR'
) ENGINE=InnoDB;
INSERT INTO companies VALUES (1,'Externe Firma','EUR');
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(64) COLLATE utf8mb4_0900_as_ci NOT NULL UNIQUE,
 display_name VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL,
 kind ENUM('internal','customer') NOT NULL DEFAULT 'internal',
 company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
 active BOOLEAN NOT NULL DEFAULT TRUE, must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
 initial_expires_at DATETIME NULL, auth_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE,
 system_key VARCHAR(30) NULL UNIQUE, is_admin BOOLEAN NOT NULL DEFAULT FALSE,
 version INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE permissions (name VARCHAR(60) PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO permissions VALUES ('entries.manage_team'),('finance.view'),('rates.manage'),('entries.release'),('billing.finalize'),('billing.correct'),('audit.view');
CREATE TABLE role_permissions (
 role_id BIGINT UNSIGNED NOT NULL, permission VARCHAR(60) NOT NULL,
 PRIMARY KEY(role_id,permission), FOREIGN KEY(role_id) REFERENCES roles(id), FOREIGN KEY(permission) REFERENCES permissions(name)
) ENGINE=InnoDB;
CREATE TABLE user_roles (
 user_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(user_id,role_id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(role_id) REFERENCES roles(id)
) ENGINE=InnoDB;
CREATE TABLE user_permissions (
 user_id BIGINT UNSIGNED NOT NULL, permission VARCHAR(60) NOT NULL,
 PRIMARY KEY(user_id,permission), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(permission) REFERENCES permissions(name)
) ENGINE=InnoDB;
INSERT INTO roles (id,name,system_key,is_admin) VALUES (1,'Teammitglied','member',0),(2,'Abrechnung','billing',0),(3,'Administrator','admin',1),(4,'Kundenzugang','customer',0);
INSERT INTO role_permissions SELECT 2,name FROM permissions;
CREATE TABLE hourly_rates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
 user_id BIGINT UNSIGNED NULL, valid_from DATE NOT NULL, valid_until DATE NULL,
 cents_per_hour INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id),
 CHECK(valid_until IS NULL OR valid_until>valid_from), CHECK(cents_per_hour<=10000000),
 INDEX rate_lookup(company_id,user_id,valid_from,valid_until)
) ENGINE=InnoDB;
CREATE TABLE time_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL DEFAULT 1, user_id BIGINT UNSIGNED NOT NULL,
 kind ENUM('time','adjustment') NOT NULL DEFAULT 'time', original_id BIGINT UNSIGNED NULL,
 service_date DATE NOT NULL, minutes INT NOT NULL, description VARCHAR(500) NOT NULL,
 category VARCHAR(40) NOT NULL DEFAULT '', billable BOOLEAN NOT NULL DEFAULT TRUE,
 rate_cents INT UNSIGNED NULL, amount_cents BIGINT NULL,
 target_minutes INT NULL, target_amount_cents BIGINT NULL,
 status ENUM('draft','released','billed') NOT NULL DEFAULT 'draft',
 version INT UNSIGNED NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
 updated_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 released_at DATETIME NULL, billed_at DATETIME NULL, invoice_reference VARCHAR(100) NOT NULL DEFAULT '',
 deleted_at DATETIME NULL,
 FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id),
 FOREIGN KEY(original_id) REFERENCES time_entries(id), FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id),
 CHECK((kind='time' AND original_id IS NULL AND minutes BETWEEN 1 AND 1440) OR (kind='adjustment' AND original_id IS NOT NULL)),
 CHECK(kind='adjustment' OR amount_cents IS NULL OR amount_cents>=0),
 INDEX(company_id,service_date), INDEX(user_id,service_date), INDEX(company_id,status,service_date), INDEX(original_id,status)
) ENGINE=InnoDB;
CREATE TABLE audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_id BIGINT UNSIGNED NULL,
 action VARCHAR(60) NOT NULL, entity VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NULL,
 before_json JSON NULL, after_json JSON NULL, reason VARCHAR(500) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(actor_id) REFERENCES users(id), INDEX(created_at)
) ENGINE=InnoDB;
CREATE TABLE submission_keys (
 user_id BIGINT UNSIGNED NOT NULL, request_key VARCHAR(64) NOT NULL, fingerprint CHAR(64) NOT NULL,
 result_json JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,request_key), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE login_attempts (
 bucket CHAR(64) PRIMARY KEY, failures INT UNSIGNED NOT NULL DEFAULT 0,
 window_start DATETIME NOT NULL, blocked_until DATETIME NULL
) ENGINE=InnoDB;
INSERT INTO schema_versions(version) VALUES ('001_initial');

-- Migration 002_activity_templates (keep synchronized with migrations/002_activity_templates.sql).
CREATE TABLE IF NOT EXISTS activity_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
 label VARCHAR(150) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(company_id) REFERENCES companies(id),
 UNIQUE KEY template_label(company_id,label)
) ENGINE=InnoDB;
INSERT IGNORE INTO permissions(name) VALUES ('templates.manage');
INSERT IGNORE INTO activity_templates(company_id,label) VALUES
 (1,'Benutzersupport'),(1,'Updates installieren'),(1,'Backup prüfen'),
 (1,'Arbeitsplatz einrichten'),(1,'Netzwerk / VPN betreuen'),(1,'Abstimmung / Beratung');
INSERT IGNORE INTO schema_versions(version) VALUES ('002_activity_templates');
