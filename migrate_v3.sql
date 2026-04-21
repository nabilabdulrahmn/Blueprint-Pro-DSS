ALTER TABLE event_analyses
ADD COLUMN expected_wastage_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER hours_per_day,
ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER expected_wastage_pct,
ADD COLUMN is_tax_inclusive BOOLEAN NOT NULL DEFAULT 1 AFTER tax_rate;
