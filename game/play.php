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
        <?php if ($mode !== 'online'): ?>
        <button class="btn btn-ghost" onclick="board.reset()" title="Новая игра">↺</button>
        <?php else: ?>
        <button class="btn btn-ghost" onclick="surrenderGame()" style="color:var(--danger)" title="Сдаться">🏳️</button>
        <?php endif; ?>
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
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:8px;color:var(--text2)">💬 Чат</div>
            <div id="chatMessages" class="chat-box"></div>
            <div class="chat-input-row">
                <input type="text" id="chatInput" class="form-input"
                       style="padding:7px 10px;font-size:.82rem;flex:1"
                       placeholder="Напиши сообщение..." maxlength="200" autocomplete="off">
                <button id="chatSendBtn" onclick="sendChat()" class="chat-send-btn" title="Отправить">➤</button>
            </div>
            <div style="font-size:.68rem;color:var(--text2);margin-top:4px">Enter — отправить</div>
        </div>
        <?php endif; ?>



    </aside>
</div>

<!-- Mobile bottom bar (visible on small screens) -->
<div class="game-mobile-bar" id="gameMobileBar">
    <div class="mobile-timer-row">
        <div class="mobile-timer" id="mobile-panel-p2">
            <div class="mobile-timer-name"><?= $mode==='ai' ? '🤖 '.ucfirst($difficulty).' ИИ' : 'Игрок 2' ?></div>
            <div class="mobile-timer-time" id="mobile-timer-p2">05:00</div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <button onclick="board.reset()" class="btn btn-ghost" style="padding:6px 10px;font-size:1rem" title="Сброс">↺</button>
            <a href="lobby.php" class="btn btn-ghost" style="padding:6px 10px;font-size:.8rem">← Лобби</a>
        </div>
        <div class="mobile-timer active-turn" id="mobile-panel-p1">
            <div class="mobile-timer-name"><?= $playerName1 ?></div>
            <div class="mobile-timer-time" id="mobile-timer-p1">05:00</div>
        </div>
    </div>
    <div style="width:100%;text-align:center;font-size:.85rem;color:var(--text2)" id="mobileStatus">Ваш ход ♟</div>
</div>


<!-- Game Over Modal -->
<div class="modal-overlay" id="gameOverModal">
    <div class="modal-box">
        <div class="modal-trophy" id="modalTrophy">🏆</div>
        <div class="modal-title" id="modalTitle">Победа!</div>
        <div class="modal-sub" id="modalSub">Отличная игра!</div>
        <!-- XP & level-up feedback -->
        <div id="xpFeedback" style="display:none;margin:12px 0;padding:12px 16px;background:rgba(124,106,247,.12);border:1px solid rgba(124,106,247,.25);border-radius:12px;font-size:.9rem">
            <span id="xpFeedbackText"></span>
        </div>
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

