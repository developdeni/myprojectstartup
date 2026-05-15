-- Migration: add updated_at to games table if not exists
-- Run this if your games table doesn't have updated_at column

ALTER TABLE games
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add move_data column to moves table if online_move.php needs it
-- (move_data stores full JSON, from_row/to_row are also available)
ALTER TABLE moves
  ADD COLUMN IF NOT EXISTS move_data LONGTEXT DEFAULT NULL;
