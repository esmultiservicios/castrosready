-- CASTRO'S READY CMS - CUMULATIVE DATABASE UPDATE (SAFE TO RE-RUN)
-- Designed for older MySQL and MariaDB environments used by the CMS hosting.
-- information_schema checks, CREATE TABLE IF NOT EXISTS, INSERT IGNORE
-- and NOT EXISTS guards prevent duplicate schema/content when re-run.
-- Do NOT use database.sql on an existing production database.

SET NAMES utf8mb4;

-- PUBLIC FORM ANTISPAM RATE LIMITING
-- Stores only one-way hashes of the visitor IP/session identifiers.
CREATE TABLE IF NOT EXISTS public_form_rate_limits (
  action_key VARCHAR(40) NOT NULL,
  identity_hash CHAR(64) NOT NULL,
  window_started_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  blocked_until DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (action_key, identity_hash),
  KEY idx_public_form_rate_updated (updated_at),
  KEY idx_public_form_rate_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EMAIL DELIVERY: SMTP / MICROSOFT GRAPH
-- Creates the structures only when an older production database does not have them.
CREATE TABLE IF NOT EXISTS correo_tipo (
  correo_tipo_id INT NOT NULL,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correo (
  correo_id INT NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico de la configuracion de correo',
  correo_tipo_id INT NOT NULL COMMENT 'Tipo de correo',
  metodo_envio ENUM('SMTP','GRAPH') NOT NULL DEFAULT 'SMTP' COMMENT 'SMTP o Microsoft Graph',
  server VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Servidor SMTP o graph.microsoft.com',
  correo VARCHAR(180) NOT NULL COMMENT 'Correo emisor',
  password TEXT NULL COMMENT 'Contrasena SMTP cifrada',
  port INT NOT NULL DEFAULT 587 COMMENT 'Puerto SMTP; Graph usa 0',
  smtp_secure VARCHAR(10) NOT NULL DEFAULT 'tls' COMMENT 'tls o ssl',
  tenant_id VARCHAR(150) DEFAULT NULL,
  client_id VARCHAR(150) DEFAULT NULL,
  client_secret TEXT NULL COMMENT 'Client secret cifrado',
  graph_user VARCHAR(180) DEFAULT NULL,
  save_to_sent_items TINYINT(1) NOT NULL DEFAULT 1,
  estado TINYINT NOT NULL DEFAULT 1 COMMENT '1 Activo, 2 Inactivo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (correo_id),
  KEY idx_correo_tipo_estado (correo_tipo_id,estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='correo_tipo' AND COLUMN_NAME='nombre'
      AND CHARACTER_MAXIMUM_LENGTH < 30
  ),
  'ALTER TABLE correo_tipo MODIFY COLUMN nombre VARCHAR(30) NOT NULL',
  'SELECT 1'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='correo' AND COLUMN_NAME='correo_id'
      AND EXTRA NOT LIKE '%auto_increment%'
  ),
  'ALTER TABLE correo MODIFY COLUMN correo_id INT NOT NULL AUTO_INCREMENT',
  'SELECT 1'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='correo' AND COLUMN_NAME='updated_at'
  ),
  'SELECT 1',
  'ALTER TABLE correo ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_registro'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='correo' AND INDEX_NAME='idx_correo_tipo_estado'
  ),
  'SELECT 1',
  'ALTER TABLE correo ADD INDEX idx_correo_tipo_estado (correo_tipo_id,estado)'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