<!-- Achievement unlock toast -->
<style>
#achToastContainer{position:fixed;bottom:24px;right:24px;z-index:3000;display:flex;flex-direction:column-reverse;gap:10px;pointer-events:none}
.ach-toast{background:linear-gradient(135deg,rgba(20,20,32,.97),rgba(30,28,50,.97));border:1px solid rgba(124,106,247,.5);border-radius:16px;padding:14px 18px;display:flex;align-items:center;gap:12px;min-width:260px;max-width:320px;box-shadow:0 8px 32px rgba(0,0,0,.5),0 0 0 1px rgba(124,106,247,.2);pointer-events:all;animation:toastIn .4s cubic-bezier(.22,1,.36,1) forwards}
@keyframes toastIn{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:none}}
.ach-toast.out{animation:toastOut .3s ease forwards}
@keyframes toastOut{to{opacity:0;transform:translateX(60px)}}
.ach-toast-icon{font-size:2rem;flex-shrink:0}
.ach-toast-body{}
.ach-toast-label{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);margin-bottom:2px}
.ach-toast-name{font-size:.92rem;font-weight:700;color:#fff}
.ach-toast-desc{font-size:.75rem;color:rgba(255,255,255,.55);margin-top:2px}
</style>
<div id="achToastContainer"></div>

<!-- Chest Opening Modal -->
<style>
#chestModal{position:fixed;inset:0;z-index:4000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:.35s}
#chestModal.visible{opacity:1;pointer-events:all}
#chestModal .cm-bg{position:absolute;inset:0;background:rgba(0,0,0,.88);backdrop-filter:blur(20px)}
.cm-box{position:relative;z-index:1;text-align:center;padding:52px 40px;max-width:440px;width:92%}
/* Chest card */
.cm-chest-wrap{position:relative;display:inline-flex;flex-direction:column;align-items:center;gap:10px;cursor:pointer;margin-bottom:20px}
.cm-chest-emoji{font-size:7rem;line-height:1;transition:transform .3s;filter:drop-shadow(0 0 24px var(--cc)) drop-shadow(0 0 50px var(--cc));animation:chestFloat 2.5s ease-in-out infinite}
@keyframes chestFloat{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-10px) rotate(2deg)}}
.cm-chest-wrap:hover .cm-chest-emoji{transform:scale(1.12)!important;animation:none}
.cm-chest-wrap.shaking .cm-chest-emoji{animation:chestShake .5s ease;}
@keyframes chestShake{0%,100%{transform:rotate(0)}20%{transform:rotate(-12deg) scale(1.05)}40%{transform:rotate(12deg) scale(1.1)}60%{transform:rotate(-8deg)}80%{transform:rotate(8deg)}}
.cm-chest-wrap.opening .cm-chest-emoji{animation:chestPop .7s cubic-bezier(.22,1,.36,1) forwards}
@keyframes chestPop{0%{transform:scale(1)}40%{transform:scale(1.4) rotate(15deg)}100%{transform:scale(0) rotate(30deg);opacity:0}}
.cm-chest-name{font-size:1.1rem;font-weight:800;letter-spacing:.04em;color:var(--cc)}
.cm-chest-tap{font-size:.82rem;color:rgba(255,255,255,.5);animation:tapPulse 1.4s ease-in-out infinite}
@keyframes tapPulse{0%,100%{opacity:.4}50%{opacity:1}}
/* Particle burst */
#chestParticles{position:fixed;inset:0;pointer-events:none;z-index:3999}
/* Rewards screen */
.cm-rewards{display:none;flex-direction:column;align-items:center;gap:20px;animation:rewardsFadeIn .5s ease}
@keyframes rewardsFadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.cm-reward-grid{display:flex;flex-direction:column;gap:12px;width:100%;max-width:340px}
.cm-reward-row{display:flex;align-items:center;gap:14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:14px 18px;animation:rewardSlide .4s ease backwards}
@keyframes rewardSlide{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:none}}
.cm-reward-icon{font-size:2rem;flex-shrink:0}
.cm-reward-name{font-size:.95rem;font-weight:700;text-align:left}
.cm-reward-type{font-size:.72rem;color:rgba(255,255,255,.45);text-align:left;text-transform:uppercase;letter-spacing:.08em}
.cm-got-btn{background:linear-gradient(135deg,var(--cc),var(--cc2));color:#000;font-family:'Outfit',sans-serif;font-size:1rem;font-weight:800;border:none;border-radius:14px;padding:15px 44px;cursor:pointer;transition:.2s;box-shadow:0 6px 24px rgba(0,0,0,.3)}
.cm-got-btn:hover{transform:translateY(-2px);box-shadow:0 10px 32px rgba(0,0,0,.4)}
@media(max-width:600px){.cm-chest-emoji{font-size:5rem}.cm-box{padding:36px 20px}}
</style>
<canvas id="chestParticles"></canvas>
<div id="chestModal">
    <div class="cm-bg" id="cmBg"></div>
    <div class="cm-box" id="cmBox">
        <!-- Phase 1: Tap to open -->
        <div id="cmPhase1">
            <div style="font-size:.82rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:16px">ඝ️ Вы получили награду</div>
            <div class="cm-chest-wrap" id="cmChestWrap" onclick="tapChest()">
                <span class="cm-chest-emoji" id="cmChestEmoji">📦</span>
                <span class="cm-chest-name" id="cmChestName">Common Chest</span>
            </div>
            <div class="cm-chest-tap" id="cmTapHint">👆 Нажми чтобы открыть</div>
        </div>
        <!-- Phase 2: Rewards -->
        <div class="cm-rewards" id="cmPhase2">
            <div style="font-size:1.1rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase" id="cmRewardsTitle">Contents</div>
            <div class="cm-reward-grid" id="cmRewardGrid"></div>
            <button class="cm-got-btn" onclick="closeChestModal()">✨ Забрать!</button>
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
let gameStartTime = Date.now();
let hadDoubleCapture = false;   // set true when player captures 2+ in one move
let hadKingCapture   = false;   // set true when player captures a king
let piecesLostCount  = 0;       // track own pieces lost during game

// Init board
const board = new BoardUI('gameBoard', {
    mode: MODE,
    difficulty: DIFFICULTY,
    playerSide: P1,   // Default; for online this is set after joining
    skin: USER_SKIN,
    timeControl: 300,
    hintsEnabled: true,
    soundEnabled: true,
    onMove: handleMove,
    onGameOver: handleGameOver,
});

// ======================================
// ONLINE GAME INIT
// ======================================
let myPlayerNum = 0;  // 1 or 2
let onlineSyncInterval = null;
let lastSyncMoveNum = 0;
let opponentJoined = false;

if (MODE === 'online' && ROOM_CODE) {
    initOnlineGame();
}

async function initOnlineGame() {
    showStatus('⏳ Подключаемся...');
    try {
        const res = await fetch('../api/join_game.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ room_code: ROOM_CODE })
        });
        const data = await res.json();
        if (!data.success) { showStatus('❌ Ошибка: ' + data.error); return; }

        myPlayerNum = data.player_num;
        GAME_ID = data.game_id;

        // Set which side I play
        board.options.playerSide = myPlayerNum === 1 ? P1 : P2;

        if (data.status === 'active') {
            opponentJoined = true;
            board.startTimer();
            showStatus(myPlayerNum === 1 ? 'Ваш ход ♟' : 'Ход соперника');
        } else {
            showStatus('⏳ Ждём соперника...');
        }

        // Start polling
        syncGameState();
        onlineSyncInterval = setInterval(syncGameState, 1500);

    } catch(e) {
        showStatus('❌ Ошибка подключения');
    }
}

