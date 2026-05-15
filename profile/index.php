<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();
$user = getCurrentUser();

$games = Database::query(
    "SELECT g.*, 
        u1.username as p1_name, u2.username as p2_name, uw.username as winner_name
     FROM games g
     LEFT JOIN users u1 ON g.player1_id=u1.id
     LEFT JOIN users u2 ON g.player2_id=u2.id
     LEFT JOIN users uw ON g.winner_id=uw.id
     WHERE g.player1_id=? OR g.player2_id=?
     ORDER BY g.started_at DESC LIMIT 20",
    [$user['id'], $user['id']]
);

$winRate = $user['total_games'] > 0 ? round($user['wins'] / $user['total_games'] * 100) : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> — CheckMasters</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="landing-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="chess-grid"></div></div>
<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="/" class="nav-logo"><span class="logo-icon">♛</span><span class="logo-text">Check<span class="accent">Masters</span></span></a>
        <div style="margin-left:auto;display:flex;gap:12px">
            <a href="../game/lobby.php" class="btn btn-ghost">Лобби</a>
            <a href="../auth/logout.php" class="btn btn-ghost">Выйти</a>
        </div>
    </div>
</nav>
<div class="profile-hero">
    <div class="profile-card">
        <div class="profile-banner"></div>
        <div class="profile-info">
            <div class="profile-avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
            <div>
                <div class="profile-name">
                    <?= htmlspecialchars($user['username']) ?>
                    <?php if ($user['is_pro']): ?><span class="profile-badge">⚡ Pro</span><?php endif; ?>
                </div>
                <div style="color:var(--text2);font-size:.9rem">
                    <?= htmlspecialchars($user['city'] ?? '') ?><?= ($user['city'] && $user['country']) ? ', ' : '' ?><?= htmlspecialchars($user['country'] ?? '') ?>
                    &nbsp;• На платформе с <?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?>
                </div>
            </div>
            <?php if (!$user['is_pro']): ?>
            <a href="upgrade.php" class="btn btn-hero" style="margin-left:auto">⚡ Upgrade to Pro</a>
            <?php endif; ?>
        </div>

        <div class="profile-stats">
            <div class="pstat"><div class="pstat-num" style="color:var(--accent)"><?= $user['rating'] ?></div><div class="pstat-label">Рейтинг ELO</div></div>
            <div class="pstat"><div class="pstat-num" style="color:var(--green)"><?= $user['wins'] ?></div><div class="pstat-label">Победы</div></div>
            <div class="pstat"><div class="pstat-num"><?= $user['total_games'] ?></div><div class="pstat-label">Всего игр</div></div>
            <div class="pstat"><div class="pstat-num" style="color:var(--gold)"><?= $winRate ?>%</div><div class="pstat-label">Winrate</div></div>
        </div>

        <div style="padding:24px">
            <h3 style="margin-bottom:16px;font-size:1rem;color:var(--text2)">ИСТОРИЯ ПАРТИЙ</h3>
            <?php if (empty($games)): ?>
            <p style="color:var(--text2);text-align:center;padding:40px">Нет сыгранных партий. <a href="../game/lobby.php" style="color:var(--accent)">Начни играть!</a></p>
            <?php else: ?>
            <table class="games-table">
                <thead><tr><th>Противник</th><th>Режим</th><th>Результат</th><th>Дата</th></tr></thead>
                <tbody>
                <?php foreach ($games as $g):
                    $isP1 = $g['player1_id'] == $user['id'];
                    $opponentName = $isP1 ? ($g['p2_name'] ?? '—') : $g['p1_name'];
                    $result = $g['winner_id'] == $user['id'] ? 'win' : ($g['winner_id'] ? 'loss' : ($g['status']==='finished'?'draw':'—'));
                    $resultLabel = match($result) {'win'=>'✅ Победа','loss'=>'❌ Поражение','draw'=>'🤝 Ничья',default=>'—'};
                    $resultClass = match($result) {'win'=>'result-win','loss'=>'result-loss','draw'=>'result-draw',default=>''};
                ?>
                <tr>
                    <td><?= htmlspecialchars($opponentName ?: ($g['mode']==='ai'?'🤖 ИИ':'—')) ?></td>
                    <td style="color:var(--text2);font-size:.85rem"><?= ucfirst($g['mode'] ?? '—') ?></td>
                    <td class="<?= $resultClass ?>"><?= $resultLabel ?></td>
                    <td style="color:var(--text2);font-size:.85rem"><?= date('d.m.Y', strtotime($g['started_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
