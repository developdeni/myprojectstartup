-- ============================================
-- Migration 003: Achievements System
-- ============================================

-- Master achievements catalog (static data)
-- NOTE: icon DEFAULT '' — MySQL strict mode rejects emoji as column defaults.
--       Actual emoji values are set in the INSERT statements below.
CREATE TABLE IF NOT EXISTS achievements (
    id          VARCHAR(40)  PRIMARY KEY COMMENT 'slug key e.g. first_win',
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon        VARCHAR(20)  NOT NULL DEFAULT '',
    tier        ENUM('basic','advanced','hardcore') DEFAULT 'basic',
    sort_order  INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user unlocked achievements
CREATE TABLE IF NOT EXISTS user_achievements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT         NOT NULL,
    achievement_id VARCHAR(40) NOT NULL,
    unlocked_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_ach (user_id, achievement_id),
    FOREIGN KEY (user_id)        REFERENCES users(id),
    FOREIGN KEY (achievement_id) REFERENCES achievements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_ua_user ON user_achievements(user_id);

-- ── Seed achievement catalog ──────────────────────────────────────────────
-- Emoji values are safe in INSERT statements (only DEFAULT clause is restricted).
INSERT IGNORE INTO achievements (id, name, description, icon, tier, sort_order) VALUES
-- Basic
('first_win',       'First Win',        'Win your very first game',                  '🎉', 'basic',    10),
('wins_3_streak',   '3 Wins in a Row',  'Win 3 games consecutively',                 '🔥', 'basic',    20),
('games_10',        '10 Games Played',  'Play a total of 10 games',                  '🎮', 'basic',    30),
('online_winner',   'Online Champion',  'Win your first online multiplayer game',    '🌐', 'basic',    35),
-- Advanced
('double_killer',   'Double Killer',    'Capture two pieces in a single move',       '⚡', 'advanced', 40),
('king_hunter',     'King Hunter',      'Capture an opponents king piece',           '👑', 'advanced', 50),
('no_loss_victory', 'No Loss Victory',  'Win without losing a single piece',         '🛡', 'advanced', 60),
('rating_1200',     'Rising Star',      'Reach an ELO rating of 1200',              '⭐', 'advanced', 75),
('games_50',        'Veteran',          'Play 50 games in total',                    '🎖', 'advanced', 70),
-- Hardcore
('beat_hard_ai',    'Beat Hard AI',     'Defeat the AI on Hard or Expert',           '🤖', 'hardcore', 80),
('win_under_3min',  'Speed Demon',      'Win a game in under 3 minutes',             '💨', 'hardcore', 90),
('wins_5_streak',   '5 Win Streak',     'Win 5 games in a row',                      '💥', 'hardcore', 100);
