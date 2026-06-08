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

// ── Auth ──────────────────────────────────────────────────────────────────
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

if (!in_array($action, ['', 'login']) && empty($_SESSION['user'])) {
    header('Location: /app/');
    exit;
}

$pdo = null;
if (!empty($_SESSION['user'])) {
    try { $pdo = getPDO($host, $db, $user, $pass); } catch (Exception $e) { $message = $e->getMessage(); }
}

// ── CRUD Produk ───────────────────────────────────────────────────────────
if ($pdo) {
    if ($action === 'add_product') {
        $stmt = $pdo->prepare("INSERT INTO products (name, price, category_id, stock) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['name']),
            (float)$_POST['price'],
            (int)$_POST['category_id'],
            (int)$_POST['stock']
        ]);
        header('Location: ?action=dashboard&msg=added');
        exit;
    }

    if ($action === 'edit_product') {
        $stmt = $pdo->prepare("UPDATE products SET name=?, price=?, category_id=?, stock=? WHERE id=?");
        $stmt->execute([
            trim($_POST['name']),
            (float)$_POST['price'],
            (int)$_POST['category_id'],
            (int)$_POST['stock'],
            (int)$_POST['id']
        ]);
        header('Location: ?action=dashboard&msg=updated');
        exit;
    }

    if ($action === 'delete_product') {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
        header('Location: ?action=dashboard&msg=deleted');
        exit;
    }
}

// ── Data untuk dashboard ──────────────────────────────────────────────────
$page        = (!empty($_SESSION['user'])) ? 'dashboard' : 'login';
$currentUser = $_SESSION['user'] ?? '';
$stats       = ['users' => 0, 'categories' => 0, 'products' => 0];
$products    = [];
$categories  = [];
$editProduct = null;

