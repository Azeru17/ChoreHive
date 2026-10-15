<?php
require '../config.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    verify_csrf();
    $name = v_str('name', 100, true, 'Service name');
    $desc = v_str('description', 500, false, 'Description');
    $price = v_money('base_price');
    if ($price <= 0) fail('Price must be greater than zero.');
    $dur = v_int('duration_hours', 1, 24, 'Duration');
    $stmt = $conn->prepare("INSERT INTO services (name,description,base_price,duration_hours) VALUES (?,?,?,?)");
    $stmt->bind_param('ssdi', $name, $desc, $price, $dur);
    $stmt->execute();
    flash('Service added.');
    header('Location: services.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verify_csrf();
    $sid = v_int('delete', 1, PHP_INT_MAX, 'Service');
    $used = $conn->query("SELECT COUNT(*) c FROM bookings WHERE service_id=$sid")->fetch_assoc()['c'];
    if ($used) { flash('Cannot delete — service is used by bookings.', 'error'); }
    else { $conn->query("DELETE FROM services WHERE id=$sid"); flash('Service deleted.'); }
    header('Location: services.php'); exit;
}
$title = 'Manage Services';
require '../layout.php';

$res = $conn->query("SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.service_id=s.id) bookings FROM services s");
?>
<h1>Manage Services</h1>
<div class="card">
  <h2>Add service</h2>
  <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div style="flex:2;"><label>Name</label><input name="name" maxlength="100" required></div>
    <div style="flex:3;"><label>Description</label><input name="description" maxlength="500"></div>
    <div><label>Price (₱)</label><input type="number" step="0.01" min="0.01" name="base_price" required></div>
    <div><label>Hours</label><input type="number" name="duration_hours" min="1" max="24" value="2"></div>
    <button name="add" value="1">Add</button>
  </form>
</div>
<div class="card">
  <div class="table-wrap">
  <table>
    <tr><th>Name</th><th>Description</th><th>Base price</th><th>Duration</th><th>Bookings</th><th></th></tr>
    <?php while ($s = $res->fetch_assoc()): ?>
      <tr><td><b><?= e($s['name']) ?></b></td><td><?= e($s['description']) ?></td>
      <td>₱<?= number_format($s['base_price'],2) ?></td><td><?= $s['duration_hours'] ?>h</td><td><?= $s['bookings'] ?></td>
      <td><form method="post"><?= csrf_field() ?><button name="delete" value="<?= $s['id'] ?>" class="btn btn-sm btn-sec">Delete</button></form></td></tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>
<?php require '../footer.php'; ?>
