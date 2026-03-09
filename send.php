<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/generate.php';

function sendWaMessage(string $token, string $toPhone, string $message, string $refId): array {
    $gateway = getGatewayConfig();
    $baseUrl = $gateway['url'];
    $gatewayKey = $gateway['key'];

    if ($baseUrl === '' || $gatewayKey === '') {
        return ['success' => false, 'error' => 'Gateway URL/KEY belum diatur di dashboard.'];
    }

    $url = $baseUrl . '/api/send-message';

    $payload = http_build_query([
        'phone'   => $toPhone,
        'message' => $message,
        'isGroup' => 'false',
        'ref_id'  => $refId,
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'key: ' . $gatewayKey,
            'Authorization: ' . $token,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);

    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    $data = json_decode($response, true);

    return [
        'success'   => $httpCode === 200 && isset($data['status']) && $data['status'] === true,
        'http_code' => $httpCode,
        'response'  => $data,
    ];
}

function log_out(string $msg): void {

    $timestamp = date('Y-m-d H:i:s');

    echo "[$timestamp] $msg\n";
}

/* =========================
   WARMUP + DAILY LIMIT
========================= */

function getDailyLimit(string $phone): int {

    $db = getDb();

    $stmt = $db->prepare("
        SELECT created_at
        FROM numbers
        WHERE phone = ?
    ");

    $stmt->execute([$phone]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return 100;

    $days = floor((time() - strtotime($row['created_at'])) / 86400);

    if ($days <= 1) return 10;
    if ($days <= 3) return 30;
    if ($days <= 7) return 60;

    return 120;
}

function isDailyLimitReached(string $phone): bool {

    $db = getDb();

    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM message_log
        WHERE from_phone = ?
        AND date(sent_at) = date('now')
    ");

    $stmt->execute([$phone]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $limit = getDailyLimit($phone);

    return $row['total'] >= $limit;
}

/* =========================
   INTERVAL RANDOM
========================= */

function getNextIntervalSeconds(): int {

    $options = [
        rand(20,40) * 60,
        rand(50,80) * 60,
        rand(90,180) * 60,
        rand(180,360) * 60
    ];

    return $options[array_rand($options)];
}

function intervalLabel(int $seconds): string {

    if ($seconds < 3600) {
        return round($seconds / 60) . ' menit';
    }

    return round($seconds / 3600,1) . ' jam';
}

/* =========================
   HUMAN BEHAVIOR ENGINE
========================= */

function getSmartDelay(): int {

    $hour = (int)date('H');

    if ($hour >= 6 && $hour < 9) return rand(5,12);
    if ($hour >= 9 && $hour < 12) return rand(4,8);
    if ($hour >= 12 && $hour < 14) return rand(6,12);
    if ($hour >= 14 && $hour < 18) return rand(4,9);
    if ($hour >= 18 && $hour < 22) return rand(6,14);

    return rand(10,20);
}

function isNightSleep(): bool {

    $hour = (int)date('H');

    return ($hour >= 22 || $hour < 6);
}

function microBreak(): void {

    if (rand(1,18) === 9) {

        $pause = rand(30,120);

        log_out("Micro break {$pause} detik...");

        sleep($pause);
    }
}

function hourlySafety(): void {

    static $count = 0;
    static $hourMark = null;

    $currentHour = date('H');

    if ($hourMark === null) {
        $hourMark = $currentHour;
    }

    if ($currentHour !== $hourMark) {

        $count = 0;

        $hourMark = $currentHour;
    }

    $count++;

    if ($count >= 35) {

        $pause = rand(300,900);

        log_out("Hourly safety pause {$pause} detik...");

        sleep($pause);

        $count = 0;
    }
}

function shuffleSenders(array $numbers): array {

    shuffle($numbers);

    return $numbers;
}

function shuffleReceivers(array $numbers): array {

    shuffle($numbers);

    return $numbers;
}

/* =========================
   MAIN SCRIPT
========================= */

$numbers = getActiveNumbers();

if (count($numbers) < 2) {

    log_out("Tidak cukup nomor aktif (butuh minimal 2). Keluar.");

    exit(0);
}

if (isNightSleep()) {

    log_out("Mode tidur aktif (22:00 - 06:00). Tidak mengirim pesan.");

    exit;
}

$defaultToken = getSetting('default_token');

$timestamp = time();

$sent = 0;
$failed = 0;

log_out("Mulai pengiriman peer-to-peer untuk " . count($numbers) . " nomor.");

$senders = shuffleSenders($numbers);

foreach ($senders as $from) {

    if (isDailyLimitReached($from['phone'])) {

        log_out("SKIP {$from['phone']} (daily limit tercapai)");

        continue;
    }

    $token = !empty($from['token']) ? $from['token'] : $defaultToken;

    if (empty($token)) {

        log_out("SKIP {$from['phone']}: tidak ada token.");

        continue;
    }

    $receivers = shuffleReceivers($numbers);

    foreach ($receivers as $to) {

        if ($from['id'] === $to['id']) continue;

        hourlySafety();

        $message = generateRandomText($to['name'] ?? '');

        $refId = "wato-" . uniqid() . "-{$from['phone']}-{$to['phone']}";

        $result = sendWaMessage($token, $to['phone'], $message, $refId);

        $status = $result['success'] ? 'success' : 'failed';

        logMessage($from['phone'], $to['phone'], $message, 'out', $status, $refId);

        if ($result['success']) {

            $sent++;

            log_out("OK  {$from['phone']} → {$to['phone']}");
        }
        else {

            $failed++;

            $errDetail = $result['error'] ?? json_encode($result['response'] ?? '');

            log_out("ERR {$from['phone']} → {$to['phone']}: $errDetail");
        }

        $delay = getSmartDelay();

        log_out("Delay {$delay} detik...");

        sleep($delay);

        microBreak();
    }
}

$interval = getNextIntervalSeconds();

$nextSend  = time() + $interval;

setSetting('next_send_at', (string)$nextSend);

log_out("Selesai. Terkirim: $sent, Gagal: $failed.");

log_out("Jadwal pengiriman berikutnya: " . date('Y-m-d H:i:s', $nextSend) . " (+" . intervalLabel($interval) . ")");
