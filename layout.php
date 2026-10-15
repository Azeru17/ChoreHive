<?php
require_once 'config.php';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$u = current_user();
$base = ch_base();
if (($u['role'] ?? '') === 'worker') {
  $presenceStmt = $conn->prepare('INSERT INTO worker_presence (worker_id,last_seen) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE last_seen=NOW()');
  $presenceStmt->bind_param('i', $u['id']); $presenceStmt->execute();
} // app root — works from every subfolder
$notif_count = 0;
if ($u) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->bind_param('i', $u['id']);
    $stmt->execute();
    $notif_count = (int)$stmt->get_result()->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($title) ? e($title) . ' — ChoreHive' : 'ChoreHive' ?></title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/favicon.png">
<link rel="apple-touch-icon" href="<?= $base ?>/assets/logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php if ($u && $u['role']==='admin'): ?>
<?php endif; ?>
<style>
  :root {
    /* Brand */
    --navy:#17427a; --navy-dark:#0f2d55;
    --honey:#e0aa33; --honey-dark:#c1861a; --dark:#17427a;
    /* Surfaces & text */
    --bg:#f4f7fc; --card:#fff; --ink:#1f2c44; --muted:#6b7a90; --line:#e3e9f2; --line-2:#edf1f8;
    /* Status */
    --ok:#2e7d32; --warn:#b7791f; --err:#c62828; --info:#1565c0;
    --ok-bg:#e8f5e9; --warn-bg:#fdf3e3; --err-bg:#fdecea; --info-bg:#e8f0fd;
    /* Shape & depth */
    --radius:16px; --radius-sm:10px; --radius-pill:999px;
    --shadow-sm:0 1px 2px rgba(23,66,122,.06), 0 1px 3px rgba(23,66,122,.04);
    --shadow:0 1px 2px rgba(23,66,122,.05), 0 6px 20px rgba(23,66,122,.07);
    --shadow-lg:0 12px 38px rgba(23,66,122,.15);
    --ring:0 0 0 3px rgba(224,170,51,.35);
    /* Motion */
    --ease:cubic-bezier(.4,0,.2,1);
    --fast:.15s; --med:.25s; --slow:.45s;
  }
  * ,*::before,*::after { box-sizing:border-box; }
  body,h1,h2,h3,h4,h5,p,ul,ol,figure { margin:0; }
  ul,ol { padding-left:1.2rem; }
  html { -webkit-text-size-adjust:100%; scroll-behavior:smooth; }
  body {
    font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
    background:var(--bg); color:var(--ink); min-height:100vh;
    line-height:1.55; letter-spacing:-.005em; -webkit-font-smoothing:antialiased;
  }

  /* Focus, selection & scrollbars */
  :focus-visible { outline:2px solid var(--navy); outline-offset:2px; border-radius:6px; }
  button:focus-visible, .btn:focus-visible { outline:none; box-shadow:var(--ring); }
  ::selection { background:rgba(224,170,51,.3); }
  ::-webkit-scrollbar { width:11px; height:11px; }
  ::-webkit-scrollbar-track { background:transparent; }
  ::-webkit-scrollbar-thumb { background:#c9d5e6; border-radius:99px; border:3px solid transparent; background-clip:content-box; }
  ::-webkit-scrollbar-thumb:hover { background:#aebbcf; background-clip:content-box; }

  @keyframes pop-in { from{opacity:0;transform:translateY(-8px) scale(.985)} to{opacity:1;transform:none} }
  @keyframes fade-in { from{opacity:0} to{opacity:1} }
  @keyframes rise { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
  @keyframes grow-x { from{transform:scaleX(0);transform-origin:left center} to{transform:scaleX(1)} }

  /* ---------- Header ---------- */
  header {
    background:rgba(255,255,255,.85); -webkit-backdrop-filter:saturate(180%) blur(14px); backdrop-filter:saturate(180%) blur(14px);
    color:var(--ink); padding:0 20px; display:flex; align-items:center; gap:8px; min-height:60px;
    position:sticky; top:0; z-index:50; border-bottom:1px solid var(--line-2);
  }
  .brand {
    font-weight:800; font-size:1.2rem; color:var(--navy); text-decoration:none;
    display:flex; align-items:center; gap:10px; padding:8px 0; letter-spacing:-.012em;
  }
  .brand:hover { color:var(--navy-dark); }
  .brand:hover img { transform:rotate(-7deg) scale(1.06); }
  .brand img {
    width:36px; height:36px; border-radius:50%; display:block; flex-shrink:0;
    box-shadow:0 0 0 2px rgba(224,170,51,.5), 0 2px 8px rgba(23,66,122,.16);
    transition:transform var(--med) var(--ease), box-shadow var(--med) var(--ease);
  }
  nav.main { display:flex; align-items:center; gap:4px; margin-left:14px; }
  nav.main a {
    color:#4a5a73; text-decoration:none; padding:8px 13px; border-radius:var(--radius-pill);
    font-size:.93rem; font-weight:500; white-space:nowrap;
    transition:background var(--fast) var(--ease), color var(--fast) var(--ease), box-shadow var(--fast) var(--ease);
  }
  nav.main a:hover { background:#eef3fb; color:var(--navy); }
  nav.main a.active { background:var(--honey); color:#2a2110; font-weight:600; box-shadow:0 2px 10px rgba(224,170,51,.35); }
  .userbox { margin-left:auto; display:flex; align-items:center; gap:12px; font-size:.9rem; }
  .userbox .who { color:var(--navy); white-space:nowrap; display:flex; align-items:center; gap:9px; text-decoration:none; font-weight:600; }
  .userbox .who:hover { color:var(--honey-dark); }
  .userbox a { color:var(--navy); text-decoration:none; font-weight:500; transition:color var(--fast) var(--ease); }
  .userbox a:hover { color:var(--honey-dark); }
  .bell { position:relative; text-decoration:none; font-size:1.1rem; padding:7px 10px; border-radius:var(--radius-pill); display:inline-flex; transition:background var(--fast) var(--ease), transform var(--fast) var(--ease); }
  .bell:hover { background:#eef3fb; }
  .bell:active { transform:scale(.94); }
  .bell .count {
    position:absolute; top:2px; right:2px; background:var(--err); color:#fff; border-radius:99px;
    font-size:.62rem; font-weight:700; min-width:17px; height:17px; line-height:17px; text-align:center;
    padding:0 4px; border:2px solid #fff;
  }
  .burger { display:none; background:none; border:0; color:var(--navy); font-size:1.5rem; cursor:pointer; padding:8px 10px; border-radius:var(--radius-sm); transition:background var(--fast) var(--ease); }
  .burger:hover { background:#eef3fb; }

  /* ---------- Layout ---------- */
  .container { max-width:1080px; margin:28px auto; padding:0 20px; }
  .card {
    background:var(--card); border:1px solid var(--line-2); border-radius:var(--radius);
    padding:24px; margin-bottom:18px; box-shadow:var(--shadow);
    transition:box-shadow var(--med) var(--ease), border-color var(--med) var(--ease);
  }
  h1 { font-size:1.6rem; margin-bottom:14px; letter-spacing:-.022em; line-height:1.25; }
  h2 { font-size:1.05rem; color:var(--navy); margin-bottom:12px; letter-spacing:-.01em; }

  /* ---------- Forms ---------- */
  input,select,textarea {
    width:100%; padding:11px 13px; margin:6px 0 14px;
    border:1.5px solid #dbe3f0; border-radius:var(--radius-sm);
    font:inherit; color:var(--ink); background:#fff;
    transition:border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease);
  }
  input::placeholder, textarea::placeholder { color:#9aa8bd; }
  input:hover,select:hover,textarea:hover { border-color:#c6d2e6; }
  input:focus,select:focus,textarea:focus {
    outline:none; border-color:var(--honey); box-shadow:0 0 0 4px rgba(224,170,51,.16); background:#fff;
  }
  select {
    appearance:none; -webkit-appearance:none; cursor:pointer; padding-right:38px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7a90' d='M1.4 0 6 4.6 10.6 0 12 1.4 6 7.4 0 1.4z'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 14px center;
  }

  /* ---------- Buttons ---------- */
  button,.btn {
    background:linear-gradient(180deg,#e7b23d,var(--honey));
    color:#2a2110; border:0; padding:10px 18px; border-radius:var(--radius-sm);
    cursor:pointer; text-decoration:none; display:inline-block; font:inherit; font-weight:600;
    box-shadow:0 1px 2px rgba(23,66,122,.1);
    transition:transform var(--fast) var(--ease), box-shadow var(--med) var(--ease), background var(--fast) var(--ease);
  }
  button:hover,.btn:hover { transform:translateY(-1px); box-shadow:0 8px 20px rgba(224,170,51,.4); }
  button:active,.btn:active { transform:translateY(0) scale(.985); box-shadow:0 1px 2px rgba(23,66,122,.1); }
  button:disabled { opacity:.55; cursor:not-allowed; transform:none; box-shadow:none; }
  .btn-sm { padding:7px 13px; font-size:.85rem; border-radius:9px; }
  .btn-sec { background:linear-gradient(180deg,#1e5295,var(--navy)); color:#fff; }
  .btn-sec:hover { box-shadow:0 8px 20px rgba(23,66,122,.34); }
  .btn-danger { background:linear-gradient(180deg,#d94b42,var(--err)); color:#fff; }
  .btn-danger:hover { box-shadow:0 8px 20px rgba(198,40,40,.34); }
  .btn-outline { background:transparent; color:var(--navy); box-shadow:none; border:1.5px solid var(--line); }
  .btn-outline:hover { background:#f6f9fe; border-color:var(--honey); box-shadow:none; transform:translateY(-1px); }

  /* ---------- Tables ---------- */
  .table-wrap { overflow-x:auto; border:1px solid var(--line-2); border-radius:var(--radius-sm); background:var(--card); }
  table { width:100%; border-collapse:collapse; margin-top:0; min-width:520px; }
  table.narrow { min-width:0; }
  th,td { text-align:left; padding:12px 14px; border-bottom:1px solid var(--line-2); font-size:.92rem; vertical-align:middle; }
  th { color:var(--muted); font-size:.73rem; text-transform:uppercase; letter-spacing:.07em; font-weight:700; background:#f8fafd; }
  tbody tr { transition:background var(--fast) var(--ease); }
  tr:hover td { background:#f6f9fe; }
  tbody tr:last-child td { border-bottom:0; }

  /* ---------- Misc ---------- */
  .badge { display:inline-flex; align-items:center; padding:4px 12px; border-radius:var(--radius-pill); font-size:.76rem; font-weight:600; white-space:nowrap; }
  .ok{background:var(--ok)} .warn{background:var(--warn)} .err{background:var(--err)} .info{background:var(--info)}
  .badge.ok{background:var(--ok-bg);color:#1b5e20} .badge.warn{background:var(--warn-bg);color:#8a5a00}
  .badge.err{background:var(--err-bg);color:#b3261e} .badge.info{background:var(--info-bg);color:#0d47a1}
  .flash {
    max-width:1040px; margin:18px auto 0; padding:13px 18px; border-radius:var(--radius-sm);
    border:1px solid transparent; font-weight:500; animation:pop-in var(--med) var(--ease);
  }
  .flash.success { background:var(--ok-bg); color:#1b5e20; border-color:#cde8cf; }
  .flash.error { background:var(--err-bg); color:#b3261e; border-color:#f6cdc9; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:16px; }
  .stat {
    background:var(--card); border:1px solid var(--line-2); border-radius:var(--radius);
    padding:20px; text-align:center; box-shadow:var(--shadow-sm);
    transition:transform var(--med) var(--ease), box-shadow var(--med) var(--ease);
  }
  .stat:hover { transform:translateY(-2px); box-shadow:var(--shadow); }
  .stat b { font-size:1.95rem; color:var(--navy); display:block; line-height:1.15; letter-spacing:-.025em; }
  label { font-size:.86rem; font-weight:600; color:#33425c; }
  .muted { color:var(--muted); font-size:.9rem; }
  .actions { display:flex; gap:9px; flex-wrap:wrap; align-items:center; }

  /* ---------- Notifications ---------- */
  .notif-wrap { position:relative; }
  .notif-panel {
    display:none; position:absolute; right:0; top:calc(100% + 10px); width:340px; max-height:400px; overflow:auto;
    background:#fff; color:var(--ink); border:1px solid var(--line-2); border-radius:var(--radius);
    box-shadow:var(--shadow-lg); padding:8px; z-index:100;
  }
  .notif-panel.open { display:block; animation:pop-in var(--fast) var(--ease); }
  .notif-item { padding:11px 13px; border-radius:var(--radius-sm); font-size:.88rem; border-bottom:1px solid var(--line-2); transition:background var(--fast) var(--ease); }
  .notif-item:last-child { border-bottom:0; }
  .notif-item:hover { background:#f6f9fe; }
  .notif-item.unread { background:#fff8e8; }
  .notif-item .when { display:block; color:var(--muted); font-size:.74rem; margin-top:3px; }
  .notif-empty { padding:26px 18px; text-align:center; color:var(--muted); font-size:.9rem; }
  .notif-item { position:relative; padding-right:36px; }
  .notif-follow { display:block; width:100%; padding:0; border:0; background:transparent; box-shadow:none; color:inherit; text-align:left; font:inherit; line-height:1.45; cursor:pointer; }
  .notif-follow:hover { color:var(--navy); text-decoration:underline; box-shadow:none; }
  .notif-del {
    position:absolute; top:6px; right:6px; width:21px; height:21px; padding:0; line-height:1;
    border:0; border-radius:50%; background:transparent; box-shadow:none; color:#9aa8bd;
    font-size:1.05rem; cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:background var(--fast) var(--ease), color var(--fast) var(--ease), transform var(--fast) var(--ease);
  }
  .notif-del:hover { background:var(--err-bg); color:var(--err); transform:scale(1.12); box-shadow:none; }
  .notif-clear {
    display:block; width:100%; margin-top:6px; padding:9px; border:0; border-radius:var(--radius-sm);
    background:#f6f9fe; box-shadow:none; color:var(--muted); font-size:.83rem; font-weight:600; cursor:pointer;
    transition:background var(--fast) var(--ease), color var(--fast) var(--ease);
  }
  .notif-clear:hover { background:var(--err-bg); color:var(--err); }

  /* ---------- Cleanup / delete controls ---------- */
  .row-actions { display:flex; gap:7px; flex-wrap:wrap; align-items:center; }
  .icon-btn {
    width:26px; height:26px; padding:0; line-height:1; border-radius:8px; border:0;
    background:#f2f6fc; box-shadow:none; color:#8a97ab; font-size:1.05rem; cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center;
    transition:background var(--fast) var(--ease), color var(--fast) var(--ease), transform var(--fast) var(--ease);
  }
  .icon-btn:hover { background:var(--err-bg); color:var(--err); transform:scale(1.08); box-shadow:none; }
  .icon-btn.danger { background:var(--err-bg); color:var(--err); }
  .icon-btn.danger:hover { background:var(--err); color:#fff; }
  .check-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--line-2); }
  .check-row:last-child { border-bottom:0; }
  .check-row .txt { flex:1; min-width:0; }
  .check-row.is-done .txt { color:var(--muted); }
  .danger-zone { border:1px dashed #f2c6c2; background:#fffafa; }
  .danger-zone h2 { color:var(--err); }
  .empty-state { text-align:center; padding:36px 16px; color:var(--muted); }
  .empty-state .big { font-size:2.1rem; display:block; margin-bottom:8px; opacity:.75; }

  /* ---------- Profile ---------- */
  .avatar {
    width:96px; height:96px; border-radius:50%; object-fit:cover; flex-shrink:0;
    border:3px solid #fff; box-shadow:0 0 0 2px rgba(224,170,51,.5), var(--shadow);
    background:#eef3fb; color:var(--navy);
    display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:800;
  }
  .avatar.sm { width:34px; height:34px; font-size:.8rem; border-width:2px; box-shadow:0 0 0 1.5px rgba(224,170,51,.5); }
  .profile-head { display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
  .profile-meta { min-width:0; flex:1; }
  .profile-meta h1 { margin-bottom:4px; }
  .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:0 16px; }
  .kv { display:flex; justify-content:space-between; align-items:baseline; gap:14px; padding:11px 0; border-bottom:1px solid var(--line-2); }
  .kv:last-child { border-bottom:0; }
  .kv .k { color:var(--muted); font-size:.88rem; flex-shrink:0; }
  .kv .v { font-weight:600; text-align:right; word-break:break-word; }
  .money { font-variant-numeric:tabular-nums; }
  .star-row { color:var(--honey-dark); letter-spacing:1px; }

  /* ---------- Welcome hero ---------- */
  .welcome-hero {
    position:relative; overflow:hidden;
    background:linear-gradient(135deg,#1b4a88,var(--navy) 45%,var(--navy-dark));
    color:#fff; border-radius:var(--radius); padding:34px 30px; margin-bottom:20px;
    box-shadow:var(--shadow-lg);
  }
  .welcome-hero::after {
    content:""; position:absolute; right:-80px; top:-100px; width:300px; height:300px; border-radius:50%;
    background:radial-gradient(circle,rgba(224,170,51,.35),transparent 68%); pointer-events:none;
  }
  .welcome-hero > * { position:relative; z-index:1; }
  .welcome-hero h1 { font-size:1.7rem; margin-bottom:10px; }
  .welcome-hero p { opacity:.92; max-width:640px; }

  /* ---------- Photo grid & lightbox ---------- */
  .photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
  .photo-grid figure { margin:0; position:relative; }
  .photo-grid img { width:100%; height:120px; object-fit:cover; border-radius:var(--radius-sm); cursor:zoom-in; transition:transform var(--med) var(--ease), box-shadow var(--med) var(--ease); }
  .photo-grid img:hover { transform:scale(1.04); box-shadow:var(--shadow); }
  .photo-grid figcaption { position:absolute; bottom:7px; left:7px; background:rgba(15,45,85,.66); color:#fff; font-size:.68rem; padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:.06em; }
  #lightbox { position:fixed; inset:0; background:rgba(15,45,85,.9); -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px); display:none; align-items:center; justify-content:center; z-index:99; cursor:zoom-out; animation:fade-in var(--med) var(--ease); }
  #lightbox img { max-width:92vw; max-height:92vh; border-radius:var(--radius); box-shadow:0 24px 70px rgba(0,0,0,.55); animation:pop-in var(--med) var(--ease); }

  /* ---------- Admin analytics layout ---------- */
  body.adminpage { font-family:'Segoe UI',system-ui,-apple-system,sans-serif; }
  body.adminpage .container { margin-left:304px; margin-right:24px; max-width:none; }
  body.adminpage .flash { margin-left:340px; margin-right:28px; max-width:none; }

  /* Top bar */
  .admin-top {
    background:rgba(255,255,255,.85); -webkit-backdrop-filter:saturate(180%) blur(14px); backdrop-filter:saturate(180%) blur(14px);
    color:var(--ink); border-bottom:1px solid var(--line-2); position:sticky; top:0; z-index:60;
    display:flex; align-items:center; gap:14px; padding:0 20px; height:58px;
  }
  .admin-top .menu-btn { display:none; background:none; border:0; color:var(--navy); font-size:1.02rem; font-weight:600; cursor:pointer; padding:8px 12px; border-radius:var(--radius-sm); white-space:nowrap; transition:background var(--fast) var(--ease); }
  .admin-top .menu-btn:hover { background:#eef3fb; }
  .admin-top .admin-brand { margin-right:auto; display:flex; align-items:center; gap:9px; text-decoration:none; font-weight:800; font-size:1.12rem; color:var(--navy); letter-spacing:-.012em; }
  .admin-top .admin-brand img { width:32px; height:32px; border-radius:50%; box-shadow:0 0 0 2px rgba(224,170,51,.5), 0 2px 8px rgba(23,66,122,.16); }
  .admin-top .admin-brand .honey-txt { color:var(--honey-dark); }
  .admin-top .admin-brand .console { color:var(--muted); font-weight:400; font-size:.79rem; border-left:1px solid var(--line); padding-left:9px; }

  /* ---------- Modern pro layer ---------- */
  body { font-family:'Inter','Segoe UI',system-ui,-apple-system,sans-serif; }
  body:not(.adminpage) { background:radial-gradient(1000px 420px at 100% -10%, rgba(224,170,51,.14), transparent 60%), radial-gradient(900px 420px at -10% 0%, rgba(23,66,122,.08), transparent 60%), var(--bg); background-attachment:fixed; }
  header { position:sticky; top:0; z-index:1030; box-shadow:0 8px 30px rgba(15,45,85,.07); }
  .brand img { border-radius:14px; box-shadow:0 6px 16px rgba(23,66,122,.25), 0 0 0 2px rgba(224,170,51,.55); }
  nav.main { align-items:center; }
  nav.main a { border-radius:var(--radius-pill); padding:8px 15px; font-weight:600; }
  nav.main a.active { background:linear-gradient(180deg,#1e5295,var(--navy)); color:#fff; box-shadow:0 6px 16px rgba(23,66,122,.3); }
  nav.main .notif-wrap { display:inline-flex; margin:0 2px; }
  nav.main .bell { display:inline-flex; align-items:center; gap:7px; padding:8px 14px; font-size:.95rem; font-weight:700; color:#7a5410; background:linear-gradient(180deg,#fff8e6,#fdeecb); border:1px solid #f0d48a; border-radius:var(--radius-pill); }
  nav.main .bell:hover { box-shadow:0 6px 16px rgba(224,170,51,.35); transform:translateY(-1px); background:linear-gradient(180deg,#fff8e6,#fdeecb); }
  nav.main .bell .count { position:static; margin-left:2px; }
  .bell .bi-bell-fill { color:var(--honey-dark); }
  .bell.ring .bi-bell-fill { animation:bell-ring .9s var(--ease) 2; transform-origin:top center; }
  @keyframes bell-ring { 0%,100%{transform:rotate(0)} 20%{transform:rotate(16deg)} 40%{transform:rotate(-14deg)} 60%{transform:rotate(9deg)} 80%{transform:rotate(-6deg)} }
  .card { border:1px solid var(--line-2); border-radius:20px; box-shadow:var(--shadow); transition:box-shadow var(--med) var(--ease), transform var(--med) var(--ease); }
  .card:hover { box-shadow:var(--shadow-lg); }
  h1 { font-weight:800; letter-spacing:-.025em; }
  .btn, button { border-radius:var(--radius-pill); }
  .stat { border-radius:20px; }
  th { text-transform:uppercase; font-size:.72rem; letter-spacing:.07em; color:var(--muted); }
  @media (max-width:800px) { nav.main .notif-wrap { display:block; } nav.main .bell { width:100%; justify-content:flex-start; } .notif-panel { width:auto; left:0; right:0; } }

  /* Sidebar */
  .sidebar-admin { position:fixed; top:58px; left:0; bottom:0; width:264px; background:linear-gradient(180deg,#123566,var(--navy-dark)); color:#fff; z-index:55; padding:22px 0 30px; overflow-y:auto; }
  .sidebar-admin .who { padding:0 22px 18px; border-bottom:1px solid rgba(255,255,255,.09); margin-bottom:12px; display:flex; align-items:center; gap:13px; }
  .sidebar-admin .who .avatar { width:46px; height:46px; border-radius:50%; background:rgba(224,170,51,.16); color:var(--honey); display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
  .sidebar-admin .who .who-txt { line-height:1.35; min-width:0; }
  .sidebar-admin .who b { color:#fff; display:block; font-size:1rem; }
  .sidebar-admin .who .sub { font-size:.75rem; color:#9db4d2; }
  .sidebar-admin a { display:flex; align-items:center; gap:14px; color:#d3dff0; text-decoration:none; padding:11px 22px; margin:2px 10px; border-radius:var(--radius-sm); font-size:.93rem; font-weight:500; transition:background var(--fast) var(--ease), color var(--fast) var(--ease), box-shadow var(--fast) var(--ease); }
  .sidebar-admin a:hover { background:rgba(255,255,255,.09); color:#fff; }
  .sidebar-admin a.active { background:var(--honey); color:#2a2110; font-weight:600; box-shadow:0 4px 14px rgba(224,170,51,.3); }
  .sidebar-admin a i { width:20px; text-align:center; color:var(--honey); transition:color var(--fast) var(--ease); }
  .sidebar-admin a.active i { color:#2a2110; }
  .sidebar-admin .sect { padding:18px 22px 7px; color:#8fa9cb; font-size:.68rem; text-transform:uppercase; letter-spacing:.14em; font-weight:700; }

  /* Stat cards */
  .stat { display:flex; flex-direction:column; justify-content:space-between; padding:20px; min-height:118px; }
  .stat .top { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
  .stat .top b { font-size:2rem; line-height:1; letter-spacing:-.03em; }
  .stat .top .bi {
    font-size:1rem; width:38px; height:38px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; background:rgba(23,66,122,.08); color:var(--navy);
  }
  .stat.dark .top .bi { background:rgba(255,255,255,.16); color:var(--honey); }
  .stat.honey .top .bi { background:rgba(42,33,16,.12); color:#2a2110; }
  .stat.ok .top .bi, .stat.info .top .bi, .stat.err .top .bi { background:rgba(255,255,255,.18); color:#fff; }
  .stat h4 { margin:14px 0 0; font-size:.83rem; opacity:.92; font-weight:600; text-align:left; }
  .stat.honey { background:linear-gradient(135deg,#eab949,var(--honey)); border-color:transparent; } .stat.honey b, .stat.honey .bi, .stat.honey h4 { color:#2a2110; }
  .stat.dark  { background:linear-gradient(135deg,#1b4a88,var(--navy)); border-color:transparent; }  .stat.dark b, .stat.dark h4 { color:#fff; }
  .stat.info  { background:var(--info); border-color:transparent; }  .stat.info b, .stat.info .bi, .stat.info h4 { color:#fff; }
  .stat.ok    { background:var(--ok); border-color:transparent; }    .stat.ok b, .stat.ok .bi, .stat.ok h4 { color:#fff; }
  .stat.err   { background:var(--err); border-color:transparent; }   .stat.err b, .stat.err .bi, .stat.err h4 { color:#fff; }
  .stat.dark:hover, .stat.honey:hover, .stat.info:hover, .stat.ok:hover, .stat.err:hover { transform:none; box-shadow:var(--shadow); }

  /* Admin content tables & feed */
  .dash-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(185px,1fr)); gap:16px; }
  .dash-cols { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:28px; align-items:start; }
  .card-title { display:flex; align-items:center; gap:9px; margin-bottom:14px; }
  .card-title i.bi { color:var(--honey-dark); font-size:.95rem; }
  body.adminpage table { min-width:0; }
  body.adminpage .card { padding:24px; }
  body.adminpage .table-wrap { border:0; }
  /* Activity feed (timeline) */
  .feed { display:flex; flex-direction:column; }
  .feed-row { display:flex; gap:12px; align-items:flex-start; padding:11px 0; border-bottom:1px solid var(--line-2); animation:rise .45s var(--ease) both; }
  .feed-row:last-child { border-bottom:0; }
  .feed-ico { flex:0 0 32px; width:32px; height:32px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:var(--info-bg); color:var(--info); font-size:.85rem; }
  .feed-ico.ok{background:var(--ok-bg);color:var(--ok)} .feed-ico.warn{background:var(--warn-bg);color:var(--warn)}
  .feed-ico.err{background:var(--err-bg);color:var(--err)} .feed-ico.info{background:var(--info-bg);color:var(--info)}
  .feed-text { font-size:.89rem; line-height:1.45; }
  .feed-text b { color:var(--navy); }
  .feed-when { color:var(--muted); font-size:.74rem; margin-top:3px; }

  /* Horizontal bar chart */
  .bar-row { display:flex; justify-content:space-between; align-items:baseline; gap:12px; margin:0 0 6px; font-size:.88rem; }
  .bar-row b { color:var(--navy); font-variant-numeric:tabular-nums; }
  .bar-track { background:#e9eef7; border-radius:99px; overflow:hidden; height:10px; margin-bottom:15px; }
  .bar-fill { background:linear-gradient(90deg,#e7b23d,var(--honey)); border-radius:99px; height:100%; min-width:8px; animation:grow-x .7s var(--ease) both; }

  @media (max-width:1240px) {
    body.adminpage .container, body.adminpage .flash { margin-left:24px; margin-right:16px; }
    .sidebar-admin { top:0; display:none; }
    .sidebar-admin.open { display:block; box-shadow:var(--shadow-lg); }
    .admin-top .menu-btn { display:block; }
    .admin-top .admin-brand { margin:0 auto; }
  }
  @media (max-width:560px) {
    .admin-top .admin-brand .console { display:none; }
  }

  @media (max-width: 800px) {
    .burger { display:block; }
    nav.main {
      display:flex; position:absolute; top:60px; left:0; right:0; background:var(--card);
      border-bottom:1px solid var(--line-2); flex-direction:column; align-items:stretch;
      padding:10px 14px 16px; box-shadow:var(--shadow-lg);
      max-height:0; opacity:0; visibility:hidden; overflow:hidden; transform:translateY(-8px);
      transition:max-height var(--med) var(--ease), opacity var(--med) var(--ease), transform var(--med) var(--ease), visibility var(--med);
    }
    nav.main.open { max-height:70vh; overflow:auto; opacity:1; visibility:visible; transform:none; }
    nav.main a { padding:12px 14px; font-size:.98rem; border-radius:var(--radius-sm); }
    .userbox .who { display:none; }
    .container { margin:18px auto; padding:0 14px; }
    .card { padding:18px; }
    h1 { font-size:1.35rem; }
    .welcome-hero { padding:26px 20px; }
    .client-header { flex-wrap:wrap; column-gap:8px; padding:0 14px; }
    .client-header .brand { order:1; }
    .client-header .userbox { order:2; }
    .client-header .userbox .who { display:none; }
    .client-header nav.main.client-main {
      display:flex; position:static; order:3; flex:0 0 100%; width:100%; gap:3px; margin:0;
      flex-direction:row; align-items:center; padding:0 0 8px; max-height:none; overflow-x:auto;
      opacity:1; visibility:visible; transform:none; background:transparent; border:0; box-shadow:none;
      scrollbar-width:none;
    }
    .client-header nav.main.client-main::-webkit-scrollbar { display:none; }
    .client-header nav.main.client-main a { flex:0 0 auto; padding:8px 11px; font-size:.82rem; border-radius:var(--radius-pill); }
  }

  @media (prefers-reduced-motion: reduce) {
    *,*::before,*::after { animation-duration:.001ms !important; animation-iteration-count:1 !important; transition-duration:.001ms !important; scroll-behavior:auto !important; }
  }
</style>
<?php if (!empty($enableMapAssets)): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""><?php endif; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/chorehive-ui.css?v=<?= filemtime(__DIR__ . '/assets/css/chorehive-ui.css') ?>">
</head>
<body class="<?= ($u && $u['role']==='admin') ? 'adminpage' : '' ?>">
<?php if ($u && $u['role']==='admin'): ?>
<div class="admin-top">
  <button class="menu-btn" id="burger" aria-label="Menu" aria-controls="mainnav" aria-expanded="false"><i class="bi bi-list"></i> Menu</button>
  <a class="admin-brand" href="<?= $base ?>/index.php"><img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo"><span>Chore<span class="honey-txt">Hive</span></span> <span class="console">Admin Console</span></a>
  <div class="userbox">
    <span class="notif-wrap">
      <a class="bell" href="#" id="bell" title="Notifications" aria-label="Notifications"><i class="bi bi-bell-fill"></i><?php if ($notif_count): ?><span class="count"><?= $notif_count ?></span><?php endif; ?></a>
      <div class="notif-panel" id="notifpanel">
        <div class="notif-empty">Loading…</div>
      </div>
    </span>
    <a class="who" href="<?= $base ?>/admin/profile.php" title="My profile">
      <?php if (avatar_url($u, $base)): ?><img class="avatar sm" src="<?= e(avatar_url($u, $base)) ?>" alt="">
      <?php else: ?><span class="avatar sm"><?= e(initials($u['name'])) ?></span><?php endif; ?>
      <span><?= e($u['name']) ?></span>
    </a>
    <a href="<?= $base ?>/logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
</div>
<nav class="sidebar-admin" id="mainnav">
  <div class="who">
    <div class="avatar"><i class="bi bi-person"></i></div>
    <div class="who-txt">
      <b><?= e($u['name']) ?></b>
      <span class="sub">Administrator</span>
    </div>
  </div>
  <div class="sect">Dashboard</div>
  <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Overview</a>
  <a href="bookings.php"><i class="bi bi-calendar3"></i> Bookings</a>
  <a href="chats.php"><i class="bi bi-chat-dots"></i> Customer Chats</a>
  <a href="users.php"><i class="bi bi-people"></i> Users &amp; Badges</a>
  <a href="services.php"><i class="bi bi-tags"></i> Services</a>
  <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
  <a href="audit.php"><i class="bi bi-clock-history"></i> Audit Log</a>
  <div class="sect">Site</div>
  <a href="<?= $base ?>/index.php"><i class="bi bi-house"></i> View Homepage</a>
  <a href="<?= $base ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</nav>
<?php else: ?>
<header class="<?= $u && $u['role'] === 'client' ? 'client-header' : '' ?>">
  <a class="brand" href="<?= $base ?>/index.php"><img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo"> ChoreHive</a>
  <?php if ($u && $u['role'] !== 'client'): ?><button class="burger" id="burger" aria-label="Open navigation menu" aria-controls="mainnav" aria-expanded="false">☰</button><?php endif; ?>
  <?php if ($u): ?>
  <?php ob_start(); ?>
    <span class="notif-wrap">
      <a class="bell" href="#" id="bell" title="Notifications" aria-label="Notifications"><i class="bi bi-bell-fill"></i><?php if ($notif_count): ?><span class="count"><?= $notif_count ?></span><?php endif; ?></a>
      <div class="notif-panel" id="notifpanel">
        <div class="notif-empty">Loading…</div>
      </div>
    </span>
  <?php $bell_html = ob_get_clean(); ?>
  <nav class="main <?= $u['role'] === 'client' ? 'client-main' : '' ?>" id="mainnav">
    <a href="<?= $base ?>/<?= $u['role'] ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <?php if ($u['role']==='client'): ?>
      <a href="<?= $base ?>/client/workers.php"><i class="bi bi-people"></i> Find a Cleaner</a>
      <a href="<?= $base ?>/chat.php"><i class="bi bi-chat-dots"></i> Messages</a>
      <a href="<?= $base ?>/client/book.php"><i class="bi bi-plus-circle"></i> Book a Service</a>
      <a href="<?= $base ?>/client/bookings.php"><i class="bi bi-bag-check"></i> My Bookings</a>
    <?php elseif ($u['role']==='worker'): ?>
      <a href="<?= $base ?>/worker/jobs.php"><i class="bi bi-briefcase"></i> My Jobs</a>
      <a href="<?= $base ?>/chat.php"><i class="bi bi-chat-dots"></i> Messages</a>
      <a href="<?= $base ?>/worker/reviews.php"><i class="bi bi-star"></i> My Reviews</a>
    <?php else: ?>
      <a href="<?= $base ?>/admin/bookings.php">Bookings</a><a href="<?= $base ?>/admin/chats.php">Chats</a><a href="<?= $base ?>/admin/users.php">Users</a><a href="<?= $base ?>/admin/services.php">Services</a><a href="<?= $base ?>/admin/reports.php">Reports</a><a href="<?= $base ?>/admin/audit.php">Audit Log</a>
    <?php endif; ?>
  </nav>
  <div class="userbox">
    <?= $bell_html ?>
    <a class="who" href="<?= $base ?>/<?= $u['role'] === 'admin' ? 'admin' : $u['role'] ?>/profile.php" title="My profile">
      <?php if (avatar_url($u, $base)): ?>
        <img class="avatar sm" src="<?= e(avatar_url($u, $base)) ?>" alt="">
      <?php else: ?>
        <span class="avatar sm"><?= e(initials($u['name'])) ?></span>
      <?php endif; ?>
      <span><?= e($u['name']) ?></span>
    </a>
    <a href="<?= $base ?>/logout.php">Logout</a>
  </div>
  <?php else: ?>
  <div class="userbox">
    <a href="<?= $base ?>/login.php">Login</a> · <a href="<?= $base ?>/register.php">Register</a>
  </div>
  <?php endif; ?>
</header>
<?php endif; ?>
<?php flash(); ?>
<div class="container">
<?php if ($u): ?>
<script>
(function () {
  var CSRF = <?= json_encode(csrf_token()) ?>;
  var API = '<?= $base ?>/notifications.php';
  const burger = document.getElementById('burger');
  const nav = document.getElementById('mainnav');
  if (burger && nav) {
    burger.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    nav.addEventListener('click', ev => {
      if (ev.target.closest('a')) {
        nav.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', ev => {
      if (ev.key === 'Escape' && nav.classList.contains('open')) {
        nav.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
        burger.focus();
      }
    });
  }
  // highlight current page in nav
  const here = location.pathname.split('/').pop();
  document.querySelectorAll('nav.main a, .sidebar-admin a').forEach(a => { if (a.getAttribute('href') && a.getAttribute('href').endsWith(here)) a.classList.add('active'); });

  const bell = document.getElementById('bell'), panel = document.getElementById('notifpanel');
  if (bell && panel) {
    function render(list) {
      panel.replaceChildren();
      if (list.length) {
        list.forEach(n => {
          const item = document.createElement('div');
          item.className = 'notif-item' + (n.unread ? ' unread' : '');
          item.dataset.id = String(n.id);
          const del = document.createElement('button');
          del.className = 'notif-del';
          del.dataset.id = String(n.id);
          del.type = 'button';
          del.title = 'Delete notification';
          del.setAttribute('aria-label', 'Delete notification');
          del.textContent = '×';
          item.appendChild(del);
          const content = n.url ? document.createElement('a') : document.createElement('div');
          content.className = 'notif-follow';
          if (n.url) {
            content.href = n.url;
            content.dataset.followId = String(n.id);
          }
          content.textContent = n.message;
          item.appendChild(content);
          const when = document.createElement('span');
          when.className = 'when';
          when.textContent = n.when;
          item.appendChild(when);
          panel.appendChild(item);
        });
        const clear = document.createElement('button');
        clear.className = 'notif-clear';
        clear.dataset.clear = '1';
        clear.type = 'button';
        clear.textContent = 'Clear all';
        panel.appendChild(clear);
      } else {
        const empty = document.createElement('div');
        empty.className = 'notif-empty';
        empty.textContent = '🔔 You’re all caught up.';
        panel.appendChild(empty);
      }
      const c = bell.querySelector('.count');
      const unread = list.filter(n => n.unread).length;
      if (unread && c) c.textContent = unread;
      else if (unread) {
        const badge = document.createElement('span');
        badge.className = 'count';
        badge.textContent = unread;
        bell.appendChild(badge);
      } else if (c) c.remove();
    }
    function post(action, extra) {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('action', action);
      Object.keys(extra || {}).forEach(k => fd.append(k, extra[k]));
      return fetch(API, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
        .then(r => r.json())
        .then(d => { if (d && d.items) render(d.items); return d; })
        .catch(() => {});
    }
    bell.addEventListener('click', function (ev) {
      ev.preventDefault();
      if (panel.classList.contains('open')) { panel.classList.remove('open'); return; }
      panel.classList.add('open');
      post('read');
    });
    panel.addEventListener('click', function (ev) {
      const del = ev.target.closest('.notif-del');
      if (del) { post('delete_one', { id: del.dataset.id }); return; }
      if (ev.target.closest('[data-clear]')) post('clear');
      const follow = ev.target.closest('[data-follow-id]');
      if (follow) {
        ev.preventDefault();
        const url = follow.href;
        post('follow', { id: follow.dataset.followId }).then(d => {
          if (d && d.redirect) location.assign(d.redirect);
          else location.assign(url);
        });
      }
    });
    document.addEventListener('click', ev => {
      if (!ev.target.closest('.notif-wrap')) panel.classList.remove('open');
    });
    document.addEventListener('keydown', ev => { if (ev.key === 'Escape') panel.classList.remove('open'); });
  }
})();
</script>
<?php endif; ?>
<script>
/* Accessible password visibility toggles on login and registration. */
document.addEventListener('click', function (ev) {
  var toggle = ev.target.closest('[data-password-toggle]');
  if (!toggle) return;
  var field = document.getElementById(toggle.getAttribute('data-password-toggle'));
  if (!field) return;
  var reveal = field.type === 'password';
  field.type = reveal ? 'text' : 'password';
  toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
  toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
  toggle.innerHTML = reveal
    ? '<i class="bi bi-eye-slash" aria-hidden="true"></i><span>Hide</span>'
    : '<i class="bi bi-eye" aria-hidden="true"></i><span>Show</span>';
});
</script>
<script>
/* Shared photo lightbox (used by client + worker photo grids) */
(function () {
  var lb = document.getElementById('lightbox');
  if (!lb) return;
  var img = document.getElementById('lightbox-img');
  document.addEventListener('click', function (ev) {
    if (ev.target.classList && ev.target.classList.contains('lightbox-img')) {
      img.src = ev.target.src;
      lb.style.display = 'flex';
    } else if (ev.target.closest('#lightbox')) {
      lb.style.display = 'none';
    }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') lb.style.display = 'none';
  });
})();
</script>
