<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лобби — CheckMasters</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="landing-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="bg-gradient-orb orb-2"></div><div class="chess-grid"></div></div>
<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="/" class="nav-logo"><span class="logo-icon">♛</span><span class="logo-text">Check<span class="accent">Masters</span></span></a>
        <div style="margin-left:auto;display:flex;gap:12px;align-items:center">
            <span style="color:var(--text2);font-size:.9rem">★ <?= $user['rating'] ?></span>
            <a href="../profile/index.php" class="btn btn-ghost"><?= htmlspecialchars($user['username']) ?></a>
            <a href="../auth/logout.php" class="btn btn-ghost">Выйти</a>
        </div>
    </div>
</nav>
<section class="lobby-page">
    <div style="max-width:900px;margin:0 auto">
        <div class="section-header" style="margin-bottom:40px">
            <h1 class="section-title">Выбери <span class="gradient-text">режим игры</span></h1>
            <p style="color:var(--text2)">Привет, <?= htmlspecialchars($user['username']) ?>! Рейтинг: <strong style="color:var(--accent)"><?= $user['rating'] ?></strong></p>
        </div>
        <div class="lobby-grid">
            <!-- vs AI -->
            <a href="play.php?mode=ai&difficulty=medium" class="lobby-card">
                <div class="card-icon">🤖</div>
                <h3>Vs ИИ</h3>
                <p>Сражайся с нашим ИИ с разными уровнями сложности. Получи анализ партии после игры.</p>
                <span class="card-tag tag-green">Доступно всем</span>
            </a>
            <!-- PvP local -->
            <a href="play.php?mode=pvp" class="lobby-card">
                <div class="card-icon">👥</div>
                <h3>2 игрока</h3>
                <p>Играй с другом на одном устройстве. Идеально для быстрых дуэлей.</p>
                <span class="card-tag tag-accent">Локально</span>
            </a>
            <!-- Online -->
            <div class="lobby-card" id="createRoomCard" onclick="createRoom()">
                <div class="card-icon">🌐</div>
                <h3>Создать комнату</h3>
                <p>Создай комнату и отправь другу ссылку. Играйте онлайн в реальном времени.</p>
                <span class="card-tag tag-accent">WebSocket</span>
            </div>
            <div class="lobby-card" onclick="showJoinRoom()">
                <div class="card-icon">🔗</div>
                <h3>Войти по коду</h3>
                <p>Есть код комнаты от друга? Введи его и начни игру мгновенно.</p>
                <span class="card-tag tag-gold">По приглашению</span>
            </div>
        </div>

        <!-- Difficulty selector for AI -->
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-top:24px">
            <h3 style="margin-bottom:16px">⚡ Быстрая игра vs ИИ</h3>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <a href="play.php?mode=ai&difficulty=easy" class="btn btn-hero-outline">🟢 Лёгкий</a>
                <a href="play.php?mode=ai&difficulty=medium" class="btn btn-hero-outline">🟡 Средний</a>
                <a href="play.php?mode=ai&difficulty=hard" class="btn btn-hero-outline<?= !$user['is_pro'] ? ' pro-locked' : '' ?>">🔴 Сложный <?= !$user['is_pro'] ? '🔒' : '' ?></a>
                <a href="<?= $user['is_pro'] ? 'play.php?mode=ai&difficulty=expert' : '../profile/upgrade.php' ?>" class="btn btn-hero-outline<?= !$user['is_pro'] ? ' pro-locked' : '' ?>">💀 Эксперт <?= !$user['is_pro'] ? '⚡Pro' : '' ?></a>
            </div>
        </div>
    </div>
</section>

<!-- Join Room Modal -->
<div class="modal-overlay" id="joinModal" onclick="if(event.target===this)this.classList.remove('visible')">
    <div class="modal-box" style="max-width:360px">
        <div style="font-size:2rem;margin-bottom:16px">🔗</div>
        <h2 style="margin-bottom:8px">Войти в комнату</h2>
        <p style="color:var(--text2);margin-bottom:24px">Введи 6-значный код комнаты</p>
        <input type="text" id="roomCodeInput" class="form-input" placeholder="ABC123" maxlength="6" style="text-align:center;font-size:1.5rem;letter-spacing:.2em;text-transform:uppercase;margin-bottom:16px">
        <div class="modal-actions">
            <button onclick="joinRoom()" class="btn btn-hero">Войти →</button>
            <button onclick="document.getElementById('joinModal').classList.remove('visible')" class="btn btn-ghost">Отмена</button>
        </div>
    </div>
</div>

<script>
async function createRoom() {
    const res = await fetch('../api/create_room.php', {method:'POST'});
    const data = await res.json();
    if (data.room_code) window.location = `play.php?mode=online&room=${data.room_code}`;
}
function showJoinRoom() {
    document.getElementById('joinModal').classList.add('visible');
    document.getElementById('roomCodeInput').focus();
}
function joinRoom() {
    const code = document.getElementById('roomCodeInput').value.trim().toUpperCase();
    if (code.length === 6) window.location = `play.php?mode=online&room=${code}`;
}
document.getElementById('roomCodeInput')?.addEventListener('keydown', e => { if(e.key==='Enter') joinRoom(); });
</script>
</body>
</html>
