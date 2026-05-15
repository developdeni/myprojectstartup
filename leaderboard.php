<?php
require_once __DIR__ . '/config/app.php';
$city = $_GET['city'] ?? null;
$country = $_GET['country'] ?? null;

$where = 'WHERE total_games > 0';
$params = [];
if ($city) { $where .= ' AND city=?'; $params[] = $city; }
if ($country) { $where .= ' AND country=?'; $params[] = $country; }

$players = Database::query(
    "SELECT username, city, country, rating, wins, losses, draws, total_games FROM users $where ORDER BY rating DESC LIMIT 50",
    $params
);
$cities = Database::query("SELECT DISTINCT city FROM users WHERE city IS NOT NULL AND city != '' ORDER BY city");
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лидерборд — CheckMasters</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=JetBrains+Mono:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="landing-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="chess-grid"></div></div>
<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="/" class="nav-logo"><span class="logo-icon">♛</span><span class="logo-text">Check<span class="accent">Masters</span></span></a>
        <div style="margin-left:auto;display:flex;gap:12px">
            <?php if ($user): ?>
            <a href="game/lobby.php" class="btn btn-ghost">Лобби</a>
            <a href="profile/index.php" class="btn btn-primary"><?= htmlspecialchars($user['username']) ?></a>
            <?php else: ?>
            <a href="auth/login.php" class="btn btn-ghost">Войти</a>
            <a href="auth/register.php" class="btn btn-primary">Играть</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<section style="padding:100px 24px 60px;position:relative;z-index:1">
    <div class="container">
        <div class="section-header">
            <h1 class="section-title">🏆 Глобальный <span class="gradient-text">рейтинг</span></h1>
            <p style="color:var(--text2)">Лучшие игроки CheckMasters со всего мира</p>
        </div>

        <!-- Filters -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;justify-content:center">
            <a href="leaderboard.php" class="btn <?= !$city&&!$country?'btn-primary':'btn-ghost' ?>">🌍 Все</a>
            <?php foreach($cities as $c): ?>
            <a href="leaderboard.php?city=<?= urlencode($c['city']) ?>" class="btn <?= $city===$c['city']?'btn-primary':'btn-ghost' ?>">
                📍 <?= htmlspecialchars($c['city']) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="leaderboard-widget">
            <div class="lb-row" style="background:var(--bg3);font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text2)">
                <span style="text-align:center">Ранг</span>
                <span></span>
                <span>Игрок</span>
                <span>Город</span>
                <span style="text-align:right">Рейтинг</span>
                <span style="text-align:right">Winrate</span>
            </div>
            <?php if (empty($players)): ?>
            <div class="lb-empty">
                <div style="font-size:3rem;margin-bottom:16px">🎯</div>
                <p>Нет игроков. <a href="auth/register.php" style="color:var(--accent)">Зарегистрируйся</a> и стань первым!</p>
            </div>
            <?php else: ?>
            <?php foreach ($players as $i => $p):
                $rank = $i + 1;
                $medal = match($rank){ 1=>'🥇', 2=>'🥈', 3=>'🥉', default=>"#$rank" };
                $winrate = $p['total_games'] > 0 ? round($p['wins']/$p['total_games']*100) : 0;
                $isMe = $user && $user['username'] === $p['username'];
            ?>
            <div class="lb-row <?= $rank<=3?'lb-row--top':'' ?>" style="<?= $isMe?'background:rgba(124,106,247,.1)':'' ?>">
                <span class="lb-rank" style="font-family:'JetBrains Mono',monospace"><?= $medal ?></span>
                <span class="lb-avatar" style="<?= $isMe?'background:var(--accent2)':'' ?>"><?= strtoupper(substr($p['username'],0,1)) ?></span>
                <span class="lb-name"><?= htmlspecialchars($p['username']) ?><?= $isMe?' ← Ты':''; ?></span>
                <span class="lb-city"><?= htmlspecialchars($p['city'] ?? '—') ?></span>
                <span class="lb-rating"><?= $p['rating'] ?></span>
                <span class="lb-winrate"><?= $winrate ?>%</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
</body>
</html>
