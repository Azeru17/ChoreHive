<?php
require '../config.php';
$u = require_role('client');
$title = 'Find Your Cleaner';
$stats = $conn->query("SELECT SUM(status IN ('pending','assigned','in_progress','awaiting_verification')) active, SUM(status='completed') completed FROM bookings WHERE client_id=".(int)$u['id'])->fetch_assoc();
$workers = $conn->query("SELECT w.id,w.name,w.bio,w.avatar,w.address,
  COALESCE(AVG(f.rating),0) rating, COUNT(DISTINCT f.id) review_count,
  (SELECT COUNT(*) FROM bookings bx WHERE bx.worker_id=w.id AND bx.status='completed') completed_jobs,
  (SELECT COUNT(*) FROM badges bd WHERE bd.worker_id=w.id) badge_count,
  (SELECT COUNT(*) FROM bookings ba WHERE ba.worker_id=w.id AND ba.status IN ('assigned','in_progress','awaiting_verification')) active_jobs,
  (SELECT last_seen FROM worker_presence wp WHERE wp.worker_id=w.id) last_seen
  FROM users w LEFT JOIN bookings b ON b.worker_id=w.id LEFT JOIN feedback f ON f.booking_id=b.id
  WHERE w.role='worker' AND w.worker_approved=1 GROUP BY w.id ORDER BY rating DESC, completed_jobs DESC, w.name ASC LIMIT 12");
require '../layout.php';
?>
<section class="market-hero">
  <div class="market-hero-copy">
    <span class="eyebrow">YOUR HOME, YOUR CHOICE</span>
    <h1>Meet your next<br><span>favorite cleaner.</span></h1>
    <p>Browse local cleaning professionals, compare ratings and reviews, ask questions, and choose someone who feels right for your home. Listing approval is not an identity or background check.</p>
    <div class="market-hero-actions"><a class="btn" href="workers.php">Explore all cleaners <span aria-hidden="true">→</span></a><a class="market-text-link" href="bookings.php">View my bookings</a></div>
  </div>
  <div class="market-hero-art" aria-hidden="true"><div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div><div class="hero-sparkle">✦</div><div class="hero-note"><span class="hero-note-icon">✓</span><span><b>Cleaner, chosen by you</b><small>Chat first. Hire with confidence.</small></span></div><div class="hero-emoji">🧹</div></div>
</section>
<div class="market-stats">
  <div class="market-stat"><span class="market-stat-icon">⌂</span><div><b><?= (int)($stats['active'] ?? 0) ?></b><span>Active bookings</span></div></div>
  <div class="market-stat"><span class="market-stat-icon gold">✦</span><div><b><?= (int)($stats['completed'] ?? 0) ?></b><span>Completed jobs</span></div></div>
  <a class="market-stat market-stat-link" href="../chat.php"><span class="market-stat-icon lavender">☏</span><div><b>Messages</b><span>Chat with cleaners</span></div><span class="market-arrow">↗</span></a>
</div>
<div class="section-heading"><div><span class="eyebrow">HANDPICKED FOR YOU</span><h2>Find your cleaning match</h2><p>Great service starts with the right person.</p></div><a class="market-text-link" href="workers.php">See all cleaners →</a></div>
<div class="worker-discovery-grid">
<?php if (!$workers || $workers->num_rows===0): ?>
  <div class="card empty-state"><span class="empty-state-icon">✦</span><h3>Our cleaner community is growing</h3><p>There are no worker profiles to show yet. Please check back soon.</p></div>
<?php else: $palette=['mint','peach','lilac','blue']; while($w=$workers->fetch_assoc()):
  $isOnline=!empty($w['last_seen']) && strtotime($w['last_seen']) >= time()-300;
  $available=((int)$w['active_jobs']===0);
  $avatar=avatar_url($w,ch_base());
?>
  <article class="discover-card">
    <div class="discover-card-top <?= $palette[(int)$w['id']%4] ?>">
      <span class="discover-card-kicker">CHOREHIVE CLEANER</span>
      <span class="availability-pill <?= $available?'available':'busy' ?>"><i></i><?= $available?'Available':'Currently booked' ?></span>
      <?php if($avatar): ?><img class="discover-avatar" src="<?= e($avatar) ?>" alt="<?= e($w['name']) ?> profile photo"><?php else: ?><div class="discover-avatar discover-initials"><?= e(initials($w['name'])) ?></div><?php endif; ?>
      <span class="online-dot <?= $isOnline?'is-online':'' ?>" title="<?= $isOnline?'Recently active':'Not recently active' ?>"></span>
    </div>
    <div class="discover-card-body">
      <div class="discover-name-row"><h3><?= e($w['name']) ?></h3><span class="verified-mini" title="Profile reviewed for marketplace listing; this is not an identity or background check.">✓</span></div>
      <div class="discover-rating"><span>★</span> <?= $w['review_count'] ? number_format((float)$w['rating'],1) : 'New' ?> <small><?= (int)$w['review_count'] ?> review<?= (int)$w['review_count']===1?'':'s' ?></small></div>
      <p class="discover-bio"><?= e(mb_strimwidth(trim((string)$w['bio']) ?: 'Ready to help make your home feel fresh and comfortable.',0,105,'…')) ?></p>
      <div class="discover-mini-stats"><span>✦ <?= (int)$w['badge_count'] ?> badge<?= (int)$w['badge_count']===1?'':'s' ?></span><span>✓ <?= (int)$w['completed_jobs'] ?> jobs done</span></div>
      <div class="discover-actions"><a class="btn btn-sm" href="worker.php?id=<?= (int)$w['id'] ?>">View profile</a><a class="btn btn-sm btn-sec" href="../chat.php?user_id=<?= (int)$w['id'] ?>">Message</a></div>
    </div>
  </article>
<?php endwhile; endif; ?>
</div>
<?php require '../footer.php'; ?>
