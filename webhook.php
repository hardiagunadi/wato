<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/generate.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!$payload) {
    echo json_encode(['status' => 'ok', 'note' => 'empty payload']);
    exit;
}

/* =========================
   NORMALIZE PAYLOAD
========================= */

$phone     = $payload['phone'] ?? $payload['from'] ?? '';
$message   = $payload['message'] ?? $payload['text'] ?? '';
$type      = $payload['type'] ?? 'text';
$fromMe    = $payload['fromMe'] ?? false;

// hapus suffix WA
$phone = preg_replace('/@.+$/', '', $phone);

/* =========================
   FILTER EVENT
========================= */

// ignore pesan dari sistem sendiri
if ($fromMe) {
    echo json_encode(['status' => 'ok', 'note' => 'from self']);
    exit;
}

if (empty($phone) || empty($message) || $type !== 'text') {
    echo json_encode(['status' => 'ok', 'note' => 'ignored']);
    exit;
}

/* =========================
   CEK NOMOR TERDAFTAR
========================= */

$db = getDb();

$stmt = $db->prepare("
SELECT *
FROM numbers
WHERE phone = ?
AND active = 1
AND (
    paused_until IS NULL
    OR paused_until < datetime('now')
)
");

$stmt->execute([$phone]);

$number = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$number) {

    echo json_encode(['status' => 'ok', 'note' => 'number not registered']);

    exit;
}

/* =========================
   LOG PESAN MASUK
========================= */

logMessage($phone, 'system', $message, 'in', 'received', '');

/* =========================
   DELAY BALASAN (NATURAL)
========================= */

sleep(rand(10,30));

/* =========================
   GENERATE BALASAN
========================= */

$reply = generateRandomText($number['name'] ?? '');

/* =========================
   TOKEN
========================= */

$replyToken = !empty($number['token'])
    ? $number['token']
    : getSetting('default_token');

if (empty($replyToken)) {

    echo json_encode([
        'status' => 'ok',
        'note' => 'no token configured'
    ]);

    exit;
}

/* =========================
   KIRIM BALASAN
========================= */

$url = WA_GATEWAY_URL . '/api/send-message';

$refId = 'wato-reply-' . uniqid();

$postData = http_build_query([
    'phone'   => $phone,
    'message' => $reply,
    'isGroup' => 'false',
    'ref_id'  => $refId,
]);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_HTTPHEADER     => [
        'key: ' . WA_GATEWAY_KEY,
        'Authorization: ' . $replyToken,
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

$data = json_decode($response, true);

$status = ($httpCode === 200 && isset($data['status']) && $data['status'] === true)
    ? 'sent'
    : 'failed';

/* =========================
   LOG BALASAN
========================= */

logMessage('system', $phone, $reply, 'out', $status, $refId);

echo json_encode([
    'status' => 'ok',
    'reply' => $status
]);