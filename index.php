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
  [id] { scroll-margin-top:24px; }
  .hero { animation:rise .6s var(--ease) both; }

  /* Showcase tiles */
  .photo-grid {column-count:2;column-gap:16px}
  .photo-grid .shot {position:relative;display:block;width:100%;margin-top:16px;border-radius:var(--radius);overflow:hidden;cursor:pointer;box-shadow:var(--shadow);transition:transform var(--med) var(--ease), box-shadow var(--med) var(--ease);}
  .photo-grid .shot:hover {transform:translateY(-5px); box-shadow:var(--shadow-lg);}
  .photo-grid .shot > img {display:block;width:100%;height:100%;object-fit:cover;transition:transform .5s var(--ease);}
  .photo-grid .shot:hover > img {transform:scale(1.07);}
  .photo-grid .shot figcaption {position:absolute;left:0;right:0;bottom:0;padding:46px 18px 16px;color:#fff;
    background:linear-gradient(to top,rgba(15,45,85,.94) 18%,rgba(15,45,85,.55) 58%,rgba(15,45,85,0));}
  .photo-grid .shot figcaption b {display:block;font-size:1rem;letter-spacing:-.01em;text-shadow:0 1px 4px rgba(0,0,0,.45);}
  .photo-grid .shot figcaption span {display:block;margin-top:3px;font-size:.8rem;line-height:1.45;opacity:.92;}
  @media (max-width:600px) {.photo-grid {column-count:1}}
  dialog {border:0;padding:0;width:min(900px,90%);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-lg);animation:pop-in var(--med) var(--ease);}
  dialog::backdrop {background:rgba(15,45,85,.82); -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px);}

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

<!-- Sidebar/menu -->
<nav class="w3-sidebar w3-collapse w3-top w3-large w3-padding" style="z-index:3;width:300px;font-weight:bold;" id="mySidebar"><br>
  <a href="javascript:void(0)" onclick="w3_close()" class="w3-button w3-hide-large w3-display-topleft" style="width:100%;font-size:22px">Close Menu</a>
  <div class="w3-container">
    <h3 class="w3-padding-64" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo" style="width:54px;height:54px;border-radius:50%;box-shadow:0 0 0 2px rgba(224,170,51,.55);">
      <span><b>Chore<span class="honey">Hive</span></b><br><span style="font-size:.6em;font-weight:400;color:var(--ink);opacity:.7;">Cleaning you can verify.</span></span>
    </h3>
  </div>
  <div class="w3-bar-block">
    <a href="<?= $base ?>/index.php" class="w3-bar-item w3-button">Home</a>
    <a href="#showcase" onclick="w3_close()" class="w3-bar-item w3-button">Showcase</a>
    <a href="#services" onclick="w3_close()" class="w3-bar-item w3-button">Services</a>
    <a href="#workers" onclick="w3_close()" class="w3-bar-item w3-button">Our Workers</a>
    <a href="#packages" onclick="w3_close()" class="w3-bar-item w3-button">Packages</a>
    <a href="#contact" onclick="w3_close()" class="w3-bar-item w3-button">Contact</a>
    <?php if ($u): ?>
      <a href="<?= $base ?>/<?= e($u['role']) ?>/dashboard.php" onclick="w3_close()" class="w3-bar-item w3-button bg-honey" style="margin-top:12px;">My Dashboard →</a>
    <?php else: ?>
      <a href="<?= $base ?>/register.php" onclick="w3_close()" class="w3-bar-item w3-button bg-honey" style="margin-top:12px;">Get Started — Free</a>
      <a href="<?= $base ?>/login.php" onclick="w3_close()" class="w3-bar-item w3-button">Log In</a>
    <?php endif; ?>
  </div>
</nav>

<!-- Top menu on small screens -->
<header class="w3-container w3-top w3-hide-large w3-xlarge w3-padding" style="background:var(--card);color:var(--navy);border-bottom:1px solid var(--line);">
  <a href="javascript:void(0)" class="w3-button w3-margin-right" style="color:var(--navy);" onclick="w3_open()">☰</a>
  <img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo" style="width:38px;height:38px;border-radius:50%;box-shadow:0 0 0 2px rgba(224,170,51,.55);vertical-align:middle;">
  <span style="vertical-align:middle;">Chore<span class="honey">Hive</span></span>
</header>

<!-- Overlay effect when opening sidebar on small screens -->
<div class="w3-overlay w3-hide-large" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>

