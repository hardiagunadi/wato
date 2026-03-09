<?php

session_start();

define('AUTH_USER', 'wato');
define('AUTH_PASS', 'wato');

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (($_POST['username'] ?? '') === AUTH_USER && ($_POST['password'] ?? '') === AUTH_PASS) {
        $_SESSION['logged_in'] = true;
    } else {
        $loginError = 'Username atau password salah';
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: /');
    exit;
}

if (empty($_SESSION['logged_in'])) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WATO Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        :root {
            --brand-bg: linear-gradient(135deg, #eef6ff 0%, #f8fbff 45%, #f4fff8 100%);
            --brand-primary: #0f62fe;
            --brand-primary-hover: #004dd9;
            --brand-text: #17233a;
            --brand-muted: #6a7891;
            --card-shadow: 0 20px 50px rgba(12, 44, 84, 0.12);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--brand-bg);
        }

        .auth-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .auth-card {
            width: 100%;
            max-width: 430px;
            border: 0;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .auth-header {
            background: #fff;
            padding: 28px 28px 16px;
            text-align: center;
        }

        .auth-title {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--brand-text);
            margin: 0;
        }

        .auth-subtitle {
            margin: 8px 0 0;
            color: var(--brand-muted);
            font-size: 0.95rem;
        }

        .auth-body {
            background: #fff;
            padding: 14px 28px 30px;
        }

        .auth-body .form-control {
            border-radius: 12px;
            height: 46px;
            border-color: #d4deee;
        }

        .auth-body .btn {
            border-radius: 12px;
            height: 46px;
            font-weight: 700;
            background: var(--brand-primary);
            border-color: var(--brand-primary);
        }

        .auth-body .btn:hover {
            background: var(--brand-primary-hover);
            border-color: var(--brand-primary-hover);
        }
    </style>
</head>
<body>
<div class="auth-shell">
    <div class="card auth-card">
        <div class="auth-header">
            <h1 class="auth-title">WATO Dashboard</h1>
            <p class="auth-subtitle">Masuk untuk mengelola nomor dan aktivitas pengiriman.</p>
        </div>
        <div class="auth-body">
            <?php if (!empty($loginError)) { ?>
                <div class="alert alert-danger"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php } ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" class="form-control" placeholder="Masukkan username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Login</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

define('CRON_FILE', '/etc/cron.d/wato');
define('MANUAL_SEND_LOG_DIR', __DIR__ . '/tmp/manual-send');

function isCronInstalled(): bool {
    return file_exists(CRON_FILE);
}

function ensureManualSendLogDir(): void {
    if (!is_dir(MANUAL_SEND_LOG_DIR)) {
        mkdir(MANUAL_SEND_LOG_DIR, 0775, true);
    }
}

function buildManualSendLogPath(string $jobId): string {
    return MANUAL_SEND_LOG_DIR . '/' . $jobId . '.log';
}

function isProcessRunning(int $pid): bool {
    if ($pid <= 0) {
        return false;
    }

    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }

    return is_dir('/proc/' . $pid);
}

function resolvePhpCliBinary(): string {
    $candidates = [];

    if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
        $candidates[] = PHP_BINARY;
    }

    $whichPhp = trim((string) shell_exec('command -v php 2>/dev/null'));

    if ($whichPhp !== '') {
        $candidates[] = $whichPhp;
    }

    $candidates[] = '/usr/bin/php';
    $candidates[] = '/usr/local/bin/php';

    foreach ($candidates as $candidate) {
        if ($candidate === '' || !is_file($candidate) || !is_executable($candidate)) {
            continue;
        }

        $binaryName = strtolower(basename($candidate));

        if (str_contains($binaryName, 'php-fpm')) {
            continue;
        }

        $sapi = trim((string) @shell_exec(escapeshellarg($candidate) . " -r 'echo PHP_SAPI;' 2>/dev/null"));

        if ($sapi === 'cli') {
            return $candidate;
        }
    }

    return 'php';
}

