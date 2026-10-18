<?php
require '../config.php';
$u=require_role('admin');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT) ?: 0;
$st=$conn->prepare("SELECT b.id,b.address,b.schedule,c.name client,w.name worker,s.name service FROM bookings b JOIN users c ON c.id=b.client_id LEFT JOIN users w ON w.id=b.worker_id JOIN services s ON s.id=b.service_id WHERE b.id=?");
$st->bind_param('i',$id);$st->execute();$booking=$st->get_result()->fetch_assoc();
if(!$booking) {
  http_response_code(404);
  $title='Booking not found'; require '../layout.php';
  echo '<div class="card"><h1>Booking not found</h1><p class="muted">The booking may have been deleted or the ID is invalid.</p><a class="btn btn-sec" href="bookings.php">Back to bookings</a></div>';
  require '../footer.php'; exit;
}
$title='Booking Photos';require '../layout.php';
?>
<h1>Before &amp; After Photos — Booking #<?= (int)$id ?></h1>
<div class="card"><p><b><?=e($booking['client'])?></b> · <?=e($booking['service'])?></p><p class="muted"><?=e($booking['address'])?> · <?=e(date('M j, Y g:i A',strtotime($booking['schedule'])))?></p></div>
<?php foreach(['before'=>'Before photos','after'=>'After photos'] as $stage=>$label): $q=$conn->prepare("SELECT id,filepath,uploaded_at FROM booking_photos WHERE booking_id=? AND stage=? ORDER BY uploaded_at");$q->bind_param('is',$id,$stage);$q->execute();$items=$q->get_result(); ?>
<div class="card"><h2><?=e($label)?></h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px">
<?php if(!$items->num_rows):?><p class="muted">No <?=e($stage)?> photos uploaded.</p><?php endif;?>
<?php while($photo=$items->fetch_assoc()):?><a href="../photo.php?id=<?=(int)$photo['id']?>" target="_blank" rel="noopener" style="display:block"><img src="../photo.php?id=<?=(int)$photo['id']?>" alt="<?=e($label)?>" style="width:100%;height:180px;object-fit:cover;border-radius:12px;border:1px solid var(--line)"><small>Uploaded <?=e(date('M j, Y g:i A',strtotime($photo['uploaded_at'])))?> · Open full size</small></a><?php endwhile;?>
</div></div><?php endforeach;?>
<?php require '../footer.php'; ?>