async function syncGameState() {
    if (!ROOM_CODE || board.engine.gameOver) return;
    try {
        const res = await fetch(`../api/game_sync.php?room_code=${ROOM_CODE}&since=${lastSyncMoveNum}`);
        const data = await res.json();
        if (!data.success) return;

        // Check for abandoned game
        if (data.status === 'abandoned' || data.status === 'finished') {
            clearInterval(onlineSyncInterval);
            if (!board.engine.gameOver) {
                board.engine.gameOver = true;
                // If it's abandoned, the winner is usually current_turn or already set on server.
                // We'll just set winner to myPlayerNum if it was abandoned (assuming opponent left)
                board.engine.winner = myPlayerNum === 1 ? P1 : P2; 
                board.handleGameOver();
            }
            return;
        }

        // Opponent joined for first time
        if (data.player2_id && !opponentJoined) {
            opponentJoined = true;
            board.startTimer();
            showStatus(myPlayerNum === 1 ? 'Соперник подключился! Ваш ход ♟' : 'Подключено! Ход соперника');
            board.showToast('✅ Соперник вошёл в игру!');
        }

        // Apply new moves from opponent
        if (data.moves && data.moves.length > 0) {
            for (const item of data.moves) {
                // If this move is newer than our local history, apply it
                if (item.move_number > board.engine.moveHistory.length) {
                    board.engine.applyMove(item.move);
                    board.lastMove = item.move;
                    board.updateSidebars(item.move);
                }
                lastSyncMoveNum = Math.max(lastSyncMoveNum, item.move_number);
            }
            board.selected = null;
            board.possibleMoves = [];
            board.render();

            if (board.engine.gameOver) {
                clearInterval(onlineSyncInterval);
                board.handleGameOver();
                return;
            }
        }

        // Update status text
        if (opponentJoined && !board.engine.gameOver) {
            const isMyTurn = (data.current_turn === myPlayerNum);
            const statusEl = document.getElementById('gameStatus');
            const mStatusEl = document.getElementById('mobileStatus');
            const txt = isMyTurn ? 'Ваш ход ♟' : 'Ход соперника...';
            if (statusEl) statusEl.textContent = txt;
            if (mStatusEl) mStatusEl.textContent = txt;
        }

    } catch(e) { /* silent */ }
}

