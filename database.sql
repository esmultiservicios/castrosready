SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_admin_username (username)
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
  PRIMARY KEY (id), KEY idx_estimate_status_created(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('estimate_text','Request a free estimate. Fill in all or only the information you have available; attaching a photo is optional.'),
('contact_title','Ready when your home needs us.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO settings(setting_key,setting_value) VALUES
('phone','+1 (202) 644-2717'),('phone_digits','12026442717'),('email','castrosreadycompany@gmail.com'),('youtube','https://www.youtube.com/@CastrosReady'),('facebook','#'),('tiktok','#'),('website','castrosready.us'),('business_hours','')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO services(title,details,sort_order,active) VALUES
('Painting','Interior Painting · Exterior Painting · Cabinet Painting · Ceiling Painting · Trim & Doors Painting',10,1),
('Remodeling','Bathroom Remodeling · Kitchen Remodeling · Basement Remodeling',20,1),
('Flooring','Luxury Vinyl Plank (LVP) · Laminate Flooring · Hardwood Flooring · Floor Repair',30,1),
('Decks & Outdoor Living','Deck Construction · Deck Repair · Staining & Sealing',40,1),
('Siding & Carpentry','Siding Installation · Wood Repair · Doors & Windows',50,1),
('Drywall Services','Installation · Repairs · Water Damage Repair · Texture Matching',60,1),
('Concrete Services','Patios · Sidewalks · Slabs · Repairs',70,1),
('Pressure Washing','Houses · Decks · Driveways · Patios · Fences',80,1);

INSERT INTO gallery(title,image_path,sort_order,active) VALUES
('Before & After','',10,1),('Interior Painting','',20,1),('Exterior Painting','',30,1),('Remodeling','',40,1),('Flooring','',50,1),('Drywall Repair','',60,1),('Decks','',70,1),('Concrete','',80,1);

INSERT INTO tips(title,url,sort_order,active) VALUES
('How Often Should You Paint Your Home?','#',10,1),
('Signs Your Home Needs Maintenance','#',20,1),
('Best Paint Colors for Modern Homes','#',30,1),
('How to Protect Your Deck Year-Round','#',40,1),
('Top Remodeling Trends','#',50,1);

SET FOREIGN_KEY_CHECKS=1;
