</div>
<div class="ch-confirm-overlay" id="chConfirmModal" role="presentation" aria-hidden="true">
  <div class="ch-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="chConfirmTitle" aria-describedby="chConfirmMessage">
    <span class="ch-confirm-icon" aria-hidden="true"><i class="bi bi-question-lg"></i></span>
    <h3 class="ch-confirm-title" id="chConfirmTitle">Please confirm</h3>
    <p class="ch-confirm-message" id="chConfirmMessage"></p>
    <div class="ch-confirm-actions">
      <button type="button" class="ch-confirm-btn ch-confirm-no" id="chConfirmNo">No</button>
      <button type="button" class="ch-confirm-btn ch-confirm-yes" id="chConfirmYes">Yes</button>
    </div>
  </div>
</div>
<script>
/* Global Facebook-style confirmation dialog.
   Usage:
     - <a data-confirm="Are you sure?" href="...">…</a>
     - <form data-confirm="Delete this?" method="post">…</form>
     - <button data-confirm="Remove photo?">…</button>   (works inside a form too)
     - JS: chConfirm({ title, message, yes, no, danger }).then(ok => { if (ok) … });
   Optional attributes: data-confirm-title, data-confirm-yes, data-confirm-no,
   data-confirm-danger, data-confirm-safe. */
(function () {
  var modal = document.getElementById('chConfirmModal');
  if (!modal) return;
  var titleEl = document.getElementById('chConfirmTitle');
  var msgEl = document.getElementById('chConfirmMessage');
  var yesBtn = document.getElementById('chConfirmYes');
  var noBtn = document.getElementById('chConfirmNo');
  var lastFocus = null;
  var resolver = null;

  function pretty(raw) {
    return String(raw == null ? '' : raw).replace(/\\n/g, '\n').trim();
  }
  function looksDangerous(text) {
    return /(delete|remove|clear|purge|cancel|reject|decline|revoke|unassign|permanent|discard|log ?out)/i.test(text);
  }

  function open(opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      resolver = resolve;
      titleEl.textContent = opts.title || 'Please confirm';
      msgEl.textContent = pretty(opts.message);
      yesBtn.textContent = opts.yes || 'Yes';
      noBtn.textContent = opts.no || 'No';
      var danger = opts.danger === undefined ? looksDangerous(msgEl.textContent) : !!opts.danger;
      yesBtn.classList.toggle('is-danger', danger);
      lastFocus = document.activeElement;
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('ch-confirm-open');
      (danger ? noBtn : yesBtn).focus();
    });
  }
  function close(result) {
    if (!modal.classList.contains('open')) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('ch-confirm-open');
    if (lastFocus && typeof lastFocus.focus === 'function') { try { lastFocus.focus(); } catch (e) {} }
    var r = resolver; resolver = null;
    if (r) r(result);
  }

  yesBtn.addEventListener('click', function () { close(true); });
  noBtn.addEventListener('click', function () { close(false); });
  modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(false); });
  document.addEventListener('keydown', function (e) {
    if (!modal.classList.contains('open')) return;
    if (e.key === 'Escape') { e.preventDefault(); close(false); }
    else if (e.key === 'Enter') {
      e.preventDefault();
      if (document.activeElement === yesBtn) close(true);
      else if (document.activeElement === noBtn) close(false);
      else close(!yesBtn.classList.contains('is-danger'));
    }
    else if (e.key === 'Tab') {
      var order = [noBtn, yesBtn];
      var i = order.indexOf(document.activeElement);
      e.preventDefault();
      order[e.shiftKey ? (i <= 0 ? order.length - 1 : i - 1) : (i === -1 || i === order.length - 1 ? 0 : i + 1)].focus();
    }
  });

  window.chConfirm = function (opts) {
    return open(typeof opts === 'string' ? { message: opts } : opts);
  };

  function readOpts(el) {
    return {
      title: el.getAttribute('data-confirm-title') || undefined,
      message: el.getAttribute('data-confirm') || '',
      yes: el.getAttribute('data-confirm-yes') || undefined,
      no: el.getAttribute('data-confirm-no') || undefined,
      danger: el.hasAttribute('data-confirm-danger') ? true : (el.hasAttribute('data-confirm-safe') ? false : undefined)
    };
  }

  // Clicks on links / buttons that carry data-confirm.
  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (!el) return;
    // Form-level confirmations are handled by the submit listener below.
    if (el.tagName === 'FORM') return;
    if (el.getAttribute('data-confirm-on') === 'form') return;
    if (el.dataset.chConfirmed === '1') { el.dataset.chConfirmed = ''; return; }
    var tag = el.tagName;
    var isLink = tag === 'A';
    var form = el.form || (tag === 'BUTTON' ? el.closest('form') : null);
    var isSubmit = el.type === 'submit' || (tag === 'BUTTON' && form && (el.type === '' || el.type === 'submit'));
    e.preventDefault();
    e.stopImmediatePropagation();
    open(readOpts(el)).then(function (ok) {
      if (!ok) return;
      if (isLink) {
        if (el.target && el.target !== '_self') window.open(el.href, el.target);
        else window.location.href = el.href;
      } else if (isSubmit && form) {
        if (form.requestSubmit) form.requestSubmit(el); else form.submit();
      } else {
        el.dataset.chConfirmed = '1';
        el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }
    });
  }, true);

  // Form-level confirmations (data-confirm sits on the <form>).
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches || !form.matches('form[data-confirm]')) return;
    if (form.dataset.chConfirmed === '1') { form.dataset.chConfirmed = ''; return; }
    e.preventDefault();
    e.stopImmediatePropagation();
    open(readOpts(form)).then(function (ok) {
      if (!ok) return;
      form.dataset.chConfirmed = '1';
      var submitter = e.submitter || form.querySelector('button[type=submit], button:not([type]), input[type=submit]');
      if (form.requestSubmit) form.requestSubmit(submitter || undefined); else form.submit();
    });
  }, true);
})();
</script>
<div id="profileImageViewer" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Profile picture viewer">
  <figure class="profile-showcase-content">
    <button type="button" class="profile-viewer-close" aria-label="Close profile picture viewer">×</button>
    <img id="profileImageViewerImage" alt="Enlarged profile picture">
    <figcaption class="profile-showcase-caption"><b id="profileImageViewerTitle">Profile picture</b><span>Use the controls to zoom in or out.</span><div class="profile-viewer-controls"><button type="button" data-profile-zoom="out" aria-label="Zoom out">−</button><button type="button" data-profile-zoom="reset">Reset</button><button type="button" data-profile-zoom="in" aria-label="Zoom in">+</button></div></figcaption>
  </figure>