INSERT INTO correo_tipo (correo_tipo_id,nombre) VALUES
(1,'Website Alerts'),(2,'Admin Security'),(3,'Estimate Requests'),(4,'Auto Replies')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO settings(setting_key,setting_value) VALUES
('email','castrosreadycompany@gmail.com'),
('estimate_notification_email','castrosreadycompany@gmail.com'),
('estimate_copy_email','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

CREATE TABLE IF NOT EXISTS content_drafts (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key), KEY idx_content_drafts_user (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS content_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_json LONGTEXT NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_content_versions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_sections (
  section_key VARCHAR(60) NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_library (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_media_path (file_path), KEY idx_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action_type VARCHAR(80) NOT NULL,
  description VARCHAR(500) NOT NULL,
  metadata_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_activity_created (created_at), KEY idx_activity_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_type VARCHAR(40) NOT NULL DEFAULT 'info',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  action_url VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_notifications_read_created (is_read,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_backups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  backup_name VARCHAR(180) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_backups_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO site_sections(section_key,label,sort_order,active) VALUES
('home','Home / Hero',10,1),('intro','What We Do',20,1),('about','About Us',30,1),('services','Services',40,1),('gallery','Gallery',50,1),('areas','Service Areas',60,1),('estimate','Free Estimate',70,1),('tips','Home Tips',80,1),('contact','Contact',90,1)
ON DUPLICATE KEY UPDATE label=VALUES(label);
INSERT INTO settings(setting_key,setting_value) VALUES
('seo_title','Castro''s Ready | Home Improvement'),
('seo_description','Castro''s Ready — professional painting, remodeling, flooring, decks, carpentry, concrete, drywall and pressure washing.'),
('seo_social_image',''),('seo_robots','index,follow'),
('developer_credit_enabled','0'),('developer_credit_text','Website by ES MULTISERVICIOS')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- USERS, ROLES, APPROVALS, SALES TRACKING & SECURITY CENTER

CREATE TABLE IF NOT EXISTS admin_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key VARCHAR(60) NOT NULL,
  role_name VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_role_key(role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permission_key VARCHAR(100) NOT NULL,
  permission_name VARCHAR(160) NOT NULL,
  permission_group VARCHAR(80) NOT NULL DEFAULT 'General',
  PRIMARY KEY(id), UNIQUE KEY uq_admin_permission_key(permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(role_id,permission_id), KEY idx_role_permission_permission(permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submitted_by INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','changes_requested','cancelled') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  reviewer_note TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY(id), KEY idx_content_approval_status(status,submitted_at), KEY idx_content_approval_submitter(submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_estimate_notes_request(estimate_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  last_seen_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_session_hash(session_hash), KEY idx_admin_sessions_user(admin_id,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  username_attempt VARCHAR(80) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_login_events_user(admin_id,created_at), KEY idx_login_events_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_notification_reads (
  notification_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(notification_id,admin_id), KEY idx_notification_reads_admin(admin_id,read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_roles(role_key,role_name,description,is_system,active) VALUES
('owner','Owner','Full protected ownership of the website.',1,1),
('administrator','Administrator','Full day-to-day administration except protected owner controls.',1,1),
('editor','Editor','Creates and edits website content; publishing requires approval.',1,1),
('sales','Sales','Works with estimate requests and customer follow-ups.',1,1),
('viewer','Viewer','Read-only operational access.',1,1)
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name),description=VALUES(description),active=1;

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('dashboard.view','View dashboard','General'),
('content.view','View page content','Content'),
('content.edit','Edit drafts','Content'),
('content.publish','Publish content','Content'),
('content.approve','Approve submitted content','Content'),
('sections.manage','Manage landing sections','Content'),
('media.manage','Manage Media Library','Content'),
('services.manage','Manage services','Content'),
('gallery.manage','Manage gallery','Content'),
('areas.manage','Manage service areas','Content'),
('tips.manage','Manage home tips','Content'),
('estimates.view','View estimate requests','Business'),
('estimates.manage_assigned','Manage assigned estimates','Business'),
('estimates.manage_all','Manage all estimates and assignments','Business'),
('email.manage','Manage email configuration','System'),
('integrations.manage','Manage integrations & APIs','System'),
('seo.manage','Manage SEO','Content'),
('health.view','View Website Health','General'),
('notifications.view','View notifications','General'),
('activity.view','View activity log','General'),
('backups.manage','Manage backups','System'),
('settings.manage','Manage site settings','System'),
('users.manage','Manage administrator users','Administration'),
('roles.manage','Manage roles & permissions','Administration'),
('security.manage','Manage active sessions and security','Administration')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.publish' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.approve' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_all' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='email.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='integrations.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='activity.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='backups.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='settings.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='users.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='security.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p WHERE r.role_key='owner';

-- MySQL/MariaDB hosting compatibility:
-- check information_schema before every ALTER instead of using
-- ADD COLUMN/INDEX IF NOT EXISTS, which is unavailable on some servers.
SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='role_id'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN role_id INT UNSIGNED NULL AFTER password_hash'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='active'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role_id'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='created_by'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN created_by INT UNSIGNED NULL AFTER active'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='last_login_at'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN last_login_at DATETIME NULL AFTER created_by'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='last_login_ip'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN last_login_ip VARCHAR(64) NULL AFTER last_login_at'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='last_user_agent'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN last_user_agent VARCHAR(500) NULL AFTER last_login_ip'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='two_factor_secret_enc'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN two_factor_secret_enc TEXT NULL AFTER last_user_agent'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='two_factor_enabled'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_secret_enc'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND INDEX_NAME='idx_admin_role_active'
  ),
  'SELECT 1',
  'ALTER TABLE admin_users ADD INDEX idx_admin_role_active (role_id,active)'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

ALTER TABLE estimate_requests
  MODIFY COLUMN status ENUM('new','contacted','in_progress','won','lost','closed') NOT NULL DEFAULT 'new';

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estimate_requests' AND COLUMN_NAME='assigned_to'
  ),
  'SELECT 1',
  'ALTER TABLE estimate_requests ADD COLUMN assigned_to INT UNSIGNED NULL AFTER status'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estimate_requests' AND COLUMN_NAME='priority'
  ),
  'SELECT 1',
  'ALTER TABLE estimate_requests ADD COLUMN priority ENUM(''low'',''normal'',''high'',''urgent'') NOT NULL DEFAULT ''normal'' AFTER assigned_to'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estimate_requests' AND COLUMN_NAME='follow_up_date'
  ),
  'SELECT 1',
  'ALTER TABLE estimate_requests ADD COLUMN follow_up_date DATE NULL AFTER priority'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estimate_requests' AND COLUMN_NAME='internal_notes'
  ),
  'SELECT 1',
  'ALTER TABLE estimate_requests ADD COLUMN internal_notes TEXT NULL AFTER follow_up_date'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estimate_requests' AND COLUMN_NAME='updated_at'
  ),
  'SELECT 1',
  'ALTER TABLE estimate_requests ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estimate_requests' AND INDEX_NAME='idx_estimate_assigned'
  ),
  'SELECT 1',
  'ALTER TABLE estimate_requests ADD INDEX idx_estimate_assigned (assigned_to,status,follow_up_date)'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

UPDATE admin_users
SET role_id=(SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1)
WHERE role_id IS NULL;


-- ============================================================
-- CLIENT CONTENT UPDATE: SERVICE BADGES + MISSION/VISION ART + VIDEOS
-- Safe to re-run: existing structures/content are preserved and duplicate seeds are skipped.
-- ============================================================
SET @cr_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='services' AND COLUMN_NAME='icon_path'
  ),
  'SELECT 1',
  'ALTER TABLE services ADD COLUMN icon_path VARCHAR(500) NULL AFTER details'
);
PREPARE cr_stmt FROM @cr_sql;
EXECUTE cr_stmt;
DEALLOCATE PREPARE cr_stmt;

CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  video_type ENUM('youtube','vimeo','upload') NOT NULL DEFAULT 'youtube',
  video_url VARCHAR(700) NULL,
  file_path VARCHAR(500) NULL,
  poster_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_videos_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_sections(section_key,label,sort_order,active) VALUES ('videos','Videos',50,1)
ON DUPLICATE KEY UPDATE label=VALUES(label);
UPDATE site_sections SET sort_order=60 WHERE section_key='gallery' AND sort_order=50;
UPDATE site_sections SET sort_order=70 WHERE section_key='areas' AND sort_order=60;
UPDATE site_sections SET sort_order=80 WHERE section_key='tips' AND sort_order=70;
UPDATE site_sections SET sort_order=90 WHERE section_key='estimate' AND sort_order=80;
UPDATE site_sections SET sort_order=100 WHERE section_key='contact' AND sort_order=90;

INSERT INTO settings(setting_key,setting_value) VALUES
('mission_artwork_path','assets/about/mission.png'),
('vision_artwork_path','assets/about/vision.png')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('videos.manage','Manage website videos','Content')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage'
WHERE r.role_key IN ('administrator','editor');

UPDATE services SET icon_path='assets/service-badges/painting.png' WHERE title='Painting';
UPDATE services SET icon_path='assets/service-badges/remodeling.png' WHERE title='Remodeling';
UPDATE services SET icon_path='assets/service-badges/flooring.png' WHERE title='Flooring';
UPDATE services SET icon_path='assets/service-badges/decks-outdoor-living.png' WHERE title='Decks & Outdoor Living';
UPDATE services SET icon_path='assets/service-badges/siding-carpentry.png' WHERE title='Siding & Carpentry';
UPDATE services SET icon_path='assets/service-badges/drywall-services.png' WHERE title IN ('Drywall','Drywall Services');
UPDATE services SET icon_path='assets/service-badges/concrete-services.png' WHERE title IN ('Concrete','Concrete Services');
UPDATE services SET icon_path='assets/service-badges/pressure-washing.png' WHERE title='Pressure Washing';


