<?php
session_start();

$host = getenv('DATABASE_HOST') ?: 'db';
$db   = getenv('DATABASE_NAME') ?: 'app_db';
$user = getenv('DATABASE_USER') ?: 'app_user';
$pass = getenv('DATABASE_PASS') ?: 'apppassword123';

function getPDO($host, $db, $user, $pass): PDO {
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';

if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        $pdo  = getPDO($host, $db, $user, $pass);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user'] = $row['username'];
            header('Location: ?action=dashboard');
            exit;
        }
        $message = 'Username atau password salah.';
    } catch (Exception $e) {
        $message = 'Koneksi database gagal: ' . $e->getMessage();
    }
}

if ($action === 'logout') {
    session_destroy();
    header('Location: /app/');
    exit;
}

if ($action === 'dashboard' && empty($_SESSION['user'])) {
    header('Location: /app/');
    exit;
}

$page        = ($action === 'dashboard' && !empty($_SESSION['user'])) ? 'dashboard' : 'login';
$currentUser = $_SESSION['user'] ?? '';

$stats = ['users' => 0, 'categories' => 0, 'products' => 0];
$recentProducts = [];
if ($page === 'dashboard') {
    try {
        $pdo = getPDO($host, $db, $user, $pass);
        $stats['users']      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['categories'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $stats['products']   = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $recentProducts = $pdo->query(
            "SELECT p.name, p.price, c.name AS category, p.created_at
             FROM products p JOIN categories c ON p.category_id = c.id
             ORDER BY p.created_at DESC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $page === 'login' ? 'Login' : 'Dashboard — ' . htmlspecialchars($currentUser) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0d0f14; --surface: #161a22; --border: #252b38;
      --text: #e8eaf0; --muted: #5a6378;
      --green: #00e5a0; --blue: #4d9fff; --red: #ff5c5c; --yellow: #ffd166;
    }
    body { font-family: 'Syne', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
    .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: radial-gradient(ellipse 80% 60% at 50% 0%, #1a2540 0%, var(--bg) 70%); }
    .login-card { width: 100%; max-width: 420px; border: 1px solid var(--border); background: var(--surface); padding: 2.8rem; }
    .login-logo { font-size: 2rem; font-weight: 800; margin-bottom: .4rem; }
    .login-logo span { color: var(--green); }
    .login-sub { font-size: .85rem; color: var(--muted); margin-bottom: 2rem; font-family: 'IBM Plex Mono', monospace; }
    .field { margin-bottom: 1.2rem; }
    .field label { display: block; font-size: .78rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: .5rem; }
    .field input { width: 100%; padding: .75rem 1rem; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-family: 'IBM Plex Mono', monospace; font-size: .92rem; outline: none; transition: border-color .2s; }
    .field input:focus { border-color: var(--green); }
    .btn { width: 100%; padding: .85rem; background: var(--green); color: #0d0f14; font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 700; border: none; cursor: pointer; }
    .btn:hover { opacity: .85; }
    .error-msg { margin-top: 1rem; font-size: .84rem; color: var(--red); font-family: 'IBM Plex Mono', monospace; }
    .demo-hint { margin-top: 1.4rem; font-size: .78rem; color: var(--muted); font-family: 'IBM Plex Mono', monospace; border-top: 1px solid var(--border); padding-top: 1rem; }
    .dash-layout { display: flex; min-height: 100vh; }
    .sidebar { width: 220px; border-right: 1px solid var(--border); padding: 2rem 1.5rem; display: flex; flex-direction: column; background: var(--surface); }
    .sidebar-logo { font-size: 1.3rem; font-weight: 800; margin-bottom: 2.5rem; }
    .sidebar-logo span { color: var(--green); }
    .nav-item { display: flex; align-items: center; gap: .7rem; padding: .6rem .8rem; font-size: .88rem; color: var(--muted); margin-bottom: .3rem; }
    .nav-item.active { background: var(--border); color: var(--text); }
    .sidebar-footer { margin-top: auto; }
    .avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--green); color: #0d0f14; display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; }
    .user-badge { display: flex; align-items: center; gap: .6rem; padding: .6rem .8rem; border: 1px solid var(--border); }
    .user-name { font-size: .82rem; }
    .logout-btn { display: block; width: 100%; margin-top: .7rem; padding: .5rem; background: transparent; border: 1px solid var(--border); color: var(--muted); font-family: 'Syne', sans-serif; font-size: .8rem; cursor: pointer; }
    .logout-btn:hover { border-color: var(--red); color: var(--red); }
    .main { flex: 1; padding: 2.5rem; }
    .page-title { font-size: 1.5rem; font-weight: 800; margin-bottom: .4rem; }
    .page-sub { font-size: .82rem; color: var(--muted); font-family: 'IBM Plex Mono', monospace; margin-bottom: 2rem; }
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; margin-bottom: 2.5rem; }
    .stat-card { border: 1px solid var(--border); background: var(--surface); padding: 1.4rem 1.6rem; }
    .stat-label { font-size: .72rem; letter-spacing: .15em; text-transform: uppercase; color: var(--muted); margin-bottom: .6rem; }
    .stat-value { font-size: 2.4rem; font-weight: 800; line-height: 1; }
    .stat-value.green { color: var(--green); }
    .stat-value.blue  { color: var(--blue); }
    .stat-value.yellow{ color: var(--yellow); }
    .table-card { border: 1px solid var(--border); background: var(--surface); }
    .table-header { padding: 1.2rem 1.6rem; border-bottom: 1px solid var(--border); font-size: .88rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
    .badge { font-family: 'IBM Plex Mono', monospace; font-size: .72rem; padding: .25rem .6rem; border: 1px solid var(--green); color: var(--green); }
    table { width: 100%; border-collapse: collapse; }
    th { padding: .8rem 1.6rem; text-align: left; font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
    td { padding: .9rem 1.6rem; font-size: .88rem; border-bottom: 1px solid #1c2130; }
    tr:last-child td { border-bottom: none; }
    .price-col { font-family: 'IBM Plex Mono', monospace; color: var(--green); }
    .cat-tag { display: inline-block; padding: .15rem .55rem; border: 1px solid var(--border); font-size: .72rem; color: var(--muted); }
  </style>
</head>
<body>
<?php if ($page === 'login'): ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">Cloud<span>App</span></div>
    <div class="login-sub">// secure admin panel v1.0</div>
    <form method="POST" action="?action=login">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" placeholder="admin" required/>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required/>
      </div>
      <button type="submit" class="btn">MASUK →</button>
      <?php if ($message): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
    </form>
    <div class="demo-hint">Demo → username: <strong>admin</strong> / password: <strong>admin123</strong></div>
  </div>
</div>
<?php else: ?>
<div class="dash-layout">
  <nav class="sidebar">
    <div class="sidebar-logo">Cloud<span>App</span></div>
    <div class="nav-item active">◈ Dashboard</div>
    <div class="nav-item">◉ Produk</div>
    <div class="nav-item">◎ Kategori</div>
    <div class="nav-item">◌ Pengguna</div>
    <div class="sidebar-footer">
      <div class="user-badge">
        <div class="avatar"><?= strtoupper(substr($currentUser, 0, 1)) ?></div>
        <span class="user-name"><?= htmlspecialchars($currentUser) ?></span>
      </div>
      <form method="POST" action="?action=logout">
        <button class="logout-btn" type="submit">Logout ×</button>
      </form>
    </div>
  </nav>
  <main class="main">
    <div class="page-title">Dashboard</div>
    <div class="page-sub">Selamat datang, <?= htmlspecialchars($currentUser) ?> — <?= date('l, d F Y') ?></div>
    <?php if ($message): ?>
      <p style="color:var(--red);font-size:.85rem;margin-bottom:1.5rem;"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <div class="stats-row">
      <div class="stat-card"><div class="stat-label">Total Pengguna</div><div class="stat-value green"><?= $stats['users'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Kategori</div><div class="stat-value blue"><?= $stats['categories'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Produk</div><div class="stat-value yellow"><?= $stats['products'] ?></div></div>
    </div>
    <div class="table-card">
      <div class="table-header">Produk Terbaru <span class="badge">LIVE DATA</span></div>
      <table>
        <thead><tr><th>Nama Produk</th><th>Kategori</th><th>Harga</th><th>Ditambahkan</th></tr></thead>
        <tbody>
          <?php if (!empty($recentProducts)): ?>
            <?php foreach ($recentProducts as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><span class="cat-tag"><?= htmlspecialchars($row['category']) ?></span></td>
              <td class="price-col">Rp <?= number_format($row['price'], 0, ',', '.') ?></td>
              <td style="color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.8rem"><?= $row['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" style="color:var(--muted);text-align:center;padding:2rem;">Belum ada data.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<?php endif; ?>
</body>
</html>
 