function showStatus(msg) {
    const el = document.getElementById('gameStatus');
    const m = document.getElementById('mobileStatus');
    if (el) el.textContent = msg;
    if (m) m.textContent = msg;
}

// Surrender logic
function surrenderGame() {
    if (confirm('Вы уверены, что хотите сдаться?')) {
        if (MODE === 'online' && ROOM_CODE) {
            navigator.sendBeacon('../api/surrender.php', JSON.stringify({ room_code: ROOM_CODE }));
        }
        board.engine.gameOver = true;
        board.engine.winner = myPlayerNum === 1 ? P2 : P1;
        board.handleGameOver();
    }
}

// Surrender on tab close (online mode only)
window.addEventListener('beforeunload', () => {
    if (MODE === 'online' && ROOM_CODE && !board.engine.gameOver && opponentJoined) {
        navigator.sendBeacon('../api/surrender.php', JSON.stringify({ room_code: ROOM_CODE }));
    }
});

// Patch renderTimers to also update mobile bar
const _origRenderTimers = board.renderTimers.bind(board);
board.renderTimers = function() {
    _origRenderTimers();
    const fmt = s => `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`;
    const mt1 = document.getElementById('mobile-timer-p1');
    const mt2 = document.getElementById('mobile-timer-p2');
    if (mt1) mt1.textContent = fmt(this.timers[P1]);
    if (mt2) mt2.textContent = fmt(this.timers[P2]);
    // Active panel highlight
    document.getElementById('mobile-panel-p1')?.classList.toggle('active-turn', this.engine.turn === P1);
    document.getElementById('mobile-panel-p2')?.classList.toggle('active-turn', this.engine.turn === P2);
};

function handleMove(move, engine) {
    // Update status (online status is managed by syncGameState)
    if (MODE !== 'online') {
        const txt = engine.gameOver ? '' :
            (engine.turn === P1 ? 'Ваш ход ♟' : (MODE === 'ai' ? 'ИИ думает...' : 'Ход соперника'));
        showStatus(txt);
    }

    // Online: send move to server
    if (MODE === 'online' && ROOM_CODE) {
        lastSyncMoveNum++; // Optimistic update to avoid re-applying our own move
        fetch('../api/online_move.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ room_code: ROOM_CODE, move })
        }).catch(() => {});
    }

    // AI/PvP: save move if logged in
    if (MODE !== 'online' && USER_ID && GAME_ID) {
        fetch('../api/save_move.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ game_id: GAME_ID, move })
        }).catch(() => {});
    }
}