-- ============================================================
-- CLIENT MEDIA FLEXIBILITY: ABOUT ARTWORK GALLERY + INITIAL VIDEOS
-- ============================================================
CREATE TABLE IF NOT EXISTS about_artworks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  image_path VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_about_artworks_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO about_artworks(title,image_path,sort_order,active)
SELECT 'Mission','assets/about/mission.png',10,1
WHERE NOT EXISTS (SELECT 1 FROM about_artworks WHERE image_path='assets/about/mission.png');
INSERT INTO about_artworks(title,image_path,sort_order,active)
SELECT 'Vision','assets/about/vision.png',20,1
WHERE NOT EXISTS (SELECT 1 FROM about_artworks WHERE image_path='assets/about/vision.png');

INSERT INTO videos(title,description,video_type,video_url,file_path,poster_path,sort_order,active)
SELECT 'Project Showcase','', 'youtube','https://youtu.be/01nlW2CAMgA?si=2RbdC7qcg1i3etdD',NULL,NULL,10,1
WHERE NOT EXISTS (SELECT 1 FROM videos WHERE video_url LIKE '%01nlW2CAMgA%');
INSERT INTO videos(title,description,video_type,video_url,file_path,poster_path,sort_order,active)
SELECT 'Project Highlight','', 'youtube','https://youtube.com/shorts/rIuzhI1PAL0?si=tn8Ny8yd7EpI2AmF',NULL,NULL,20,1
WHERE NOT EXISTS (SELECT 1 FROM videos WHERE video_url LIKE '%rIuzhI1PAL0%');


-- ============================================================
-- PUBLIC LAYOUT: FREE ESTIMATE BEFORE HOME TIPS
-- Applied once so later changes made in Section Manager remain intact.
-- ============================================================
UPDATE site_sections
SET sort_order = CASE section_key
  WHEN 'estimate' THEN 80
  WHEN 'tips' THEN 90
  ELSE sort_order
END
WHERE section_key IN ('estimate','tips')
  AND NOT EXISTS (
    SELECT 1
    FROM settings
    WHERE setting_key = 'layout_estimate_before_tips_20260903'
  );

INSERT INTO settings(setting_key,setting_value)
VALUES ('layout_estimate_before_tips_20260903','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- ============================================================
-- PUBLIC MAP: CASTRO'S READY LOCATION
-- Applied once so later map edits made in Service Areas remain intact.
-- ============================================================
SET @cr_apply_map_location_20260903 = (
  SELECT COUNT(*) = 0
  FROM settings
  WHERE setting_key = 'service_map_location_20260903'
);

INSERT INTO settings(setting_key,setting_value)
SELECT 'service_map_query','6624 Aspern Drive, Elkridge, MD 21075, United States'
WHERE @cr_apply_map_location_20260903 = 1
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO settings(setting_key,setting_value)
SELECT 'service_map_label','Castro''s Ready'
WHERE @cr_apply_map_location_20260903 = 1
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO settings(setting_key,setting_value)
SELECT 'service_map_enabled','1'
WHERE @cr_apply_map_location_20260903 = 1
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO settings(setting_key,setting_value)
VALUES ('service_map_location_20260903','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- ============================================================
-- PUBLIC ANALYTICS, BRAND ASSETS, WHATSAPP AND COMPLETE SEO
-- Safe to run repeatedly on an existing installation.
-- ============================================================
CREATE TABLE IF NOT EXISTS website_visits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_hash CHAR(64) NOT NULL,
  visit_date DATE NOT NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  page_views INT UNSIGNED NOT NULL DEFAULT 1,
  entry_path VARCHAR(500) NULL,
  referrer VARCHAR(700) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_website_visit_day (visitor_hash,visit_date),
  KEY idx_website_visit_date (visit_date),
  KEY idx_website_visit_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value)
SELECT 'site_logo_path',COALESCE(NULLIF((SELECT setting_value FROM settings WHERE setting_key='admin_logo_path' LIMIT 1),''),'assets/logo.jpg')
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='site_logo_path');

