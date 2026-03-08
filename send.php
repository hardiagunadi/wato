<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/generate.php';

function sendWaMessage(string $token, string $toPhone, string $message, string $refId): array {
    $url = WA_GATEWAY_URL . '/api/send-message';
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
            'key: ' . WA_GATEWAY_KEY,
            'Authorization: ' . $token,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 15,
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
        'success'  => $httpCode === 200 && isset($data['status']) && $data['status'] === true,
        'http_code' => $httpCode,
        'response'  => $data,
    ];
}

function log_out(string $msg): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $msg\n";
}

// ---- Main ----

$numbers = getActiveNumbers();

if (count($numbers) < 2) {
    log_out("Tidak cukup nomor aktif (butuh minimal 2). Keluar.");
    exit(0);
}

$defaultToken = getSetting('default_token');

if (empty($defaultToken)) {
    log_out("PERINGATAN: default_token belum dikonfigurasi di Settings dashboard.");
}

$timestamp = time();
$sent = 0;
$failed = 0;

log_out("Mulai pengiriman peer-to-peer untuk " . count($numbers) . " nomor.");

foreach ($numbers as $from) {
    $token = !empty($from['token']) ? $from['token'] : getSetting('default_token');

    if (empty($token)) {
        log_out("SKIP {$from['phone']}: tidak ada token.");
        continue;
    }

    foreach ($numbers as $to) {
        if ($from['id'] === $to['id']) continue;

        $message = generateRandomText($to['name'] ?? '');
        $refId   = "wato-{$timestamp}-{$from['phone']}-{$to['phone']}";

        $result = sendWaMessage($token, $to['phone'], $message, $refId);
        $status = $result['success'] ? 'sent' : 'failed';

        logMessage($from['phone'], $to['phone'], $message, 'out', $status, $refId);

        if ($result['success']) {
            $sent++;
            log_out("OK  {$from['phone']} → {$to['phone']}");
        } else {
            $failed++;
            $errDetail = $result['error'] ?? json_encode($result['response'] ?? '');
            log_out("ERR {$from['phone']} → {$to['phone']}: $errDetail");
        }

        // Jeda kecil agar tidak flood
        usleep(500000); // 0.5 detik
    }
}

log_out("Selesai. Terkirim: $sent, Gagal: $failed.");
