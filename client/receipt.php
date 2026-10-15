<?php
require '../config.php';
$u = require_role('client');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $conn->prepare("SELECT b.*, s.name service, c.name client_name FROM bookings b JOIN services s ON s.id=b.service_id JOIN users c ON c.id=b.client_id WHERE b.id=? AND b.client_id=?");
$stmt->bind_param('ii', $id, $u['id']);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
if (!$b) {
    http_response_code(404);
    $title = 'Receipt not found';
    require '../layout.php';
    echo '<div class="card"><h1>Receipt not found</h1><p class="muted">This receipt is not available on your account.</p><a class="btn" href="bookings.php">Back to bookings</a></div>';
    require '../footer.php';
    exit;
}
if (!in_array($b['payment_status'], ['paid','demo_paid'], true) || empty($b['payment_reference'])) {
    flash('A receipt is available after payment is recorded.', 'error');
    header('Location: booking.php?id=' . $id);
    exit;
}
$title = 'Receipt for Booking #' . $id;
require '../layout.php';
$isDemo = $b['payment_status'] === 'demo_paid';
$methodLabel = $isDemo
    ? ucfirst($b['payment_method']) . ' (simulation only)'
    : (['cash' => 'Cash (customer-confirmed)', 'gcash' => 'GCash (PayMongo)', 'maya' => 'Maya (PayMongo)'][$b['payment_method']] ?? ucfirst($b['payment_method']));
$paymentDate = $b['paid_at'] ? date('M j, Y g:i A', strtotime($b['paid_at'])) : 'Not recorded';
$note = $isDemo
    ? 'SIMULATION ONLY — no money was transferred. This is a test record, not a real payment receipt.'
    : ($b['payment_method'] === 'cash'
    ? 'Cash payment was marked as confirmed by the customer after the job was completed.'
    : 'This receipt records a GCash or Maya payment processed through PayMongo. Keep the reference for your records.');
?>
<section class="page-intro receipt-page-intro">
  <span class="eyebrow">PAYMENT RECORD</span>
  <h1><?= $isDemo ? 'Payment simulation record' : 'Your receipt' ?><span class="heading-period">.</span></h1>
  <p><?= $isDemo ? 'This simulation record is for testing only; no money was transferred.' : 'Print this record or save a PNG copy for your booking records.' ?></p>
</section>
<div class="receipt-actions no-print">
  <a class="btn btn-sec" href="booking.php?id=<?= (int)$id ?>">← Back to booking</a>
  <button type="button" class="btn btn-outline" id="printReceipt"><?= $isDemo ? 'Print simulation record' : 'Print receipt' ?></button>
  <button type="button" class="btn" id="saveReceiptPng"><?= $isDemo ? 'Save record as PNG' : 'Save as PNG' ?></button>
</div>
<section class="receipt-paper" id="receiptPaper" aria-label="<?= $isDemo ? 'ChoreHive payment simulation record' : 'ChoreHive payment receipt' ?>">
  <div class="receipt-topline"><div class="receipt-brand-mark">CH</div><div><div class="receipt-brand">ChoreHive</div><div class="receipt-brand-subtitle">CLEANING SERVICE MARKETPLACE</div></div><span class="receipt-paid-pill"><?= $isDemo ? 'DEMO ONLY' : 'PAID' ?></span></div>
  <div class="receipt-heading"><div><span class="receipt-kicker"><?= $isDemo ? 'SIMULATION RECORD — NOT A RECEIPT' : 'PAYMENT RECEIPT' ?></span><h2><?= $isDemo ? 'No money was transferred.' : 'Thank you for your booking.' ?></h2><p>Keep this reference for your booking records.</p></div><div class="receipt-ref-block"><small><?= $isDemo ? 'DEMO REFERENCE' : 'RECEIPT REFERENCE' ?></small><b><?= e($b['payment_reference']) ?></b></div></div>
  <div class="receipt-amount-block"><span>Amount recorded</span><strong>₱<?= number_format((float)$b['total_price'], 2) ?></strong><small><?= e($methodLabel) ?></small></div>
  <div class="receipt-data">
    <div class="receipt-data-row"><span class="receipt-label"><?= $isDemo ? 'Demo reference' : 'Receipt reference' ?></span><b class="receipt-value"><?= e($b['payment_reference']) ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Booking number</span><b class="receipt-value">#<?= (int)$b['id'] ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Customer</span><b class="receipt-value"><?= e($b['client_name']) ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Cleaning service</span><b class="receipt-value"><?= e($b['service']) ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Service address</span><b class="receipt-value"><?= e($b['address']) ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Scheduled service</span><b class="receipt-value"><?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Payment method</span><b class="receipt-value"><?= e($methodLabel) ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Payment date</span><b class="receipt-value"><?= e($paymentDate) ?></b></div>
    <div class="receipt-data-row"><span class="receipt-label">Payment status</span><b class="receipt-value"><?= $isDemo ? 'SIMULATED — NO MONEY TRANSFERRED' : 'Paid' ?></b></div>
  </div>
  <p class="receipt-legal-note"><?= e($note) ?></p>
  <div class="receipt-foot"><span>ChoreHive · Booking #<?= (int)$b['id'] ?></span><span>Generated <?= date('M j, Y') ?></span></div>