function getNumberHealth($phone): string {
    $db = getDb();

    $stmt = $db->prepare(
        "
        SELECT COUNT(*) as failed
        FROM message_log
        WHERE from_phone = ?
        AND status = 'failed'
        AND sent_at >= datetime('now','-1 hour')
        "
    );

    $stmt->execute([$phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (($row['failed'] ?? 0) >= 5) {
        return 'paused';
    }

    if (($row['failed'] ?? 0) >= 2) {
        return 'warning';
    }

    return 'healthy';
}

$action = $_POST['action'] ?? '';
$message = '';
$messageType = 'success';
$manualSendOutput = '';

if ($action === 'start_send_now') {
    header('Content-Type: application/json');

    ensureManualSendLogDir();

    $jobId = 'manual-send-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $logPath = buildManualSendLogPath($jobId);
    $phpBinary = escapeshellarg(resolvePhpCliBinary());
    $scriptPath = escapeshellarg(__DIR__ . '/send.php');
    $logPathEscaped = escapeshellarg($logPath);
    $command = $phpBinary . ' ' . $scriptPath . ' --force > ' . $logPathEscaped . ' 2>&1 & echo $!';

    $output = [];
    exec($command, $output);
    $pid = (int) ($output[0] ?? 0);

    if ($pid <= 0) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Gagal memulai proses kirim manual.',
        ]);
        exit;
    }

    $_SESSION['manual_send_job'] = [
        'job_id' => $jobId,
        'pid' => $pid,
        'log_path' => $logPath,
        'started_at' => time(),
    ];

    echo json_encode([
        'ok' => true,
        'job_id' => $jobId,
        'pid' => $pid,
    ]);
    exit;
}

if ($action === 'poll_send_now') {
    header('Content-Type: application/json');

    $jobId = trim($_POST['job_id'] ?? '');
    $job = $_SESSION['manual_send_job'] ?? null;

    if (!is_array($job) || ($job['job_id'] ?? '') !== $jobId) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'error' => 'Job tidak ditemukan atau sesi sudah habis.',
        ]);
        exit;
    }

    $logPath = (string) ($job['log_path'] ?? '');
    $pid = (int) ($job['pid'] ?? 0);
    $running = isProcessRunning($pid);
    $output = '';

    if ($logPath !== '' && file_exists($logPath)) {
        $content = file_get_contents($logPath);
        $output = trim((string) $content);
    }

    $summary = null;

    if ($output !== '' && preg_match('/Selesai\.\s*Terkirim:\s*(\d+),\s*Gagal:\s*(\d+)\./i', $output, $matches) === 1) {
        $summary = [
            'success' => (int) $matches[1],
            'failed' => (int) $matches[2],
        ];
    }

    echo json_encode([
        'ok' => true,
        'running' => $running,
        'output' => $output,
        'summary' => $summary,
    ]);
    exit;
}

if ($action === 'add_number') {
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $token = trim($_POST['token'] ?? '');

    if ($phone === '' || $name === '') {
        $message = 'Nomor dan nama wajib diisi.';
        $messageType = 'danger';
    } else {
        $db = getDb();

        $stmt = $db->prepare(
            "
            INSERT OR IGNORE INTO numbers(phone, name, token)
            VALUES(?, ?, ?)
            "
        );

        $stmt->execute([$phone, $name, $token !== '' ? $token : null]);

        if ($stmt->rowCount() > 0) {
            $message = 'Nomor berhasil ditambahkan.';
            $messageType = 'success';
        } else {
            $message = 'Nomor sudah ada, tidak ditambahkan ulang.';
            $messageType = 'warning';
        }
    }
}

if ($action === 'update_number') {
    $id = (int) ($_POST['id'] ?? 0);
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $token = trim($_POST['token'] ?? '');

    if ($id <= 0 || $phone === '' || $name === '') {
        $message = 'Data edit nomor tidak valid.';
        $messageType = 'danger';
    } else {
        $db = getDb();

        $existsStmt = $db->prepare('SELECT id FROM numbers WHERE id = ? LIMIT 1');
        $existsStmt->execute([$id]);

        if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
            $message = 'Nomor yang akan diedit tidak ditemukan.';
            $messageType = 'danger';
        } else {
            $duplicateStmt = $db->prepare('SELECT id FROM numbers WHERE phone = ? AND id != ? LIMIT 1');
            $duplicateStmt->execute([$phone, $id]);

            if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
                $message = 'Nomor sudah dipakai data lain.';
                $messageType = 'warning';
            } else {
                $updateStmt = $db->prepare(
                    "
                    UPDATE numbers
                    SET phone = ?, name = ?, token = ?
                    WHERE id = ?
                    "
                );

                $updateStmt->execute([$phone, $name, $token !== '' ? $token : null, $id]);

                $message = 'Data nomor berhasil diperbarui.';
                $messageType = 'success';
            }
        }
    }
}

if ($action === 'delete_number') {
    $id = (int) ($_POST['id'] ?? 0);

    getDb()->prepare('DELETE FROM numbers WHERE id = ?')->execute([$id]);

    $message = 'Nomor berhasil dihapus.';
    $messageType = 'success';
}

if ($action === 'clear_logs') {
    getDb()->exec('DELETE FROM message_log');

    $message = 'Semua log pesan berhasil dihapus.';
    $messageType = 'success';
}

