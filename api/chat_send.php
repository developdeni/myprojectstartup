<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$roomCode = trim($data['room_code'] ?? '');
$message  = trim($data['message'] ?? '');

if (empty($roomCode) || empty($message)) jsonError('Missing data');
if (mb_strlen($message) > 300) jsonError('Message too long');

// Sanitize
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Get game
$game = Database::queryOne(
    "SELECT id FROM games WHERE room_code = ? AND (player1_id = ? OR player2_id = ?)",
    [$roomCode, $_SESSION['user_id'], $_SESSION['user_id']]
);
if (!$game) jsonError('Game not found or access denied', 403);

Database::execute(
    "INSERT INTO chat_messages (game_id, user_id, message) VALUES (?,?,?)",
    [$game['id'], $_SESSION['user_id'], $message]
);

jsonSuccess(['sent' => true]);
