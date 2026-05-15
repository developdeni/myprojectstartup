<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();
header('Content-Type: application/json');

$code = uniqueRoomCode();
$gameId = Database::execute(
    "INSERT INTO games (room_code, player1_id, mode, status) VALUES (?,?,?,?)",
    [$code, $_SESSION['user_id'], 'online', 'waiting']
);
jsonSuccess(['room_code' => $code, 'game_id' => $gameId]);
