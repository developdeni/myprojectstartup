<?php
/**
 * api/open_chest.php
 * Opens a chest owned by the current user and returns the rewards.
 */
require_once __DIR__ . '/../config/app.php';
requireAuth();

$data    = json_decode(file_get_contents('php://input'), true);
$chestId = isset($data['chest_id']) ? (int)$data['chest_id'] : 0;
$userId  = (int)$_SESSION['user_id'];

if ($chestId <= 0) jsonError('Invalid chest_id');

$result = openChest($userId, $chestId);
if (!$result) jsonError('Chest not found or already opened', 404);

// Also return updated XP so frontend can refresh
$freshUser = Database::queryOne("SELECT xp, level FROM users WHERE id=?", [$userId]);

jsonSuccess([
    'chest_type' => $result['chest_type'],
    'chest_meta' => $result['chest_meta'],
    'rewards'    => $result['rewards'],
    'total_xp'   => (int)($freshUser['xp'] ?? 0),
    'new_level'  => (int)($freshUser['level'] ?? 1),
]);