<!-- !PAGE CONTENT! -->
<div class="w3-main" style="margin-left:340px;margin-right:40px">

  <!-- Header / Hero -->
  <div class="w3-container hero" style="margin-top:80px" id="showcase">
    <h1 class="w3-jumbo" style="line-height:1.1;"><b>Cleaning you can<br><span class="honey">verify.</span></b></h1>
    <hr class="honey w3-round">
    <p style="max-width:640px;margin-top:16px;">Book a cleaner, build your own task checklist, and review <b>before &amp; after photos</b> when the work is done. Cash is confirmed after you review the job; GCash and Maya are paid through PayMongo before work starts.</p>
    <p style="margin-top:24px;">
      <?php if ($u): ?>
        <a class="w3-button btn-honey w3-padding-large" href="<?= $base ?>/<?= e($u['role']) ?>/dashboard.php">Go to my dashboard →</a>
      <?php else: ?>
        <a class="w3-button btn-honey w3-padding-large" href="<?= $base ?>/register.php">Get started — it's free</a>
        <a class="w3-button btn-dark w3-padding-large" href="<?= $base ?>/login.php">I already have an account</a>
      <?php endif; ?>
    </p>
  </div>

  <!-- Photo gallery (modal) -->
  <div class="photo-grid">
    <figure class="shot" style="height:265px;margin:0;" onclick="onClick(this, 'Disinfection &amp; sanitization &#8212; high-touch surfaces are wiped down with hospital-grade disinfectant before the job is marked done.')">
      <img src="<?= $base ?>/assets/img/sanitize.jpg" alt="Cleaning professional in protective gear disinfecting a table" loading="lazy">
      <figcaption><b>Disinfection &amp; Sanitization</b><span>High-touch surfaces wiped with hospital-grade disinfectant.</span></figcaption>
    </figure>
    <figure class="shot" style="height:190px;margin:0;" onclick="onClick(this, 'Glass &amp; window care &#8212; sills, panes and frames are cleaned to a streak-free finish.')">
      <img src="<?= $base ?>/assets/img/glass.jpg" alt="Window being soaped and cleaned with a squeegee" loading="lazy">
      <figcaption><b>Glass &amp; Window Care</b><span>Sills, panes and frames finished streak-free.</span></figcaption>
    </figure>
    <figure class="shot" style="height:230px;margin:0;" onclick="onClick(this, 'Kitchen deep clean &#8212; counters, sinks and grease-prone areas get the extra attention.')">
      <img src="<?= $base ?>/assets/img/kitchen.jpg" alt="Clean kitchen sink and countertop" loading="lazy">
      <figcaption><b>Kitchen Deep Clean</b><span>Counters, sinks and grease-prone areas handled.</span></figcaption>
    </figure>
    <figure class="shot" style="height:180px;margin:0;" onclick="onClick(this, 'Surface scrubbing &#8212; detail wiping across every finished room in your checklist.')">
      <img src="<?= $base ?>/assets/img/surface.jpg" alt="Hands scrubbing a surface with a sponge" loading="lazy">
      <figcaption><b>Surface Scrubbing</b><span>Detail wiping across every room on your checklist.</span></figcaption>
    </figure>
    <figure class="shot" style="height:250px;margin:0;" onclick="onClick(this, 'Floor &amp; tile care &#8212; floors are mopped, scrubbed and left to dry cleanly.')">
      <img src="<?= $base ?>/assets/img/floor.jpg" alt="Mopping a tiled floor" loading="lazy">
      <figcaption><b>Floor &amp; Tile Care</b><span>Mopped, scrubbed and left to dry cleanly.</span></figcaption>
    </figure>
    <figure class="shot" style="height:205px;margin:0;" onclick="onClick(this, 'Tools &amp; supplies &#8212; equipment is sanitised and brought fresh to every booking.')">
      <img src="<?= $base ?>/assets/img/supplies.jpg" alt="Cleaning supplies and equipment" loading="lazy">
      <figcaption><b>Tools &amp; Supplies</b><span>Sanitised equipment brought fresh to every job.</span></figcaption>
    </figure>
  </div>

  <!-- Image Dialog -->
  <dialog id="modal01" class="bg-dark w3-animate-zoom">
    <button class="w3-button bg-dark w3-xxlarge w3-display-topright" commandfor="modal01" command="close" title="Close image">&times;</button>
    <div class="w3-center w3-transparent w3-padding-64">
      <img id="img01" alt="ChoreHive cleaning service" style="display:block;max-width:100%;max-height:68vh;margin:0 auto;border-radius:10px;">
      <p id="caption" class="w3-large" style="color:#fff;"></p>
    </div>
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
            <a class="w3-button btn-honey" style="margin-top:6px;" href="<?= $base ?>/client/book.php">Book now</a>
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
const sidebar = document.getElementById("mySidebar");
const overlay = document.getElementById("myOverlay");
const imageDialog = document.getElementById("modal01");
const dialogTile = document.getElementById("img01");
const caption = document.getElementById("caption");

function w3_open() {
  sidebar.style.display = "block";
  overlay.style.display = "block";
}
function w3_close() {
  sidebar.style.display = "none";
  overlay.style.display = "none";
}
function onClick(el, text) {
  var img = el.querySelector("img");
  dialogTile.src = img ? img.getAttribute("src") : "";
  dialogTile.alt = img ? img.alt : "";
  caption.textContent = text;
  imageDialog.showModal();
}
</script>

</body>
</html>
