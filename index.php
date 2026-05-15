<?php
require_once __DIR__ . '/config/app.php';
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="CheckMasters — современная платформа для игры в шашки с мультиплеером, ИИ-тренером и глобальным лидербордом">
    <title>CheckMasters — Шашки нового уровня</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
</head>

<body class="landing-page">

    <!-- Animated background -->
    <div class="bg-canvas">
        <div class="bg-gradient-orb orb-1"></div>
        <div class="bg-gradient-orb orb-2"></div>
        <div class="bg-gradient-orb orb-3"></div>
        <div class="chess-grid"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="/" class="nav-logo">
                <span class="logo-icon">♛</span>
                <span class="logo-text">Check<span class="accent">Masters</span></span>
            </a>
            <ul class="nav-links">
                <li><a href="#features">Возможности</a></li>
                <li><a href="#leaderboard">Рейтинг</a></li>
                <li><a href="#pricing">Pro</a></li>
            </ul>
            <div class="nav-actions">
                <?php if ($user): ?>
                    <a href="game/lobby.php" class="btn btn-ghost">Лобби</a>
                    <a href="profile/index.php" class="btn btn-primary">
                        <span class="avatar-sm"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                        <?= htmlspecialchars($user['username']) ?>
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-ghost">Войти</a>
                    <a href="auth/register.php" class="btn btn-primary">Играть бесплатно</a>
                <?php endif; ?>
            </div>
            <button class="nav-burger" id="navBurger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="badge-dot"></span>
                <span>🔥 Уже играют 1,200+ игроков</span>
            </div>
            <h1 class="hero-title">
                Шашки, которые<br>
                <span class="gradient-text">заставят думать</span>
            </h1>
            <p class="hero-subtitle">
                Сражайся с ИИ-тренером, играй с друзьями онлайн, анализируй свои партии
                и покоряй мировой рейтинг. Это не просто шашки — это стратегия.
            </p>
            <div class="hero-actions">
                <a href="<?= $user ? 'game/lobby.php' : 'auth/register.php' ?>" class="btn btn-hero">
                    <span>⚡</span> Начать игру
                </a>
                <a href="game/play.php?mode=ai&difficulty=easy" class="btn btn-hero-outline">
                    <span>🤖</span> Vs ИИ (без регистрации)
                </a>
            </div>
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number" data-target="12847">0</span>
                    <span class="stat-label">Партий сыграно</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number" data-target="1247">0</span>
                    <span class="stat-label">Игроков онлайн</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number" data-target="48">0</span>
                    <span class="stat-label">Стран</span>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="board-preview">
                <div class="board-glow"></div>
                <div class="mini-board" id="demoBoardHero"></div>
                <div class="board-overlay">
                    <div class="overlay-tag">AI анализирует ход...</div>
                    <div class="ai-thinking">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Всё что нужно<br><span class="gradient-text">настоящему игроку</span></h2>
            </div>
            <div class="features-grid">
                <div class="feature-card feature-card--large">
                    <div class="feature-icon">🤖</div>
                    <h3>AI Coach</h3>
                    <p>После каждой партии ИИ анализирует ваши ходы, указывает на ошибки и показывает лучшие варианты.
                        Учитесь на каждом матче.</p>
                    <div class="feature-preview coach-preview">
                        <div class="coach-message">
                            <span class="coach-icon">♟</span>
                            <span>Ход 14: Вы упустили двойное взятие! Лучше было c3→e5</span>
                        </div>
                        <div class="coach-message">
                            <span class="coach-icon">⚠️</span>
                            <span>Ход 22: Этот ход открыл вашу дамку под атаку</span>
                        </div>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🌐</div>
                    <h3>Мультиплеер</h3>
                    <p>Создай комнату и отправь другу ссылку. Играйте в реальном времени через WebSockets.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🏆</div>
                    <h3>Лидерборд</h3>
                    <p>Глобальный рейтинг ELO. Топ по городам, странам. Соревнуйся с игроками из своего города.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>4 уровня ИИ</h3>
                    <p>От новичка до эксперта. Алгоритм Minimax с alpha-beta pruning не даст вам расслабиться.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎨</div>
                    <h3>Скины & Темы</h3>
                    <p>Neon Cyber, Fire & Ice, Galaxy — персонализируй доску и фигуры под свой стиль.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Leaderboard -->
    <section class="leaderboard-section" id="leaderboard">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Топ игроков <span class="gradient-text">сегодня</span></h2>
            </div>
            <div class="leaderboard-widget">
                <div class="lb-header">
                    <button class="lb-tab active" data-tab="global">🌍 Глобальный</button>
                    <button class="lb-tab" data-tab="local">📍 Ваш город</button>
                </div>
                <div class="lb-body" id="lbGlobal">
                    <?php
                    $topPlayers = Database::query(
                        "SELECT username, city, rating, wins, total_games FROM users ORDER BY rating DESC LIMIT 10"
                    );
                    foreach ($topPlayers as $i => $p):
                        $rank = $i + 1;
                        $medal = match ($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => "#$rank"};
                        $winrate = $p['total_games'] > 0 ? round($p['wins'] / $p['total_games'] * 100) : 0;
                        ?>
                        <div class="lb-row <?= $rank <= 3 ? 'lb-row--top' : '' ?>">
                            <span class="lb-rank"><?= $medal ?></span>
                            <span class="lb-avatar"><?= strtoupper(substr($p['username'], 0, 1)) ?></span>
                            <span class="lb-name"><?= htmlspecialchars($p['username']) ?></span>
                            <span class="lb-city"><?= htmlspecialchars($p['city'] ?? '—') ?></span>
                            <span class="lb-rating"><?= $p['rating'] ?></span>
                            <span class="lb-winrate"><?= $winrate ?>%</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($topPlayers)): ?>
                        <div class="lb-empty">Стань первым! Зарегистрируйся и сыграй партию 🏆</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section class="pricing-section" id="pricing">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Выбери свой <span class="gradient-text">уровень</span></h2>
            </div>
            <div class="pricing-grid">
                <div class="pricing-card">
                    <div class="pricing-badge">Бесплатно</div>
                    <div class="pricing-price">$0 <span>/месяц</span></div>
                    <ul class="pricing-features">
                        <li>✅ Неограниченные игры</li>
                        <li>✅ Игра против ИИ (Easy/Medium)</li>
                        <li>✅ Мультиплеер с другом</li>
                        <li>✅ История 20 партий</li>
                        <li>✅ Классический скин</li>
                        <li>❌ AI Coach анализ</li>
                        <li>❌ Скин Neon, Galaxy</li>
                        <li>❌ Hard/Expert ИИ</li>
                    </ul>
                    <a href="auth/register.php" class="btn btn-outline-full">Начать бесплатно</a>
                </div>
                <div class="pricing-card pricing-card--pro">
                    <div class="pricing-badge badge-pro">⚡ Pro</div>
                    <div class="pricing-price">$4.99 <span>/месяц</span></div>
                    <ul class="pricing-features">
                        <li>✅ Всё из Free</li>
                        <li>✅ AI Coach после каждой партии</li>
                        <li>✅ ИИ Hard & Expert</li>
                        <li>✅ Все скины включены</li>
                        <li>✅ Неограниченная история</li>
                        <li>✅ Аналитика прогресса</li>
                        <li>✅ Приоритетный матчмейкинг</li>
                        <li>✅ Pro значок в профиле</li>
                    </ul>
                    <a href="profile/upgrade.php" class="btn btn-hero">Upgrade to Pro ⚡</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-box">
                <h2>Готов стать мастером шашек?</h2>
                <p>Присоединяйся к тысячам игроков. Регистрация занимает 30 секунд.</p>
                <a href="auth/register.php" class="btn btn-hero">🚀 Играть прямо сейчас</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <span class="logo-icon">♛</span>
                    <span class="logo-text">Check<span class="accent">Masters</span></span>
                    <p>Шашки нового поколения</p>
                </div>
                <div class="footer-links">
                    <a href="game/play.php?mode=ai">Играть с ИИ</a>
                    <a href="game/lobby.php">Лобби</a>
                    <a href="leaderboard.php">Лидерборд</a>
                    <a href="profile/upgrade.php">Pro</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p></p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>

</html>