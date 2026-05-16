-- ============================================
-- Migration 006: Chests & Rewards System
-- ============================================

-- User's earned chests (opened + unopened)
CREATE TABLE IF NOT EXISTS user_chests (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT         NOT NULL,
    chest_type VARCHAR(20) NOT NULL COMMENT 'common|rare|epic|legendary',
    earned_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    opened_at  TIMESTAMP   NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Individual rewards from an opened chest
CREATE TABLE IF NOT EXISTS user_chest_rewards (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_chest_id INT NOT NULL,
    user_id      INT NOT NULL,
    reward_type  VARCHAR(50)  NOT NULL COMMENT 'xp|skin|board_theme|win_animation',
    reward_value VARCHAR(100) NOT NULL,
    received_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_chest_id) REFERENCES user_chests(id),
    FOREIGN KEY (user_id)       REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_uc_user  ON user_chests(user_id, opened_at);
CREATE INDEX IF NOT EXISTS idx_ucr_user ON user_chest_rewards(user_id);
