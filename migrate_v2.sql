-- DeKukis Migration Script v2
-- Adds inventory tracking columns for wastage/shrinkage analysis
-- Run this in phpMyAdmin if you have EXISTING data

USE dekukis_db;

-- Add inventory tracking columns to event_actual_products
ALTER TABLE event_actual_products
  ADD COLUMN IF NOT EXISTS starting_inventory INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS remaining_stock INT NOT NULL DEFAULT 0;
