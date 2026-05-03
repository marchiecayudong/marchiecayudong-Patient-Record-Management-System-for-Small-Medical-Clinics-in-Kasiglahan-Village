<?php
$pageTitle = 'QR Scanner';
$active = 'qr';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="qr-box" style="max-width:520px">
  <h2>Scan Patient QR Code</h2>
  <p style="color:#6b7280;font-size:13px;margin-bottom:14px">Allow camera access to scan a patient's QR code. The system will open the record instantly.</p>
  <div id="reader" style="width:100%"></div>
  <p id="result" style="margin-top:14px;font-size:13px;color:#0f5132"></p>
</div>

<script src="https://unpkg.com/html5-qrcode" defer></script>
<script defer>
window.addEventListener('load', () => {
  const result = document.getElementById('result');
  const scanner = new Html5Qrcode("reader");
  Html5Qrcode.getCameras().then(cams => {
    if (!cams.length) { result.textContent = 'No camera found.'; return; }
    scanner.start(cams[0].id, { fps: 10, qrbox: 240 }, (txt) => {
      result.innerHTML = 'Detected: opening record…';
      scanner.stop().then(() => { window.location.href = txt; });
    });
  }).catch(e => result.textContent = 'Camera error: ' + e);
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
