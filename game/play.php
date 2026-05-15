<?php
require_once __DIR__ . '/../config/app.php';
$user = getCurrentUser(); // null if not logged in — guest play allowed
$mode = $_GET['mode'] ?? 'ai';
$difficulty = $_GET['difficulty'] ?? 'medium';
$roomCode = $_GET['room'] ?? null;
$skin = $user['skin'] ?? 'classic';
$boardTheme = $user['board_theme'] ?? 'classic';
$playerName1 = $user ? htmlspecialchars($user['username']) : 'Гость';
$playerRating = $user ? $user['rating'] : 1000;

if ($mode === 'online' && !$user) redirect('/auth/login.php');

// Load game from DB if room code provided
$gameData = null;
if ($roomCode) {
    $gameData = Database::queryOne("SELECT * FROM games WHERE room_code=?", [$roomCode]);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Игра — CheckMasters</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/game.css">
</head>
<body class="game-page" data-theme="dark">

<!-- Game Header -->
<header class="game-header">
    <a href="<?= $user ? 'lobby.php' : '/' ?>" class="btn btn-ghost" style="padding:6px 12px">← Назад</a>
    <div style="display:flex;align-items:center;gap:16px">
        <span class="logo-text" style="font-size:1rem">♛ Check<span class="accent">Masters</span></span>
        <span class="game-mode-badge"><?= match($mode){ 'ai'=>'🤖 vs ИИ', 'pvp'=>'👥 2 игрока', 'online'=>'🌐 Онлайн', default=>'🤖 vs ИИ' } ?></span>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <button class="btn btn-ghost" id="btnHints" onclick="toggleHints()" title="Подсказки">💡</button>
        <button class="btn btn-ghost" id="btnSound" onclick="toggleSound()" title="Звук">🔊</button>
        <button class="btn btn-ghost" onclick="board.reset()" title="Новая игра">↺</button>
        <?php if ($user): ?><a href="../profile/index.php" class="btn btn-ghost"><?= strtoupper(substr($user['username'],0,1)) ?></a><?php endif; ?>
    </div>
</header>

<!-- Game Layout -->
<div class="game-layout">
    <!-- Left Sidebar: Player 2 (Opponent) -->
    <aside class="game-sidebar">
        <div class="player-panel" id="panel-p2">
            <div class="pp-head">
                <div class="pp-avatar"><?= $mode==='ai' ? '🤖' : '●' ?></div>
                <div>
                    <div class="pp-name"><?= $mode==='ai' ? ucfirst($difficulty).' ИИ' : ($mode==='pvp' ? 'Игрок 2' : 'Соперник') ?></div>
                    <div class="pp-rating">★ <?= $mode==='ai' ? match($difficulty){'easy'=>'800','medium'=>'1200','hard'=>'1600','expert'=>'2000',default=>'1200'} : '1000' ?></div>
                </div>
            </div>
            <div class="pp-timer" id="timer-p2">05:00</div>
            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.8rem;color:var(--text2)">
                <span>Взяты:</span><span id="capturedP1">0</span>
            </div>
        </div>

        <?php if ($mode === 'ai'): ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:10px;color:var(--text2)">Сложность ИИ</div>
            <?php foreach(['easy'=>'Лёгкий','medium'=>'Средний','hard'=>'Сложный','expert'=>'Эксперт'] as $d=>$label): ?>
            <button onclick="setDifficulty('<?=$d?>')" class="diff-btn <?= $difficulty===$d?'active':'' ?>" data-diff="<?=$d?>">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:10px;color:var(--text2)">История ходов</div>
            <div class="moves-list" id="moveList"></div>
        </div>
    </aside>

    <!-- Main Board -->
    <main class="game-main">
        <div class="board-wrap">
            <div class="board-labels-top"><?php for($c=0;$c<8;$c++) echo "<span>".chr(97+$c)."</span>"; ?></div>
            <div class="board-and-labels" style="display:flex">
                <div class="board-labels-side"><?php for($r=1;$r<=8;$r++) echo "<span>$r</span>"; ?></div>
                <div id="gameBoard" class="board"></div>
            </div>
        </div>
        <div class="ai-thinking-bar" id="aiThinkingIndicator" style="display:none">
            <span>ИИ думает</span>
            <div class="ai-thinking"><span></span><span></span><span></span></div>
        </div>
        <div class="game-status" id="gameStatus">Ваш ход ♟</div>
    </main>

    <!-- Right Sidebar: Player 1 (You) -->
    <aside class="game-sidebar game-sidebar--right">
        <div class="player-panel active" id="panel-p1">
            <div class="pp-head">
                <div class="pp-avatar"><?= strtoupper(substr($playerName1,0,1)) ?></div>
                <div>
                    <div class="pp-name"><?= $playerName1 ?> <?= ($user && $user['is_pro']) ? '<span class="profile-badge">⚡Pro</span>' : '' ?></div>
                    <div class="pp-rating">★ <?= $playerRating ?></div>
                </div>
            </div>
            <div class="pp-timer" id="timer-p1">05:00</div>
            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.8rem;color:var(--text2)">
                <span>Взяты:</span><span id="capturedP2">0</span>
            </div>
        </div>

        <!-- Skin selector -->
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:10px;color:var(--text2)">🎨 Скин</div>
            <div id="skinSelector" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px"></div>
        </div>

        <?php if ($mode === 'online'): ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:10px;color:var(--text2)">💬 Чат</div>
            <div id="chatMessages" style="height:120px;overflow-y:auto;font-size:.82rem;color:var(--text2);margin-bottom:8px"></div>
            <div style="display:flex;gap:6px">
                <input type="text" id="chatInput" class="form-input" style="padding:6px 10px;font-size:.82rem" placeholder="Сообщение...">
                <button onclick="sendChat()" class="btn btn-primary" style="padding:6px 12px">→</button>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top:auto;padding-top:16px">
            <a href="lobby.php" class="btn btn-ghost" style="width:100%;justify-content:center">← Лобби</a>
        </div>
    </aside>
</div>

<!-- Game Over Modal -->
<div class="modal-overlay" id="gameOverModal">
    <div class="modal-box">
        <div class="modal-trophy" id="modalTrophy">🏆</div>
        <div class="modal-title" id="modalTitle">Победа!</div>
        <div class="modal-sub" id="modalSub">Отличная игра!</div>
        <div id="aiCoachSection" style="display:none;margin-bottom:24px;text-align:left">
            <div style="font-size:.9rem;font-weight:700;margin-bottom:10px;color:var(--accent)">🤖 AI Coach анализ:</div>
            <div id="coachInsights" style="display:flex;flex-direction:column;gap:6px"></div>
        </div>
        <div class="modal-actions">
            <button onclick="board.reset();closeModal()" class="btn btn-hero">↺ Ещё раз</button>
            <a href="lobby.php" class="btn btn-hero-outline">🏠 Лобби</a>
        </div>
    </div>
</div>

<script src="../assets/js/engine.js"></script>
<script src="../assets/js/board.js"></script>
<script>
const MODE = '<?= $mode ?>';
const DIFFICULTY = '<?= $difficulty ?>';
const USER_ID = <?= $user ? $user['id'] : 'null' ?>;
const IS_PRO = <?= ($user && $user['is_pro']) ? 'true' : 'false' ?>;
const ROOM_CODE = '<?= $roomCode ?? '' ?>';
const USER_SKIN = '<?= $skin ?>';
let GAME_ID = null; // Will be set after game is created in DB

// Init board
const board = new BoardUI('gameBoard', {
    mode: MODE,
    difficulty: DIFFICULTY,
    playerSide: P1,
    skin: USER_SKIN,
    timeControl: 300,
    hintsEnabled: true,
    soundEnabled: true,
    onMove: handleMove,
    onGameOver: handleGameOver,
});

function handleMove(move, engine) {
    const status = document.getElementById('gameStatus');
    if (status) {
        if (engine.gameOver) {
            status.textContent = '';
        } else {
            status.textContent = engine.turn === P1 ? 'Ваш ход ♟' : (MODE === 'ai' ? 'ИИ думает...' : 'Ход соперника');
        }
    }
    // Save move to server (only if logged in and game exists)
    if (USER_ID && GAME_ID) {
        fetch('../api/save_move.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ game_id: GAME_ID, move })
        }).catch(() => {}); // Silently ignore network errors
    }
}

