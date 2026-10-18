<?php
require '../config.php';
$u = require_role('admin');
function finance_table_ready($conn) {
  $r = $conn->query("SHOW TABLES LIKE 'worker_finance_entries'");
  return $r && $r->num_rows > 0;
}
if (!finance_table_ready($conn)) {
  $title='Finance & Worker Payments'; require '../layout.php';
  echo '<h1>Finance &amp; Worker Payments</h1><div class="card"><b>Database setup required</b><p>Import <code>migration_finance_and_worker_location.sql</code> in your ChoreHive database, then reload this page.</p></div>';
  require '../footer.php'; exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_entry'])) {
  verify_csrf();
  $workerId = !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : 0;
  $type = $_POST['entry_type'] ?? '';
  if (!in_array($type,['payout','bonus','deduction','discount','expense'],true)) fail('Choose a valid financial entry type.');
  $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
  if ($amount === false || $amount <= 0 || $amount > 10000000) fail('Enter an amount greater than zero.');
  $note = v_str('note',255,true,'Description');
  $bookingId = !empty($_POST['booking_id']) ? filter_var($_POST['booking_id'], FILTER_VALIDATE_INT) : null;
  if ($bookingId === false || ($bookingId !== null && $bookingId < 1)) fail('Choose a valid booking ID or leave it blank.');
  if ($bookingId !== null) { $bookingCheck=$conn->prepare('SELECT id FROM bookings WHERE id=?'); $bookingCheck->bind_param('i',$bookingId); $bookingCheck->execute(); if (!$bookingCheck->get_result()->fetch_assoc()) fail('The linked booking was not found.'); }
  if ($type !== 'expense' && $workerId < 1) fail('Choose a worker for payouts and worker adjustments.');
  if ($workerId > 0) { $check=$conn->prepare("SELECT id FROM users WHERE id=? AND role='worker'"); $check->bind_param('i',$workerId); $check->execute(); if (!$check->get_result()->fetch_assoc()) fail('Worker not found.'); }
  else $workerId = null;
  $st=$conn->prepare("INSERT INTO worker_finance_entries (worker_id,booking_id,entry_type,amount,note,created_by) VALUES (?,?,?,?,?,?)");
  $st->bind_param('iisdsi',$workerId,$bookingId,$type,$amount,$note,$u['id']); $st->execute();
  audit_log('finance_entry_added', "Admin {$u['name']} recorded $type of PHP ".number_format($amount,2)." for worker #$workerId");
  flash('Financial entry recorded.');
  header('Location: finance.php'); exit;
}
$title='Finance & Worker Payments'; require '../layout.php';
$workers=$conn->query("SELECT id,name FROM users WHERE role='worker' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$selected=(int)($_GET['worker_id'] ?? 0);
$where=$selected>0 ? " WHERE e.worker_id=".$selected : "";
$rows=$conn->query("SELECT e.*,w.name worker FROM worker_finance_entries e LEFT JOIN users w ON w.id=e.worker_id $where ORDER BY e.created_at DESC LIMIT 100");
$gross=$conn->query("SELECT COALESCE(SUM(total_price),0) v FROM bookings WHERE status='completed' AND payment_status='paid'")->fetch_assoc()['v'];
$expenses=$conn->query("SELECT COALESCE(SUM(amount),0) v FROM worker_finance_entries WHERE entry_type='expense'")->fetch_assoc()['v'];
$payouts=$conn->query("SELECT COALESCE(SUM(amount),0) v FROM worker_finance_entries WHERE entry_type='payout'")->fetch_assoc()['v'];
$daily=[]; $q=$conn->query("SELECT d.day, d.income, COALESCE(x.expenses,0) expenses FROM
 (SELECT DATE(schedule) day, SUM(total_price) income FROM bookings WHERE status='completed' AND payment_status='paid' AND schedule >= DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY DATE(schedule)) d
 LEFT JOIN (SELECT DATE(created_at) day, SUM(amount) expenses FROM worker_finance_entries WHERE entry_type='expense' AND created_at >= DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY DATE(created_at)) x ON x.day=d.day
 UNION
 SELECT x.day,0 income,x.expenses FROM
 (SELECT DATE(created_at) day,SUM(amount) expenses FROM worker_finance_entries WHERE entry_type='expense' AND created_at >= DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY DATE(created_at)) x
 LEFT JOIN (SELECT DATE(schedule) day FROM bookings WHERE status='completed' AND payment_status='paid' GROUP BY DATE(schedule)) d ON d.day=x.day WHERE d.day IS NULL
 ORDER BY day");
while($r=$q->fetch_assoc()) $daily[]=$r;
?>
<h1>Finance &amp; Worker Payments</h1>
<p class="muted">Recorded paid completed bookings are shown as gross booking income. Expenses and worker payouts are entered manually; demo payments are excluded. This is an operational ledger, not an accounting/tax statement.</p>
<div class="grid">
 <div class="stat"><b>₱<?=number_format((float)$gross,2)?></b>Recorded paid booking income</div>
 <div class="stat"><b>₱<?=number_format((float)$expenses,2)?></b>Recorded expenses</div>
 <div class="stat"><b>₱<?=number_format((float)$payouts,2)?></b>Worker payouts recorded</div>
</div>
<div class="card" style="margin-top:16px">
 <h2>Daily income vs expenses (last 14 days)</h2>
 <canvas id="financeChart" height="100" aria-label="Daily income and expense line graph"></canvas>
</div>
<div class="card">
 <h2>Record worker payment or adjustment</h2>
 <p class="muted">Use payout for money actually paid to a worker; bonus for an increase; deduction or discount for an agreed reduction; expense for business costs. Enter positive amounts only.</p>
 <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
  <?=csrf_field()?>
  <label>Worker (required except for business expenses)<select name="worker_id"><option value="">General expense / no worker</option><?php foreach($workers as $w):?><option value="<?=$w['id']?>"><?=e($w['name'])?></option><?php endforeach;?></select></label>
  <label>Entry type<select name="entry_type" required><option value="payout">Worker payout</option><option value="bonus">Bonus / raise</option><option value="deduction">Deduction</option><option value="discount">Discount</option><option value="expense">Business expense (worker optional)</option></select></label>
  <label>Amount (PHP)<input type="number" name="amount" min="0.01" max="10000000" step="0.01" required></label>
  <label>Booking ID (optional)<input type="number" name="booking_id" min="1" placeholder="e.g. 42"></label>
  <label style="grid-column:1/-1">Description<input name="note" maxlength="255" required placeholder="e.g. payout for completed booking #42"></label>
  <div><button class="btn" name="add_entry" value="1">Save financial entry</button></div>
 </form>
</div>
<div class="card">
 <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"><h2>Financial ledger</h2>
 <form method="get"><select name="worker_id" onchange="this.form.submit()"><option value="0">All workers</option><?php foreach($workers as $w):?><option value="<?=$w['id']?>" <?=$selected===(int)$w['id']?'selected':''?>><?=e($w['name'])?></option><?php endforeach;?></select></form></div>
 <div class="table-wrap"><table><tr><th>Date</th><th>Worker</th><th>Type</th><th>Amount</th><th>Description</th><th>Booking</th></tr>
 <?php if(!$rows->num_rows):?><tr><td colspan="6" class="muted">No financial entries recorded yet.</td></tr><?php endif;?>
 <?php while($r=$rows->fetch_assoc()):?><tr><td><?=e(date('M j, Y',strtotime($r['created_at'])))?></td><td><?=e($r['worker'] ?: 'General / business expense')?></td><td><?=e(ucfirst($r['entry_type']))?></td><td>₱<?=number_format((float)$r['amount'],2)?></td><td><?=e($r['note'])?></td><td><?= $r['booking_id'] ? '#'.(int)$r['booking_id'] : '—'?></td></tr><?php endwhile;?>
 </table></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const financeRows=<?=json_encode($daily,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
if(window.Chart){new Chart(document.getElementById('financeChart'),{type:'line',data:{labels:financeRows.map(r=>r.day),datasets:[{label:'Recorded income (₱)',data:financeRows.map(r=>Number(r.income)),tension:.25},{label:'Recorded expenses (₱)',data:financeRows.map(r=>Number(r.expenses)),tension:.25}]},options:{responsive:true,interaction:{mode:'index',intersect:false},scales:{y:{beginAtZero:true}}}});}
</script>
<?php require '../footer.php'; ?>