function handleGameOver(engine) {
    const modal  = document.getElementById('gameOverModal');
    const trophy = document.getElementById('modalTrophy');
    const title  = document.getElementById('modalTitle');
    const sub    = document.getElementById('modalSub');

    const playerWon = engine.winner === P1;
    trophy.textContent = playerWon ? '🏆' : (engine.winner ? '😔' : '🤝');
    title.textContent  = playerWon ? 'Победа!' : (engine.winner ? 'Поражение' : 'Ничья');
    sub.textContent    = playerWon ? 'Великолепная игра!' : 'Не сдавайся — следующий раз повезёт!';

    // AI Coach (Pro feature)
    if (IS_PRO || MODE !== 'online') {
        const insights = engine.analyzeGame ? engine.analyzeGame() : [];
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

    // Save result + award XP + check achievements
    if (USER_ID) {
        const durationSec = Math.floor((Date.now() - gameStartTime) / 1000);
        fetch('../api/end_game.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                winner:              playerWon ? 1 : (engine.winner ? 0 : null),
                mode:                MODE,
                difficulty:          DIFFICULTY,
                game_id:             GAME_ID,
                duration_seconds:    durationSec,
                no_pieces_lost:      playerWon && (piecesLostCount === 0),
                had_double_capture:  hadDoubleCapture,
                had_king_capture:    hadKingCapture,
            })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            // XP feedback in modal
            if (data.xp_gained > 0) {
                const fb = document.getElementById('xpFeedback');
                let txt = `⚡ +${data.xp_gained} XP`;
                if (data.level_up) txt += ` · 🎉 Новый уровень: ${data.level_icon} ${data.level_name}!`;
                document.getElementById('xpFeedbackText').textContent = txt;
                fb.style.display = 'block';
            }

            // Achievement toasts (queue with delay)
            if (data.new_achievements && data.new_achievements.length > 0) {
                data.new_achievements.forEach((ach, i) => {
                    setTimeout(() => showAchToast(ach), 800 + i * 1200);
                });
            }

            // Chest earned button
            if (data.chest_earned) {
                pendingChest = data.chest_earned;
                const fb = document.getElementById('xpFeedback');
                const chestBtn = document.createElement('button');
                chestBtn.id = 'chestEarnedBtn';
                chestBtn.style.cssText = 'display:flex;align-items:center;gap:8px;width:100%;margin-top:12px;padding:13px 20px;background:linear-gradient(135deg,rgba(255,255,255,.08),rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.15);border-radius:12px;color:#fff;font-family:Outfit,sans-serif;font-size:.95rem;font-weight:700;cursor:pointer;transition:.2s;justify-content:center';
                chestBtn.style.setProperty('--cc', data.chest_earned.color);
                chestBtn.onmouseenter = () => chestBtn.style.borderColor = data.chest_earned.color;
                chestBtn.onmouseleave = () => chestBtn.style.borderColor = 'rgba(255,255,255,.15)';
                chestBtn.innerHTML = `${data.chest_earned.emoji} Открыть ${data.chest_earned.name}!`;
                chestBtn.onclick = () => { closeModal(); setTimeout(showChestModal, 300); };
                fb.parentNode.insertBefore(chestBtn, fb.nextSibling);
            }
        })
        .catch(() => {});
    }
}

function showAchToast(ach) {
    const container = document.getElementById('achToastContainer');
    const tierColors = {basic:'#10b981', advanced:'#f59e0b', hardcore:'#ef4444'};
    const tierNames  = {basic:'Базовое', advanced:'Продвинутое', hardcore:'Хардкор'};

    const toast = document.createElement('div');
    toast.className = 'ach-toast';
    toast.innerHTML = `
        <span class="ach-toast-icon">${ach.icon}</span>
        <div class="ach-toast-body">
            <div class="ach-toast-label" style="color:${tierColors[ach.tier]||'var(--accent)'}">🏆 Достижение разблокировано · ${tierNames[ach.tier]||''}</div>
            <div class="ach-toast-name">${ach.name}</div>
            <div class="ach-toast-desc">${ach.description}</div>
        </div>`;
    container.appendChild(toast);

    // Auto-dismiss after 5 s
    setTimeout(() => {
        toast.classList.add('out');
        setTimeout(() => toast.remove(), 350);
    }, 5000);
}

// ── Chest opening ──────────────────────────────────────────────────────────
let pendingChest = null;
let chestOpened  = false;

