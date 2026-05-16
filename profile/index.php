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

$winRate      = $user['total_games'] > 0 ? round($user['wins'] / $user['total_games'] * 100) : 0;
$totalXp      = (int)($user['xp'] ?? 0);
$lvlInfo      = calcLevelFromXp($totalXp);
$lvlColor     = $lvlInfo['color'];
$xpPct        = round($lvlInfo['progress'] * 100);
$achievements = getAllAchievementsForUser($user['id']);
$tierLabel    = ['basic' => '⬡ Базовые', 'advanced' => '◈ Продвинутые', 'hardcore' => '☠ Хардкор'];
$tierGroups   = [];
foreach ($achievements as $a) {
    $tierGroups[$a['tier']][] = $a;
}
$unlockedCount = count(array_filter($achievements, fn($a) => $a['unlocked']));
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
    <style>
    /* ── XP / Level ──────────────────────────────────────── */
    .level-widget{background:linear-gradient(135deg,rgba(255,255,255,.04),rgba(255,255,255,.01));border:1px solid var(--border);border-radius:16px;padding:20px 24px;margin:0 24px 24px}
    .level-header{display:flex;align-items:center;gap:14px;margin-bottom:14px}
    .level-badge{display:flex;align-items:center;gap:8px;padding:6px 16px;border-radius:30px;font-weight:700;font-size:.9rem;letter-spacing:.02em;border:2px solid var(--lvl-color,#cd7f32);color:var(--lvl-color,#cd7f32);background:rgba(205,127,50,.08);white-space:nowrap}
    .level-xp-info{flex:1}
    .level-xp-label{font-size:.78rem;color:var(--text2);margin-bottom:4px;display:flex;justify-content:space-between}
    .level-xp-label .xp-total{color:var(--text);font-weight:600}
    .xp-bar-track{height:10px;background:var(--border2);border-radius:99px;overflow:hidden}
    .xp-bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--lvl-color,#cd7f32),var(--lvl-color2,#e8c07d));transition:width 1.2s cubic-bezier(.22,1,.36,1);position:relative}
    .xp-bar-fill::after{content:'';position:absolute;top:0;right:0;bottom:0;width:30px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.3));border-radius:99px;animation:xp-shine 2s ease-in-out infinite}
    @keyframes xp-shine{0%,100%{opacity:0}50%{opacity:1}}
    .level-next-label{font-size:.75rem;color:var(--text2);margin-top:6px;text-align:right}

    /* ── Achievements ────────────────────────────────────── */
    .achievements-section{padding:24px}
    .ach-section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
    .ach-section-head h3{font-size:1rem;color:var(--text2)}
    .ach-counter{font-size:.85rem;color:var(--accent);font-weight:700;background:rgba(124,106,247,.12);padding:4px 12px;border-radius:20px}
    .ach-tier{margin-bottom:28px}
    .ach-tier-label{font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text2);margin-bottom:12px;display:flex;align-items:center;gap:8px}
    .ach-tier-label::after{content:'';flex:1;height:1px;background:var(--border)}
    .ach-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
    .ach-card{background:var(--bg3);border:1.5px solid var(--border);border-radius:14px;padding:16px;cursor:pointer;transition:all .25s;position:relative;overflow:hidden}
    .ach-card.unlocked{border-color:rgba(124,106,247,.5);background:linear-gradient(135deg,rgba(124,106,247,.08),rgba(34,211,238,.04))}
    .ach-card.unlocked:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(124,106,247,.2);border-color:var(--accent)}
    .ach-card.locked{opacity:.45;filter:grayscale(1)}
    .ach-card.locked:hover{opacity:.6}
    .ach-card-inner{display:flex;align-items:flex-start;gap:12px}
    .ach-icon{font-size:2rem;flex-shrink:0;line-height:1}
    .ach-body{}
    .ach-name{font-weight:700;font-size:.9rem;margin-bottom:3px}
    .ach-desc{font-size:.78rem;color:var(--text2);line-height:1.4}
    .ach-date{font-size:.7rem;color:var(--accent);margin-top:6px;font-weight:600}
    .ach-card.unlocked::before{content:'✓';position:absolute;top:10px;right:12px;font-size:.75rem;font-weight:800;color:var(--accent);background:rgba(124,106,247,.15);border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center}
    .ach-locked-icon{position:absolute;top:10px;right:12px;font-size:.85rem;opacity:.5}

    /* Tier colors */
    .tier-basic .ach-card.unlocked   { border-color:rgba(16,185,129,.4);background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(16,185,129,.02)) }
    .tier-advanced .ach-card.unlocked { border-color:rgba(245,158,11,.4);background:linear-gradient(135deg,rgba(245,158,11,.08),rgba(245,158,11,.02)) }
    .tier-hardcore .ach-card.unlocked { border-color:rgba(239,68,68,.4);background:linear-gradient(135deg,rgba(239,68,68,.08),rgba(239,68,68,.02)) }

    /* ── Achievement pop-up modal ─────────────────────────── */
    #achModal{position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(12px);z-index:2000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:.3s}
    #achModal.visible{opacity:1;pointer-events:all}
    .ach-modal-box{background:var(--bg2);border:1px solid var(--border);border-radius:24px;padding:40px 36px;text-align:center;max-width:380px;width:90%;transform:scale(.85) translateY(20px);transition:.35s cubic-bezier(.22,1,.36,1);position:relative}
    #achModal.visible .ach-modal-box{transform:scale(1) translateY(0)}
    .ach-modal-glow{position:absolute;inset:-1px;border-radius:24px;background:linear-gradient(135deg,var(--accent),var(--cyan));z-index:-1;opacity:.25;filter:blur(12px)}
    .ach-modal-icon{font-size:4.5rem;margin-bottom:16px;display:block}
    .ach-modal-badge{display:inline-block;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:14px}
    .badge-basic    {background:rgba(16,185,129,.15);color:var(--green)}
    .badge-advanced {background:rgba(245,158,11,.15);color:var(--gold)}
    .badge-hardcore {background:rgba(239,68,68,.15);color:var(--red)}
    .ach-modal-title{font-size:1.5rem;font-weight:800;margin-bottom:8px}
    .ach-modal-desc{color:var(--text2);font-size:.9rem;line-height:1.5;margin-bottom:24px}
    .ach-modal-close{background:var(--accent);color:#fff;border:none;border-radius:12px;padding:12px 32px;font-family:'Outfit',sans-serif;font-size:.95rem;font-weight:700;cursor:pointer;transition:.2s}
    .ach-modal-close:hover{background:#6b5ce7}

    /* ── Mobile Adaptation ── */
    @media (max-width: 768px) {
        .profile-info { flex-direction: column; align-items: center; text-align: center; margin-top: -40px; }
        .profile-info > .btn-hero { margin-left: 0 !important; margin-top: 16px; width: 100%; justify-content: center; }
        .profile-stats { grid-template-columns: repeat(2, 1fr) !important; gap: 10px; }
        .profile-stats .pstat:nth-child(5) { grid-column: span 2; }
        .ach-grid { grid-template-columns: 1fr; }
        .level-widget { margin: 0 16px 16px; padding: 16px; }
        .level-header { flex-direction: column; text-align: center; }
        .level-xp-label, .level-next-label { justify-content: center; text-align: center; gap: 8px; }
        .achievements-section { padding: 16px; }
        .ach-section-head { flex-direction: column; gap: 12px; align-items: flex-start; }
    }
    </style>
</head>
<body class="landing-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="chess-grid"></div></div>

<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="/" class="nav-logo"><span class="logo-icon">♛</span><span class="logo-text">Check<span class="accent">Masters</span></span></a>
        <div style="margin-left:auto;display:flex;gap:12px;align-items:center">
            <span style="font-size:.82rem;color:<?= $lvlColor ?>;font-weight:700;border:1.5px solid <?= $lvlColor ?>;padding:4px 10px;border-radius:20px">
                <?= $lvlInfo['icon'] ?> <?= $lvlInfo['name'] ?>
            </span>
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

    <!-- ── XP / Level Widget ── -->
    <div class="level-widget" style="--lvl-color:<?= $lvlColor ?>;--lvl-color2:<?= $lvlInfo['level'] < 5 ? getLevelColor($lvlInfo['level']+1) : $lvlColor ?>">
        <div class="level-header">
            <div class="level-badge"><?= $lvlInfo['icon'] ?> <?= $lvlInfo['name'] ?></div>
            <div class="level-xp-info">
                <div class="level-xp-label">
                    <span>Прогресс уровня</span>
                    <span class="xp-total"><?= number_format($totalXp) ?> XP</span>
                </div>
                <div class="xp-bar-track">
                    <div class="xp-bar-fill" id="xpBarFill" style="width:0%"></div>
                </div>
                <?php if (!$lvlInfo['is_max']): ?>
                <div class="level-next-label">
                    <?= number_format($totalXp - $lvlInfo['xp_current']) ?> / <?= number_format($lvlInfo['xp_next'] - $lvlInfo['xp_current']) ?> XP
                    до <?= getLevelIcon($lvlInfo['level']+1) ?> <?= getLevelName($lvlInfo['level']+1) ?>
                </div>
                <?php else: ?>
                <div class="level-next-label" style="color:var(--lvl-color);font-weight:700">👑 Максимальный уровень!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Stats ── -->
    <div class="profile-stats" style="grid-template-columns:repeat(5,1fr)">
        <div class="pstat"><div class="pstat-num" style="color:var(--accent)"><?= $user['rating'] ?></div><div class="pstat-label">Рейтинг ELO</div></div>
        <div class="pstat"><div class="pstat-num" style="color:var(--green)"><?= $user['wins'] ?></div><div class="pstat-label">Победы</div></div>
        <div class="pstat"><div class="pstat-num"><?= $user['total_games'] ?></div><div class="pstat-label">Всего игр</div></div>
        <div class="pstat"><div class="pstat-num" style="color:var(--gold)"><?= $winRate ?>%</div><div class="pstat-label">Winrate</div></div>
        <div class="pstat"><div class="pstat-num" style="color:<?= $lvlColor ?>"><?= $totalXp ?></div><div class="pstat-label">Всего XP</div></div>
    </div>

    <!-- ── Achievements Section ── -->
    <div class="achievements-section">
        <div class="ach-section-head">
            <h3>🏆 ДОСТИЖЕНИЯ</h3>
            <span class="ach-counter"><?= $unlockedCount ?> / <?= count($achievements) ?></span>
        </div>

        <?php foreach (['basic','advanced','hardcore'] as $tier):
            if (empty($tierGroups[$tier])) continue;
        ?>
        <div class="ach-tier tier-<?= $tier ?>">
            <div class="ach-tier-label"><?= $tierLabel[$tier] ?? $tier ?></div>
            <div class="ach-grid">
            <?php foreach ($tierGroups[$tier] as $ach): ?>
                <div class="ach-card <?= $ach['unlocked'] ? 'unlocked' : 'locked' ?>"
                     onclick="showAchModal(<?= htmlspecialchars(json_encode([
                         'icon'        => $ach['icon'],
                         'name'        => $ach['name'],
                         'description' => $ach['description'],
                         'tier'        => $ach['tier'],
                         'unlocked'    => (bool)$ach['unlocked'],
                         'unlocked_at' => $ach['unlocked_at'] ?? null,
                     ]), ENT_QUOTES) ?>)">
                    <div class="ach-card-inner">
                        <span class="ach-icon"><?= $ach['icon'] ?></span>
                        <div class="ach-body">
                            <div class="ach-name"><?= htmlspecialchars($ach['name']) ?></div>
                            <div class="ach-desc"><?= htmlspecialchars($ach['description']) ?></div>
                            <?php if ($ach['unlocked'] && $ach['unlocked_at']): ?>
                            <div class="ach-date">✓ <?= date('d.m.Y', strtotime($ach['unlocked_at'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($ach['unlocked']): ?>
                    <span style="position:absolute;top:10px;right:12px;font-size:.72rem;font-weight:800;color:var(--accent)">✓</span>
                    <?php else: ?>
                    <span class="ach-locked-icon">🔒</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Game History ── -->
    <div style="padding:0 24px 24px">
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

<!-- ── Achievement Detail Modal ── -->
<div id="achModal" onclick="if(event.target===this)closeAchModal()">
    <div class="ach-modal-box">
        <div class="ach-modal-glow"></div>
        <span class="ach-modal-icon" id="achModalIcon">🏆</span>
        <span class="ach-modal-badge" id="achModalBadge">Базовое</span>
        <div class="ach-modal-title" id="achModalTitle">Achievement</div>
        <div class="ach-modal-desc"  id="achModalDesc">Description</div>
        <div class="ach-modal-date"  id="achModalDate"></div>
        <button class="ach-modal-close" onclick="closeAchModal()">Закрыть</button>
    </div>
</div>

<script>
// Animate XP bar
document.addEventListener('DOMContentLoaded', () => {
    const fill = document.getElementById('xpBarFill');
    if (fill) requestAnimationFrame(() => { fill.style.width = '<?= $xpPct ?>%'; });
});

// Achievement modal
const tierNames = {basic:'⬡ Базовое', advanced:'◈ Продвинутое', hardcore:'☠ Хардкор'};
const tierClasses = {basic:'badge-basic', advanced:'badge-advanced', hardcore:'badge-hardcore'};

function showAchModal(ach) {
    document.getElementById('achModalIcon').textContent  = ach.icon;
    document.getElementById('achModalTitle').textContent = ach.name;
    document.getElementById('achModalDesc').textContent  = ach.description;

    const badge = document.getElementById('achModalBadge');
    badge.textContent = tierNames[ach.tier] || ach.tier;
    badge.className   = 'ach-modal-badge ' + (tierClasses[ach.tier] || '');

    const dateEl = document.getElementById('achModalDate');
    if (ach.unlocked && ach.unlocked_at) {
        const d = new Date(ach.unlocked_at.replace(' ','T'));
        dateEl.textContent = '🎉 Получено: ' + d.toLocaleDateString('ru-RU', {day:'2-digit',month:'long',year:'numeric'});
    } else if (!ach.unlocked) {
        dateEl.textContent = '🔒 Ещё не получено';
    } else {
        dateEl.textContent = '';
    }

    document.getElementById('achModal').classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function closeAchModal() {
    document.getElementById('achModal').classList.remove('visible');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeAchModal();
});
</script>
</body>
</html>
