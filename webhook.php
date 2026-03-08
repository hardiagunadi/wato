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

// Normalize: bisa dari berbagai format webhook wa-gateway
$phone     = $payload['phone'] ?? $payload['from'] ?? '';
$message   = $payload['message'] ?? $payload['text'] ?? '';
$sessionId = $payload['sessionId'] ?? $payload['session_id'] ?? '';
$type      = $payload['type'] ?? 'text';

// Hapus suffix @s.whatsapp.net atau @g.us
$phone = preg_replace('/@.+$/', '', $phone);

// Hanya proses pesan teks yang masuk (bukan yang dikirim sendiri)
if (empty($phone) || empty($message) || $type !== 'text') {
    echo json_encode(['status' => 'ok', 'note' => 'ignored']);
    exit;
}

// Cek apakah nomor terdaftar di sistem
$db = getDb();
$stmt = $db->prepare("SELECT * FROM numbers WHERE phone = ? AND active = 1");
$stmt->execute([$phone]);
$number = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$number) {
    echo json_encode(['status' => 'ok', 'note' => 'number not registered']);
    exit;
}

// Log pesan masuk
logMessage($phone, 'system', $message, 'in', 'received', '');

// Jeda sebelum membalas
sleep(20);

// Generate balasan acak
$reply = generateRandomText($number['name'] ?? '');

// Tentukan token untuk membalas (pakai token nomor penerima asli, yaitu nomor yang dikirim pesan oleh sistem)
$replyToken = !empty($number['token']) ? $number['token'] : getSetting('default_token');

if (empty($replyToken)) {
    echo json_encode(['status' => 'ok', 'note' => 'no token configured for reply']);
    exit;
}

// Kirim balasan
$url = WA_GATEWAY_URL . '/api/send-message';
$refId = 'wato-reply-' . time() . '-' . $phone;

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

$data   = json_decode($response, true);
$status = ($httpCode === 200 && isset($data['status']) && $data['status'] === true) ? 'sent' : 'failed';

logMessage('system', $phone, $reply, 'out', $status, $refId);

echo json_encode(['status' => 'ok', 'reply' => $status]);
