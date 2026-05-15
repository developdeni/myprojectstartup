<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$winner = $data['winner'] ?? null;
$mode = $data['mode'] ?? 'ai';
$userId = $_SESSION['user_id'];

$user = Database::queryOne("SELECT * FROM users WHERE id=?", [$userId]);
if (!$user)
    jsonError('User not found', 404);

$isWin = $winner === 1;
$isDraw = $winner === null;
$wins = $user['wins'] + ($isWin ? 1 : 0);
$losses = $user['losses'] + (!$isWin && !$isDraw ? 1 : 0);
$draws = $user['draws'] + ($isDraw ? 1 : 0);
$total = $user['total_games'] + 1;
$newRating = $user['rating'];
if ($mode === 'online') {
    $score = $isWin ? 1.0 : ($isDraw ? 0.5 : 0.0);
    $elo = calculateElo($user['rating'], 1200, $score);
    $newRating = $elo['new_a'];
}

Database::execute(
    "UPDATE users SET wins=?,losses=?,draws=?,total_games=?,rating=? WHERE id=?",
    [$wins, $losses, $draws, $total, $newRating, $userId]
);

jsonSuccess(['new_rating' => $newRating]);