INSERT INTO settings(setting_key,setting_value)
SELECT 'whatsapp_number',COALESCE(NULLIF((SELECT setting_value FROM settings WHERE setting_key='phone_digits' LIMIT 1),''),'12026442717')
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='whatsapp_number');

INSERT INTO settings(setting_key,setting_value) VALUES
('seo_canonical_url','https://castrosready.us/'),
('seo_site_name','Castro''s Ready'),
('seo_business_type','HomeAndConstructionBusiness'),
('seo_area_served','Elkridge, Maryland and surrounding areas')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- ============================================================
-- COMPLETE PUBLIC CONTENT AND SECTION IMAGE ADMINISTRATION
-- Safe to run repeatedly: existing client content is preserved.
-- ============================================================
INSERT INTO site_content(content_key,content_value) VALUES
('hero_eyebrow','PAINTING · REPAIRS · MAINTENANCE'),
('hero_title','More Than Repairs and Maintenance. We Care About Your Home.'),
('hero_text','Your home is where you share meaningful moments with your family, rest after a long day, and build lasting memories. At Castro’s Ready, we work to ensure every space reflects comfort, safety, and pride through reliable repairs, maintenance, and home improvement services.'),
('intro_title','Professional home improvement services you can trust.'),
('intro_text','Professional painting, remodeling, flooring, decks, carpentry, concrete, drywall, and pressure washing services you can trust.'),
('about_title','Built to care for the places that matter most.'),
('about_text','Castro’s Ready is focused on dependable home improvement work, thoughtful service, and quality results for every project.'),
('about_text_2','We believe each home deserves to be a safe, functional, and pleasant space for the people who live there.'),
('mission','Provide painting, remodeling, and maintenance services with high quality standards, building trust and satisfaction with every client.'),
('vision','Be a company recognized for transforming homes through honest, professional, and excellent work.'),
('areas_title','Quality work, wherever your project is.'),
('areas_text','Our confirmed service cities and coverage areas can be managed from the administrator panel.'),
('estimate_title','Tell us about your project.'),
('estimate_text','Request a free estimate. Fill in all or only the information you have available; attaching photos is optional.'),
('contact_title','Ready when your home needs us.'),
('brand_name','CASTRO''S READY'),
('brand_tagline','Painting · Repairs · Maintenance'),
('nav_home','Home'),('nav_about','About'),('nav_services','Services'),('nav_videos','Videos'),
('nav_gallery','Gallery'),('nav_areas','Service Areas'),('nav_contact','Contact'),('nav_estimate','Free Estimate'),
('hero_primary_button','Request a Free Estimate'),('hero_secondary_button','Call'),
('hero_trust_1','Reliable service'),('hero_trust_2','Quality workmanship'),('hero_trust_3','Home-focused care'),
('hero_badge_title','One trusted team.'),('hero_badge_text','From touch-ups to full transformations.'),
('intro_kicker','WHAT WE DO'),
('about_kicker','ABOUT CASTRO''S READY'),('about_note_kicker','Built around one standard'),('about_note_title','Do the work right.'),
('mission_label','Mission'),('vision_label','Vision'),
('about_value_1','Integrity'),('about_value_2','Respect'),('about_value_3','Quality'),
('about_value_4','Reliability'),('about_value_5','Accountability'),('about_value_6','Customer Focus'),
('services_kicker','OUR SERVICES'),('services_title','From maintenance to complete transformations.'),
('services_text','Select a category to explore the work Castro''s Ready can help with.'),
('videos_kicker','SEE OUR WORK'),('videos_title','Watch the craftsmanship behind the finished result.'),
('videos_text','Real project videos, presented in a clean and consistent showcase.'),
('gallery_kicker','PROJECT GALLERY'),('gallery_title','Craftsmanship is easier to trust when you can see it.'),
('gallery_cta','Start your project'),
('areas_kicker','SERVICE AREAS'),('areas_button','Ask if we serve your area'),
('tips_kicker','HOME IMPROVEMENT TIPS'),('tips_title','Useful advice for protecting your home.'),
('tips_text','Practical guidance for keeping your property looking and performing its best.'),('tips_link_label','Read guide'),
('estimate_kicker','FREE ESTIMATE'),('estimate_call_label','Call'),('estimate_email_label','Email'),
('form_name_label','Full Name'),('form_name_placeholder','Your name'),
('form_phone_label','Phone'),('form_phone_placeholder','(000) 000-0000'),
('form_email_label','Email'),('form_email_placeholder','you@email.com'),('form_date_label','Desired Date'),
('form_address_label','Address'),('form_address_placeholder','Project address'),
('form_service_label','Service Needed'),('form_service_placeholder','Select a service'),
('form_message_label','Tell us about your project'),('form_message_placeholder','A short description is enough to get started.'),
('form_upload_title','Add project photos'),('form_upload_text','Drag & drop, paste from clipboard, or choose files'),
('form_upload_note','Optional · JPG, PNG or WebP · up to 8 images'),
('form_submit_label','Send Free Estimate Request'),('form_whatsapp_label','Continue on WhatsApp'),
('form_note','You can submit the form even if you do not attach a photo.'),
('contact_kicker','CONTACT'),('contact_text','Choose the easiest way to reach Castro''s Ready.'),
('contact_accent','Fast, direct, professional contact.'),('contact_phone_label','Phone'),
('contact_phone_note','Call our team directly'),('contact_whatsapp_label','WhatsApp'),
('contact_whatsapp_title','Start a conversation'),('contact_whatsapp_note','Quick project questions'),
('contact_email_label','Email'),('contact_email_note','Send project information'),
('contact_youtube_label','YouTube'),('contact_youtube_title','@CastrosReady'),
('contact_youtube_note','See more of our work'),
('footer_explore_title','Explore'),('footer_contact_title','Contact'),('footer_website_title','Website'),
('footer_email_label','Email us'),('footer_estimate_label','Free Estimate'),('footer_copyright','© 2026 Castro''s Ready')
ON DUPLICATE KEY UPDATE content_value=content_value;