if ($page === 'dashboard' && $pdo) {
    try {
        $stats['users']      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['categories'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $stats['products']   = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $products   = $pdo->query(
            "SELECT p.*, c.name AS category FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             ORDER BY p.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Edit mode
        if (isset($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
            $stmt->execute([(int)$_GET['edit']]);
            $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $msgMap = ['added' => '✅ Produk berhasil ditambahkan!', 'updated' => '✅ Produk berhasil diupdate!', 'deleted' => '✅ Produk berhasil dihapus!'];
        if (isset($_GET['msg'], $msgMap[$_GET['msg']])) $message = $msgMap[$_GET['msg']];
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
  <title><?= $page === 'login' ? 'Login' : 'Dashboard' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --bg:#0d0f14; --surface:#161a22; --border:#252b38; --text:#e8eaf0; --muted:#5a6378; --green:#00e5a0; --blue:#4d9fff; --red:#ff5c5c; --yellow:#ffd166; }
    body { font-family:'Syne',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

    /* LOGIN */
    .login-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; background:radial-gradient(ellipse 80% 60% at 50% 0%,#1a2540,var(--bg)); }
    .login-card { width:100%; max-width:420px; border:1px solid var(--border); background:var(--surface); padding:2.8rem; }
    .login-logo { font-size:2rem; font-weight:800; margin-bottom:.4rem; }
    .login-logo span { color:var(--green); }
    .login-sub { font-size:.85rem; color:var(--muted); margin-bottom:2rem; font-family:'IBM Plex Mono',monospace; }
    .field { margin-bottom:1.2rem; }
    .field label { display:block; font-size:.78rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin-bottom:.5rem; }
    .field input, .field select { width:100%; padding:.75rem 1rem; background:var(--bg); border:1px solid var(--border); color:var(--text); font-family:'IBM Plex Mono',monospace; font-size:.92rem; outline:none; transition:border-color .2s; }
    .field input:focus, .field select:focus { border-color:var(--green); }
    .field select option { background:var(--surface); }
    .btn { padding:.75rem 1.4rem; font-family:'Syne',sans-serif; font-size:.9rem; font-weight:700; border:none; cursor:pointer; transition:opacity .2s; }
    .btn-green { background:var(--green); color:#0d0f14; }
    .btn-blue  { background:var(--blue);  color:#0d0f14; }
    .btn-red   { background:var(--red);   color:#fff; }
    .btn-ghost { background:transparent; border:1px solid var(--border); color:var(--muted); }
    .btn:hover { opacity:.85; }
    .btn-full  { width:100%; }
    .error-msg  { margin-top:1rem; font-size:.84rem; color:var(--red); font-family:'IBM Plex Mono',monospace; }
    .success-msg{ margin-top:1rem; font-size:.84rem; color:var(--green); font-family:'IBM Plex Mono',monospace; }
    .demo-hint  { margin-top:1.4rem; font-size:.78rem; color:var(--muted); font-family:'IBM Plex Mono',monospace; border-top:1px solid var(--border); padding-top:1rem; }

    /* LAYOUT */
    .dash-layout { display:flex; min-height:100vh; }
    .sidebar { width:220px; border-right:1px solid var(--border); padding:2rem 1.5rem; display:flex; flex-direction:column; background:var(--surface); }
    .sidebar-logo { font-size:1.3rem; font-weight:800; margin-bottom:2.5rem; }
    .sidebar-logo span { color:var(--green); }
    .nav-item { display:flex; align-items:center; gap:.7rem; padding:.6rem .8rem; font-size:.88rem; color:var(--muted); margin-bottom:.3rem; border-radius:4px; }
    .nav-item.active { background:var(--border); color:var(--text); }
    .sidebar-footer { margin-top:auto; }
    .user-badge { display:flex; align-items:center; gap:.6rem; padding:.6rem .8rem; border:1px solid var(--border); }
    .avatar { width:28px; height:28px; border-radius:50%; background:var(--green); color:#0d0f14; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; }
    .logout-btn { display:block; width:100%; margin-top:.7rem; padding:.5rem; background:transparent; border:1px solid var(--border); color:var(--muted); font-family:'Syne',sans-serif; font-size:.8rem; cursor:pointer; }
    .logout-btn:hover { border-color:var(--red); color:var(--red); }

    /* MAIN */
    .main { flex:1; padding:2.5rem; overflow-y:auto; }
    .page-title { font-size:1.5rem; font-weight:800; margin-bottom:.3rem; }
    .page-sub { font-size:.82rem; color:var(--muted); font-family:'IBM Plex Mono',monospace; margin-bottom:1.5rem; }

    /* STATS */
    .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:1.2rem; margin-bottom:2rem; }
    .stat-card { border:1px solid var(--border); background:var(--surface); padding:1.2rem 1.4rem; }
    .stat-label { font-size:.72rem; letter-spacing:.15em; text-transform:uppercase; color:var(--muted); margin-bottom:.5rem; }
    .stat-value { font-size:2rem; font-weight:800; }
    .stat-value.green{color:var(--green);} .stat-value.blue{color:var(--blue);} .stat-value.yellow{color:var(--yellow);}

    /* FORM CARD */
    .form-card { border:1px solid var(--border); background:var(--surface); padding:1.6rem; margin-bottom:1.5rem; }
    .form-card h3 { font-size:.95rem; font-weight:700; margin-bottom:1.2rem; padding-bottom:.8rem; border-bottom:1px solid var(--border); }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-actions { display:flex; gap:.8rem; margin-top:1.2rem; }

    /* TABLE */
    .table-card { border:1px solid var(--border); background:var(--surface); }
    .table-header { padding:1rem 1.4rem; border-bottom:1px solid var(--border); font-size:.9rem; font-weight:700; display:flex; justify-content:space-between; align-items:center; }
    .badge { font-family:'IBM Plex Mono',monospace; font-size:.72rem; padding:.25rem .6rem; border:1px solid var(--green); color:var(--green); }
    table { width:100%; border-collapse:collapse; }
    th { padding:.7rem 1.2rem; text-align:left; font-size:.7rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); border-bottom:1px solid var(--border); }
    td { padding:.8rem 1.2rem; font-size:.87rem; border-bottom:1px solid #1c2130; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(255,255,255,.015); }
    .price-col { font-family:'IBM Plex Mono',monospace; color:var(--green); }
    .cat-tag { display:inline-block; padding:.15rem .55rem; border:1px solid var(--border); font-size:.72rem; color:var(--muted); }
    .action-btns { display:flex; gap:.5rem; }
    .btn-sm { padding:.35rem .7rem; font-size:.78rem; }

    .alert { padding:.8rem 1.2rem; margin-bottom:1.2rem; font-size:.85rem; font-family:'IBM Plex Mono',monospace; border-left:3px solid var(--green); background:rgba(0,229,160,.08); color:var(--green); }
    .alert.err { border-color:var(--red); background:rgba(255,92,92,.08); color:var(--red); }
  </style>
</head>
<body>
<?php if ($page === 'login'): ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">Cloud<span>App</span></div>
    <div class="login-sub">// secure admin panel v1.0</div>
    <form method="POST" action="?action=login">
      <div class="field"><label>Username</label><input type="text" name="username" placeholder="admin" required/></div>
      <div class="field"><label>Password</label><input type="password" name="password" placeholder="••••••••" required/></div>
      <button type="submit" class="btn btn-green btn-full">MASUK →</button>
      <?php if ($message): ?><div class="error-msg">⚠ <?= htmlspecialchars($message) ?></div><?php endif; ?>
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
    <div class="sidebar-footer">
      <div class="user-badge">
        <div class="avatar"><?= strtoupper(substr($currentUser,0,1)) ?></div>
        <span style="font-size:.82rem"><?= htmlspecialchars($currentUser) ?></span>
      </div>
      <form method="POST" action="?action=logout">
        <button class="logout-btn" type="submit">Logout ×</button>
      </form>
    </div>
  </nav>

  <main class="main">
    <div class="page-title">Dashboard</div>
    <div class="page-sub">Selamat datang, <?= htmlspecialchars($currentUser) ?> — <?= date('d F Y') ?></div>

    <?php if ($message): ?>
      <div class="alert <?= str_starts_with($message,'✅') ? '' : 'err' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card"><div class="stat-label">Pengguna</div><div class="stat-value green"><?= $stats['users'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Kategori</div><div class="stat-value blue"><?= $stats['categories'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Produk</div><div class="stat-value yellow"><?= $stats['products'] ?></div></div>
    </div>

    <!-- Form Tambah / Edit -->
    <div class="form-card">
      <h3><?= $editProduct ? '✏️ Edit Produk' : '➕ Tambah Produk Baru' ?></h3>
      <form method="POST" action="?action=<?= $editProduct ? 'edit_product' : 'add_product' ?>">
        <?php if ($editProduct): ?>
          <input type="hidden" name="id" value="<?= $editProduct['id'] ?>"/>
        <?php endif; ?>
        <div class="form-grid">
          <div class="field">
            <label>Nama Produk</label>
            <input type="text" name="name" placeholder="Nama produk..." value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" required/>
          </div>
          <div class="field">
            <label>Harga (Rp)</label>
            <input type="number" name="price" placeholder="0" value="<?= $editProduct['price'] ?? '' ?>" required/>
          </div>
          <div class="field">
            <label>Kategori</label>
            <select name="category_id" required>
              <option value="">-- Pilih Kategori --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Stok</label>
            <input type="number" name="stock" placeholder="0" value="<?= $editProduct['stock'] ?? '' ?>" required/>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn <?= $editProduct ? 'btn-blue' : 'btn-green' ?>">
            <?= $editProduct ? '💾 Simpan Perubahan' : '➕ Tambah Produk' ?>
          </button>
          <?php if ($editProduct): ?>
            <a href="?action=dashboard" class="btn btn-ghost">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Tabel Produk -->
    <div class="table-card">
      <div class="table-header">
        Daftar Produk
        <span class="badge"><?= count($products) ?> ITEM</span>
      </div>
      <table>
        <thead>
          <tr><th>#</th><th>Nama Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if (!empty($products)): ?>
            <?php foreach ($products as $i => $p): ?>
            <tr>
              <td style="color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.78rem"><?= $i+1 ?></td>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td><span class="cat-tag"><?= htmlspecialchars($p['category']) ?></span></td>
              <td class="price-col">Rp <?= number_format($p['price'],0,',','.') ?></td>
              <td style="font-family:'IBM Plex Mono',monospace"><?= $p['stock'] ?></td>
              <td>
                <div class="action-btns">
                  <a href="?action=dashboard&edit=<?= $p['id'] ?>" class="btn btn-blue btn-sm">Edit</a>
                  <form method="POST" action="?action=delete_product" onsubmit="return confirm('Hapus produk ini?')">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
                    <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">Belum ada produk.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<?php endif; ?>
</body>
</html>
 
<?php // v2-crud
