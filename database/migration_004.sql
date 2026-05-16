-- ============================================
-- Migration 004: Daily Streak System
-- ============================================

-- Add streak & XP boost columns to users table
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS daily_streak          INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS max_streak            INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_played_at        DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS xp_boost_until        DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS last_streak_reward_day INT DEFAULT 0;

-- Add two extra board themes as streak rewards to skins catalog
INSERT IGNORE INTO skins (slug, name, description, price, is_pro_only, type, color1, color2, board_light, board_dark) VALUES
('midnight', 'Midnight Board', 'Dark cosmic board — rare streak reward',    0, 0, 'board', '#7c6af7', '#4c1d95', '#1e1b4b', '#0f0c29'),
('inferno',  'Inferno Board',  'Blazing lava board — legendary streak reward', 0, 0, 'board', '#ef4444', '#991b1b', '#3b0101', '#1c0101');

-- Streak reward events log
CREATE TABLE IF NOT EXISTS streak_reward_events (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    milestone    INT NOT NULL COMMENT '3, 7, 14, 30',
    reward_type  VARCHAR(50)  NOT NULL COMMENT 'skin | board_theme | xp_boost',
    reward_slug  VARCHAR(100) NOT NULL COMMENT 'skin slug or empty for xp_boost',
    awarded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_sre_user ON streak_reward_events(user_id);