</section>
<div id="receiptDownloadStatus" class="receipt-download-status" role="status" aria-live="polite"></div>
<script>
(()=>{
 const printBtn=document.getElementById('printReceipt'),saveBtn=document.getElementById('saveReceiptPng'),paper=document.getElementById('receiptPaper'),status=document.getElementById('receiptDownloadStatus');
 const saveLabel=<?= json_encode($isDemo ? 'Save record as PNG' : 'Save as PNG') ?>;
 printBtn.addEventListener('click',()=>window.print());
 function wrapText(ctx,text,x,y,maxWidth,lineHeight){const words=String(text||'—').split(/\s+/);let line='';for(const word of words){const test=line?line+' '+word:word;if(ctx.measureText(test).width>maxWidth&&line){ctx.fillText(line,x,y);line=word;y+=lineHeight;}else{line=test;}}if(line)ctx.fillText(line,x,y);return y+lineHeight;}
 saveBtn.addEventListener('click',()=>{
   saveBtn.disabled=true;saveBtn.textContent='Preparing PNG…';status.textContent='Preparing your payment record image…';
   try{
     const rows=[...paper.querySelectorAll('.receipt-data-row')].map(row=>({label:row.querySelector('.receipt-label')?.innerText.trim()||'',value:row.querySelector('.receipt-value')?.innerText.trim()||''}));
     const getText=sel=>paper.querySelector(sel)?.innerText.trim()||'';
     const note=getText('.receipt-legal-note'),pill=getText('.receipt-paid-pill'),kicker=getText('.receipt-kicker'),headline=getText('.receipt-heading h2'),subline=getText('.receipt-heading p');const w=1100,pad=76,lineH=34;const canvas=document.createElement('canvas');canvas.width=w;canvas.height=1120;const ctx=canvas.getContext('2d');
     ctx.fillStyle='#f3f7f4';ctx.fillRect(0,0,w,canvas.height);ctx.fillStyle='#ffffff';ctx.fillRect(34,34,w-68,canvas.height-68);
     ctx.fillStyle='#17427a';ctx.fillRect(34,34,w-68,12);
     ctx.fillStyle='#dca935';ctx.beginPath();ctx.arc(pad+22,108,28,0,Math.PI*2);ctx.fill();ctx.fillStyle='#183657';ctx.font='bold 23px Arial';ctx.textAlign='center';ctx.fillText('CH',pad+22,116);ctx.textAlign='left';ctx.fillStyle='#183657';ctx.font='bold 30px Arial';ctx.fillText('ChoreHive',pad+66,105);ctx.fillStyle='#78869a';ctx.font='14px Arial';ctx.fillText('CLEANING SERVICE MARKETPLACE',pad+66,130);ctx.fillStyle='#e2f5e8';ctx.fillRect(w-pad-125,82,125,36);ctx.fillStyle='#1e7749';ctx.font='bold 15px Arial';ctx.fillText(pill,w-pad-115,105);
     ctx.strokeStyle='#e7edf2';ctx.beginPath();ctx.moveTo(pad,165);ctx.lineTo(w-pad,165);ctx.stroke();ctx.fillStyle='#8794a5';ctx.font='bold 13px Arial';ctx.fillText(kicker,pad,205);ctx.fillStyle='#183657';ctx.font='bold 25px Arial';ctx.fillText(headline,pad,246);ctx.fillStyle='#77859a';ctx.font='16px Arial';ctx.fillText(subline,pad,274);
     ctx.fillStyle='#f2f8f3';ctx.fillRect(pad,305,w-pad*2,108);ctx.fillStyle='#718198';ctx.font='15px Arial';ctx.fillText('AMOUNT RECORDED',pad+24,338);ctx.fillStyle='#1d7748';ctx.font='bold 37px Arial';ctx.fillText(getText('.receipt-amount-block strong'),pad+24,382);ctx.fillStyle='#5e7188';ctx.font='14px Arial';ctx.fillText(getText('.receipt-amount-block small'),w-pad-350,382);
     let y=456;ctx.font='15px Arial';rows.forEach((row)=>{ctx.fillStyle='#7b899b';ctx.font='15px Arial';ctx.fillText(row.label,pad,y);ctx.fillStyle='#263d58';ctx.font='bold 16px Arial';const valueY=wrapText(ctx,row.value,w-pad-450,y,430,23);ctx.strokeStyle='#e7edf2';ctx.beginPath();ctx.moveTo(pad,y+17);ctx.lineTo(w-pad,y+17);ctx.stroke();y=Math.max(y+lineH,valueY+2);});
     y+=13;ctx.fillStyle='#fff6df';const noteLines=Math.max(1,Math.ceil(note.length/105));const noteHeight=Math.min(135,28+noteLines*22);ctx.fillRect(pad,y,w-pad*2,noteHeight);ctx.fillStyle='#755a1b';ctx.font='14px Arial';wrapText(ctx,note,pad+18,y+25,w-pad*2-36,20);ctx.fillStyle='#8793a4';ctx.font='13px Arial';ctx.fillText('ChoreHive · Booking #<?= (int)$id ?>',pad,canvas.height-64);ctx.textAlign='right';ctx.fillText('Generated <?= e(date('M j, Y')) ?>',w-pad,canvas.height-64);ctx.textAlign='left';
     canvas.toBlob(blob=>{if(!blob){status.textContent='Your browser could not create the PNG. Use Print and choose Save as PDF.';return;}const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='ChoreHive-<?= $isDemo ? 'Demo-Record' : 'Receipt' ?>-<?= (int)$id ?>.png';document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1500);status.textContent='Payment record PNG saved.';},'image/png');
   }catch(err){status.textContent='Could not create the PNG. Use Print and choose Save as PDF.';}
   finally{setTimeout(()=>{saveBtn.disabled=false;saveBtn.textContent=saveLabel;},500);}
 });
})();
</script>
<?php require '../footer.php'; ?>