function handleGameOver(engine) {
    const modal = document.getElementById('gameOverModal');
    const trophy = document.getElementById('modalTrophy');
    const title = document.getElementById('modalTitle');
    const sub = document.getElementById('modalSub');

    const playerWon = engine.winner === P1;
    trophy.textContent = playerWon ? '🏆' : (engine.winner ? '😔' : '🤝');
    title.textContent = playerWon ? 'Победа!' : (engine.winner ? 'Поражение' : 'Ничья');
    sub.textContent = playerWon ? 'Великолепная игра! +32 к рейтингу' : 'Не сдавайся — следующий раз повезёт!';

    // AI Coach (Pro feature)
    if (IS_PRO || MODE !== 'online') {
        const insights = engine.analyzeGame();
        if (insights.length > 0) {
            document.getElementById('aiCoachSection').style.display = 'block';
            const container = document.getElementById('coachInsights');
            insights.slice(0, 4).forEach(ins => {
                const el = document.createElement('div');
                el.className = 'coach-message';
                el.innerHTML = `<span class="coach-icon">${ins.type === 'missed_capture' ? '⚠️' : '💡'}</span><span>${ins.msg}</span>`;
                container.appendChild(el);
            });
        }
    }

    modal.classList.add('visible');

    // Save result to server
    if (USER_ID) {
        fetch('../api/end_game.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ winner: engine.winner, mode: MODE })
        });
    }
}

