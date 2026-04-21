-- DeKukis Migration Script
-- Run this in phpMyAdmin if you have EXISTING data in event_analyses
-- This safely migrates old single-product data to the new multi-product schema

USE dekukis_db;

-- Step 1: Add new columns to event_analyses (ignore if already exist)
ALTER TABLE event_analyses ADD COLUMN IF NOT EXISTS weighted_margin DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE event_analyses ADD COLUMN IF NOT EXISTS total_estimated_units INT NOT NULL DEFAULT 0;
ALTER TABLE event_analyses ADD COLUMN IF NOT EXISTS total_cogs DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE event_analyses ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'forecast';

-- Step 2: Create new tables if they don't exist
CREATE TABLE IF NOT EXISTS event_products (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    event_id            INT NOT NULL,
    product_name        VARCHAR(255) NOT NULL,
    selling_price       DECIMAL(10,2) NOT NULL DEFAULT 0,
    cogs_per_unit       DECIMAL(10,2) NOT NULL DEFAULT 0,
    gross_margin        DECIMAL(10,2) NOT NULL DEFAULT 0,
    estimated_units     INT NOT NULL DEFAULT 0,
    estimated_revenue   DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES event_analyses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_actuals (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    event_id                INT NOT NULL,
    actual_booth_rental     DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_transport_cost   DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_marketing_cost   DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_labor_cost       DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_total_fixed      DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_total_revenue    DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_total_cogs       DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_profit           DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes                   TEXT,
    completed_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES event_analyses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_actual_products (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    event_id                INT NOT NULL,
    product_name            VARCHAR(255) NOT NULL,
    actual_selling_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_cogs_per_unit    DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_units_sold       INT NOT NULL DEFAULT 0,
    actual_revenue          DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES event_analyses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 3: Migrate existing single-product data into event_products
-- Only runs for rows that have selling_price column and no matching event_products yet
INSERT INTO event_products (event_id, product_name, selling_price, cogs_per_unit, gross_margin, estimated_units, estimated_revenue)
SELECT
    ea.id,
    'Product 1',
    ea.selling_price,
    ea.cogs_per_unit,
    ea.gross_margin,
    ea.estimated_units,
    ea.estimated_revenue
FROM event_analyses ea
WHERE ea.selling_price > 0
  AND NOT EXISTS (SELECT 1 FROM event_products ep WHERE ep.event_id = ea.id);

-- Step 4: Update the new aggregate columns from old data
UPDATE event_analyses
SET weighted_margin = gross_margin,
    total_estimated_units = estimated_units,
    total_cogs = cogs_per_unit * estimated_units
WHERE weighted_margin = 0 AND gross_margin > 0;

-- Step 5: Optionally drop old single-product columns (uncomment when ready)
-- ALTER TABLE event_analyses DROP COLUMN IF EXISTS selling_price;
-- ALTER TABLE event_analyses DROP COLUMN IF EXISTS cogs_per_unit;
-- ALTER TABLE event_analyses DROP COLUMN IF EXISTS gross_margin;
-- ALTER TABLE event_analyses DROP COLUMN IF EXISTS estimated_units;
