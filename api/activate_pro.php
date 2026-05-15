<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();
// Demo: just activate Pro
Database::execute("UPDATE users SET is_pro=1 WHERE id=?", [$_SESSION['user_id']]);
jsonSuccess(['message' => 'Pro activated']);