function showChestModal() {
    if (!pendingChest) return;
    chestOpened = false;
    const c = pendingChest;
    const box = document.getElementById('cmBox');
    box.style.setProperty('--cc',  c.color);
    box.style.setProperty('--cc2', c.color);
    document.getElementById('cmChestEmoji').textContent = c.emoji;
    document.getElementById('cmChestName').textContent  = c.name;
    document.getElementById('cmChestWrap').classList.remove('shaking','opening');
    document.getElementById('cmPhase1').style.display   = 'block';
    document.getElementById('cmPhase2').style.display   = 'none';
    document.getElementById('cmRewardGrid').innerHTML   = '';
    document.getElementById('cmTapHint').style.opacity  = '1';
    document.getElementById('chestModal').classList.add('visible');
}

function tapChest() {
    if (chestOpened || !pendingChest) return;
    chestOpened = true;
    const wrap = document.getElementById('cmChestWrap');
    document.getElementById('cmTapHint').style.opacity = '0';
    wrap.classList.add('shaking');
    setTimeout(() => {
        wrap.classList.remove('shaking');
        wrap.classList.add('opening');
        launchChestParticles(pendingChest.color);
        fetch('../api/open_chest.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ chest_id: pendingChest.id })
        })
        .then(r => r.json())
        .then(data => { if (data.success) showRewards(data); })
        .catch(() => { closeChestModal(); });
    }, 600);
}

function showRewards(data) {
    const typeLabels = { xp:'XP Бонус', skin:'Скин шашек', board_theme:'Тема доски', win_animation:'Анимация победы' };
    document.getElementById('cmRewardsTitle').textContent = data.chest_meta.name + ' — Содержимое';
    const grid = document.getElementById('cmRewardGrid');
    data.rewards.forEach((r, i) => {
        const row = document.createElement('div');
        row.className = 'cm-reward-row';
        row.style.animationDelay = (i * 0.13) + 's';
        row.innerHTML = `<span class="cm-reward-icon">${r.icon}</span>
            <div><div class="cm-reward-name">${r.name}</div>
            <div class="cm-reward-type">${typeLabels[r.type] || r.type}</div></div>`;
        grid.appendChild(row);
    });
    setTimeout(() => {
        document.getElementById('cmPhase1').style.display = 'none';
        document.getElementById('cmPhase2').style.display = 'flex';
    }, 800);
}

function closeChestModal() {
    document.getElementById('chestModal').classList.remove('visible');
    stopChestParticles();
    pendingChest = null;
}

// Chest particle burst
let chestParticleRAF = null;
function launchChestParticles(color) {
    const canvas = document.getElementById('chestParticles');
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    const ctx = canvas.getContext('2d');
    const cx  = canvas.width / 2, cy = canvas.height / 2;
    const palette = [color, '#ffffff', '#ffd700', '#fff8'];
    const parts = Array.from({length: 80}, () => {
        const angle = Math.random() * Math.PI * 2;
        const speed = Math.random() * 12 + 5;
        return { x:cx, y:cy, vx: Math.cos(angle)*speed, vy: Math.sin(angle)*speed - 4,
                 size: Math.random()*9+3, color: palette[Math.floor(Math.random()*palette.length)],
                 life: 1, decay: Math.random()*.015+.01, shape: Math.random()>.5?'rect':'circle' };
    });
    function draw() {
        ctx.clearRect(0,0,canvas.width,canvas.height);
        let alive = false;
        parts.forEach(p => {
            p.x += p.vx; p.y += p.vy; p.vy += .25; p.life -= p.decay;
            if (p.life <= 0) return;
            alive = true;
            ctx.save(); ctx.globalAlpha = p.life; ctx.fillStyle = p.color;
            ctx.translate(p.x, p.y);
            if (p.shape === 'rect') ctx.fillRect(-p.size/2,-p.size/4,p.size,p.size/2);
            else { ctx.beginPath(); ctx.arc(0,0,p.size/2,0,Math.PI*2); ctx.fill(); }
            ctx.restore();
        });
        if (alive) chestParticleRAF = requestAnimationFrame(draw);
    }
    stopChestParticles();
    draw();
}
function stopChestParticles() {
    if (chestParticleRAF) { cancelAnimationFrame(chestParticleRAF); chestParticleRAF = null; }
    const c = document.getElementById('chestParticles');
    if (c) c.getContext('2d').clearRect(0,0,c.width,c.height);
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

// ======================================
// CHAT (online mode only)
// ======================================
<?php if ($mode === 'online'): ?>
let chatLastTime = Math.floor(Date.now() / 1000) - 5;
let chatPolling = null;

function sendChat() {
    const input = document.getElementById('chatInput');
    if (!input) return;
    const msg = input.value.trim();
    if (!msg || !ROOM_CODE) return;

    input.value = '';
    input.disabled = true;

    fetch('../api/chat_send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_code: ROOM_CODE, message: msg })
    })
    .then(r => r.json())
    .then(d => {
        input.disabled = false;
        input.focus();
        if (!d.success) console.warn('Chat error:', d.error);
        else fetchChatMessages(); // Fetch immediately after send
    })
    .catch(() => {
        input.disabled = false;
        input.focus();
        appendChatMsg({ username: 'Система', message: '❌ Не удалось отправить', is_mine: false, error: true });
    });
}