if ($action === 'toggle_number') {
    $id = (int) ($_POST['id'] ?? 0);

    getDb()->prepare(
        "
        UPDATE numbers
        SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END
        WHERE id = ?
        "
    )->execute([$id]);

    $message = 'Status nomor berhasil diperbarui.';
    $messageType = 'success';
}

if ($action === 'save_gateway_settings') {
    $gatewayUrl = trim($_POST['wa_gateway_url'] ?? '');
    $gatewayKey = trim($_POST['wa_gateway_key'] ?? '');

    if ($gatewayUrl === '' || $gatewayKey === '') {
        $message = 'WA Gateway URL dan WA Gateway Key wajib diisi.';
        $messageType = 'danger';
    } elseif (!filter_var($gatewayUrl, FILTER_VALIDATE_URL)) {
        $message = 'Format WA Gateway URL tidak valid.';
        $messageType = 'danger';
    } else {
        setSetting('wa_gateway_url', rtrim($gatewayUrl, '/'));
        setSetting('wa_gateway_key', $gatewayKey);

        $message = 'Pengaturan WA Gateway berhasil disimpan.';
        $messageType = 'success';
    }
}

if ($action === 'send_now') {
    $phpBinary = escapeshellarg(resolvePhpCliBinary());
    $scriptPath = escapeshellarg(__DIR__ . '/send.php');
    $command = $phpBinary . ' ' . $scriptPath . ' --force 2>&1';

    ob_start();
    passthru($command, $exitCode);
    $manualSendOutput = trim((string) ob_get_clean());

    if ($manualSendOutput === '') {
        $manualSendOutput = '(tidak ada output)';
    }

    if ($exitCode !== 0) {
        $message = 'Test kirim manual selesai dengan error (exit code ' . $exitCode . ').';
        $messageType = 'danger';
    } elseif (preg_match('/Selesai\.\s*Terkirim:\s*(\d+),\s*Gagal:\s*(\d+)\./i', $manualSendOutput, $matches) === 1) {
        $successCount = (int) $matches[1];
        $failedCount = (int) $matches[2];
        $message = 'Test kirim manual selesai. Sukses: ' . $successCount . ', Gagal: ' . $failedCount . '.';
        $messageType = $failedCount > 0 ? 'warning' : 'success';
    } else {
        $message = 'Test kirim manual selesai dijalankan, tetapi ringkasan sukses/gagal tidak ditemukan.';
        $messageType = 'warning';
    }
}

