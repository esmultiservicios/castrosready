SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  full_name VARCHAR(150) NULL,
  email VARCHAR(180) NULL,
  avatar_path VARCHAR(500) NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username),
  KEY idx_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_reset_token (token_hash),
  KEY idx_admin_reset_user (admin_id),
  KEY idx_admin_reset_expiry (expires_at),
  CONSTRAINT fk_admin_reset_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_remember_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_remember_selector (selector),
  KEY idx_admin_remember_user (admin_id),
  KEY idx_admin_remember_expiry (expires_at),
  CONSTRAINT fk_admin_remember_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_content (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  details TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  image_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_areas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(220) NOT NULL,
  url VARCHAR(500) NOT NULL DEFAULT '#',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(150) NULL,
  phone VARCHAR(80) NULL,
  email VARCHAR(180) NULL,
  address VARCHAR(255) NULL,
  service_needed VARCHAR(150) NULL,
  desired_date DATE NULL,
  message TEXT NULL,
  photo_path VARCHAR(500) NULL,
  status ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_status_created(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_attachment_estimate (estimate_id),
  CONSTRAINT fk_estimate_attachment_request FOREIGN KEY (estimate_id) REFERENCES estimate_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY idx_correo_tipo_estado (correo_tipo_id,estado),
  CONSTRAINT fk_correo_tipo FOREIGN KEY (correo_tipo_id) REFERENCES correo_tipo(correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_integrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_name VARCHAR(120) NOT NULL,
  api_type VARCHAR(40) NOT NULL DEFAULT 'custom',
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  environment ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  auth_type VARCHAR(40) NOT NULL DEFAULT 'api_key',
  base_url VARCHAR(500) NULL,
  public_key VARCHAR(500) NULL,
  secret_key TEXT NULL,
  webhook_secret TEXT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO correo_tipo (correo_tipo_id,nombre) VALUES
(1,'Website Alerts'),(2,'Admin Security'),(3,'Estimate Requests'),(4,'Auto Replies')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

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
('contact_title','Ready when your home needs us.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO settings(setting_key,setting_value) VALUES
('phone','+1 (202) 644-2717'),
('phone_digits','12026442717'),
('email','castrosreadycompany@gmail.com'),
('youtube','https://www.youtube.com/@CastrosReady'),
('facebook','#'),('tiktok','#'),('website','castrosready.us'),('business_hours',''),
('admin_brand_name','Castro''s Ready Admin'),('admin_logo_path','assets/logo.jpg'),
('maintenance_mode','0'),('maintenance_title','We are improving our website.'),('maintenance_text','Castro''s Ready will be back shortly. For immediate assistance, contact us by phone or WhatsApp.'),('maintenance_image_path',''),
('whatsapp_enabled','1'),('whatsapp_message','Hello, I would like more information about Castro''s Ready services.'),('whatsapp_position','right')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO services(title,details,sort_order,active)
SELECT 'Painting','Interior Painting · Exterior Painting · Cabinet Painting · Ceiling Painting · Trim & Doors Painting',10,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Painting');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Remodeling','Bathroom Remodeling · Kitchen Remodeling · Basement Remodeling',20,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Remodeling');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Flooring','Luxury Vinyl Plank (LVP) · Laminate Flooring · Hardwood Flooring · Floor Repair',30,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Flooring');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Decks & Outdoor Living','Deck Construction · Deck Repair · Staining & Sealing',40,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Decks & Outdoor Living');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Siding & Carpentry','Siding Installation · Wood Repair · Doors & Windows',50,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Siding & Carpentry');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Drywall Services','Installation · Repairs · Water Damage Repair · Texture Matching',60,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Drywall Services');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Concrete Services','Patios · Sidewalks · Slabs · Repairs',70,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Concrete Services');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Pressure Washing','Houses · Decks · Driveways · Patios · Fences',80,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Pressure Washing');

INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Before & After','',10,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Before & After');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Interior Painting','',20,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Interior Painting');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Exterior Painting','',30,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Exterior Painting');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Remodeling','',40,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Remodeling');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Flooring','',50,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Flooring');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Drywall Repair','',60,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Drywall Repair');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Decks','',70,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Decks');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Concrete','',80,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Concrete');

INSERT INTO tips(title,url,sort_order,active)
SELECT 'How Often Should You Paint Your Home?','#',10,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='How Often Should You Paint Your Home?');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Signs Your Home Needs Maintenance','#',20,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Signs Your Home Needs Maintenance');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Best Paint Colors for Modern Homes','#',30,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Best Paint Colors for Modern Homes');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'How to Protect Your Deck Year-Round','#',40,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='How to Protect Your Deck Year-Round');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Top Remodeling Trends','#',50,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Top Remodeling Trends');

SET FOREIGN_KEY_CHECKS=1;
