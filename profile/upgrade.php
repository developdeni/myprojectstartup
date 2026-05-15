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
    <title>Upgrade to Pro — CheckMasters</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="landing-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="bg-gradient-orb orb-2"></div><div class="chess-grid"></div></div>
<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="/" class="nav-logo"><span class="logo-icon">♛</span><span class="logo-text">Check<span class="accent">Masters</span></span></a>
        <div style="margin-left:auto"><a href="index.php" class="btn btn-ghost">← Профиль</a></div>
    </div>
</nav>
<section style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:100px 24px;position:relative;z-index:1">
    <div style="max-width:700px;width:100%;text-align:center">
        <div style="font-size:4rem;margin-bottom:16px">⚡</div>
        <h1 class="hero-title" style="margin-bottom:16px">Стань <span class="gradient-text">Pro игроком</span></h1>
        <p style="color:var(--text2);font-size:1.1rem;margin-bottom:48px">Разблокируй все возможности платформы и стань лучшим игроком</p>

        <?php if ($user['is_pro']): ?>
        <div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:var(--radius);padding:32px;margin-bottom:32px">
            <div style="font-size:2rem;margin-bottom:8px">🎉</div>
            <h2 style="color:var(--green)">Ты уже Pro!</h2>
            <p style="color:var(--text2)">Наслаждайся всеми возможностями CheckMasters Pro</p>
        </div>
        <?php else: ?>
        <div class="pricing-grid" style="max-width:600px;margin:0 auto 40px">
            <div class="pricing-card" style="text-align:left">
                <div class="pricing-badge">Ежемесячно</div>
                <div class="pricing-price">$4.99 <span>/месяц</span></div>
                <ul class="pricing-features">
                    <li>✅ AI Coach анализ партий</li>
                    <li>✅ Hard & Expert ИИ</li>
                    <li>✅ Все скины</li>
                    <li>✅ Приоритетный матчмейкинг</li>
                    <li>✅ ⚡ Pro значок</li>
                </ul>
                <button onclick="subscribe('monthly')" class="btn btn-hero" style="width:100%;justify-content:center">⚡ Начать за $4.99/мес</button>
            </div>
            <div class="pricing-card pricing-card--pro" style="text-align:left">
                <div class="pricing-badge badge-pro">🔥 Лучшее предложение</div>
                <div class="pricing-price">$39.99 <span>/год</span></div>
                <div style="color:var(--green);font-size:.85rem;margin-bottom:16px">Экономия $19.89 (33%)</div>
                <ul class="pricing-features">
                    <li>✅ Всё из ежемесячного</li>
                    <li>✅ 2 месяца бесплатно</li>
                    <li>✅ Ранний доступ к фичам</li>
                    <li>✅ Эксклюзивный скин "Diamond"</li>
                </ul>
                <button onclick="subscribe('yearly')" class="btn btn-hero" style="width:100%;justify-content:center">🚀 Оформить за $39.99/год</button>
            </div>
        </div>
        <p style="color:var(--text2);font-size:.85rem">🔒 Безопасная оплата через Stripe. Отмена в любое время.</p>
        <?php endif; ?>
    </div>
</section>
<script>
function subscribe(plan) {
    // In production: redirect to Stripe Checkout
    // For demo: activate Pro directly
    fetch('../api/activate_pro.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({plan})
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            alert('🎉 Pro активирован! (Demo mode)');
            window.location = 'index.php';
        }
    });
}
</script>
</body>
</html>
