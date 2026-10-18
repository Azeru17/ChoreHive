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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    verify_csrf();
    $sid = v_int('edit', 1, PHP_INT_MAX, 'Service');
    $name = v_str('edit_name', 100, true, 'Service name');
    $desc = v_str('edit_description', 500, false, 'Description');
    $price = v_money('edit_base_price');
    if ($price <= 0) fail('Price must be greater than zero.');
    $dur = v_int('edit_duration_hours', 1, 24, 'Duration');
    $stmt = $conn->prepare("UPDATE services SET name=?, description=?, base_price=?, duration_hours=? WHERE id=?");
    $stmt->bind_param('ssdii', $name, $desc, $price, $dur, $sid);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) fail('Service not found or no changes made.');
    flash('Service updated.');
    header('Location: services.php'); exit;
}
$title = 'Manage Services';
require '../layout.php';

$res = $conn->query("SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.service_id=s.id) bookings FROM services s");
?>
<h1>Manage Services</h1>
<div class="card">
  <h2>Add service</h2>
  <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div style="flex:2;min-width:150px;display:flex;flex-direction:column;"><label for="svcName" style="margin-bottom:4px;">Name</label><input id="svcName" name="name" maxlength="100" required></div>
    <div style="flex:3;min-width:200px;display:flex;flex-direction:column;"><label for="svcDesc" style="margin-bottom:4px;">Description</label><input id="svcDesc" name="description" maxlength="500"></div>
    <div style="min-width:100px;display:flex;flex-direction:column;"><label for="svcPrice" style="margin-bottom:4px;">Price (₱)</label><input id="svcPrice" type="number" step="0.01" min="0.01" name="base_price" required></div>
    <div style="min-width:80px;display:flex;flex-direction:column;"><label for="svcHours" style="margin-bottom:4px;">Hours</label><input id="svcHours" type="number" name="duration_hours" min="1" max="24" value="2"></div>
    <div style="display:flex;align-items:center;padding-bottom:2px;"><button name="add" value="1" class="btn">Add</button></div>
  </form>
</div>
<div class="card">
  <div class="table-wrap">
  <table>
    <tr><th>Name</th><th>Description</th><th>Base price</th><th>Duration</th><th>Bookings</th><th style="white-space:nowrap;">Actions</th></tr>
    <?php while ($s = $res->fetch_assoc()): ?>
      <tr><td><b><?= e($s['name']) ?></b></td><td><?= e($s['description']) ?></td>
      <td>₱<?= number_format($s['base_price'],2) ?></td><td><?= $s['duration_hours'] ?>h</td><td><?= $s['bookings'] ?></td>
      <td style="white-space:nowrap;">
        <button type="button" class="btn btn-sm" onclick="toggleEdit(<?= $s['id'] ?>)">Edit</button>
        <form method="post" style="display:inline;"><?= csrf_field() ?><button name="delete" value="<?= $s['id'] ?>" class="btn btn-sm btn-sec" style="white-space:nowrap;">Delete</button></form>
      </td></tr>
      <tr id="edit-row-<?= $s['id'] ?>" style="display:none;">
        <td colspan="6">
          <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <?= csrf_field() ?>
            <input type="hidden" name="edit" value="<?= $s['id'] ?>">
            <div style="flex:2;min-width:150px;"><label>Name</label><input name="edit_name" maxlength="100" value="<?= e($s['name']) ?>" required></div>
            <div style="flex:3;min-width:200px;"><label>Description</label><input name="edit_description" maxlength="500" value="<?= e($s['description']) ?>"></div>
            <div style="min-width:100px;"><label>Price (₱)</label><input type="number" step="0.01" min="0.01" name="edit_base_price" value="<?= e($s['base_price']) ?>" required></div>
            <div style="min-width:80px;"><label>Hours</label><input type="number" name="edit_duration_hours" min="1" max="24" value="<?= e($s['duration_hours']) ?>" required></div>
            <button type="submit" class="btn btn-sm">Save</button>
            <button type="button" class="btn btn-sm btn-sec" onclick="toggleEdit(<?= $s['id'] ?>)">Cancel</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>
<script>
function toggleEdit(id) {
  var row = document.getElementById('edit-row-' + id);
  if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>
<?php require '../footer.php'; ?>
