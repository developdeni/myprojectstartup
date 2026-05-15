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
    <style>
    .lobby-page{padding:90px 16px 40px;position:relative;z-index:1}
    .lobby-quick{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px 24px;margin-top:20px}
    .lobby-quick h3{font-size:1rem;margin-bottom:14px}
    .diff-row{display:flex;gap:10px;flex-wrap:wrap}
    .pro-locked{opacity:.6;cursor:not-allowed}
    .creating-spinner{display:none;flex-direction:column;align-items:center;gap:12px;padding:20px}
    .spinner{width:36px;height:36px;border:3px solid var(--border2);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body class="landing-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="bg-gradient-orb orb-2"></div><div class="chess-grid"></div></div>

<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="/" class="nav-logo"><span class="logo-icon">♛</span><span class="logo-text">Check<span class="accent">Masters</span></span></a>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
            <span style="color:var(--text2);font-size:.85rem">★ <?= $user['rating'] ?></span>
            <a href="../profile/index.php" class="btn btn-ghost" style="padding:8px 14px"><?= htmlspecialchars($user['username']) ?></a>
            <a href="../auth/logout.php" class="btn btn-ghost" style="padding:8px 14px">Выйти</a>
        </div>
    </div>
</nav>

<section class="lobby-page">
    <div style="max-width:900px;margin:0 auto">
        <div class="section-header" style="margin-bottom:32px">
            <h1 class="section-title">Выбери <span class="gradient-text">режим игры</span></h1>
            <p style="color:var(--text2)">Привет, <strong style="color:var(--text)"><?= htmlspecialchars($user['username']) ?></strong>! Рейтинг: <strong style="color:var(--accent)"><?= $user['rating'] ?></strong></p>
        </div>

        <div class="lobby-grid">
            <a href="play.php?mode=ai&difficulty=medium" class="lobby-card">
                <div class="card-icon">🤖</div>
                <h3>Vs ИИ</h3>
                <p>Сражайся с нашим Minimax ИИ. Анализ партии после игры от AI Coach.</p>
                <span class="card-tag tag-green">Доступно всем</span>
            </a>
            <a href="play.php?mode=pvp" class="lobby-card">
                <div class="card-icon">👥</div>
                <h3>2 игрока</h3>
                <p>Играй с другом на одном устройстве. Идеально для быстрых дуэлей.</p>
                <span class="card-tag tag-accent">Локально</span>
            </a>
            <div class="lobby-card" id="createRoomCard" onclick="createRoom()">
                <div class="card-icon">🌐</div>
                <h3>Создать комнату</h3>
                <p>Создай онлайн-комнату и получи код для друга. Играйте вместе!</p>
                <span class="card-tag tag-accent">Онлайн</span>
            </div>
            <div class="lobby-card" onclick="showJoinRoom()">
                <div class="card-icon">🔗</div>
                <h3>Войти по коду</h3>
                <p>Есть код комнаты от друга? Введи его и начни игру мгновенно.</p>
                <span class="card-tag tag-gold">По приглашению</span>
            </div>
        </div>

        <div class="lobby-quick">
            <h3>⚡ Быстрая игра vs ИИ</h3>
            <div class="diff-row">
                <a href="play.php?mode=ai&difficulty=easy" class="btn btn-hero-outline" style="padding:10px 18px;font-size:.9rem">🟢 Лёгкий</a>
                <a href="play.php?mode=ai&difficulty=medium" class="btn btn-hero-outline" style="padding:10px 18px;font-size:.9rem">🟡 Средний</a>
                <a href="play.php?mode=ai&difficulty=hard" class="btn btn-hero-outline<?= !$user['is_pro'] ? ' pro-locked' : '' ?>" style="padding:10px 18px;font-size:.9rem">🔴 Сложный<?= !$user['is_pro'] ? ' 🔒' : '' ?></a>
                <a href="<?= $user['is_pro'] ? 'play.php?mode=ai&difficulty=expert' : '../profile/upgrade.php' ?>" class="btn btn-hero-outline<?= !$user['is_pro'] ? ' pro-locked' : '' ?>" style="padding:10px 18px;font-size:.9rem">💀 Эксперт<?= !$user['is_pro'] ? ' ⚡Pro' : '' ?></a>
            </div>
        </div>
    </div>
</section>

<!-- ROOM CODE MODAL (shown after creating a room) -->
<div class="modal-overlay" id="roomCreatedModal" onclick="if(event.target===this)this.classList.remove('visible')">
    <div class="modal-box" style="max-width:400px">
        <div class="creating-spinner" id="creatingSpinner">
            <div class="spinner"></div>
            <p style="color:var(--text2)">Создаём комнату...</p>
        </div>
        <div id="roomCodeContent" style="display:none">
            <div style="font-size:2.5rem;margin-bottom:12px;text-align:center">🌐</div>
            <h2 style="text-align:center;margin-bottom:6px">Комната создана!</h2>
            <p style="color:var(--text2);text-align:center;margin-bottom:16px;font-size:.9rem">Отправь этот код другу чтобы он присоединился</p>
            <div class="room-code-display">
                <div class="room-code-label">Код комнаты</div>
                <span class="room-code-big" id="displayedRoomCode">------</span>
                <br>
                <button class="copy-btn" id="copyCodeBtn" onclick="copyRoomCode()">📋 Скопировать код</button>
                <br><br>
                <button class="copy-btn" id="copyLinkBtn" onclick="copyRoomLink()" style="width:100%">🔗 Скопировать ссылку</button>
            </div>
            <div style="display:flex;gap:12px;margin-top:16px">
                <button onclick="startOnlineGame()" class="btn btn-hero" style="flex:1;justify-content:center">🎮 Начать игру</button>
                <button onclick="closeRoomModal()" class="btn btn-ghost">Отмена</button>
            </div>
        </div>
    </div>
</div>

<!-- JOIN ROOM MODAL -->
<div class="modal-overlay" id="joinModal" onclick="if(event.target===this)this.classList.remove('visible')">
    <div class="modal-box" style="max-width:360px">
        <div style="font-size:2rem;margin-bottom:16px;text-align:center">🔗</div>
        <h2 style="margin-bottom:8px;text-align:center">Войти в комнату</h2>
        <p style="color:var(--text2);margin-bottom:20px;text-align:center;font-size:.9rem">Введи 6-значный код комнаты</p>
        <input type="text" id="roomCodeInput" class="form-input"
               placeholder="ABC123" maxlength="6"
               style="text-align:center;font-size:1.8rem;letter-spacing:.25em;text-transform:uppercase;margin-bottom:16px">
        <div class="modal-actions">
            <button onclick="joinRoom()" class="btn btn-hero">Войти →</button>
            <button onclick="document.getElementById('joinModal').classList.remove('visible')" class="btn btn-ghost">Отмена</button>
        </div>
    </div>
</div>

<script>
let currentRoomCode = null;

async function createRoom() {
    // Show modal with spinner
    document.getElementById('creatingSpinner').style.display = 'flex';
    document.getElementById('roomCodeContent').style.display = 'none';
    document.getElementById('roomCreatedModal').classList.add('visible');

    try {
        const res = await fetch('../api/create_room.php', { method: 'POST' });
        const data = await res.json();
        if (data.room_code) {
            currentRoomCode = data.room_code;
            document.getElementById('displayedRoomCode').textContent = data.room_code;
            document.getElementById('creatingSpinner').style.display = 'none';
            document.getElementById('roomCodeContent').style.display = 'block';
        }
    } catch(e) {
        alert('Ошибка создания комнаты. Проверь подключение.');
        document.getElementById('roomCreatedModal').classList.remove('visible');
    }
}

function copyRoomCode() {
    if (!currentRoomCode) return;
    navigator.clipboard.writeText(currentRoomCode).then(() => {
        const btn = document.getElementById('copyCodeBtn');
        btn.textContent = '✅ Скопировано!';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = '📋 Скопировать код'; btn.classList.remove('copied'); }, 2000);
    });
}

