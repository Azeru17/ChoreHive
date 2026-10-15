<?php
require '../config.php';
$u=require_role('client');
$title='Discover Cleaners';
// Fetch workers with their badge names (joined) and address for searching
$workers=$conn->query("SELECT w.id,w.name,w.bio,w.avatar,w.address,
  COALESCE(AVG(f.rating),0) rating,
  COUNT(DISTINCT f.id) review_count,
  (SELECT COUNT(*) FROM bookings x WHERE x.worker_id=w.id AND x.status='completed') completed_jobs,
  (SELECT COUNT(*) FROM badges bd WHERE bd.worker_id=w.id) badge_count,
  (SELECT COUNT(*) FROM bookings a WHERE a.worker_id=w.id AND a.status IN ('assigned','in_progress','awaiting_verification')) active_jobs,
  (SELECT last_seen FROM worker_presence p WHERE p.worker_id=w.id) last_seen,
  (SELECT 1 FROM favorite_workers fw WHERE fw.client_id={$u['id']} AND fw.worker_id=w.id) is_favorited,
  COALESCE(GROUP_CONCAT(DISTINCT bd.name ORDER BY bd.earned_at SEPARATOR '|'),'') badge_names
  FROM users w
  LEFT JOIN bookings b ON b.worker_id=w.id
  LEFT JOIN feedback f ON f.booking_id=b.id
  LEFT JOIN badges bd ON bd.worker_id=w.id
  WHERE w.role='worker' AND w.worker_approved=1
  GROUP BY w.id
  ORDER BY rating DESC,completed_jobs DESC,w.name");

// Build the list of distinct badge names for filter pills
$allBadges = [];
$workerRows = [];
if ($workers) {
    while ($w = $workers->fetch_assoc()) {
        $workerRows[] = $w;
        foreach (explode('|', $w['badge_names']) as $bn) {
            $bn = trim($bn);
            if ($bn !== '') $allBadges[$bn] = ($allBadges[$bn] ?? 0) + 1;
        }
    }
}
arsort($allBadges);
require '../layout.php';
?>
<div class="page-intro"><span class="eyebrow">THE CHOREHIVE COMMUNITY</span><h1>Find your cleaning match<span class="heading-period">.</span></h1><p>Compare real profiles, see who's available, and get to know your cleaner before you hire.</p></div>
<div class="market-filter-row">
  <label class="worker-search"><span>⌕</span><input id="workerSearch" type="search" placeholder="Search by name, skill, badge, or area…" aria-label="Search workers"></label>
  <select id="workerSort" aria-label="Sort workers">
    <option value="recommended">Recommended</option>
    <option value="rating">Highest rated</option>
    <option value="jobs">Most experienced</option>
    <option value="available">Available first</option>
  </select>
</div>
<?php if (!empty($allBadges)): ?>
<div class="worker-badge-filters" id="workerBadgeFilters">
  <button type="button" class="badge-filter is-active" data-badge="">All skills</button>
  <?php foreach ($allBadges as $bn => $cnt): ?>
    <button type="button" class="badge-filter" data-badge="<?= e(strtolower($bn)) ?>"><?= e($bn) ?> <small><?= (int)$cnt ?></small></button>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="worker-discovery-grid" id="workerGrid">
<?php if(empty($workerRows)): ?><div class="card empty-state"><span class="empty-state-icon">✦</span><h3>No cleaners listed yet</h3><p>Please check again later.</p></div><?php else: $palette=['mint','peach','lilac','blue']; foreach($workerRows as $w): $online=!empty($w['last_seen'])&&strtotime($w['last_seen'])>=time()-300; $available=(int)$w['active_jobs']===0; $avatar=avatar_url($w,ch_base()); $badgesLower=strtolower($w['badge_names']); ?>
<article class="discover-card" data-name="<?= e(strtolower($w['name'])) ?>" data-bio="<?= e(strtolower($w['bio']??'')) ?>" data-badges="<?= e($badgesLower) ?>" data-address="<?= e(strtolower($w['address']??'')) ?>" data-rating="<?= (float)$w['rating'] ?>" data-jobs="<?= (int)$w['completed_jobs'] ?>" data-available="<?= $available?1:0 ?>">
<div class="discover-card-top <?= $palette[(int)$w['id']%4] ?>"><span class="discover-card-kicker">CHOREHIVE CLEANER</span><span class="availability-pill <?= $available?'available':'busy' ?>"><i></i><?= $available?'Available':'Currently booked' ?></span><?php if($avatar): ?><img class="discover-avatar" src="<?= e($avatar) ?>" alt="<?= e($w['name']) ?> profile photo"><?php else: ?><div class="discover-avatar discover-initials"><?= e(initials($w['name'])) ?></div><?php endif; ?><span class="online-dot <?= $online?'is-online':'' ?>" title="<?= $online?'Recently active':'Not recently active' ?>"></span><?php if(!empty($w['is_favorited'])): ?><span class="fav-star" title="In your favorites">★</span><?php endif; ?></div>
<div class="discover-card-body"><div class="discover-name-row"><h3><?= e($w['name']) ?></h3><span class="verified-mini" title="Profile reviewed for marketplace listing; this is not an identity or background check.">✓</span></div><div class="discover-rating"><span>★</span> <?= $w['review_count']?number_format((float)$w['rating'],1):'New' ?> <small><?= (int)$w['review_count'] ?> review<?= (int)$w['review_count']===1?'':'s' ?></small></div><p class="discover-bio"><?= e(mb_strimwidth(trim((string)$w['bio'])?:'Ready to help make your home feel fresh and comfortable.',0,125,'…')) ?></p><div class="discover-mini-stats"><span>✦ <?= (int)$w['badge_count'] ?> badges</span><span>✓ <?= (int)$w['completed_jobs'] ?> completed jobs</span></div><div class="discover-actions"><a class="btn btn-sm" href="worker.php?id=<?= (int)$w['id'] ?>">View profile</a><a class="btn btn-sm btn-sec" href="../chat.php?user_id=<?= (int)$w['id'] ?>">Message</a></div></div></article>
<?php endforeach; endif; ?></div>
<p id="noWorkersFound" class="muted" style="display:none;text-align:center;padding:24px;">No workers match that search. Try a different name, skill, or area.</p>
<script>
(()=>{
  const grid=document.getElementById('workerGrid');
  if(!grid) return;
  const search=document.getElementById('workerSearch');
  const sort=document.getElementById('workerSort');
  const filterBar=document.getElementById('workerBadgeFilters');
  let activeBadge='';
  const cards=()=>Array.from(grid.querySelectorAll('.discover-card'));
  function matchesBadge(card){
    if(!activeBadge) return true;
    const badges=' '+(card.dataset.badges||'').split('|').map(s=>s.trim()).join(' ')+' ';
    return badges.includes(' '+activeBadge+' ');
  }
  function update(){
    const q=search.value.trim().toLowerCase();
    let list=cards();
    list.forEach(c=>{
      const hay=(c.dataset.name+' '+c.dataset.bio+' '+c.dataset.badges+' '+c.dataset.address);
      const textOk=q===''||hay.includes(q);
      const badgeOk=matchesBadge(c);
      c.hidden=!(textOk&&badgeOk);
    });
    list.sort((a,b)=>{
      if(sort.value==='rating') return +b.dataset.rating-+a.dataset.rating;
      if(sort.value==='jobs') return +b.dataset.jobs-+a.dataset.jobs;
      if(sort.value==='available') return +b.dataset.available-+a.dataset.available;
      return 0;
    }).forEach(c=>grid.appendChild(c));
    document.getElementById('noWorkersFound').style.display=list.some(c=>!c.hidden)?'none':'block';
  }
  search.addEventListener('input',update);
  sort.addEventListener('change',update);
  if(filterBar){
    filterBar.addEventListener('click',e=>{
      const btn=e.target.closest('.badge-filter');
      if(!btn) return;
      filterBar.querySelectorAll('.badge-filter').forEach(b=>b.classList.remove('is-active'));
      btn.classList.add('is-active');
      activeBadge=btn.dataset.badge;
      update();
    });
  }
})();
</script>
<style>
.worker-badge-filters{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 18px;}
.worker-badge-filters .badge-filter{cursor:pointer;font-family:inherit;font-size:.82rem;font-weight:600;color:#4a5a73;background:#fff;border:1px solid #e3e9f2;padding:7px 14px;border-radius:999px;transition:background .15s,color .15s,border-color .15s,transform .15s;}
.worker-badge-filters .badge-filter:hover{border-color:#e0aa33;transform:translateY(-1px);}
.worker-badge-filters .badge-filter.is-active{background:#17427a;border-color:#17427a;color:#fff;}
.worker-badge-filters .badge-filter small{opacity:.7;margin-left:4px;}
.fav-star{position:absolute;top:10px;right:10px;font-size:1.1rem;color:#e0aa33;text-shadow:0 1px 2px rgba(0,0,0,.2);z-index:2;}
.discover-card-top{position:relative;}
</style>
<?php require '../footer.php'; ?>
