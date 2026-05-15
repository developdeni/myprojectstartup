<?php
require_once __DIR__ . '/../config/app.php';
if (isLoggedIn()) redirect('/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) { $error = 'Ошибка безопасности'; }
    else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $city     = trim($_POST['city'] ?? '');
        $country  = trim($_POST['country'] ?? '');

        if (strlen($username) < 3 || strlen($username) > 30) $error = 'Имя: 3-30 символов';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Некорректный email';
        elseif (strlen($password) < 6) $error = 'Пароль: минимум 6 символов';
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $error = 'Username: только буквы, цифры, _';
        else {
            $exists = Database::queryOne("SELECT id FROM users WHERE email=? OR username=?", [$email, $username]);
            if ($exists) {
                $error = 'Email или username уже занят';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $uid = Database::execute(
                    "INSERT INTO users (username,email,password_hash,city,country) VALUES (?,?,?,?,?)",
                    [$username, $email, $hash, $city ?: null, $country ?: null]
                );
                $_SESSION['user_id'] = $uid;
                $_SESSION['username'] = $username;
                redirect('/game/lobby.php');
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
    <title>Регистрация — CheckMasters</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">
<div class="bg-canvas"><div class="bg-gradient-orb orb-1"></div><div class="bg-gradient-orb orb-2"></div><div class="chess-grid"></div></div>
<div class="auth-container">
    <div class="auth-card">
        <a href="/" class="auth-logo"><span class="logo-icon">♛</span> Check<span class="accent">Masters</span></a>
        <h1>Создай аккаунт</h1>
        <p class="auth-sub">Присоединяйся к сообществу мастеров шашек</p>
        <?php if ($error): ?><div class="auth-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-input" placeholder="pro_player" required maxlength="30" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-input" placeholder="you@mail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Пароль *</label>
                <div class="input-wrap">
                    <input type="password" name="password" id="pwdInput" class="form-input" placeholder="Минимум 6 символов" required>
                    <button type="button" class="pwd-toggle" onclick="togglePwd()">👁</button>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Страна</label>
                    <input type="text" name="country" class="form-input" placeholder="Казахстан" value="<?= htmlspecialchars($_POST['country'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Город</label>
                    <input type="text" name="city" class="form-input" placeholder="Алматы" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-hero" style="width:100%;justify-content:center">🚀 Создать аккаунт</button>
        </form>
        <p class="auth-switch">Уже есть аккаунт? <a href="login.php">Войти</a></p>
    </div>
</div>
<script>function togglePwd(){const i=document.getElementById('pwdInput');i.type=i.type==='password'?'text':'password'}</script>
</body>
</html>
