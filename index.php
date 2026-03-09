```php
<?php

session_start();

/* =========================
   AUTH
========================= */

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WATO - Login</title>

<style>

body{
font-family:system-ui;
background:#f1f5f9;
display:flex;
align-items:center;
justify-content:center;
height:100vh;
}

.box{
background:#fff;
padding:30px;
border-radius:12px;
box-shadow:0 4px 16px rgba(0,0,0,.1);
width:320px;
}

input{
width:100%;
padding:8px;
margin-bottom:10px;
border:1px solid #cbd5e1;
border-radius:6px;
}

button{
width:100%;
padding:10px;
background:#1e40af;
color:#fff;
border:none;
border-radius:6px;
cursor:pointer;
}

.err{
background:#fee2e2;
color:#991b1b;
padding:8px;
border-radius:6px;
margin-bottom:10px;
}

</style>
</head>

<body>

<div class="box">

<h2>WATO Login</h2>

<?php if ($loginError): ?>
<div class="err"><?= htmlspecialchars($loginError) ?></div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="action" value="login">
<input name="username" placeholder="Username">
<input type="password" name="password" placeholder="Password">
<button>Login</button>
</form>

</div>

</body>
</html>
<?php
exit;
}

/* =========================
   LOAD CONFIG
========================= */

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
        AND status='failed'
        AND sent_at >= datetime('now','-1 hour')
    ");

    $stmt->execute([$phone]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['failed'] >= 5) return 'paused';
    if ($row['failed'] >= 2) return 'warning';

    return 'healthy';
}

/* =========================
   HANDLE POST
========================= */

$action = $_POST['action'] ?? '';
$message = '';

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

        $message = "Nomor $phone berhasil ditambahkan.";
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
    passthru('php ' . escapeshellarg(__DIR__.'/send.php') . ' --force');
    $message = ob_get_clean();
}

/* =========================
   FETCH DATA
========================= */

$numbers = getDb()->query("
SELECT * FROM numbers ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$logs = getRecentLogs(50);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>WATO Dashboard</title>

<style>

body{
font-family:system-ui;
background:#f1f5f9;
margin:0;
padding:20px;
}

header{
background:#1e40af;
color:white;
padding:14px 20px;
border-radius:10px;
margin-bottom:20px;
}

.card{
background:white;
padding:20px;
border-radius:12px;
box-shadow:0 1px 4px rgba(0,0,0,.1);
margin-bottom:20px;
}

table{
width:100%;
border-collapse:collapse;
}

th{
text-align:left;
padding:10px;
background:#f8fafc;
border-bottom:2px solid #e2e8f0;
}

td{
padding:8px;
border-bottom:1px solid #f1f5f9;
}

input{
padding:6px;
border:1px solid #cbd5e1;
border-radius:6px;
}

button{
padding:6px 12px;
border:none;
border-radius:6px;
cursor:pointer;
}

.btn{
background:#1e40af;
color:white;
}

.btn-red{
background:#ef4444;
color:white;
}

.btn-gray{
background:#94a3b8;
color:white;
}

.pill{
padding:2px 8px;
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

<header>
<h2>WATO Dashboard</h2>
<form method="POST" style="float:right">
<input type="hidden" name="action" value="logout">
<button class="btn-gray">Logout</button>
</form>
</header>

<?php if ($message): ?>
<div class="card"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">

<h3>Tambah Nomor</h3>

<form method="POST">

<input type="hidden" name="action" value="add_number">

<input name="phone" placeholder="628xxxx">
<input name="name" placeholder="Nama">
<input name="token" placeholder="Token">

<button class="btn">Tambah</button>

</form>

</div>

<div class="card">

<h3>Daftar Nomor</h3>

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

<button class="btn-gray">Toggle</button>

</form>

<form method="POST" style="display:inline">

<input type="hidden" name="action" value="delete_number">
<input type="hidden" name="id" value="<?= $num['id'] ?>">

<button class="btn-red">Hapus</button>

</form>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<div class="card">

<h3>Log Pesan</h3>

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

</div>

</body>
</html>
