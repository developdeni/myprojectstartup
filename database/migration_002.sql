-- ============================================
-- Migration 002: XP & Levels System
-- ============================================

-- Add XP tracking columns to users table
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS xp INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS level INT DEFAULT 1,
  ADD COLUMN IF NOT EXISTS win_streak INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS xp_log JSON DEFAULT NULL;

-- XP events log table (for history/debugging)
CREATE TABLE IF NOT EXISTS xp_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL COMMENT 'win_pvp, win_ai_medium, win_ai_hard, win_fast, double_capture, win_streak_3',
    xp_awarded INT NOT NULL,
    game_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL
);

-- Index for fast per-user lookups
CREATE INDEX IF NOT EXISTS idx_xp_events_user ON xp_events(user_id);
