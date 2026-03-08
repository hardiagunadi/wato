<?php

session_start();

// ---- Auth ----
define('AUTH_USER', 'wato');
define('AUTH_PASS', 'wato');

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['username'] === AUTH_USER && $_POST['password'] === AUTH_PASS) {
        $_SESSION['logged_in'] = true;
    } else {
        $loginError = 'Username atau password salah.';
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: /');
    exit;
}

if (empty($_SESSION['logged_in'])) {
    $loginError = $loginError ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>WATO - Login</title>
</head>
<body>
<form method="POST">
<input type="hidden" name="action" value="login">
<input name="username" placeholder="username">
<input name="password" type="password" placeholder="password">
<button>Login</button>
</form>
</body>
</html>
<?php
exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* =========================
   HEALTH INDICATOR
========================= */

function getNumberHealth(string $phone): string {

    $db = getDb();

    $stmt = $db->prepare("
        SELECT COUNT(*) as failed
        FROM message_log
        WHERE from_phone = ?
        AND status = 'failed'
        AND sent_at >= datetime('now','-1 hour')
    ");

    $stmt->execute([$phone]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['failed'] >= 5) {
        return 'paused';
    }

    if ($row['failed'] >= 2) {
        return 'warning';
    }

    return 'healthy';
}

/* =========================
   HANDLE POST
========================= */

$action  = $_POST['action'] ?? '';
$message = '';

if ($action === 'save_settings') {
    setSetting('default_token', trim($_POST['default_token'] ?? ''));
    setSetting('webhook_url', trim($_POST['webhook_url'] ?? ''));
    $message = 'Pengaturan berhasil disimpan.';
}

if ($action === 'add_number') {

    $phone = preg_replace('/\D/','',$_POST['phone'] ?? '');
    $name  = $_POST['name'] ?? '';
    $token = $_POST['token'] ?? '';

    if ($phone) {

        $db = getDb();

        $stmt = $db->prepare("
            INSERT OR IGNORE INTO numbers (phone,name,token)
            VALUES (?,?,?)
        ");

        $stmt->execute([$phone,$name,$token ?: null]);

        $message = "Nomor $phone ditambahkan.";
    }
}

if ($action === 'delete_number') {

    $id = (int)($_POST['id'] ?? 0);

    getDb()->prepare("DELETE FROM numbers WHERE id=?")->execute([$id]);

    $message = "Nomor dihapus.";
}

if ($action === 'toggle_number') {

    $id = (int)($_POST['id'] ?? 0);

    getDb()->prepare("
        UPDATE numbers
        SET active = CASE WHEN active=1 THEN 0 ELSE 1 END
        WHERE id=?
    ")->execute([$id]);

    $message = "Status nomor diperbarui.";
}

if ($action === 'send_now') {

    ob_start();

    passthru('php ' . escapeshellarg(__DIR__.'/send.php') . ' --force 2>&1');

    $message = ob_get_clean();
}

/* =========================
   FETCH DATA
========================= */

$numbers = getDb()->query("
SELECT * FROM numbers ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$logs = getRecentLogs(50);

$defaultToken = getSetting('default_token');
$webhookUrl   = getSetting('webhook_url');

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>WATO</title>

<style>

body{
font-family:system-ui;
background:#f1f5f9;
padding:20px;
}

table{
border-collapse:collapse;
width:100%;
}

th,td{
padding:8px;
border-bottom:1px solid #ddd;
}

.pill{
padding:3px 8px;
border-radius:999px;
font-size:12px;
font-weight:600;
}

.pill-active{
background:#dcfce7;
color:#166534;
}

.pill-inactive{
background:#fee2e2;
color:#991b1b;
}

</style>

</head>

<body>

<h1>WATO Dashboard</h1>

<?php if ($message): ?>
<p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<h2>Tambah Nomor</h2>

<form method="POST">

<input type="hidden" name="action" value="add_number">

<input name="phone" placeholder="628xxxx">

<input name="name" placeholder="Nama">

<input name="token" placeholder="Token">

<button>Tambah</button>

</form>

<h2>Daftar Nomor</h2>

<table>

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

<?php foreach ($numbers as $num): ?>

<?php $health = getNumberHealth($num['phone']); ?>

<tr>

<td><?= htmlspecialchars($num['phone']) ?></td>

<td><?= htmlspecialchars($num['name']) ?></td>

<td>
<span class="pill <?= $num['active'] ? 'pill-active':'pill-inactive' ?>">
<?= $num['active'] ? 'Aktif':'Nonaktif' ?>
</span>
</td>

<td>

<?php if ($health === 'healthy'): ?>

<span class="pill pill-active">🟢 Healthy</span>

<?php elseif ($health === 'warning'): ?>

<span class="pill" style="background:#fef9c3;color:#854d0e;">🟡 Warning</span>

<?php else: ?>

<span class="pill pill-inactive">🔴 Paused</span>

<?php endif; ?>

</td>

<td>

<form method="POST" style="display:inline">
<input type="hidden" name="action" value="toggle_number">
<input type="hidden" name="id" value="<?= $num['id'] ?>">
<button>Toggle</button>
</form>

<form method="POST" style="display:inline">
<input type="hidden" name="action" value="delete_number">
<input type="hidden" name="id" value="<?= $num['id'] ?>">
<button>Hapus</button>
</form>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<h2>Log Pesan</h2>

<table>

<tr>
<th>Waktu</th>
<th>Dari</th>
<th>Ke</th>
<th>Status</th>
</tr>

<?php foreach ($logs as $log): ?>

<tr>
<td><?= $log['sent_at'] ?></td>
<td><?= $log['from_phone'] ?></td>
<td><?= $log['to_phone'] ?></td>
<td><?= $log['status'] ?></td>
</tr>

<?php endforeach; ?>

</table>

</body>
</html>