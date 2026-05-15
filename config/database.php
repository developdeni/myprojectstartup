<?php
// ============================================
// Database Configuration
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'broldru_aibektest');
define('DB_USER', 'broldru_aibektest');
define('DB_PASS', 'aibektest!');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CheckMasters');
define('APP_URL', 'https://studybridge.expres.kz/');
define('APP_VERSION', '1.0.0');

// Session settings
define('SESSION_LIFETIME', 86400 * 30); // 30 days

// Stripe (optional)
define('STRIPE_PUBLIC_KEY', '');
define('STRIPE_SECRET_KEY', '');

// JWT Secret for WebSocket auth
define('JWT_SECRET', 'checkmasters_secret_key_change_in_production_2026');

// AI difficulty ELO thresholds
define('AI_EASY_DEPTH', 2);
define('AI_MEDIUM_DEPTH', 4);
define('AI_HARD_DEPTH', 6);
define('AI_EXPERT_DEPTH', 8);

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): array {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function queryOne(string $sql, array $params = []): ?array {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function execute(string $sql, array $params = []): int {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return (int)self::getInstance()->lastInsertId();
    }
}
