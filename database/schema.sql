-- ==========================================================
-- TubigKo Water Refilling Station System
-- Database schema + seed data
-- Import this file into MySQL / MariaDB before using the app.
--   mysql -u root -p < database/schema.sql
-- ==========================================================

CREATE DATABASE IF NOT EXISTS tubigko_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tubigko_db;

-- ----------------------------------------------------------
-- users  (admins + customers)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100)    NOT NULL,
  username      VARCHAR(50)     NOT NULL UNIQUE,
  email         VARCHAR(100)    NOT NULL UNIQUE,
  password      VARCHAR(255)    NOT NULL,
  phone         VARCHAR(30)     DEFAULT NULL,
  address       TEXT            DEFAULT NULL,
  barangay      VARCHAR(100)    DEFAULT NULL,
  landmark      VARCHAR(150)    DEFAULT NULL,
  role          ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- gallons  (product catalog managed by admin)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallons (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  code              VARCHAR(20)     NOT NULL UNIQUE,
  name              VARCHAR(150)    NOT NULL,
  water_type        VARCHAR(50)     NOT NULL,
  gallon_size       VARCHAR(30)     NOT NULL,
  price_per_gallon  DECIMAL(10,2)   NOT NULL DEFAULT 0,
  stock             INT             NOT NULL DEFAULT 0,
  description       TEXT            DEFAULT NULL,
  status            ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- deliveries  (one row per order placed by a customer)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS deliveries (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  order_code        VARCHAR(20)     NOT NULL UNIQUE,
  customer_id       INT             NOT NULL,
  delivery_address  TEXT            NOT NULL,
  delivery_date     DATE            NOT NULL,
  delivery_time     VARCHAR(40)     NOT NULL,
  rider             VARCHAR(100)    DEFAULT NULL,
  notes             TEXT            DEFAULT NULL,
  total_amount      DECIMAL(10,2)   NOT NULL DEFAULT 0,
  status            ENUM('pending','confirmed','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_deliveries_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_deliveries_customer (customer_id),
  INDEX idx_deliveries_status (status)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- delivery_items  (line items per order; a customer can order
-- several gallon products in one delivery request)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS delivery_items (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  delivery_id       INT             NOT NULL,
  gallon_id         INT             NOT NULL,
  gallon_name       VARCHAR(150)    NOT NULL,
  quantity          INT             NOT NULL DEFAULT 1,
  price_per_gallon  DECIMAL(10,2)   NOT NULL DEFAULT 0,
  total_amount      DECIMAL(10,2)   NOT NULL DEFAULT 0,
  CONSTRAINT fk_items_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_gallon FOREIGN KEY (gallon_id) REFERENCES gallons(id) ON DELETE RESTRICT,
  INDEX idx_items_delivery (delivery_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- payments
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  payment_code      VARCHAR(20)     NOT NULL UNIQUE,
  customer_id       INT             NOT NULL,
  delivery_id       INT             NOT NULL,
  amount            DECIMAL(10,2)   NOT NULL DEFAULT 0,
  payment_method    VARCHAR(50)     NOT NULL,
  reference_number  VARCHAR(100)    DEFAULT NULL,
  payment_date      DATETIME        DEFAULT NULL,
  status            ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
  INDEX idx_payments_customer (customer_id),
  INDEX idx_payments_status (status)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- payment_methods  (reference list shown on admin > Manage Payment)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_methods (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(20)   NOT NULL UNIQUE,
  method      VARCHAR(60)   NOT NULL,
  provider    VARCHAR(100)  DEFAULT NULL,
  fee         DECIMAL(10,2) NOT NULL DEFAULT 0,
  status      ENUM('enabled','disabled') NOT NULL DEFAULT 'enabled'
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- notifications
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  notif_code    VARCHAR(20)   NOT NULL UNIQUE,
  user_id       INT           DEFAULT NULL, -- NULL = broadcast to all customers
  audience      VARCHAR(100)  NOT NULL DEFAULT 'All Customers',
  title         VARCHAR(150)  NOT NULL,
  message       TEXT          NOT NULL,
  type          VARCHAR(50)   NOT NULL DEFAULT 'Announcement',
  is_read       BOOLEAN       NOT NULL DEFAULT FALSE,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user (user_id)
) ENGINE=InnoDB;

-- ==========================================================
-- Seed data
-- ==========================================================

-- Default admin account. Username: admin  Password: Admin@123
-- (hash generated with password_hash('Admin@123', PASSWORD_DEFAULT))
INSERT INTO users (full_name, username, email, password, phone, address, role, status)
VALUES (
  'System Administrator', 'admin', 'admin@tubigko.ph',
  '$2y$10$ZFBGnScQcacUAoIsvdOiUeFyER7eMiBKKKwTJ6hPAgYty8U1xZzLa',
  '0900-000-0000', 'TubigKo Main Station', 'admin', 'active'
);

-- Sample customers. Password for all: Customer@123
-- (hash generated with password_hash('Customer@123', PASSWORD_DEFAULT))
INSERT INTO users (full_name, username, email, password, phone, address, barangay, role, status, created_at) VALUES
('Maria Santos','maria.santos','maria.santos@example.com','$2y$10$PMo4COH0ok8bk.lnNDej5eV5Qo2i0O25OSpECm8.ewSXRTnMugN02','0917-555-0182','12 Mabini St., Brgy. San Roque','Brgy. San Roque','customer','active','2026-01-14 09:00:00'),
('Jose Dela Cruz','jose.dc','jose.dc@example.com','$2y$10$PMo4COH0ok8bk.lnNDej5eV5Qo2i0O25OSpECm8.ewSXRTnMugN02','0928-555-7741','88 Rizal Ave., Brgy. Poblacion','Brgy. Poblacion','customer','active','2026-02-02 09:00:00'),
('Ana Reyes','ana.reyes','ana.reyes@example.com','$2y$10$PMo4COH0ok8bk.lnNDej5eV5Qo2i0O25OSpECm8.ewSXRTnMugN02','0939-555-3390','5 Sampaguita Lane, Brgy. Malaya','Brgy. Malaya','customer','active','2026-02-19 09:00:00'),
('Pedro Bautista','pedro.b','pedro.b@example.com','$2y$10$PMo4COH0ok8bk.lnNDej5eV5Qo2i0O25OSpECm8.ewSXRTnMugN02','0916-555-2214','41 Narra St., Brgy. Bagong Silang','Brgy. Bagong Silang','customer','inactive','2026-03-05 09:00:00'),
('Liza Manalo','liza.manalo','liza.manalo@example.com','$2y$10$PMo4COH0ok8bk.lnNDej5eV5Qo2i0O25OSpECm8.ewSXRTnMugN02','0905-555-9987','7 Acacia Drive, Brgy. San Roque','Brgy. San Roque','customer','active','2026-03-22 09:00:00'),
('Ramon Villanueva','ramon.v','ramon.v@example.com','$2y$10$PMo4COH0ok8bk.lnNDej5eV5Qo2i0O25OSpECm8.ewSXRTnMugN02','0977-555-1123','23 Ilang-Ilang St., Brgy. Malaya','Brgy. Malaya','customer','active','2026-04-11 09:00:00');

-- Gallon catalog
INSERT INTO gallons (code, name, water_type, gallon_size, price_per_gallon, stock, description) VALUES
('G-001','Round Slim 5 Gallon','Purified','5 Gallons',30,124,'Standard round slim container. Best seller for households.'),
('G-002','Round Slim 5 Gallon (Mineral)','Mineral','5 Gallons',35,86,'Mineral water with added natural minerals.'),
('G-003','Alkaline 5 Gallon','Alkaline','5 Gallons',50,42,'pH 8.5+ alkaline water for daily drinking.'),
('G-004','Distilled 5 Gallon','Distilled','5 Gallons',40,58,'Steam-distilled water for appliances and drinking.'),
('G-005','Small Container 1 Gallon','Purified','1 Gallon',15,210,'Handy one gallon container for small households.'),
('G-006','New Empty Container','Container','5 Gallons',250,33,'Brand new empty container for first time customers.');

-- Payment methods reference list
INSERT INTO payment_methods (code, method, provider, fee, status) VALUES
('PM-01','Cash on Delivery','Walk-in / Rider',0,'enabled'),
('PM-02','GCash','G-Xchange Inc.',0,'enabled'),
('PM-03','Maya','Maya Philippines',0,'enabled'),
('PM-04','Bank Transfer','BPI / BDO',15,'enabled'),
('PM-05','Credit / Debit Card','Visa / Mastercard',20,'disabled');

-- Sample deliveries + items + payments + notifications
-- (customer_id 2 = Jose, 3 = Ana, 4 = Pedro is inactive, 5 = Liza, 6 = Ramon, 1 = admin skipped, use ids 2-7 relative)
-- NOTE: user ids above are inserted in order admin(1), Maria(2), Jose(3), Ana(4), Pedro(5), Liza(6), Ramon(7)

INSERT INTO deliveries (order_code, customer_id, delivery_address, delivery_date, delivery_time, rider, notes, total_amount, status, created_at) VALUES
('ORD-3301', 2, '12 Mabini St., Brgy. San Roque', '2026-08-17', '10:00 AM - 12:00 NN', 'Jun Castro', NULL, 150.00, 'delivered', '2026-08-17 08:00:00'),
('ORD-3302', 3, '88 Rizal Ave., Brgy. Poblacion', '2026-08-18', '1:00 PM - 3:00 PM', 'Mark Aquino', NULL, 60.00, 'out_for_delivery', '2026-08-17 12:00:00'),
('ORD-3303', 4, '5 Sampaguita Lane, Brgy. Malaya', '2026-08-18', '8:00 AM - 10:00 AM', 'Mark Aquino', NULL, 100.00, 'delivered', '2026-08-17 15:00:00'),
('ORD-3304', 6, '7 Acacia Drive, Brgy. San Roque', '2026-08-18', '3:00 PM - 5:00 PM', NULL, NULL, 265.00, 'confirmed', '2026-08-18 09:00:00'),
('ORD-3305', 7, '23 Ilang-Ilang St., Brgy. Malaya', '2026-08-18', '3:00 PM - 5:00 PM', 'Jun Castro', NULL, 90.00, 'confirmed', '2026-08-18 09:30:00'),
('ORD-3306', 2, '12 Mabini St., Brgy. San Roque', '2026-08-18', '10:00 AM - 12:00 NN', 'Jun Castro', NULL, 30.00, 'delivered', '2026-08-18 10:00:00'),
('ORD-3307', 4, '5 Sampaguita Lane, Brgy. Malaya', '2026-08-18', '1:00 PM - 3:00 PM', 'Mark Aquino', NULL, 50.00, 'confirmed', '2026-08-18 11:00:00');

INSERT INTO delivery_items (delivery_id, gallon_id, gallon_name, quantity, price_per_gallon, total_amount) VALUES
(1, 1, 'Round Slim 5 Gallon', 5, 30, 150.00),
(2, 1, 'Round Slim 5 Gallon', 2, 30, 60.00),
(3, 2, 'Round Slim 5 Gallon (Mineral)', 2, 35, 70.00),
(3, 1, 'Round Slim 5 Gallon', 1, 30, 30.00),
(4, 4, 'Distilled 5 Gallon', 1, 40, 40.00),
(4, 1, 'Round Slim 5 Gallon', 5, 30, 150.00),
(5, 1, 'Round Slim 5 Gallon', 3, 30, 90.00),
(6, 1, 'Round Slim 5 Gallon', 1, 30, 30.00),
(7, 3, 'Alkaline 5 Gallon', 1, 50, 50.00);

INSERT INTO payments (payment_code, customer_id, delivery_id, amount, payment_method, reference_number, payment_date, status, created_at) VALUES
('PAY-2051', 2, 1, 150.00, 'GCash', 'GC-88213', '2026-08-17 10:12:00', 'paid', '2026-08-17 08:05:00'),
('PAY-2052', 3, 2, 60.00, 'Cash on Delivery', NULL, NULL, 'pending', '2026-08-17 12:05:00'),
('PAY-2053', 4, 3, 100.00, 'Maya', 'MY-55219', '2026-08-18 08:20:00', 'paid', '2026-08-17 15:05:00'),
('PAY-2054', 6, 4, 265.00, 'Bank Transfer', 'BT-90021', NULL, 'failed', '2026-08-18 09:05:00'),
('PAY-2055', 7, 5, 90.00, 'GCash', 'GC-90114', NULL, 'pending', '2026-08-18 09:35:00'),
('PAY-2056', 2, 6, 30.00, 'Cash on Delivery', NULL, '2026-08-18 12:00:00', 'paid', '2026-08-18 10:05:00');

INSERT INTO notifications (notif_code, user_id, audience, title, message, type, is_read, created_at) VALUES
('N-501', 3, 'Jose Dela Cruz', 'Your order is on the way', 'Rider Mark Aquino is delivering ORD-3302. Please prepare your empty containers.', 'Delivery', FALSE, '2026-08-18 13:40:00'),
('N-502', 2, 'Maria Santos', 'Payment received', 'We received your GCash payment of PHP 150.00 for ORD-3301. Thank you!', 'Payment', TRUE, '2026-08-17 10:12:00'),
('N-503', NULL, 'All Customers', 'Station maintenance notice', 'TubigKo will be closed on Aug 25, 2026 for equipment cleaning. Orders resume Aug 26.', 'Announcement', TRUE, '2026-08-16 08:00:00'),
('N-504', NULL, 'All Customers', 'Alkaline water back in stock', 'Alkaline 5 Gallon is available again. Order now while supply lasts.', 'Announcement', FALSE, '2026-08-18 07:30:00');
