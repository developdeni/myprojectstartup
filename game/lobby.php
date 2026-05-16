<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();
$user    = getCurrentUser();
$totalXp = (int)($user['xp'] ?? 0);
$lvlInfo = calcLevelFromXp($totalXp);
$xpPct   = round($lvlInfo['progress'] * 100);
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
    .lobby-lvl-badge{display:flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-weight:700;font-size:.8rem;border:1.5px solid var(--lvl-c);color:var(--lvl-c);white-space:nowrap}
    .lobby-xp-mini{display:flex;flex-direction:column;gap:3px;min-width:90px}
    .lobby-xp-mini .lxp-bar{height:5px;background:var(--border2);border-radius:99px;overflow:hidden}
    .lobby-xp-mini .lxp-fill{height:100%;border-radius:99px;background:var(--lvl-c);transition:width 1s ease}
    .lobby-xp-mini .lxp-label{font-size:.68rem;color:var(--text2);text-align:right}

    /* ── Streak Widget ─────────────────────────────── */
    .streak-widget{background:linear-gradient(135deg,rgba(255,120,0,.08),rgba(255,60,0,.04));border:1px solid rgba(255,120,0,.25);border-radius:var(--radius);padding:20px 24px;margin-top:20px;position:relative;overflow:hidden}
    .streak-widget::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at top left,rgba(255,120,0,.06),transparent 70%);pointer-events:none}
    .streak-top{display:flex;align-items:center;gap:14px;margin-bottom:16px}
    .streak-flame{font-size:2.4rem;line-height:1;animation:flamePulse 1.8s ease-in-out infinite}
    @keyframes flamePulse{0%,100%{transform:scale(1) rotate(-3deg)}50%{transform:scale(1.12) rotate(3deg)}}
    .streak-flame.dead{animation:none;filter:grayscale(1);opacity:.4}
    .streak-count{font-size:2rem;font-weight:800;line-height:1;color:#ff7800}
    .streak-label{font-size:.82rem;color:var(--text2);margin-top:2px}
    .streak-boost-badge{margin-left:auto;display:flex;align-items:center;gap:6px;background:rgba(124,106,247,.15);border:1px solid rgba(124,106,247,.35);border-radius:20px;padding:5px 12px;font-size:.78rem;font-weight:700;color:var(--accent);white-space:nowrap}
    .streak-milestones{display:flex;gap:8px;align-items:center}
    .streak-ms{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1}
    .streak-ms-icon{font-size:1.4rem;transition:.3s}
    .streak-ms-day{font-size:.68rem;font-weight:700;color:var(--text2)}
    .streak-ms.reached .streak-ms-icon{filter:drop-shadow(0 0 6px rgba(255,180,0,.7));transform:scale(1.1)}
    .streak-ms.reached .streak-ms-day{color:#ff9500;font-weight:800}
    .streak-ms.locked .streak-ms-icon{filter:grayscale(1);opacity:.35}
    .streak-connector{flex:1;height:3px;background:var(--border2);border-radius:99px;position:relative;overflow:hidden}
    .streak-connector-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#ff7800,#ff4500);transition:width 1.2s cubic-bezier(.22,1,.36,1)}
    .streak-next{font-size:.78rem;color:var(--text2);margin-top:12px;text-align:center}

    /* ── Reward Modal ──────────────────────────────── */
    #streakRewardModal{position:fixed;inset:0;z-index:3000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:.35s}
    #streakRewardModal.visible{opacity:1;pointer-events:all}
    #streakRewardModal .srm-bg{position:absolute;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(16px)}
    .srm-box{position:relative;z-index:1;background:var(--bg2);border:1px solid rgba(255,120,0,.4);border-radius:28px;padding:48px 40px;text-align:center;max-width:380px;width:90%;transform:scale(.8) translateY(30px);transition:.4s cubic-bezier(.22,1,.36,1)}
    #streakRewardModal.visible .srm-box{transform:scale(1) translateY(0)}
    .srm-glow{position:absolute;inset:-2px;border-radius:28px;background:linear-gradient(135deg,#ff7800,#ff4500,#ffd700);z-index:-1;opacity:.3;filter:blur(14px)}
    .srm-big-icon{font-size:5rem;display:block;margin-bottom:16px;animation:rewardPop .6s cubic-bezier(.22,1,.36,1)}
    @keyframes rewardPop{from{transform:scale(0) rotate(-20deg)}to{transform:scale(1) rotate(0)}}
    .srm-streak-num{font-size:.85rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#ff9500;margin-bottom:8px}
    .srm-title{font-size:1.6rem;font-weight:800;margin-bottom:8px}
    .srm-desc{color:var(--text2);font-size:.9rem;line-height:1.5;margin-bottom:28px}
    .srm-close{background:linear-gradient(135deg,#ff7800,#ff4500);color:#fff;border:none;border-radius:14px;padding:14px 36px;font-family:'Outfit',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;box-shadow:0 4px 20px rgba(255,100,0,.4)}
    .srm-close:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(255,100,0,.5)}
    /* Confetti canvas */
    #streakConfetti{position:fixed;inset:0;pointer-events:none;z-index:2999}

    /* ── Mobile Adaptation ── */
    @media (max-width: 768px) {
        .lobby-page { padding-top: 75px; }
        .diff-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .diff-row .btn-hero-outline { padding: 10px; font-size: .8rem; text-align: center; }
        .streak-widget { padding: 16px; margin-top: 16px; }
        .streak-top { flex-direction: column; align-items: flex-start; gap: 10px; text-align: left; }
        .streak-boost-badge { margin-left: 0; align-self: stretch; justify-content: center; }
        .streak-count { font-size: 1.8rem; }
        .streak-ms-icon { font-size: 1.2rem; }
        .streak-ms-day { font-size: .6rem; }
        .srm-box { padding: 32px 24px; }
        .srm-title { font-size: 1.3rem; }
    }
    </style>
</head>
<body class="landing-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="bg-gradient-orb orb-2"></div><div class="chess-grid"></div></div>

<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="/" class="nav-logo"><span class="logo-icon">♛</span><span class="logo-text">Check<span class="accent">Masters</span></span></a>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
            <span style="color:var(--text2);font-size:.85rem">★ <?= $user['rating'] ?></span>
            <!-- Level badge -->
            <a href="../profile/index.php" class="lobby-lvl-badge" style="--lvl-c:<?= $lvlInfo['color'] ?>;text-decoration:none">
                <?= $lvlInfo['icon'] ?> <?= $lvlInfo['name'] ?>
            </a>
            <!-- Mini XP bar -->
            <div class="lobby-xp-mini" style="--lvl-c:<?= $lvlInfo['color'] ?>">
                <div class="lxp-bar"><div class="lxp-fill" id="lobbyXpFill" style="width:0%"></div></div>
                <div class="lxp-label"><?= number_format($totalXp) ?> XP</div>
            </div>
            <a href="../profile/index.php" class="btn btn-ghost" style="padding:8px 14px"><?= htmlspecialchars($user['username']) ?></a>
            <a href="../auth/logout.php" class="btn btn-ghost" style="padding:8px 14px">Выйти</a>
        </div>
    </div>
</nav>

<section class="lobby-page">
    <div style="max-width:900px;margin:0 auto">
        <div class="section-header" style="margin-bottom:32px">
            <h1 class="section-title">Выбери <span class="gradient-text">режим игры</span></h1>
            <p style="color:var(--text2)">
                Привет, <strong style="color:var(--text)"><?= htmlspecialchars($user['username']) ?></strong>!
                Рейтинг: <strong style="color:var(--accent)"><?= $user['rating'] ?></strong>
                &nbsp;·&nbsp;
                <span style="color:<?= $lvlInfo['color'] ?>;font-weight:700"><?= $lvlInfo['icon'] ?> <?= $lvlInfo['name'] ?></span>
                <?php if (!$lvlInfo['is_max']): ?>
                &nbsp;<span style="color:var(--text2);font-size:.82rem">(<?= number_format($totalXp) ?> / <?= number_format($lvlInfo['xp_next']) ?> XP)</span>
                <?php endif; ?>
            </p>
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

        <!-- ── Daily Streak Widget ── -->
        <div class="streak-widget" id="streakWidget" style="display:none">
            <div class="streak-top">
                <span class="streak-flame" id="streakFlame">🔥</span>
                <div>
                    <div class="streak-count" id="streakCount">0</div>
                    <div class="streak-label" id="streakLabel">день подряд</div>
                </div>
                <div id="streakBoostBadge" class="streak-boost-badge" style="display:none">
                    ⚡ XP Boost ×2 активен
                </div>
            </div>
            <div class="streak-milestones" id="streakMilestones"></div>
            <div class="streak-next" id="streakNext"></div>
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

<!-- Streak Reward Modal -->
<div id="streakRewardModal">
    <div class="srm-bg" onclick="closeStreakReward()"></div>
    <div class="srm-box">
        <div class="srm-glow"></div>
        <span class="srm-big-icon" id="srmIcon">🔥</span>
        <div class="srm-streak-num" id="srmStreak"></div>
        <div class="srm-title" id="srmTitle">Награда получена!</div>
        <div class="srm-desc" id="srmDesc"></div>
        <button class="srm-close" onclick="closeStreakReward()">🎉 Отлично!</button>
    </div>
</div>
<canvas id="streakConfetti"></canvas>

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

// Animate mini XP bar
document.addEventListener('DOMContentLoaded', () => {
    const fill = document.getElementById('lobbyXpFill');
    if (fill) requestAnimationFrame(() => { fill.style.width = '<?= $xpPct ?>%'; });

    // Load streak
    loadStreak();
});

// ── Daily Streak ────────────────────────────────────────────────────────────
const MILESTONES = [3, 7, 14, 30];
const MS_ICONS   = {3:'✨', 7:'⚡', 14:'🌌', 30:'🪐'};
let pendingRewards = [];

async function loadStreak() {
    try {
        const res  = await fetch('../api/check_streak.php');
        const data = await res.json();
        if (!data.success) return;
        renderStreakWidget(data);
        if (data.new_rewards && data.new_rewards.length > 0) {
            pendingRewards = data.new_rewards;
            setTimeout(() => showNextReward(), 600);
        }
    } catch(e) { /* silent */ }
}

function renderStreakWidget(data) {
    const widget = document.getElementById('streakWidget');
    widget.style.display = 'block';

    const streak = data.streak;
    document.getElementById('streakCount').textContent = streak;
    document.getElementById('streakLabel').textContent =
        streak === 1 ? 'день подряд' :
        streak >= 2 && streak <= 4 ? 'дня подряд' : 'дней подряд';

    const flame = document.getElementById('streakFlame');
    if (streak === 0) flame.classList.add('dead');
    else flame.classList.remove('dead');

    // XP Boost badge
    if (data.xp_boost_active) {
        const badge = document.getElementById('streakBoostBadge');
        badge.style.display = 'flex';
        if (data.xp_boost_until) {
            const until = new Date(data.xp_boost_until.replace(' ','T'));
            const hrs   = Math.max(0, Math.round((until - Date.now()) / 3600000));
            badge.textContent = `⚡ XP ×2 ещё ${hrs}ч`;
        }
    }

    // Milestones row
    const container = document.getElementById('streakMilestones');
    container.innerHTML = '';
    data.milestones.forEach((ms, idx) => {
        if (idx > 0) {
            // Connector line between milestones
            const prevMs = data.milestones[idx - 1];
            const conn = document.createElement('div');
            conn.className = 'streak-connector';
            const fill = document.createElement('div');
            fill.className = 'streak-connector-fill';
            // Fill proportion: how far through this segment?
            const segStart = prevMs.day;
            const segEnd   = ms.day;
            const pct = ms.reached ? 100
                : Math.min(100, Math.round(Math.max(0, (streak - segStart) / (segEnd - segStart)) * 100));
            fill.style.width = '0%';
            setTimeout(() => { fill.style.width = pct + '%'; }, 200);
            conn.appendChild(fill);
            container.appendChild(conn);
        }
        const el = document.createElement('div');
        el.className = 'streak-ms ' + (ms.reached ? 'reached' : 'locked');
        el.innerHTML = `<span class="streak-ms-icon">${ms.icon}</span><span class="streak-ms-day">День ${ms.day}</span>`;
        el.title = ms.name;
        container.appendChild(el);
    });

    // Next milestone hint
    const nextEl = document.getElementById('streakNext');
    if (data.next_milestone) {
        const n = data.next_milestone;
        nextEl.textContent = `${n.reward_icon} До награды «${n.reward_name}» осталось ${n.days_left} ${n.days_left === 1 ? 'день' : n.days_left < 5 ? 'дня' : 'дней'}`;
    } else {
        nextEl.textContent = '👑 Все награды серии получены!';
    }
}

// ── Reward celebration ───────────────────────────────────────────────────────
function showNextReward() {
    if (!pendingRewards.length) return;
    const reward = pendingRewards.shift();
    document.getElementById('srmIcon').textContent    = reward.reward_icon;
    document.getElementById('srmStreak').textContent  = `🔥 Серия: ${reward.milestone} дней подряд`;
    document.getElementById('srmTitle').textContent   = reward.reward_name;
    document.getElementById('srmDesc').textContent    = reward.reward_desc;
    document.getElementById('streakRewardModal').classList.add('visible');
    launchConfetti();
}

function closeStreakReward() {
    document.getElementById('streakRewardModal').classList.remove('visible');
    stopConfetti();
    // Queue next reward if any
    if (pendingRewards.length) setTimeout(() => showNextReward(), 500);
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeStreakReward(); });

// ── Confetti engine ──────────────────────────────────────────────────────────
let confettiRAF = null;
const confettiColors = ['#ff7800','#ffd700','#ff4500','#7c6af7','#00e5ff','#ff69b4','#50fa7b'];

function launchConfetti() {
    const canvas = document.getElementById('streakConfetti');
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    const ctx = canvas.getContext('2d');
    const particles = Array.from({length: 120}, () => ({
        x:   Math.random() * canvas.width,
        y:   Math.random() * -canvas.height * 0.5,
        vx:  (Math.random() - 0.5) * 4,
        vy:  Math.random() * 4 + 2,
        size:Math.random() * 8 + 4,
        color: confettiColors[Math.floor(Math.random() * confettiColors.length)],
        rot:  Math.random() * 360,
        rotV: (Math.random() - 0.5) * 8,
        shape: Math.random() > 0.5 ? 'rect' : 'circle',
    }));
    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            p.x  += p.vx;
            p.y  += p.vy;
            p.rot += p.rotV;
            p.vy += 0.08; // gravity
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot * Math.PI / 180);
            ctx.fillStyle = p.color;
            ctx.globalAlpha = Math.max(0, 1 - p.y / canvas.height);
            if (p.shape === 'rect') ctx.fillRect(-p.size/2, -p.size/4, p.size, p.size/2);
            else { ctx.beginPath(); ctx.arc(0,0,p.size/2,0,Math.PI*2); ctx.fill(); }
            ctx.restore();
        });
        if (particles.some(p => p.y < canvas.height)) {
            confettiRAF = requestAnimationFrame(draw);
        }
    }
    stopConfetti();
    draw();
}

function stopConfetti() {
    if (confettiRAF) { cancelAnimationFrame(confettiRAF); confettiRAF = null; }
    const c = document.getElementById('streakConfetti');
    c.getContext('2d').clearRect(0, 0, c.width, c.height);
}
</script>
</body>
</html>
