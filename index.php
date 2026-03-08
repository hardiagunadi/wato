<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ---- Handle POST actions ----

$action  = $_POST['action'] ?? '';
$message = '';
$msgType = 'success';

if ($action === 'save_settings') {
    setSetting('default_session_id', trim($_POST['default_session_id'] ?? ''));
    setSetting('webhook_url', trim($_POST['webhook_url'] ?? ''));
    $message = 'Pengaturan berhasil disimpan.';
}

if ($action === 'add_number') {
    $phone = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    $name  = trim($_POST['name'] ?? '');
    $sess  = trim($_POST['session_id'] ?? '');

    if ($phone) {
        try {
            $db = getDb();
            $stmt = $db->prepare("INSERT OR IGNORE INTO numbers (phone, name, session_id) VALUES (?, ?, ?)");
            $stmt->execute([$phone, $name, $sess ?: null]);
            $message = "Nomor $phone berhasil ditambahkan.";
        } catch (Exception $e) {
            $message = 'Gagal menambahkan: ' . $e->getMessage();
            $msgType = 'error';
        }
    } else {
        $message = 'Nomor tidak valid.';
        $msgType = 'error';
    }
}

if ($action === 'delete_number') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        getDb()->prepare("DELETE FROM numbers WHERE id = ?")->execute([$id]);
        $message = 'Nomor berhasil dihapus.';
    }
}

if ($action === 'toggle_number') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        getDb()->prepare("UPDATE numbers SET active = CASE WHEN active=1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
        $message = 'Status nomor diperbarui.';
    }
}

if ($action === 'send_now') {
    ob_start();
    passthru('php ' . escapeshellarg(__DIR__ . '/send.php') . ' 2>&1');
    $output  = ob_get_clean();
    $message = "Pengiriman selesai:\n" . htmlspecialchars($output);
}

// ---- Fetch data ----

