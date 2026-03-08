<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function log_out(string $msg): void {

    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

$pdo = getPDO();

/*
cek pesan gagal dalam 30 menit
*/

$sql = "
SELECT 
    from_phone,
    COUNT(*) as failed_count
FROM log_messages
WHERE status='failed'
AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
GROUP BY from_phone
HAVING failed_count >= 5
";

$stmt = $pdo->query($sql);

$numbers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$numbers) {

    log_out("Tidak ada nomor bermasalah.");

    exit;
}

foreach ($numbers as $row) {

    $phone = $row['from_phone'];

    log_out("Nomor $phone terdeteksi sering gagal kirim.");

    /*
    pause nomor selama 1 jam
    */

    $pauseUntil = date('Y-m-d H:i:s', time() + 3600);

    $update = $pdo->prepare("
        UPDATE numbers 
        SET paused_until = ?
        WHERE phone = ?
    ");

    $update->execute([$pauseUntil, $phone]);

    log_out("Nomor $phone dipause sampai $pauseUntil");
}