INSERT INTO settings(setting_key,setting_value) VALUES
('hero_image_path','https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=88'),
('hero_image_alt','Castro''s Ready construction project'),
('hero_image_position','center'),
('about_image_path','https://images.unsplash.com/photo-1531835551805-16d864c8d311?auto=format&fit=crop&w=1100&q=85'),
('about_image_alt','Comfortable finished home interior'),
('about_image_position','center')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SET @cr_apply_public_media_20261023 = (
  SELECT COUNT(*) = 0 FROM settings WHERE setting_key='public_media_admin_20261023'
);

UPDATE gallery SET image_path='https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1100&q=85' WHERE @cr_apply_public_media_20261023=1 AND title='Before & After' AND (image_path IS NULL OR image_path='');
UPDATE gallery SET image_path='https://images.unsplash.com/photo-1562259949-e8e7689d7828?auto=format&fit=crop&w=900&q=85' WHERE @cr_apply_public_media_20261023=1 AND title='Interior Painting' AND (image_path IS NULL OR image_path='');
UPDATE gallery SET image_path='https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85' WHERE @cr_apply_public_media_20261023=1 AND title='Exterior Painting' AND (image_path IS NULL OR image_path='');
UPDATE gallery SET image_path='https://images.unsplash.com/photo-1581858726788-75bc0f6a952d?auto=format&fit=crop&w=900&q=85' WHERE @cr_apply_public_media_20261023=1 AND title='Remodeling' AND (image_path IS NULL OR image_path='');
UPDATE gallery SET image_path='https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85' WHERE @cr_apply_public_media_20261023=1 AND title='Flooring' AND (image_path IS NULL OR image_path='');
UPDATE gallery SET image_path='https://images.unsplash.com/photo-1590725121839-892b458a74fe?auto=format&fit=crop&w=900&q=85' WHERE @cr_apply_public_media_20261023=1 AND title IN ('Drywall Repair','Decks','Concrete') AND (image_path IS NULL OR image_path='');

INSERT INTO settings(setting_key,setting_value) VALUES ('public_media_admin_20261023','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
