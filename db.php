<?php

require_once __DIR__ . '/config.php';

function getDb(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("
        CREATE TABLE IF NOT EXISTS numbers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100),
            session_id VARCHAR(100),
            token VARCHAR(200),
            active INTEGER DEFAULT 1,
            paused_until DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS message_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            from_phone VARCHAR(20),
            to_phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            direction VARCHAR(10) NOT NULL,
            status VARCHAR(20) DEFAULT 'sent',
            ref_id VARCHAR(50),
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS settings (
            key VARCHAR(50) PRIMARY KEY,
            value TEXT
        );
    ");

    $stmt = $db->prepare("
        INSERT OR IGNORE INTO settings (key, value)
        VALUES (?, ?)
    ");

    $stmt->execute(['wa_gateway_url', WA_GATEWAY_URL_DEFAULT]);
    $stmt->execute(['wa_gateway_key', WA_GATEWAY_KEY_DEFAULT]);

    // ===== MIGRATION CHECK =====

    $cols = array_column(
        $db->query("PRAGMA table_info(numbers)")->fetchAll(PDO::FETCH_ASSOC),
        'name'
    );

    if (!in_array('token', $cols)) {
        $db->exec("ALTER TABLE numbers ADD COLUMN token VARCHAR(200)");
    }

    if (!in_array('paused_until', $cols)) {
        $db->exec("ALTER TABLE numbers ADD COLUMN paused_until DATETIME");
    }

    // ===== DATABASE INDEX OPTIMIZATION =====

    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_message_log_sent_at
        ON message_log(sent_at);

        CREATE INDEX IF NOT EXISTS idx_message_log_from_phone
        ON message_log(from_phone);

        CREATE INDEX IF NOT EXISTS idx_message_log_status
        ON message_log(status);

        CREATE INDEX IF NOT EXISTS idx_numbers_active
        ON numbers(active);
    ");

    return $db;
}

function getActiveNumbers(): array {

    $db = getDb();

    return $db->query("
        SELECT *
        FROM numbers
        WHERE active = 1
        AND (
            paused_until IS NULL
            OR paused_until < datetime('now')
        )
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function getSetting(string $key, string $default = ''): string {

    $db = getDb();

    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");

    $stmt->execute([$key]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? $row['value'] : $default;
}

function setSetting(string $key, string $value): void {

    $db = getDb();

    $stmt = $db->prepare("
        INSERT OR REPLACE INTO settings (key,value)
        VALUES (?,?)
    ");

    $stmt->execute([$key,$value]);
}

function getGatewayConfig(): array {
    return [
        'url' => rtrim(getSetting('wa_gateway_url', WA_GATEWAY_URL_DEFAULT), '/'),
        'key' => getSetting('wa_gateway_key', WA_GATEWAY_KEY_DEFAULT),
    ];
}

function logMessage(
    string $fromPhone,
    string $toPhone,
    string $message,
    string $direction,
    string $status,
    string $refId = ''
): void {

    $db = getDb();

    $stmt = $db->prepare("
        INSERT INTO message_log
        (from_phone,to_phone,message,direction,status,ref_id)
        VALUES (?,?,?,?,?,?)
    ");

    $stmt->execute([
        $fromPhone,
        $toPhone,
        $message,
        $direction,
        $status,
        $refId
    ]);
}

function getRecentLogs(int $limit = 50): array {

    $db = getDb();

    return $db->query("
        SELECT *
        FROM message_log
        ORDER BY sent_at DESC
        LIMIT $limit
    ")->fetchAll(PDO::FETCH_ASSOC);
}
