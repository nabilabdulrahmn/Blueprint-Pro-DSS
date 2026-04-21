-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    username    VARCHAR(50) NOT NULL UNIQUE,
    phone       VARCHAR(20) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Main event analyses table (no single-product columns)
CREATE TABLE IF NOT EXISTS event_analyses (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    event_name            VARCHAR(255) NOT NULL,
    event_days            INT NOT NULL DEFAULT 1,
    booth_rental          DECIMAL(10,2) NOT NULL DEFAULT 0,
    transport_cost        DECIMAL(10,2) NOT NULL DEFAULT 0,
    marketing_cost        DECIMAL(10,2) NOT NULL DEFAULT 0,
    num_staff             INT NOT NULL DEFAULT 1,
    hourly_wage           DECIMAL(10,2) NOT NULL DEFAULT 0,
    hours_per_day         DECIMAL(5,2) NOT NULL DEFAULT 8,
    total_traffic         INT NOT NULL DEFAULT 0,
    capture_rate          DECIMAL(5,2) NOT NULL DEFAULT 3.00,
    conversion_rate       DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    total_fixed_costs     DECIMAL(10,2) NOT NULL DEFAULT 0,
    weighted_margin       DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_estimated_units INT NOT NULL DEFAULT 0,
    estimated_revenue     DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_cogs            DECIMAL(10,2) NOT NULL DEFAULT 0,
    break_even_units      INT NOT NULL DEFAULT 0,
    projected_profit      DECIMAL(10,2) NOT NULL DEFAULT 0,
    required_conv_rate    DECIMAL(8,4) NOT NULL DEFAULT 0,
    risk_level            VARCHAR(20) NOT NULL DEFAULT 'LOW',
    verdict               VARCHAR(100) NOT NULL DEFAULT 'VIABLE',
    status                VARCHAR(20) NOT NULL DEFAULT 'forecast',
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forecast products per event
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

-- Post-event actual fixed costs
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

-- Post-event actual per-product sales
CREATE TABLE IF NOT EXISTS event_actual_products (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    event_id                INT NOT NULL,
    product_name            VARCHAR(255) NOT NULL,
    actual_selling_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_cogs_per_unit    DECIMAL(10,2) NOT NULL DEFAULT 0,
    actual_units_sold       INT NOT NULL DEFAULT 0,
    actual_revenue          DECIMAL(10,2) NOT NULL DEFAULT 0,
    starting_inventory      INT NOT NULL DEFAULT 0,
    remaining_stock         INT NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES event_analyses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;