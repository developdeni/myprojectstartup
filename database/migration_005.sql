-- ============================================
-- Migration 005: Fix Achievement Icons
-- ============================================
-- Replaces broken hex strings in the database with actual emoji characters.

UPDATE achievements SET icon = '🎉' WHERE id = 'first_win';
UPDATE achievements SET icon = '🔥' WHERE id = 'wins_3_streak';
UPDATE achievements SET icon = '🎮' WHERE id = 'games_10';
UPDATE achievements SET icon = '🌐' WHERE id = 'online_winner';
UPDATE achievements SET icon = '⚡' WHERE id = 'double_killer';
UPDATE achievements SET icon = '👑' WHERE id = 'king_hunter';
UPDATE achievements SET icon = '🛡️' WHERE id = 'no_loss_victory';
UPDATE achievements SET icon = '⭐' WHERE id = 'rating_1200';
UPDATE achievements SET icon = '🎖️' WHERE id = 'games_50';
UPDATE achievements SET icon = '🤖' WHERE id = 'beat_hard_ai';
UPDATE achievements SET icon = '⚡' WHERE id = 'win_under_3min';
UPDATE achievements SET icon = '💥' WHERE id = 'wins_5_streak';
