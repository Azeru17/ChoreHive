<?php
require '../config.php';
$u=require_role('client');
$wid=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT) ?: 0;

// Favorite toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    verify_csrf();
    $check = $conn->prepare("SELECT 1 FROM favorite_workers WHERE client_id=? AND worker_id=?");
    $check->bind_param('ii', $u['id'], $wid);
    $check->execute();
    $isFav = (bool)$check->get_result()->fetch_assoc();
    if ($isFav) {
        $del = $conn->prepare("DELETE FROM favorite_workers WHERE client_id=? AND worker_id=?");
        $del->bind_param('ii', $u['id'], $wid);
        $del->execute();
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO favorite_workers (client_id, worker_id) VALUES (?,?)");
        $ins->bind_param('ii', $u['id'], $wid);
        $ins->execute();
    }
    header('Location: worker.php?id=' . $wid);
    exit;
}

$stmt=$conn->prepare("SELECT w.*,COALESCE(AVG(f.rating),0) rating,COUNT(DISTINCT f.id) review_count,(SELECT COUNT(*) FROM bookings x WHERE x.worker_id=w.id AND x.status='completed') completed_jobs,(SELECT COUNT(*) FROM badges bd WHERE bd.worker_id=w.id) badge_count,(SELECT COUNT(*) FROM bookings a WHERE a.worker_id=w.id AND a.status IN ('assigned','in_progress','awaiting_verification')) active_jobs,(SELECT last_seen FROM worker_presence p WHERE p.worker_id=w.id) last_seen FROM users w LEFT JOIN bookings b ON b.worker_id=w.id LEFT JOIN feedback f ON f.booking_id=b.id WHERE w.id=? AND w.role='worker' AND w.worker_approved=1 GROUP BY w.id");
$stmt->bind_param('i',$wid);$stmt->execute();$worker=$stmt->get_result()->fetch_assoc();if(!$worker){flash('That worker profile could not be found.','error');header('Location: workers.php');exit;}
$badges=$conn->prepare('SELECT name,description FROM badges WHERE worker_id=? ORDER BY earned_at DESC');$badges->bind_param('i',$wid);$badges->execute();$badgeRows=$badges->get_result();
$reviews=$conn->prepare("SELECT f.rating,f.comment,f.created_at,c.name client FROM feedback f JOIN bookings b ON b.id=f.booking_id JOIN users c ON c.id=f.client_id WHERE b.worker_id=? ORDER BY f.created_at DESC LIMIT 8");$reviews->bind_param('i',$wid);$reviews->execute();$reviewRows=$reviews->get_result();
$portfolio=$conn->prepare("SELECT id,image,caption FROM worker_portfolio WHERE worker_id=? ORDER BY created_at DESC LIMIT 12");$portfolio->bind_param('i',$wid);$portfolio->execute();$portfolioRows=$portfolio->get_result();
// Is this worker favorited by the current client?
$favCheck = $conn->prepare("SELECT 1 FROM favorite_workers WHERE client_id=? AND worker_id=?");
$favCheck->bind_param('ii', $u['id'], $wid);
$favCheck->execute();
$isFavorite = (bool)$favCheck->get_result()->fetch_assoc();
$online=!empty($worker['last_seen'])&&strtotime($worker['last_seen'])>=time()-300;$available=(int)$worker['active_jobs']===0;$avatar=avatar_url($worker,ch_base());$title=$worker['name'].' — Cleaner Profile';require '../layout.php';
?>
<div class="profile-back"><a href="workers.php">← Back to all cleaners</a></div>
<section class="worker-profile-hero"><div class="worker-profile-art <?= ((int)$worker['id']%2)?'mint':'peach' ?>"><?php if($avatar): ?><img class="worker-profile-avatar" src="<?= e($avatar) ?>" alt="<?= e($worker['name']) ?> profile photo"><?php else: ?><div class="worker-profile-avatar discover-initials"><?= e(initials($worker['name'])) ?></div><?php endif; ?><span class="online-dot profile-online <?= $online?'is-online':'' ?>"></span></div><div class="worker-profile-summary"><span class="eyebrow">MEET YOUR CLEANER</span><h1><?= e($worker['name']) ?> <span class="verified-mini" title="Profile reviewed for marketplace listing; this is not an identity or background check.">✓</span></h1><div class="profile-rating"><span>★</span> <?= $worker['review_count']?number_format((float)$worker['rating'],1).' / 5':'New profile' ?> <span class="muted">· <?= (int)$worker['review_count'] ?> reviews</span></div><p><?= e($worker['bio']?:'A ChoreHive cleaning professional ready to help you feel more comfortable at home.') ?></p><div class="profile-status-line"><span class="availability-pill <?= $available?'available':'busy' ?>"><i></i><?= $available?'Available for a new job':'Currently booked' ?></span><span class="muted"><?= $online?'Recently active':'Not recently active' ?></span></div><div class="worker-profile-actions"><a class="btn" href="../chat.php?user_id=<?= (int)$worker['id'] ?>">☏ Chat with <?= e(explode(' ',$worker['name'])[0]) ?></a><?php if($available): ?><a class="btn btn-sec" href="book.php?worker_id=<?= (int)$worker['id'] ?>">Hire this cleaner →</a><?php else: ?><button class="btn btn-sec" disabled title="This cleaner has an active job">Currently unavailable</button><?php endif; ?>
<form method="post" style="display:inline;margin:0;"><?= csrf_field() ?><button type="submit" name="toggle_favorite" value="1" class="btn <?= $isFavorite ? 'btn-sec' : '' ?>" style="<?= $isFavorite ? 'background:#fff3d6;border-color:#e0aa33;color:#8a6d1f;' : '' ?>" title="<?= $isFavorite ? 'Remove from favorites' : 'Save to favorites' ?>"><?= $isFavorite ? '★ Saved' : '☆ Save' ?></button></form>
</div></div></section>
<div class="worker-profile-columns"><div class="worker-profile-main"><section class="card profile-section"><h2>About this cleaner</h2><p><?= nl2br(e($worker['bio']?:'This cleaner has not added a personal introduction yet.')) ?></p><div class="profile-facts"><div><b><?= (int)$worker['completed_jobs'] ?></b><span>Completed jobs</span></div><div><b><?= (int)$worker['badge_count'] ?></b><span>Skill badges</span></div><div><b><?= (int)$worker['review_count'] ?></b><span>Customer reviews</span></div></div></section><section class="card profile-section"><h2>Skills &amp; badges</h2><?php if(!$badgeRows->num_rows): ?><p class="muted">No badges have been added to this profile yet.</p><?php else: ?><div class="worker-badge-list"><?php while($b=$badgeRows->fetch_assoc()): ?><div class="worker-badge"><span>✦</span><div><b><?= e($b['name']) ?></b><p><?= e($b['description']?:'Recognized on ChoreHive') ?></p></div></div><?php endwhile; ?></div><?php endif; ?></section><section class="card profile-section"><h2>What customers say</h2><?php if(!$reviewRows->num_rows): ?><p class="muted">No reviews yet. Be one of this cleaner's first customers.</p><?php else: while($r=$reviewRows->fetch_assoc()): ?><article class="review-card"><div class="review-head"><b><?= e($r['client']) ?></b><span class="review-stars"><?= str_repeat('★',(int)$r['rating']) ?><span class="muted"><?= (int)$r['rating'] ?>/5</span></span></div><?php if($r['comment']): ?><p><?= e($r['comment']) ?></p><?php else: ?><p class="muted">No written comment.</p><?php endif; ?><small class="muted"><?= date('M j, Y',strtotime($r['created_at'])) ?></small></article><?php endwhile; endif; ?></section>
<?php if ($portfolioRows->num_rows): ?>
<section class="card profile-section"><h2>🖼️ Portfolio</h2><p class="muted" style="margin-bottom:12px;">A look at this cleaner's past work.</p><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;"><?php while($p=$portfolioRows->fetch_assoc()): ?><figure style="margin:0;border:1px solid var(--line);border-radius:10px;overflow:hidden;"><img src="<?= e(media_url($p['image'], ch_base())) ?>" alt="<?= e($p['caption']?:'Portfolio photo') ?>" style="width:100%;height:140px;object-fit:cover;display:block;cursor:pointer;" onclick="window.open(this.src,'_blank')"><?php if($p['caption']): ?><figcaption style="padding:6px 8px;font-size:.78rem;color:#4a5a73;"><?= e($p['caption']) ?></figcaption><?php endif; ?></figure><?php endwhile; ?></div></section>
<?php endif; ?></div><aside class="worker-profile-side"><div class="card hire-card"><span class="eyebrow">READY WHEN YOU ARE</span><h2>Found your match?</h2><p>Start a conversation, ask about your cleaning needs, then hire when you're comfortable.</p><a class="btn" style="width:100%;text-align:center" href="../chat.php?user_id=<?= (int)$worker['id'] ?>">Start a conversation</a><?php if($available): ?><a class="btn btn-sec" style="width:100%;text-align:center;margin-top:8px" href="book.php?worker_id=<?= (int)$worker['id'] ?>">Hire this cleaner</a><?php endif; ?><p class="muted" style="font-size:.78rem;margin:12px 0 0">Your booking is only created when you complete the next steps.</p></div></aside></div>
<?php require '../footer.php'; ?>