</div>
<script>
(function(){var viewer=document.getElementById('profileImageViewer'),image=document.getElementById('profileImageViewerImage'),title=document.getElementById('profileImageViewerTitle'),close=viewer&&viewer.querySelector('.profile-viewer-close'),scale=1,last=null;
if(!viewer||!image)return;function shut(){viewer.classList.remove('is-open');viewer.setAttribute('aria-hidden','true');document.body.classList.remove('profile-showcase-open');image.removeAttribute('src');scale=1;image.style.transform='scale(1)';if(last&&last.focus)last.focus();}
function open(img){last=img;image.src=img.currentSrc||img.src;image.alt=img.alt||'Enlarged profile picture';title.textContent=(img.alt||'Profile picture').replace(/\s*profile photo\s*/i,'').replace(/\s*profile picture\s*/i,'')||'Profile picture';scale=1;image.style.transform='scale(1)';viewer.classList.add('is-open');viewer.setAttribute('aria-hidden','false');document.body.classList.add('profile-showcase-open');close.focus();}
document.addEventListener('click',function(e){var trigger=e.target.closest&&e.target.closest('.profile-zoom-trigger');if(trigger){e.preventDefault();open(trigger);return;}if(e.target===viewer||e.target===close){shut();return;}var z=e.target.closest&&e.target.closest('[data-profile-zoom]');if(z){var action=z.getAttribute('data-profile-zoom');scale=action==='in'?Math.min(3,scale+.25):action==='out'?Math.max(.5,scale-.25):1;image.style.transform='scale('+scale+')';}});
document.addEventListener('keydown',function(e){if(viewer.getAttribute('aria-hidden')==='true')return;if(e.key==='Escape'){shut();}if((e.key==='Enter'||e.key===' ')&&e.target.classList&&e.target.classList.contains('profile-zoom-trigger')){e.preventDefault();open(e.target);}});
})();
</script>
</body>
</html>