$numbers = getDb()->query("SELECT * FROM numbers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$logs    = getRecentLogs(50);
$defaultSession = getSetting('default_session_id');
$webhookUrl     = getSetting('webhook_url');

// ---- Gateway status ----
$gatewayOk = false;
$ch = curl_init(WA_GATEWAY_URL . '/health');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$gatewayOk = ($code === 200);

// ---- Available sessions ----
$sessions = [];
$ch = curl_init(WA_GATEWAY_URL . '/api/device/info');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_HTTPHEADER => ['key: ' . WA_GATEWAY_KEY],
]);
$resp = curl_exec($ch);
curl_close($ch);
$devData = json_decode($resp, true);
if (isset($devData['data']) && is_array($devData['data'])) {
    $sessions = $devData['data'];
} elseif (isset($devData['status']) && is_array($devData['status'])) {
    $sessions = $devData['status'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WATO - WA Auto Text Organizer</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }
header { background: #1e40af; color: #fff; padding: 16px 24px; display: flex; align-items: center; gap: 12px; }
header h1 { font-size: 1.25rem; font-weight: 700; }
header .sub { font-size: 0.8rem; opacity: 0.75; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
.badge.ok { background: #22c55e; color: #fff; }
.badge.err { background: #ef4444; color: #fff; }
main { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 700px) { .grid2 { grid-template-columns: 1fr; } }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 16px; }
.card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 16px; color: #1e40af; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
.alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem; white-space: pre-wrap; }
.alert.success { background: #dcfce7; color: #166534; }
.alert.error { background: #fee2e2; color: #991b1b; }
form .row { margin-bottom: 12px; }
form label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; color: #475569; }
form input, form select { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 0.875rem; }
form input:focus, form select:focus { outline: 2px solid #3b82f6; border-color: transparent; }
.btn { display: inline-block; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.875rem; font-weight: 600; transition: opacity .15s; }
.btn:hover { opacity: 0.85; }
.btn-primary { background: #1e40af; color: #fff; }
.btn-danger  { background: #ef4444; color: #fff; }
.btn-gray    { background: #94a3b8; color: #fff; }
.btn-green   { background: #16a34a; color: #fff; }
.btn-sm { padding: 4px 10px; font-size: 0.75rem; }
table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
th { background: #f8fafc; text-align: left; padding: 8px 10px; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
tr:hover td { background: #f8fafc; }
.dir-out { color: #1e40af; font-weight: 600; }
.dir-in  { color: #059669; font-weight: 600; }
.status-ok   { color: #16a34a; }
.status-fail { color: #dc2626; }
.pill { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
.pill-active   { background: #dcfce7; color: #166534; }
.pill-inactive { background: #fee2e2; color: #991b1b; }
.mono { font-family: monospace; font-size: 0.8rem; }
</style>
</head>
<body>

<header>
  <div>
    <h1>WATO &mdash; WA Auto Text Organizer</h1>
    <div class="sub">Kirim pesan acak antar nomor terdaftar setiap 1 jam</div>
  </div>
  <div style="margin-left:auto; display:flex; align-items:center; gap:8px;">
    <span>Gateway:</span>
    <span class="badge <?= $gatewayOk ? 'ok' : 'err' ?>"><?= $gatewayOk ? 'Online' : 'Offline' ?></span>
  </div>
</header>

<main>

<?php if ($message): ?>
<div class="alert <?= $msgType ?>"><?= $message ?></div>
<?php endif; ?>

<!-- Row 1: Settings + Send Now -->
<div class="grid2">

  <!-- Settings -->
  <div class="card">
    <h2>Pengaturan</h2>
    <form method="POST">
      <input type="hidden" name="action" value="save_settings">
      <div class="row">
        <label>Session Aktif (Default)</label>
        <?php if ($sessions): ?>
        <select name="default_session_id">
          <option value="">-- Pilih session --</option>
          <?php foreach ($sessions as $s):
            $sid = $s['sessionId'] ?? $s['id'] ?? $s['session_id'] ?? '';
            $status = $s['status'] ?? '';
          ?>
          <option value="<?= htmlspecialchars($sid) ?>" <?= $defaultSession === $sid ? 'selected' : '' ?>>
            <?= htmlspecialchars($sid) ?> (<?= htmlspecialchars($status) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="text" name="default_session_id" value="<?= htmlspecialchars($defaultSession) ?>" placeholder="Contoh: my-session">
        <small style="color:#64748b; font-size:0.75rem;">Isi manual karena gateway tidak bisa diakses atau belum ada session.</small>
        <?php endif; ?>
      </div>
      <div class="row">
        <label>Webhook URL (URL publik ke webhook.php)</label>
        <input type="text" name="webhook_url" value="<?= htmlspecialchars($webhookUrl) ?>" placeholder="https://domain.com/wato/webhook.php">
      </div>
      <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </form>
  </div>

  <!-- Kirim Sekarang -->
  <div class="card">
    <h2>Aksi Manual</h2>
    <p style="font-size:0.875rem; color:#475569; margin-bottom:16px;">
      Trigger pengiriman pesan sekarang (tidak menunggu jadwal cron). Setiap nomor aktif akan mengirim pesan ke semua nomor lainnya.
    </p>
    <form method="POST" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Mengirim...'">
      <input type="hidden" name="action" value="send_now">
      <button type="submit" class="btn btn-green">Kirim Sekarang</button>
    </form>
    <hr style="margin:16px 0; border:none; border-top:1px solid #e2e8f0;">
    <p style="font-size:0.8rem; color:#475569;">
      <strong>Cron job:</strong><br>
      <code class="mono">0 * * * * www-data php <?= __DIR__ ?>/send.php >> <?= __DIR__ ?>/cron.log 2&gt;&amp;1</code>
    </p>
  </div>

</div>

<!-- Daftar Nomor -->
<div class="card">
  <h2>Daftar Nomor</h2>
  <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-start;">

    <!-- Tabel nomor -->
    <div style="flex:1; min-width:300px;">
      <?php if ($numbers): ?>
      <table>
        <thead>
          <tr><th>Nomor</th><th>Nama</th><th>Session</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($numbers as $num): ?>
        <tr>
          <td class="mono"><?= htmlspecialchars($num['phone']) ?></td>
          <td><?= htmlspecialchars($num['name'] ?? '-') ?></td>
          <td class="mono" style="font-size:0.7rem;"><?= htmlspecialchars($num['session_id'] ?? 'default') ?></td>
          <td>
            <span class="pill <?= $num['active'] ? 'pill-active' : 'pill-inactive' ?>">
              <?= $num['active'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
          </td>
          <td style="display:flex; gap:4px;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle_number">
              <input type="hidden" name="id" value="<?= $num['id'] ?>">
              <button class="btn btn-gray btn-sm"><?= $num['active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus nomor ini?')">
              <input type="hidden" name="action" value="delete_number">
              <input type="hidden" name="id" value="<?= $num['id'] ?>">
              <button class="btn btn-danger btn-sm">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="color:#64748b; font-size:0.875rem;">Belum ada nomor terdaftar.</p>
      <?php endif; ?>
    </div>

    <!-- Form tambah -->
    <div style="width:260px;">
      <p style="font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:8px;">Tambah Nomor</p>
      <form method="POST">
        <input type="hidden" name="action" value="add_number">
        <div class="row">
          <label>Nomor WA (628xxx)</label>
          <input type="text" name="phone" placeholder="628123456789" required>
        </div>
        <div class="row">
          <label>Nama (opsional)</label>
          <input type="text" name="name" placeholder="Nama kontak">
        </div>
        <div class="row">
          <label>Session ID (kosong = pakai default)</label>
          <input type="text" name="session_id" placeholder="<?= htmlspecialchars($defaultSession ?: 'default') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Tambah</button>
      </form>
    </div>

  </div>
</div>

<!-- Log Pesan -->
<div class="card">
  <h2>Log Pesan (50 terbaru)</h2>
  <?php if ($logs): ?>
  <div style="overflow-x:auto;">
  <table>
    <thead>
      <tr><th>Waktu</th><th>Arah</th><th>Dari</th><th>Ke</th><th>Pesan</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <tr>
      <td class="mono" style="white-space:nowrap;"><?= htmlspecialchars($log['sent_at']) ?></td>
      <td class="<?= $log['direction'] === 'out' ? 'dir-out' : 'dir-in' ?>">
        <?= $log['direction'] === 'out' ? '↑ OUT' : '↓ IN' ?>
      </td>
      <td class="mono"><?= htmlspecialchars($log['from_phone']) ?></td>
      <td class="mono"><?= htmlspecialchars($log['to_phone']) ?></td>
      <td style="max-width:300px; word-break:break-word;"><?= htmlspecialchars(mb_strimwidth($log['message'], 0, 100, '…')) ?></td>
      <td class="<?= $log['status'] === 'sent' ? 'status-ok' : 'status-fail' ?>">
        <?= htmlspecialchars($log['status']) ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p style="color:#64748b; font-size:0.875rem;">Belum ada log pesan.</p>
  <?php endif; ?>
</div>

</main>
</body>
</html>