function fetchChatMessages() {
    if (!ROOM_CODE) return;
    fetch(`../api/chat_get.php?room_code=${ROOM_CODE}&since=${chatLastTime}`)
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        if (d.messages && d.messages.length > 0) {
            d.messages.forEach(m => appendChatMsg(m));
            chatLastTime = d.server_time;
        }
    })
    .catch(() => {}); // Silent fail on network error
}

function appendChatMsg(msg) {
    const box = document.getElementById('chatMessages');
    if (!box) return;

    const el = document.createElement('div');
    el.style.cssText = `
        margin-bottom: 6px;
        display: flex;
        flex-direction: column;
        align-items: ${msg.is_mine ? 'flex-end' : 'flex-start'};
    `;

    const bubble = document.createElement('div');
    bubble.style.cssText = `
        background: ${msg.is_mine ? 'rgba(124,106,247,.25)' : 'rgba(255,255,255,.06)'};
        border: 1px solid ${msg.is_mine ? 'rgba(124,106,247,.4)' : 'rgba(255,255,255,.1)'};
        border-radius: ${msg.is_mine ? '12px 12px 2px 12px' : '12px 12px 12px 2px'};
        padding: 5px 10px;
        max-width: 90%;
        word-break: break-word;
        ${msg.error ? 'color:#ef4444;' : ''}
    `;

    if (!msg.is_mine) {
        const name = document.createElement('div');
        name.style.cssText = 'font-size:.68rem;color:var(--accent);font-weight:600;margin-bottom:2px';
        name.textContent = msg.username;
        bubble.appendChild(name);
    }

    const text = document.createElement('div');
    text.style.cssText = 'font-size:.82rem;color:var(--text)';
    text.textContent = msg.message;
    bubble.appendChild(text);
    el.appendChild(bubble);

    const time = document.createElement('div');
    time.style.cssText = 'font-size:.65rem;color:var(--text2);margin-top:2px;padding:0 2px';
    const d = msg.created_at ? new Date(msg.created_at.replace(' ', 'T')) : new Date();
    time.textContent = d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
    el.appendChild(time);

    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
}

// Enter to send
document.getElementById('chatInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChat();
    }
});

// Start polling every 2.5s
if (ROOM_CODE) {
    fetchChatMessages(); // Initial load
    chatPolling = setInterval(fetchChatMessages, 2500);

    // Stop polling when page is hidden
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(chatPolling);
        } else {
            fetchChatMessages();
            chatPolling = setInterval(fetchChatMessages, 2500);
        }
    });
}
<?php endif; ?>
</script>
</body>
</html>