$numbers = getDb()->query('SELECT * FROM numbers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$logs = getRecentLogs(50);
$gatewayUrl = getSetting('wa_gateway_url', WA_GATEWAY_URL_DEFAULT);
$gatewayKey = getSetting('wa_gateway_key', WA_GATEWAY_KEY_DEFAULT);
$cronInstalled = isCronInstalled();
$nextSendAt = (int) getSetting('next_send_at', '0');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$webhookLink = $scheme . '://' . $host . ($basePath !== '' ? $basePath : '') . '/webhook.php';

if ($nextSendAt > 0) {
    $remaining = $nextSendAt - time();

    if ($remaining > 0) {
        $remainingHours = (int) floor($remaining / 3600);
        $remainingMinutes = (int) floor(($remaining % 3600) / 60);
        $nextSendLabel = date('Y-m-d H:i:s', $nextSendAt) . ' (sisa ' . ($remainingHours > 0 ? $remainingHours . 'j ' : '') . $remainingMinutes . 'm)';
    } else {
        $nextSendLabel = 'Segera / Belum terjadwal ulang';
    }
} else {
    $nextSendLabel = 'Belum ada jadwal (jalankan test manual untuk memulai)';
}

$cronInstallCmd = "sudo tee /etc/cron.d/wato << 'EOF'\n# WATO cron job\n*/30 * * * * www-data /usr/bin/php " . __DIR__ . "/send.php >> " . __DIR__ . "/cron.log 2>&1\nEOF";
$cronRemoveCmd = "sudo rm -f /etc/cron.d/wato";

$healthMap = [];
$healthCount = [
    'healthy' => 0,
    'warning' => 0,
    'paused' => 0,
];

$activeCount = 0;

foreach ($numbers as $num) {
    if ((int) $num['active'] === 1) {
        $activeCount++;
    }

    $health = getNumberHealth($num['phone']);
    $healthMap[$num['id']] = $health;
    $healthCount[$health]++;
}

$logSuccess = 0;
$logFailed = 0;

foreach ($logs as $log) {
    if (in_array(($log['status'] ?? ''), ['success', 'sent'], true)) {
        $logSuccess++;
    }

    if (($log['status'] ?? '') === 'failed') {
        $logFailed++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WATO Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <style>
        :root {
            --bg-main: linear-gradient(155deg, #f5f9ff 0%, #f9fffd 60%, #f8f8ff 100%);
            --card-bg: #ffffff;
            --text-main: #1b2435;
            --text-soft: #65728a;
            --line: #dbe5f2;
            --primary: #0f62fe;
            --primary-strong: #004dd9;
            --ok: #1f9d62;
            --warn: #d98a00;
            --danger: #c2410c;
            --shadow: 0 14px 40px rgba(17, 49, 96, 0.10);
            --radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
        }

        .layout-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 14px 36px;
        }

        .topbar {
            position: sticky;
            top: 10px;
            z-index: 30;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.93);
            border: 1px solid #ecf2fa;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .brand p {
            margin: 2px 0 0;
            color: var(--text-soft);
            font-size: 0.86rem;
        }

        .logout-btn {
            border-radius: 12px;
            font-weight: 700;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid #ebf1f8;
            border-radius: var(--radius);
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-soft);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .content-card {
            background: var(--card-bg);
            border: 1px solid #ebf1f8;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 14px;
            overflow: hidden;
        }

        .content-card .card-header {
            border-bottom: 1px solid #edf2f8;
            background: transparent;
            padding: 14px 18px;
        }

        .content-card .card-title {
            margin: 0;
            font-size: 1.03rem;
            font-weight: 700;
        }

        .content-card .card-subtitle {
            font-size: 0.84rem;
            color: var(--text-soft);
            margin: 4px 0 0;
        }

        .content-card .card-body {
            padding: 16px 18px 18px;
        }

        .form-control {
            border-radius: 12px;
            border-color: #d5e0ee;
            height: 44px;
        }

        .btn {
            border-radius: 12px;
            font-weight: 700;
        }

        .btn-clear-log {
            padding: 0.3rem 0.55rem;
            font-size: 0.75rem;
            line-height: 1.1;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-strong);
            border-color: var(--primary-strong);
        }

        .table thead th {
            border-bottom: 0;
            font-size: 0.82rem;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .table td,
        .table th {
            vertical-align: middle;
            border-top: 1px solid #eef3fa;
        }

        .badge {
            border-radius: 999px;
            padding: 0.45em 0.7em;
            font-weight: 700;
            font-size: 0.73rem;
        }

        .badge-healthy,
        .badge-success {
            background-color: #e8f8ef;
            color: var(--ok);
        }

        .badge-warning {
            background-color: #fff5e5;
            color: var(--warn);
        }

        .badge-danger,
        .badge-paused,
        .badge-failed {
            background-color: #fdece7;
            color: var(--danger);
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .toolbar .form-control {
            max-width: 280px;
            height: 40px;
            font-size: 0.9rem;
        }

        .action-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 18px;
            color: var(--text-soft);
            font-weight: 500;
        }

        .command-block {
            background: #0f172a;
            color: #dbeafe;
            border-radius: 12px;
            padding: 12px;
            font-size: 0.78rem;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
            margin-bottom: 8px;
        }

        .modal-shell {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1200;
        }

        .modal-shell.is-open {
            display: flex;
        }

        .modal-card {
            width: 100%;
            max-width: 760px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e7eef8;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .modal-card-header {
            padding: 14px 18px;
            border-bottom: 1px solid #edf2f8;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .modal-card-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .modal-card-subtitle {
            margin: 4px 0 0;
            color: var(--text-soft);
            font-size: 0.82rem;
        }

        .modal-card-body {
            padding: 16px 18px 18px;
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .layout-shell {
                padding: 14px 10px 20px;
            }

            .topbar {
                position: static;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar .form-control {
                max-width: none;
            }

            .form-row [class*="col-"] {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
<div class="layout-shell">
    <header class="topbar">
        <div class="brand">
            <h1>WATO Dashboard</h1>
            <p>Kelola nomor, pantau kesehatan, dan cek log pengiriman dengan cepat.</p>
        </div>
        <div class="topbar-actions">
            <button class="btn btn-outline-primary btn-sm js-open-gateway-modal" type="button">
                <i class="fas fa-cog mr-1"></i> Pengaturan Gateway
            </button>
            <button class="btn btn-primary btn-sm js-open-add-number-modal" type="button">
                <i class="fas fa-plus mr-1"></i> Tambah Nomor
            </button>
            <form method="POST" class="mb-0">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-danger logout-btn btn-sm" type="submit">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </button>
            </form>
        </div>
    </header>

    <div class="modal-shell" id="add-number-modal" aria-hidden="true">
        <div class="modal-card">
            <div class="modal-card-header">
                <div>
                    <h2 class="modal-card-title">Tambah Nomor</h2>
                    <p class="modal-card-subtitle">Isi data nomor baru. Token boleh dikosongkan jika memakai token default.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm js-close-add-number-modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
            <div class="modal-card-body">
                <form method="POST" class="form-row align-items-end" autocomplete="off">
                    <input type="hidden" name="action" value="add_number">

                    <div class="col-md-3">
                        <label for="modal_phone" class="mb-1">Nomor</label>
                        <input id="modal_phone" name="phone" class="form-control js-phone-input" placeholder="628xxxx" minlength="8" maxlength="20" required>
                    </div>

                    <div class="col-md-3">
                        <label for="modal_name" class="mb-1">Nama</label>
                        <input id="modal_name" name="name" class="form-control" placeholder="Nama kontak" required>
                    </div>

                    <div class="col-md-4">
                        <label for="modal_token" class="mb-1">Token (Opsional)</label>
                        <input id="modal_token" name="token" class="form-control" placeholder="Token khusus nomor ini">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary btn-block" type="submit">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-shell" id="gateway-modal" aria-hidden="true">
        <div class="modal-card">
            <div class="modal-card-header">
                <div>
                    <h2 class="modal-card-title">Pengaturan WA Gateway</h2>
                    <p class="modal-card-subtitle">Konfigurasi endpoint dan key gateway disimpan ke database (`settings`).</p>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm js-close-gateway-modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
            <div class="modal-card-body">
                <form method="POST" class="form-row align-items-end" autocomplete="off">
                    <input type="hidden" name="action" value="save_gateway_settings">

                    <div class="col-md-5">
                        <label for="modal_wa_gateway_url" class="mb-1">WA Gateway URL</label>
                        <input id="modal_wa_gateway_url" name="wa_gateway_url" class="form-control" placeholder="https://domain/wa" value="<?= h($gatewayUrl) ?>" required>
                    </div>

                    <div class="col-md-5">
                        <label for="modal_wa_gateway_key" class="mb-1">WA Gateway Key</label>
                        <input id="modal_wa_gateway_key" type="password" name="wa_gateway_key" class="form-control" placeholder="Masukkan API key" value="<?= h($gatewayKey) ?>" required>
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary btn-block" type="submit">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </div>
                </form>

                <hr>
                <p class="text-muted small mb-1">Link webhook (klik kolom atau tombol untuk salin):</p>
                <div class="input-group">
                    <input class="form-control js-copy-input" value="<?= h($webhookLink) ?>" readonly data-command="<?= h($webhookLink) ?>">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary js-copy-command" data-command="<?= h($webhookLink) ?>">
                            <i class="fas fa-copy mr-1"></i> Salin
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="alert alert-<?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php } ?>

    <section class="stats-grid" aria-label="Ringkasan">
        <article class="stat-card">
            <div class="stat-label">Total Nomor</div>
            <div class="stat-value"><?= count($numbers) ?></div>
        </article>
        <article class="stat-card">
            <div class="stat-label">Nomor Aktif</div>
            <div class="stat-value"><?= $activeCount ?></div>
        </article>
        <article class="stat-card">
            <div class="stat-label">Warning / Paused</div>
            <div class="stat-value"><?= $healthCount['warning'] ?> / <?= $healthCount['paused'] ?></div>
        </article>
        <article class="stat-card">
            <div class="stat-label">Log Sukses / Gagal</div>
            <div class="stat-value"><?= $logSuccess ?> / <?= $logFailed ?></div>
        </article>
    </section>

    <section class="content-card d-none" id="edit-number-card">
        <div class="card-header">
            <h2 class="card-title">Edit Nomor</h2>
            <p class="card-subtitle">Perbarui data nomor yang dipilih dari tabel.</p>
        </div>
        <div class="card-body">
            <form method="POST" class="form-row align-items-end" autocomplete="off">
                <input type="hidden" name="action" value="update_number">
                <input type="hidden" name="id" id="edit_id">

                <div class="col-md-3">
                    <label for="edit_phone" class="mb-1">Nomor</label>
                    <input id="edit_phone" name="phone" class="form-control js-phone-input" placeholder="628xxxx" minlength="8" maxlength="20" required>
                </div>

                <div class="col-md-3">
                    <label for="edit_name" class="mb-1">Nama</label>
                    <input id="edit_name" name="name" class="form-control" placeholder="Nama kontak" required>
                </div>

                <div class="col-md-4">
                    <label for="edit_token" class="mb-1">Token (Opsional)</label>
                    <input id="edit_token" name="token" class="form-control" placeholder="Token khusus nomor ini">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="content-card">
        <div class="card-header">
            <h2 class="card-title">Aksi Manual & Cron</h2>
            <p class="card-subtitle">Jalankan test kirim manual, lihat jadwal berikutnya, dan copy command cron.</p>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <p class="mb-1"><strong>Jadwal berikutnya:</strong> <?= h($nextSendLabel) ?></p>
                    <p class="text-muted small mb-3">Interval acak di `send.php`: sekitar 20 menit sampai 6 jam.</p>
                    <form method="POST" class="mb-0" id="manual-send-form">
                        <input type="hidden" name="action" value="send_now">
                        <button class="btn btn-success js-send-now-btn" type="button" id="manual-send-start-btn">
                            <i class="fas fa-paper-plane mr-1"></i> Test Kirim Manual
                        </button>
                    </form>
                    <div class="mt-3">
                        <p class="text-muted small mb-1" id="manual-send-live-status">Belum ada proses manual berjalan.</p>
                        <pre class="command-block mb-0 d-none" id="manual-send-live-log"></pre>
                    </div>
                </div>
                <div class="col-lg-7">
                    <p class="mb-1">
                        <strong>Status Cron:</strong>
                        <?php if ($cronInstalled) { ?>
                            <span class="badge badge-success">Terpasang</span>
                        <?php } else { ?>
                            <span class="badge badge-danger">Tidak Terpasang</span>
                        <?php } ?>
                    </p>

                    <p class="text-muted small mb-1">Pasang cron:</p>
                    <pre class="command-block"><?= h($cronInstallCmd) ?></pre>
                    <button type="button" class="btn btn-outline-secondary btn-sm js-copy-command mb-3" data-command="<?= h($cronInstallCmd) ?>">Salin Perintah Pasang</button>

                    <p class="text-muted small mb-1">Hapus cron:</p>
                    <pre class="command-block"><?= h($cronRemoveCmd) ?></pre>
                    <button type="button" class="btn btn-outline-secondary btn-sm js-copy-command" data-command="<?= h($cronRemoveCmd) ?>">Salin Perintah Hapus</button>
                </div>
            </div>

            <?php if ($manualSendOutput !== '') { ?>
                <hr>
                <p class="text-muted small mb-1">Output test manual terbaru:</p>
                <pre class="command-block mb-0"><?= h($manualSendOutput) ?></pre>
            <?php } ?>
        </div>
    </section>

    <section class="content-card">
        <div class="card-header">
            <h2 class="card-title">Daftar Nomor</h2>
            <p class="card-subtitle">Cari cepat berdasarkan nomor atau nama, lalu lakukan aksi dari kolom terakhir.</p>
        </div>
        <div class="card-body table-responsive">
            <div class="toolbar">
                <span class="text-muted small">Menampilkan <?= count($numbers) ?> nomor</span>
                <input type="search" class="form-control js-filter" data-target="numbers-table" placeholder="Cari nomor / nama...">
            </div>

            <table class="table" id="numbers-table">
                <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Kesehatan</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($numbers) === 0) { ?>
                    <tr>
                        <td colspan="5" class="empty-state">Belum ada nomor. Tambahkan nomor pertama di form atas.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($numbers as $num) {
                    $health = $healthMap[$num['id']] ?? 'healthy';
                ?>
                    <tr>
                        <td><?= h($num['phone']) ?></td>
                        <td><?= h($num['name']) ?></td>
                        <td>
                            <?php if ((int) $num['active'] === 1) { ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php } else { ?>
                                <span class="badge badge-danger">Nonaktif</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($health === 'healthy') { ?>
                                <span class="badge badge-healthy">Healthy</span>
                            <?php } elseif ($health === 'warning') { ?>
                                <span class="badge badge-warning">Warning</span>
                            <?php } else { ?>
                                <span class="badge badge-paused">Paused</span>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="action-group">
                                <form method="POST" class="mb-0">
                                    <input type="hidden" name="action" value="toggle_number">
                                    <input type="hidden" name="id" value="<?= (int) $num['id'] ?>">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">
                                        <i class="fas fa-power-off mr-1"></i> Toggle
                                    </button>
                                </form>
                                <button
                                    class="btn btn-outline-primary btn-sm js-edit-number"
                                    type="button"
                                    data-id="<?= (int) $num['id'] ?>"
                                    data-phone="<?= h($num['phone']) ?>"
                                    data-name="<?= h($num['name']) ?>"
                                    data-token="<?= h($num['token'] ?? '') ?>"
                                >
                                    <i class="fas fa-pen mr-1"></i> Edit
                                </button>
                                <form method="POST" class="mb-0">
                                    <input type="hidden" name="action" value="delete_number">
                                    <input type="hidden" name="id" value="<?= (int) $num['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm js-confirm-delete" type="submit" data-name="<?= h($num['name']) ?>">
                                        <i class="fas fa-trash mr-1"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="content-card">
        <div class="card-header">
            <h2 class="card-title">Log Pesan Terbaru</h2>
            <p class="card-subtitle">Riwayat 50 pengiriman terakhir untuk pemantauan cepat.</p>
        </div>
        <div class="card-body table-responsive">
            <div class="toolbar">
                <span class="text-muted small">Menampilkan <?= count($logs) ?> log</span>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <input type="search" class="form-control js-filter" data-target="logs-table" placeholder="Cari nomor / status...">
                    <form method="POST" class="mb-0">
                        <input type="hidden" name="action" value="clear_logs">
                        <button class="btn btn-outline-danger btn-sm btn-clear-log js-confirm-clear-logs" type="submit">
                            <i class="fas fa-broom mr-1"></i> Clear Log
                        </button>
                    </form>
                </div>
            </div>

            <table class="table" id="logs-table">
                <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Dari</th>
                    <th>Ke</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($logs) === 0) { ?>
                    <tr>
                        <td colspan="4" class="empty-state">Belum ada data log.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($logs as $log) { ?>
                    <tr>
                        <td><?= h($log['sent_at']) ?></td>
                        <td><?= h($log['from_phone']) ?></td>
                        <td><?= h($log['to_phone']) ?></td>
                        <td>
                            <?php if (in_array(($log['status'] ?? ''), ['success', 'sent'], true)) { ?>
                                <span class="badge badge-success">success</span>
                            <?php } elseif (($log['status'] ?? '') === 'failed') { ?>
                                <span class="badge badge-failed">failed</span>
                            <?php } else { ?>
                                <span class="badge badge-warning"><?= h($log['status']) ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    (function () {
        document.querySelectorAll('.js-phone-input').forEach(function (phoneInput) {
            phoneInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D+/g, '');
            });
        });

        document.querySelectorAll('.js-confirm-delete').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var name = this.getAttribute('data-name') || 'nomor ini';

                if (!window.confirm('Hapus ' + name + '? Tindakan ini tidak dapat dibatalkan.')) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('.js-confirm-clear-logs').forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!window.confirm('Hapus semua log pesan terbaru?')) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('.js-filter').forEach(function (input) {
            input.addEventListener('input', function () {
                var table = document.getElementById(this.getAttribute('data-target'));

                if (!table || !table.tBodies.length) {
                    return;
                }

                var keyword = this.value.toLowerCase().trim();
                var rows = table.tBodies[0].rows;

                Array.prototype.forEach.call(rows, function (row) {
                    var text = row.textContent.toLowerCase();
                    var isEmptyState = row.querySelector('.empty-state');

                    if (isEmptyState) {
                        row.style.display = '';
                        return;
                    }

                    row.style.display = text.indexOf(keyword) !== -1 ? '' : 'none';
                });
            });
        });

        function copyText(text, done) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () {});
                return;
            }

            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            done();
        }

        document.querySelectorAll('.js-copy-command').forEach(function (button) {
            button.addEventListener('click', function () {
                var text = this.getAttribute('data-command') || '';
                var currentButton = this;
                var original = currentButton.innerHTML;

                copyText(text, function () {
                    currentButton.innerHTML = 'Tersalin';
                    currentButton.classList.remove('btn-outline-secondary');
                    currentButton.classList.add('btn-success');

                    setTimeout(function () {
                        currentButton.innerHTML = original;
                        currentButton.classList.remove('btn-success');
                        currentButton.classList.add('btn-outline-secondary');
                    }, 1800);
                });
            });
        });

        document.querySelectorAll('.js-copy-input').forEach(function (input) {
            input.addEventListener('click', function () {
                this.select();
                copyText(this.getAttribute('data-command') || this.value, function () {});
            });
        });

        var manualSendButton = document.getElementById('manual-send-start-btn');
        var manualSendStatus = document.getElementById('manual-send-live-status');
        var manualSendLog = document.getElementById('manual-send-live-log');
        var manualSendPolling = null;

        function updateManualSendLogDisplay(text) {
            if (!manualSendLog) {
                return;
            }

            var value = (text || '').trim();

            if (value === '') {
                manualSendLog.classList.add('d-none');
                manualSendLog.textContent = '';
                return;
            }

            manualSendLog.classList.remove('d-none');
            manualSendLog.textContent = value;
            manualSendLog.scrollTop = manualSendLog.scrollHeight;
        }

        function setManualSendButtonIdle() {
            if (!manualSendButton) {
                return;
            }

            manualSendButton.disabled = false;
            manualSendButton.innerHTML = '<i class="fas fa-paper-plane mr-1"></i> Test Kirim Manual';
        }

        function pollManualSendJob(jobId) {
            if (!jobId) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'poll_send_now');
            formData.append('job_id', jobId);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.error) ? data.error : 'Gagal membaca status proses.');
                }

                updateManualSendLogDisplay(data.output || '');

                if (data.running) {
                    if (manualSendStatus) {
                        manualSendStatus.textContent = 'Proses kirim manual sedang berjalan...';
                    }
                    return;
                }

                if (manualSendPolling) {
                    clearInterval(manualSendPolling);
                    manualSendPolling = null;
                }

                if (manualSendStatus) {
                    if (data.summary) {
                        manualSendStatus.textContent = 'Selesai. Sukses: ' + data.summary.success + ', Gagal: ' + data.summary.failed + '.';
                    } else {
                        manualSendStatus.textContent = 'Proses selesai. Ringkasan tidak ditemukan.';
                    }
                }

                setManualSendButtonIdle();
            }).catch(function (error) {
                if (manualSendPolling) {
                    clearInterval(manualSendPolling);
                    manualSendPolling = null;
                }

                if (manualSendStatus) {
                    manualSendStatus.textContent = 'Gagal membaca proses: ' + error.message;
                }

                setManualSendButtonIdle();
            });
        }

        if (manualSendButton) {
            manualSendButton.addEventListener('click', function () {
                manualSendButton.disabled = true;
                manualSendButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menjalankan...';

                if (manualSendStatus) {
                    manualSendStatus.textContent = 'Memulai proses kirim manual...';
                }

                updateManualSendLogDisplay('');

                var formData = new FormData();
                formData.append('action', 'start_send_now');

                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (!data || !data.ok || !data.job_id) {
                        throw new Error((data && data.error) ? data.error : 'Gagal memulai proses.');
                    }

                    pollManualSendJob(data.job_id);

                    manualSendPolling = setInterval(function () {
                        pollManualSendJob(data.job_id);
                    }, 2000);
                }).catch(function (error) {
                    if (manualSendStatus) {
                        manualSendStatus.textContent = 'Gagal memulai proses: ' + error.message;
                    }
                    setManualSendButtonIdle();
                });
            });
        }

        var addNumberModal = document.getElementById('add-number-modal');
        var addNumberModalPhone = document.getElementById('modal_phone');
        var gatewayModal = document.getElementById('gateway-modal');
        var gatewayModalUrl = document.getElementById('modal_wa_gateway_url');

        function openAddNumberModal() {
            if (!addNumberModal) {
                return;
            }

            addNumberModal.classList.add('is-open');
            addNumberModal.setAttribute('aria-hidden', 'false');

            if (addNumberModalPhone) {
                addNumberModalPhone.focus();
            }
        }

        function closeAddNumberModal() {
            if (!addNumberModal) {
                return;
            }

            addNumberModal.classList.remove('is-open');
            addNumberModal.setAttribute('aria-hidden', 'true');
        }

        function openGatewayModal() {
            if (!gatewayModal) {
                return;
            }

            gatewayModal.classList.add('is-open');
            gatewayModal.setAttribute('aria-hidden', 'false');

            if (gatewayModalUrl) {
                gatewayModalUrl.focus();
            }
        }

        function closeGatewayModal() {
            if (!gatewayModal) {
                return;
            }

            gatewayModal.classList.remove('is-open');
            gatewayModal.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('.js-open-add-number-modal').forEach(function (button) {
            button.addEventListener('click', openAddNumberModal);
        });

        document.querySelectorAll('.js-close-add-number-modal').forEach(function (button) {
            button.addEventListener('click', closeAddNumberModal);
        });

        document.querySelectorAll('.js-open-gateway-modal').forEach(function (button) {
            button.addEventListener('click', openGatewayModal);
        });

        document.querySelectorAll('.js-close-gateway-modal').forEach(function (button) {
            button.addEventListener('click', closeGatewayModal);
        });

        if (addNumberModal) {
            addNumberModal.addEventListener('click', function (event) {
                if (event.target === addNumberModal) {
                    closeAddNumberModal();
                }
            });
        }

        if (gatewayModal) {
            gatewayModal.addEventListener('click', function (event) {
                if (event.target === gatewayModal) {
                    closeGatewayModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAddNumberModal();
                closeGatewayModal();
            }
        });

        var editCard = document.getElementById('edit-number-card');
        var editId = document.getElementById('edit_id');
        var editPhone = document.getElementById('edit_phone');
        var editName = document.getElementById('edit_name');
        var editToken = document.getElementById('edit_token');

        document.querySelectorAll('.js-edit-number').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!editCard || !editId || !editPhone || !editName || !editToken) {
                    return;
                }

                editId.value = this.getAttribute('data-id') || '';
                editPhone.value = (this.getAttribute('data-phone') || '').replace(/\D+/g, '');
                editName.value = this.getAttribute('data-name') || '';
                editToken.value = this.getAttribute('data-token') || '';

                editCard.classList.remove('d-none');
                editCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                editPhone.focus();
            });
        });
    })();
</script>
</body>
</html>
