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
