<?php
require_once __DIR__ . '/../config/app.php';
if (isLoggedIn()) redirect('/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) { $error = 'Ошибка безопасности'; }
    else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($login) || empty($password)) {
            $error = 'Заполните все поля';
        } else {
            $user = Database::queryOne(
                "SELECT * FROM users WHERE email = ? OR username = ?", [$login, $login]
            );
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                redirect('/game/lobby.php');
            } else {
                $error = 'Неверный логин или пароль';
            }
        }
    }
}
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Войти — CheckMasters</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="bg-gradient-orb orb-2"></div><div class="chess-grid"></div></div>
<div class="auth-container">
    <div class="auth-card">
        <a href="/" class="auth-logo"><span class="logo-icon">♛</span> Check<span class="accent">Masters</span></a>
        <h1>С возвращением</h1>
        <p class="auth-sub">Войди и продолжи своё восхождение</p>
        <?php if ($error): ?><div class="auth-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" class="auth-form" id="loginForm">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <div class="form-group">
                <label>Email или Username</label>
                <input type="text" name="login" class="form-input" placeholder="pro_player" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <div class="input-wrap">
                    <input type="password" name="password" id="pwdInput" class="form-input" placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="pwd-toggle" onclick="togglePwd()">👁</button>
                </div>
            </div>
            <button type="submit" class="btn btn-hero" style="width:100%;justify-content:center">Войти</button>
        </form>
        <p class="auth-switch">Нет аккаунта? <a href="register.php">Зарегистрируйся</a></p>
        <div class="auth-divider"><span>или</span></div>
        <a href="../game/play.php?mode=ai" class="btn btn-hero-outline" style="width:100%;justify-content:center">🤖 Играть без регистрации</a>
    </div>
</div>
<script>function togglePwd(){const i=document.getElementById('pwdInput');i.type=i.type==='password'?'text':'password'}</script>
</body>
</html>
