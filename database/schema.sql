-- ============================================
-- CHECKERS PLATFORM - Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS checkers_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE checkers_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    skin VARCHAR(50) DEFAULT 'classic',
    board_theme VARCHAR(50) DEFAULT 'classic',
    is_pro TINYINT(1) DEFAULT 0,
    country VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    rating INT DEFAULT 1000,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    draws INT DEFAULT 0,
    total_games INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Games table
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) UNIQUE NOT NULL,
    player1_id INT NOT NULL,
    player2_id INT DEFAULT NULL,
    winner_id INT DEFAULT NULL,
    status ENUM('waiting','active','finished','abandoned') DEFAULT 'waiting',
    mode ENUM('pvp','ai','online') DEFAULT 'pvp',
    ai_difficulty ENUM('easy','medium','hard','expert') DEFAULT 'medium',
    time_control INT DEFAULT 300,
    moves_json LONGTEXT DEFAULT NULL,
    board_state LONGTEXT DEFAULT NULL,
    analysis_json LONGTEXT DEFAULT NULL,
    player1_rating_before INT DEFAULT 1000,
    player2_rating_before INT DEFAULT NULL,
    rating_change INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP DEFAULT NULL,
    FOREIGN KEY (player1_id) REFERENCES users(id),
    FOREIGN KEY (player2_id) REFERENCES users(id),
    FOREIGN KEY (winner_id) REFERENCES users(id)
);

-- Moves table
CREATE TABLE IF NOT EXISTS moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    player_id INT NOT NULL,
    move_number INT NOT NULL,
    from_row INT NOT NULL,
    from_col INT NOT NULL,
    to_row INT NOT NULL,
    to_col INT NOT NULL,
    captures JSON DEFAULT NULL,
    is_king_move TINYINT(1) DEFAULT 0,
    piece_became_king TINYINT(1) DEFAULT 0,
    board_state_after LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (player_id) REFERENCES users(id)
);

-- Leaderboard (cached)
CREATE TABLE IF NOT EXISTS leaderboard_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    city VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    rating INT DEFAULT 1000,
    wins INT DEFAULT 0,
    total_games INT DEFAULT 0,
    win_rate DECIMAL(5,2) DEFAULT 0,
    rank_global INT DEFAULT NULL,
    rank_city INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Transactions / Pro subscriptions
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stripe_session_id VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    type ENUM('pro_monthly','pro_yearly','skin','board') NOT NULL,
    item_id VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Skins catalog
CREATE TABLE IF NOT EXISTS skins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(10,2) DEFAULT 0,
    is_pro_only TINYINT(1) DEFAULT 0,
    preview_img VARCHAR(255) DEFAULT NULL,
    type ENUM('piece','board') DEFAULT 'piece',
    color1 VARCHAR(20) DEFAULT '#e74c3c',
    color2 VARCHAR(20) DEFAULT '#2c3e50',
    board_light VARCHAR(20) DEFAULT '#f0d9b5',
    board_dark VARCHAR(20) DEFAULT '#b58863'
);

-- User owned skins
CREATE TABLE IF NOT EXISTS user_skins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skin_slug VARCHAR(50) NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Chat messages (for online games)
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================
-- Default skins
-- ============================================
INSERT INTO skins (slug, name, description, price, is_pro_only, type, color1, color2, board_light, board_dark) VALUES
('classic', 'Classic', 'Traditional checkers look', 0, 0, 'piece', '#e8c07d', '#2c2c2c', '#f0d9b5', '#b58863'),
('neon', 'Neon Cyber', 'Futuristic neon style', 2.99, 0, 'piece', '#00ffff', '#ff00ff', '#1a1a2e', '#16213e'),
('fire', 'Fire & Ice', 'Hot vs cold theme', 2.99, 0, 'piece', '#ff4500', '#00bfff', '#2d1b00', '#00152d'),
('gold', 'Gold & Obsidian', 'Premium metallic look', 0, 1, 'piece', '#ffd700', '#1a1a1a', '#3d2b00', '#1a1a1a'),
('emerald', 'Emerald', 'Royal green theme', 0, 1, 'piece', '#50fa7b', '#282a36', '#0d2614', '#071a0d'),
('galaxy', 'Galaxy', 'Space-inspired design', 4.99, 0, 'piece', '#a78bfa', '#f472b6', '#0f0c29', '#302b63');
