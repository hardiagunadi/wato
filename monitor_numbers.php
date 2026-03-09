<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function log_out(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . "] $msg\n";
}

$db = getDb();

$sql = "
SELECT
    from_phone,
    COUNT(*) AS failed_count
FROM message_log
WHERE status = 'failed'
  AND sent_at >= datetime('now', '-30 minutes')
GROUP BY from_phone
HAVING failed_count >= 5
";

$stmt = $db->query($sql);
$numbers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$numbers) {
    log_out('Tidak ada nomor bermasalah.');
    exit;
}

foreach ($numbers as $row) {
    $phone = (string) ($row['from_phone'] ?? '');

    if ($phone === '' || $phone === 'system') {
        continue;
    }

    log_out("Nomor $phone terdeteksi sering gagal kirim.");

    $pauseUntil = date('Y-m-d H:i:s', time() + 3600);

    $update = $db->prepare('
        UPDATE numbers
        SET paused_until = ?
        WHERE phone = ?
    ');

    $update->execute([$pauseUntil, $phone]);

    log_out("Nomor $phone dipause sampai $pauseUntil");
}
