<?php
require 'config.php';
$base = ch_base();
$u = current_user();
$ok = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = v_str('name', 100, true, 'Name');
    $email = v_email();
    $message = v_str('message', 2000, true, 'Message');
    $stmt = $conn->prepare('INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $name, $email, $message);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) $error = 'We could not save your message right now. Please try again later.';
} else {
    $error = 'Please submit the contact form from the homepage.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $ok ? 'Message sent' : 'Contact' ?> — ChoreHive</title>
<link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
<style>body{background:#f4f7fc;font-family:Poppins,sans-serif}</style>
<link rel="stylesheet" href="<?= $base ?>/assets/css/chorehive-ui.css?v=<?= filemtime(__DIR__ . '/assets/css/chorehive-ui.css') ?>">
</head>
<body>
<header class="site-header">
  <a class="site-brand" href="<?= $base ?>/index.php">
    <img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo">
    <span>ChoreHive</span>
  </a>
  <nav class="site-nav" id="siteNav" aria-label="Main navigation">
    <a href="<?= $base ?>/index.php#showcase">Showcase</a>
    <a href="<?= $base ?>/index.php#services">Services</a>
    <a href="<?= $base ?>/index.php#packages">Packages</a>
    <a href="<?= $base ?>/workers.php">Our Workers</a>
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
<div class="w3-container" style="max-width:560px;margin:40px auto;text-align:center;">
  <div class="w3-card w3-white w3-round w3-padding-32">
    <img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo" style="width:74px;height:74px;border-radius:50%;box-shadow:0 0 0 3px rgba(224,170,51,.55);margin-bottom:8px;">
    <?php if ($ok): ?>
      <h2>Thanks, <?= e($name ?? 'friend') ?>!</h2>
      <p>Your message was received. An administrator can review it and reply to <b><?= e($email ?? '') ?></b>.</p>
    <?php else: ?>
      <h2>Could not send</h2>
      <p><?= e($error) ?></p>
    <?php endif; ?>
    <a class="w3-button w3-padding-large" style="background:#e0aa33;color:#241d12;border-radius:10px;font-weight:700;" href="<?= $base ?>/index.php">← Back to home</a>
  </div>
</div>
<script>
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
</script>
</body>
</html>