function closeModal() {
    document.getElementById('gameOverModal').classList.remove('visible');
}

// Difficulty buttons
function setDifficulty(d) {
    board.setDifficulty(d);
    board.reset();
    document.querySelectorAll('.diff-btn').forEach(b => b.classList.toggle('active', b.dataset.diff === d));
}

// Sound & hints toggles
let soundOn = true, hintsOn = true;
function toggleSound() {
    soundOn = !soundOn;
    board.toggleSound(soundOn);
    document.getElementById('btnSound').textContent = soundOn ? '🔊' : '🔇';
}
function toggleHints() {
    hintsOn = !hintsOn;
    board.toggleHints(hintsOn);
    document.getElementById('btnHints').textContent = hintsOn ? '💡' : '◦';
}

// Skin selector
const skins = [
    {slug:'classic', name:'Classic', c1:'#e8c07d', c2:'#1a1a1a'},
    {slug:'neon', name:'Neon', c1:'#00ffff', c2:'#ff00ff'},
    {slug:'fire', name:'Fire', c1:'#ff4500', c2:'#00bfff'},
    {slug:'gold', name:'Gold', c1:'#ffd700', c2:'#1a1a1a', pro:true},
    {slug:'emerald', name:'Emerald', c1:'#50fa7b', c2:'#282a36', pro:true},
    {slug:'galaxy', name:'Galaxy', c1:'#a78bfa', c2:'#f472b6'},
];
const skinSel = document.getElementById('skinSelector');
skins.forEach(s => {
    const btn = document.createElement('button');
    btn.className = `skin-btn ${s.slug === USER_SKIN ? 'active' : ''}`;
    btn.title = s.name + (s.pro ? ' (Pro)' : '');
    btn.style.cssText = `background:linear-gradient(135deg,${s.c1},${s.c2});width:100%;aspect-ratio:1;border-radius:8px;border:2px solid ${s.slug===USER_SKIN?'var(--accent)':'transparent'};cursor:pointer;transition:.2s;position:relative`;
    if (s.pro && !IS_PRO) btn.innerHTML = '<span style="position:absolute;top:2px;right:2px;font-size:.5rem;background:gold;color:#000;border-radius:3px;padding:1px 3px">PRO</span>';
    btn.onclick = () => {
        if (s.pro && !IS_PRO) { window.location='../profile/upgrade.php'; return; }
        board.setSkin(s.slug);
        skinSel.querySelectorAll('.skin-btn').forEach(b => b.style.borderColor='transparent');
        btn.style.borderColor = 'var(--accent)';
    };
    skinSel.appendChild(btn);
});

// Click outside modal to close
document.getElementById('gameOverModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
