<?php
require 'config.php';
require_once 'worker_data.php';
require_marketplace_schema();
$title = 'Welcome';
$plain = true; // standalone page, skip the app layout
$u = current_user();
$base = ch_base();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ChoreHive — Cleaning you can verify</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/favicon.png">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins">
<style>
  :root {
    --honey:#e0aa33; --honey-dark:#c1861a; --navy:#17427a; --navy-dark:#0f2d55; --dark:#17427a;
    --bg:#f4f7fc; --card:#fff; --ink:#1f2c44; --muted:#6b7a90; --line:#e3e9f2; --line-2:#edf1f8;
    --radius:16px; --radius-sm:10px;
    --shadow:0 1px 2px rgba(23,66,122,.05), 0 6px 20px rgba(23,66,122,.07);
    --shadow-lg:0 14px 40px rgba(23,66,122,.16);
    --ease:cubic-bezier(.4,0,.2,1); --fast:.15s; --med:.25s;
  }
  * { box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body,h1,h2,h3,h4,h5 {font-family:"Poppins",sans-serif}
  body {font-size:16px; background:var(--bg); color:var(--ink); line-height:1.6; -webkit-font-smoothing:antialiased;}
  :focus-visible { outline:2px solid var(--navy); outline-offset:3px; border-radius:6px; }
  ::selection { background:rgba(224,170,51,.3); }
  ::-webkit-scrollbar { width:11px; }
  ::-webkit-scrollbar-track { background:transparent; }
  ::-webkit-scrollbar-thumb { background:#c9d5e6; border-radius:99px; border:3px solid transparent; background-clip:content-box; }
  ::-webkit-scrollbar-thumb:hover { background:#aebbcf; background-clip:content-box; }
  @keyframes pop-in { from{opacity:0;transform:translateY(-10px) scale(.97)} to{opacity:1;transform:none} }
  @keyframes rise { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }

  /* Sidebar */
  .w3-sidebar {
    background:rgba(255,255,255,.9) !important; -webkit-backdrop-filter:saturate(180%) blur(14px); backdrop-filter:saturate(180%) blur(14px);
    color:var(--ink); border-right:1px solid var(--line-2);
  }
  .w3-bar-item {color:#4a5a73 !important; border-radius:10px; margin:2px 8px; transition:background var(--fast) var(--ease), color var(--fast) var(--ease), transform var(--fast) var(--ease);}
  .w3-bar-item:hover {background:#eef3fb !important; color:var(--navy) !important; transform:translateX(3px);}
  .w3-bar-item.bg-honey:hover {background:var(--honey) !important; color:#2a2110 !important;}

  /* Accents & buttons */
  .honey {color:var(--honey-dark) !important;}
  .bg-honey {background:var(--honey) !important; color:#2a2110 !important;}
  .bg-dark {background:var(--navy) !important; color:#fff !important;}
  hr.honey {width:56px;border:5px solid var(--honey);border-radius:99px;margin-top:18px;}
  .btn-honey,.btn-dark {border-radius:12px !important; font-weight:700; transition:transform var(--fast) var(--ease), box-shadow var(--med) var(--ease);}
  .btn-honey {background:linear-gradient(180deg,#e7b23d,var(--honey)) !important; color:#2a2110 !important; box-shadow:0 2px 10px rgba(224,170,51,.35);}
  .btn-honey:hover {background:linear-gradient(180deg,#d99a1f,#c1861a) !important; color:#fff !important; transform:translateY(-2px); box-shadow:0 10px 24px rgba(224,170,51,.4);}
  .btn-dark {background:linear-gradient(180deg,#1e5295,var(--navy)) !important; color:#fff !important; box-shadow:0 2px 10px rgba(23,66,122,.28);}
  .btn-dark:hover {background:linear-gradient(180deg,#17427a,var(--navy-dark)) !important; transform:translateY(-2px); box-shadow:0 10px 24px rgba(23,66,122,.34);}

  /* Anchored sections */
  [id] { scroll-margin-top:92px; }
  .hero { animation:rise .6s var(--ease) both; }

  /* Showcase tiles */
  .photo-grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-rows:220px;gap:16px;margin-top:16px}
  .photo-grid .shot {position:relative;display:block;width:100%;height:100%;margin:0;border-radius:var(--radius);overflow:hidden;cursor:pointer;box-shadow:var(--shadow);transition:transform var(--med) var(--ease), box-shadow var(--med) var(--ease);}
  .photo-grid .shot:hover {transform:translateY(-3px); box-shadow:var(--shadow-lg);}
  .photo-grid .shot > img {display:block;width:100%;height:100%;object-fit:cover;transition:transform .5s var(--ease);}
  .photo-grid .shot:hover > img {transform:scale(1.07);}
  .photo-grid .shot figcaption {position:absolute;left:0;right:0;bottom:0;padding:46px 18px 16px;color:#fff;
    background:linear-gradient(to top,rgba(15,45,85,.94) 18%,rgba(15,45,85,.55) 58%,rgba(15,45,85,0));}
  .photo-grid .shot figcaption b {display:block;font-size:1rem;letter-spacing:-.01em;text-shadow:0 1px 4px rgba(0,0,0,.45);}
  .photo-grid .shot figcaption span {display:block;margin-top:3px;font-size:.8rem;line-height:1.45;opacity:.92;}
  @media (max-width:700px) {.photo-grid {grid-template-columns:minmax(0,1fr);grid-auto-rows:210px;gap:12px}}
  /* Showcase lightbox — the photo fills the card, caption overlaid on it */
  #modal01 {
    position:fixed; inset:0; width:100%; max-width:none;
    height:100%; height:100dvh; max-height:none;
    margin:0; padding:clamp(14px,4vw,44px); border:0; background:transparent;
    overflow:hidden; cursor:zoom-out;
    opacity:0; transition:opacity .26s var(--ease);
  }
  /* Only lay the dialog out while it is actually open — otherwise the
     UA default `dialog:not([open]){display:none}` would be overridden and
     a transparent full-screen layer would swallow every click. */
  #modal01[open] { display:grid; place-items:center; }
  #modal01.is-open { opacity:1; }
  #modal01::backdrop { background:rgba(8,22,40,.82); -webkit-backdrop-filter:blur(10px) saturate(140%); backdrop-filter:blur(10px) saturate(140%); }

  #modal01 .showcase-dialog-content {
    position:relative; display:block;
    width:auto; max-width:100%; max-height:100%; margin:0; overflow:hidden;
    border-radius:18px; background:#0b2242; cursor:default;
    box-shadow:0 30px 70px rgba(0,0,0,.45);
    opacity:0; transform:translateY(16px) scale(.965);
    transition:transform .34s var(--ease), opacity .26s var(--ease);
  }
  #modal01.is-open .showcase-dialog-content { opacity:1; transform:none; }

  /* Image fills the whole card — no stage, no letterboxing. */
  #img01 {
    display:block; width:auto; height:auto;
    max-width:100%; max-height:min(86vh,760px); object-fit:cover;
  }

  /* Caption sits on the photo over a soft scrim so it stays readable
     on bright and dark images alike. */
  #caption {
    position:absolute; left:0; right:0; bottom:0; z-index:2; pointer-events:none;
    padding:clamp(56px,13vh,104px) clamp(18px,3vw,28px) clamp(18px,3vw,26px);
    background:linear-gradient(to top, rgba(6,18,34,.95) 0%, rgba(6,18,34,.78) 34%, rgba(6,18,34,.42) 64%, rgba(6,18,34,0) 100%);
  }
  #caption b { display:block; max-width:100%; font-size:clamp(1.05rem,2.4vw,1.35rem); font-weight:750; letter-spacing:-.02em; color:#fff; text-shadow:0 2px 12px rgba(0,0,0,.55); }
  #caption span { display:block; max-width:52ch; margin-top:5px; font-size:clamp(.86rem,1.8vw,.98rem); line-height:1.5; color:rgba(255,255,255,.9); text-shadow:0 1px 10px rgba(0,0,0,.5); }

  #modal01 .showcase-dialog-close {
    position:absolute; top:6px; right:8px; z-index:4;
    display:grid; place-items:center; width:42px; height:42px; min-height:42px; padding:0;
    border:0; border-radius:8px; background:none; box-shadow:none;
    color:#fff; font-size:2.1rem; line-height:1; font-weight:300; cursor:pointer;
    text-shadow:0 1px 2px rgba(0,0,0,.75), 0 2px 12px rgba(0,0,0,.55);
    transition:background .18s var(--ease), transform .18s var(--ease), opacity .18s var(--ease);
  }
  #modal01 .showcase-dialog-close:hover { background:rgba(255,255,255,.16); box-shadow:none; transform:scale(1.06); }
  #modal01 .showcase-dialog-close:active { transform:scale(.92); }
  body.showcase-open { overflow:hidden; }
  @media (max-width:700px) {
    #modal01 { padding:12px; }
    #modal01 .showcase-dialog-content { border-radius:14px; }
    #img01 { max-height:78vh; }
    #modal01 .showcase-dialog-close { top:2px; right:4px; width:40px; height:40px; min-height:40px; }
    /* Smaller photos on phones need a tighter scrim, or the gradient eats the image. */
    #caption { padding:clamp(40px,9vh,72px) 16px 14px; }
    #caption b { font-size:1rem; }
    #caption span { font-size:.84rem; line-height:1.45; }
  }

  .t1{background:linear-gradient(135deg,#17427a,#0f2d55)} .t2{background:linear-gradient(135deg,#e0aa33,#c1861a)}
  .t3{background:linear-gradient(135deg,#2a5a9e,#17427a)} .t4{background:linear-gradient(135deg,#0f2d55,#17427a)}
  .t5{background:linear-gradient(135deg,#e0aa33,#b8860b)} .t6{background:linear-gradient(135deg,#cfe0f5,#9dc0ea)}

  /* Cards */
  .w3-card {border-radius:var(--radius) !important; border:1px solid var(--line-2); box-shadow:var(--shadow); transition:transform var(--med) var(--ease), box-shadow var(--med) var(--ease);}
  .w3-card:hover {transform:translateY(-4px); box-shadow:var(--shadow-lg);}
  .w3-card h3 {color:var(--navy); letter-spacing:-.015em;}
/* Worker cards (homepage + directory share these) */
  .workers-grid {display:flex;flex-wrap:wrap;gap:16px;margin-top:16px;}
  .worker-card {flex:1 1 250px;min-width:250px;max-width:340px;overflow:hidden;}
  .worker-top {height:132px;display:flex;align-items:center;justify-content:center;}
  .worker-top .avatar {width:82px;height:82px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-size:1.7rem;font-weight:700;color:#2a2110;background:rgba(255,255,255,.93);box-shadow:0 0 0 4px rgba(255,255,255,.35);}
  .worker-card h3 {margin:0;}
  .worker-meta {margin:3px 0 10px;font-size:.85rem;color:var(--muted);}
  .chips {display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;}
  .chip {font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:999px;background:#fdf3e3;color:var(--honey-dark);border:1px solid #f3e2bd;}
  .price-card li {border-bottom:1px solid var(--line-2); transition:background var(--fast) var(--ease);}
  .price-card li:hover {background:#f8fafd;}
  .price-card li:last-child {border-bottom:0;}
  input.w3-input {border-radius:var(--radius-sm) !important; border-color:var(--line) !important; transition:border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease);}
  input.w3-input:focus {border-color:var(--honey) !important; box-shadow:0 0 0 4px rgba(224,170,51,.16) !important;}
  .w3-jumbo {letter-spacing:-.032em; line-height:1.08;}

  @media (prefers-reduced-motion: reduce) {
    *,*::before,*::after { animation-duration:.001ms !important; transition-duration:.001ms !important; scroll-behavior:auto !important; }
  }
</style>
<link rel="stylesheet" href="<?= $base ?>/assets/css/chorehive-ui.css?v=<?= filemtime(__DIR__ . '/assets/css/chorehive-ui.css') ?>">
</head>
<body>

<!-- Shared site header (matches the in-app navigation) -->
<header class="site-header">
  <a class="site-brand" href="<?= $base ?>/index.php">
    <img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo">
    <span>ChoreHive</span>
  </a>
  <nav class="site-nav" id="siteNav" aria-label="Main navigation">
    <a href="#showcase">Showcase</a>
    <a href="#services">Services</a>
    <a href="#workers">Our Workers</a>
    <a href="#packages">Packages</a>
    <a href="#contact">Contact</a>
  </nav>
  <div class="site-actions">
    <?php if ($u): ?>
      <a class="btn-honey" href="<?= $base ?>/<?= e($u['role']) ?>/dashboard.php">My Dashboard</a>
    <?php else: ?>
      <a class="btn-dark" href="<?= $base ?>/login.php">Log In</a>
      <a class="btn-honey" href="<?= $base ?>/register.php">Get Started</a>
    <?php endif; ?>
    <button class="site-burger" id="siteBurger" type="button" aria-label="Toggle navigation menu" aria-controls="siteNav" aria-expanded="false">&#9776;</button>
  </div>
</header>

<!-- !PAGE CONTENT! -->
<div class="w3-main" style="margin-right:clamp(16px,3vw,40px)">

  <!-- Header / Hero -->
  <div class="w3-container hero" style="margin-top:26px" id="showcase">
    <h1 class="w3-jumbo" style="line-height:1.1;"><b>Cleaning you can<br><span class="honey">verify.</span></b></h1>
    <hr class="honey w3-round">
    <p style="max-width:640px;margin-top:16px;">A cleaner home starts with a plan that fits your life. Choose the tasks that matter, book a local cleaner, and review the results when the work is done.</p>
    <p style="margin-top:24px;">
      <?php if ($u): ?>
        <a class="w3-button btn-honey w3-padding-large" href="<?= $base ?>/<?= e($u['role']) ?>/dashboard.php">Go to my dashboard →</a>
      <?php else: ?>
        <a class="w3-button btn-honey w3-padding-large" href="<?= $base ?>/register.php">Get started</a>
        <a class="w3-button btn-dark w3-padding-large" style="margin-left:12px;" href="<?= $base ?>/login.php">Log in</a>
      <?php endif; ?>
    </p>
  </div>

  <!-- Photo gallery (modal) -->
  <div class="photo-grid">
    <figure class="shot" tabindex="0" role="button" aria-label="View Disinfection and Sanitization showcase photo" data-title="Disinfection &amp; Sanitization" data-desc="High-touch surfaces wiped with hospital-grade disinfectant." onclick="openShowcase(this)">
      <img src="<?= $base ?>/assets/img/sanitize.jpg" alt="Cleaning professional in protective gear disinfecting a table" loading="lazy">
      <figcaption><b>Disinfection &amp; Sanitization</b><span>High-touch surfaces wiped with hospital-grade disinfectant.</span></figcaption>
    </figure>
    <figure class="shot" tabindex="0" role="button" aria-label="View Glass and Window Care showcase photo" data-title="Glass &amp; Window Care" data-desc="Sills, panes and frames finished streak-free." onclick="openShowcase(this)">
      <img src="<?= $base ?>/assets/img/glass.jpg" alt="Window being soaped and cleaned with a squeegee" loading="lazy">
      <figcaption><b>Glass &amp; Window Care</b><span>Sills, panes and frames finished streak-free.</span></figcaption>
    </figure>
    <figure class="shot" tabindex="0" role="button" aria-label="View Kitchen Deep Clean showcase photo" data-title="Kitchen Deep Clean" data-desc="Counters, sinks and grease-prone areas handled." onclick="openShowcase(this)">
      <img src="<?= $base ?>/assets/img/kitchen.jpg" alt="Clean kitchen sink and countertop" loading="lazy">
      <figcaption><b>Kitchen Deep Clean</b><span>Counters, sinks and grease-prone areas handled.</span></figcaption>
    </figure>
    <figure class="shot" tabindex="0" role="button" aria-label="View Surface Scrubbing showcase photo" data-title="Surface Scrubbing" data-desc="Detail wiping across every room on your checklist." onclick="openShowcase(this)">
      <img src="<?= $base ?>/assets/img/surface.jpg" alt="Hands scrubbing a surface with a sponge" loading="lazy">
      <figcaption><b>Surface Scrubbing</b><span>Detail wiping across every room on your checklist.</span></figcaption>
    </figure>
    <figure class="shot" tabindex="0" role="button" aria-label="View Floor and Tile Care showcase photo" data-title="Floor &amp; Tile Care" data-desc="Mopped, scrubbed and left to dry cleanly." onclick="openShowcase(this)">
      <img src="<?= $base ?>/assets/img/floor.jpg" alt="Mopping a tiled floor" loading="lazy">
      <figcaption><b>Floor &amp; Tile Care</b><span>Mopped, scrubbed and left to dry cleanly.</span></figcaption>
    </figure>
    <figure class="shot" tabindex="0" role="button" aria-label="View Tools and Supplies showcase photo" data-title="Tools &amp; Supplies" data-desc="Sanitised equipment brought fresh to every job." onclick="openShowcase(this)">
      <img src="<?= $base ?>/assets/img/supplies.jpg" alt="Cleaning supplies and equipment" loading="lazy">
      <figcaption><b>Tools &amp; Supplies</b><span>Sanitised equipment brought fresh to every job.</span></figcaption>
    </figure>
  </div>

  <!-- Image Dialog -->
  <dialog id="modal01" aria-labelledby="caption">
    <figure class="showcase-dialog-content">
      <button class="showcase-dialog-close" type="button" aria-label="Close image">&times;</button>
      <img id="img01" alt="ChoreHive cleaning service">
      <figcaption id="caption">
        <b id="captionTitle"></b>
        <span id="captionDesc"></span>
      </figcaption>
    </figure>
  </dialog>

  <!-- Services -->
  <div class="w3-container" id="services" style="margin-top:75px">
    <h1 class="w3-xxxlarge honey"><b>Services.</b></h1>
    <hr class="honey w3-round">
    <p>Choose the tasks and review the worker’s photos when the job is done. Cash is recorded after your review; GCash and Maya checkout must be completed before the worker starts. Service prices are shown before you book.</p>
    <?php
    $res = $conn->query("SELECT * FROM services ORDER BY base_price");
    while ($s = $res->fetch_assoc()): ?>
      <div class="w3-card w3-white w3-round" style="margin-top:16px;padding:18px 22px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <div style="flex:1;min-width:220px;">
          <h3 style="margin:0;"><?= e($s['name']) ?></h3>
          <p class="w3-opacity" style="margin:4px 0 0;"><?= e($s['description']) ?></p>
          <p style="margin:6px 0 0;font-size:.9rem;">⏱ approx. <?= (int)$s['duration_hours'] ?> hour<?= $s['duration_hours'] > 1 ? 's' : '' ?></p>
        </div>
        <div style="text-align:right;">
          <h2 class="honey" style="margin:0;">₱<?= number_format($s['base_price'], 2) ?></h2>
          <?php if ($u && $u['role'] === 'client'): ?>
            <a class="w3-button btn-honey" style="margin-top:6px;" href="<?= $base ?>/client/workers.php">Choose a cleaner</a>
          <?php elseif (!$u): ?>
            <a class="w3-button btn-dark" style="margin-top:6px;" href="<?= $base ?>/register.php">Sign up to book</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <!-- Our Workers -->
  <div class="w3-container" id="workers" style="margin-top:75px">
    <h1 class="w3-xxxlarge honey"><b>Our Workers.</b></h1>
    <hr class="honey w3-round">
    <p>Compare worker profiles, completed-job counts, customer reviews, and badges managed by ChoreHive administrators. These profile details help you choose; they are not a substitute for independent identity or background checks.</p>
  </div>

  <!-- Worker profiles are sourced from registered worker accounts. -->
  <?php
  $homeWorkers = ch_workers($conn);
  $homeCount   = count($homeWorkers);
  $homeShown   = array_slice($homeWorkers, 0, 3, true);
  ?>
  <div class="workers-grid">
    <?php foreach ($homeShown as $w): ?>
      <div class="w3-card w3-white w3-round worker-card">
        <div class="worker-top t2"><span class="avatar"><?= e(ch_initials($w['name'])) ?></span></div>
        <div class="w3-container w3-padding">
          <h3><?= e($w['name']) ?></h3>
          <p class="worker-meta"><?= e(ch_worker_status($w)) ?> · <?= $w['jobs'] ?> job<?= $w['jobs'] === 1 ? '' : 's' ?> done</p>
          <p><?= $w['bio'] ? e($w['bio']) : 'A ChoreHive worker profile, ready to receive bookings.' ?></p>
          <?php if ($w['badges']): ?>
            <div class="chips">
              <?php foreach ($w['badges'] as $b): ?>
                <span class="chip" title="<?= e($b['description']) ?>">🏅 <?= e($b['name']) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <p style="margin-top:24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <a class="w3-button btn-dark" href="<?= $base ?>/workers.php">See more →</a>
    <span class="w3-opacity" style="font-size:.9rem;">Browse worker profiles and their administrator-managed badges.</span>
  </p>

  <!-- Payment and verification flow -->
  <div class="w3-container" id="packages" style="margin-top:75px">
    <h1 class="w3-xxxlarge honey"><b>Payment &amp; verification.</b></h1>
    <hr class="honey w3-round">
    <p>Choose a listed cleaning service and see its price before booking. Tell the worker what to do with a custom checklist, then review the job photos and completion status.</p>
  </div>

  <div class="w3-grid" style="grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:16px;">
    <div>
      <ul class="w3-ul w3-white w3-center w3-card price-card w3-round" style="overflow:hidden;">
        <li class="bg-dark w3-xlarge w3-padding-32">Cash</li>
        <li class="w3-padding-16">Pay the worker after the job</li>
        <li class="w3-padding-16">Review the checklist and photos</li>
        <li class="w3-padding-16">Confirm cash payment in ChoreHive</li>
        <li class="w3-padding-16">
          <h2>Pay after review</h2>
          <span class="w3-opacity">Customer confirms cash payment</span>
        </li>
        <li class="w3-padding-24">
          <a class="w3-button btn-dark w3-padding-large" href="#services">See services</a>
        </li>
      </ul>
    </div>
    <div>
      <ul class="w3-ul w3-white w3-center w3-card price-card w3-round" style="overflow:hidden;">
        <li class="bg-honey w3-xlarge w3-padding-32"><b>GCash or Maya</b></li>
        <li class="w3-padding-16">Checkout securely through PayMongo</li>
        <li class="w3-padding-16">Payment is confirmed before work starts</li>
        <li class="w3-padding-16">Review the completed job afterward</li>
        <li class="w3-padding-16">
          <h2>Pay before work</h2>
          <span class="w3-opacity">Receipt available after payment</span>
        </li>
        <li class="w3-padding-24">
          <a class="w3-button btn-honey w3-padding-large" href="<?= $base ?>/register.php">Get started</a>
        </li>
      </ul>
    </div>
  </div>

  <!-- Contact -->
  <div class="w3-container" id="contact" style="margin-top:75px">
    <h1 class="w3-xxxlarge honey"><b>Contact.</b></h1>
    <hr class="honey w3-round">
    <p>Questions about a booking, your checklist, or becoming a ChoreHive worker? Send us a message — we'd love to hear from you!</p>
    <form action="<?= $base ?>/contact.php" method="post">
      <?= csrf_field() ?>
      <div class="w3-section">
        <label>Name</label>
        <input class="w3-input w3-border" type="text" name="name" required>
      </div>
      <div class="w3-section">
        <label>Email</label>
        <input class="w3-input w3-border" type="email" name="email" required>
      </div>
      <div class="w3-section">
        <label for="contactMessage">Message</label>
        <textarea class="w3-input w3-border" id="contactMessage" name="message" rows="5" maxlength="2000" required></textarea>
      </div>
      <button type="submit" class="w3-button w3-block w3-padding-large btn-honey w3-margin-bottom">Send Message</button>
    </form>
  </div>

<!-- End page content -->
</div>

<!-- Footer -->
<div class="w3-container w3-center w3-padding-32 bg-dark" style="margin-top:75px;">
  <p><img src="<?= $base ?>/assets/logo-light.png" alt="ChoreHive logo" style="width:46px;height:46px;border-radius:50%;vertical-align:middle;margin-right:8px;"> <b>Chore<span class="honey">Hive</span></b> — Cleaning you can verify. © <?= date('Y') ?></p>
</div>

<script>
const imageDialog = document.getElementById("modal01");
const dialogTile = document.getElementById("img01");
const captionTitle = document.getElementById("captionTitle");
const captionDesc = document.getElementById("captionDesc");
const dialogCloseBtn = imageDialog.querySelector(".showcase-dialog-close");

/* Mobile navigation (burger -> dropdown panel) */
(function () {
  const burger = document.getElementById("siteBurger");
  const nav = document.getElementById("siteNav");
  if (!burger || !nav) return;
  const setOpen = (open) => {
    nav.classList.toggle("open", open);
    burger.setAttribute("aria-expanded", open ? "true" : "false");
  };
  burger.addEventListener("click", () => setOpen(!nav.classList.contains("open")));
  nav.addEventListener("click", (ev) => { if (ev.target.closest("a")) setOpen(false); });
  document.addEventListener("keydown", (ev) => { if (ev.key === "Escape") setOpen(false); });
  window.addEventListener("resize", () => { if (window.innerWidth > 900) setOpen(false); });
})();
function openShowcase(el) {
  var img = el.querySelector("img");
  dialogTile.src = img ? img.getAttribute("src") : "";
  dialogTile.alt = img ? img.alt : "";
  captionTitle.textContent = el.dataset.title || "";
  captionDesc.textContent = el.dataset.desc || "";
  imageDialog.showModal();
  document.body.classList.add("showcase-open");
  requestAnimationFrame(function () {
    requestAnimationFrame(function () { imageDialog.classList.add("is-open"); });
  });
  dialogCloseBtn.focus();
}
function closeShowcase() {
  imageDialog.classList.remove("is-open");
  document.body.classList.remove("showcase-open");
  window.setTimeout(function () { imageDialog.close(); }, 260);
}
document.querySelectorAll(".photo-grid .shot").forEach(function (tile) {
  tile.addEventListener("keydown", function (event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      tile.click();
    }
  });
});
dialogCloseBtn.addEventListener("click", closeShowcase);
imageDialog.addEventListener("cancel", function (event) {
  event.preventDefault();
  closeShowcase();
});
imageDialog.addEventListener("click", function (event) {
  if (event.target === imageDialog) closeShowcase();
});
</script>

</body>
</html>
