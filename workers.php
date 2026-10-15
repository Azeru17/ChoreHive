<?php
/**
 * Public worker directory — lists every admin-approved worker account,
 * grouped by the specialty badges they have actually earned.
 */
require 'config.php';
require_once 'worker_data.php';
require_marketplace_schema();

$u       = current_user();
$base    = ch_base();
$workers = ch_workers($conn);
$total   = count($workers);

// Build the category list from badges genuinely earned by our workers.
$cats = [];
$badgeCount = 0;
$jobCount   = 0;
foreach ($workers as $w) {
    $badgeCount += count($w['badges']);
    $jobCount   += $w['jobs'];
    foreach ($w['badges'] as $b) {
        $k = $b['name'];
        if (!isset($cats[$k])) {
            $cats[$k] = ['name' => $k, 'desc' => $b['description'], 'count' => 0];
        }
        $cats[$k]['count']++;
    }
}
uasort($cats, function ($a, $b) {
    return $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Our Workers — ChoreHive</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/favicon.png">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins">
<style>
  :root {
    --honey:#e0aa33; --honey-dark:#c1861a; --navy:#17427a; --navy-dark:#0f2d55;
    --bg:#f4f7fc; --card:#fff; --ink:#1f2c44; --muted:#6b7a90; --line:#e3e9f2; --line-2:#edf1f8;
    --radius:16px; --radius-sm:10px;
    --shadow:0 1px 2px rgba(23,66,122,.05), 0 6px 20px rgba(23,66,122,.07);
    --shadow-lg:0 14px 40px rgba(23,66,122,.16);
    --ease:cubic-bezier(.4,0,.2,1); --fast:.15s; --med:.25s;
  }
  * { box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body,h1,h2,h3 { font-family:"Poppins",sans-serif; }
  body { margin:0; background:var(--bg); color:var(--ink); line-height:1.6; -webkit-font-smoothing:antialiased; }
  :focus-visible { outline:2px solid var(--navy); outline-offset:3px; border-radius:6px; }
  ::selection { background:rgba(224,170,51,.3); }
  .honey { color:var(--honey-dark) !important; }
  .btn-honey,.btn-dark { border-radius:12px !important; font-weight:700; transition:transform var(--fast) var(--ease), box-shadow var(--med) var(--ease); }
  .btn-honey { background:linear-gradient(180deg,#e7b23d,var(--honey)) !important; color:#2a2110 !important; box-shadow:0 2px 10px rgba(224,170,51,.35); }
  .btn-honey:hover { background:linear-gradient(180deg,#d99a1f,#c1861a) !important; color:#fff !important; transform:translateY(-2px); }
  .btn-dark { background:linear-gradient(180deg,#1e5295,var(--navy)) !important; color:#fff !important; box-shadow:0 2px 10px rgba(23,66,122,.28); }
  .btn-dark:hover { background:linear-gradient(180deg,#17427a,var(--navy-dark)) !important; transform:translateY(-2px); }
  @keyframes rise { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }

  /* Top bar */
  .topbar { background:rgba(255,255,255,.92); -webkit-backdrop-filter:blur(14px); backdrop-filter:blur(14px);
            border-bottom:1px solid var(--line-2); position:sticky; top:0; z-index:10; }
  .topbar .inner { max-width:1140px; margin:0 auto; padding:12px 20px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
  .brand { display:flex; align-items:center; gap:10px; font-weight:700; color:var(--navy); text-decoration:none; font-size:1.1rem; }
  .brand img { width:40px; height:40px; border-radius:50%; box-shadow:0 0 0 2px rgba(224,170,51,.55); }
  .spacer { flex:1 1 auto; }
  .navlink { color:#4a5a73; text-decoration:none; font-weight:600; font-size:.92rem; padding:8px 12px; border-radius:10px; transition:background var(--fast) var(--ease); }
  .navlink:hover { background:#eef3fb; color:var(--navy); }

  .wrap { max-width:1140px; margin:0 auto; padding:0 20px; }
  .hero { padding:56px 0 8px; animation:rise .6s var(--ease) both; }
  .hero h1 { line-height:1.1; letter-spacing:-.03em; margin:0; }
  hr.honey { width:56px; border:5px solid var(--honey); border-radius:99px; margin-top:18px; }
  .stats { display:flex; gap:26px; flex-wrap:wrap; margin-top:26px; }
  .stat b { display:block; font-size:1.7rem; color:var(--navy); line-height:1.1; letter-spacing:-.02em; }
  .stat span { font-size:.82rem; color:var(--muted); }

  /* Category filter */
  .filters { display:flex; flex-wrap:wrap; gap:9px; margin-top:34px; }
  .fbtn { cursor:pointer; font-family:inherit; font-size:.85rem; font-weight:600; color:#4a5a73;
          background:var(--card); border:1px solid var(--line); padding:9px 16px; border-radius:999px;
          transition:background var(--fast) var(--ease), color var(--fast) var(--ease), border-color var(--fast) var(--ease), transform var(--fast) var(--ease); }
  .fbtn:hover { border-color:var(--honey); transform:translateY(-2px); }
  .fbtn[aria-pressed="true"] { background:var(--navy); border-color:var(--navy); color:#fff; box-shadow:0 4px 14px rgba(23,66,122,.28); }
  .fbtn small { opacity:.7; margin-left:5px; font-weight:600; }

  /* Worker cards */
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:18px; margin-top:26px; }
  .wcard { background:var(--card); border:1px solid var(--line-2); border-radius:var(--radius); overflow:hidden;
           box-shadow:var(--shadow); transition:transform var(--med) var(--ease), box-shadow var(--med) var(--ease); }
  .wcard:hover { transform:translateY(-5px); box-shadow:var(--shadow-lg); }
  .wtop { height:120px; display:flex; align-items:center; justify-content:center; }
  .wtop .avatar { width:76px; height:76px; border-radius:50%; display:flex; align-items:center; justify-content:center;
                  font-size:1.55rem; font-weight:700; color:#2a2110; background:rgba(255,255,255,.94); box-shadow:0 0 0 4px rgba(255,255,255,.4); }
  .wbody { padding:20px; }
  .wcard h3 { margin:0; color:var(--navy); letter-spacing:-.015em; }
  .wmeta { margin:3px 0 12px; font-size:.84rem; color:var(--muted); }
  .wbio { margin:0; }
  .wstats { display:flex; gap:18px; margin-top:16px; padding-top:14px; border-top:1px solid var(--line-2); }
  .wstats div b { display:block; font-size:1.15rem; color:var(--navy); line-height:1.2; }
  .wstats div span { font-size:.74rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }
  .badges { list-style:none; padding:0; margin:14px 0 0; display:flex; flex-direction:column; gap:7px; }
  .badges li { font-size:.82rem; color:var(--ink); background:#fdf3e3; border:1px solid #f3e2bd; border-radius:10px; padding:8px 11px; }
  .badges li b { color:var(--honey-dark); }
  .empty { text-align:center; padding:50px 20px; color:var(--muted); }
  .foot { text-align:center; color:var(--muted); font-size:.83rem; padding:44px 0 40px; border-top:1px solid var(--line-2); margin-top:56px; }
  @media (prefers-reduced-motion: reduce) { *,*::before,*::after { animation-duration:.001ms !important; transition-duration:.001ms !important; } }

  .t1{background:linear-gradient(135deg,#17427a,#0f2d55)} .t2{background:linear-gradient(135deg,#e0aa33,#c1861a)}
  .t3{background:linear-gradient(135deg,#2a5a9e,#17427a)} .t4{background:linear-gradient(135deg,#0f2d55,#17427a)}
  .t5{background:linear-gradient(135deg,#e0aa33,#b8860b)} .t6{background:linear-gradient(135deg,#cfe0f5,#9dc0ea)}
  @media (max-width: 760px) {
    .topbar .inner { gap:6px; padding:10px 14px; }
    .topbar .spacer { display:none; }
    .topbar .brand { flex:1 0 100%; }
    .topbar .navlink { flex:1 1 auto; text-align:center; padding:9px 8px; }
    .wrap { padding-inline:14px; }
    .hero { padding-top:36px; }
    .stats { gap:12px 20px; }
    .grid { grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr)); }
    .wbody { padding:17px; }
    .wstats { justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .fbtn { min-height:42px; }
    .foot { margin-top:36px; padding:32px 0; }
  }
  @media (max-width: 380px) {
    .wrap { padding-inline:12px; }
    .grid { grid-template-columns:minmax(0,1fr); }
    .wstats div { min-width:0; }
    .fbtn { padding-inline:11px; }
  }
</style>
</head>
<body>

<div class="topbar">
  <div class="inner">
    <a class="brand" href="<?= $base ?>/index.php">
      <img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo">Chore<span class="honey">Hive</span>
    </a>
    <div class="spacer"></div>
    <a class="navlink" href="<?= $base ?>/index.php#services">Services</a>
    <a class="navlink" href="<?= $base ?>/index.php#packages">Packages</a>
    <?php if ($u): ?>
      <a class="w3-button btn-honey" href="<?= $base ?>/<?= e($u['role']) ?>/dashboard.php">My Dashboard →</a>
    <?php else: ?>
      <a class="navlink" href="<?= $base ?>/login.php">Log In</a>
      <a class="w3-button btn-honey" href="<?= $base ?>/register.php">Get Started — Free</a>
    <?php endif; ?>
  </div>
</div>

<main class="wrap">
  <div class="hero">
    <h1><b>Every ChoreHive<br><span class="honey">worker.</span></b></h1>
    <hr class="honey w3-round">
    <p style="max-width:680px;margin-top:16px;">Profiles are reviewed by a ChoreHive administrator before listing. Compare their customer reviews, completed-job history, and administrator-managed badges. Marketplace approval is not an identity or background check.</p>
    <div class="stats">
      <div class="stat"><b><?= $total ?></b><span>Listed worker<?= $total === 1 ? '' : 's' ?></span></div>
      <div class="stat"><b><?= count($cats) ?></b><span>Specialit<?= count($cats) === 1 ? 'y' : 'ies' ?></span></div>
      <div class="stat"><b><?= $badgeCount ?></b><span>Badges earned</span></div>
      <div class="stat"><b><?= $jobCount ?></b><span>Jobs completed</span></div>
    </div>

    <?php if ($cats): ?>
      <div class="filters" id="filters" role="group" aria-label="Filter workers by specialty">
        <button class="fbtn" type="button" data-filter="*" aria-pressed="true">All workers</button>
        <?php foreach ($cats as $c): ?>
          <button class="fbtn" type="button" data-filter="<?= e($c['name']) ?>" aria-pressed="false"
                  title="<?= e($c['desc']) ?>"><?= e($c['name']) ?><small><?= $c['count'] ?></small></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="search-row">
      <input id="publicWorkerSearch" type="search" placeholder="Search by name or specialty…" aria-label="Search workers" style="width:100%;max-width:420px;padding:12px 16px;border:1px solid var(--line);border-radius:12px;font-size:.95rem;font-family:inherit;">
    </div>
  </div>

  <?php if (!$workers): ?>
    <div class="wcard empty" style="margin-top:26px;">
      <h3 style="margin:0 0 6px;">No worker profiles listed yet</h3>
      <p style="margin:0;">We're onboarding cleaners now. Please check back soon.</p>
    </div>
  <?php else: ?>
    <div class="grid" id="grid">
      <?php foreach ($workers as $i => $w): ?>
        <?php $tints = ['t2', 't1', 't5', 't3', 't4', 't6']; ?>
        <article class="wcard" data-name="<?= e(strtolower($w['name'])) ?>" data-bio="<?= e(strtolower($w['bio'] ?? '')) ?>" data-badges="<?= e(implode('|', array_column($w['badges'], 'name'))) ?>">
          <div class="wtop <?= $tints[$i % 6] ?>"><span class="avatar"><?= e(ch_initials($w['name'])) ?></span></div>
          <div class="wbody">
            <h3><?= e($w['name']) ?></h3>
            <p class="wmeta"><?= e(ch_worker_status($w)) ?></p>
            <p class="wbio"><?= $w['bio'] ? e($w['bio']) : 'A ChoreHive worker profile, ready to receive bookings.' ?></p>

            <?php if ($w['badges']): ?>
              <ul class="badges">
                <?php foreach ($w['badges'] as $b): ?>
                  <li><b>🏅 <?= e($b['name']) ?></b><?= $b['description'] ? ' — ' . e($b['description']) : '' ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <div class="wstats">
              <div><b><?= $w['jobs'] ?></b><span>Jobs</span></div>
              <div><b><?= $w['reviews'] > 0 ? number_format((float)$w['rating'], 1) : '—' ?></b><span>Rating</span></div>
              <div><b><?= count($w['badges']) ?></b><span>Badges</span></div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="empty" id="noMatch" style="display:none;">No workers match that specialty yet.</p>
  <?php endif; ?>

  <p style="text-align:center;margin-top:40px;">
    <?php if ($u && $u['role'] === 'client'): ?>
      <a class="w3-button btn-honey w3-padding-large" href="<?= $base ?>/client/book.php">Book a listed cleaner →</a>
    <?php elseif (!$u): ?>
      <a class="w3-button btn-honey w3-padding-large" href="<?= $base ?>/register.php">Sign up and book →</a>
    <?php endif; ?>
  </p>

  <div class="foot">
    ChoreHive — Cleaning you can verify.<br>
    Photography licensed under CC&nbsp;BY&nbsp;2.0 / CC&nbsp;BY-SA&nbsp;2.0 — see <code>CREDITS.md</code>.
  </div>
</main>

<script>
(function () {
  var bar = document.getElementById('filters');
  var search = document.getElementById('publicWorkerSearch');
  if (!bar && !search) return;
  var cards = Array.prototype.slice.call(document.querySelectorAll('#grid .wcard'));
  var empty = document.getElementById('noMatch');
  var want = '*';
  var q = '';

  function apply() {
    var shown = 0;
    cards.forEach(function (card) {
      var badges = (card.dataset.badges || '').split('|');
      var badgeHit = want === '*' || badges.indexOf(want) !== -1;
      var hay = (card.dataset.name || '') + ' ' + (card.dataset.bio || '') + ' ' + (card.dataset.badges || '');
      var textHit = q === '' || hay.indexOf(q) !== -1;
      var hit = badgeHit && textHit;
      card.style.display = hit ? '' : 'none';
      if (hit) shown++;
    });
    if (empty) empty.style.display = shown ? 'none' : '';
  }

  if (bar) {
    bar.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.fbtn');
      if (!btn) return;
      want = btn.dataset.filter;
      Array.prototype.forEach.call(bar.querySelectorAll('.fbtn'), function (b) {
        b.setAttribute('aria-pressed', String(b === btn));
      });
      apply();
    });
  }
  if (search) {
    search.addEventListener('input', function () {
      q = search.value.trim().toLowerCase();
      apply();
    });
  }
})();
</script>
</body>
</html>