function copyRoomLink() {
    if (!currentRoomCode) return;
    const link = `${window.location.origin}/aibek/game/play.php?mode=online&room=${currentRoomCode}`;
    navigator.clipboard.writeText(link).then(() => {
        const btn = document.getElementById('copyLinkBtn');
        btn.textContent = '✅ Ссылка скопирована!';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = '🔗 Скопировать ссылку'; btn.classList.remove('copied'); }, 2000);
    });
}

function startOnlineGame() {
    if (currentRoomCode) window.location = `play.php?mode=online&room=${currentRoomCode}`;
}

function closeRoomModal() {
    document.getElementById('roomCreatedModal').classList.remove('visible');
    currentRoomCode = null;
}

function showJoinRoom() {
    document.getElementById('joinModal').classList.add('visible');
    setTimeout(() => document.getElementById('roomCodeInput').focus(), 100);
}

function joinRoom() {
    const code = document.getElementById('roomCodeInput').value.trim().toUpperCase();
    if (code.length >= 4) window.location = `play.php?mode=online&room=${code}`;
    else document.getElementById('roomCodeInput').focus();
}

document.getElementById('roomCodeInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') joinRoom();
    // Auto uppercase
    setTimeout(() => {
        e.target.value = e.target.value.toUpperCase();
    }, 0);
});
</script>
</body>
</html>
