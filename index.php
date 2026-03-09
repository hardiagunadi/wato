<?php

session_start();

define('AUTH_USER','wato');
define('AUTH_PASS','wato');

if(isset($_POST['action']) && $_POST['action']=='login'){
    if($_POST['username']==AUTH_USER && $_POST['password']==AUTH_PASS){
        $_SESSION['logged_in']=true;
    }else{
        $loginError="Username atau password salah";
    }
}

if(isset($_POST['action']) && $_POST['action']=='logout'){
    session_destroy();
    header("Location: /");
    exit;
}

if(empty($_SESSION['logged_in'])){
?>
<!DOCTYPE html>
<html>
<head>

<title>WATO Login</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

</head>

<body class="login-page">

<div class="login-box">

<div class="card">

<div class="card-body login-card-body">

<p class="login-box-msg">WATO Login</p>

<form method="POST">

<input type="hidden" name="action" value="login">

<div class="input-group mb-3">
<input name="username" class="form-control" placeholder="Username">
</div>

<div class="input-group mb-3">
<input type="password" name="password" class="form-control" placeholder="Password">
</div>

<button class="btn btn-primary btn-block">Login</button>

</form>

</div>

</div>

</div>

</body>
</html>
<?php
exit;
}

require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';

function getNumberHealth($phone){

$db=getDb();

$stmt=$db->prepare("
SELECT COUNT(*) as failed
FROM message_log
WHERE from_phone=?
AND status='failed'
AND sent_at>=datetime('now','-1 hour')
");

$stmt->execute([$phone]);

$row=$stmt->fetch(PDO::FETCH_ASSOC);

if($row['failed']>=5) return "paused";
if($row['failed']>=2) return "warning";

return "healthy";
}

$action=$_POST['action']??'';
$message="";

if($action=='add_number'){

$phone=preg_replace('/\D/','',$_POST['phone']);
$name=$_POST['name'];
$token=$_POST['token'];

$db=getDb();

$stmt=$db->prepare("
INSERT OR IGNORE INTO numbers(phone,name,token)
VALUES(?,?,?)
");

$stmt->execute([$phone,$name,$token?:null]);

$message="Nomor berhasil ditambahkan";
}

if($action=='delete_number'){

$id=$_POST['id'];

getDb()->prepare("DELETE FROM numbers WHERE id=?")->execute([$id]);

}

if($action=='toggle_number'){

$id=$_POST['id'];

getDb()->prepare("
UPDATE numbers
SET active=CASE WHEN active=1 THEN 0 ELSE 1 END
WHERE id=?
")->execute([$id]);

}

$numbers=getDb()->query("SELECT * FROM numbers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$logs=getRecentLogs(50);

?>

<!DOCTYPE html>
<html>

<head>

<title>WATO Dashboard</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">

</head>

<body class="hold-transition sidebar-mini">

<div class="wrapper">

<nav class="main-header navbar navbar-expand navbar-white navbar-light">

<ul class="navbar-nav">
<li class="nav-item">
<a class="nav-link"><b>WATO Dashboard</b></a>
</li>
</ul>

<form method="POST" class="ml-auto">
<input type="hidden" name="action" value="logout">
<button class="btn btn-danger btn-sm">Logout</button>
</form>

</nav>

<div class="content-wrapper">

<section class="content pt-3">

<div class="container-fluid">

<?php if($message){ ?>

<div class="alert alert-success">
<?=htmlspecialchars($message)?>
</div>

<?php } ?>

<div class="card">

<div class="card-header">

<h3 class="card-title">Tambah Nomor</h3>

</div>

<div class="card-body">

<form method="POST">

<input type="hidden" name="action" value="add_number">

<div class="row">

<div class="col">
<input name="phone" class="form-control" placeholder="628xxxx">
</div>

<div class="col">
<input name="name" class="form-control" placeholder="Nama">
</div>

<div class="col">
<input name="token" class="form-control" placeholder="Token">
</div>

<div class="col">
<button class="btn btn-primary">Tambah</button>
</div>

</div>

</form>

</div>

</div>

<div class="card">

<div class="card-header">

<h3 class="card-title">Daftar Nomor</h3>

</div>

<div class="card-body table-responsive">

<table class="table table-bordered">

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

<?php foreach($numbers as $num): ?>

<?php $health=getNumberHealth($num['phone']); ?>

<tr>

<td><?=$num['phone']?></td>

<td><?=$num['name']?></td>

<td>
<?php if($num['active']){ ?>
<span class="badge badge-success">Aktif</span>
<?php }else{ ?>
<span class="badge badge-danger">Nonaktif</span>
<?php } ?>
</td>

<td>

<?php if($health=="healthy"){ ?>

<span class="badge badge-success">Healthy</span>

<?php }elseif($health=="warning"){ ?>

<span class="badge badge-warning">Warning</span>

<?php }else{ ?>

<span class="badge badge-danger">Paused</span>

<?php } ?>

</td>

<td>

<form method="POST" style="display:inline">

<input type="hidden" name="action" value="toggle_number">
<input type="hidden" name="id" value="<?=$num['id']?>">

<button class="btn btn-secondary btn-sm">Toggle</button>

</form>

<form method="POST" style="display:inline">

<input type="hidden" name="action" value="delete_number">
<input type="hidden" name="id" value="<?=$num['id']?>">

<button class="btn btn-danger btn-sm">Hapus</button>

</form>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<div class="card">

<div class="card-header">

<h3 class="card-title">Log Pesan</h3>

</div>

<div class="card-body table-responsive">

<table class="table table-striped">

<tr>
<th>Waktu</th>
<th>Dari</th>
<th>Ke</th>
<th>Status</th>
</tr>

<?php foreach($logs as $log): ?>

<tr>

<td><?=$log['sent_at']?></td>
<td><?=$log['from_phone']?></td>
<td><?=$log['to_phone']?></td>
<td><?=$log['status']?></td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

</div>

</section>

</div>

</div>

</body>

</html>
