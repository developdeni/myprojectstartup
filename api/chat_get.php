<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();
header('Content-Type: application/json');

$roomCode = trim($_GET['room_code'] ?? '');
$since = (int) ($_GET['since'] ?? 0);

if (empty($roomCode))
    jsonError('Missing room_code');

$game = Database::queryOne(
    "SELECT id FROM games WHERE room_code = ?",
    [$roomCode]
);
if (!$game)
    jsonError('Game not found', 404);

$messages = Database::query(
    "SELECT cm.id, cm.message, cm.created_at,
            u.username,
            (cm.user_id = ?) AS is_mine
     FROM chat_messages cm
     JOIN users u ON cm.user_id = u.id
     WHERE cm.game_id = ?
       AND UNIX_TIMESTAMP(cm.created_at) > ?
     ORDER BY cm.created_at ASC
     LIMIT 50",
    [$_SESSION['user_id'], $game['id'], $since]
);

jsonSuccess([
    'messages' => $messages,
    'server_time' => time(),
]);
