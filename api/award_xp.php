<?php
/**
 * api/award_xp.php
 * Awards XP for in-game events like double captures.
 * Called from the game JS when an eligible event occurs.
 */
require_once __DIR__ . '/../config/app.php';
requireAuth();

$data      = json_decode(file_get_contents('php://input'), true);
$eventType = $data['event'] ?? '';
$gameId    = isset($data['game_id']) ? (int)$data['game_id'] : null;
$userId    = (int)$_SESSION['user_id'];

// Allowed events and their XP values
$allowedEvents = [
    'double_capture' => 10,
];

if (!isset($allowedEvents[$eventType])) {
    jsonError('Unknown event type');
}

$xpAmount = $allowedEvents[$eventType];
$result   = awardXp($userId, $xpAmount, $eventType, $gameId);

jsonSuccess($result);
