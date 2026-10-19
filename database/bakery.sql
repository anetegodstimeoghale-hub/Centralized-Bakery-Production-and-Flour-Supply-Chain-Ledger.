-- ==========================================================================
-- database/bakery.sql
-- Centralized Bakery Production and Flour Supply Chain Ledger
-- --------------------------------------------------------------------------
-- How to use (Laragon + phpMyAdmin):
--   1. Start MySQL in Laragon
--   2. Open http://localhost/phpmyadmin
--   3. Click "Import" -> choose this file -> "Go"
--
-- The script CREATES the database "bakery_ledger" itself, so you do not
-- have to create it by hand. Re-importing the file resets all data.
--
-- Design notes for the defence:
--   * InnoDB engine  -> real FOREIGN KEY constraints
--   * DECIMAL(12,3)  -> exact kilogram values (never float)
--   * all money      -> INTEGER Kobo (1 Naira = 100 kobo), never a float
--   * production_runs.production_date is UNIQUE -> idempotent planning
--   * composite index (outlet_id, production_date) on deliveries
--   * production_audit_log is append-only (triggers refuse UPDATE/DELETE)
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS bakery_ledger
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE bakery_ledger;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS deliveries;
DROP TABLE IF EXISTS flour_usage;
DROP TABLE IF EXISTS flour_orders;
DROP TABLE IF EXISTS production_audit_log;
DROP TABLE IF EXISTS production_runs;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS outlets;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================================
-- 1. OUTLETS  (registry of bakery outlets + their daily demand)
-- ==========================================================================
CREATE TABLE outlets (
    id                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name               VARCHAR(120)     NOT NULL,
    daily_demand_units INT UNSIGNED     NOT NULL DEFAULT 0,
    -- price of one bread unit in kobo (150000 kobo = 1,500.00 Naira)
    unit_price_kobo    INT UNSIGNED     NOT NULL DEFAULT 150000,
    created_at         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_outlets_name (name),
    KEY idx_outlets_demand (daily_demand_units)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 2. SUPPLIERS  (flour suppliers + lead time used by the reorder formula)
-- ==========================================================================
CREATE TABLE suppliers (
    id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120)     NOT NULL,
    lead_time_days SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    contact_phone  VARCHAR(30)      NULL,
    created_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 3. PRODUCTION RUNS
--    planned_units is calculated by the application as
--        total outlet demand x 1.10     (mandatory 10% buffer)
--    The UNIQUE key on production_date makes the calculation IDEMPOTENT:
--    submitting the same planning request twice can never create a second
--    plan for the same day.
-- ==========================================================================
CREATE TABLE production_runs (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    production_date    DATE          NOT NULL,
    total_demand_units INT UNSIGNED  NOT NULL DEFAULT 0,
    planned_units      INT UNSIGNED  NOT NULL,
    actual_units       INT UNSIGNED  NOT NULL DEFAULT 0,
    waste_units        INT UNSIGNED  NOT NULL DEFAULT 0,
    buffer_rate        DECIMAL(4,3)  NOT NULL DEFAULT 1.100,
    created_by         VARCHAR(60)   NOT NULL DEFAULT 'system',
    created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- IDEMPOTENCY GUARANTEE AT DATABASE LEVEL
    UNIQUE KEY uq_production_date (production_date),
    KEY idx_production_actual (actual_units)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 4. PRODUCTION AUDIT LOG  (append-only history)
--    Every INSERT/UPDATE of a production run writes one row here.
-- ==========================================================================
CREATE TABLE production_audit_log (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_run_id  INT UNSIGNED    NOT NULL,
    action             ENUM('CREATE','UPDATE') NOT NULL,
    old_values         TEXT            NULL,
    new_values         TEXT            NOT NULL,
    total_demand_units INT UNSIGNED    NOT NULL DEFAULT 0,
    planned_units      INT UNSIGNED    NOT NULL DEFAULT 0,
    actual_units       INT UNSIGNED    NOT NULL DEFAULT 0,
    waste_units        INT UNSIGNED    NOT NULL DEFAULT 0,
    performed_by       VARCHAR(60)     NOT NULL DEFAULT 'system',
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_run (production_run_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_production_run
        FOREIGN KEY (production_run_id) REFERENCES production_runs (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 5. FLOUR ORDERS  (money is stored in Kobo - integer, never a float)
-- ==========================================================================
CREATE TABLE flour_orders (
    id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    supplier_id      INT UNSIGNED      NOT NULL,
    quantity_kg      DECIMAL(12,3)     NOT NULL,
    unit_price_kobo  INT UNSIGNED      NOT NULL DEFAULT 0,   -- price per kg
    total_cost_kobo  BIGINT UNSIGNED   NOT NULL DEFAULT 0,   -- quantity x price
    ordered_at       DATETIME          NOT NULL,
    delivered_at     DATETIME          NULL,                 -- NULL = still on the way
    status           ENUM('ordered','received','cancelled') NOT NULL DEFAULT 'ordered',
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_supplier (supplier_id),
    KEY idx_orders_delivered (delivered_at),
    CONSTRAINT fk_orders_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 6. FLOUR USAGE  (kilograms consumed by one production run)
-- ==========================================================================
CREATE TABLE flour_usage (
    id                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    production_run_id INT UNSIGNED   NOT NULL,
    flour_kg          DECIMAL(12,3)  NOT NULL,
    created_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usage_run (production_run_id),
    CONSTRAINT fk_usage_production_run
        FOREIGN KEY (production_run_id) REFERENCES production_runs (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 7. DELIVERIES  (morning + evening delivery to every outlet)
--    REQUIRED COMPOSITE INDEX: (outlet_id, production_date)
-- ==========================================================================
CREATE TABLE deliveries (
    id                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    outlet_id         INT UNSIGNED   NOT NULL,
    production_run_id INT UNSIGNED   NULL,
    production_date   DATE           NOT NULL,
    session_name      ENUM('morning','evening') NOT NULL,
    units_delivered   INT UNSIGNED   NOT NULL DEFAULT 0,
    revenue_kobo      BIGINT UNSIGNED NOT NULL DEFAULT 0,   -- money in kobo
    delivered_at      DATETIME       NOT NULL,
    created_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- one delivery per outlet, per day, per session  -> idempotent recording
    UNIQUE KEY uq_delivery_slot (outlet_id, production_date, session_name),
    -- REQUIRED COMPOSITE INDEX (outlet_id, production_date):
    -- "show every delivery of one outlet on one date"
    KEY idx_outlet_production_date (outlet_id, production_date),
    KEY idx_delivery_date (production_date),
    CONSTRAINT fk_deliveries_outlet
        FOREIGN KEY (outlet_id) REFERENCES outlets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_deliveries_run
        FOREIGN KEY (production_run_id) REFERENCES production_runs (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 8. AUDIT PROTECTION
--    Normal users (and the application itself) cannot change history:
--    these two triggers refuse every UPDATE / DELETE statement.
-- ==========================================================================
DELIMITER $$

CREATE TRIGGER trg_audit_no_update
BEFORE UPDATE ON production_audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'production_audit_log is APPEND ONLY: UPDATE is not allowed';
END$$

CREATE TRIGGER trg_audit_no_delete
BEFORE DELETE ON production_audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'production_audit_log is APPEND ONLY: DELETE is not allowed';
END$$

DELIMITER ;

-- ==========================================================================
-- 9. SAMPLE DATA  (safe to delete - the application also works without it)
--    Demand = 400 + 350 + 250 = 1,000 units  ->  planned = 1,000 x 1.10
--                                                      = 1,100 units
-- ==========================================================================

INSERT INTO outlets (name, daily_demand_units, unit_price_kobo) VALUES
    ('Igando Branch',      400, 150000),
    ('Iyana-Ipaja Branch', 350, 150000),
    ('Agege Central Store',250, 180000);

INSERT INTO suppliers (name, lead_time_days, contact_phone) VALUES
    ('Golden Mills Flour Co.',    2, '+234-801-000-0001'),
    ('Sunrise Grain Supplies',    3, '+234-802-000-0002'),
    ('Anchor Foods Distributors', 1, '+234-803-000-0003');

-- Seven production runs (the last 7 days, including today)
INSERT INTO production_runs
    (production_date, total_demand_units, planned_units, actual_units, waste_units, buffer_rate, created_by)
VALUES
    (DATE_SUB(CURDATE(), INTERVAL 6 DAY), 1000, 1100, 1060, 30, 1.100, 'seed_script'),
    (DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1000, 1100, 1070, 25, 1.100, 'seed_script'),
    (DATE_SUB(CURDATE(), INTERVAL 4 DAY), 1000, 1100, 1040, 60, 1.100, 'seed_script'),  -- 5.77% -> alert
    (DATE_SUB(CURDATE(), INTERVAL 3 DAY), 1000, 1100, 1080, 40, 1.100, 'seed_script'),
    (DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1000, 1100, 1090, 20, 1.100, 'seed_script'),
    (DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1000, 1100, 1075, 35, 1.100, 'seed_script'),
    (CURDATE(),                            1000, 1100, 1050, 55, 1.100, 'seed_script'); -- 5.24% -> alert

-- Flour usage: about 0.57 kg of flour for every bread unit
INSERT INTO flour_usage (production_run_id, flour_kg)
SELECT id, ROUND(actual_units * 0.570, 3)
FROM production_runs;

-- Flour orders (money in kobo: 45000 kobo = 450.00 Naira per kg)
INSERT INTO flour_orders
    (supplier_id, quantity_kg, unit_price_kobo, total_cost_kobo, ordered_at, delivered_at, status)
VALUES
    (1, 2500.000, 45000, 112500000,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:00:00'),
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:30:00'), 'received'),
    (2, 3000.000, 44500, 133500000,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:15:00'),
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '14:00:00'), 'received'),
    (3, 1000.000, 46000, 46000000,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '11:00:00'),
     NULL, 'ordered');

-- One audit entry per seeded production run (the table is append-only)
INSERT INTO production_audit_log
    (production_run_id, action, old_values, new_values,
     total_demand_units, planned_units, actual_units, waste_units, performed_by, created_at)
SELECT id, 'CREATE', NULL,
       CONCAT('date=', production_date,
              '; demand=', total_demand_units,
              '; planned=', planned_units,
              '; actual=', actual_units,
              '; waste=', waste_units),
       total_demand_units, planned_units, actual_units, waste_units,
       created_by, created_at
FROM production_runs;

-- Deliveries: morning + evening for the last two days
--   Igando 400 = 220 + 180   (price 150000 kobo)
--   Iyana   350 = 190 + 160   (price 150000 kobo)
--   Agege   250 = 140 + 110   (price 180000 kobo)
INSERT INTO deliveries
    (outlet_id, production_run_id, production_date, session_name,
     units_delivered, revenue_kobo, delivered_at)
VALUES
    (1, (SELECT id FROM production_runs WHERE production_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'morning', 220, 33000000,
        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:30:00')),
    (1, (SELECT id FROM production_runs WHERE production_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'evening', 180, 27000000,
        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '18:00:00')),
    (2, (SELECT id FROM production_runs WHERE production_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'morning', 190, 28500000,
        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:45:00')),
    (2, (SELECT id FROM production_runs WHERE production_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'evening', 160, 24000000,
        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '18:15:00')),
    (3, (SELECT id FROM production_runs WHERE production_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'morning', 140, 25200000,
        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00')),
    (3, (SELECT id FROM production_runs WHERE production_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'evening', 110, 19800000,
        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '18:30:00')),
    (1, (SELECT id FROM production_runs WHERE production_date = CURDATE()),
        CURDATE(), 'morning', 220, 33000000, TIMESTAMP(CURDATE(), '07:30:00')),
    (1, (SELECT id FROM production_runs WHERE production_date = CURDATE()),
        CURDATE(), 'evening', 180, 27000000, TIMESTAMP(CURDATE(), '18:00:00')),
    (2, (SELECT id FROM production_runs WHERE production_date = CURDATE()),
        CURDATE(), 'morning', 190, 28500000, TIMESTAMP(CURDATE(), '07:45:00')),
    (2, (SELECT id FROM production_runs WHERE production_date = CURDATE()),
        CURDATE(), 'evening', 160, 24000000, TIMESTAMP(CURDATE(), '18:15:00')),
    (3, (SELECT id FROM production_runs WHERE production_date = CURDATE()),
        CURDATE(), 'morning', 140, 25200000, TIMESTAMP(CURDATE(), '08:00:00')),
    (3, (SELECT id FROM production_runs WHERE production_date = CURDATE()),
        CURDATE(), 'evening', 110, 19800000, TIMESTAMP(CURDATE(), '18:30:00'));

-- ==========================================================================
-- 10. OPTIONAL: a read-only account for normal users (run manually)
-- --------------------------------------------------------------------------
-- CREATE USER 'bakery_reader'@'localhost' IDENTIFIED BY 'ChangeMe123!';
-- GRANT SELECT, INSERT ON bakery_ledger.* TO 'bakery_reader'@'localhost';
-- FLUSH PRIVILEGES;
-- The reader account has NO UPDATE and NO DELETE right, so historical
-- production audit records cannot be changed by a normal user.
-- ==========================================================================
