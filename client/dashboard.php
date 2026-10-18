<?php
require '../config.php';
$u = require_role('client');
$title = 'Find Your Cleaner';
$stats = $conn->query("SELECT SUM(status IN ('pending','assigned','in_progress','awaiting_verification')) active, SUM(status='completed') completed FROM bookings WHERE client_id=".(int)$u['id'])->fetch_assoc();
$clientCity = trim((string)($u['city'] ?? ''));
$workers = $conn->query("SELECT w.id,w.name,w.bio,w.avatar,w.address,w.city,
  COALESCE(AVG(f.rating),0) rating, COUNT(DISTINCT f.id) review_count,
  (SELECT COUNT(*) FROM bookings bx WHERE bx.worker_id=w.id AND bx.status='completed') completed_jobs,
  (SELECT COUNT(*) FROM badges bd WHERE bd.worker_id=w.id) badge_count,
  (SELECT COUNT(*) FROM bookings ba WHERE ba.worker_id=w.id AND ba.status IN ('assigned','in_progress','awaiting_verification')) active_jobs,
  (SELECT last_seen FROM worker_presence wp WHERE wp.worker_id=w.id) last_seen,
  COALESCE(GROUP_CONCAT(DISTINCT bd.name ORDER BY bd.earned_at SEPARATOR '|'),'') badge_names
  FROM users w LEFT JOIN bookings b ON b.worker_id=w.id LEFT JOIN feedback f ON f.booking_id=b.id
  LEFT JOIN badges bd ON bd.worker_id=w.id
  WHERE w.role='worker' AND w.worker_approved=1 GROUP BY w.id");

// Rank every cleaner, preferring the client's city, then the strongest overall match.
$allWorkers = [];
while ($w = $workers->fetch_assoc()) {
    $w['near']  = same_city($clientCity, $w['city'] ?? '');
    $w['score'] = worker_score($w);
    $allWorkers[] = $w;
}
usort($allWorkers, function ($a, $b) {
    return ($b['near'] <=> $a['near']) ?: ($b['score'] <=> $a['score']);
});
$topPicks = array_slice($allWorkers, 0, 3);
$featuredIds = array_column($topPicks, 'id');
$others = array_slice(array_values(array_filter($allWorkers, fn($w) => !in_array($w['id'], $featuredIds, true))), 0, 9);

/** Build the "why this cleaner" chips shown on a smart pick. */
function ch_pick_reasons(array $w): array {
    $reasons = [];
    if ((int)$w['review_count'] > 0) {
        $reasons[] = '★ ' . number_format((float)$w['rating'], 1) . ' rating';
    } else {
        $reasons[] = 'New profile';
    }
    if ((int)$w['completed_jobs'] > 0) $reasons[] = (int)$w['completed_jobs'] . ' jobs done';
    if ((int)$w['badge_count'] > 0) $reasons[] = (int)$w['badge_count'] . ' skill badge' . ((int)$w['badge_count'] === 1 ? '' : 's');
    $topSkill = trim(explode('|', (string)$w['badge_names'])[0] ?? '');
    if ($topSkill !== '') $reasons[] = 'Great at ' . $topSkill;
    return $reasons;
}
require '../layout.php';
?>
<section class="market-hero">
  <div class="market-hero-copy">
    <span class="eyebrow">YOUR HOME, YOUR CHOICE</span>
    <h1>Meet your next<br><span>favorite cleaner.</span></h1>
    <p>Find a cleaner who fits your home. Explore local professionals, compare reviews, and chat before you book.</p>
    <div class="market-hero-actions"><a class="btn" href="workers.php">Explore all cleaners <span aria-hidden="true">→</span></a><a class="market-text-link" href="bookings.php">View my bookings</a></div>
  </div>
  <div class="market-hero-art" aria-hidden="true"><div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div><div class="hero-sparkle">✦</div><div class="hero-note"><span class="hero-note-icon">✓</span><span><b>Cleaner, chosen by you</b><small>Chat first. Hire with confidence.</small></span></div><div class="hero-emoji">🧹</div></div>
</section>
<div class="market-stats">
  <div class="market-stat"><span class="market-stat-icon">⌂</span><div><b><?= (int)($stats['active'] ?? 0) ?></b><span>Active bookings</span></div></div>
  <div class="market-stat"><span class="market-stat-icon gold">✦</span><div><b><?= (int)($stats['completed'] ?? 0) ?></b><span>Completed jobs</span></div></div>
  <a class="market-stat market-stat-link" href="../chat.php"><span class="market-stat-icon lavender">☏</span><div><b>Messages</b><span>Chat with cleaners</span></div><span class="market-arrow">↗</span></a>
</div>

<div class="section-heading"><div><span class="eyebrow">SMART PICKS FOR YOU</span><h2>Top cleaners for you</h2><p>Ranked by customer ratings, experience, skill badges, and how often they're hired.</p></div><a class="market-text-link" href="workers.php">See all cleaners →</a></div>
<?php if ($clientCity): ?>
  <p class="smart-location-note">📍 Prioritising cleaners in <b><?= e($clientCity) ?></b> — your city. <a href="profile.php">Change my city</a></p>
<?php else: ?>
  <p class="smart-location-note smart-location-warn">📍 <a href="profile.php">Set your city</a> so we can show cleaners closest to you first.</p>
<?php endif; ?>
<?php if (!$topPicks): ?>
  <div class="card empty-state"><span class="empty-state-icon">✦</span><h3>Our cleaner community is growing</h3><p>There are no worker profiles to show yet. Please check back soon.</p></div>
<?php else: $palette=['mint','peach','lilac','blue']; $rank=0; foreach ($topPicks as $w): $rank++;
  $isOnline=!empty($w['last_seen']) && strtotime($w['last_seen']) >= time()-300;
  $available=((int)$w['active_jobs']===0);
  $avatar=avatar_url($w,ch_base());
  $reasons=ch_pick_reasons($w);
?>
  <article class="discover-card smart-pick<?= $w['near'] ? ' is-near' : '' ?>">
    <div class="discover-card-top <?= $palette[(int)$w['id']%4] ?>">
      <?php if($w['near']): ?><span class="near-ribbon">📍 Near you</span><?php else: ?><span class="discover-card-kicker">CHOREHIVE CLEANER</span><?php endif; ?>
      <span class="smart-rank">#<?= $rank ?> match</span>
      <span class="availability-pill <?= $available?'available':'busy' ?>"><i></i><?= $available?'Available':'Currently booked' ?></span>
      <?php if($avatar): ?><img class="discover-avatar" src="<?= e($avatar) ?>" alt="<?= e($w['name']) ?> profile photo"><?php else: ?><div class="discover-avatar discover-initials"><?= e(initials($w['name'])) ?></div><?php endif; ?>
      <span class="online-dot <?= $isOnline?'is-online':'' ?>" title="<?= $isOnline?'Recently active':'Not recently active' ?>"></span>
    </div>
    <div class="discover-card-body">
      <div class="discover-name-row"><h3><?= e($w['name']) ?></h3><span class="verified-mini" title="Profile reviewed for marketplace listing; this is not an identity or background check.">✓</span></div>
      <div class="discover-rating"><span>★</span> <?= $w['review_count'] ? number_format((float)$w['rating'],1) : 'New' ?> <small><?= (int)$w['review_count'] ?> review<?= (int)$w['review_count']===1?'':'s' ?></small></div>
      <div class="smart-why"><?php foreach ($reasons as $r): ?><span><?= e($r) ?></span><?php endforeach; ?></div>
      <p class="discover-bio"><?= e(mb_strimwidth(trim((string)$w['bio']) ?: 'Ready to help make your home feel fresh and comfortable.',0,105,'…')) ?></p>
      <div class="discover-actions"><a class="btn btn-sm" href="worker.php?id=<?= (int)$w['id'] ?>">View profile</a><a class="btn btn-sm btn-sec" href="../chat.php?user_id=<?= (int)$w['id'] ?>">Message</a></div>
    </div>
  </article>
<?php endforeach; endif; ?>

<?php if ($others): ?>
<div class="section-heading" style="margin-top:34px;"><div><span class="eyebrow">KEEP EXPLORING</span><h2>More cleaners you can consider</h2><p>Every cleaner is reviewed before listing.</p></div><a class="market-text-link" href="workers.php">See all cleaners →</a></div>
<div class="worker-discovery-grid">
<?php $palette=['mint','peach','lilac','blue']; foreach($others as $w):
  $isOnline=!empty($w['last_seen']) && strtotime($w['last_seen']) >= time()-300;
  $available=((int)$w['active_jobs']===0);
  $avatar=avatar_url($w,ch_base());
  $city=trim((string)($w['city'] ?? ''));
?>
  <article class="discover-card">
    <div class="discover-card-top <?= $palette[(int)$w['id']%4] ?>">
      <?php if($w['near']): ?><span class="near-ribbon" title="In your city">📍 Near you</span><?php else: ?><span class="discover-card-kicker">CHOREHIVE CLEANER</span><?php endif; ?>
      <span class="availability-pill <?= $available?'available':'busy' ?>"><i></i><?= $available?'Available':'Currently booked' ?></span>
      <?php if($avatar): ?><img class="discover-avatar" src="<?= e($avatar) ?>" alt="<?= e($w['name']) ?> profile photo"><?php else: ?><div class="discover-avatar discover-initials"><?= e(initials($w['name'])) ?></div><?php endif; ?>
      <span class="online-dot <?= $isOnline?'is-online':'' ?>" title="<?= $isOnline?'Recently active':'Not recently active' ?>"></span>
    </div>
    <div class="discover-card-body">
      <div class="discover-name-row"><h3><?= e($w['name']) ?></h3><span class="verified-mini" title="Profile reviewed for marketplace listing; this is not an identity or background check.">✓</span></div>
      <div class="discover-rating"><span>★</span> <?= $w['review_count'] ? number_format((float)$w['rating'],1) : 'New' ?> <small><?= (int)$w['review_count'] ?> review<?= (int)$w['review_count']===1?'':'s' ?></small></div>
      <p class="discover-bio"><?= e(mb_strimwidth(trim((string)$w['bio']) ?: 'Ready to help make your home feel fresh and comfortable.',0,105,'…')) ?></p>
      <div class="discover-mini-stats"><?php if($city): ?><span class="discover-city">📍 <?= e($city) ?></span><?php endif; ?><span>✦ <?= (int)$w['badge_count'] ?> badge<?= (int)$w['badge_count']===1?'':'s' ?></span><span>✓ <?= (int)$w['completed_jobs'] ?> jobs done</span></div>
      <div class="discover-actions"><a class="btn btn-sm" href="worker.php?id=<?= (int)$w['id'] ?>">View profile</a><a class="btn btn-sm btn-sec" href="../chat.php?user_id=<?= (int)$w['id'] ?>">Message</a></div>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require '../footer.php'; ?>
