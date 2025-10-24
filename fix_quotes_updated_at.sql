-- Fix: Make updated_at column NULLABLE
-- Root Cause: Entity defines updatedAt as nullable, but DB column is NOT NULL
-- This causes constraint violation on INSERT

USE gembira_db;

-- Change updated_at to NULLABLE
ALTER TABLE quotes
MODIFY COLUMN updated_at datetime NULL COMMENT 'Waktu terakhir diupdate';

-- Verify
SHOW COLUMNS FROM quotes LIKE 'updated_at';
