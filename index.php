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

if ($action === 'delete_number') {
    $id = (int) ($_POST['id'] ?? 0);

    getDb()->prepare('DELETE FROM numbers WHERE id = ?')->execute([$id]);

    $message = 'Nomor berhasil dihapus.';
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

$numbers = getDb()->query('SELECT * FROM numbers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$logs = getRecentLogs(50);
$gatewayUrl = getSetting('wa_gateway_url', WA_GATEWAY_URL_DEFAULT);
$gatewayKey = getSetting('wa_gateway_key', WA_GATEWAY_KEY_DEFAULT);

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
    if (($log['status'] ?? '') === 'success') {
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
        <form method="POST" class="mb-0">
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-danger logout-btn btn-sm" type="submit">
                <i class="fas fa-sign-out-alt mr-1"></i> Logout
            </button>
        </form>
    </header>

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

    <section class="content-card">
        <div class="card-header">
            <h2 class="card-title">Pengaturan WA Gateway</h2>
            <p class="card-subtitle">Konfigurasi endpoint dan key gateway disimpan ke database (`settings`).</p>
        </div>
        <div class="card-body">
            <form method="POST" class="form-row align-items-end" autocomplete="off">
                <input type="hidden" name="action" value="save_gateway_settings">

                <div class="col-md-5">
                    <label for="wa_gateway_url" class="mb-1">WA Gateway URL</label>
                    <input id="wa_gateway_url" name="wa_gateway_url" class="form-control" placeholder="https://domain/wa" value="<?= h($gatewayUrl) ?>" required>
                </div>

                <div class="col-md-5">
                    <label for="wa_gateway_key" class="mb-1">WA Gateway Key</label>
                    <input id="wa_gateway_key" type="password" name="wa_gateway_key" class="form-control" placeholder="Masukkan API key" value="<?= h($gatewayKey) ?>" required>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="content-card">
        <div class="card-header">
            <h2 class="card-title">Tambah Nomor</h2>
            <p class="card-subtitle">Isi data nomor baru. Token boleh dikosongkan jika memakai token default dari konfigurasi.</p>
        </div>
        <div class="card-body">
            <form method="POST" class="form-row align-items-end" autocomplete="off">
                <input type="hidden" name="action" value="add_number">

                <div class="col-md-3">
                    <label for="phone" class="mb-1">Nomor</label>
                    <input id="phone" name="phone" class="form-control js-phone-input" placeholder="628xxxx" minlength="8" maxlength="20" required>
                </div>

                <div class="col-md-3">
                    <label for="name" class="mb-1">Nama</label>
                    <input id="name" name="name" class="form-control" placeholder="Nama kontak" required>
                </div>

                <div class="col-md-4">
                    <label for="token" class="mb-1">Token (Opsional)</label>
                    <input id="token" name="token" class="form-control" placeholder="Token khusus nomor ini">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </button>
                </div>
            </form>
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
                <input type="search" class="form-control js-filter" data-target="logs-table" placeholder="Cari nomor / status...">
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
                            <?php if (($log['status'] ?? '') === 'success') { ?>
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
        var phoneInput = document.querySelector('.js-phone-input');

        if (phoneInput) {
            phoneInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D+/g, '');
            });
        }

        document.querySelectorAll('.js-confirm-delete').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var name = this.getAttribute('data-name') || 'nomor ini';

                if (!window.confirm('Hapus ' + name + '? Tindakan ini tidak dapat dibatalkan.')) {
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
    })();
</script>
</body>
</html>
